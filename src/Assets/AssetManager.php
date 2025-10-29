<?php

namespace MantraBrain\UltimateWatermark\Assets;


/**
 * Asset Manager Class
 * 
 * Manages all CSS, JS, and other assets for the plugin
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class AssetManager
{

    /**
     * Plugin instance
     *
     * @var \MantraBrain\UltimateWatermark\Core\Plugin
     */
    private $plugin;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        
        // Use direct admin_enqueue_scripts hook instead of custom action
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        $plugin = \MantraBrain\UltimateWatermark\Core\Plugin::getInstance();
        
        // Enqueue frontend CSS
        wp_enqueue_style(
            'ultimate-watermark-frontend',
            $plugin->getPluginUrl() . 'assets/css/frontend.css',
            [],
            $plugin->getVersion()
        );

        // Enqueue frontend JS
        wp_enqueue_script(
            'ultimate-watermark-frontend',
            $plugin->getPluginUrl() . 'assets/js/frontend.js',
            ['jquery'],
            $plugin->getVersion(),
            true
        );

        // Localize script
        wp_localize_script('ultimate-watermark-frontend', 'ultimateWatermark', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ultimate_watermark_nonce'),
            'settings' => get_option('ultimate_watermark_options', []),
            'isLoggedIn' => is_user_logged_in(),
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(): void
    {
        $screen = get_current_screen();
        $plugin = \MantraBrain\UltimateWatermark\Core\Plugin::getInstance();
        
        // Only load on our admin pages
        if (!$screen || strpos($screen->id, 'ultimate-watermark') === false) {
            return;
        }

                // Enqueue common admin CSS
                wp_enqueue_style(
                    'ultimate-watermark-admin',
                    $plugin->getPluginUrl() . 'assets/css/admin.css',
                    [],
                    $plugin->getVersion()
                );

                // Enqueue layout CSS
                wp_enqueue_style(
                    'ultimate-watermark-layout',
                    $plugin->getPluginUrl() . 'assets/css/layout.css',
                    ['ultimate-watermark-admin'],
                    $plugin->getVersion()
                );

        // Enqueue common admin JS
        wp_enqueue_script(
            'ultimate-watermark-admin',
            $plugin->getPluginUrl() . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker', 'media-upload', 'media-views', 'media-models'],
            $plugin->getVersion(),
            true
        );

        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');

        //echo $screen->id ;exit;
        // Page-specific assets
        if ($screen->id === 'toplevel_page_ultimate-watermark') {
           
            // Dashboard page
            wp_enqueue_style(
                'ultimate-watermark-dashboard',
                $plugin->getPluginUrl() . 'assets/css/dashboard.css',
                ['ultimate-watermark-admin'],
                $plugin->getVersion()
            );

            wp_enqueue_script(
                'ultimate-watermark-dashboard',
                $plugin->getPluginUrl() . 'assets/js/dashboard.js',
                ['jquery'],
                $plugin->getVersion(),
                true
            );
                } elseif ($screen->id === 'ultimate-watermark_page_ultimate-watermark-watermarks') {
                    // Watermark page
                    
                    wp_enqueue_style(
                        'ultimate-watermark-watermarks',
                        $plugin->getPluginUrl() . 'assets/css/watermarks.css',
                        ['ultimate-watermark-admin', 'ultimate-watermark-layout'],
                        $plugin->getVersion()
                    );

                    wp_enqueue_script(
                        'ultimate-watermark-watermarks',
                        $plugin->getPluginUrl() . 'assets/js/watermarks.js',
                        ['jquery', 'wp-color-picker'],
                        $plugin->getVersion(),
                        true
                    );

                    // Localize script for AJAX
                    wp_localize_script(
                        'ultimate-watermark-watermarks',
                        'ultimate_watermark_ajax',
                        [
                            'ajax_url' => admin_url('admin-ajax.php'),
                            'nonce' => wp_create_nonce('ultimate_watermark_ajax')
                        ]
                    );
                } elseif ($screen->id === 'ultimate-watermark_page_ultimate-watermark-add-watermark') {
                   
                    // Add Watermark page
                    wp_enqueue_style(
                        'ultimate-watermark-add-watermark',
                        $plugin->getPluginUrl() . 'assets/css/add-watermark.css',
                        ['ultimate-watermark-admin', 'ultimate-watermark-layout'],
                        $plugin->getVersion()
                    );
                    
                    // Enqueue media library first
                    wp_enqueue_media();
                    
                    // Enqueue add-watermark.js with media library dependencies
                    wp_enqueue_script(
                        'ultimate-watermark-add-watermark',
                        $plugin->getPluginUrl() . 'assets/js/add-watermark.js',
                        ['jquery', 'wp-color-picker', 'media-upload', 'media-views', 'media-models'],
                        $plugin->getVersion(),
                        true
                    );
                    
                    // Localize script for AJAX
                    wp_localize_script('ultimate-watermark-add-watermark', 'ultimate_watermark_ajax', [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('ultimate_watermark_ajax'),
                    ]);
                } elseif ($screen->id === 'ultimate-watermark_page_ultimate-watermark-analytics') {
                    // Analytics page
                    wp_enqueue_style(
                        'ultimate-watermark-analytics',
                        $plugin->getPluginUrl() . 'assets/css/analytics.css',
                        ['ultimate-watermark-admin', 'ultimate-watermark-layout'],
                        $plugin->getVersion()
                    );

                    wp_enqueue_script(
                        'ultimate-watermark-analytics',
                        $plugin->getPluginUrl() . 'assets/js/analytics.js',
                        ['jquery'],
                        $plugin->getVersion(),
                        true
                    );
                    
                    // Localize script for AJAX
                    wp_localize_script('ultimate-watermark-analytics', 'ultimateWatermarkAnalytics', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('ultimate_watermark_analytics'),
                    ]);
                } elseif ($screen->id === 'ultimate-watermark_page_ultimate-watermark-settings') {
                    // Settings page
                    wp_enqueue_style(
                        'ultimate-watermark-settings',
                        $plugin->getPluginUrl() . 'assets/css/settings.css',
                        ['ultimate-watermark-admin', 'ultimate-watermark-layout'],
                        $plugin->getVersion()
                    );

                    wp_enqueue_script(
                        'ultimate-watermark-settings',
                        $plugin->getPluginUrl() . 'assets/js/settings.js',
                        ['jquery', 'ultimate-watermark-notification-system'],
                        $plugin->getVersion(),
                        true
                    );
                    
                    // Localize script for AJAX
                    wp_localize_script('ultimate-watermark-settings', 'ultimateWatermarkSettings', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('ultimate_watermark_settings'),
                    ]);
                }

        // Localize script
        wp_localize_script('ultimate-watermark-admin', 'ultimateWatermarkAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ultimate_watermark_admin_nonce'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'ultimate-watermark'),
                'processing' => __('Processing...', 'ultimate-watermark'),
                'success' => __('Success!', 'ultimate-watermark'),
                'error' => __('Error occurred!', 'ultimate-watermark'),
            ],
        ]);
    }

    /**
     * Get asset URL
     *
     * @param string $path
     * @return string
     */
    public function getAssetUrl(string $path): string
    {
        $plugin = \MantraBrain\UltimateWatermark\Core\Plugin::getInstance();
        return $plugin->getPluginUrl() . 'assets/' . ltrim($path, '/');
    }

    /**
     * Get asset path
     *
     * @param string $path
     * @return string
     */
    public function getAssetPath(string $path): string
    {
        $plugin = \MantraBrain\UltimateWatermark\Core\Plugin::getInstance();
        return $plugin->getPluginDir() . 'assets/' . ltrim($path, '/');
    }
}
