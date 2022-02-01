<?php

namespace Ultimate_Watermark;


use Ultimate_Watermark\Admin\Ajax;
use Ultimate_Watermark\Admin\Assets;
use Ultimate_Watermark\Admin\Menu;
use Ultimate_Watermark\Admin\Utils;
use Ultimate_Watermark\Image\Watermark;

defined('ABSPATH') || exit;

final class Init
{
    /**
     * The single instance of the class.
     *
     * @var Init
     * @since 1.0.0
     */
    protected static $_instance = null;


    /**
     * Utils instance.
     *
     * @var Utils
     */
    public $utils;

    /**
     * Watermark instance.
     *
     * @var Watermark
     */
    public $watermark;

    /**
     * Main Ultimate Watermark Instance.
     *
     * Ensures only one instance of Ultimate Watermark is loaded or can be loaded.
     *
     * @return Init - Main instance.
     * @since 1.0.0
     * @static
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'ultimate-watermark'), '1.0.0');
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'ultimate-watermark'), '1.0.0');
    }

    /**
     * Auto-load in-accessible properties on demand.
     *
     * @param mixed $key Key name.
     * @return mixed
     */
    public function __get($key)
    {
        if (in_array($key, array(''), true)) {
            return $this->$key();
        }
    }

    /**
     * Constructor.
     */
    public function __construct()
    {

        $this->define_constants();
        $this->includes();
        $this->init_hooks();


        do_action('ultimate_watermark_loaded');
    }

    /**
     * Hook into actions and filters.
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {

        register_activation_hook(ULTIMATE_WATERMARK_FILE, array('\Ultimate_Watermark\Install', 'install'));

        add_action('init', array($this, 'init'), 0);
        add_action('admin_notices', array($this, 'bulk_admin_notices'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_filter('plugin_action_links', array($this, 'plugin_settings_link'), 10, 2);
        add_action('admin_notices', array($this, 'folder_writable_admin_notice'));


    }

    /**
     * Define Constants.
     */
    private function define_constants()
    {

        $this->define('ULTIMATE_WATERMARK_ABSPATH', dirname(ULTIMATE_WATERMARK_FILE) . '/');
        $this->define('ULTIMATE_WATERMARK_BASENAME', plugin_basename(ULTIMATE_WATERMARK_FILE));

    }

    /**
     * Define constant if not already set.
     *
     * @param string $name Constant name.
     * @param string|bool $value Constant value.
     */
    private function define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * What type of request is this?
     *
     * @param string $type admin, ajax, cron or frontend.
     * @return bool
     */
    private function is_request($type)
    {
        switch ($type) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return defined('DOING_AJAX');
            case 'cron':
                return defined('DOING_CRON');
            case 'frontend':
                return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON') && !defined('REST_REQUEST');
        }
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    public function includes()
    {
        include_once ULTIMATE_WATERMARK_ABSPATH . 'includes/Helpers/general-functions.php';
        include_once ULTIMATE_WATERMARK_ABSPATH . 'includes/Helpers/settings-functions.php';

        new Assets();
        new \Ultimate_Watermark\Assets();
        new Hooks();
        new Ajax();
        $this->utils = new Utils();
        $this->watermark = new Watermark();

        if (is_admin()) {
            new Menu();
        }
    }


