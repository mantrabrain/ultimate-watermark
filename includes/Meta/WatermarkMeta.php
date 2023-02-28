<?php

namespace Ultimate_Watermark\Meta;

use Ultimate_Watermark\Admin\Fields\GeneralSettings;
use Ultimate_Watermark\Admin\Fields\MapTypeFields;
use Ultimate_Watermark\Admin\Fields\MarkerFields;
use Ultimate_Watermark\Admin\Fields\OSMProviderFields;
use Ultimate_Watermark\Admin\Fields\WatermarkApplyConditions;
use Ultimate_Watermark\Admin\Fields\WatermarkGeneralFields;
use Ultimate_Watermark\Admin\Fields\WatermarkImageFields;
use Ultimate_Watermark\Admin\Fields\WatermarkPositionFields;

class WatermarkMeta
{

    public function metabox()
    {
        $current_screen = get_current_screen();

        $screen_id = $current_screen->id ?? '';


        if ($screen_id != 'ultimate-watermark') {
            return;
        }
        add_action('edit_form_after_editor', array($this, 'watermark_setting_template'));
        add_meta_box('ultimate-watermark-watermark-preview',
            __('Watermark Preview', 'ultimate-watermark'), array($this, 'watermark_preview'), 'ultimate-watermark', 'side', 'low');

    }


    public function save($post_id)
    {

        if (get_post_type($post_id) !== 'ultimate-watermark') {
            return;
        }

        $general_fields = new WatermarkGeneralFields();
        $general_fields->save($_POST, $post_id);


        $active_tab = isset($_POST['ultimate_watermark_meta_active_tab']) ? sanitize_text_field($_POST['ultimate_watermark_meta_active_tab']) : 'watermark_general_options';


        update_post_meta($post_id, 'ultimate_watermark_meta_active_tab', $active_tab);

    }

    public function watermark_preview($post)
    {

        if ($post->post_type !== 'ultimate-watermark') {
            return;
        }

        ultimate_watermark_load_admin_template('Metabox.Preview');
    }


    public function watermark_setting_template($post)
    {
        if ($post->post_type !== 'ultimate-watermark') {
            return;
        }

        $setting_tabs = array(
            'watermark_general_options' => __('General Settings', 'ultimate-watermark'),
            'watermark_image_options' => __('Watermark Image', 'ultimate-watermark'),
            'watermark_position_options' => __('Watermark Position', 'ultimate-watermark'),
            'watermark_condition_options' => __('Apply Conditions', 'ultimate-watermark'),

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

    public function position_option_template()
    {
        $watermark_position_fields = new WatermarkPositionFields();

        $watermark_position_fields->render();

    }

    public function image_option_template()
    {
        $watermark_image_fields = new WatermarkImageFields();

        $watermark_image_fields->render();

    }

    public function condition_option_template()
    {
        $apply_conditions = new WatermarkApplyConditions();

        $apply_conditions->render();

    }

    public function scripts()
    {
        $screen = get_current_screen();

        $screen_id = $screen->id ?? '';

        if ($screen_id === 'edit-ultimate-watermark') {
            wp_enqueue_style('ultimate-watermark-html-style');
        }


        if ($screen_id != 'ultimate-watermark') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('ultimate-watermark-admin-meta-style');
        wp_enqueue_script('ultimate-watermark-admin-meta-script');
        wp_localize_script('ultimate-watermark-admin-script', 'geoMapsAdminParams', array(
            'options' => [],
            'default_marker' => [],
        ));


    }

    public function preview_watermark()
    {

        $watermark_id = get_the_ID();

        $ajax_url = admin_url('admin-ajax.php?action=ultimate_watermark_preview_placeholder&watermark_id=' . $watermark_id)
        ?>
        <img src="<?php echo esc_url($ajax_url); ?>"
             style="width:100%; max-width:100%;"/>
        <?php

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
        add_action('ultimate_watermark_metabox_preview_watermark', array($self, 'preview_watermark'), 10);
        add_action('ultimate_watermark_meta_tab_content_watermark_image_options', array($self, 'image_option_template'), 10);
        add_action('ultimate_watermark_meta_tab_content_watermark_position_options', array($self, 'position_option_template'), 10);
        add_action('ultimate_watermark_meta_tab_content_watermark_general_options', array($self, 'general_option_template'), 10);
        add_action('ultimate_watermark_meta_tab_content_watermark_condition_options', array($self, 'condition_option_template'), 10);

    }

}

