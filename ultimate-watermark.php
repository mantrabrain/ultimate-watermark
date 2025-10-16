<?php
/**
 * Plugin Name: Ultimate Watermark
 * Plugin URI: https://mantrabrain.com/ultimate-watermark
 * Description: Advanced WordPress Image Watermarking Plugin with PSR-4 architecture
 * Version: 2.0.0
 * Author: MantraBrain
 * Author URI: https://mantrabrain.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ultimate-watermark
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: false
 *
 * @package UltimateWatermark
 * @author MantraBrain
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ULTIMATE_WATERMARK_VERSION', '2.0.0');
define('ULTIMATE_WATERMARK_FILE', __FILE__);
define('ULTIMATE_WATERMARK_DIR', plugin_dir_path(__FILE__));
define('ULTIMATE_WATERMARK_URL', plugin_dir_url(__FILE__));
define('ULTIMATE_WATERMARK_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader
if (file_exists(ULTIMATE_WATERMARK_DIR . 'vendor/autoload.php')) {
    require_once ULTIMATE_WATERMARK_DIR . 'vendor/autoload.php';
}

// Initialize the plugin
add_action('plugins_loaded', function () {
    if (class_exists('MantraBrain\\UltimateWatermark\\Core\\Plugin')) {
        \MantraBrain\UltimateWatermark\Core\Plugin::getInstance();
    }
});

// Activation hook
register_activation_hook(__FILE__, function () {
    if (class_exists('MantraBrain\\UltimateWatermark\\Core\\Activator')) {
        \MantraBrain\UltimateWatermark\Core\Activator::activate();
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    if (class_exists('MantraBrain\\UltimateWatermark\\Core\\Deactivator')) {
        \MantraBrain\UltimateWatermark\Core\Deactivator::deactivate();
    }
});
