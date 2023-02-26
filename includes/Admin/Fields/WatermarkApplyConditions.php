<?php

namespace Ultimate_Watermark\Admin\Fields;

class WatermarkApplyConditions extends Base
{
    public function get_settings()
    {
        return [

            'ultimate_watermark_automatic_watermarking' => [
                'type' => 'checkbox',
                'title' => __('Automatic watermarking', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-automatic-watermarking',
                'desc' => __("Enable watermark for uploaded images.", 'ultimate-watermark')
            ],
            'ultimate_watermark_manual_watermarking' => [
                'type' => 'checkbox',
                'title' => __('Manual watermarking', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-manual-watermarking',
                'desc' => __(" Enable Apply Watermark option for Media Library images.", 'ultimate-watermark')
            ],

            'ultimate_watermark_image_watermark_for' => [
                'title' => __('Watermark alignment', 'ultimate-watermark'),
                'desc' => __('Select the watermark alignment.', 'ultimate-watermark'),
                'desc_tip' => false,
                'options' => array(
                    'top_left' => __('Top Left', 'ultimate-watermark'),
                    'top_center' => __('Top Center', 'ultimate-watermark'),
                    'top_right' => __('Top Right', 'ultimate-watermark'),
                    'middle_left' => __('Middle Left', 'ultimate-watermark'),
                    'middle_center' => __('Middle Center', 'ultimate-watermark'),
                    'middle_right' => __('Middle Right', 'ultimate-watermark'),
                    'bottom_left' => __('Bottom Left', 'ultimate-watermark'),
                    'bottom_center' => __('Bottom Center', 'ultimate-watermark'),
                    'bottom_right' => __('Bottom Right', 'ultimate-watermark'),
                ),
                'type' => 'select',
                'default' => 'bottom_right'
            ],
            'ultimate_watermark_watermark_offset_width' => [
                'title' => __('Watermark offset [X]', 'ultimate-watermark'),
                'desc' => __('Enter watermark offset value for X ( ie offset width).', 'ultimate-watermark'),
                'desc_tip' => false,

                'default' => 0,
                'type' => 'number',
            ]
            ,
            'ultimate_watermark_watermark_offset_height' => [
                'title' => __('Watermark offset [Y]', 'ultimate-watermark'),
                'desc' => __('Enter watermark offset value for Y ( ie offset height).', 'ultimate-watermark'),
                'desc_tip' => false,
                'default' => 0,
                'type' => 'number',
            ]
            ,
            'ultimate_watermark_watermark_offset_unit' => [
                'title' => __('Offset unit', 'ultimate-watermark'),
                'desc' => __('Select the watermark offset unit.', 'ultimate-watermark'),
                'desc_tip' => false,
                'options' => array(
                    'pixels' => __('Pixels', 'ultimate-watermark'),
                    'percentages' => __('Percentages', 'ultimate-watermark'),
                ),
                'type' => 'select',
            ],

        ];
    }

    public function render()
    {
        $this->output();
    }

    public function nonce_id()
    {
        return 'ultimate_watermark_map_marker_fields';
    }

}
