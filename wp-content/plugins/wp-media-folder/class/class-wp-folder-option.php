<?php
/* Prohibit direct script loading */
defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Class WpmfMediaFolderOption
 * This class that holds most of the settings functionality for Media Folder.
 */
class WpmfMediaFolderOption
{

    /**
     * Use to store breadcrumb of folder on media library
     *
     * @var array
     */
    public $breadcrumb_category = array();

    /**
     * Message when gennerate thumbnail success
     *
     * @var string
     */
    public $result_gennerate_thumb = '';

    /**
     * Allow file extension to import
     *
     * @var array
     */
    public $type_import = array(
        'jpg',
        'jpeg',
        'jpe',
        'gif',
        'png',
        'svg',
        'bmp',
        'tiff',
        'tif',
        'ico',
        '7z',
        'bz2',
        'gz',
        'rar',
        'tgz',
        'zip',
        'csv',
        'doc',
        'docx',
        'ods',
        'odt',
        'pdf',
        'pps',
        'ppt',
        'pptx',
        'ppsx',
        'rtf',
        'txt',
        'xls',
        'xlsx',
        'psd',
        'tif',
        'tiff',
        'mid',
        'mp3',
        'mp4',
        'ogg',
        'wma',
        '3gp',
        'avi',
        'flv',
        'm4v',
        'mkv',
        'mov',
        'mpeg',
        'mpg',
        'swf',
        'vob',
        'wmv'
    );

    /**
     * Default time sync file
     *
     * @var integer
     */
    public $default_time_sync = 60;

