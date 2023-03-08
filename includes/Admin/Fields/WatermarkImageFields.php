<?php

namespace Ultimate_Watermark\Admin\Fields;

class WatermarkImageFields extends Base
{
    public function get_settings()
    {
        return [

            'ultimate_watermark_watermark_image' => [
                'type' => 'image',
                'title' => __('Watermark Image', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-marker-image',
                'desc' => __("You can choose watermark image for your files. This image will be applied as a watermark on the files.", 'ultimate-watermark'),
            ],
            'ultimate_watermark_watermark_size_type' => [
                'title' => __('Watermark Size', 'ultimate-watermark'),
                'desc' => __("Watermark size.", 'ultimate-watermark'),
                'type' => 'select',
                'class' => 'ultimate-watermark-watermark-size',
                'options' => array(
                    'original' => __('Original', 'ultimate-watermark'),
                    'custom' => __('Custom', 'ultimate-watermark'),
                    'scaled' => __('Scaled', 'ultimate-watermark'),
                ),
            ],
            'ultimate_watermark_watermark_absolute_width' => [
                'type' => 'number',
                'title' => __('Watermark custom size [X]', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-size-x',
                'desc' => __("Watermark custom size  [X] - Width", 'ultimate-watermark'),
                'display_conditions' => array(
                    array(
                        'field' => 'ultimate_watermark_watermark_size_type',
                        'compare' => '=',
                        'value' => 'custom'
                    )
                )
            ],
            'ultimate_watermark_watermark_absolute_height' => [
                'type' => 'number',
                'title' => __('Watermark custom size [Y]', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-size-y',
                'desc' => __("Watermark custom size  [Y] - Height", 'ultimate-watermark'),
                'display_conditions' => array(
                    array(
                        'field' => 'ultimate_watermark_watermark_size_type',
                        'compare' => '=',
                        'value' => 'custom'
                    )
                )
            ],
            'ultimate_watermark_watermark_scale_image_width' => [
                'title' => __('Watermark scale', 'ultimate-watermark'),
                'desc' => __('Enter a number ranging from 0 to 100. 100 makes width of watermark image equal to width of the image it is applied to.', 'ultimate-watermark'),
                'desc_tip' => false,
                'default' => 80,
                'type' => 'slider',
                'data' => array(
                    'max' => 100,
                    'min' => 1,
                    'step' => 1
                ),
                'display_conditions' => array(
                    array(
                        'field' => 'ultimate_watermark_watermark_size_type',
                        'compare' => '=',
                        'value' => 'scaled'
                    )
                )
            ],
            'ultimate_watermark_watermark_image_transparent' => [
                'title' => __('Watermark transparency / opacity', 'ultimate-watermark'),
                'desc' => __('Enter a number ranging from 0 to 100. 0 makes watermark image completely transparent, 100 shows it as is.', 'ultimate-watermark'),
                'desc_tip' => false,
                'default' => 50,
                'type' => 'slider',
                'data' => array(
                    'max' => 100,
                    'min' => 1,
                    'step' => 1
                )
            ],
            'ultimate_watermark_watermark_image_quality' => [
                'title' => __('Image quality', 'ultimate-watermark'),
                'desc' => __('Set output image quality.', 'ultimate-watermark'),
                'desc_tip' => false,
                'default' => 90,
                'type' => 'slider',
                'data' => array(
                    'max' => 100,
                    'min' => 1,
                    'step' => 1
                )
            ],
            'ultimate_watermark_watermark_image_format' => [
                'title' => __('Image format', 'ultimate-watermark'),
                'desc' => __('Select baseline or progressive image format.', 'ultimate-watermark'),
                'desc_tip' => false,
                'options' => array(
                    'baseline' => __('Baseline', 'ultimate-watermark'),
                    'progressive' => __('Progressive', 'ultimate-watermark'),
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
        return 'ultimate_watermark_watermark_image_fields';
    }

}
