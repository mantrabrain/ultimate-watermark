<?php

namespace Ultimate_Watermark\Admin\Settings\Video;


class Image
{
    public static function get_settings()
    {

        return array(
            array(
                'title' => __('Watermark Image Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_video_image_options',
            ),


            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_video_image_options',
            )

        );

    }
}
