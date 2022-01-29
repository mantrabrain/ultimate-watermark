<?php

namespace Ultimate_Watermark;

use Ultimate_Watermark\Admin\Admin;
use Ultimate_Watermark\Admin\Ajax;
use Ultimate_Watermark\Admin\Assets;
use Ultimate_Watermark\Admin\Options;
use Ultimate_Watermark\Admin\Settings;
use Ultimate_Watermark\Admin\Update;
use Ultimate_Watermark\Admin\Utils;
use Ultimate_Watermark\Image\Watermark;

final class Init
{

    private static $instance;
    private $is_admin = true;
    private $extension = false;
    private $allowed_mime_types = array(
        'image/jpeg',
        'image/pjpeg',
        'image/png'
    );
    private $is_watermarked_metakey = 'ulwm-is-watermarked';
    public $is_backup_folder_writable = null;
    public $extensions;
    public $defaults = array(
        'options' => array(
            'watermark_on' => array(),
            'watermark_cpt_on' => array('everywhere'),
            'watermark_image' => array(
                'extension' => '',
                'attachment_id' => 0,
                'width' => 80,
                'plugin_off' => 0,
                'frontend_active' => false,
                'manual_watermarking' => 0,
                'position' => 'bottom_right',
                'watermark_size_type' => 2,
                'offset_unit' => 'pixels',
                'offset_width' => 0,
                'offset_height' => 0,
                'absolute_width' => 0,
                'absolute_height' => 0,
                'transparent' => 50,
                'quality' => 90,
                'jpeg_format' => 'baseline',
                'deactivation_delete' => false,
                'media_library_notice' => true
            ),
            'image_protection' => array(
                'rightclick' => 0,
                'draganddrop' => 0,
                'forlogged' => 0
            ),
            'backup' => array(
                'backup_image' => true,
                'backup_quality' => 90
            )
        ),
        'version' => ULTIMATE_WATERMARK_VERSION
    );

