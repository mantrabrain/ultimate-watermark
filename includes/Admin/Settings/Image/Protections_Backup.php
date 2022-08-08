<?php

namespace Ultimate_Watermark\Admin\Settings\Image;


class Protections_Backup
{

    public static function get_settings()
    {

        return array(
            array(
                'title' => __('Image Protection Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_image_protection_options',
            ),

            array(
                'title' => __('Right click', 'ultimate-watermark'),
                'desc' => __(' Disable right mouse click on images.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_disable_rightclick',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Drag and Drop', 'ultimate-watermark'),
                'desc' => __(' Prevent drag and drop', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_disable_drag_and_drop',
                'default' => 'no',
                'type' => 'checkbox',
            )
        , array(
                'title' => __('Logged-in Users', 'ultimate-watermark'),
                'desc' => __(' Enable image protection for logged-in users also', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_enable_protection_for_logged_in_users',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_image_protection_options',
            ),
            array(
                'title' => __('Backup Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_image_backup_options',
            ),
            array(
                'title' => __('Backup full size image', 'ultimate-watermark'),
                'desc' => __('  Backup the full size image.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_backup_image',
                'default' => 'yes',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Backup image quality.', 'ultimate-watermark'),
                'desc' => __('Set output image quality.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_backup_image_quality',
                'default' => 90,
                'type' => 'slider',
                'data' => array(
                    'max' => 100,
                    'min' => 1,
                    'step' => 1
                )
            ),
            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_image_backup_options',
            )

        );
    }
}
