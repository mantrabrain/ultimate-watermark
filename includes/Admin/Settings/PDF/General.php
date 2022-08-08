<?php

namespace Ultimate_Watermark\Admin\Settings\PDF;

class General
{

    public static function get_settings()
    {
        $image_sizes = ultimate_watermark_get_image_sizes();

        $post_types = ultimate_watermark_get_post_types();

        return array(
            array(
                'title' => __('General Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_pdf_general_options',
            ),


            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_pdf_general_options',
            )

        );


    }
}
