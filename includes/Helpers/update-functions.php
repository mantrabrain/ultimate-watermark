<?php
if (!function_exists('ultimate_watermark_update_1040_update_previous_option')) {
    function ultimate_watermark_update_1040_update_previous_option()
    {
        $options = get_option('ultimate_watermark_options', array());

        $watermark_image = $options['watermark_image'] ?? array();

        $watermark_on = $options['watermark_on'] ?? array();
        $protection = $options['image_protection'] ?? array();
        $backup = $options['backup'] ?? array();

        $ultimate_watermark_watermark_size_type = 'original';
        if ($watermark_image['watermark_size_type'] == 1) {
            $ultimate_watermark_watermark_size_type = 'custom';
        } else if ($watermark_image['watermark_size_type'] == 2) {
            $ultimate_watermark_watermark_size_type = 'scaled';

        }

        $updated_watermark_image_size = [];

        foreach ($watermark_on as $watermark_size) {

            $updated_watermark_image_size[$watermark_size] = 'yes';
        }

        $updated_options = array();
        if (is_array($options)) {

            if (count($options) > 0) {
                $updated_options = array(
                    'ultimate_watermark_automatic_watermarking' => isset($watermark_image['plugin_off']) && $watermark_image['plugin_off'] == 1 ? 'yes' : 'no',
                    'ultimate_watermark_manual_watermarking' => isset($watermark_image['manual_watermarking']) && $watermark_image['manual_watermarking'] == 1 ? 'yes' : 'no',
                    'ultimate_watermark_watermark_on_image_size' => $updated_watermark_image_size,
                    'ultimate_watermark_watermark_on' => 'everywhere',
                    'ultimate_watermark_frontend_watermarking' => isset($watermark_image['frontend_active']) && (boolean)$watermark_image['frontend_active'] ? 'yes' : 'no',
                    'ultimate_watermark_watermark_alignment' => $watermark_image['position'] ?? 'bottom_right',

                    'ultimate_watermark_offset_width' => $watermark_image['offset_width'] ?? 0,
                    'ultimate_watermark_offset_height' => $watermark_image['offset_height'] ?? 0,
                    'ultimate_watermark_watermark_offset_unit' => $watermark_image['offset_unit'] ?? 'pixels',
                    'ultimate_watermark_watermark_image' => $watermark_image['attachment_id'] ?? 0,
                    'ultimate_watermark_watermark_size_type' => $ultimate_watermark_watermark_size_type,

                    'ultimate_watermark_absolute_width' => $watermark_image['absolute_width'] ?? 0,
                    'ultimate_watermark_absolute_height' => $watermark_image['absolute_height'] ?? 0,
                    'ultimate_watermark_scaled_image_width' => $watermark_image['width'] ?? 80,
                    'ultimate_watermark_image_transparent' => $watermark_image['transparent'] ?? 50,
                    'ultimate_watermark_image_quality' => $watermark_image['quality'] ?? 90,
                    'ultimate_watermark_image_format' => $watermark_image['jpeg_format'] ?? 'baseline',

                    'ultimate_watermark_disable_rightclick' => isset($protection['rightclick']) && (boolean)$protection['rightclick'] ? 'yes' : 'no',
                    'ultimate_watermark_disable_drag_and_drop' => isset($protection['draganddrop']) && (boolean)$protection['draganddrop'] ? 'yes' : 'no',
                    'ultimate_watermark_enable_protection_for_logged_in_users' => isset($protection['forlogged']) && (boolean)$protection['forlogged'] ? 'yes' : 'no',

                    'ultimate_watermark_backup_image' => isset($backup['backup_image']) && (boolean)$backup['backup_image'] ? 'yes' : 'no',
                    'ultimate_watermark_backup_image_quality' => $watermark_image['backup_quality'] ?? 90,


                );
            }
        }
        foreach ($updated_options as $option_id => $option_value) {
            update_option($option_id, $option_value);
        }
        delete_option('ultimate_watermark_options');
    }
}