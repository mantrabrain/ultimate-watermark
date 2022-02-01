<?php

namespace Ultimate_Watermark;
class Install
{

    private static $update_callbacks = array(
        '1.0.4' => array(
            'ultimate_watermark_update_1040_update_previous_option',
        ),
    );

    public static function install()
    {
        if (!is_blog_installed()) {
            return;
        }

        $ultimate_watermark_version = get_option('ultimate_watermark_plugin_version');

        if (empty($ultimate_watermark_version)) {
            self::create_options();
        }
        //save install date
        if (false == get_option('ultimate_watermark_install_date')) {
            update_option('ultimate_watermark_install_date', current_time('timestamp'));
        }

        self::setup_environment();
        self::versionwise_update();
        self::update_ultimate_watermark_version();

    }

    public static function setup_environment()
    {

    }

    private static function create_options()
    {
        
    }

    private static function versionwise_update()
    {
        $ultimate_watermark_version = get_option('ultimate_watermark_plugin_version', null);

        if ($ultimate_watermark_version == '' || $ultimate_watermark_version == null || empty($ultimate_watermark_version)) {
            return;
        }
        if (version_compare($ultimate_watermark_version, ULTIMATE_WATERMARK_VERSION, '<')) { // 2.0.15 < 2.0.16

            foreach (self::$update_callbacks as $version => $callbacks) {

                if (version_compare($ultimate_watermark_version, $version, '<')) { // 2.0.15 < 2.0.16

                    self::exe_update_callback($callbacks);
                }
            }
        }
    }

    private static function exe_update_callback($callbacks)
    {
        include_once ULTIMATE_WATERMARK_ABSPATH . 'includes/Helpers/update-functions.php';

        foreach ($callbacks as $callback) {

            call_user_func($callback);

        }
    }

    /**
     * Update version to current.
     */
    private static function update_ultimate_watermark_version()
    {
        delete_option('ultimate_watermark_plugin_version');
        delete_option('ultimate_watermark_plugin_db_version');
        add_option('ultimate_watermark_plugin_version', ULTIMATE_WATERMARK_VERSION);
        add_option('ultimate_watermark_plugin_db_version', ULTIMATE_WATERMARK_VERSION);
    }

    public static function init()
    {

        add_action('init', array(__CLASS__, 'check_version'), 5);

    }

    public static function check_version()
    {
        if (!defined('IFRAME_REQUEST') && version_compare(get_option('ultimate_watermark_plugin_version'), ULTIMATE_WATERMARK_VERSION, '<')) {
            self::install();
            do_action('ultimate_watermark_updated');
        }
    }

}
