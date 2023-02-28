<?php

namespace Ultimate_Watermark\Admin\Fields;

class WatermarkApplyConditionFields extends Base
{
    public function get_settings()
    {
        $image_sizes = ultimate_watermark_get_image_sizes();
        $post_types = ultimate_watermark_get_post_types();

        return [

            'ultimate_watermark_automatic_watermarking' => [
                'type' => 'checkbox',
                'title' => __('Automatic watermarking', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-automatic-watermarking',
                'label' => __("Enable watermark for uploaded images.", 'ultimate-watermark')
            ],
            'ultimate_watermark_manual_watermarking' => [
                'type' => 'checkbox',
                'title' => __('Manual watermarking', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-manual-watermarking',
                'label' => __(" Enable Apply Watermark option for Media Library images.", 'ultimate-watermark')
            ],

            'ultimate_watermark_watermark_on_image_size' => [
                'title' => __('Watermark For (Image Sizes)', 'ultimate-watermark'),
                'desc' => __('Watermark for image sizes.', 'ultimate-watermark'),
                'desc_tip' => false,
                'options' => $image_sizes,
                'type' => 'multicheckbox',
                'default' => 'bottom_right'
            ],
            'ultimate_watermark_watermark_on' => [
                'title' => __('Watermark On', 'ultimate-watermark'),
                'desc' => __('Where do you want to apply this watermark?', 'ultimate-watermark'),
                'desc_tip' => false,
                'options' => array(
                    'everywhere' => __('Everywhere', 'ultimate-watermark'),
                    'selected_custom_post_types' => __('Selected Custom Post Types', 'ultimate-watermark'),
                ),
                'type' => 'select',
                'default' => 'everywhere'
            ],
            'ultimate_watermark_watermark_on_custom_post_type' => [
                'title' => __('Watermark For(Custom Post Types)', 'ultimate-watermark'),
                'desc' => __('Check custom post types on which watermark should be applied to uploaded images.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_on_custom_post_type',
                'options' => $post_types,
                'type' => 'multicheckbox',
                'display_conditions' => array(
                    array(
                        'field' => 'ultimate_watermark_watermark_on',
                        'compare' => '=',
                        'value' => 'selected_custom_post_types'
                    )
                )
            ],

            'ultimate_watermark_frontend_watermarking' => [
                'type' => 'checkbox',
                'title' => __('Frontend watermarking', 'ultimate-watermark'),
                'class' => 'ultimate-watermark-frontend-watermarking',
                'label' => sprintf(__('Enable frontend image uploading. (uploading script is not included, but you may use a plugin or custom code).%sNotice:%s This functionality works only if uploaded images are processed using WordPress native upload methods.', 'ultimate-watermark'), '<br/><strong>', '</strong>'),
            ],

        ];
    }

    public function render()
    {
        $this->output();
    }

    public function nonce_id()
    {
        return 'ultimate_watermark_apply_watermark_conditions_fields';
    }

}
