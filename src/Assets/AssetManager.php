<?php

namespace MantraBrain\UltimateWatermark\Assets;

/**
 * Asset Manager Class
 * 
 * Manages all CSS, JS, and other assets for the plugin with performance optimization
 * and proper security practices.
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class AssetManager
{
    /**
     * Plugin instance
     *
     * @var \MantraBrain\UltimateWatermark\Core\Plugin|null
     */
    private ?\MantraBrain\UltimateWatermark\Core\Plugin $plugin = null;

    /**
     * Cache for asset URLs to avoid repeated calculations
     *
     * @var array<string, string>
     */
    private array $asset_cache = [];

    /**
     * Registered admin pages keyed by their menu slug.
     *
     * We deliberately key on menu slug (not the full hook suffix) because
     * WordPress builds the submenu hook prefix from sanitize_title($parent_menu_title) —
     * for our plugin the parent title "Watermark" sanitizes to "watermark", so the
     * actual hook suffix for "ultimate-watermark-watermarks" is
     * "watermark_page_ultimate-watermark-watermarks", not
     * "ultimate-watermark_page_ultimate-watermark-watermarks". Hard-coding the full
     * hook suffix is fragile; matching on menu slug is robust.
     *
     * @var array<string, array{styles?: string[], scripts?: string[], dependencies?: string[]}>
     */
    private array $admin_page_assets = [
        // Dashboard (top-level menu page).
        'ultimate-watermark' => [
            'styles' => ['dashboard'],
            'scripts' => ['dashboard'],
        ],
        'ultimate-watermark-watermarks' => [
            'styles' => ['watermarks'],
            'scripts' => ['watermarks'],
        ],
        'ultimate-watermark-add-watermark' => [
            'styles' => ['add-watermark'],
            'scripts' => ['add-watermark'],
            'dependencies' => ['media-upload', 'media-views', 'media-models'],
        ],
        'ultimate-watermark-analytics' => [
            'styles' => ['analytics'],
            'scripts' => ['analytics'],
        ],
        'ultimate-watermark-backups' => [
            'styles' => ['backup-page'],
            'scripts' => ['backup-page'],
        ],
        'ultimate-watermark-settings' => [
            'styles' => ['settings'],
            'scripts' => ['settings'],
        ],
        'ultimate-watermark-get-pro' => [
            'styles' => ['pro-features'],
            'scripts' => [],
        ],
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        try {
            // Don't get plugin instance here to avoid circular dependency
            $this->initHooks();
        } catch (\Exception $e) {
            error_log('Ultimate Watermark Asset Manager initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Get plugin version (with cache-bust when SCRIPT_DEBUG is enabled)
     */
    private function getVersion(): string
    {
        $base = defined('ULTIMATE_WATERMARK_VERSION') ? ULTIMATE_WATERMARK_VERSION : '2.1.0';

        // When SCRIPT_DEBUG is on (or WP_DEBUG), append the plugin file mtime so
        // every CSS/JS edit invalidates the browser cache immediately.
        if ((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || (defined('WP_DEBUG') && WP_DEBUG)) {
            $marker = defined('ULTIMATE_WATERMARK_FILE') && file_exists(ULTIMATE_WATERMARK_FILE)
                ? filemtime(ULTIMATE_WATERMARK_FILE)
                : null;
            if ($marker) {
                return $base . '.' . $marker;
            }
        }

        return $base;
    }

    /**
     * Set plugin instance (called by Plugin class after initialization)
     */
    public function setPluginInstance(\MantraBrain\UltimateWatermark\Core\Plugin $plugin): void
    {
        $this->plugin = $plugin;
    }

    /**
     * Initialize WordPress hooks with proper priorities
     */
    private function initHooks(): void
    {
        // Frontend assets with lower priority to allow theme overrides
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets'], 15);

        // Admin assets with proper priority
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets'], 10);

        // NOTE: Do not "optimize" admin stylesheets via preload tricks.
        // admin.css holds the :root design tokens (--uw-primary, --uw-space-*, etc.)
        // that every page-specific stylesheet relies on. If admin.css is converted
        // to <link rel="preload"> and the JS onload handler doesn't fire (which it
        // won't in many environments — caching plugins, CSP, slow hosts, etc.), the
        // tokens stay undefined and the entire admin UI renders without styling.
    }

    /**
     * Enqueue frontend assets with conditional loading
     */
    public function enqueueFrontendAssets(): void
    {
        if (!$this->shouldLoadFrontendAssets()) {
            return;
        }

        try {
            $this->enqueueFrontendStyles();
            $this->enqueueFrontendScripts();
        } catch (\Exception $e) {
            error_log('Ultimate Watermark frontend asset loading failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if frontend assets should be loaded
     */
    private function shouldLoadFrontendAssets(): bool
    {
        // Only load if protection features are enabled
        $options = get_option('ultimate_watermark_options', []);
        $protection_enabled = !empty($options['disable_rightclick']) || !empty($options['disable_drag_drop']);
        
        return apply_filters('ultimate_watermark_load_frontend_assets', $protection_enabled);
    }

    /**
     * Enqueue frontend styles
     */
    private function enqueueFrontendStyles(): void
    {
        wp_enqueue_style(
            'ultimate-watermark-frontend',
            $this->getAssetUrl('css/frontend.css'),
            [],
            $this->getVersion(),
            'all'
        );
    }

    /**
     * Enqueue frontend scripts with proper localization
     */
    private function enqueueFrontendScripts(): void
    {
        wp_enqueue_script(
            'ultimate-watermark-frontend',
            $this->getAssetUrl('js/frontend.js'),
            ['jquery'],
            $this->getVersion(),
            [
                'strategy' => 'defer',
                'in_footer' => true
            ]
        );

        $this->localizeFrontendScript();
    }

    /**
     * Localize frontend script with sanitized data
     */
    private function localizeFrontendScript(): void
    {
        $raw_settings = get_option('ultimate_watermark_options', []);
        $sanitized_settings = $this->sanitizeSettingsForJs($raw_settings);
        
        wp_localize_script('ultimate-watermark-frontend', 'ultimateWatermark', [
            'ajaxUrl' => esc_url(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('ultimate_watermark_ajax'),
            'settings' => $sanitized_settings,
            'isLoggedIn' => is_user_logged_in(),
            'strings' => [
                'protected' => __('This image is protected', 'ultimate-watermark'),
                'rightClickDisabled' => __('Right-click is disabled', 'ultimate-watermark'),
            ],
        ]);
    }

    /**
     * Sanitize settings for JavaScript output
     */
    private function sanitizeSettingsForJs(array $settings): array
    {
        $sanitized = [];
        
        foreach ($settings as $key => $value) {
            $sanitized_key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$sanitized_key] = array_map(function($item) {
                    return is_string($item) ? sanitize_text_field($item) : $item;
                }, $value);
            } elseif (is_string($value)) {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            } elseif (is_bool($value)) {
                $sanitized[$sanitized_key] = $value;
            } elseif (is_numeric($value)) {
                $sanitized[$sanitized_key] = (int) $value;
            } else {
                $sanitized[$sanitized_key] = '';
            }
        }
        
        return $sanitized;
    }

    /**
     * Enqueue admin assets with page-specific loading
     */
    public function enqueueAdminAssets(string $hook_suffix = ''): void
    {
        if (!$this->shouldLoadAdminAssets($hook_suffix)) {
            return;
        }

        try {
            $this->enqueueCommonAdminAssets();
            $this->enqueuePageSpecificAssets($hook_suffix);
            $this->localizeAdminScript();

            /**
             * Fires after the plugin has finished enqueuing its admin assets.
             * Pro plugins use this hook to enqueue their own admin CSS/JS.
             *
             * @param string $hook_suffix Current admin page hook suffix.
             */
            do_action('ultimate_watermark_admin_enqueue_scripts', $hook_suffix);
        } catch (\Exception $e) {
            error_log('Ultimate Watermark admin asset loading failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if admin assets should be loaded
     */
    private function shouldLoadAdminAssets(string $hook_suffix): bool
    {
        return $this->resolveMenuSlugFromHookSuffix($hook_suffix) !== null;
    }

    /**
     * Resolve our menu slug from any of the hook-suffix shapes WordPress emits.
     *
     * WP builds the suffix as "{page_type}_page_{plugin_page}":
     *  - page_type = "toplevel"          for the top-level menu page itself
     *  - page_type = sanitize_title($parent_title) for submenu pages
     *
     * Our parent menu title is "Watermark", which sanitizes to "watermark", so
     * submenu pages come through as "watermark_page_ultimate-watermark-*". We
     * also defensively support the historical "ultimate-watermark_page_*" form
     * in case the parent title is changed in the future.
     *
     * @return string|null The menu slug (key into $admin_page_assets) or null
     *                     if this hook doesn't belong to our plugin.
     */
    private function resolveMenuSlugFromHookSuffix(string $hook_suffix): ?string
    {
        if ($hook_suffix === '') {
            return null;
        }

        // Strip the "{page_type}_page_" prefix in a prefix-agnostic way.
        if (preg_match('/_page_(.+)$/', $hook_suffix, $m)) {
            $candidate = $m[1];
        } elseif (strpos($hook_suffix, 'toplevel_page_') === 0) {
            $candidate = substr($hook_suffix, strlen('toplevel_page_'));
        } else {
            $candidate = $hook_suffix;
        }

        return isset($this->admin_page_assets[$candidate]) ? $candidate : null;
    }

    /**
     * Enqueue common admin assets
     */
    private function enqueueCommonAdminAssets(): void
    {
        // Common admin styles
        wp_enqueue_style(
            'ultimate-watermark-admin',
            $this->getAssetUrl('css/admin.css'),
            [],
            $this->getVersion(),
            'all'
        );

        wp_enqueue_style(
            'ultimate-watermark-layout',
            $this->getAssetUrl('css/layout.css'),
            ['ultimate-watermark-admin'],
            $this->getVersion(),
            'all'
        );

        // Common admin scripts
        wp_enqueue_script(
            'ultimate-watermark-admin',
            $this->getAssetUrl('js/admin.js'),
            ['jquery', 'wp-color-picker'],
            $this->getVersion(),
            [
                'strategy' => 'defer',
                'in_footer' => true
            ]
        );

        wp_enqueue_style('wp-color-picker');
    }

    /**
     * Enqueue page-specific assets
     */
    private function enqueuePageSpecificAssets(string $hook_suffix): void
    {
        $menu_slug = $this->resolveMenuSlugFromHookSuffix($hook_suffix);

        if ($menu_slug === null || !isset($this->admin_page_assets[$menu_slug])) {
            return;
        }

        $page_assets = $this->admin_page_assets[$menu_slug];

        // Enqueue page-specific styles
        if (!empty($page_assets['styles'])) {
            foreach ($page_assets['styles'] as $style) {
                wp_enqueue_style(
                    "ultimate-watermark-{$style}",
                    $this->getAssetUrl("css/{$style}.css"),
                    ['ultimate-watermark-admin', 'ultimate-watermark-layout'],
                    $this->getVersion(),
                    'all'
                );
            }
        }

        // Enqueue page-specific scripts
        if (!empty($page_assets['scripts'])) {
            foreach ($page_assets['scripts'] as $script) {
                $dependencies = ['jquery', 'wp-color-picker'];
                
                if (!empty($page_assets['dependencies'])) {
                    $dependencies = array_merge($dependencies, $page_assets['dependencies']);
                }

                // Special handling for media-dependent pages
                (in_array('media-upload', $page_assets['dependencies'] ?? [])) && wp_enqueue_media();

                wp_enqueue_script(
                    "ultimate-watermark-{$script}",
                    $this->getAssetUrl("js/{$script}.js"),
                    $dependencies,
                    $this->getVersion(),
                    [
                        'strategy' => 'defer',
                        'in_footer' => true
                    ]
                );
            }
        }
    }

    /**
     * Localize admin script
     */
    private function localizeAdminScript(): void
    {
        // Localize main admin script with multiple nonces for different operations
        wp_localize_script('ultimate-watermark-admin', 'ultimateWatermarkAdmin', [
            'ajaxUrl' => esc_url(admin_url('admin-ajax.php')),
            'nonces' => [
                'ajax' => wp_create_nonce('ultimate_watermark_ajax'),
                'settings' => wp_create_nonce('ultimate_watermark_settings'),
                'backup' => wp_create_nonce('ultimate_watermark_backup_nonce'),
                'toggle' => wp_create_nonce('ultimate_watermark_toggle'),
                'analytics' => wp_create_nonce('ultimate_watermark_analytics'),
            ],
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'ultimate-watermark'),
                'confirmBulkAction' => __('Are you sure you want to perform this bulk action?', 'ultimate-watermark'),
                'processing' => __('Processing...', 'ultimate-watermark'),
                'success' => __('Success!', 'ultimate-watermark'),
                'error' => __('Error occurred!', 'ultimate-watermark'),
                'noWatermarks' => __('No watermarks found', 'ultimate-watermark'),
                'selectImages' => __('Please select at least one image', 'ultimate-watermark'),
            ],
        ]);

        // Localize add-watermark script with AJAX URL
        if (wp_script_is('ultimate-watermark-add-watermark', 'enqueued')) {
            wp_localize_script('ultimate-watermark-add-watermark', 'ultimate_watermark_ajax', [
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('ultimate_watermark_ajax'),
                'strings' => [
                    'saving' => __('Saving watermark...', 'ultimate-watermark'),
                    'saved' => __('Watermark saved successfully!', 'ultimate-watermark'),
                    'error' => __('Error saving watermark', 'ultimate-watermark'),
                    'preview_loading' => __('Loading preview...', 'ultimate-watermark'),
                    'preview_error' => __('Error loading preview', 'ultimate-watermark'),
                ],
            ]);
        }

        // Localize watermarks script with AJAX URL
        if (wp_script_is('ultimate-watermark-watermarks', 'enqueued')) {
            wp_localize_script('ultimate-watermark-watermarks', 'ultimate_watermark_ajax', [
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('ultimate_watermark_ajax'),
                'strings' => [
                    'deleting' => __('Deleting watermark...', 'ultimate-watermark'),
                    'deleted' => __('Watermark deleted successfully!', 'ultimate-watermark'),
                    'error' => __('Error deleting watermark', 'ultimate-watermark'),
                    'confirmDelete' => __('Are you sure you want to delete this watermark?', 'ultimate-watermark'),
                ],
            ]);
        }

        // Localize dashboard script with AJAX URL
        if (wp_script_is('ultimate-watermark-dashboard', 'enqueued')) {
            wp_localize_script('ultimate-watermark-dashboard', 'ultimate_watermark_ajax', [
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('ultimate_watermark_ajax'),
                'strings' => [
                    'loading' => __('Loading...', 'ultimate-watermark'),
                    'error' => __('Error loading data', 'ultimate-watermark'),
                ],
            ]);
        }

        // Localize settings script with specific nonce
        if (wp_script_is('ultimate-watermark-settings', 'enqueued')) {
            wp_localize_script('ultimate-watermark-settings', 'ultimateWatermarkSettings', [
                'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('ultimate_watermark_settings'),
                'strings' => [
                    'saving' => __('Saving settings...', 'ultimate-watermark'),
                    'saved' => __('Settings saved successfully!', 'ultimate-watermark'),
                    'error' => __('Error saving settings', 'ultimate-watermark'),
                ],
            ]);
        }
    }

    /**
     * Get asset URL with caching
     *
     * @param string $path
     * @return string
     */
    public function getAssetUrl(string $path): string
    {
        $cache_key = md5($path);
        
        if (!isset($this->asset_cache[$cache_key])) {
            $this->asset_cache[$cache_key] = $this->plugin->getPluginUrl() . 'assets/' . ltrim($path, '/');
        }
        
        return $this->asset_cache[$cache_key];
    }

    /**
     * Get asset path with validation
     *
     * @param string $path
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getAssetPath(string $path): string
    {
        $full_path = $this->plugin->getPluginDir() . 'assets/' . ltrim($path, '/');
        
        // Security check - ensure path is within assets directory
        $real_path = realpath($full_path);
        $assets_path = realpath($this->plugin->getPluginDir() . 'assets');
        
        if ($real_path === false || strpos($real_path, $assets_path) !== 0) {
            throw new \InvalidArgumentException('Invalid asset path: ' . $path);
        }
        
        return $full_path;
    }

    /**
     * Check if asset exists
     *
     * @param string $path
     * @return bool
     */
    public function assetExists(string $path): bool
    {
        try {
            return file_exists($this->getAssetPath($path));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get asset version based on file modification time
     *
     * @param string $path
     * @return string
     */
    public function getAssetVersion(string $path): string
    {
        try {
            $asset_path = $this->getAssetPath($path);
            if (file_exists($asset_path)) {
                return (string) filemtime($asset_path);
            }
        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Failed to get asset version for ' . $path . ': ' . $e->getMessage());
        }
        
        return $this->plugin->getVersion();
    }
}