    /**
     * Media_Folder_Option constructor.
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'addSettingsMenu'));
        add_action('admin_enqueue_scripts', array($this, 'loadAdminScripts'));
        add_action('admin_enqueue_scripts', array($this, 'heartbeatEnqueue'));
        add_filter('heartbeat_received', array($this, 'heartbeatReceived'), 10, 2);

        $wpmf_version = get_option('wpmf_version');
        if (version_compare(WPMF_VERSION, $wpmf_version, '>') || empty($wpmf_version)) {
            add_action('admin_init', array($this, 'addSettingsOption'));
        }

        add_action('wp_ajax_import_gallery', array($this, 'importGallery'));
        add_action('wp_ajax_import_categories', array($this, 'importCategories'));
        add_action('wp_ajax_wpmf_add_dimension', array($this, 'addDimension'));
        add_action('wp_ajax_wpmf_remove_dimension', array($this, 'removeDimension'));
        add_action('wp_ajax_wpmf_add_weight', array($this, 'addWeight'));
        add_action('wp_ajax_wpmf_remove_weight', array($this, 'removeWeight'));
        add_action('wp_ajax_wpmf_edit', array($this, 'edit'));
        add_action('wp_ajax_wpmf_get_folder', array($this, 'getFolder'));
        add_action('wp_ajax_wpmf_import_folder', array($this, 'importFolder'));
        add_action('wp_ajax_wpmf_add_syncmedia', array($this, 'addSyncMedia'));
        add_action('wp_ajax_wpmf_remove_syncmedia', array($this, 'removeSyncMedia'));
        add_action('wp_ajax_wpmf_regeneratethumbnail', array($this, 'regenerateThumbnail'));
        add_action('wp_ajax_wpmf_syncmedia', array($this, 'syncMedia'));
        add_action('wp_ajax_wpmf_syncmedia_external', array($this, 'syncMediaExternal'));
        add_action('wp_ajax_wpmf_import_size_filetype', array($this, 'importSizeFiletype'));
    }

    /**
     * Import size and filetype to meta for attachment
     *
     * @return void
     */
    public function importSizeFiletype()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to import file infos (size and filetype)
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'import_size_filetype');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        global $wpdb;
        $current_page = (int) $_POST['wpmf_current_page'];
        $limit        = 50;
        $offset       = $current_page * $limit;
        $attachments  = $wpdb->get_results($wpdb->prepare('SELECT ID FROM ' . $wpdb->prefix . 'posts as posts
               WHERE   posts.post_type     = %s LIMIT %d OFFSET %d', array('attachment', $limit, $offset)));

        if (empty($attachments)) {
            update_option('_wpmf_import_size_notice_flag', 'yes');
            wp_send_json(array('status' => true, 'continue' => false));
        }

        foreach ($attachments as $attachment) {
            $wpmf_size_filetype = wpmfGetSizeFiletype($attachment->ID);
            $size               = $wpmf_size_filetype['size'];
            $ext                = $wpmf_size_filetype['ext'];
            if (!get_post_meta($attachment->ID, 'wpmf_size')) {
                update_post_meta($attachment->ID, 'wpmf_size', $size);
            }

            if (!get_post_meta($attachment->ID, 'wpmf_filetype')) {
                update_post_meta($attachment->ID, 'wpmf_filetype', $ext);
            }
        }

        wp_send_json(array('status' => true, 'continue' => true));
    }

    /**
     * Create attachment and insert attachment to database
     *
     * @param string  $upload_path Path of file
     * @param string  $upload_url  URL of file
     * @param string  $file_title  Title of tile
     * @param string  $file        File name
     * @param string  $form_file   Path of file need copy
     * @param string  $mime_type   Mime type of file
     * @param string  $ext         Extension of file
     * @param integer $term_id     Folder id
     *
     * @return boolean|integer
     */
    public function insertAttachmentMetadata(
        $upload_path,
        $upload_url,
        $file_title,
        $file,
        $form_file,
        $mime_type,
        $ext,
        $term_id
    ) {
        if (file_exists($upload_path . '/' . $file)) {
            $file   = wp_unique_filename($upload_path, $file);
        }

        $upload = copy($form_file, $upload_path . '/' . $file);
        if ($upload) {
            $attachment = array(
                'guid'           => $upload_url . '/' . $file,
                'post_mime_type' => $mime_type,
                'post_title'     => str_replace('.' . $ext, '', $file_title),
                'post_status'    => 'inherit'
            );

            $image_path = $upload_path . '/' . $file;
            // Insert attachment
            $attach_id   = wp_insert_attachment($attachment, $image_path);
            // set attachment to term
            wp_set_object_terms((int) $attach_id, (int) $term_id, WPMF_TAXO, false);
            $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            /**
             * Create an attachment when importing or syncing files from FTP
             * This hook is also used when a remote video is created
             *
             * @param integer       Created attachment ID
             * @param integer|array Target folder or array of target folders
             * @param array         Extra informations
             */
            do_action('wpmf_add_attachment', $attach_id, $term_id, array('type' => 'attachment'));
            return $attach_id;
        }
        return false;
    }

    /**
     * Create attachment and insert attachment to database
     *
     * @param string  $filepath Path of file
     * @param integer $term_id  Folder id
     *
     * @return boolean|integer
     */
    public function syncAttachmentMetadata($filepath, $term_id)
    {
        if (file_exists($filepath)) {
            return false;
        }
        $info = pathinfo($filepath);
        $filetype   = wp_check_filetype($info['basename']);
        $mimeType = $filetype['type'];
        $guid = str_replace(ABSPATH, site_url('/'), $filepath);
        $attachment = array(
            'guid'           => $guid,
            'post_mime_type' => $mimeType,
            'post_title'     => $info['filename'],
            'post_type'     => 'attachment',
            'post_status'    => 'inherit'
        );

        $attach_id   = wp_insert_post($attachment);
        $attached = $filepath;
        wp_set_object_terms((int) $attach_id, (int) $term_id, WPMF_TAXO);

        update_post_meta($attach_id, 'wpmf_import_path', $filepath);
        update_post_meta($attach_id, '_wp_attached_file', $attached);
        update_post_meta($attach_id, 'wpmf_filetype', $info['extension']);
        update_post_meta($attach_id, 'wpmf_order', 0);


        if (strpos($mimeType, 'image') !== false) {
            $meta = array();
            if (!empty($width) && !empty($height)) {
                $meta['width'] = $width;
                $meta['height'] = $height;
            } else {
                list($width, $heigth) = wpmfGetImgSize($filepath);
                $meta['width'] = $width;
                $meta['height'] = $heigth;
            }

            update_post_meta($attach_id, '_wp_attachment_metadata', $meta);
        }

        return $attach_id;
    }

    /**
     * Scan folder to insert term and attachment
     *
     * @param string  $dir         Root folder to scan
     * @param integer $folder_name Folder name
     * @param integer $parent      Parent of folder
     * @param integer $percent     Percent
     *
     * @return void
     */
    public function addScandirFolder($dir, $folder_name, $parent, $percent)
    {
        global $wpdb;
        require_once('ForceUTF8/Encoding.php');
        $folder_name = WpmfEncoding::toUTF8($folder_name);
        $term_id     = $wpdb->get_results($wpdb->prepare(
            'SELECT t1.term_id FROM ' . $wpdb->terms . ' as t1, ' . $wpdb->term_taxonomy . ' as t2 
 WHERE taxonomy=%s AND name=%s AND parent=%d AND t1.term_id=t2.term_id',
            array(WPMF_TAXO, $folder_name, $parent)
        ));

        $index           = 0;
        if (empty($term_id)) {
            $inserted = wp_insert_term(
                $folder_name,
                WPMF_TAXO,
                array(
                    'parent' => $parent,
                    'slug'   => sanitize_title($folder_name) . WPMF_TAXO
                )
            );

            if (is_array($inserted)) {
                $termID = $inserted['term_id'];
                /**
                 * Create a folder when importing a folder from FTP
                 *
                 * @param integer Created folder ID
                 * @param string  Created folder name
                 * @param integer Parent folder ID
                 * @param array   Extra informations
                 *
                 * @ignore Hook already documented
                 */
                do_action('wpmf_create_folder', $inserted['term_id'], $folder_name, $parent, array('trigger'=>'ftp_import'));
            } else {
                $termID = $inserted->error_data['term_exists'];
            }
        } else {
            $termID = $term_id[0]->term_id;
        }

        // List files and directories inside $dir path
        $files = scandir($dir);
        $files = array_diff($files, array('..', '.'));
        if (count($files) === 0) {
            return;
        }

        // loop list files and directories
        $upload_dir = wp_upload_dir();
        foreach ($files as $file) {
            if ($index >= 3) {
                wp_send_json(array('status' => true, 'continue' => true, 'percent' => $percent)); // run again ajax
            }

            $filepath = $dir . '/' . $file;
            if (is_dir($filepath)) { // is directory
                $this->addScandirFolder($filepath, str_replace('  ', ' ', $file), $termID, $percent);
            } else {
                // is file
                $info_file  = wp_check_filetype($filepath);
                if (empty($info_file) || (!empty($info_file) && empty($info_file['ext'])) || (!empty($info_file) && !empty($info_file['ext']) && !in_array(strtolower($info_file['ext']), $this->type_import))) {
                    continue;
                }

                $file_title = $file;
                $file       = sanitize_file_name($file);
                // check file exist , if not exist then insert file
                $file_exists = $this->checkExistPost('/' . $file, $termID, $upload_dir);
                if (!empty($file_exists)) {
                    continue;
                }

                if (defined('WPMF_SYNC_ATTACHMENT_IMPORT')) {
                    $attachmentId = $this->syncAttachmentMetadata(
                        $filepath,
                        $termID
                    );
                } else {
                    $attachmentId = $this->insertAttachmentMetadata(
                        $upload_dir['path'],
                        $upload_dir['url'],
                        $file_title,
                        $file,
                        $filepath,
                        $info_file['type'],
                        $info_file['ext'],
                        $termID
                    );
                }

                if (!$attachmentId) {
                    continue;
                }
                $index++;
            }
        }
    }

    /**
     * Ajax add a row to lists sync media
     *
     * @return void
     */
    public function addSyncMedia()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to add sync list
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'add_sync_list');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        if (isset($_POST['folder_category']) && isset($_POST['folder_ftp'])) {
            $folder_ftp      = str_replace('\\', '/', stripcslashes($_POST['folder_ftp']));
            $folder_category = $_POST['folder_category'];

            $lists = get_option('wpmf_list_sync_media');
            if (is_array($lists) && !empty($lists)) {
                $lists[$folder_category] = array('folder_ftp' => $folder_ftp);
            } else {
                $lists                   = array();
                $lists[$folder_category] = array('folder_ftp' => $folder_ftp);
            }

            update_option('wpmf_list_sync_media', $lists);
            wp_send_json(array('folder_category' => $folder_category, 'folder_ftp' => $folder_ftp));
        }
    }

    /**
     * Ajax remove a row to lists sync media
     *
     * @return void
     */
    public function removeSyncMedia()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to remove sync list
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'remove_sync_item');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        $lists = get_option('wpmf_list_sync_media');

        if (isset($_POST['key']) && $_POST['key'] !== '') {
            foreach (explode(',', $_POST['key']) as $key) {
                if (isset($lists[$key])) {
                    unset($lists[$key]);
                }
            }
            update_option('wpmf_list_sync_media', $lists);
            wp_send_json(explode(',', $_POST['key']));
        }
        wp_send_json(false);
    }

    /**
     * This function do import from FTP to media library
     *
     * @return void
     */
    public function importFolder()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to import the files and folders from FTP
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'import_from_ftp');
        if ($wpmf_capability) {
            if (isset($_POST['wpmf_list_import'])) {
                $list_import = $_POST['wpmf_list_import'];

                /**
                 * Filter the filetype allowed to be imported through ftp or folder import
                 *
                 * @param array  Filetypes allowed to be imported
                 *
                 * @return array
                 *
                 * @ignore Hook already documented
                 */
                $this->type_import = apply_filters('wpmf_import_allowed_filetypes', $this->type_import);

                if ($list_import !== '') {
                    $lists = explode(',', $list_import);
                    if (in_array('', $lists)) {
                        $key_null = array_search('', $lists);
                        unset($lists[$key_null]);
                    }

                    // get count files and directories in folder
                    $i = 0;
                    if (!empty($lists)) {
                        foreach ($lists as $list) {
                            $root = ABSPATH . $list;
                            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $filename) {
                                $info_file = wp_check_filetype((string) $filename);
                                if (is_dir((string) $filename)) {
                                    $i ++;
                                } else {
                                    if (!empty($info_file['ext'])
                                        && in_array(strtolower($info_file['ext']), $this->type_import)
                                    ) {
                                        $i ++;
                                    }
                                }
                            }
                        }
                    }

                    $percent = (100 * 3) / $i;
                    foreach ($lists as $list) {
                        if ($list !== '/') {
                            $root     = ABSPATH . $list;
                            $info     = pathinfo($list);
                            $filename = $info['basename'];
                            $parent   = 0;
                            $this->addScandirFolder($root, $filename, $parent, $percent);
                        }
                    }

                    wp_send_json(array('status' => true, 'continue' => false));
                }
            }
        }
    }

    /**
     * This function do validate path
     *
     * @param string $path Path of file
     *
     * @return string
     */
    public function validatePath($path)
    {
        return rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $path), '/');
    }

    /**
     * Get term to display folder tree
     *
     * @return void
     */
    public function getFolder()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to get folder from FTP
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'get_folder_from_ftp');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }

        if (isset($_POST['wpmf_list_import'])) {
            $list_checked = $_POST['wpmf_list_import'];
        } else {
            $list_checked = '';
        }

        $uploads_dir      = wp_upload_dir();
        $uploads_dir_path = $uploads_dir['path'];
        $selected_folders = explode(',', $list_checked);
        $path             = $this->validatePath(WPMF_ABSPATH);
        $dir              = $_REQUEST['dir'];
        $return           = array();
        $dirs             = array();
        require_once('ForceUTF8/Encoding.php');
        if (file_exists($path . $dir)) {
            $files = scandir($path . $dir);
            $files = array_diff($files, array('..', '.'));
            natcasesort($files);
            if (count($files) > 0) {
                $baseDir = ltrim(rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir), '/'), '/');
                if ($baseDir !== '') {
                    $baseDir .= '/';
                }

                foreach ($files as $file) {
                    if (file_exists($path . $dir . $file) && is_dir($path . $dir . $file)) {
                        $file = WpmfEncoding::toUTF8($file);
                        if (in_array($baseDir . $file, $selected_folders)) {
                            if ($path . $dir . $file === $this->validatePath($uploads_dir_path)) {
                                $dirs[] = array('type' => 'dir', 'dir' => $dir, 'file' => $file, 'checked' => true, 'disable' => true);
                            } else {
                                $dirs[] = array('type' => 'dir', 'dir' => $dir, 'file' => $file, 'checked' => true, 'disable' => false);
                            }
                        } else {
                            $hasSubFolderSelected = false;
                            foreach ($selected_folders as $selected_folder) {
                                if (strpos($selected_folder, $baseDir . $file) === 1) {
                                    $hasSubFolderSelected = true;
                                }
                            }

                            if ($hasSubFolderSelected) {
                                if ($path . $dir . $file === $this->validatePath($uploads_dir_path)) {
                                    $dirs[] = array('type' => 'dir', 'dir' => $dir, 'file' => $file, 'pchecked' => true, 'disable' => true);
                                } else {
                                    $dirs[] = array('type' => 'dir', 'dir' => $dir, 'file' => $file, 'pchecked' => true, 'disable' => false);
                                }
                            } else {
                                if ($path . $dir . $file === $this->validatePath($uploads_dir_path)) {
                                    $dirs[] = array('type' => 'dir', 'dir' => $dir, 'file' => $file, 'disable' => true);
                                } else {
                                    $dirs[] = array('type' => 'dir', 'dir' => $dir, 'file' => $file, 'disable' => false);
                                }
                            }
                        }
                    }
                }
                $return = $dirs;
            }
        }
        wp_send_json($return);
    }

    /**
     * Add default settings option
     *
     * @return void
     */
    public function addSettingsOption()
    {
        update_option('wpmf_version', WPMF_VERSION);
        if (!get_option('wpmf_gallery_image_size_value', false)) {
            add_option('wpmf_gallery_image_size_value', '["thumbnail","medium","large","full"]');
        }
        if (!get_option('wpmf_padding_masonry', false)) {
            add_option('wpmf_padding_masonry', 5);
        }

        if (!get_option('wpmf_padding_portfolio', false)) {
            add_option('wpmf_padding_portfolio', 10);
        }

        if (!get_option('wpmf_usegellery', false)) {
            add_option('wpmf_usegellery', 1);
        }

        if (!get_option('wpmf_useorder', false)) {
            add_option('wpmf_useorder', 1, '', 'yes');
        }

        if (!get_option('wpmf_create_folder', false)) {
            add_option('wpmf_create_folder', 'role', '', 'yes');
        }

        if (!get_option('wpmf_option_override', false)) {
            add_option('wpmf_option_override', 0, '', 'yes');
        }

        if (!get_option('wpmf_option_duplicate', false)) {
            add_option('wpmf_option_duplicate', 0, '', 'yes');
        }

        if (!get_option('wpmf_active_media', false)) {
            add_option('wpmf_active_media', 0, '', 'yes');
        }

        if (!get_option('wpmf_folder_option2', false)) {
            add_option('wpmf_folder_option2', 1, '', 'yes');
        }

        if (!get_option('wpmf_option_searchall', false)) {
            add_option('wpmf_option_searchall', 0, '', 'yes');
        }

        if (!get_option('wpmf_usegellery_lightbox', false)) {
            add_option('wpmf_usegellery_lightbox', 1, '', 'yes');
        }

        if (!get_option('wpmf_media_rename', false)) {
            add_option('wpmf_media_rename', 0, '', 'yes');
        }

        if (!get_option('wpmf_patern_rename', false)) {
            add_option('wpmf_patern_rename', '{sitename} - {foldername} - #', '', 'yes');
        }

        if (!get_option('wpmf_rename_number', false)) {
            add_option('wpmf_rename_number', 0, '', 'yes');
        }

        if (!get_option('wpmf_option_media_remove', false)) {
            add_option('wpmf_option_media_remove', 0, '', 'yes');
        }

        $dimensions        = array('400x300', '640x480', '800x600', '1024x768', '1600x1200');
        $dimensions_string = json_encode($dimensions);
        if (!get_option('wpmf_default_dimension', false)) {
            add_option('wpmf_default_dimension', $dimensions_string, '', 'yes');
        }

        if (!get_option('wpmf_selected_dimension', false)) {
            add_option('wpmf_selected_dimension', $dimensions_string, '', 'yes');
        }

        $weights       = array(
            array('0-61440', 'kB'),
            array('61440-122880', 'kB'),
            array('122880-184320', 'kB'),
            array('184320-245760', 'kB'),
            array('245760-307200', 'kB')
        );
        $weight_string = json_encode($weights);
        if (!get_option('wpmf_weight_default', false)) {
            add_option('wpmf_weight_default', $weight_string, '', 'yes');
        }

        if (!get_option('wpmf_weight_selected', false)) {
            add_option('wpmf_weight_selected', $weight_string, '', 'yes');
        }

        $wpmf_color_singlefile = array(
            'bgdownloadlink'   => '#444444',
            'hvdownloadlink'   => '#888888',
            'fontdownloadlink' => '#ffffff',
            'hoverfontcolor'   => '#ffffff'
        );
        if (!get_option('wpmf_color_singlefile', false)) {
            add_option('wpmf_color_singlefile', json_encode($wpmf_color_singlefile), '', 'yes');
        }

        if (!get_option('wpmf_option_singlefile', false)) {
            add_option('wpmf_option_singlefile', 0, '', 'yes');
        }

        if (!get_option('wpmf_option_sync_media', false)) {
            add_option('wpmf_option_sync_media', 0, '', 'yes');
        }

        if (!get_option('wpmf_option_sync_media_external', false)) {
            add_option('wpmf_option_sync_media_external', 0, '', 'yes');
        }

        if (!get_option('wpmf_list_sync_media', false)) {
            add_option('wpmf_list_sync_media', array(), '', 'yes');
        }

        if (!get_option('wpmf_time_sync', false)) {
            add_option('wpmf_time_sync', $this->default_time_sync, '', 'yes');
        }

        if (!get_option('wpmf_lastRun_sync', false)) {
            add_option('wpmf_lastRun_sync', time(), '', 'yes');
        }

        if (!get_option('wpmf_slider_animation', false)) {
            add_option('wpmf_slider_animation', 'slide', '', 'yes');
        }

        if (!get_option('wpmf_option_mediafolder', false)) {
            add_option('wpmf_option_mediafolder', 0, '', 'yes');
        }

        if (!get_option('wpmf_option_countfiles', false)) {
            add_option('wpmf_option_countfiles', 0, '', 'yes');
        }

        if (!get_option('wpmf_option_lightboximage', false)) {
            add_option('wpmf_option_lightboximage', 0, '', 'yes');
        }

        if (!get_option('wpmf_option_hoverimg', false)) {
            add_option('wpmf_option_hoverimg', 1, '', 'yes');
        }

        $format_title = array(
            'hyphen'          => 1,
            'underscore'      => 1,
            'period'          => 0,
            'tilde'           => 0,
            'plus'            => 0,
            'capita'          => 'cap_all',
            'alt'             => 0,
            'caption'         => 0,
            'description'     => 0,
            'hash'            => 0,
            'ampersand'       => 0,
            'number'          => 0,
            'square_brackets' => 0,
            'round_brackets'  => 0,
            'curly_brackets'  => 0
        );

        if (!get_option('wpmf_options_format_title', false)) {
            add_option('wpmf_options_format_title', $format_title, '', 'yes');
        }

        $watermark_apply = array(
            'all_size' => 1
        );
        $sizes           = apply_filters('image_size_names_choose', array(
            'thumbnail' => __('Thumbnail', 'wpmf'),
            'medium'    => __('Medium', 'wpmf'),
            'large'     => __('Large', 'wpmf'),
            'full'      => __('Full Size', 'wpmf'),
        ));
        foreach ($sizes as $ksize => $vsize) {
            $watermark_apply[$ksize] = 0;
        }

        if (!get_option('wpmf_image_watermark_apply', false)) {
            add_option('wpmf_image_watermark_apply', $watermark_apply, '', 'yes');
        }

        if (!get_option('wpmf_option_image_watermark', false)) {
            add_option('wpmf_option_image_watermark', 0, '', 'yes');
        }

        if (!get_option('wpmf_watermark_position', false)) {
            add_option('wpmf_watermark_position', 'top_left', '', 'yes');
        }

        if (!get_option('wpmf_watermark_image', false)) {
            add_option('wpmf_watermark_image', '', '', 'yes');
        }

        if (!get_option('wpmf_watermark_image_id', false)) {
            add_option('wpmf_watermark_image_id', 0, '', 'yes');
        }

        $gallery_settings = array(
            'theme' => array(
                'default_theme'     => array(
                    'columns'    => 3,
                    'size'       => 'medium',
                    'targetsize' => 'large',
                    'link'       => 'file',
                    'orderby'    => 'post__in',
                    'order'      => 'ASC'
                ),
                'portfolio_theme'   => array(
                    'columns'    => 3,
                    'size'       => 'medium',
                    'targetsize' => 'large',
                    'link'       => 'file',
                    'orderby'    => 'post__in',
                    'order'      => 'ASC'
                ),
                'masonry_theme'     => array(
                    'columns'    => 3,
                    'size'       => 'medium',
                    'targetsize' => 'large',
                    'link'       => 'file',
                    'orderby'    => 'post__in',
                    'order'      => 'ASC'
                ),
                'slider_theme'      => array(
                    'columns'        => 3,
                    'size'           => 'medium',
                    'targetsize'     => 'large',
                    'link'           => 'file',
                    'orderby'        => 'post__in',
                    'order'          => 'ASC',
                    'animation'      => 'slide',
                    'duration'       => 4000,
                    'auto_animation' => 1
                ),
                'flowslide_theme'   => array(
                    'columns'      => 3,
                    'size'         => 'medium',
                    'targetsize'   => 'large',
                    'link'         => 'file',
                    'orderby'      => 'post__in',
                    'order'        => 'ASC',
                    'show_buttons' => 1
                ),
                'square_grid_theme' => array(
                    'columns'    => 3,
                    'size'       => 'medium',
                    'targetsize' => 'large',
                    'link'       => 'file',
                    'orderby'    => 'post__in',
                    'order'      => 'ASC'
                ),
                'material_theme'    => array(
                    'columns'    => 3,
                    'size'       => 'medium',
                    'targetsize' => 'large',
                    'link'       => 'file',
                    'orderby'    => 'post__in',
                    'order'      => 'ASC'
                ),
            )
        );
        if (!get_option('wpmf_gallery_settings', false)) {
            add_option('wpmf_gallery_settings', $gallery_settings, '', 'yes');
        }
    }

    /**
     * Includes styles and some scripts
     *
     * @return void
     */
    public function loadAdminScripts()
    {
        global $current_screen;
        $params = array(
            'vars' => array(
                'wpmf_nonce' => wp_create_nonce('wpmf_nonce'),
                'ajaxurl'    => admin_url('admin-ajax.php'),
            )
        );

        wp_enqueue_script(
            'wpmf-folder-snackbar',
            plugins_url('/assets/js/snackbar.js', dirname(__FILE__)),
            array('jquery'),
            WPMF_VERSION
        );

        wp_enqueue_script(
            'wpmfimport-gallery',
            plugins_url('/assets/js/import_nextgen_gallery.js', dirname(__FILE__)),
            array('jquery'),
            WPMF_VERSION
        );

        wp_localize_script('wpmfimport-gallery', 'wpmfImportGallery', $params);
        if (!empty($current_screen->base) && $current_screen->base === 'settings_page_option-folder') {
            wp_enqueue_media();

            wp_enqueue_style(
                'wpmf-settings-google-icon',
                'https://fonts.googleapis.com/icon?family=Material+Icons'
            );

            // Register CSS
            wp_enqueue_style(
                'wpmf_ju_framework_styles',
                plugins_url('assets/wordpress-css-framework/css/style.css', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );

            wp_enqueue_script(
                'wpmf_ju_velocity_js',
                plugins_url('assets/wordpress-css-framework/js/velocity.min.js', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );
            wp_enqueue_script(
                'wpmf_ju_waves_js',
                plugins_url('assets/wordpress-css-framework/js/waves.js', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );
            wp_enqueue_script(
                'wpmf_ju_tabs_js',
                plugins_url('assets/wordpress-css-framework/js/tabs.js', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );

            wp_enqueue_script(
                'wpmf_ju_framework_js',
                plugins_url('assets/wordpress-css-framework/js/script.js', dirname(__FILE__)),
                array('wpmf_ju_tabs_js'),
                WPMF_VERSION
            );

            wp_enqueue_script(
                'wpmf-script-option',
                plugins_url('/assets/js/script-option.js', dirname(__FILE__)),
                array('jquery', 'plupload', 'wpmf-folder-snackbar'),
                WPMF_VERSION
            );

            wp_localize_script('wpmf-script-option', 'wpmfoption', $this->localizeScript());
            wp_enqueue_script(
                'wpmf-folder-tree-sync',
                plugins_url('/assets/js/sync_media/folder_tree_sync.js', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );
            wp_enqueue_script(
                'wpmf-folder-tree-categories',
                plugins_url('/assets/js/sync_media/folder_tree_categories.js', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );
            wp_enqueue_script(
                'wpmf-folder-tree-user',
                plugins_url('/assets/js/tree_users_media.js', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );
            wp_enqueue_script(
                'wpmf-script-qtip',
                plugins_url('/assets/js/jquery.qtip.min.js', dirname(__FILE__)),
                array('jquery'),
                WPMF_VERSION,
                true
            );
            wp_enqueue_script(
                'wpmf-general-thumb',
                plugins_url('/assets/js/regenerate_thumbnails.js', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );

            wp_enqueue_style(
                'wpmf-setting-style',
                plugins_url('/assets/css/setting_style.css', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );

            wp_enqueue_style(
                'wpmf-style-qtip',
                plugins_url('/assets/css/jquery.qtip.css', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );
        }
    }

    /**
     * Includes a script heartbeat
     *
     * @return void
     */
    public function heartbeatEnqueue()
    {
        wp_enqueue_script('heartbeat');
        add_action('admin_print_footer_scripts', array($this, 'heartbeatFooterJs'), 20);
    }

    /**
     * Inject our JS into the admin footer
     *
     * @return void
     */
    public function heartbeatFooterJs()
    {
        ?>
        <script>
            (function ($) {
                var wpmfajaxsyn = function (current, wpmf_limit_external, sync_token) {
                    $.ajax({
                        type: "POST",
                        url: ajaxurl,
                        dataType: 'json',
                        data: {
                            action: "wpmf_syncmedia",
                            current: current,
                            sync_token: sync_token,
                            wpmf_nonce: wpmf.vars.wpmf_nonce
                        },
                        success: function (response) {
                            if (response.status) {
                                if (response.continue) {
                                    wpmfajaxsyn(current, wpmf_limit_external, sync_token);
                                } else {
                                    if (wpmf_limit_external) {
                                        wpmfajaxsyn_external(wpmf_limit_external[current[0]]);
                                    }
                                }
                            }
                        }
                    });
                };

                var wpmfajaxsyn_external = function (current) {
                    $.ajax({
                        type: "POST",
                        url: ajaxurl,
                        dataType: 'json',
                        data: {
                            action: "wpmf_syncmedia_external",
                            current: current,
                            wpmf_nonce: wpmf.vars.wpmf_nonce
                        },
                        success: function (response) {
                            if (response.status === 'limit') {
                                wpmfajaxsyn_external(current);
                            }
                        }
                    });
                };
                // Hook into the heartbeat-send
                $(document).on('heartbeat-send', function (e, data) {
                    data['wpmf_heartbeat'] = 'wpmf_queue_process';
                });

                $(document).on('heartbeat-tick', function (e, data) {
                    // Only proceed if our EDD data is present
                    if (!data['wpmf_limit'] && !data['wpmf_limit_external']) {

                    } else if (data['wpmf_limit'] && !data['wpmf_limit_external']) {
                        $.each(data['wpmf_limit'], function (i, v) {
                            var sync_token = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                            wpmfajaxsyn(v, false, sync_token);
                        });
                    } else if (!data['wpmf_limit'] && data['wpmf_limit_external']) {
                        $.each(data['wpmf_limit_external'], function (i, v) {
                            wpmfajaxsyn_external(v);
                        });
                    } else {

                        $.each(data['wpmf_limit'], function (i, v) {
                            var sync_token = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                            wpmfajaxsyn(v, data['wpmf_limit_external'], sync_token);
                        });
                    }
                });
            }(jQuery));
        </script>
        <?php
    }

    /**
     * Ajax sync from FTP to media library
     *
     * @return void
     */
    public function syncMedia()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to sync to media library from FTP
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'sync_media');
        if (!$wpmf_capability) {
            wp_send_json(array('status' => true, 'continue' => false));
        }

        if (!empty($_POST['sync_token'])) {
            if (!get_option('wpmf_ftp_sync_time', false) && !get_option('wpmf_ftp_sync_token', false)) {
                add_option('wpmf_ftp_sync_time', time());
                add_option('wpmf_ftp_sync_token', $_POST['sync_token']);
            } else {
                if ($_POST['sync_token'] !== get_option('wpmf_ftp_sync_token')) {
                    // stop run
                    $timer = get_option('wpmf_time_sync', true);
                    if (time() - (int) get_option('wpmf_ftp_sync_time') < 60 * (int) $timer) {
                        wp_send_json(array('status' => false, 'continue' => false));
                    } else {
                        update_option('wpmf_ftp_sync_token', $_POST['sync_token']);
                        update_option('wpmf_ftp_sync_time', time());
                    }
                }
            }
        }

        $lists = get_option('wpmf_list_sync_media');
        if (empty($lists)) {
            wp_send_json(array('status' => true, 'continue' => false));
        }
        $folderID = $_POST['current'][0];
        if ((int) $folderID === 0) {
            $root_id            = get_option('wpmf_folder_root_id');
            $folderID = (int) $root_id;
        }
        $v        = $_POST['current'][1];
        $root     = $v['folder_ftp'];
        if (!file_exists($root)) {
            wp_send_json(array('status' => true, 'continue' => false));
        }

        $term = get_term($folderID, WPMF_TAXO);
        if (!empty($term)) {
            $count = $this->syncFTP($root, $folderID, true);
            if ($count >= 3) {
                wp_send_json(array('status' => true, 'continue' => true));
            } else {
                wp_send_json(array('status' => true, 'continue' => false));
            }
        }
    }

    /**
     * Sync FTP
     *
     * @param string  $dir         Path of root folder
     * @param integer $parent      Parent of folder
     * @param string  $folder_name Folder name
     * @param string  $is_root     Is root
     * @param string  $index       Index
     *
     * @return integer
     */
    public function syncFTP($dir, $parent, $folder_name = '', $is_root = true, $index = 0)
    {
        global $wpdb;

        /**
         * Filter the filetype allowed to be imported through ftp or folder import
         *
         * @param array  Filetypes allowed to be imported
         *
         * @return array
         */
        $this->type_import = apply_filters('wpmf_import_allowed_filetypes', $this->type_import);
        if ($is_root) {
            $termID = $parent;
        } else {
            require_once('ForceUTF8/Encoding.php');
            $folder_name = WpmfEncoding::toUTF8($folder_name);
            $root_id            = get_option('wpmf_folder_root_id');
            if ((int) $root_id === (int) $parent) {
                $folder_parent = 0;
            } else {
                $folder_parent = (int) $parent;
            }

            $folder_exist     = $wpdb->get_results($wpdb->prepare(
                'SELECT t1.term_id FROM ' . $wpdb->terms . ' as t1, ' . $wpdb->term_taxonomy . ' as t2
 WHERE taxonomy=%s AND name=%s AND parent=%d AND t1.term_id=t2.term_id',
                array(WPMF_TAXO, $folder_name, $folder_parent)
            ));

            if (empty($folder_exist)) {
                $inserted = wp_insert_term(
                    $folder_name,
                    WPMF_TAXO,
                    array(
                        'parent' => $folder_parent,
                        'slug'   => sanitize_title($folder_name) . WPMF_TAXO
                    )
                );

                if (is_array($inserted)) {
                    $termID = $inserted['term_id'];

                    /**
                     * Create a folder when syncing the files from FTP
                     *
                     * @param integer Created folder ID
                     * @param string  Created folder name
                     * @param integer Parent folder ID
                     * @param array   Extra informations
                     *
                     * @ignore Hook already documented
                     */
                    do_action('wpmf_create_folder', $termID, $folder_name, $folder_parent, array('trigger'=>'ftp_synchronization'));
                } else {
                    $termID = $inserted->error_data['term_exists'];
                }
            } else {
                $termID = $folder_exist[0]->term_id;
            }
        }

        // List files and directories inside $dir path
        $files = scandir($dir);
        $files = array_diff($files, array('..', '.'));
        if (count($files) === 0) {
            return $index;
        }

        // loop list files and directories
        $upload_dir = wp_upload_dir();
        foreach ($files as $file) {
            if ($index >= 3) {
                return $index;
            }

            $filepath = $dir . '/' . $file;
            if (is_dir($filepath)) { // is directory
                $index = $this->syncFTP($filepath, $termID, str_replace('  ', ' ', $file), false, $index);
            } else {
                // is file
                $file_url = str_replace(get_home_path(), site_url(), $dir) . $file;
                $info_file  = wp_check_filetype($filepath);
                $attachment = $wpdb->get_row($wpdb->prepare('SELECT ID FROM '. $wpdb->posts .' WHERE guid = %s', $file_url));
                if (!empty($attachment)) {
                    wp_set_object_terms((int) $attachment->ID, (int) $termID, WPMF_TAXO);
                } else {
                    if (empty($info_file) || (!empty($info_file) && empty($info_file['ext'])) || (!empty($info_file) && !empty($info_file['ext']) && !in_array(strtolower($info_file['ext']), $this->type_import))) {
                        continue;
                    }

                    $file_title = $file;
                    $file       = sanitize_file_name($file);
                    // check file exist , if not exist then insert file
                    $file_exists = $this->checkExistPost('/' . $file, $termID, $upload_dir);
                    if (!empty($file_exists)) {
                        continue;
                    }

                    $attachmentId = $this->insertAttachmentMetadata(
                        $upload_dir['path'],
                        $upload_dir['url'],
                        $file_title,
                        $file,
                        $filepath,
                        $info_file['type'],
                        $info_file['ext'],
                        $termID
                    );

                    if (!$attachmentId) {
                        continue;
                    }
                    $index++;
                }
            }
        }

        return $index;
    }

    /**
     * Ajax sync from media library to ftp
     *
     * @return void
     */
    public function syncMediaExternal()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to sync to FTP from media library
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'sync_media_external');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        $lists = get_option('wpmf_list_sync_media');
        if (empty($lists)) {
            wp_send_json(array('status' => false));
        }
        $folderID   = $_POST['current'][0];
        $ftp        = $_POST['current'][1];
        $folder_ftp = $ftp['folder_ftp'];
        if (!file_exists($folder_ftp)) {
            wp_send_json(array('status' => false));
        }
        $i = $this->syncFromMediaToFtp($folderID, $folder_ftp);
        if ($i >= 3) {
            wp_send_json(array('status' => 'limit'));
        }
    }

    /**
     * Ajax sync from media library to ftp
     *
     * @param integer $folderID   Id of folder on media library
     * @param string  $folder_ftp Path of folder on ftp
     *
     * @return integer
     */
    public function syncFromMediaToFtp($folderID, $folder_ftp)
    {
        $i = 0;
        // get file
        if (empty($folderID)) {
            $terms     = get_categories(array('taxonomy' => WPMF_TAXO, 'hide_empty' => false));
            $unsetTags = array();
            foreach ($terms as $term) {
                $unsetTags[] = $term->slug;
            }
            $args  = array(
                'posts_per_page' => - 1,
                'post_status'    => 'any',
                'post_type'      => 'attachment',
                'tax_query'      => array(
                    array(
                        'taxonomy'         => WPMF_TAXO,
                        'field'            => 'term_id',
                        'terms'            => $unsetTags,
                        'operator'         => 'NOT IN',
                        'include_children' => false
                    )
                ),
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'wpmf_drive_id',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key'     => 'wpmf_awsS3_info',
                        'compare' => 'NOT EXISTS'
                    )
                )
            );
            $query = new WP_Query($args);
            $files = $query->get_posts();
        } else {
            $files = get_objects_in_term($folderID, WPMF_TAXO);
        }

        // each files & create file
        foreach ($files as $fileID) {
            $pathfile    = get_attached_file($fileID);
            $infofile    = pathinfo($pathfile);
            $fileContent = file_get_contents($pathfile);
            if (!file_exists($folder_ftp . '/' . $infofile['basename'])) {
                file_put_contents($folder_ftp . '/' . $infofile['basename'], $fileContent);
                $i ++;
            }

            if ($i >= 3) {
                return $i;
            }
        }

        // get folder
        $subfolders = get_categories(array(
            'taxonomy'   => WPMF_TAXO,
            'parent'     => (int) $folderID,
            'hide_empty' => false
        ));
        if (count($subfolders) > 0) {
            foreach ($subfolders as $subfolder) {
                if ($subfolder->name === 'WP Media Folder Root') {
                    continue;
                }
                // create folder if not exist
                if (!file_exists($folder_ftp . '/' . $subfolder->name)) {
                    mkdir($folder_ftp . '/' . $subfolder->name);
                    $i ++;
                }
                $subfiles      = get_objects_in_term($subfolder->term_id, WPMF_TAXO);
                $subsubfolders = get_categories(
                    array(
                        'taxonomy'   => WPMF_TAXO,
                        'parent'     => (int) $subfolder->term_id,
                        'hide_empty' => false
                    )
                );
                if (!empty($subfiles) || !empty($subsubfolders)) {
                    $this->syncFromMediaToFtp($subfolder->term_id, $folder_ftp . '/' . $subfolder->name);
                }
                if ($i >= 3) {
                    return $i;
                }
            }
        }
        if ($i >= 3) {
            return $i;
        }

        return $i;
    }

    /**
     * Modify the data that goes back with the heartbeat-tick
     *
     * @param array $response The Heartbeat response.
     * @param array $data     The $_POST data sent.
     *
     * @return mixed $response
     */
    public function heartbeatReceived($response, $data)
    {
        /**
         * Filter check capability of current user to use heartbeat to sync
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('install_plugins'), 'heartbeat_sync');
        if (!$wpmf_capability) {
            return $response;
        }

        $sync         = get_option('wpmf_option_sync_media');
        $sync_externa = get_option('wpmf_option_sync_media_external');
        if (empty($sync) && empty($sync_externa)) {
            return $response;
        }

        if (isset($data['wpmf_heartbeat']) && $data['wpmf_heartbeat'] === 'wpmf_queue_process') {
            $lists     = get_option('wpmf_list_sync_media');
            $lastRun   = get_option('wpmf_lastRun_sync');
            $time_sync = get_option('wpmf_time_sync');
            if (empty($lists)) {
                return $response;
            }

            if ((int) $time_sync === 0) {
                return $response;
            }

            if (time() - (int) $lastRun < (int) $time_sync * 60) {
                return $response;
            }

            update_option('wpmf_lastRun_sync', time());
            foreach ($lists as $folderId => $v) {
                if (file_exists($v['folder_ftp'])) {
                    $current = array($folderId, $v);
                    // check option sync from ftp to media active
                    $option_sync = get_option('wpmf_option_sync_media');
                    if (!empty($option_sync)) {
                        $response['wpmf_limit'][$folderId] = $current;
                    }
                    // check option sync from media to ftp active
                    $option_external = get_option('wpmf_option_sync_media_external');
                    if (!empty($option_external)) {
                        $response['wpmf_limit_external'][$folderId] = $current;
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Check post exist to sync . If not exist then do sync
     *
     * @param string  $file       URL of file
     * @param integer $termID     Id of folder
     * @param array   $upload_dir Upload directory
     *
     * @return null|string
     */
    public function checkExistPost($file, $termID, $upload_dir)
    {
        global $wpdb;
        $infos = pathinfo($file);
        $lower_file = $infos['dirname'] . $infos['filename'] . '.' . strtolower($infos['extension']);
        if (file_exists($upload_dir['path'] . $file) || file_exists($upload_dir['path'] . $lower_file)) {
            $file = $infos['filename'];
        }
        $ext   = strtolower($infos['extension']);
        if (empty($termID)) {
            $found = $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'posts as p'
                . ' INNER JOIN ' . $wpdb->term_relationships . ' as t2 ON p.ID = t2.object_id'
                . ' INNER JOIN ' . $wpdb->term_taxonomy . ' as t1 ON t2.term_taxonomy_id = t1.term_taxonomy_id'
                . ' WHERE guid LIKE %s AND guid LIKE %s AND post_type = "attachment"',
                array('%' . $file, '%' . $ext)
            ));
        } else {
            $found = $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'posts as p'
                . ' INNER JOIN ' . $wpdb->term_relationships . ' as t2 ON p.ID = t2.object_id'
                . ' INNER JOIN ' . $wpdb->term_taxonomy . ' as t1 ON t2.term_taxonomy_id = t1.term_taxonomy_id'
                . ' WHERE guid LIKE %s AND guid LIKE %s AND post_type = "attachment"'
                . ' AND t1.term_id=%d',
                array('%' . $file . '%', '%' . $ext, $termID)
            ));
        }

        return $found;
    }

    /**
     * Localize a script.
     * Works only if the script has already been added.
     *
     * @return array
     */
    public function localizeScript()
    {
        $wpmf_folder_root_id = get_option('wpmf_folder_root_id');
        $root_media_root     = get_term_by('id', $wpmf_folder_root_id, WPMF_TAXO);
        $l18n = array(
            'confirm_delete_bucket'   => __('Do you really want to delete this bucket?', 'wpmf'),
            'delete_bucket'           => __('Delete', 'wpmf'),
            'cancel'                  => __('Cancel', 'wpmf'),
            'undimension'             => __('Remove dimension', 'wpmf'),
            'editdimension'           => __('Edit dimension', 'wpmf'),
            'unweight'                => __('Remove weight', 'wpmf'),
            'editweight'              => __('Edit weight', 'wpmf'),
            'media_library'           => __('Media Library', 'wpmf'),
            'error'                   => __('This value is already existing', 'wpmf'),
            'continue'                => __('Continue...', 'wpmf'),
            'regenerate_all_image_lb' => __('Regenerate all image thumbnails', 'wpmf'),
            'regenerate_watermark_lb' => __('Thumbnails Regeneration', 'wpmf'),
            'sync_s3_lb'              => __('Synchronize with Amazon S3', 'wpmf'),
            'sync_all_clouds_notice'  => __('Please wait while WP Media Folder is syncing the cloud files', 'wpmf')
        );
        return array(
            'l18n' => $l18n,
            'vars' => array(
                'wpmf_root_site'  => $this->validatePath(ABSPATH),
                'root_media_root' => $root_media_root->term_id,
                'image_path'      => WPMF_PLUGIN_URL . '/assets/images/'
            )
        );
    }

    /**
     * Add WP Media Folder setting menu
     *
     * @return void
     */
    public function addSettingsMenu()
    {
        $manage_options_cap = apply_filters('wpmf_manage_options_capability', 'manage_options');
        add_options_page(
            'Setting Folder Options',
            'WP Media Folder',
            $manage_options_cap,
            'option-folder',
            array($this, 'viewFolderOptions')
        );
    }

    /**
     * Render gallery settings
     *
     * @param array $gallery_configs Gallery config params
     *
     * @return string
     */
    public function gallerySettings($gallery_configs)
    {
        $html = '';
        ob_start();
        $default_label   = __('Default gallery theme', 'wpmf');
        $portfolio_label = __('Portfolio gallery theme', 'wpmf');
        $masonry_label   = __('Masonry gallery theme', 'wpmf');
        $slider_label    = __('Slider gallery theme', 'wpmf');

        $default_theme   = $this->themeSettings('default_theme', $gallery_configs, $default_label);
        $portfolio_theme = $this->themeSettings('portfolio_theme', $gallery_configs, $portfolio_label);
        $masonry_theme   = $this->themeSettings('masonry_theme', $gallery_configs, $masonry_label);
        $slider_theme    = $this->themeSettings('slider_theme', $gallery_configs, $slider_label);
        require_once(WP_MEDIA_FOLDER_PLUGIN_DIR . '/class/pages/gallery_settings/gallery_settings.php');
        $html .= ob_get_contents();
        ob_end_clean();
        return $html;
    }

    /**
     * Render gallery settings
     *
     * @param string $theme_name      Current theme name
     * @param array  $gallery_configs Gallery config params
     * @param string $theme_label     Current theme label
     *
     * @return string
     */
    public function themeSettings($theme_name, $gallery_configs, $theme_label)
    {
        ob_start();
        $settings = $gallery_configs['theme'][$theme_name];
        $slider_animation  = get_option('wpmf_slider_animation');
        require(WP_MEDIA_FOLDER_PLUGIN_DIR . '/class/pages/gallery_settings/theme_settings.php');
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    /**
     * View settings page and update option
     *
     * @return void
     */
    public function viewFolderOptions()
    {
        $upload_dir   = wp_upload_dir();
        $options = array(
            'delete_all_datas',
            'folder_design',
            'load_gif',
            'social_sharing',
            'hide_tree',
            'hide_remote_video',
            'watermark_margin',
            'watermark_image_scaling',
            'social_sharing_link',
            'format_mediatitle',
            'all_media_in_user_root',
            'caption_lightbox_gallery',
            'sync_method',
            'sync_periodicity',
            'show_folder_id'
        );
        if (isset($_POST['btn_wpmf_save'])) {
            if (empty($_POST['wpmf_nonce'])
                || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
                die();
            }

            if (is_plugin_active('wp-media-folder-gallery-addon/wp-media-folder-gallery-addon.php')) {
                if (isset($_POST['wpmf_gallery_settings'])) {
                    update_option('wpmf_gallery_settings', $_POST['wpmf_gallery_settings']);
                }

                if (isset($_POST['gallery_shortcode'])) {
                    wpmfSetOption('gallery_shortcode', $_POST['gallery_shortcode']);
                }
            }

            if (isset($_POST['wpmf_gallery_shortcode_cf'])) {
                wpmfSetOption('gallery_shortcode_cf', $_POST['wpmf_gallery_shortcode_cf']);
            }

            if (isset($_POST['wpmf_glr_settings'])) {
                wpmfSetOption('gallery_settings', $_POST['wpmf_glr_settings']);
            }

            if (isset($_POST['wpmf_watermark_exclude_folders'])) {
                $excludes = explode(',', $_POST['wpmf_watermark_exclude_folders']);
                wpmfSetOption('watermark_exclude_folders', $excludes);
            }

            if (isset($_POST['wpmf_color_singlefile'])) {
                update_option('wpmf_color_singlefile', json_encode($_POST['wpmf_color_singlefile']));
                $file = $upload_dir['basedir'] . '/wpmf/css/wpmf_single_file.css';
                if (!file_exists($upload_dir['basedir'] . '/wpmf/css/')) {
                    mkdir($upload_dir['basedir'] . '/wpmf/css/', 0777, true);
                }

                if (!file_exists($file)) {
                    fopen($file, 'w');
                }

                if (!file_exists($upload_dir['basedir'] . '/wpmf/images/')) {
                    mkdir($upload_dir['basedir'] . '/wpmf/images/', 0777, true);
                }

                if (!file_exists($upload_dir['basedir'] . '/wpmf/images/download.png')) {
                    copy(WP_MEDIA_FOLDER_PLUGIN_DIR . '/assets/images/download.png', $upload_dir['basedir'] . '/wpmf/images/download.png');
                }

                // get custom settings single file
                $wpmf_color_singlefile = json_decode(get_option('wpmf_color_singlefile'));
                $image_download        = '../images/download.png';
                // custom css by settings
                $custom_css = '
                        .wpmf-defile{
                            background: ' . $wpmf_color_singlefile->bgdownloadlink . ' url(' . $image_download . ')
                             no-repeat scroll 5px center !important;
                            color: ' . $wpmf_color_singlefile->fontdownloadlink . ' !important;
                            border: none !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                            text-shadow: none !important;
                            transition: all 0.2s ease 0s !important;
                            float: left !important;
                            margin: 7px !important;
                            padding: 10px 20px 10px 60px !important;
                            text-decoration: none !important;
                        }
                        
                        .wpmf-defile:hover{
                            background: ' . $wpmf_color_singlefile->hvdownloadlink . ' url(' . $image_download . ')
                             no-repeat scroll 5px center !important;
                            box-shadow: 1px 1px 12px #ccc !important;
                            color: ' . $wpmf_color_singlefile->hoverfontcolor . ' !important;
                        }
                        
                        .wp-block-file .wp-block-file__button {
                            color: ' . $wpmf_color_singlefile->fontdownloadlink . ' !important;
                            background: ' . $wpmf_color_singlefile->bgdownloadlink . ' !important;
                            box-shadow: none !important;
                        }
                        
                        .wp-block-file__button:hover {
                            background: ' . $wpmf_color_singlefile->hvdownloadlink . ' !important;
                            color: ' . $wpmf_color_singlefile->hoverfontcolor . ' !important;
                        }
                        ';

                // write custom css to file wpmf_single_file.css
                file_put_contents(
                    $file,
                    $custom_css
                );
            }

            // update selected dimension
            if (isset($_POST['dimension'])) {
                $selected_d = json_encode($_POST['dimension']);
                update_option('wpmf_selected_dimension', $selected_d);
            } else {
                update_option('wpmf_selected_dimension', '[]');
            }

            // update selected weight
            if (isset($_POST['weight'])) {
                $selected_w = array();
                foreach ($_POST['weight'] as $we) {
                    $s            = explode(',', $we);
                    $selected_w[] = array($s[0], $s[1]);
                }

                $se_w = json_encode($selected_w);
                update_option('wpmf_weight_selected', $se_w);
            } else {
                update_option('wpmf_weight_selected', '[]');
            }

            // update padding gallery
            if (isset($_POST['padding_gallery'])) {
                $padding_themes = $_POST['padding_gallery'];
                foreach ($padding_themes as $key => $padding_theme) {
                    if (!is_numeric($padding_theme)) {
                        if ($key === 'wpmf_padding_masonry') {
                            $padding_theme = 5;
                        } else {
                            $padding_theme = 10;
                        }
                    }
                    $padding_theme = (int) $padding_theme;
                    if ($padding_theme > 30 || $padding_theme < 0) {
                        if ($key === 'wpmf_padding_masonry') {
                            $padding_theme = 5;
                        } else {
                            $padding_theme = 10;
                        }
                    }

                    $pad = get_option($key);
                    if (!isset($pad)) {
                        add_option($key, $padding_theme);
                    } else {
                        update_option($key, $padding_theme);
                    }
                }
            }

            // update list size
            if (isset($_POST['size_value'])) {
                $size_value = json_encode($_POST['size_value']);
                update_option('wpmf_gallery_image_size_value', $size_value);
            }

            if (isset($_POST['wpmf_patern'])) {
                $pattern = trim($_POST['wpmf_patern']);
                update_option('wpmf_patern_rename', $pattern);
            }

            if (isset($_POST['input_time_sync'])) {
                if ((int) $_POST['input_time_sync'] < 0) {
                    $time_sync = (int) $this->default_time_sync;
                } else {
                    $time_sync = (int) $_POST['input_time_sync'];
                }
                update_option('wpmf_time_sync', $time_sync);
            }

            // update folder design option
            foreach ($options as $option) {
                if (isset($_POST[$option])) {
                    wpmfSetOption($option, $_POST[$option]);
                }
            }

            // update checkbox options
            $options_name = array(
                'delete_all_datas',
                'wpmf_option_mediafolder',
                'wpmf_create_folder',
                'wpmf_option_override',
                'wpmf_option_duplicate',
                'wpmf_active_media',
                'wpmf_usegellery',
                'wpmf_useorder',
                'wpmf_option_searchall',
                'wpmf_option_media_remove',
                'wpmf_usegellery_lightbox',
                'wpmf_media_rename',
                'wpmf_option_singlefile',
                'wpmf_option_sync_media',
                'wpmf_option_sync_media_external',
                'wpmf_slider_animation',
                'wpmf_option_countfiles',
                'wpmf_option_lightboximage',
                'wpmf_option_hoverimg',
                'wpmf_option_image_watermark',
                'wpmf_watermark_position',
                'wpmf_image_watermark_apply',
                'wpmf_options_format_title',
                'wpmf_watermark_image',
                'wpmf_watermark_image_id'
            );

            foreach ($options_name as $option) {
                if (isset($_POST[$option])) {
                    update_option($option, $_POST[$option]);
                }
            }

            if (isset($_POST['wpmf_active_media']) && (int) $_POST['wpmf_active_media'] === 1) {
                $wpmf_checkbox_tree = get_option('wpmf_checkbox_tree');
                if (!empty($wpmf_checkbox_tree)) {
                    $current_parrent = get_term($wpmf_checkbox_tree, WPMF_TAXO);
                    if (!empty($current_parrent)) {
                        $term_user_root = $wpmf_checkbox_tree;
                    } else {
                        $term_user_root = 0;
                    }
                } else {
                    $term_user_root = 0;
                }

                if (isset($_POST['wpmf_checkbox_tree']) && (int) $_POST['wpmf_checkbox_tree'] !== (int) $term_user_root) {
                    global $wpdb;
                    $lists_terms = $wpdb->get_results($wpdb->prepare('SELECT t1.term_id, t1.term_group FROM ' . $wpdb->terms . ' as t1 INNER JOIN ' . $wpdb->term_taxonomy . ' mt ON mt.term_id = t1.term_id AND mt.parent = %d WHERE t1.term_group !=0', array($term_user_root)));
                    update_option('wpmf_checkbox_tree', $_POST['wpmf_checkbox_tree']);
                    $term_user_root = $_POST['wpmf_checkbox_tree'];
                    if (!empty($lists_terms)) {
                        foreach ($lists_terms as $lists_term) {
                            $user_data  = get_userdata($lists_term->term_group);
                            $user_roles = $user_data->roles;
                            $role       = array_shift($user_roles);
                            if (isset($role) && $role !== 'administrator') {
                                wp_update_term(
                                    (int) $lists_term->term_id,
                                    WPMF_TAXO,
                                    array('parent' => (int) $term_user_root)
                                );

                                /**
                                 * Update root folder for users
                                 *
                                 * @param integer Folder moved ID
                                 * @param string  Destination folder ID
                                 * @param array   Extra informations
                                 *
                                 * @ignore Hook already documented
                                 */
                                do_action('wpmf_move_folder', $lists_term->term_id, $term_user_root, array('trigger'=>'update_user_root_folder'));
                            }
                        }
                    }
                }
            }

            /**
             * Save settings
             *
             * @ignore Hook already documented
             */
            do_action('wpmf_save_settings');
        }

        foreach ($options as $option) {
            ${$option} = wpmfGetOption($option);
        }

        $option_mediafolder         = get_option('wpmf_option_mediafolder');
        $create_folder              = get_option('wpmf_create_folder');
        $option_override            = get_option('wpmf_option_override');
        $option_duplicate           = get_option('wpmf_option_duplicate');
        $active_media               = get_option('wpmf_active_media');
        $usegellery                 = get_option('wpmf_usegellery');
        $useorder                   = get_option('wpmf_useorder');
        $option_searchall           = get_option('wpmf_option_searchall');
        $option_media_remove        = get_option('wpmf_option_media_remove');
        $usegellery_lightbox        = get_option('wpmf_usegellery_lightbox');
        $media_rename               = get_option('wpmf_media_rename');
        $option_singlefile          = get_option('wpmf_option_singlefile');
        $option_sync_media          = get_option('wpmf_option_sync_media');
        $option_sync_media_external = get_option('wpmf_option_sync_media_external');
        $option_countfiles          = get_option('wpmf_option_countfiles');
        $option_lightboximage       = get_option('wpmf_option_lightboximage');
        $option_hoverimg            = get_option('wpmf_option_hoverimg');
        $option_image_watermark     = get_option('wpmf_option_image_watermark');
        $watermark_position         = get_option('wpmf_watermark_position');
        $image_watermark_apply      = get_option('wpmf_image_watermark_apply');
        $options_format_title       = get_option('wpmf_options_format_title');
        $watermark_image            = get_option('wpmf_watermark_image');
        $watermark_image_id         = get_option('wpmf_watermark_image_id');

        $padding_masonry   = get_option('wpmf_padding_masonry');
        $padding_portfolio = get_option('wpmf_padding_portfolio');
        $size_selected     = json_decode(get_option('wpmf_gallery_image_size_value'));
        $wpmf_pattern      = get_option('wpmf_patern_rename');
        $s_dimensions          = get_option('wpmf_default_dimension');
        $a_dimensions          = json_decode($s_dimensions);
        $string_s_de           = get_option('wpmf_selected_dimension');
        $array_s_de            = json_decode($string_s_de);
        $s_weights             = get_option('wpmf_weight_default');
        $a_weights             = json_decode($s_weights);
        $string_s_we           = get_option('wpmf_weight_selected');
        $array_s_we            = json_decode($string_s_we);
        $wpmf_color_singlefile = json_decode(get_option('wpmf_color_singlefile'));
        $wpmf_list_sync_media  = get_option('wpmf_list_sync_media');
        $time_sync             = get_option('wpmf_time_sync');

        if (!empty($wpmf_list_sync_media)) {
            foreach ($wpmf_list_sync_media as $k => $v) {
                if (!empty($k)) {
                    $term = get_term($k, WPMF_TAXO);
                    if (!empty($term)) {
                        $this->getCategoryDir($k, $term->parent, $term->name);
                    }
                } else {
                    $this->breadcrumb_category[0] = '/';
                }
            }
        }

        if (is_plugin_active('wp-media-folder-addon/wp-media-folder-addon.php')) {
            if (file_exists(WP_PLUGIN_DIR . '/wp-media-folder-addon/class/wpmfGoogle.php')) {
                require_once(WP_PLUGIN_DIR . '/wp-media-folder-addon/class/wpmfGoogle.php');
            }
            if (file_exists(WP_PLUGIN_DIR . '/wp-media-folder-addon/class/wpmfDropbox.php')) {
                require_once(WP_PLUGIN_DIR . '/wp-media-folder-addon/class/wpmfDropbox.php');
            }
            if (file_exists(WP_PLUGIN_DIR . '/wp-media-folder-addon/class/wpmfOneDrive.php')) {
                require_once(WP_PLUGIN_DIR . '/wp-media-folder-addon/class/wpmfOneDrive.php');
            }
            if (file_exists(WP_PLUGIN_DIR . '/wp-media-folder-addon/class/wpmfHelper.php')) {
                require_once(WP_PLUGIN_DIR . '/wp-media-folder-addon/class/wpmfHelper.php');
            }

            // save settings for google drive
            $googleconfig = get_option('_wpmfAddon_cloud_config');
            $googleDrive  = new WpmfAddonGoogleDrive();
            if (!is_array($googleconfig) || (is_array($googleconfig) && empty($googleconfig))) {
                $googleconfig = array(
                    'googleClientId'     => '',
                    'googleClientSecret' => ''
                );
            }

            if (empty($googleconfig['link_type'])) {
                $googleconfig['link_type'] = 'private';
            }

            if (isset($_POST['googleClientId']) && isset($_POST['googleClientSecret'])) {
                $googleconfig['googleClientId']     = trim($_POST['googleClientId']);
                $googleconfig['googleClientSecret'] = trim($_POST['googleClientSecret']);

                if (isset($_POST['google_link_type'])) {
                    $googleconfig['link_type'] = $_POST['google_link_type'];
                }

                update_option('_wpmfAddon_cloud_config', $googleconfig);
                $googleconfig = get_option('_wpmfAddon_cloud_config');
                $googleDrive  = new WpmfAddonGoogleDrive();
            }

            // save settings for dropbox
            $Dropbox                  = new WpmfAddonDropbox();
            $dropboxconfig = get_option('_wpmfAddon_dropbox_config');
            if (empty($dropboxconfig)) {
                $dropboxconfig = array('dropboxKey' => '', 'dropboxSecret' => '');
            }

            $dropboxconfig = get_option('_wpmfAddon_dropbox_config');
            if (!is_array($dropboxconfig) || (is_array($dropboxconfig) && empty($dropboxconfig))) {
                $dropboxconfig = array(
                    'dropboxKey'     => '',
                    'dropboxSecret' => ''
                );
            }

            if (empty($dropboxconfig['link_type'])) {
                $dropboxconfig['link_type'] = 'private';
            }

            $dropbox_error = '';
            if (isset($_POST['dropboxKey']) && isset($_POST['dropboxSecret'])) {
                $dropboxconfig['dropboxKey']     = trim($_POST['dropboxKey']);
                $dropboxconfig['dropboxSecret'] = trim($_POST['dropboxSecret']);
                if (isset($_POST['dropbox_link_type'])) {
                    $dropboxconfig['link_type'] = $_POST['dropbox_link_type'];
                }
                update_option('_wpmfAddon_dropbox_config', $dropboxconfig);
                if (!empty($_POST['dropboxAuthor'])) {
                    //convert code authorCOde to Token

                    try {
                        $list = $Dropbox->convertAuthorizationCode($_POST['dropboxAuthor']);
                        if (!empty($list['accessToken'])) {
                            if (!isset($dropboxconfig['dropboxToken'])) {
                                $dropboxconfig['first_connected'] = 1;
                            }
                            //save accessToken to database
                            $dropboxconfig['dropboxToken'] = $list['accessToken'];
                        }

                        update_option('_wpmfAddon_dropbox_config', $dropboxconfig);
                        $dropboxconfig = get_option('_wpmfAddon_dropbox_config');
                        $Dropbox                  = new WpmfAddonDropbox();
                    } catch (Exception $e) {
                        $dropbox_error = $e->getMessage();
                    }
                }
            }

            /**
             * Filter render google settings
             *
             * @param string HTML default
             * @param object WpmfAddonGoogleDrive class
             * @param array  Google drive config
             *
             * @return string
             *
             * @internal
             */
            $html_tabgoogle = apply_filters('wpmfaddon_ggsettings', '', $googleDrive, $googleconfig);

            /**
             * Filter render dropbox settings
             *
             * @param string HTML default
             * @param object WpmfAddonDropbox class
             * @param array  Dropbox config
             * @param string Dropbox error message
             *
             * @return string
             *
             * @internal
             */
            $html_tabdropbox = apply_filters('wpmfaddon_dbxsettings', '', $Dropbox, $dropboxconfig, $dropbox_error);

            /**
             * Filter render onedrive settings
             *
             * @param string HTML default
             *
             * @return string
             *
             * @internal
             */
            $html_onedrive_settings = apply_filters('wpmfaddon_onedrivesettings', '');

            /**
             * Filter render onedrive settings
             *
             * @param string HTML default
             *
             * @return string
             *
             * @internal
             */
            $html_onedrive_business_settings = apply_filters('wpmfaddon_onedrive_business_settings', '');

            /**
             * Filter render Amazon s3 settings
             *
             * @param string HTML default
             *
             * @return string
             *
             * @internal
             */
            $html_tabaws3 = apply_filters('wpmfaddon_aws3settings', '');

            /**
             * Filter render synchronization settings
             *
             * @param string HTML default
             *
             * @return string
             *
             * @internal
             */
            $synchronization = apply_filters('wpmfaddon_synchronization_settings', '');
        }

        // get defaul gallery settings
        $gallery_configs          = wpmfGetOption('gallery_settings');
        $glrdefault_settings_html = $this->gallerySettings($gallery_configs);

        // get gallery settings
        if (is_plugin_active('wp-media-folder-gallery-addon/wp-media-folder-gallery-addon.php')) {
            /**
             * Action render gallery settings
             *
             * @param integer       Default html
             *
             * @return string
             *
             * @internal
             */
            $gallery_settings_html     = apply_filters('wpmfgallery_settings', '');

            /**
             * Action render gallery shortcode settings
             *
             * @param integer       Default html
             *
             * @return string
             *
             * @internal
             */
            $gallery_shortcode_html    = apply_filters('wpmfgallery_shortcode', '');
        }

        if (isset($_POST['setting_tab_value'])) {
            $tab = $_POST['setting_tab_value'];
        } elseif (isset($setting_tab_value)) {
            $tab = $setting_tab_value;
        } elseif (isset($_GET['tab'])) {
            $tab = $_GET['tab'];
        } else {
            $tab = 'wpmf-general';
        }

        require_once(WP_MEDIA_FOLDER_PLUGIN_DIR . 'class/pages/settings/wp-folder-options.php');
    }

    /**
     * Get folder breadcrumb
     *
     * @param integer $id     Folder id
     * @param integer $parent Folder parent
     * @param string  $string Current breadcrumb
     *
     * @return void
     */
    public function getCategoryDir($id, $parent, $string)
    {
        $this->breadcrumb_category[$id] = '/' . $string . '/';
        if (!empty($parent)) {
            $term = get_term($parent, WPMF_TAXO);
            $this->getCategoryDir($id, $term->parent, $term->name . '/' . $string);
        }
    }

    /**
     * Display info after save settings
     *
     * @return void
     */
    public function getSuccessMessage()
    {
        require_once(WP_MEDIA_FOLDER_PLUGIN_DIR . 'class/pages/settings/saved_info.php');
    }

    /**
     * Ajax import from next gallery to media library
     *
     * @return void
     */
    public function importGallery()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to import nextgen gallery
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'import_nextgen_gallery');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        global $wpdb;
        if ($_POST['doit'] === 'true') {
            update_option('wpmf_import_nextgen_gallery', 'yes');
        } else {
            update_option('wpmf_import_nextgen_gallery', 'no');
        }

        if ($_POST['doit'] === 'true') {
            $loop       = 0;
            $limit      = 3;
            $gallerys   = $wpdb->get_results('SELECT path,title,gid FROM ' . $wpdb->prefix . 'ngg_gallery', OBJECT);
            $site_path  = get_home_path();
            $upload_dir = wp_upload_dir();

            if (is_multisite()) {
                $checks = get_term_by('name', 'sites-' . get_current_blog_id(), WPMF_TAXO);
                if (empty($checks) || ((!empty($checks) && (int) $checks->parent !== 0))) {
                    $sites_inserted = wp_insert_term('sites-' . get_current_blog_id(), WPMF_TAXO, array('parent' => 0));
                    if (is_wp_error($sites_inserted)) {
                        $glrId = $checks->term_id;
                    } else {
                        $glrId = $sites_inserted['term_id'];

                        /**
                         * Create a folder when importing from Nextgen Gallery
                         *
                         * @param integer Created folder ID
                         * @param string  Created folder name
                         * @param integer Parent folder ID
                         * @param array   Extra informations
                         *
                         * @ignore Hook already documented
                         */
                        do_action('wpmf_create_folder', $glrId, 'sites-' . get_current_blog_id(), 0, array('trigger'=>'nextgen_gallery_import'));
                    }
                } else {
                    $glrId = $checks->term_id;
                }
            } else {
                $glrId = 0;
            }

            if (count($gallerys) > 0) {
                foreach ($gallerys as $gallery) {
                    $gallery_path = $gallery->path;
                    $gallery_path = str_replace('\\', '/', $gallery_path);
                    // create folder from nextgen gallery
                    $wpmf_category = get_term_by('name', $gallery->title, WPMF_TAXO);
                    if (empty($wpmf_category) || ((!empty($wpmf_category)
                                                   && (int) $wpmf_category->parent !== (int) $glrId))
                    ) {
                        $inserted = wp_insert_term($gallery->title, WPMF_TAXO, array('parent' => $glrId));
                        if (is_wp_error($inserted)) {
                            $termID = $wpmf_category->term_id;
                        } else {
                            $termID = $inserted['term_id'];

                            /**
                             * Create a sub folder when importing from Nextgen Gallery
                             *
                             * @param integer Created folder ID
                             * @param string  Created folder name
                             * @param integer Parent folder ID
                             * @param array   Extra informations
                             *
                             * @ignore Hook already documented
                             */
                            do_action('wpmf_create_folder', $termID, $gallery->title, $glrId, array('trigger'=>'nextgen_gallery_import'));
                        }
                    } else {
                        $termID = $wpmf_category->term_id;
                    }

                    // =========================
                    $image_childs = $wpdb->get_results($wpdb->prepare(
                        'SELECT pid,filename FROM  ' . $wpdb->prefix . 'ngg_pictures WHERE galleryid = %d',
                        array(
                            $gallery->gid
                        )
                    ), OBJECT);
                    if (count($image_childs) > 0) {
                        foreach ($image_childs as $image_child) {
                            if ($loop >= $limit) {
                                wp_send_json('error time'); // run again ajax
                            } else {
                                $check_import = $wpdb->get_var($wpdb->prepare(
                                    'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'posts WHERE post_content=%s',
                                    array(
                                        '[wpmf-nextgen-image-' . $image_child->pid . ']'
                                    )
                                ));
                                // check imported
                                if ((int) $check_import === 0) {
                                    $url_image    = $site_path . DIRECTORY_SEPARATOR . $gallery_path;
                                    $url_image    .= DIRECTORY_SEPARATOR . $image_child->filename;
                                    $file_headers = get_headers($url_image);
                                    if ($file_headers[0] !== 'HTTP/1.1 404 Not Found') {
                                        $info = pathinfo($url_image);
                                        if (!empty($info) && !empty($info['extension'])) {
                                            $ext = '.' . $info['extension'];
                                            $filename       = sanitize_file_name($image_child->filename);
                                            // check file exist , if not exist then insert file
                                            $pid = $this->checkExistPost('/' . $filename, $termID, $upload_dir);
                                            if (empty($pid)) {
                                                $upload = copy($url_image, $upload_dir['path'] . '/' . $filename);
                                                // upload images
                                                if ($upload) {
                                                    if (($ext === '.jpg')) {
                                                        $post_mime_type = 'image/jpeg';
                                                    } else {
                                                        $post_mime_type = 'image/' . substr($ext, 1);
                                                    }
                                                    $attachment = array(
                                                        'guid'           => $upload_dir['url'] . '/' . $filename,
                                                        'post_mime_type' => $post_mime_type,
                                                        'post_title'     => str_replace($ext, '', $filename),
                                                        'post_content'   => '[wpmf-nextgen-image-' . $image_child->pid . ']',
                                                        'post_status'    => 'inherit'
                                                    );

                                                    $image_path = $upload_dir['path'] . '/' . $filename;
                                                    $attach_id  = wp_insert_attachment($attachment, $image_path);
                                                    // create image in folder
                                                    wp_set_object_terms((int) $attach_id, (int) $termID, WPMF_TAXO, false);
                                                    $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
                                                    wp_update_attachment_metadata($attach_id, $attach_data);

                                                    /**
                                                     * Set attachment folder after image import from nextgen gallery
                                                     *
                                                     * @param integer Attachment ID
                                                     * @param integer Target folder
                                                     * @param array   Extra informations
                                                     *
                                                     * @ignore Hook already documented
                                                     */
                                                    do_action('wpmf_attachment_set_folder', $attach_id, $termID, array('trigger'=>'nextgen_gallery_import'));
                                                }
                                                $loop ++;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * This function do import wordpress category default
     *
     * @return void
     */
    public static function importCategories()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        require_once(WP_MEDIA_FOLDER_PLUGIN_DIR . '/class/class-main.php');
        WpMediaFolder::importCategories();
    }

    /**
     * Ajax add dimension in settings
     *
     * @return void
     */
    public function addDimension()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to add dimension setting
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'add_dimension_setting');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        if (isset($_POST['width_dimension']) && isset($_POST['height_dimension'])) {
            $min           = $_POST['width_dimension'];
            $max           = $_POST['height_dimension'];
            $new_dimension = $min . 'x' . $max;
            $s_dimensions  = get_option('wpmf_default_dimension');
            $a_dimensions  = json_decode($s_dimensions);
            if (!in_array($new_dimension, $a_dimensions)) {
                array_push($a_dimensions, $new_dimension);
                update_option('wpmf_default_dimension', json_encode($a_dimensions));
                wp_send_json($new_dimension);
            } else {
                wp_send_json(false);
            }
        }
    }

    /**
     * Ajax edit selected size and weight filter
     *
     * @param string $option_name Option name
     * @param array  $old_value   Old value
     * @param array  $new_value   New value
     *
     * @return void
     */
    public function editSelected($option_name, $old_value, $new_value)
    {
        $s_selected = get_option($option_name);
        $a_selected = json_decode($s_selected);

        if (in_array($old_value, $a_selected)) {
            $key_selected              = array_search($old_value, $a_selected);
            $a_selected[$key_selected] = $new_value;
            update_option($option_name, json_encode($a_selected));
        }
    }

    /**
     * Ajax remove selected size and weight filter
     *
     * @param string $option_name Option name
     * @param array  $value       Value of option
     *
     * @return void
     */
    public function removeSelected($option_name, $value)
    {
        $s_selected = get_option($option_name);
        $a_selected = json_decode($s_selected);
        if (in_array($value, $a_selected)) {
            $key_selected = array_search($value, $a_selected);
            unset($a_selected[$key_selected]);
            $a_selected = array_slice($a_selected, 0, count($a_selected));
            update_option($option_name, json_encode($a_selected));
        }
    }

    /**
     * Ajax remove size and weight filter
     *
     * @return void
     */
    public function removeDimension()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to remove dimension setting
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'remove_dimension_setting');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        if (isset($_POST['value']) && $_POST['value'] !== '') {
            // remove dimension
            $s_dimensions = get_option('wpmf_default_dimension');
            $a_dimensions = json_decode($s_dimensions);
            if (in_array($_POST['value'], $a_dimensions)) {
                $key = array_search($_POST['value'], $a_dimensions);
                unset($a_dimensions[$key]);
                $a_dimensions = array_slice($a_dimensions, 0, count($a_dimensions));
                $update_demen = update_option('wpmf_default_dimension', json_encode($a_dimensions));
                if (is_wp_error($update_demen)) {
                    wp_send_json($update_demen->get_error_message());
                } else {
                    $this->removeSelected('wpmf_selected_dimension', $_POST['value']); // remove selected
                    wp_send_json(true);
                }
            } else {
                wp_send_json(false);
            }
        }
    }

    /**
     * Ajax edit size and weight filter
     *
     * @return void
     */
    public function edit()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to edit dimension and weight setting
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'edit_dimension_weight_setting');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        if (isset($_POST['old_value']) && $_POST['old_value'] !== ''
            && isset($_POST['new_value']) && $_POST['new_value'] !== ''
        ) {
            $label = $_POST['label'];
            if ($label === 'dimension') {
                $s_dimensions = get_option('wpmf_default_dimension');
                $a_dimensions = json_decode($s_dimensions);
                if (in_array($_POST['old_value'], $a_dimensions)
                    && !in_array($_POST['new_value'], $a_dimensions)
                ) {
                    $key                = array_search($_POST['old_value'], $a_dimensions);
                    $a_dimensions[$key] = $_POST['new_value'];
                    $update_demen       = update_option('wpmf_default_dimension', json_encode($a_dimensions));
                    if (is_wp_error($update_demen)) {
                        wp_send_json($update_demen->get_error_message());
                    } else {
                        $this->editSelected('wpmf_selected_dimension', $_POST['old_value'], $_POST['new_value']); // edit selected
                        wp_send_json(array('value' => $_POST['new_value']));
                    }
                } else {
                    wp_send_json(false);
                }
            } else {
                $s_weights = get_option('wpmf_weight_default');
                $a_weights = json_decode($s_weights);
                if (isset($_POST['unit'])) {
                    $old_values = explode(',', $_POST['old_value']);
                    $old        = array($old_values[0], $old_values[1]);
                    $new_values = explode(',', $_POST['new_value']);
                    $new        = array($new_values[0], $new_values[1]);

                    if (in_array($old, $a_weights) && !in_array($new, $a_weights)) {
                        $key             = array_search($old, $a_weights);
                        $a_weights[$key] = $new;
                        $new_labels      = explode('-', $new_values[0]);
                        if ($new_values[1] === 'kB') {
                            $label = ($new_labels[0] / 1024) . ' ' . $new_values[1];
                            $label .= '-';
                            $label .= ($new_labels[1] / 1024) . ' ' . $new_values[1];
                        } else {
                            $label = ($new_labels[0] / (1024 * 1024)) . ' ';
                            $label .= $new_values[1] . '-' . ($new_labels[1] / (1024 * 1024)) . ' ' . $new_values[1];
                        }
                        $update_weight = update_option('wpmf_weight_default', json_encode($a_weights));
                        if (is_wp_error($update_weight)) {
                            wp_send_json($update_weight->get_error_message());
                        } else {
                            $this->editSelected('wpmf_weight_selected', $old, $new); // edit selected
                            wp_send_json(array('value' => $new_values[0], 'label' => $label));
                        }
                    } else {
                        wp_send_json(false);
                    }
                }
            }
        }
    }

    /**
     * Ajax add size to size filter
     *
     * @return void
     */
    public function addWeight()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to add weight setting
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'add_weight_setting');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        if (isset($_POST['min_weight']) && isset($_POST['max_weight'])) {
            if (!$_POST['unit'] || $_POST['unit'] === 'kB') {
                $min  = $_POST['min_weight'] * 1024;
                $max  = $_POST['max_weight'] * 1024;
                $unit = 'kB';
            } else {
                $min  = $_POST['min_weight'] * 1024 * 1024;
                $max  = $_POST['max_weight'] * 1024 * 1024;
                $unit = 'MB';
            }
            $label      = $_POST['min_weight'] . ' ' . $unit . '-' . $_POST['max_weight'] . ' ' . $unit;
            $new_weight = array($min . '-' . $max, $unit);

            $s_weights = get_option('wpmf_weight_default');
            $a_weights = json_decode($s_weights);
            if (!in_array($new_weight, $a_weights)) {
                array_push($a_weights, $new_weight);
                update_option('wpmf_weight_default', json_encode($a_weights));
                wp_send_json(array('key' => $min . '-' . $max, 'unit' => $unit, 'label' => $label));
            } else {
                wp_send_json(false);
            }
        }
    }

    /**
     * Ajax remove size to size filter
     *
     * @return void
     */
    public function removeWeight()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to remove weight setting
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'remove_weight_setting');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        if (isset($_POST['value']) && $_POST['value'] !== '') {
            $s_weights     = get_option('wpmf_weight_default');
            $a_weights     = (array) json_decode($s_weights);
            $unit          = $_POST['unit'];
            $weight_remove = array($_POST['value'], $unit);
            if (in_array($weight_remove, $a_weights)) {
                $key = array_search($weight_remove, $a_weights);
                unset($a_weights[$key]);
                $a_weights     = array_slice($a_weights, 0, count($a_weights));
                $update_weight = update_option('wpmf_weight_default', json_encode($a_weights));
                if (is_wp_error($update_weight)) {
                    wp_send_json($update_weight->get_error_message());
                } else {
                    $this->removeSelected('wpmf_weight_selected', $weight_remove);  // remove selected
                    wp_send_json(true);
                }
            } else {
                wp_send_json(false);
            }
        }
    }

    /**
     * Ajax generate thumbnail
     *
     * @return void
     */
    public function regenerateThumbnail()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to regenerate image thumbnail
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('manage_options'), 'regenerate_thumbnail');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }

        remove_filter('add_attachment', array($GLOBALS['wp_media_folder'], 'afterUpload'));
        global $wpdb;
        $limit        = 1;
        $offset       = ((int) $_POST['paged'] - 1) * $limit;
        $count_images = $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(ID) FROM ' . $wpdb->posts . ' WHERE  post_type = "attachment"
             AND post_mime_type LIKE %s AND guid  NOT LIKE %s',
            array('image%', '%.svg')
        ));

        $present     = (100 / $count_images) * $limit;
        $k           = 0;
        $urls        = array();
        $query = new WP_Query(array(
            'posts_per_page' => (int) $limit,
            'offset' => (int) $offset,
            'post_type' => 'attachment',
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC',
            'post_mime_type' => array('image/jpeg', 'image/jpg', 'image/gif', 'image/png', 'image/bmp', 'image/tiff', 'image/x-icon'),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'wpmf_drive_id',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key'     => 'wpmf_awsS3_info',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        $attachments = $query->get_posts();
        if (empty($attachments)) {
            wp_send_json(array('status' => 'ok', 'paged' => 0, 'success' => $this->result_gennerate_thumb));
        }

        foreach ($attachments as $image) {
            $fullsizepath = get_attached_file($image->ID);
            $wpmf_size_filetype = wpmfGetSizeFiletype($image->ID);
            $size               = $wpmf_size_filetype['size'];
            update_post_meta($image->ID, 'wpmf_size', $size);
            if (false === $fullsizepath || !file_exists($fullsizepath)) {
                $message                      = sprintf(
                    __('The originally uploaded image file cannot be found at %s', 'wpmf'),
                    '<code>' . esc_html($fullsizepath) . '</code>'
                );
                $this->result_gennerate_thumb .= sprintf(
                    __('<p>&quot;%1$s&quot; (ID %2$s) failed to resize. The error message was: %3$s</p>', 'wpmf'),
                    esc_html(get_the_title($image->ID)),
                    $image->ID,
                    $message
                );
                wp_send_json(
                    array(
                        'status'  => 'limit',
                        'success' => $this->result_gennerate_thumb
                    )
                );
            }

            $metadata  = wp_generate_attachment_metadata($image->ID, $fullsizepath);
            $url_image = wp_get_attachment_url($image->ID);
            $urls[]    = $url_image;
            if (is_wp_error($metadata)) {
                $message                      = $metadata->get_error_message();
                $this->result_gennerate_thumb .= sprintf(
                    __('<p>&quot;%1$s&quot; (ID %2$s) failed to resize. The error message was: %3$s</p>', 'wpmf'),
                    esc_html(get_the_title($image->ID)),
                    $image->ID,
                    $message
                );
                wp_send_json(
                    array(
                        'status'  => 'limit',
                        'success' => $this->result_gennerate_thumb
                    )
                );
            }

            if (empty($metadata)) {
                $message                      = __('Unknown failure reason.', 'wpmf');
                $this->result_gennerate_thumb .= sprintf(
                    __('<p>&quot;%1$s&quot; (ID %2$s) failed to resize. The error message was: %3$s</p>', 'wpmf'),
                    esc_html(get_the_title($image->ID)),
                    $image->ID,
                    $message
                );
                wp_send_json(
                    array(
                        'status'  => 'limit',
                        'success' => $this->result_gennerate_thumb
                    )
                );
            }

            wp_update_attachment_metadata($image->ID, $metadata);
            $this->result_gennerate_thumb .= sprintf(
                __('<p>&quot;%1$s&quot; (ID %2$s) was successfully resized in %3$s seconds.</p>', 'wpmf'),
                esc_html(get_the_title($image->ID)),
                $image->ID,
                timer_stop()
            );
            $k ++;
        }

        if ($k >= $limit) {
            wp_send_json(
                array(
                    'status'  => 'limit',
                    'success' => $this->result_gennerate_thumb,
                    'percent' => $present,
                    'url'     => $urls
                )
            );
        }
    }
}
