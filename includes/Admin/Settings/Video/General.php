<?php

namespace Ultimate_Watermark\Admin\Settings\Video;

class General
{

    public static function get_settings()
    {

        return array(
            array(
                'title' => __('General Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_video_general_options',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_video_general_options',
            )

        );


    }
}