    /**
     * Init plugin when WordPress Initialises.
     */
    public function init()
    {
        // Before init action.
        do_action('before_ultimate_watermark_init');

        // Set up localisation.
        $this->load_plugin_textdomain();

        if ($this->is_request('admin')) {


        }


        // Init action.
        do_action('ultimate_watermark_init');
    }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any following ones if the same translation is present.
     *
     * Locales found in:
     *      - WP_LANG_DIR/ultimate-watermark/ultimate-watermark-LOCALE.mo
     *      - WP_LANG_DIR/plugins/ultimate-watermark-LOCALE.mo
     */
    public function load_plugin_textdomain()
    {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'ultimate-watermark');
        unload_textdomain('ultimate-watermark');
        load_textdomain('ultimate-watermark', WP_LANG_DIR . '/ultimate-watermark/ultimate-watermark-' . $locale . '.mo');
        load_plugin_textdomain('ultimate-watermark', false, plugin_basename(dirname(ULTIMATE_WATERMARK_FILE)) . '/i18n/languages');
    }


    /**
     * Get the plugin url.
     *
     * @return string
     */
    public function plugin_url()
    {
        return untrailingslashit(plugins_url('/', ULTIMATE_WATERMARK_FILE));
    }

    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path()
    {
        return untrailingslashit(plugin_dir_path(ULTIMATE_WATERMARK_FILE));
    }


    public function bulk_admin_notices()
    {
        global $post_type, $pagenow;

        if ($pagenow === 'upload.php') {
            if (!current_user_can('upload_files'))
                return;

            // hide media library notice
            if (isset($_GET['ulwm_action']) && $_GET['ulwm_action'] == 'hide_library_notice') {
                update_option('ultimate_watermark_media_library_notice', false);
            }

            // check if manual watermarking is enabled
            if (ultimate_watermark_manual_watermarking() && get_option('ultimate_watermark_media_library_notice', true)) {

                $mode = get_user_option('media_library_mode', get_current_user_id()) ? get_user_option('media_library_mode', get_current_user_id()) : 'grid';

                if (isset($_GET['mode']) && in_array($_GET['mode'], array('grid', 'list')))
                    $mode = $_GET['mode'];

                // display notice in grid mode only
                if ($mode === 'grid') {
                    // get current admin url
                    $query_string = array();

                    parse_str($_SERVER['QUERY_STRING'], $query_string);

                    $current_url = esc_url(add_query_arg(array_merge((array)$query_string, array('ulwm_action' => 'hide_library_notice')), '', admin_url(trailingslashit($pagenow))));

                    echo '<div class="error notice"><p>' . sprintf(__('<strong>Ultimate Watermark:</strong> Bulk watermarking is available in list mode only, under <em>Bulk Actions</em> dropdown. <a href="%1$s">Got to List Mode</a> or <a href="%2$s">Hide this notice</a>', 'ultimate-watermark'), esc_url(admin_url('upload.php?mode=list')), esc_url($current_url)) . '</p></div>';
                }
            }

            if (isset($_REQUEST['watermarked'], $_REQUEST['watermarkremoved'], $_REQUEST['skipped']) && $post_type === 'attachment') {
                $watermarked = (int)$_REQUEST['watermarked'];
                $watermarkremoved = (int)$_REQUEST['watermarkremoved'];
                $skipped = (int)$_REQUEST['skipped'];

                if ($watermarked === 0)
                    echo '<div class="error"><p>' . __('Watermark could not be applied to selected files or no valid images (JPEG, PNG) were selected.', 'ultimate-watermark') . ($skipped > 0 ? ' ' . __('Images skipped', 'ultimate-watermark') . ': ' . $skipped . '.' : '') . '</p></div>';
                elseif ($watermarked > 0)
                    echo '<div class="updated"><p>' . sprintf(_n('Watermark was succesfully applied to 1 image.', 'Watermark was succesfully applied to %s images.', $watermarked, 'ultimate-watermark'), number_format_i18n($watermarked)) . ($skipped > 0 ? ' ' . __('Skipped files', 'ultimate-watermark') . ': ' . $skipped . '.' : '') . '</p></div>';

                if ($watermarkremoved === 0)
                    echo '<div class="error"><p>' . __('Watermark could not be removed from selected files or no valid images (JPEG, PNG) were selected.', 'ultimate-watermark') . ($skipped > 0 ? ' ' . __('Images skipped', 'ultimate-watermark') . ': ' . $skipped . '.' : '') . '</p></div>';
                elseif ($watermarkremoved > 0)
                    echo '<div class="updated"><p>' . sprintf(_n('Watermark was succesfully removed from 1 image.', 'Watermark was succesfully removed from %s images.', $watermarkremoved, 'ultimate-watermark'), number_format_i18n($watermarkremoved)) . ($skipped > 0 ? ' ' . __('Skipped files', 'ultimate-watermark') . ': ' . $skipped . '.' : '') . '</p></div>';

                $_SERVER['REQUEST_URI'] = esc_url(remove_query_arg(array('watermarked', 'skipped'), $_SERVER['REQUEST_URI']));
            }
        }
    }

    public function admin_notices()
    {
        
    }

    function folder_writable_admin_notice()
    {
        $backup_dir = $this->get_backup_dir(true);

        if (current_user_can('manage_options') && true !== is_writable($backup_dir . 'index.html')) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('Ultimate Watermark', 'ultimate-watermark'); ?>
                    - <?php _e('Image backup', 'ultimate-watermark'); ?>
                    : <?php _e("Your uploads folder is not writable so we can't create a backup of your image uploads. We've disabled this feature for now.", 'ultimate-watermark'); ?></p>
            </div>
            <?php
        }
    }

    function plugin_settings_link($links, $file)
    {
        if (!is_admin() || !current_user_can('manage_options'))
            return $links;

        static $plugin;

        $plugin = plugin_basename(__FILE__);

        if ($file == $plugin) {
            $settings_link = sprintf('<a href="%s">%s</a>', admin_url('options-general.php') . '?page=watermark-options', __('Settings', 'ultimate-watermark'));
            array_unshift($links, $settings_link);
        }

        return $links;
    }

    /**
     * Get Ajax URL.
     *
     * @return string
     */
    public function ajax_url()
    {
        return admin_url('admin-ajax.php', 'relative');
    }

    public function get_backup_dir($create_if_not_exists = true)
    {
        $wp_upload_dir = wp_upload_dir();

        $backup_dir = $wp_upload_dir['basedir'] . '/ulwm-backup/';

        if (!file_exists(trailingslashit($backup_dir) . 'index.html') && $create_if_not_exists) {

            $files = array(
                array(
                    'base' => $backup_dir,
                    'file' => 'index.html',
                    'content' => '',
                ),
                array(
                    'base' => $backup_dir,
                    'file' => '.htaccess',
                    'content' => 'deny from all',
                )
            );

            $this->create_files($files, $backup_dir);


        }
        return $backup_dir;
    }

    private function create_files($files, $base_dir)
    {
        // Bypass if filesystem is read-only and/or non-standard upload system is used.
        if (apply_filters('ultimate_watermark_install_skip_create_files', false)) {
            return;
        }

        if (file_exists(trailingslashit($base_dir) . 'index.html')) {
            return true;
        }
        $has_created_dir = false;

        foreach ($files as $file) {
            if (wp_mkdir_p($file['base']) && !file_exists(trailingslashit($file['base']) . $file['file'])) {
                $file_handle = @fopen(trailingslashit($file['base']) . $file['file'], 'w'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen
                if ($file_handle) {
                    fwrite($file_handle, $file['content']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
                    fclose($file_handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
                    if (!$has_created_dir) {
                        $has_created_dir = true;
                    }
                }
            }
        }
        if ($has_created_dir) {
            return true;
        }


    }

}
