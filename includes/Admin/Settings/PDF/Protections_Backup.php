<?php

namespace Ultimate_Watermark\Admin\Settings\PDF;


class Protections_Backup
{

    public static function get_settings()
    {

        return array(
            array(
                'title' => __('Image Protection Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_pdf_protection_options',
            ),

            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_pdf_protection_options',
            )

        );
    }
}
