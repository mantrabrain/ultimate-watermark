<?php

namespace Ultimate_Watermark\Meta;

use Ultimate_Watermark\Admin\Fields\GeneralSettings;
use Ultimate_Watermark\Admin\Fields\MapTypeFields;
use Ultimate_Watermark\Admin\Fields\MarkerFields;
use Ultimate_Watermark\Admin\Fields\OSMProviderFields;
use Ultimate_Watermark\Admin\Fields\WatermarkGeneralFields;
use Ultimate_Watermark\Admin\Fields\WatermarkImageFields;

class WatermarkMeta
{

    public function metabox()
    {
        $current_screen = get_current_screen();

        $screen_id = $current_screen->id ?? '';


        if ($screen_id != 'ultimate-watermark') {
            return;
        }
        add_action('edit_form_after_editor', array($this, 'watermark_template'));
        add_action('edit_form_after_editor', array($this, 'watermark_setting_template'));

    }


    public function save($post_id)
    {

        if (get_post_type($post_id) !== 'ultimate-watermark') {
            return;
        }

        $active_tab = isset($_POST['ultimate_watermark_meta_active_tab']) ? sanitize_text_field($_POST['ultimate_watermark_meta_active_tab']) : 'watermark_general_options';


        update_post_meta($post_id, 'ultimate_watermark_meta_active_tab', $active_tab);

    }

    public function watermark_template($post)
    {

        if ($post->post_type !== 'ultimate-watermark') {
            return;
        }

        ultimate_watermark_load_admin_template('Metabox.Watermark');
    }


    public function watermark_setting_template($post)
    {
        if ($post->post_type !== 'ultimate-watermark') {
            return;
        }

        $setting_tabs = array(
            'watermark_general_options' => __('General Settings', 'ultimate-watermark'),
            'watermark_image_options' => __('Watermark Image', 'ultimate-watermark'),


        );
        $active_tab = get_post_meta($post->ID, 'ultimate_watermark_meta_active_tab', true);

        $active_tab = isset($setting_tabs[$active_tab]) ? $active_tab : 'watermark_general_options';

        ultimate_watermark_load_admin_template('Metabox.Settings', array(
                'setting_tabs' => $setting_tabs,
                'active_tab' => $active_tab
            )
        );

    }

    public function general_option_template()
    {

        $general_settings = new WatermarkGeneralFields();

        $general_settings->render();
    }

    public function image_option_template()
    {
        $watermark_image_fields = new WatermarkImageFields();

        $watermark_image_fields->render();

    }

    public function scripts()
    {
        $screen = get_current_screen();

        $screen_id = $screen->id ?? '';

        if ($screen_id != 'ultimate-watermark') {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('ultimate-watermark-admin-meta-style', ULTIMATE_WATERMARK_URI . '/assets/admin/css/meta-settings.css', array(), ULTIMATE_WATERMARK_VERSION);
        wp_enqueue_script('ultimate-watermark-admin-meta-script', ULTIMATE_WATERMARK_URI . '/assets/admin/js/meta-settings.js', array(), ULTIMATE_WATERMARK_VERSION, true);
        wp_localize_script('ultimate-watermark-admin-script', 'geoMapsAdminParams', array(
            'options' => [],
            'default_marker' => [],
        ));


    }

    public function preview_watermark()
    {

        $map_id = get_the_ID();

        echo '<h1>Hello This is Watermark Preview Section</h1>';

        // ultimate_watermark_render_map($map_settings);
    }

    public function hide_screen_option($show_screen)
    {
        if (get_current_screen()->post_type === 'ultimate-watermark') {

            return false;
        }
        return $show_screen;

    }

    public static function init()
    {
        $self = new self();
        add_filter('screen_options_show_screen', array($self, 'hide_screen_option'));
        add_action('add_meta_boxes', array($self, 'metabox'));
        add_action('save_post', array($self, 'save'));
        add_action('admin_enqueue_scripts', array($self, 'scripts'), 10);
        add_action('ultimate_watermark_metabox_postbox_item', array($self, 'preview_watermark'), 10);
        add_action('ultimate_watermark_meta_tab_content_watermark_image_options', array($self, 'image_option_template'), 10);
        add_action('ultimate_watermark_meta_tab_content_watermark_general_options', array($self, 'general_option_template'), 10);

    }

}
