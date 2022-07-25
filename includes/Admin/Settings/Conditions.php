<?php

namespace Ultimate_Watermark\Admin\Settings;


class Conditions extends Base
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = 'watermark_condition';
        $this->label = __('Conditional Logic', 'ultimate-watermark');

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
            '' => __('Watermark Conditions', 'ultimate-watermark'),
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

        $settings = array(
            array(
                'title' => __('Conditions', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_image_conditions',
            ),

            array(
                'title' => __('Watermark image	', 'ultimate-watermark'),
                'desc' => __('Watermark image', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_image',
                'type' => 'image',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_image_conditions',
            )

        );


        return apply_filters('ultimate_watermark_get_settings_' . $this->id, $settings, $current_section);
    }
}
