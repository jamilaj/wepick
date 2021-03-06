/**
 * Folder tree for WP Media Folder
 */
let wpmfFoldersTreeModule;
let cloud_sync_tree_icon;
(function ($) {
    cloud_sync_tree_icon = `<span title="${wpmf.l18n.hover_cloud_syncing}" class="wpmf-loading-sync"><svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" class="lds-dual-ring" style="
    height: 14px;
    width: 14px;
    vertical-align: sub;
"><circle cx="50" cy="50" ng-attr-r="{{config.radius}}" ng-attr-stroke-width="{{config.width}}" ng-attr-stroke="{{config.stroke}}" ng-attr-stroke-dasharray="{{config.dasharray}}" fill="none" stroke-linecap="round" r="40" stroke-width="12" stroke="#2196f3" stroke-dasharray="62.83185307179586 62.83185307179586" transform="rotate(53.6184 50 50)"><animateTransform attributeName="transform" type="rotate" calcMode="linear" values="0 50 50;360 50 50" keyTimes="0;1" dur="1s" begin="0s" repeatCount="indefinite"></animateTransform></circle></svg></span>`;
    wpmfFoldersTreeModule = {
        categories: [], // categories
        folders_states: [], // Contains open or closed status of folders
        show_files_count: false, // Whether or not show number of files contained in a folder
        last_scrolling_state: '', // Save the positioning while scrolling

        /**
         * Retrieve the Jquery tree view element
         * of the current frame
         * @return jQuery
         */
        getTreeElement: function () {
            return wpmfFoldersModule.getFrame().find('.wpmf-folder-tree').first();
        },

        /**
         * Initialize module related things
         */
        initModule: function ($current_frame) {
            // Check if this frame has already the tree view
            let is_initialized = false;
            if (wpmfFoldersModule.page_type === 'upload-list') {
                is_initialized = $current_frame.find('.wpmf-folder-tree').length > 0;
            } else if ($current_frame.hasClass('hide-menu')) {
                is_initialized = $current_frame.find('.attachments-browser .wpmf-folder-tree').length > 0;
            } else {
                is_initialized = $current_frame.find('.media-menu .wpmf-folder-tree').length > 0;
            }

            if (is_initialized) {
                // Show folder tree in case it has been hidden previously
                wpmfFoldersTreeModule.getTreeElement().show();
                return;
            }

            // Initialize some variables
            wpmfFoldersTreeModule.show_files_count = wpmf.vars.option_countfiles === 1;

            // Import categories from wpmf main module
            wpmfFoldersTreeModule.importCategories();

            if (wpmfFoldersModule.page_type === 'upload-list') {
                // Wrap content
                $current_frame.children('ul.attachments, .screen-reader-text:nth-of-type(2), .tablenav.top, .wp-list-table, .tablenav.bottom').wrapAll('<div id="wpmf-wrapper"></div>');

                // Add the tree view to the main content
                $('<div class="wpmf-folder-tree"></div>').insertBefore($('#wpmf-wrapper'));
            } else if ($current_frame.hasClass('hide-menu')) {
                // Add the tree view to the main content
                $('<div class="wpmf-folder-tree"></div>').insertBefore($current_frame.find('.attachments-browser ul.attachments'));
            } else {
                // Add tree view to the left menu column
                const $menu = $current_frame.find('.media-frame-menu .media-menu');
                $('<div class="wpmf-folder-tree"></div>').appendTo($menu);
            }

            // Render the tree view
            wpmfFoldersTreeModule.loadTreeView();
            // Subscribe to the change folder event in main wpmf module
            wpmfFoldersModule.on('changeFolder', function (folder_id) {
                wpmfFoldersTreeModule.changeFolder(folder_id);
            });

            // Subscribe to the add folder event in main wpmf module
            wpmfFoldersModule.on(['addFolder', 'deleteFolder', 'updateFolder', 'moveFolder'], function (folder) {
                wpmfFoldersTreeModule.importCategories();
                wpmfFoldersTreeModule.loadTreeView();
                // Initialize folder tree resizing
                wpmfFoldersTreeModule.initContainerResizing($current_frame);
            });

            // Subscribe to the move file event in main wpmf module
            wpmfFoldersModule.on('moveFile', function (files_ids, folder_to_id, folder_from_id) {
                // Update file count in main wpmf Module
                if (folder_from_id !== 0) {
                    wpmfFoldersModule.categories[folder_from_id].files_count -= files_ids.length;
                }

                if (folder_to_id !== 0) {
                    wpmfFoldersModule.categories[folder_to_id].files_count += files_ids.length;
                }

                // Import categories with updated count
                wpmfFoldersTreeModule.importCategories();

                // Reload tree view
                wpmfFoldersTreeModule.loadTreeView();
            });

            // Initialize the fixed tree view position on scrolling
            if (wpmf.vars.wpmf_pagenow === 'upload.php') {
                wpmfFoldersTreeModule.initFixedScrolling($current_frame);
            }

            // Subscribe to ordering folder filter
            wpmfFoldersFiltersModule.on('foldersOrderChanged', function () {
                wpmfFoldersTreeModule.importCategories();
                wpmfFoldersTreeModule.loadTreeView();
            });

            // Subscribe to gallery editing to hide folder tree
            wpmfFoldersModule.on('wpGalleryEdition', function () {
                wpmfFoldersTreeModule.getTreeElement().hide();
            });

            wpmfFoldersTreeModule.initContainerResizing($current_frame);

        },

        /**
         * Import categories from wpmf main module
         */
        importCategories: function () {
            let folders_ordered = [];

            // Add each category
            $(wpmfFoldersModule.categories_order).each(function () {
                folders_ordered.push(wpmfFoldersModule.categories[this]);
            });

            // Order the array depending on main ordering
            switch (wpmfFoldersModule.folder_ordering) {
                default:
                case 'name-ASC':
                    folders_ordered = Object.values(folders_ordered).sort(function (a, b) {
                        if (a.id === 0) return -1; // Root folder is always first
                        if (b.id === 0) return 1; // Root folder is always first
                        return a.label.localeCompare(b.label);
                    });
                    break;
                case 'name-DESC':
                    folders_ordered = Object.values(folders_ordered).sort(function (a, b) {
                        if (a.id === 0) return -1; // Root folder is always first
                        if (b.id === 0) return 1; // Root folder is always first
                        return b.label.localeCompare(a.label);
                    });
                    break;
                case 'id-ASC':
                    folders_ordered = Object.values(folders_ordered).sort(function (a, b) {
                        if (a.id === 0) return -1; // Root folder is always first
                        if (b.id === 0) return 1; // Root folder is always first
                        return a.id - b.id;
                    });
                    break;
                case 'id-DESC':
                    folders_ordered = Object.values(folders_ordered).sort(function (a, b) {
                        if (a.id === 0) return -1; // Root folder is always first
                        if (b.id === 0) return 1; // Root folder is always first
                        return b.id - a.id;
                    });
                    break;
                case 'custom':
                    folders_ordered = Object.values(folders_ordered).sort(function (a, b) {
                        if (a.id === 0) return -1; // Root folder is always first
                        if (b.id === 0) return 1; // Root folder is always first
                        return a.order - b.order;
                    });
                    break;
            }

            // Reorder array based on children
            let folders_ordered_deep = [];
            let processed_ids = [];
            const loadChildren = function (id) {
                if (processed_ids.indexOf(id) < 0) {
                    processed_ids.push(id);
                    for (let ij = 0; ij < folders_ordered.length; ij++) {
                        if (folders_ordered[ij].parent_id === id) {
                            folders_ordered_deep.push(folders_ordered[ij]);
                            loadChildren(folders_ordered[ij].id);
                        }
                    }
                }
            };
            loadChildren(parseInt(wpmf.vars.term_root_id));

            // Finally save it to the global var
            wpmfFoldersTreeModule.categories = folders_ordered_deep;

        },

        /**
         * Render tree view inside content
         */
        loadTreeView: function () {
            wpmfFoldersTreeModule.getTreeElement().html(wpmfFoldersTreeModule.getRendering());
            wpmfFoldersModule.openContextMenuFolder();
            let append_element;

            if (wpmfFoldersModule.page_type === 'upload-list') {
                append_element = '#posts-filter';
            } else {
                append_element = '.media-frame';
            }

            // Initialize dragping folder on tree view
            wpmfFoldersTreeModule.getTreeElement().find('ul li a[data-id]').draggable({
                revert: true,
                helper: function (ui) {
                    return $(ui.currentTarget).clone();
                },
                appendTo: append_element,
                delay: 100, // Prevent dragging when only trying to click
                distance: 10,
                cursorAt: {top: 0, left: 0},
                drag: function () {
                },
                start: function (event, ui) {
                    // Add the original size of element
                    $(ui.helper).css('width', $(ui.helper.context).outerWidth() + 'px');
                    $(ui.helper).css('height', $(ui.helper.context).outerWidth() + 'px');

                    // Add some style to original elements
                    $(this).addClass('wpmf-dragging');
                },
                stop: function (event, ui) {
                    // Revert style
                    $(this).removeClass('wpmf-dragging');
                }
            });

            // Initialize dropping folder on tree view
            wpmfFoldersTreeModule.getTreeElement().find('ul li a[data-id]').droppable({
                hoverClass: "wpmf-hover-folder",
                tolerance: 'pointer',
                drop: function (event, ui) {
                    event.stopPropagation();
                    if (($(ui.draggable).hasClass('attachment') && !$(ui.draggable).hasClass('wpmf-attachment')) || $(ui.draggable).hasClass('wpmf-move')) {
                        // Transfer the event to the wpmf main module
                        wpmfFoldersModule.droppedAttachment($(this).data('id'));
                    } else {
                        // move folder with folder tree
                        wpmfFoldersModule.moveFolder($(ui.draggable).data('id'), $(this).data('id'));
                    }
                }
            });

            // Initialize change keyword to search folder
            wpmfFoldersTreeModule.getTreeElement().find('.searchfolder').on('click', function (e) {
                wpmfFoldersTreeModule.doSearch();
            });

            // search with enter key
            $('.wpmf_search_folder').keypress(function (e) {
                if (e.which === 13) {
                    wpmfFoldersTreeModule.doSearch();
                    return false;
                }
            });

            // Initialize double click to folder title on tree view
            wpmfFoldersTreeModule.getTreeElement().find('ul a[data-id]').wpmfSingleDoubleClick(function () {
                // single click
                let id = $(this).data('id');
                if (parseInt(id) !== parseInt(wpmfFoldersModule.last_selected_folder)) {
                    if (!wpmfFoldersModule.getFrame().find('#wpmf-media-category').length) {
                        let bread = '';
                        wpmfFoldersTreeModule.changeFolder(id);
                        if (parseInt(id) === 0) {
                            bread = wpmfFoldersModule.getBreadcrumb(0);
                        } else {
                            bread = wpmfFoldersModule.getBreadcrumb(id);
                        }
                        $('.wpmf_msg_upload_folder span').html(bread);
                    } else {
                        wpmfFoldersModule.changeFolder(id);
                    }
                }
            }, function (e) {
                // double click
                let id = $(this).data('id');
                wpmfFoldersModule.clickEditFolder(e, id);
                wpmfFoldersModule.houtside();
            });

            wpmfFoldersTreeModule.getTreeElement().append('<div class="wpmf-all-tree"></div>');
            wpmfFoldersTreeModule.getTreeElement().find('.wpmf_media_library').appendTo(wpmfFoldersTreeModule.getTreeElement().find('.wpmf-all-tree'));
            if (parseInt(wpmf.vars.wpmf_addon_active) === 1) {
                if (!$('.wpmf-dropbox').length && $('.dropbox_list').length) {
                    wpmfFoldersTreeModule.getTreeElement().find('.wpmf-all-tree').append('<ul class="wpmf-dropbox"></ul>');
                    $('.dropbox_list').appendTo('.wpmf-dropbox');
                }

                if (!$('.wpmf-google').length && $('.google_drive_list').length) {
                    wpmfFoldersTreeModule.getTreeElement().find('.wpmf-all-tree').append('<ul class="wpmf-google"></ul>');
                    $('.google_drive_list').appendTo('.wpmf-google');
                }

                if (!$('.wpmf-onedrive').length && $('.onedrive_list').length) {
                    wpmfFoldersTreeModule.getTreeElement().find('.wpmf-all-tree').append('<ul class="wpmf-onedrive"></ul>');
                    $('.onedrive_list').appendTo('.wpmf-onedrive');
                }

                if (!$('.wpmf-onedrive-business').length && $('.onedrive_business_list').length) {
                    wpmfFoldersTreeModule.getTreeElement().find('.wpmf-all-tree').append('<ul class="wpmf-onedrive-business"></ul>');
                    $('.onedrive_business_list').appendTo('.wpmf-onedrive-business');
                }

                if ($('.dropbox_list').length || $('.google_drive_list').length || $('.onedrive_list').length || $('.onedrive_business_list').length) {
                    if (wpmf.vars.sync_method === 'ajax' && parseInt(wpmf.vars.sync_periodicity) !== 0 && wpmf.vars.cloudNameSyncing !== '') {
                        // add loader icon for cloud syncing
                        var cloud_name = wpmfAutoSyncClouds.vars.cloudNameSyncing;
                        if (!$('.' + cloud_name + '_list > a > .wpmf-loading-sync').length) {
                            $('.' + cloud_name + '_list > a').append(cloud_sync_tree_icon);
                        }
                    }
                }

                if ((wpmf.vars.sync_method === 'ajax') && parseInt(wpmf.vars.sync_periodicity) !== 0) {
                    // get cloud syncing
                    if (wpmf.vars.cloudNameSyncing !== '') {
                        // add loader icon for cloud syncing
                        setInterval(function () {
                            $.ajax({
                                method: "POST",
                                dataType: "json",
                                url: ajaxurl,
                                data: {
                                    action: 'wpmf_get_cloud_syncing',
                                    wpmf_nonce: wpmfAutoSyncClouds.vars.wpmf_nonce
                                },
                                success: function (res) {
                                    if (res.status) {
                                        $('.wpmf-loading-sync').remove();
                                        if (res.cloud !== '') {
                                            if (!$('.' + res.cloud + '_list > a > .wpmf-loading-sync').length) {
                                                $('.' + res.cloud + '_list > a').append(cloud_sync_icon);
                                            }
                                        }
                                    }
                                }
                            });
                        }, 30000);
                    }
                }
            }
        },

        /**
         *  Do search folder
         */
        doSearch: function () {
            wpmfFoldersModule.changeFolder(wpmfFoldersModule.last_selected_folder);
            // search on folder tree
            let keyword = $('.wpmf_search_folder').val().trim().toLowerCase();
            if (keyword !== '') {
                $('.wpmf-folder-tree li').addClass('folderhide');
                $.each(wpmfFoldersModule.folder_search, function (i, v) {
                    $('.wpmf-folder-tree li[data-id="' + v + '"]').addClass('foldershow').removeClass('folderhide closed');
                    $('.wpmf-folder-tree li[data-id="' + v + '"]').parents('.wpmf-folder-tree li').addClass('foldershow').removeClass('folderhide closed');
                });
            } else {
                $('.wpmf-folder-tree li').removeClass('foldershow folderhide');
            }
        },

        /**
         * Get the html resulting tree view
         * @return {string}
         */
        getRendering: function () {
            let ij = 0;
            let content = ''; // Final tree view cwpmf-folder-tree-resizeontent
            // render search folder box
            let search_folder = `
            <div class="wpmf-expandable-search mdl-cell--hide-phone">
                <form action="#">
                  <input type="text" class="wpmf_search_folder" placeholder="${wpmf.l18n.search_folder}" size="1">
                </form>
                <i class="material-icons searchfolder">search</i>
            </div>
            `;

            // get last status folder tree
            let lastStatusTree = wpmfFoldersModule.getCookie('lastStatusTree_' + wpmf.vars.site_url);
            if (lastStatusTree !== '') {
                lastStatusTree = JSON.parse(lastStatusTree);
            }

            /**
             * Recursively print list of folders
             * @return {boolean}
             */
            const generateList = function (tree_class = '') {
                content += '<ul class="' + tree_class + '">';

                while (ij < wpmfFoldersTreeModule.categories.length) {
                    let className = '';

                    // get color folder
                    let bgcolor = '', odvColor = '';
                    if (typeof wpmf.vars.colors !== 'undefined' && typeof wpmf.vars.colors[wpmfFoldersTreeModule.categories[ij].id] !== 'undefined' && wpmfFoldersModule.folder_design === 'material_design') {
                        bgcolor = 'color: ' + wpmf.vars.colors[wpmfFoldersTreeModule.categories[ij].id];
                        odvColor = wpmf.vars.colors[wpmfFoldersTreeModule.categories[ij].id];
                    } else {
                        bgcolor = 'color: #8f8f8f';
                        odvColor = '#8f8f8f';
                    }

                    let icondrive = '<i class="material-icons" style="' + bgcolor + '">folder</i>';

                    if (lastStatusTree.indexOf(wpmfFoldersTreeModule.categories[ij].id) !== -1) {
                        className += 'open ';
                    } else {
                        className += 'closed ';
                    }

                    let drive_root = false;

                    // get last access folder
                    let lastAccessFolder = wpmfFoldersModule.getCookie('lastAccessFolder_' + wpmf.vars.site_url);
                    // Select the last element which was selected in wpmf main module
                    if (typeof lastAccessFolder === "undefined" || (typeof lastAccessFolder !== "undefined" && lastAccessFolder === '') || (typeof lastAccessFolder !== "undefined" && parseInt(lastAccessFolder) === 0) || typeof wpmfFoldersModule.categories[lastAccessFolder] === "undefined") {
                        if (wpmfFoldersTreeModule.categories[ij].id === wpmfFoldersModule.last_selected_folder) {
                            className += 'selected ';
                        }
                    } else {
                        if (wpmfFoldersTreeModule.categories[ij].id === parseInt(lastAccessFolder)) {
                            className += 'selected ';
                        }
                    }
                    
                    if (parseInt(wpmf.vars.wpmf_addon_active) === 1) {
                        if (wpmfFoldersTreeModule.categories[ij].drive_type === 'onedrive_business') {
                            if (parseInt(wpmfFoldersTreeModule.categories[ij].parent_id) === 0) {
                                drive_root = true;
                                className += 'onedrive_business_list wpmf_drive_tree';
                                className = className.replace('closed', '');
                            }
                        }

                        if (wpmfFoldersTreeModule.categories[ij].drive_type === 'onedrive') {
                            if (parseInt(wpmfFoldersTreeModule.categories[ij].parent_id) === 0) {
                                drive_root = true;
                                className += 'onedrive_list wpmf_drive_tree';
                                className = className.replace('closed', '');
                            }
                        }

                        if (wpmfFoldersTreeModule.categories[ij].drive_type === 'onedrive' || wpmfFoldersTreeModule.categories[ij].drive_type === 'onedrive_business') {
                            icondrive = `<svg class="tree_drive_icon_img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60.43 35.95"><defs></defs><title>icon</title><path class="cls-1" d="M39.45,36.6H55.53a5.41,5.41,0,0,0,5.15-2.77c1.75-3.14,1.41-8.69-3.72-10.35-.55-.18-.91-.27-.93-1-.13-6.16-6.1-9.95-12.23-7.73a1.21,1.21,0,0,1-1.65-.47,10,10,0,0,0-8.49-4c-5.29.2-8.84,3.31-10.08,8.57a1.9,1.9,0,0,1-1.84,1.73c-3.41.53-6.06,2.74-6.43,5.52-.77,5.7,1.55,10.47,8.49,10.51C29,36.62,34.23,36.6,39.45,36.6Z" transform="translate(-1.2 -0.66)" style="fill:#fefefe"/><path class="cls-1" d="M14.58,34c-.23-.54-.4-.93-.55-1.31-2.29-5.83-.42-11.5,6.08-13.45a2.7,2.7,0,0,0,2.06-2.13,12.4,12.4,0,0,1,11.89-8.7,11,11,0,0,1,8.49,3.83c.35.4.66,1,1.4.6a6.16,6.16,0,0,1,2.49-.57c.92-.12,1.08-.45.85-1.31-1.52-5.74-5.24-9.23-11-10.15C31.12,0,26.9,2,24,6.43a1.12,1.12,0,0,1-1.72.47,8.52,8.52,0,0,0-5.6-.59C11.73,7.41,8.76,11,8.49,16.37c0,.9-.22,1.14-1.1,1.36A7.92,7.92,0,0,0,1.22,25,8.39,8.39,0,0,0,5.6,33C8.43,34.53,11.46,33.83,14.58,34Z" transform="translate(-1.2 -0.66)" style="fill: #fefefe"/><path class="cls-2" d="M39.45,36.6c-5.22,0-10.43,0-15.65,0-6.94,0-9.26-4.81-8.49-10.51.37-2.78,3-5,6.43-5.52a1.9,1.9,0,0,0,1.84-1.73c1.24-5.26,4.79-8.37,10.08-8.57a10,10,0,0,1,8.49,4,1.21,1.21,0,0,0,1.65.47c6.13-2.22,12.1,1.57,12.23,7.73,0,.72.38.81.93,1,5.13,1.66,5.47,7.21,3.72,10.35a5.41,5.41,0,0,1-5.15,2.77Z" transform="translate(-1.2 -0.66)" style="fill: ${odvColor}"/><path class="cls-2" d="M14.58,34c-3.12-.2-6.15.5-9-1.07a8.39,8.39,0,0,1-4.38-8,7.92,7.92,0,0,1,6.17-7.25c.88-.22,1.06-.46,1.1-1.36.27-5.35,3.24-9,8.17-10.06a8.52,8.52,0,0,1,5.6.59A1.12,1.12,0,0,0,24,6.43C26.9,2,31.12,0,36.28.84c5.77.92,9.49,4.41,11,10.15.23.86.07,1.19-.85,1.31a6.16,6.16,0,0,0-2.49.57c-.74.44-1.05-.2-1.4-.6a11,11,0,0,0-8.49-3.83,12.4,12.4,0,0,0-11.89,8.7,2.7,2.7,0,0,1-2.06,2.13c-6.5,1.95-8.37,7.62-6.08,13.45C14.18,33.1,14.35,33.49,14.58,34Z" transform="translate(-1.2 -0.66)" style="fill: ${odvColor}"/></svg>`;
                        }

                        if (wpmfFoldersTreeModule.categories[ij].drive_type === 'dropbox') {
                            if (parseInt(wpmfFoldersTreeModule.categories[ij].parent_id) === 0) {
                                drive_root = true;
                                className += 'dropbox_list wpmf_drive_tree';
                                className = className.replace('closed', '');
                            }

                            icondrive = '<i class="zmdi zmdi-dropbox tree_drive_icon" style="' + bgcolor + '"></i>';
                        }

                        if (wpmfFoldersTreeModule.categories[ij].drive_type === 'google_drive') {
                            if (parseInt(wpmfFoldersTreeModule.categories[ij].parent_id) === 0) {
                                drive_root = true;
                                className += 'google_drive_list wpmf_drive_tree';
                                className = className.replace('closed', '');
                            }

                            icondrive = '<i class="zmdi zmdi-google-drive tree_drive_icon" style="' + bgcolor + '"></i>';
                        }

                        if (typeof wpmfFoldersTreeModule.categories[ij].drive_type === 'undefined' || wpmfFoldersTreeModule.categories[ij].drive_type === '') {
                            className += ' wpmf_local_tree ';
                        }
                    }

                    // Open li tag
                    content += '<li class="' + className + '" data-id="' + wpmfFoldersTreeModule.categories[ij].id + '" >';

                    const a_tag = '<a data-id="' + wpmfFoldersTreeModule.categories[ij].id + '">';
                    if (wpmfFoldersTreeModule.categories[ij + 1] && wpmfFoldersTreeModule.categories[ij + 1].depth > wpmfFoldersTreeModule.categories[ij].depth) { // The next element is a sub folder
                        // Add folder icon
                        if (drive_root) {
                            content += a_tag;
                        } else {
                            content += '<a onclick="wpmfFoldersTreeModule.toggle(' + wpmfFoldersTreeModule.categories[ij].id + ')"><i class="material-icons wpmf-arrow">keyboard_arrow_down</i></a>';
                            content += a_tag;
                        }
                        content += icondrive;
                    } else {
                        content += a_tag;

                        // Add folder icon
                        if (drive_root) {
                            content += icondrive;
                        } else {
                            content += '<span class="wpmf-no-arrow">'+ icondrive +'</span>';
                        }
                    }

                    // Add current category name
                    if (wpmfFoldersTreeModule.categories[ij].id === 0) {
                        // If this is the root folder then rename it
                        content += wpmf.l18n.media_folder;
                    } else {
                        content += '<span>' + wpmfFoldersTreeModule.categories[ij].label + '</span>';
                    }

                    content += '</a>';

                    if (wpmfFoldersTreeModule.show_files_count && wpmfFoldersTreeModule.categories[ij].files_count !== undefined) {
                        content += '<span data-files-count="' + wpmfFoldersTreeModule.categories[ij].files_count + '"></span>';
                    }

                    // This is the end of the array
                    if (wpmfFoldersTreeModule.categories[ij + 1] === undefined) {
                        // Let's close all opened tags
                        for (let ik = wpmfFoldersTreeModule.categories[ij].depth; ik >= 0; ik--) {
                            content += '</li>';
                            content += '</ul>';
                        }

                        // We are at the end don't continue to process array
                        return false;
                    }


                    if (wpmfFoldersTreeModule.categories[ij + 1].depth > wpmfFoldersTreeModule.categories[ij].depth) { // The next element is a sub folder
                        // Recursively list it
                        ij++;
                        if (generateList() === false) {
                            // We have reached the end, let's recursively end
                            return false;
                        }
                    } else if (wpmfFoldersTreeModule.categories[ij + 1].depth < wpmfFoldersTreeModule.categories[ij].depth) { // The next element don't have the same parent
                        // Let's close opened tags
                        for (let ik = wpmfFoldersTreeModule.categories[ij].depth; ik > wpmfFoldersTreeModule.categories[ij + 1].depth; ik--) {
                            content += '</li>';
                            content += '</ul>';
                        }

                        // We're not at the end of the array let's continue processing it
                        return true;
                    }

                    // Close the current element
                    content += '</li>';
                    ij++;
                }
            };

            // Start generation
            generateList('wpmf_media_library');

            // Add the new folder button
            content = search_folder + '<a class="wpmf-new-folder" onclick="wpmfFoldersModule.newFolder(wpmfFoldersModule.last_selected_folder)"><i class="material-icons">create_new_folder</i>' + wpmf.l18n.create_folder + '</a>' + content;

            return content;
        },

        /**
         * Change the selected folder in tree view
         * @param folder_id
         */
        changeFolder: function (folder_id) {
            // Remove previous selection
            wpmfFoldersTreeModule.getTreeElement().find('li').removeClass('selected');

            // Select the folder
            wpmfFoldersTreeModule.getTreeElement().find('li[data-id="' + folder_id + '"]').addClass('selected').// Open parent folders
            parents('.wpmf-folder-tree li.closed').removeClass('closed');
        },

        /**
         * Toggle the open / closed state of a folder
         * @param folder_id
         */
        toggle: function (folder_id) {
            // get last status folder tree
            let lastStatusTree = [];
            // Check is folder has closed class
            if (wpmfFoldersTreeModule.getTreeElement().find('li[data-id="' + folder_id + '"]').hasClass('closed')) {
                // Open the folder
                wpmfFoldersTreeModule.openFolder(folder_id);
            } else {
                // Close the folder
                wpmfFoldersTreeModule.closeFolder(folder_id);
                // close all sub folder
                $('li[data-id="' + folder_id + '"]').find('li').addClass('closed');
            }

            wpmfFoldersTreeModule.getTreeElement().find('li:not(.closed)').each(function (i, v) {
                let id = $(v).data('id');
                lastStatusTree.push(id);
            });
            // set last status folder tree
            wpmfFoldersModule.setCookie("lastStatusTree_" + wpmf.vars.site_url, JSON.stringify(lastStatusTree), 365);
        },


        /**
         * Open a folder to show children
         */
        openFolder: function (folder_id) {
            wpmfFoldersTreeModule.getTreeElement().find('li[data-id="' + folder_id + '"]').removeClass('closed');
            wpmfFoldersTreeModule.folders_states[folder_id] = 'open';
        },

        /**
         * Close a folder and hide children
         */
        closeFolder: function (folder_id) {
            wpmfFoldersTreeModule.getTreeElement().find('li[data-id="' + folder_id + '"]').addClass('closed');
            wpmfFoldersTreeModule.folders_states[folder_id] = 'close';
        },

        /**
         * Initialize the fixed position when user is scrolling
         * to keep the folder tree always visible
         */
        initFixedScrolling: function () {
            let $attachments_browser;
            if (wpmfFoldersModule.page_type === 'upload-list') {
                $attachments_browser = $('#wpmf-wrapper');
            } else {
                $attachments_browser = $('.attachments-browser ul.attachments');
            }

            setTimeout(function () {
                // Fix initial left margin in list view
                if (wpmfFoldersModule.page_type === 'upload-list') {
                    $('#wpmf-wrapper').css('margin-left', (wpmfFoldersTreeModule.getTreeElement().outerWidth() + 10) + 'px');
                    $('.rtl #wpmf-wrapper').css({
                        'margin-right': (wpmfFoldersTreeModule.getTreeElement().outerWidth() + 10) + 'px',
                        'margin-left': 0
                    });
                }

                // Get the position of folder tree in normal mode
                let original_top_position;
                if (wpmfFoldersModule.page_type === 'upload-list') {
                    original_top_position = $attachments_browser.offset().top;
                } else {
                    if (wpmfFoldersTreeModule.getTreeElement().length) {
                        original_top_position = wpmfFoldersTreeModule.getTreeElement().offset().top;
                    }
                }

                // Check on window scroll event
                $(window).on('scroll', function (e) {
                    // Check if the window has been scrolled more than the top position of the folder tree
                    if (original_top_position < this.scrollY + 40) {
                        // Check if folder tree was already in normal position
                        if (wpmfFoldersTreeModule.last_scrolling_state !== 'fixed') {
                            if (wpmfFoldersModule.page_type === 'upload-grid') {
                                // Add a margin to the attachments browser to keep the place on left side
                                $attachments_browser.css('margin-left', $attachments_browser.position().left + 30 + 'px');
                            }

                            // Set the folder tree in the fixed position
                            if (!$('body').hasClass('rtl')) {
                                wpmfFoldersTreeModule.getTreeElement().css('position', 'fixed').css('top', '40px');
                            }

                            // Save the state
                            wpmfFoldersTreeModule.last_scrolling_state = 'fixed';
                        }
                    } else {
                        if (wpmfFoldersModule.page_type === 'upload-list') {
                            // Fix top positioning of folder tree
                            const top_position = $('#wpmf-breadcrumb').offset().top + $('#wpmf-breadcrumb').outerHeight() - this.scrollY;
                            wpmfFoldersTreeModule.getTreeElement().css('top', top_position);
                        }

                        // Check if folder tree was already in fixed position
                        if (wpmfFoldersTreeModule.last_scrolling_state !== 'initial') {
                            // Revert all fixed things
                            wpmfFoldersTreeModule.getTreeElement().css('position', '').css('top', '');

                            if (wpmfFoldersModule.page_type === 'upload-grid') {
                                $attachments_browser.css('margin-left', '');
                            }

                            // Save the state
                            wpmfFoldersTreeModule.last_scrolling_state = 'initial';
                        }
                    }

                    // Remove the loader on list page
                    if (wpmfFoldersModule.page_type === 'upload-list' && !$('.upload-php #posts-filter').hasClass('wpmf-not-loading')) {
                        setTimeout(function () {
                            $('.upload-php #posts-filter').addClass('wpmf-not-loading');
                        }, 200);
                    }
                });

                // Initialize all by simulating a scroll
                $(window).trigger('scroll');
            }, 200);
        },

        /**
         * Initialize folder tree resizing
         * @param $current_frame
         */
        initContainerResizing: function ($current_frame) {
            let is_resizing = false;
            const $body = $('body');
            if (wpmf.vars.wpmf_pagenow === 'upload.php' && wpmfFoldersModule.page_type) {
                // Main upload.php page
                const $tree = $current_frame.find('.wpmf-folder-tree');
                const $handle = $('<div class="wpmf-folder-tree-resize"></div>').appendTo($tree);
                let $attachments;
                if (wpmfFoldersModule.page_type === 'upload-list') {
                    $attachments = $current_frame.find('#wpmf-wrapper');
                } else {
                    $attachments = $current_frame.find('.attachments');
                }

                if (!$attachments.length) {
                    return;
                }

                $handle.on('mousedown', function (e) {
                    is_resizing = true;
                    $('body').css('user-select', 'none'); // prevent content selection while moving
                });

                let uploadPageTreeSize = wpmfFoldersModule.getCookie('upload-page-tree-size');
                if (typeof uploadPageTreeSize !== "undefined" && parseFloat(uploadPageTreeSize) > 0) {
                    $tree.css('width', parseFloat(uploadPageTreeSize) + 'px');
                    // We have to set margin if we are in a fixed tree position or in list page
                    if (wpmfFoldersTreeModule.last_scrolling_state === 'fixed' || wpmfFoldersModule.page_type === 'upload-list') {
                        $attachments.css('margin-left', (parseFloat(uploadPageTreeSize) + 32) + 'px');
                    }

                    $('.wpmf-folder-tree, .wpmf-folder-tree > ul').css('max-width', '100%');
                }

                $(document).on('mousemove', function (e) {
                    // we don't want to do anything if we aren't resizing.
                    if (!is_resizing)
                        return;

                    // Calculate tree width
                    $('.wpmf-folder-tree').css('max-width', '100%');
                    const tree_width = (e.clientX - $tree.offset().left - 30);
                    wpmfFoldersModule.setCookie('upload-page-tree-size', tree_width, 365);
                    $tree.css('width', tree_width + 'px');

                    // We have to set margin if we are in a fixed tree position or in list page
                    if (wpmfFoldersTreeModule.last_scrolling_state === 'fixed' || wpmfFoldersModule.page_type === 'upload-list') {
                        $attachments.css('margin-left', (tree_width + 32) + 'px');
                    }
                }).on('mouseup', function (e) {
                    if (is_resizing) {
                        // stop resizing
                        is_resizing = false;
                        $body.css('user-select', '');
                        $(window).trigger('resize');
                    }
                });
            } else if ($current_frame.hasClass('hide-menu')) {
                // Modal window with no left menu
                const $tree = $current_frame.find('.wpmf-folder-tree');
                const $handle = $('<div class="wpmf-folder-tree-resize"></div>').insertAfter($tree);
                const $attachments = $current_frame.find('.attachments');
                if (!$attachments.length) {
                    return;
                }

                $handle.on('mousedown', function (e) {
                    is_resizing = true;
                    $body.css('user-select', 'none'); // prevent content selection while moving
                });

                let hideMenuTreeSize = wpmfFoldersModule.getCookie('hide-menu-tree-size');
                if (typeof hideMenuTreeSize !== "undefined" && parseFloat(hideMenuTreeSize) > 0) {
                    // Set positioning of the different elements
                    $tree.css('width', parseFloat(hideMenuTreeSize) + 'px');
                    $handle.css('left', (parseFloat(hideMenuTreeSize) + 10) + 'px');
                    $attachments.css('left', (parseFloat(hideMenuTreeSize) + 4) + 'px');
                    $('.wpmf-folder-tree, .wpmf-folder-tree > ul').css('max-width', '100%');
                } else {
                    // Set handle initial position
                    setTimeout(function () {
                        $tree.css('width', 'calc(20% + 10px)');
                        $handle.css({right: 'auto', left: 'calc(20% + 10px)'});
                    },500);
                }

                $(document).on('mousemove', function (e) {
                    // we don't want to do anything if we aren't resizing.
                    if (!is_resizing)
                        return;

                    // Calculate tree width
                    $('.wpmf-folder-tree').css('max-width', '100%');
                    const tree_width = (e.clientX - $tree.offset().left);
                    wpmfFoldersModule.setCookie('hide-menu-tree-size', tree_width, 365);

                    // Set positioning of the different elements
                    $tree.css('width', tree_width + 'px');
                    $handle.css('left', (tree_width + 10) + 'px');

                    $attachments.css('left', (tree_width + 4) + 'px');
                }).on('mouseup', function (e) {
                    if (is_resizing) {
                        // stop resizing
                        is_resizing = false;
                        $('body').css('user-select', '');
                        $(window).trigger('resize');
                    }
                });
            } else {
                // Modal window with left menu
                const $menu = $current_frame.find('.media-frame-menu');
                const $handle = $('<div class="wpmf-folder-tree-resize"></div>').appendTo($menu);
                const $right_cols = $current_frame.find('.media-frame-content, .media-frame-router,  .media-frame-title, .media-frame-toolbar');

                $handle.on('mousedown', function (e) {
                    is_resizing = true;
                    $body.css('user-select', 'none'); // prevent content selection while moving
                });

                let menuTreeSize = wpmfFoldersModule.getCookie('menu-tree-size');
                if (typeof menuTreeSize !== "undefined" && parseFloat(menuTreeSize) > 0) {
                    $menu.css('width', parseFloat(menuTreeSize) + 'px');
                    $right_cols.css('left', (parseFloat(menuTreeSize) + 14) + 'px');
                    $('.wpmf-folder-tree, .wpmf-folder-tree > ul').css('max-width', '100%');
                }

                $(document).on('mousemove', function (e) {
                    // we don't want to do anything if we aren't resizing.
                    if (!is_resizing)
                        return;
                    $('.wpmf-folder-tree').css('max-width', '100%');
                    const menu_width = (e.clientX - $menu.offset().left);
                    wpmfFoldersModule.setCookie('menu-tree-size', menu_width, 365);

                    $menu.css('width', menu_width + 'px');

                    $right_cols.css('left', (menu_width + 14) + 'px');
                }).on('mouseup', function (e) {
                    if (is_resizing) {
                        // stop resizing
                        is_resizing = false;
                        $body.css('user-select', '');
                        $(window).trigger('resize');
                    }
                });
            }

        }
    };

    // Let's initialize WPMF folder tree features
    $(document).ready(function () {
        if (typeof wpmfFoldersModule !== "undefined") {
            if (wpmfFoldersModule.page_type === 'upload-list') {
                // Don't need to wait on list page
                wpmfFoldersTreeModule.initModule(wpmfFoldersModule.getFrame());
            } else {
                // Wait for the main wpmf module to be ready
                wpmfFoldersModule.on('ready', function ($current_frame) {
                    wpmfFoldersTreeModule.initModule($current_frame);
                });
            }
        }
    });
})(jQuery);

// call single click or double click on folder tree
jQuery.fn.wpmfSingleDoubleClick = function (single_click_callback, double_click_callback, timeout) {
    return this.each(function () {
        var clicks = 0, self = this;
        jQuery(this).click(function (event) {
            clicks++;
            if (clicks === 1) {
                setTimeout(function () {
                    if (clicks === 1) {
                        single_click_callback.call(self, event);
                    } else {
                        double_click_callback.call(self, event);
                    }
                    clicks = 0;
                }, timeout || 300);
            }
        });
    });
};
