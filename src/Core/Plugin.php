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
use MantraBrain\UltimateWatermark\Integration\RestApiIntegration;

/**
 * Main Plugin Class
 * 
 * This is the main plugin class that orchestrates all functionality.
 * Implements Singleton pattern to ensure only one instance exists.
 * Uses dependency injection and proper error handling for enterprise-level code.
 *
 * @package UltimateWatermark
 * @since 2.0.0
 * @author MantraBrain
 */
class Plugin implements PluginInterface
{
    use SingletonTrait;

    /**
     * Plugin version
     *
     * @var string
     */
    public const VERSION = ULTIMATE_WATERMARK_VERSION;

    /**
     * Plugin directory path
     *
     * @var string
     */
    private string $plugin_dir;

    /**
     * Plugin URL
     *
     * @var string
     */
    private string $plugin_url;

    /**
     * Plugin basename
     *
     * @var string
     */
    private string $plugin_basename;

    /**
     * Admin manager instance
     *
     * @var AdminManager|null
     */
    private ?AdminManager $admin_manager = null;

    /**
     * Asset manager instance
     *
     * @var AssetManager|null
     */
    private ?AssetManager $asset_manager = null;

    /**
     * Array of loaded components for tracking
     *
     * @var array<string, mixed>
     */
    private array $components = [];

    /**
     * Plugin constructor
     * 
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        try {
            $this->defineConstants();
            $this->initHooks();
            $this->loadDependencies();
        } catch (\Exception $e) {
            $this->handleInitializationError($e);
        }
    }

    /**
     * Define plugin constants with validation
     */
    private function defineConstants(): void
    {
        $this->plugin_dir = ULTIMATE_WATERMARK_DIR;
        $this->plugin_url = ULTIMATE_WATERMARK_URL;
        $this->plugin_basename = ULTIMATE_WATERMARK_BASENAME;

        // Validate constants
        if (empty($this->plugin_dir) || !is_dir($this->plugin_dir)) {
            throw new \RuntimeException('Invalid plugin directory path');
        }

        if (empty($this->plugin_url)) {
            throw new \RuntimeException('Invalid plugin URL');
        }

        if (empty($this->plugin_basename)) {
            throw new \RuntimeException('Invalid plugin basename');
        }
    }

    /**
     * Initialize WordPress hooks with proper priorities
     */
    private function initHooks(): void
    {
        // Core initialization hooks
        add_action('init', [$this, 'init'], 0);
        add_action('admin_init', [$this, 'adminInit'], 10);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 10);

         // Run migration on plugins_loaded (early, before admin_init)
        add_action('plugins_loaded', [$this, 'runMigration'], 5);
        
        // Show migration notice in admin
        add_action('admin_notices', [Migration::class, 'showMigrationNotice']);
        
        // Plugin deactivation hook for cleanup
        register_deactivation_hook(ULTIMATE_WATERMARK_FILE, [$this, 'onDeactivation']);
    }

    /**
     * Load plugin dependencies with error handling
     */
    private function loadDependencies(): void
    {
        $this->components = [];

        try {
            // Load core components first
            $this->loadCoreComponents();
            
            // Load admin-specific components
            if (is_admin()) {
                $this->loadAdminComponents();
            }

            // Load frontend components
            $this->loadFrontendComponents();

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Failed to load dependencies - ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Load core components required for both admin and frontend
     */
    private function loadCoreComponents(): void
    {
        // Load post types
        $this->components['watermark_post_type'] = WatermarkPostType::getInstance();
        $this->components['watermark_post_type']->init();
        
        // Load AJAX handlers
        $this->components['ajax_handler'] = WatermarkAjaxHandler::getInstance();
        $this->components['ajax_handler']->init();
        
        $this->components['actions_handler'] = WatermarkActionsHandler::getInstance();
        $this->components['actions_handler']->init();
        
        $this->components['preview_handler'] = new WatermarkPreviewHandler();
        
        // Load toast notification system
        $this->components['toast'] = Toast::getInstance();
        $this->components['toast']->init();
        
        // Load REST API integration (must load regardless of is_admin() for REST API uploads)
        $this->components['rest_api'] = new RestApiIntegration();
        $this->components['rest_api']->init();
    }

    /**
     * Load admin-specific components
     */
    private function loadAdminComponents(): void
    {
        $this->admin_manager = new AdminManager();
        $this->components['admin_manager'] = $this->admin_manager;
    }

    /**
     * Load frontend components
     */
    private function loadFrontendComponents(): void
    {
        $this->asset_manager = new AssetManager();
        $this->components['asset_manager'] = $this->asset_manager;
        
        // Set plugin instance to avoid circular dependency
        $this->asset_manager->setPluginInstance($this);
    }

    /**
     * Handle initialization errors gracefully
     */
    private function handleInitializationError(\Exception $e): void
    {
        error_log('Ultimate Watermark initialization failed: ' . $e->getMessage());
        
        if (is_admin() && current_user_can('activate_plugins')) {
            add_action('admin_notices', function() use ($e) {
                printf(
                    '<div class="error notice"><p><strong>%s:</strong> %s</p></div>',
                    esc_html__('Ultimate Watermark Error', 'ultimate-watermark'),
                    esc_html($e->getMessage())
                );
            });
        }
    }

    /**
     * Initialize plugin
     */
    public function init(): void
    {
        try {
            // Load text domain
            $this->loadTextDomain();
            Migration::run();
            // Fire init action for other components
            do_action('ultimate_watermark_init', $this);
            
        } catch (\Exception $e) {
            $this->handleInitializationError($e);
        }
    }

    /**
     * Admin initialization
     */
    public function adminInit(): void
    {
        try {
            // Fire admin init action for other components
            do_action('ultimate_watermark_admin_init', $this);
            
        } catch (\Exception $e) {
            error_log('Ultimate Watermark admin initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueAssets(): void
    {
        try {
            // Fire enqueue assets action for other components
            do_action('ultimate_watermark_enqueue_assets', $this);
            
        } catch (\Exception $e) {
            error_log('Ultimate Watermark asset enqueue failed: ' . $e->getMessage());
        }
    }

    /**
     * Plugin deactivation cleanup
     */
    public function onDeactivation(): void
    {
        try {
            // Clear any scheduled hooks
            wp_clear_scheduled_hook('ultimate_watermark_cleanup');
            
            // Fire deactivation action
            do_action('ultimate_watermark_deactivate', $this);
            
        } catch (\Exception $e) {
            error_log('Ultimate Watermark deactivation error: ' . $e->getMessage());
        }
    }

    /**
     * Load plugin text domain with validation
     */
    private function loadTextDomain(): void
    {
        $loaded = load_plugin_textdomain(
            'ultimate-watermark',
            false,
            dirname($this->plugin_basename) . '/languages'
        );

        if (!$loaded && is_admin()) {
            add_action('admin_notices', function() {
                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    esc_html__('Ultimate Watermark: Translation files not found.', 'ultimate-watermark')
                );
            });
        }
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
     * @return AssetManager|null
     */
    public function getAssetManager(): ?AssetManager
    {
        return $this->asset_manager;
    }

    /**
     * Get loaded component by name
     *
     * @param string $component_name
     * @return mixed|null
     */
    public function getComponent(string $component_name)
    {
        return $this->components[$component_name] ?? null;
    }

    /**
     * Check if plugin is properly initialized
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return !empty($this->components);
    }

    /**
     * Run migration from old version if needed
     */
    public function runMigration(): void
    {
        try {

            Migration::run();
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Migration failed - ' . $e->getMessage());
            }
        }
    }
}