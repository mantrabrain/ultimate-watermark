<?php

namespace Ultimate_Watermark\Admin\Settings;


class Protections_Backup extends Base
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = 'protections_backup';
        $this->label = __('Image Protection & Backup', 'ultimate-watermark');

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
            '' => __('Image Protection & Backup', 'ultimate-watermark'),
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
                'title' => __('Image Protection Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_image_protection_options',
            ),

            array(
                'title' => __('Right click', 'ultimate-watermark'),
                'desc' => __(' Disable right mouse click on images.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_disable_rightclick',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Drag and Drop', 'ultimate-watermark'),
                'desc' => __(' Prevent drag and drop', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_disable_drag_and_drop',
                'default' => 'no',
                'type' => 'checkbox',
            )
        , array(
                'title' => __('Logged-in Users', 'ultimate-watermark'),
                'desc' => __(' Enable image protection for logged-in users also', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_enable_protection_for_logged_in_users',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_image_protection_options',
            ),
            array(
                'title' => __('Backup Settings', 'ultimate-watermark'),
                'type' => 'title',
                'desc' => '',
                'id' => 'ultimate_watermark_image_backup_options',
            ),
            array(
                'title' => __('Backup full size image', 'ultimate-watermark'),
                'desc' => __('  Backup the full size image.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_backup_image',
                'default' => 'yes',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Backup image quality.', 'ultimate-watermark'),
                'desc' => __('Set output image quality.', 'ultimate-watermark'),
                'desc_tip' => false,
                'id' => 'ultimate_watermark_backup_image_quality',
                'default' => 90,
                'type' => 'slider',
                'data' => array(
                    'max' => 100,
                    'min' => 1,
                    'step' => 1
                )
            ),
            array(
                'type' => 'sectionend',
                'id' => 'ultimate_watermark_image_backup_options',
            )

        );


        return apply_filters('ultimate_watermark_get_settings_' . $this->id, $settings, $current_section);
    }
}
