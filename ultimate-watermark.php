<?php
/**
 * Plugin Name: Ultimate Watermark
 * Plugin URI: https://mantrabrain.com/ultimate-watermark
 * Description: Advanced WordPress Image Watermarking Plugin
 * Version: 2.0.6
 * Author: MantraBrain
 * Author URI: https://mantrabrain.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ultimate-watermark
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Network: false
 *
 * @package UltimateWatermark
 * @author MantraBrain
 * @version 2.0.6
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Check PHP version compatibility
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(sprintf(
        __('Ultimate Watermark requires PHP 7.4 or higher. You are running %s', 'ultimate-watermark'),
        PHP_VERSION
    ));
}

// Define plugin constants
define('ULTIMATE_WATERMARK_VERSION', '2.0.6');
define('ULTIMATE_WATERMARK_FILE', __FILE__);
define('ULTIMATE_WATERMARK_DIR', plugin_dir_path(__FILE__));
define('ULTIMATE_WATERMARK_URL', plugin_dir_url(__FILE__));
define('ULTIMATE_WATERMARK_BASENAME', plugin_basename(__FILE__));
define('ULTIMATE_WATERMARK_MIN_PHP', '7.4.0');
define('ULTIMATE_WATERMARK_MIN_WP', '5.0');

// Load Composer autoloader with error handling
$autoloader_path = ULTIMATE_WATERMARK_DIR . 'vendor/autoload.php';
if (!file_exists($autoloader_path)) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(__('Ultimate Watermark: Composer autoloader not found. Please run "composer install"', 'ultimate-watermark'));
}

require_once $autoloader_path;

// Initialize the plugin with proper error handling
add_action('plugins_loaded', function () {
    try {
        if (class_exists('MantraBrain\\UltimateWatermark\\Core\\Plugin')) {
            \MantraBrain\UltimateWatermark\Core\Plugin::getInstance();
        } else {
            throw new \Exception('Plugin class not found');
        }
    } catch (\Exception $e) {
        error_log('Ultimate Watermark initialization failed: ' . $e->getMessage());
        if (is_admin() && current_user_can('activate_plugins')) {
            add_action('admin_notices', function() use ($e) {
                printf(
                    '<div class="error notice"><p>%s</p></div>',
                    sprintf(
                        __('Ultimate Watermark initialization failed: %s', 'ultimate-watermark'),
                        esc_html($e->getMessage())
                    )
                );
            });
        }
    }
});

// Activation hook with error handling
register_activation_hook(__FILE__, function () {
    try {
        if (class_exists('MantraBrain\\UltimateWatermark\\Core\\Activator')) {
            \MantraBrain\UltimateWatermark\Core\Activator::activate();
        } else {
            throw new \Exception('Activator class not found');
        }
    } catch (\Exception $e) {
        error_log('Ultimate Watermark activation failed: ' . $e->getMessage());
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(sprintf(
            __('Ultimate Watermark activation failed: %s', 'ultimate-watermark'),
            esc_html($e->getMessage())
        ));
    }
});

// Deactivation hook with error handling
register_deactivation_hook(__FILE__, function () {
    try {
        if (class_exists('MantraBrain\\UltimateWatermark\\Core\\Deactivator')) {
            \MantraBrain\UltimateWatermark\Core\Deactivator::deactivate();
        }
    } catch (\Exception $e) {
        error_log('Ultimate Watermark deactivation error: ' . $e->getMessage());
    }
});
