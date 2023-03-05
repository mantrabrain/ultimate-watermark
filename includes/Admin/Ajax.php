<?php

namespace Ultimate_Watermark\Admin;

use Ultimate_Watermark\Handler\ImageWatermarkHandler;


class Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_ulwm_watermark_bulk_action', array($this, 'watermark_action_ajax'));
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