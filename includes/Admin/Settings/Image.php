<?php

namespace Ultimate_Watermark\Admin\Settings;


class Image extends Base
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = 'image';
        $this->label = __('Image', 'ultimate-watermark');

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
            '' => __('Image', 'ultimate-watermark'),
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
                'title' => __('Image Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_image_options',
            ),

            array(
                'title' => __('Watermark image	', 'ultimate-watermark'),
                'desc' => __('Watermark image', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_image',
                'type' => 'image',
            )
        , array(
                'title' => __('Watermark offset [X]', 'ultimate-watermark'),
                'desc' => __('Enter watermark offset value for X ( ie offset width).', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_offset_width',
                'default' => 0,
                'type' => 'number',
            )
        ,
            array(
                'title' => __('Watermark offset [Y]', 'ultimate-watermark'),
                'desc' => __('Enter watermark offset value for Y ( ie offset height).', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_offset_height',
                'default' => 0,
                'type' => 'number',
            )
        ,
            array(
                'title' => __('Offset unit', 'ultimate-watermark'),
                'desc' => __('Select the watermark offset unit.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_offset_unit',
                'options' => array(
                    'pixels' => __('Pixels', 'ultimate-watermark'),
                    'percentages' => __('Percentages', 'ultimate-watermark'),
                ),
                'type' => 'select',
            ),

            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_image_options',
            )

        );


        return apply_filters('ultimate_watermark_get_settings_' . $this->id, $settings, $current_section);
    }
}
