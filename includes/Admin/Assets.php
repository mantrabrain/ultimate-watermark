<?php

namespace Ultimate_Watermark\Admin;

class Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_print_scripts', array($this, 'admin_print_scripts'), 20);

    }

    public function admin_enqueue_scripts($page)
    {
        global $pagenow;

        wp_register_style('ultimate-watermark-jquery-ui', ULTIMATE_WATERMARK_URI . '/assets/lib/jquery-ui/jquery-ui.css', array(), ULTIMATE_WATERMARK_VERSION);

        wp_register_style('ultimate-watermark-setting-style', ULTIMATE_WATERMARK_URI . '/assets/css/settings.css', array('ultimate-watermark-jquery-ui'), ULTIMATE_WATERMARK_VERSION);


        wp_register_script('ultimate-watermark-setting-script', ULTIMATE_WATERMARK_URI . '/assets/js/settings.js', array('jquery', 'jquery-ui-core', 'jquery-ui-slider'), ULTIMATE_WATERMARK_VERSION);

        if ($page === 'toplevel_page_ultimate-watermark') {
            wp_enqueue_media();

            wp_enqueue_style('ultimate-watermark-setting-style');
            wp_enqueue_script('ultimate-watermark-setting-script');
            wp_localize_script(
                'ultimate-watermark-setting-script', 'ultimateWatermarkSettings', array(
                    'title' => __('Select watermark', 'ultimate-watermark'),
                    'originalSize' => __('Original size', 'ultimate-watermark'),
                    'noSelectedImg' => __('Watermak has not been selected yet.', 'ultimate-watermark'),
                    'notAllowedImg' => __('This image is not supported as watermark. Use JPEG, PNG or GIF.', 'ultimate-watermark'),
                    'px' => __('px', 'ultimate-watermark'),
                    'frame' => 'select',
                    'button' => array('text' => __('Add watermark', 'ultimate-watermark')),
                    'multiple' => false
                )
            );
        }
        if ($pagenow === 'upload.php') {
            wp_enqueue_style('watermark-style');
        }

        // I've omitted $pagenow === 'upload.php' because the image modal could be loaded in various places
        if (ultimate_watermark_manual_watermarking()) {

            wp_enqueue_script('watermark-admin-image-actions', ULTIMATE_WATERMARK_URI . '/assets/js/admin-image-actions.js', array('jquery'), ULTIMATE_WATERMARK_VERSION, true);

            wp_localize_script(
                'watermark-admin-image-actions', 'ulwmImageActionArgs', array(
                    'backup_image' => ultimate_watermark_backup_image(),
                    '_nonce' => wp_create_nonce('ultimate-watermark'),
                    '__applied_none' => __('Watermark could not be applied to selected files or no valid images (JPEG, PNG) were selected.', 'ultimate-watermark'),
                    '__applied_one' => __('Watermark was succesfully applied to 1 image.', 'ultimate-watermark'),
                    '__applied_multi' => __('Watermark was succesfully applied to %s images.', 'ultimate-watermark'),
                    '__removed_none' => __('Watermark could not be removed from selected files or no valid images (JPEG, PNG) were selected.', 'ultimate-watermark'),
                    '__removed_one' => __('Watermark was succesfully removed from 1 image.', 'ultimate-watermark'),
                    '__removed_multi' => __('Watermark was succesfully removed from %s images.', 'ultimate-watermark'),
                    '__skipped' => __('Skipped files', 'ultimate-watermark'),
                    '__running' => __('Bulk action is currently running, please wait.', 'ultimate-watermark'),
                    '__dismiss' => __('Dismiss this notice.','ultimate-watermark'), // Wordpress default string
                )
            );
        }
    }

    public function admin_print_scripts()
    {
        global $pagenow;

        if ($pagenow === 'upload.php') {
            if (ultimate_watermark_manual_watermarking()) {
                ?>
                <script type="text/javascript">
                    document.addEventListener("DOMContentLoaded", function (event) {

                        var backup = <?php echo ultimate_watermark_backup_image(); ?>;

                        jQuery("<option>").val("applywatermark").text("<?php _e('Apply watermark', 'ultimate-watermark'); ?>").appendTo("select[name='action'], select[name='action2']");
                        //jQuery("<option>").val("apply_video_watermark").text("<?php _e('Apply Video  watermark', 'ultimate-watermark'); ?>").appendTo("select[name='action'], select[name='action2']");

                        if (backup === 'true' || backup === true || backup === "1" || backup === 1) {
                            jQuery("<option>").val("removewatermark").text("<?php _e('Remove watermark', 'ultimate-watermark'); ?>").appendTo("select[name='action'], select[name='action2']");
                        }
                    });
                </script>
                <?php
            }
        }
    }

}