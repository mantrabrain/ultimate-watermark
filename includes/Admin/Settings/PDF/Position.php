<?php

namespace Ultimate_Watermark\Admin\Settings\PDF;


class Position
{

    public static function get_settings()
    {

        return array(
            array(
                'title' => __('Position Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_pdf_position_options',
            ),


            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_pdf_position_options',
            )

        );

    }
}
