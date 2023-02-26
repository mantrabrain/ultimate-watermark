<?php

namespace Ultimate_Watermark\Admin\Fields;

class WatermarkGeneralFields extends Base
{
    public function get_settings()
    {
        return [
            'ultimate_watermark_enable_this_watermark' => [
                'type' => 'checkbox',
                'title' => __('Enable this watermark', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-enable-this-watermark',
                'desc' => __("Enable this watermark.", 'ultimate-watermark')
            ],
            'ultimate_watermark_watermark_type' => [
                'title' => __('Watermark Type', 'ultimate-watermark'),
                'desc' => __("You can select watermark type from here.", 'ultimate-watermark'),
                'type' => 'select',
                'class' => 'ultimate-watermark-watermark-type',
                'options' => array(
                    'image' => __('Image Watermark', 'ultimate-watermark'),
                ),
            ],

        ];
    }

    public function render()
    {
        $this->output();
    }


    public function nonce_id()
    {
        return 'ultimate_watermark_map_general_setting_fields';
    }
}
