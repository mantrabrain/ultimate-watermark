<?php

namespace Ultimate_Watermark\Admin\Settings;


use Ultimate_Watermark\Admin\Settings\Image\General;
use Ultimate_Watermark\Admin\Settings\Image\Image;
use Ultimate_Watermark\Admin\Settings\Image\Position;
use Ultimate_Watermark\Admin\Settings\Image\Protections_Backup;

class Image_Watermark extends Base
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id = 'image-watermark';
        $this->label = __('Image Watermark', 'ultimate-watermark');

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
            '' => __('General Settings', 'ultimate-watermark'),
            'watermark-image' => __('Watermark Image', 'ultimate-watermark'),
            'watermark-position' => __('Watermark Position', 'ultimate-watermark'),
            'image-protection' => __('Image Protection & Backup', 'ultimate-watermark'),
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
        switch ($current_section) {
            case "watermark-image":
                $settings = Image::get_settings();
                break;
            case "watermark-position":
                $settings = Position::get_settings();
                break;
            case "image-protection":
                $settings = Protections_Backup::get_settings();
                break;
            default:
                $settings = General::get_settings();
                break;
        }

        return apply_filters('ultimate_watermark_get_settings_' . $this->id, $settings, $current_section);
    }
}
