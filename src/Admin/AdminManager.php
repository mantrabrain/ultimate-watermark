<?php

namespace MantraBrain\UltimateWatermark\Admin;

use MantraBrain\UltimateWatermark\Admin\Pages\DashboardPage;
use MantraBrain\UltimateWatermark\Admin\Pages\WatermarkPage;
use MantraBrain\UltimateWatermark\Admin\Pages\AddWatermarkPage;
use MantraBrain\UltimateWatermark\Admin\Pages\SettingsPage;
use MantraBrain\UltimateWatermark\Admin\MediaLibraryIntegration;
use MantraBrain\UltimateWatermark\Admin\MediaEditIntegration;

/**
 * Admin Manager Class
 * 
 * Manages all admin functionality including menus, pages, and admin-specific features
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class AdminManager
{

    /**
     * Admin pages
     *
     * @var array
     */
    private $pages = [];

    /**
     * Media library integration
     *
     * @var MediaLibraryIntegration
     */
    private $mediaLibraryIntegration;

    /**
     * Media edit integration
     *
     * @var MediaEditIntegration
     */
    private $mediaEditIntegration;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initHooks();
        $this->initPages();
        $this->initMediaLibraryIntegration();
        $this->initMediaEditIntegration();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'init']);
        // Remove asset enqueuing from here - let AssetManager handle it
    }

    /**
     * Initialize admin pages
     */
    private function initPages(): void
    {
        $this->pages = [
            'dashboard' => new DashboardPage(),
            'watermark' => new WatermarkPage(),
            'add-watermark' => new AddWatermarkPage(),
            'settings' => new SettingsPage(),
        ];
    }

    /**
     * Initialize media library integration
     */
    private function initMediaLibraryIntegration(): void
    {
        $this->mediaLibraryIntegration = new MediaLibraryIntegration();
        $this->mediaLibraryIntegration->init();
    }

    /**
     * Initialize media edit integration
     */
    private function initMediaEditIntegration(): void
    {
        $this->mediaEditIntegration = new MediaEditIntegration();
        $this->mediaEditIntegration->init();
    }

    /**
     * Admin initialization
     */
    public function init(): void
    {
        // Register settings
        $this->registerSettings();
        
        // Fire admin init action
        do_action('ultimate_watermark_admin_init');
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu(): void
    {
        // Main menu page
        add_menu_page(
            __('Ultimate Watermark', 'ultimate-watermark'),
            __('Ultimate Watermark', 'ultimate-watermark'),
            'manage_options',
            'ultimate-watermark',
            [$this, 'renderDashboardPage'],
            'dashicons-shield-alt',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'ultimate-watermark',
            __('Dashboard', 'ultimate-watermark'),
            __('Dashboard', 'ultimate-watermark'),
            'manage_options',
            'ultimate-watermark',
            [$this, 'renderDashboardPage']
        );

        // Watermark submenu
                add_submenu_page(
                    'ultimate-watermark',
                    __('Watermarks', 'ultimate-watermark'),
                    __('Watermarks', 'ultimate-watermark'),
                    'manage_options',
                    'ultimate-watermark-watermarks',
                    [$this, 'renderWatermarkPage']
                );

                add_submenu_page(
                    'ultimate-watermark',
                    __('Add Watermark', 'ultimate-watermark'),
                    __('Add Watermark', 'ultimate-watermark'),
                    'manage_options',
                    'ultimate-watermark-add-watermark',
                    [$this, 'renderAddWatermarkPage']
                );

                add_submenu_page(
                    'ultimate-watermark',
                    __('Settings', 'ultimate-watermark'),
                    __('Settings', 'ultimate-watermark'),
                    'manage_options',
                    'ultimate-watermark-settings',
                    [$this, 'renderSettingsPage']
                );
    }


    /**
     * Register plugin settings
     */
    private function registerSettings(): void
    {
        // Register general settings
        register_setting('ultimate_watermark_settings', 'ultimate_watermark_options', [
            'sanitize_callback' => [$this, 'sanitizeSettings'],
        ]);
    }

    /**
     * Sanitize settings
     *
     * @param array $input
     * @return array
     */
    public function sanitizeSettings(array $input): array
    {
        $sanitized = [];
        
        // Sanitize each setting based on its type
        foreach ($input as $key => $value) {
            switch ($key) {
                case 'watermark_image':
                    $sanitized[$key] = absint($value);
                    break;
                case 'watermark_size_type':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                case 'watermark_transparency':
                case 'watermark_quality':
                    $sanitized[$key] = absint($value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        return $sanitized;
    }

    /**
     * Render dashboard page
     */
    public function renderDashboardPage(): void
    {
        $this->pages['dashboard']->render();
    }

    /**
     * Render watermark page
     */
    public function renderWatermarkPage(): void
    {
        $this->pages['watermark']->render();
    }

    public function renderAddWatermarkPage(): void
    {
        $this->pages['add-watermark']->render();
    }

    public function renderSettingsPage(): void
    {
        $this->pages['settings']->render();
    }

    /**
     * Get admin pages
     *
     * @return array
     */
    public function getPages(): array
    {
        return $this->pages;
    }
}
