<?php

namespace Ultimate_Watermark\Admin\Settings\Image;


class Position
{

    public static function get_settings()
    {

        return array(
            array(
                'title' => __('Position Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_position_options',
            ),

            array(
                'title' => __('Watermark alignment', 'ultimate-watermark'),
                'desc' => __('Select the watermark alignment.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_alignment',
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
            )
        , array(
                'title' => __('Watermark offset [X]', 'ultimate-watermark'),
                'desc' => __('Enter watermark offset value for X ( ie offset width).', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_offset_width',
                'default' => 0,
                'type' => 'number',
            )
        ,
            array(
                'title' => __('Watermark offset [Y]', 'ultimate-watermark'),
                'desc' => __('Enter watermark offset value for Y ( ie offset height).', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_offset_height',
                'default' => 0,
                'type' => 'number',
            )
        ,
            array(
                'title' => __('Offset unit', 'ultimate-watermark'),
                'desc' => __('Select the watermark offset unit.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_offset_unit',
                'options' => array(
                    'pixels' => __('Pixels', 'ultimate-watermark'),
                    'percentages' => __('Percentages', 'ultimate-watermark'),
                ),
                'type' => 'select',
            ),

            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_position_options',
            )

        );

    }
}