    /**
     * Options instance.
     *
     * @var Options
     */
    public $options;

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
     * Class constructor.
     */
    public function __construct()
    {
        // installer
        register_activation_hook(__FILE__, array($this, 'activate_watermark'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_watermark'));

        // settings
        if (!isset($this->defaults['options'])) {
            $this->defaults['options'] = array();
        }
        $options = get_option('ultimate_watermark_options', $this->defaults['options']);

        $this->options = array_merge($this->defaults['options'], $options);
        $options['watermark_image'] = is_array($options['watermark_image']) ? $options['watermark_image'] : array();
        $options['image_protection'] = is_array($options['image_protection']) ? $options['image_protection'] : array();


        $this->options['watermark_image'] = array_merge($this->defaults['options']['watermark_image'], $options['watermark_image']);


        $this->options['image_protection'] = array_merge($this->defaults['options']['image_protection'], $options['image_protection']);

        $this->options['backup'] = array_merge($this->defaults['options']['backup'], isset($options['backup']) ? $options['backup'] : array());

        new Settings();
        new Update();
        new Assets();
        $option_instance = new Options();
        $this->options = $option_instance->get_options();

        // actions
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        new \Ultimate_Watermark\Assets();
        new Admin();
        add_action('admin_notices', array($this, 'bulk_admin_notices'));
        new Ajax();
        $this->utils = new Utils();
        $this->watermark = new Watermark();

        // filters
        add_filter('plugin_row_meta', array($this, 'plugin_extend_links'), 10, 2);
        add_filter('plugin_action_links', array($this, 'plugin_settings_link'), 10, 2);

        // define our backup location
        $upload_dir = wp_upload_dir();
        define('ULTIMATE_WATERMARK_BACKUP_DIR', apply_filters('ultimate_watermark_backup_dir', $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'ulwm-backup'));

        // create backup folder and security if enabled
        if ($this->options['backup']['backup_image']) {

            if (is_writable($upload_dir['basedir'])) {
                $this->is_backup_folder_writable = true;

                // create backup folder ( if it exists this returns true: https://codex.wordpress.org/Function_Reference/wp_mkdir_p )
                $backup_folder_created = wp_mkdir_p(ULTIMATE_WATERMARK_BACKUP_DIR);

                // check if the folder exists and is writable
                if ($backup_folder_created && is_writable(ULTIMATE_WATERMARK_BACKUP_DIR)) {
                    // check if the htaccess file exists
                    if (!file_exists(ULTIMATE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . '.htaccess')) {
                        // htaccess security
                        file_put_contents(ULTIMATE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all');
                    }
                } else {
                    $this->is_backup_folder_writable = false;
                }
            } else {
                $this->is_backup_folder_writable = false;
            }
            if (true !== $this->is_backup_folder_writable) {
                // disable backup setting
                $this->options['backup']['backup_image'] = false;
                update_option('ultimate_watermark_options', $this->options);
            }

            add_action('admin_notices', array($this, 'folder_writable_admin_notice'));
        }

    }

    /**
     * Create single instance.
     *
     * @return object Main plugin instance
     */
    public static function instance()
    {
        if (self::$instance === null)
            self::$instance = new self();

        return self::$instance;
    }

    /**
     * Plugin activation.
     */
    public function activate_watermark()
    {
        update_option('ultimate_watermark_options', $this->defaults['options'], 'no');
        add_option('ultimate_watermark_version', $this->defaults['version'], '', 'no');
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate_watermark()
    {
        // remove options from database?
        if ($this->options['watermark_image']['deactivation_delete'])
            delete_option('ultimate_watermark_options');
    }


    /**
     * Load textdomain.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('ultimate-watermark', false, basename(dirname(__FILE__)) . '/languages');
    }






    /**
     * Add watermark buttons on attachment image locations
     */

    /**
     * Apply watermark for selected images on media page.
     */

    /**
     * Apply watermark for selected images on media page.
     *
     * @return void
     */

    /**
     * Display admin notices.
     *
     * @return mixed
     */
    public function bulk_admin_notices()
    {
        global $post_type, $pagenow;

        if ($pagenow === 'upload.php') {
            if (!current_user_can('upload_files'))
                return;

            // hide media library notice
            if (isset($_GET['ulwm_action']) && $_GET['ulwm_action'] == 'hide_library_notice') {
                $this->options['watermark_image']['media_library_notice'] = false;
                update_option('ultimate_watermark_options', $this->options);
            }

            // check if manual watermarking is enabled
            if (!empty($this->options['watermark_image']['manual_watermarking']) && (!isset($this->options['watermark_image']['media_library_notice']) || $this->options['watermark_image']['media_library_notice'] === true)) {
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

    /**
     * Check whether ImageMagick extension is available.
     *
     * @return boolean True if extension is available
     */


    /**
     * Check whether GD extension is available.
     *
     * @return boolean True if extension is available
     */

    /**
     * Apply watermark to selected image sizes.
     *
     * @param array $data
     * @param int|string $attachment_id Attachment ID
     * @param string $method
     * @return array
     */

    /**
     * Remove watermark from selected image sizes.
     *
     * @param array $data
     * @param int|string $attachment_id Attachment ID
     * @param string $method
     * @return array
     */


    /**
     * Make a backup of the full size image.
     *
     * @param array $data
     * @param string $upload_dir
     * @param int $attachment_id
     * @return bool
     */

    /**
     * Get image resource accordingly to mimetype.
     *
     * @param string $filepath
     * @param string $mime_type
     * @return resource
     */

    /**
     * Get image filename without the uploaded folders.
     *
     * @param string $filepath
     * @return string $filename
     */
    private function get_image_filename($filepath)
    {
        return basename($filepath);
    }

    /**
     * Get image backup folder.
     *
     * @param string $filepath
     * @return string $image_backup_folder
     */

    /**
     * Get image resource from the backup folder (if available).
     *
     * @param string $filepath
     * @return string $backup_filepath
     */


    /**
     * Delete the image backup if one exists.
     *
     * @param int $attachment_id
     * @return bool $force_delete
     */

    /**
     * Create admin notice when we can't create the backup folder.
     *
     * @return    void
     */
    function folder_writable_admin_notice()
    {
        if (current_user_can('manage_options') && true !== $this->is_backup_folder_writable) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('Ultimate Watermark', 'ultimate-watermark'); ?>
                    - <?php _e('Image backup', 'ultimate-watermark'); ?>
                    : <?php _e("Your uploads folder is not writable so we can't create a backup of your image uploads. We've disabled this feature for now.", 'ultimate-watermark'); ?></p>
            </div>
            <?php
        }
    }


    /**
     * Add links to support forum.
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    public function plugin_extend_links($links, $file)
    {
        if (!current_user_can('install_plugins'))
            return $links;

        $plugin = plugin_basename(__FILE__);

        if ($file == $plugin) {
            return array_merge(
                $links, array(sprintf('<a href="http://www.mantrabrain.com/support/forum/ultimate-watermark/" target="_blank">%s</a>', __('Support', 'ultimate-watermark')))
            );
        }

        return $links;
    }

    /**
     * Add links to settings page.
     *
     * @param array $links
     * @param string $file
     * @return array
     */
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

}