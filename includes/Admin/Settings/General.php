<?php

namespace Ultimate_Watermark\Admin\Settings;


class General extends Base
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = 'general';
        $this->label = __('General', 'ultimate-watermark');

        parent::__construct();
    }

    /**
     * Get sections.
     *
     * @return array
     */
    public function get_sections()
    {
        $sections = array(
            '' => __('General', 'ultimate-watermark'),
        );

        return apply_filters('ultimate_watermark_get_sections_' . $this->id, $sections);
    }

    /**
     * Output the settings.
     */
    public function output()
    {
        global $current_section;

        $settings = $this->get_settings($current_section);

        Settings_Main::output_fields($settings);
    }

    /**
     * Save settings.
     */
    public function save()
    {
        global $current_section;

        $settings = $this->get_settings($current_section);
        Settings_Main::save_fields($settings);

        if ($current_section) {
            do_action('ultimate_watermark_update_options_' . $this->id . '_' . $current_section);
        }
    }

    /**
     * Get settings array.
     *
     * @param string $current_section Current section name.
     * @return array
     */
    public function get_settings($current_section = '')
    {
        $image_sizes = ultimate_watermark_get_image_sizes();

        $post_types = ultimate_watermark_get_post_types();

        $settings = array(
            array(
                'title' => __('General Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_general_options',
            ),

            array(
                'title' => __('Automatic watermarking', 'ultimate-watermark'),
                'desc' => __('Enable watermark for uploaded images.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_automatic_watermarking',
                'default' => 'no',
                'type' => 'checkbox',
            )
        , array(
                'title' => __('Manual watermarking', 'ultimate-watermark'),
                'desc' => __('Enable Apply Watermark option for Media Library images.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_manual_watermarking',
                'default' => 'yes',
                'type' => 'checkbox',
            )
        ,
            array(
                'title' => __('Watermark For (Image Sizes)', 'ultimate-watermark'),
                'desc' => sprintf(__('Check the image sizes watermark will be applied to.%sImportant:%s checking full size is NOT recommended as it\'s the original image. You may need it later - for removing or changing watermark, image sizes regeneration or any other image manipulations. Use it only if you know what you are doing.', 'ultimate-watermark'), '<br/><strong style="color:red">', '</strong>'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_on_image_size',
                'options' => $image_sizes,
                'type' => 'multicheckbox',
            ),
            array(
                'title' => __('Watermark On', 'ultimate-watermark'),
                'desc' => __('Select custom post types on which watermark should be applied to uploaded images.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_on',
                'options' => array(
                    'everywhere' => __('Everywhere', 'ultimate-watermark'),
                    'selected_custom_post_types' => __('Selected Custom Post Types', 'ultimate-watermark'),
                ),
                'type' => 'select',
                'default' => 'everywhere'
            ),
            array(
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
            ),
            array(
                'title' => __('Frontend watermarking', 'ultimate-watermark'),
                'desc' => sprintf(__('Enable frontend image uploading. (uploading script is not included, but you may use a plugin or custom code).%sNotice:%s This functionality works only if uploaded images are processed using WordPress native upload methods.', 'ultimate-watermark'), '<br/><strong>', '</strong>'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_frontend_watermarking',
                'default' => 'no',
                'type' => 'checkbox',
            ),

            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_general_options',
            )

        );


        return apply_filters('ultimate_watermark_get_settings_' . $this->id, $settings, $current_section);
    }
}
