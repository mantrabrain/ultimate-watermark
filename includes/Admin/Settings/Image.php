<?php

namespace Ultimate_Watermark\Admin\Settings;


class Image extends Base
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = 'watermark_image';
        $this->label = __('Watermark Image', 'ultimate-watermark');

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
            '' => __('Watermark Image', 'ultimate-watermark'),
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
                'title' => __('Watermark Image Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_image_options',
            ),

            array(
                'title' => __('Watermark image	', 'ultimate-watermark'),
                'desc' => __('Watermark image', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_image',
                'type' => 'image',
            )
        ,
            array(
                'title' => __('Watermark size', 'ultimate-watermark'),
                'desc' => __('Select method of aplying watermark size.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_watermark_size_type',
                'options' => array(
                    'original' => __('Original', 'ultimate-watermark'),
                    'custom' => __('Custom', 'ultimate-watermark'),
                    'scaled' => __('Scaled', 'ultimate-watermark'),
                ),
                'type' => 'select',
            ),
            array(
                'title' => __('Watermark custom size [X]', 'ultimate-watermark'),
                'desc' => __('[px] X ( Width).', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_absolute_width',
                'default' => 0,
                'type' => 'number',
                'display_conditions' => array(
                    array(
                        'field' => 'ultimate_watermark_watermark_size_type',
                        'compare' => '=',
                        'value' => 'custom'
                    )
                )
            ),
            array(
                'title' => __('Watermark custom size [Y]', 'ultimate-watermark'),
                'desc' => __('[px] Y ( Height).', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_absolute_height',
                'default' => 0,
                'type' => 'number',
                'display_conditions' => array(
                    array(
                        'field' => 'ultimate_watermark_watermark_size_type',
                        'compare' => '=',
                        'value' => 'custom'
                    )
                )
            ),
            array(
                'title' => __('Watermark scale', 'ultimate-watermark'),
                'desc' => __('Enter a number ranging from 0 to 100. 100 makes width of watermark image equal to width of the image it is applied to.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_scaled_image_width',
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
            ),
            array(
                'title' => __('Watermark transparency / opacity', 'ultimate-watermark'),
                'desc' => __('Enter a number ranging from 0 to 100. 0 makes watermark image completely transparent, 100 shows it as is.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_image_transparent',
                'default' => 50,
                'type' => 'slider',
                'data' => array(
                    'max' => 100,
                    'min' => 1,
                    'step' => 1
                )
            ),
            array(
                'title' => __('Image quality', 'ultimate-watermark'),
                'desc' => __('Set output image quality.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_image_quality',
                'default' => 90,
                'type' => 'slider',
                'data' => array(
                    'max' => 100,
                    'min' => 1,
                    'step' => 1
                )
            ),
            array(
                'title' => __('Image format', 'ultimate-watermark'),
                'desc' => __('Select baseline or progressive image format.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_image_format',
                'options' => array(
                    'baseline' => __('Baseline', 'ultimate-watermark'),
                    'progressive' => __('Progressive', 'ultimate-watermark'),
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
