<?php

namespace Ultimate_Watermark\Admin;

use Ultimate_Watermark\Handler\ImageWatermarkHandler;


class Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_ulwm_watermark_bulk_action', array($this, 'watermark_action_ajax'));
        add_action('wp_ajax_ultimate_watermark_preview_placeholder', array($this, 'watermark_preview'));
        add_action('wp_ajax_ultimate_watermark_status_change', array($this, 'status_change'));


    }

    public function watermark_preview()
    {
        $watermark_id = isset($_GET['watermark_id']) ? absint($_GET['watermark_id']) : 0;

        $image_url = esc_url(ULTIMATE_WATERMARK_DIR) . 'assets/images/preview-placeholder.png';

        $watermark = ultimate_watermark_get_watermark($watermark_id);

        $watermark_image = $watermark->get_watermark_image();

        if ($watermark_image->get_watermark_image() > 0) {

            $watermark_file = wp_get_attachment_metadata($watermark_image->get_watermark_image(), true);

            $upload_dir = wp_upload_dir();

            $watermark_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];

            $watermark_handler = new ImageWatermarkHandler(0, $watermark);

            $watermark_handler->do_watermark($image_url, $watermark_path, [], false);
        }

        ultimate_watermark_print_image($image_url);

        exit;

    }

    public function status_change()
    {
        $watermark_id = isset($_POST['watermark_id']) ? absint($_POST['watermark_id']) : 0;

        $status = isset($_POST['status']) && (boolean)$_POST['status'];

        $status = $status ? 1 : 0;

        $nonce = $_POST['nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'ultimate_watermark_status_change_nonce')) {
            wp_send_json_error();
        }
        if (!current_user_can('manage_options') || $watermark_id < 1) {
            wp_send_json_error();
        }
        update_post_meta($watermark_id, 'ultimate_watermark_enable_this_watermark', $status);

        wp_send_json_success();
    }

    public function watermark_action_ajax()
    {
        // Security & data check
        if (!defined('DOING_AJAX') || !DOING_AJAX || !isset($_POST['_ulwm_nonce']) || !isset($_POST['ulwm-action']) || !isset($_POST['attachment_id']) || !is_numeric($_POST['attachment_id']) || !wp_verify_nonce($_POST['_ulwm_nonce'], 'ultimate-watermark')) {
            //  wp_send_json_error(__('Something went wrong, please try again.', 'ultimate-watermark'));
        }
        $attachment_id = (int)$_POST['attachment_id'];

        $watermark_ids = ultimate_watermark_get_all_watermark_ids();


        foreach ($watermark_ids as $watermark_id) {

            $watermark = ultimate_watermark_get_watermark($watermark_id);

            $conditions = $watermark->get_conditions();

            $watermark_type = $watermark->get_watermark_type();

            if ($attachment_id > 0 && $conditions->is_manual_watermarking()) {

                $data = wp_get_attachment_metadata($attachment_id, false);

                // is this really an image?
                if (in_array(get_post_mime_type($attachment_id), ultimate_watermark()->utils->get_allowed_mime_types()) && is_array($data) && $watermark_type == "image") {

                    $watermark = new ImageWatermarkHandler($attachment_id, $watermark);

                    $success = $watermark->apply_the_watermark();
                }
            }
        }

        die('you can change the code from here');
        wp_send_json_error(__('Something went wrong, please try again.', 'ultimate-watermark'));
    }


}