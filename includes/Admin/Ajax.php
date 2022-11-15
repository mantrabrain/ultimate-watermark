<?php

namespace Ultimate_Watermark\Admin;

use Ultimate_Watermark\Video\Watermark;

class Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_ulwm_watermark_bulk_action', array($this, 'watermark_action_ajax'));

    }

    public function watermark_action_ajax()
    {
        //Need to modify the code before go to live
        if ($_POST['ulwm-action'] === "apply_video_watermark") {

            $attachment_id = $_POST['attachment_id'] ? absint($_POST['attachment_id']) : 0;

            $video_watermark = new Watermark();

            $video_watermark->apply_watermark($attachment_id);

            die('finish video watermarking');

        }
        // Security & data check
        if (!defined('DOING_AJAX') || !DOING_AJAX || !isset($_POST['_ulwm_nonce']) || !isset($_POST['ulwm-action']) || !isset($_POST['attachment_id']) || !is_numeric($_POST['attachment_id']) || !wp_verify_nonce($_POST['_ulwm_nonce'], 'ultimate-watermark'))
            wp_send_json_error(__('Something went wrong, please try again.', 'ultimate-watermark'));

        $post_id = (int)$_POST['attachment_id'];
        $action = false;

        switch ($_POST['ulwm-action']) {
            case 'applywatermark':
                $action = 'applywatermark';
                break;

            case 'removewatermark':
                $action = 'removewatermark';
        }

        // only if manual watermarking is turned and we have a valid action
        // if the action is NOT "removewatermark" we also require a watermark image to be set
        if ($post_id > 0 && $action && ultimate_watermark_manual_watermarking() && (ultimate_watermark_watermark_image() != 0 || $action == 'removewatermark')) {
            $data = wp_get_attachment_metadata($post_id, false);

            // is this really an image?
            if (in_array(get_post_mime_type($post_id), ultimate_watermark()->utils->get_allowed_mime_types()) && is_array($data)) {
                if ($action === 'applywatermark') {
                    $success = ultimate_watermark()->watermark->apply_watermark($data, $post_id, 'manual');

                    if (!empty($success['error']))
                        wp_send_json_success($success['error']);
                    else
                        wp_send_json_success('watermarked');
                } elseif ($action === 'removewatermark') {
                    $success = ultimate_watermark()->watermark->remove_watermark($data, $post_id, 'manual');

                    if ($success)
                        wp_send_json_success('watermarkremoved');
                    else
                        wp_send_json_success('skipped');
                }
            } else
                wp_send_json_success('skipped');
        }

        wp_send_json_error(__('Something went wrong, please try again.', 'ultimate-watermark'));
    }


}