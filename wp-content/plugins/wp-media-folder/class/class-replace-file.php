<?php
/* Prohibit direct script loading */
defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Class WpmfReplaceFile
 * This class that holds most of the replace file functionality for Media Folder.
 */
class WpmfReplaceFile extends WpmfHelper
{

    /**
     * WpmfReplaceFile constructor.
     */
    public function __construct()
    {
        add_action('wp_enqueue_media', array($this, 'enqueueAdminScripts'));
        add_action('wp_ajax_wpmf_replace_file', array($this, 'replaceFile'));
    }

    /**
     * Ajax replace attachment
     *
     * @return void
     */
    public function replaceFile()
    {
        if (empty($_POST['wpmf_nonce'])
            || !wp_verify_nonce($_POST['wpmf_nonce'], 'wpmf_nonce')) {
            die();
        }

        /**
         * Filter check capability of current user to replace a file
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('edit_posts'), 'replace_file');
        if (!$wpmf_capability) {
            wp_send_json(false);
        }
        if (!empty($_FILES['wpmf_replace_file'])) {
            if (empty($_POST['post_selected'])) {
                esc_html_e('Post empty', 'wpmf');
                die();
            }

            $id       = $_POST['post_selected'];
            $metadata = wp_get_attachment_metadata($id);

            $filepath          = get_attached_file($id);
            $infopath          = pathinfo($filepath);
            $allowedImageTypes = array('gif', 'jpg', 'png', 'bmp', 'pdf');
            $new_filetype      = wp_check_filetype($_FILES['wpmf_replace_file']['name']);
            if ($new_filetype['ext'] === 'jpeg') {
                $new_filetype['ext'] = 'jpg';
            }

            if ($infopath['extension'] === 'jpeg') {
                $infopath['extension'] = 'jpg';
            }
            if ($new_filetype['ext'] !== $infopath['extension']) {
                wp_send_json(
                    array(
                        'status' => false,
                        'msg'    => __('To replace a media and keep the link to this media working,
it must be in the same format, ie. jpg > jpg… Thanks!', 'wpmf')
                    )
                );
            }

            if ($_FILES['wpmf_replace_file']['error'] > 0) {
                wp_send_json(
                    array(
                        'status' => false,
                        'msg'    => $_FILES['wpmf_replace_file']['error']
                    )
                );
            } else {
                $uploadpath = wp_upload_dir();
                if (!file_exists($filepath)) {
                    wp_send_json(
                        array(
                            'status' => false,
                            'msg'    => __('This file not exists!', 'wpmf')
                        )
                    );
                }

                unlink($filepath);
                if (in_array($infopath['extension'], $allowedImageTypes)) {
                    if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                        foreach ($metadata['sizes'] as $size => $sizeinfo) {
                            $intermediate_file = str_replace(basename($filepath), $sizeinfo['file'], $filepath);
                            // This filter is documented in wp-includes/functions.php
                            $intermediate_file = apply_filters('wp_delete_file', $intermediate_file);
                            $link = path_join(
                                $uploadpath['basedir'],
                                $intermediate_file
                            );
                            if (file_exists($link) && is_writable($link)) {
                                unlink($link);
                            }
                        }
                    }
                }

                move_uploaded_file(
                    $_FILES['wpmf_replace_file']['tmp_name'],
                    $infopath['dirname'] . '/' . $infopath['basename']
                );
                update_post_meta($id, 'wpmf_size', filesize($infopath['dirname'] . '/' . $infopath['basename']));

                if ($infopath['extension'] === 'pdf') {
                    $this->createPdfThumbnail($filepath);
                }

                if (in_array($infopath['extension'], $allowedImageTypes)) {
                    if ($infopath['extension'] !== 'pdf') {
                        $actual_sizes_array = getimagesize($filepath);
                        $metadata['width']  = $actual_sizes_array[0];
                        $metadata['height'] = $actual_sizes_array[1];
                        $this->createThumbs($filepath, $infopath['extension'], $metadata, $id);
                    }
                }
                if (isset($_FILES['wpmf_replace_file']['size'])) {
                    $size = $_FILES['wpmf_replace_file']['size'];
                    update_post_meta($id, 'wpmf_size', $size);
                    if ($size >= 1024 && $size < 1024 * 1024) {
                        $size = ceil($size / 1024) . ' KB';
                    } elseif ($size >= 1024 * 1024) {
                        $size = ceil($size / (1024 * 1024)) . ' MB';
                    } elseif ($size < 1024) {
                        $size = $size . ' B';
                    }
                } else {
                    $size = '0 B';
                }

                if (in_array($infopath['extension'], $allowedImageTypes)) {
                    $metadata   = wp_get_attachment_metadata($id);
                    $dimensions = $metadata['width'] . ' x ' . $metadata['height'];
                    wp_send_json(array('status' => true, 'size' => $size, 'dimensions' => $dimensions));
                } else {
                    wp_send_json(array('status' => true, 'size' => $size));
                }
            }
        } else {
            wp_send_json(array('status' => false, 'msg' => __('File not exist', 'wpmf')));
        }
    }

    /**
     * Create Pdf Thumbnail
     *
     * @param string $filepath File path
     *
     * @return void
     */
    public function createPdfThumbnail($filepath)
    {
        $metadata       = array();
        $fallback_sizes = array(
            'thumbnail',
            'medium',
            'large',
        );

        /**
         * Filters the image sizes generated for non-image mime types.
         *
         * @param array $fallback_sizes An array of image size names.
         * @param array $metadata       Current attachment metadata.
         */
        $fallback_sizes = apply_filters('fallback_intermediate_image_sizes', $fallback_sizes, $metadata);

        $sizes                      = array();
        $_wp_additional_image_sizes = wp_get_additional_image_sizes();

        foreach ($fallback_sizes as $s) {
            if (isset($_wp_additional_image_sizes[$s]['width'])) {
                $sizes[$s]['width'] = intval($_wp_additional_image_sizes[$s]['width']);
            } else {
                $sizes[$s]['width'] = get_option($s . '_size_w');
            }

            if (isset($_wp_additional_image_sizes[$s]['height'])) {
                $sizes[$s]['height'] = intval($_wp_additional_image_sizes[$s]['height']);
            } else {
                $sizes[$s]['height'] = get_option($s . '_size_h');
            }

            if (isset($_wp_additional_image_sizes[$s]['crop'])) {
                $sizes[$s]['crop'] = $_wp_additional_image_sizes[$s]['crop'];
            } else {
                // Force thumbnails to be soft crops.
                if ('thumbnail' !== $s) {
                    $sizes[$s]['crop'] = get_option($s . '_crop');
                }
            }
        }

        // Only load PDFs in an image editor if we're processing sizes.
        if (!empty($sizes)) {
            $editor = wp_get_image_editor($filepath);

            if (!is_wp_error($editor)) { // No support for this type of file
                /*
                 * PDFs may have the same file filename as JPEGs.
                 * Ensure the PDF preview image does not overwrite any JPEG images that already exist.
                 */
                $dirname      = dirname($filepath) . '/';
                $ext          = '.' . pathinfo($filepath, PATHINFO_EXTENSION);
                $preview_file = $dirname . wp_unique_filename($dirname, wp_basename($filepath, $ext) . '-pdf.jpg');

                $uploaded = $editor->save($preview_file, 'image/jpeg');
                unset($editor);

                // Resize based on the full size image, rather than the source.
                if (!is_wp_error($uploaded)) {
                    $editor = wp_get_image_editor($uploaded['path']);
                    unset($uploaded['path']);

                    if (!is_wp_error($editor)) {
                        $metadata['sizes']         = $editor->multi_resize($sizes);
                        $metadata['sizes']['full'] = $uploaded;
                    }
                }
            }
        }
    }

    /**
     * Includes styles and some scripts
     *
     * @return void
     */
    public function enqueueAdminScripts()
    {
        /**
         * Filter check capability of current user to load assets
         *
         * @param boolean The current user has the given capability
         * @param string  Action name
         *
         * @return boolean
         *
         * @ignore Hook already documented
         */
        $wpmf_capability = apply_filters('wpmf_user_can', current_user_can('edit_posts'), 'load_script_style');
        if ($wpmf_capability) {
            wp_enqueue_script(
                'wpmf-jquery-form',
                plugins_url('assets/js/jquery.form.js', dirname(__FILE__)),
                array('jquery'),
                WPMF_VERSION
            );

            wp_enqueue_script(
                'replace-image',
                plugins_url('assets/js/replace-image.js', dirname(__FILE__)),
                array('jquery'),
                WPMF_VERSION
            );

            wp_enqueue_style(
                'replace-style',
                plugins_url('assets/css/style_replace_image.css', dirname(__FILE__)),
                array(),
                WPMF_VERSION
            );
        }
    }
}
