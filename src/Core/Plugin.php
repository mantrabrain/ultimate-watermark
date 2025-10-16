<?php

namespace MantraBrain\UltimateWatermark\Core;

use MantraBrain\UltimateWatermark\Admin\AdminManager;
use MantraBrain\UltimateWatermark\Assets\AssetManager;
use MantraBrain\UltimateWatermark\Core\Interfaces\PluginInterface;
use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\PostTypes\WatermarkPostType;
use MantraBrain\UltimateWatermark\Ajax\WatermarkAjaxHandler;
use MantraBrain\UltimateWatermark\Ajax\WatermarkActionsHandler;
use MantraBrain\UltimateWatermark\Ajax\WatermarkPreviewHandler;
use MantraBrain\UltimateWatermark\Components\Toast;

/**
 * Main Plugin Class
 * 
 * This is the main plugin class that orchestrates all functionality.
 * Implements Singleton pattern to ensure only one instance exists.
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class Plugin implements PluginInterface
{
    use SingletonTrait;

    /**
     * Plugin version
     *
     * @var string
     */
    public const VERSION = '2.0.0';

    /**
     * Plugin directory path
     *
     * @var string
     */
    private $plugin_dir;

    /**
     * Plugin URL
     *
     * @var string
     */
    private $plugin_url;

    /**
     * Plugin basename
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Admin manager instance
     *
     * @var AdminManager
     */
    private $admin_manager;

    /**
     * Asset manager instance
     *
     * @var AssetManager
     */
    private $asset_manager;

    /**
     * Plugin constructor
     */
    private function __construct()
    {
        $this->defineConstants();
        $this->initHooks();
        $this->loadDependencies();
    }

    /**
     * Define plugin constants
     */
    private function defineConstants(): void
    {
        $this->plugin_dir = ULTIMATE_WATERMARK_DIR;
        $this->plugin_url = ULTIMATE_WATERMARK_URL;
        $this->plugin_basename = ULTIMATE_WATERMARK_BASENAME;
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void
    {
        add_action('init', [$this, 'init'], 0);
        add_action('admin_init', [$this, 'adminInit']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        // Removed admin_enqueue_scripts hook - AssetManager handles this directly
    }

    /**
     * Load plugin dependencies
     */
    private function loadDependencies(): void
    {
        // Load post types
        WatermarkPostType::getInstance()->init();
        
        // Load AJAX handlers
        WatermarkAjaxHandler::getInstance()->init();
        WatermarkActionsHandler::getInstance()->init();
        new WatermarkPreviewHandler();
        
        // Load toast system
        Toast::getInstance()->init();
        
        // Load admin functionality
        if (is_admin()) {
            $this->admin_manager = new AdminManager();
        }

        // Load asset manager
        $this->asset_manager = new AssetManager();
    }

    /**
     * Initialize plugin
     */
    public function init(): void
    {
        // Load text domain
        $this->loadTextDomain();

        // Fire init action
        do_action('ultimate_watermark_init');
    }

    /**
     * Admin initialization
     */
    public function adminInit(): void
    {
        // Fire admin init action
        do_action('ultimate_watermark_admin_init');
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueAssets(): void
    {
        do_action('ultimate_watermark_enqueue_assets');
    }


    /**
     * Load plugin text domain
     */
    private function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'ultimate-watermark',
            false,
            dirname($this->plugin_basename) . '/languages'
        );
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get plugin directory path
     *
     * @return string
     */
    public function getPluginDir(): string
    {
        return $this->plugin_dir;
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public function getPluginUrl(): string
    {
        return $this->plugin_url;
    }

    /**
     * Get plugin basename
     *
     * @return string
     */
    public function getPluginBasename(): string
    {
        return $this->plugin_basename;
    }

    /**
     * Get admin manager instance
     *
     * @return AdminManager|null
     */
    public function getAdminManager(): ?AdminManager
    {
        return $this->admin_manager;
    }

    /**
     * Get asset manager instance
     *
     * @return AssetManager
     */
    public function getAssetManager(): AssetManager
    {
        return $this->asset_manager;
    }
}