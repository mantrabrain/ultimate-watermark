<?php

namespace MantraBrain\UltimateWatermark\Admin;

use MantraBrain\UltimateWatermark\Admin\Pages\DashboardPage;
use MantraBrain\UltimateWatermark\Admin\Pages\AnalyticsPage;
use MantraBrain\UltimateWatermark\Admin\Pages\WatermarkPage;
use MantraBrain\UltimateWatermark\Admin\Pages\AddWatermarkPage;
use MantraBrain\UltimateWatermark\Admin\Pages\SettingsPage;
use MantraBrain\UltimateWatermark\Admin\Pages\BackupPage;
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
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_post_ultimate_watermark_save_settings', [$this, 'handleSettingsSave']);
        // Removed updateBackupSettings hook - now handled by proper settings system
        add_action('wp_ajax_ultimate_watermark_delete_backup', [$this, 'handleDeleteBackup']);
        add_action('wp_ajax_ultimate_watermark_restore_backup', [$this, 'handleRestoreBackup']);
        add_action('wp_ajax_ultimate_watermark_bulk_restore_backup', [$this, 'handleBulkRestoreBackup']);
        add_action('wp_ajax_ultimate_watermark_bulk_delete_backup', [$this, 'handleBulkDeleteBackup']);
        add_action('wp_ajax_ultimate_watermark_save_settings', [$this, 'handleSaveSettings']);
        add_action('wp_ajax_ultimate_watermark_update_toggle_state', [$this, 'handleUpdateToggleState']);
        add_action('wp_ajax_ultimate_watermark_get_analytics_data', [$this, 'handleGetAnalyticsData']);
        add_action('wp_ajax_ultimate_watermark_get_paginated_backups', [$this, 'handleGetPaginatedBackups']);
        // Remove asset enqueuing from here - let AssetManager handle it
    }

    /**
     * Initialize admin pages
     */
    private function initPages(): void
    {
        $this->pages = [
            'dashboard' => new DashboardPage(),
            'analytics' => new AnalyticsPage(),
            'watermark' => new WatermarkPage(),
            'add-watermark' => new AddWatermarkPage(),
            'settings' => new SettingsPage(),
            'backup' => new BackupPage(),
        ];

        // Initialize pages that need initialization
        $this->pages['add-watermark']->init();
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

        // Analytics submenu
        add_submenu_page(
            'ultimate-watermark',
            __('Analytics', 'ultimate-watermark'),
            __('Analytics', 'ultimate-watermark'),
            'manage_options',
            'ultimate-watermark-analytics',
            [$this, 'renderAnalyticsPage']
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

                add_submenu_page(
                    'ultimate-watermark',
                    __('Backup Management', 'ultimate-watermark'),
                    __('Backups', 'ultimate-watermark'),
                    'manage_options',
                    'ultimate-watermark-backups',
                    [$this, 'renderBackupPage']
                );

                // Get Pro menu (only show if Pro plugin is not active)
                if (!defined('ULTIMATE_WATERMARK_PRO_VERSION')) {
                    add_submenu_page(
                        'ultimate-watermark',
                        __('Get Pro', 'ultimate-watermark'),
                        '<span style="color:#f18500;">' . __('Get Pro', 'ultimate-watermark') . '</span>',
                        'manage_options',
                        'ultimate-watermark-get-pro',
                        [$this, 'renderGetProPage']
                    );
                }
    }


    /**
     * Register plugin settings
     */
    private function registerSettings(): void
    {
        // Settings are now handled by our dynamic configuration system
        // No need for register_setting since we use direct database approach
    }


    /**
     * Render dashboard page
     */
    public function renderDashboardPage(): void
    {
        $this->pages['dashboard']->render();
    }

    /**
     * Render analytics page
     */
    public function renderAnalyticsPage(): void
    {
        $this->pages['analytics']->render();
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

    public function renderBackupPage(): void
    {
        $this->pages['backup']->render();
    }

    /**
     * Render Get Pro page
     */
    public function renderGetProPage(): void
    {
        ProFeaturesPage::render();
    }


    /**
     * Handle delete backup AJAX request
     */
    public function handleDeleteBackup(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_backup_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to delete backups.']);
            return;
        }

        $attachment_id = intval($_POST['attachment_id'] ?? 0);
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID.']);
            return;
        }

        // Delete the backup using BackupManager
        $deleted = \MantraBrain\UltimateWatermark\Utils\BackupManager::deleteAttachmentBackups($attachment_id);
        
        if ($deleted) {
            wp_send_json_success(['message' => __('Backup deleted successfully.', 'ultimate-watermark')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete backup.', 'ultimate-watermark')]);
        }
    }

    /**
     * Handle restore backup AJAX request
     */
    public function handleRestoreBackup(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_backup_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to restore backups.']);
            return;
        }

        $attachment_id = intval($_POST['attachment_id'] ?? 0);
        $delete_after_restore = isset($_POST['delete_after_restore']) && $_POST['delete_after_restore'] === '1';
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID.']);
            return;
        }

        // Get the attachment file path
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path) {
            wp_send_json_error(['message' => 'Could not find attachment file.']);
            return;
        }
        
        // Restore the backup using BackupManager
        $restored = \MantraBrain\UltimateWatermark\Utils\BackupManager::restoreFromBackup($file_path, $attachment_id);
        
        if ($restored) {
            $message = $delete_after_restore 
                ? 'Image restored successfully and backup deleted.' 
                : 'Image restored successfully from backup.';
            
            // Delete backup files if requested
            if ($delete_after_restore) {
                \MantraBrain\UltimateWatermark\Utils\BackupManager::deleteBackupFiles($attachment_id);
            }
            
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Failed to restore image from backup.', 'ultimate-watermark')]);
        }
    }

    /**
     * Handle bulk restore backup AJAX request
     */
    public function handleBulkRestoreBackup(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_backup_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to restore backups.']);
            return;
        }

        // Be flexible: accept JSON array or comma-separated list. Account for added slashes.
        // Sanitize input before processing
        $raw_ids = isset($_POST['attachment_ids']) ? $_POST['attachment_ids'] : '';
        if (is_string($raw_ids)) {
            // Sanitize string input
            $raw_ids = sanitize_text_field($raw_ids);
            $raw_ids_trim = trim($raw_ids);
            $raw_ids_trim = function_exists('wp_unslash') ? wp_unslash($raw_ids_trim) : $raw_ids_trim;
            if ($raw_ids_trim !== '' && $raw_ids_trim[0] === '[') {
                // Validate JSON before decoding to prevent errors
                $decoded = json_decode($raw_ids_trim, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $attachment_ids = array_map('absint', $decoded);
                } else {
                    // Fallback: extract digits from a bracketed string like ["59","58"]
                    preg_match_all('/\\d+/', $raw_ids_trim, $m);
                    $attachment_ids = isset($m[0]) ? array_map('absint', $m[0]) : [];
                }
            } else {
                $attachment_ids = array_filter(array_map('absint', array_map('trim', explode(',', $raw_ids_trim))));
            }
        } elseif (is_array($raw_ids)) {
            $attachment_ids = array_map('absint', $raw_ids);
        } else {
            $attachment_ids = [];
        }


        // Normalize IDs
        if (is_array($attachment_ids)) {
            $attachment_ids = array_values(array_unique(array_filter(array_map('intval', $attachment_ids), function($v){ return $v > 0; })));
        }

        if (empty($attachment_ids) || !is_array($attachment_ids)) {
            wp_send_json_error(['message' => 'Invalid attachment IDs.']);
            return;
        }

        $restored_count = 0;
        $errors = [];

        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = intval($attachment_id);
            if ($attachment_id > 0) {
                // Get the attachment file path
                $file_path = get_attached_file($attachment_id);
                
                if ($file_path) {
                    $restored = \MantraBrain\UltimateWatermark\Utils\BackupManager::restoreFromBackup($file_path, $attachment_id);
                    if ($restored) {
                        $restored_count++;
                    } else {
                        $errors[] = $attachment_id;
                    }
                } else {
                    $errors[] = $attachment_id;
                }
            }
        }

        if ($restored_count > 0) {
            $message = $restored_count . ' image(s) restored successfully from backup.';
            if (!empty($errors)) {
                $message .= ' ' . count($errors) . ' image(s) could not be restored.';
            }
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Failed to restore any images from backup.', 'ultimate-watermark')]);
        }
    }

    /**
     * Handle bulk delete backup AJAX request
     */
    public function handleBulkDeleteBackup(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_backup_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to delete backups.']);
            return;
        }

        // Accept either array (attachment_ids[]) or JSON/comma-separated
        // Sanitize input before processing
        $raw_ids = isset($_POST['attachment_ids']) ? $_POST['attachment_ids'] : '';
        if (is_array($raw_ids)) {
            $attachment_ids = array_map('absint', $raw_ids);
        } else {
            // Sanitize string input
            $raw_ids = sanitize_text_field($raw_ids);
            $raw_ids_trim = trim($raw_ids);
            $raw_ids_trim = function_exists('wp_unslash') ? wp_unslash($raw_ids_trim) : $raw_ids_trim;
            if ($raw_ids_trim !== '' && $raw_ids_trim[0] === '[') {
                // Validate JSON before decoding to prevent errors
                $decoded = json_decode($raw_ids_trim, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $attachment_ids = array_map('absint', $decoded);
                } else {
                    preg_match_all('/\\d+/', $raw_ids_trim, $m);
                    $attachment_ids = isset($m[0]) ? array_map('absint', $m[0]) : [];
                }
            } else {
                $attachment_ids = array_filter(array_map('absint', array_map('trim', explode(',', $raw_ids_trim))));
            }
        }

        
        if (is_array($attachment_ids)) {
            $attachment_ids = array_values(array_unique(array_filter(array_map('intval', $attachment_ids), function($v){ return $v > 0; })));
        }

        if (empty($attachment_ids) || !is_array($attachment_ids)) {
            wp_send_json_error(['message' => 'Invalid attachment IDs.']);
            return;
        }

        $deleted_count = 0;
        $errors = [];

        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = intval($attachment_id);
            if ($attachment_id > 0) {
                $deleted = \MantraBrain\UltimateWatermark\Utils\BackupManager::deleteAttachmentBackups($attachment_id);
                if ($deleted) {
                    $deleted_count++;
                } else {
                    $errors[] = $attachment_id;
                }
            }
        }

        if ($deleted_count > 0) {
            $message = $deleted_count . ' backup(s) deleted successfully.';
            if (!empty($errors)) {
                $message .= ' ' . count($errors) . ' backup(s) could not be deleted.';
            }
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete any backups.', 'ultimate-watermark')]);
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Enqueue notification system globally for all admin pages
        wp_enqueue_script(
            'ultimate-watermark-notification-system',
            ULTIMATE_WATERMARK_URL . 'assets/js/notification-system.js',
            [],
            '1.0.0',
            true
        );

        // Only load other assets on our plugin pages
        if (strpos($hook, 'ultimate-watermark') === false) {
            return;
        }

        // Enqueue backup page styles and scripts
        if (strpos($hook, 'ultimate-watermark-backups') !== false) {
            wp_enqueue_style(
                'ultimate-watermark-backup-page',
                ULTIMATE_WATERMARK_URL . 'assets/css/backup-page.css',
                [],
                '1.0.0'
            );
            
            wp_enqueue_script(
                'ultimate-watermark-backup-page',
                ULTIMATE_WATERMARK_URL . 'assets/js/backup-page.js',
                ['jquery', 'ultimate-watermark-notification-system'],
                '1.0.0',
                true
            );
            
            // Localize script for AJAX
            wp_localize_script('ultimate-watermark-backup-page', 'ultimateWatermarkBackup', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('ultimate_watermark_backup_nonce'),
                'mediaLibraryUrl' => admin_url('upload.php')
            ]);
        }
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

    /**
     * Handle AJAX settings save
     */
    public function handleSaveSettings(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'ultimate_watermark_settings')) {
                wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
                return;
            }

            // Get and sanitize form data
            if (!isset($_POST['form_data']) || !is_array($_POST['form_data'])) {
                wp_send_json_error(['message' => __('Invalid form data.', 'ultimate-watermark')]);
                return;
            }
            
            // Sanitize form data array
            $form_data = array_map(function($value) {
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return sanitize_text_field($value);
            }, $_POST['form_data']);
            
            // Get settings page instance to access configuration
            $settings_page = \MantraBrain\UltimateWatermark\Admin\Pages\SettingsPage::getInstance();
            $all_field_keys = $settings_page->getAllFieldKeys();
            
            // Temporarily disable any hooks that might interfere with our option
            remove_all_filters('pre_option_ultimate_watermark_options');
            remove_all_filters('option_ultimate_watermark_options');
            remove_all_filters('update_option_ultimate_watermark_options');
            
            // Process each field using the configuration
            $settings = [];
            foreach ($all_field_keys as $field_key) {
                $field_config = $settings_page->getFieldConfig($field_key);
                
                if (!$field_config) {
                    continue;
                }
                
                // Get value from form data or use default
                $raw_value = $form_data[$field_key] ?? $field_config['default'];
                
                // Sanitize and validate the value
                $sanitized_value = $settings_page->sanitizeFieldValue($field_key, $raw_value);
                
                $settings[$field_key] = $sanitized_value;
            }
            
            // Clear all caches before updating
            wp_cache_delete('ultimate_watermark_options', 'options');
            wp_cache_flush();
            
            // Use WordPress update_option for better compatibility and hook support
            // This properly handles serialization, caching, and database operations
            $update_result = update_option('ultimate_watermark_options', $settings, 'yes');
            
            if ($update_result === false) {
                // Check if option exists and values are the same (update_option returns false if no change)
                $existing = get_option('ultimate_watermark_options', []);
                if ($existing !== $settings) {
                    wp_send_json_error(['message' => __('Failed to save settings to database.', 'ultimate-watermark')]);
                    return;
                }
            }
            
            // Clear caches to ensure fresh data
            wp_cache_delete('ultimate_watermark_options', 'options');
            wp_cache_flush();
            
            wp_send_json_success(['message' => __('Settings saved successfully.', 'ultimate-watermark')]);
            
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark Settings Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => __('An error occurred while saving settings.', 'ultimate-watermark')]);
        }
    }

    /**
     * Handle update toggle state
     */
    public function handleUpdateToggleState(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_toggle')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_die('Insufficient permissions');
        }

        // Get enabled state
        // Sanitize enabled value (must be '0' or '1')
        $enabled = sanitize_text_field($_POST['enabled'] ?? '0');
        if (!in_array($enabled, ['0', '1'], true)) {
            $enabled = '0';
        }

        // Store in WordPress option
        update_option('ultimate_watermark_auto_apply_toggle', $enabled);

        // Send success response
        wp_send_json_success([
            'enabled' => $enabled === '1'
        ]);
    }

    /**
     * Handle get analytics data
     */
    public function handleGetAnalyticsData(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_analytics')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Sanitize and validate timeframe
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? '30');
        $valid_timeframes = ['today', 'yesterday', '7', '30', '90', '365'];
        if (!in_array($timeframe, $valid_timeframes, true) && !is_numeric($timeframe)) {
            $timeframe = '30';
        }
        $offsetStart = ($timeframe === 'yesterday') ? 1 : 0;
        $days = ($timeframe === 'today' || $timeframe === 'yesterday') ? 1 : (int) $timeframe;

        // Try cache (allow bypass on refresh)
        $cache_key = 'uw_analytics_' . $days . '_' . $offsetStart;
        $force = isset($_POST['force']) && $_POST['force'] === '1';
        $data = !$force ? get_transient($cache_key) : false;
        if ($data === false) {
            // Build fresh data from DB
            $data = [
                'watermark_usage_over_time' => $this->getUsageDataForTimeframe($days, $offsetStart),
                'image_protection_trends' => $this->getProtectionData($days, $offsetStart),
                'template_performance' => $this->getTemplatesData($days, $offsetStart),
                'image_size_distribution' => $this->getSizesData($days, $offsetStart)
            ];
            // Refresh cache
            set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
        }

        wp_send_json_success($data);
    }

    /**
     * Handle AJAX request for paginated backups
     */
    public function handleGetPaginatedBackups(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_backup_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
            return;
        }

        // Sanitize pagination parameters
        $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, min(100, absint($_POST['per_page']))) : 10;

        // Get paginated backups
        $result = \MantraBrain\UltimateWatermark\Utils\BackupManager::getPaginatedBackups($page, $per_page);

        wp_send_json_success($result);
    }

    /**
     * Get usage data for specific timeframe
     */
    private function getUsageDataForTimeframe(int $days, int $offsetStart = 0): array
    {
        $data = [];
        $labels = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $offset = $i + $offsetStart;
            $date = date('Y-m-d', strtotime("-{$offset} days", current_time('timestamp')));
            $labels[] = date('M j', strtotime($date));
            
            // Count watermarks applied on this date
            $count = $this->getWatermarkCountForDate($date);
            $data[] = $count;
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get protection data
     */
    private function getProtectionData(int $days = 30, int $offsetStart = 0): array
    {
        $total = $this->getTotalImagesForTimeframe($days, $offsetStart);
        $watermarked = $this->getWatermarkedImagesForTimeframe($days, $offsetStart);
        $unprotected = $total - $watermarked;
        
        return [
            'protected' => $watermarked,
            'unprotected' => $unprotected
        ];
    }

    /**
     * Get templates data
     */
    private function getTemplatesData(int $days = 30, int $offsetStart = 0): array
    {
        $watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'numberposts' => 5,
            'meta_key' => 'watermark_usage_count',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        ]);
        
        $labels = [];
        $data = [];
        
        foreach ($watermarks as $watermark) {
            // Get usage count for this timeframe
            $usage_count = $this->getWatermarkUsageForTimeframe($watermark->ID, $days, $offsetStart);
            $labels[] = $watermark->post_title ?: 'Untitled';
            $data[] = $usage_count;
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get sizes data
     */
    private function getSizesData(int $days = 30, int $offsetStart = 0): array
    {
        $sizes = ['thumbnail', 'medium', 'large', 'full'];
        $labels = [];
        $data = [];
        
        foreach ($sizes as $size) {
            $count = $this->getWatermarkedImagesBySizeForTimeframe($size, $days, $offsetStart);
            $labels[] = ucfirst($size);
            $data[] = $count;
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get total images count
     */
    private function getTotalImages(): int
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
        ]);
        return count($attachments);
    }

    /**
     * Get watermarked images count
     */
    private function getWatermarkedImages(): int
    {
        $watermarked = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'meta_query' => [
                [
                    'key' => 'applied_watermarks',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        return count($watermarked);
    }

    /**
     * Get watermark count for specific date
     */
    private function getWatermarkCountForDate(string $date): int
    {
        global $wpdb;
        // Fetch candidate attachments then verify in PHP to avoid timezone/serialization edge cases
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, pm.meta_value
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'attachment'
               AND p.post_mime_type LIKE 'image/%'
               AND pm.meta_key = 'watermark_application_dates'",
        ));
        if (empty($rows)) { return 0; }
        $seen = [];
        foreach ($rows as $row) {
            $dates = maybe_unserialize($row->meta_value);
            if (is_array($dates) && in_array($date, $dates, true)) {
                $seen[$row->ID] = true;
            }
        }
        return count($seen);
    }

    /**
     * Get watermarked images count by size
     */
    private function getWatermarkedImagesBySize(string $size): int
    {
        global $wpdb;
        
        // Get all images with watermarks_by_size meta
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'watermarks_by_size'
            AND pm.meta_value != ''
            AND p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
        "));
        
        $count = 0;
        foreach ($results as $result) {
            $watermarks_by_size = maybe_unserialize($result->meta_value);
            if (is_array($watermarks_by_size) && isset($watermarks_by_size[$size]) && !empty($watermarks_by_size[$size])) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Get total images count for specific timeframe
     */
    private function getTotalImagesForTimeframe(int $days, int $offsetStart = 0): int
    {
        global $wpdb;
        // Build exact DATE() matches for each day in the window
        $likes = [];
        $params = [];
        for ($i = 0; $i < $days; $i++) {
            $offset = $i + $offsetStart;
            $d = date('Y-m-d', strtotime("-{$offset} days", current_time('timestamp')));
            $likes[] = "DATE(p.post_date) = %s";
            $params[] = $d;
        }
        if (empty($likes)) { return 0; }
        $where_dates = implode(' OR ', $likes);
        $sql = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND ($where_dates)";
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        return (int) $count;
    }

    /**
     * Get watermarked images count for specific timeframe
     */
    private function getWatermarkedImagesForTimeframe(int $days, int $offsetStart = 0): int
    {
        global $wpdb;
        // Build OR conditions for dates within range to match serialized array strings
        $likes = [];
        $params = [];
        for ($i = 0; $i < $days; $i++) {
            $offset = $i + $offsetStart;
            $d = date('Y-m-d', strtotime("-{$offset} days", current_time('timestamp')));
            $likes[] = "pm_dates.meta_value LIKE %s";
            $params[] = '%"' . $wpdb->esc_like($d) . '"%';
        }
        $where_dates = implode(' OR ', $likes);
        $sql = "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_aw ON pm_aw.post_id = p.ID AND pm_aw.meta_key='applied_watermarks'
                INNER JOIN {$wpdb->postmeta} pm_dates ON pm_dates.post_id = p.ID AND pm_dates.meta_key='watermark_application_dates'
                WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND ($where_dates)";
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        return (int) $count;
    }

    /**
     * Get watermark usage count for specific timeframe
     */
    private function getWatermarkUsageForTimeframe(int $watermark_id, int $days, int $offsetStart = 0): int
    {
        global $wpdb;
        // First, get candidate attachment IDs watermarked in the date window
        $likes = [];
        $params = [];
        for ($i = 0; $i < $days; $i++) {
            $offset = $i + $offsetStart;
            $d = date('Y-m-d', strtotime("-{$offset} days", current_time('timestamp')));
            $likes[] = "pm_dates.meta_value LIKE %s";
            $params[] = '%"' . $wpdb->esc_like($d) . '"%';
        }
        $where_dates = implode(' OR ', $likes);
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_dates ON pm_dates.post_id = p.ID AND pm_dates.meta_key='watermark_application_dates'
             WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND ($where_dates)",
            $params
        ));
        if (empty($ids)) { return 0; }
        $count = 0;
        foreach ($ids as $aid) {
            $applied = get_post_meta((int)$aid, 'applied_watermarks', true) ?: [];
            if (is_array($applied) && in_array($watermark_id, $applied, true)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get watermarked images count by size for specific timeframe
     */
    private function getWatermarkedImagesBySizeForTimeframe(string $size, int $days, int $offsetStart = 0): int
    {
        global $wpdb;
        $likes = [];
        $params = [];
        for ($i = 0; $i < $days; $i++) {
            $offset = $i + $offsetStart;
            $d = date('Y-m-d', strtotime("-{$offset} days", current_time('timestamp')));
            $likes[] = "pm_dates.meta_value LIKE %s";
            $params[] = '%"' . $wpdb->esc_like($d) . '"%';
        }
        $where_dates = implode(' OR ', $likes);
        $sql = "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_size ON pm_size.post_id = p.ID AND pm_size.meta_key='watermarks_by_size'
                INNER JOIN {$wpdb->postmeta} pm_dates ON pm_dates.post_id = p.ID AND pm_dates.meta_key='watermark_application_dates'
                WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND ($where_dates)";
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        if (!$count) { return 0; }
        // Filter by size in PHP (serialized array check per-row in SQL is heavy). Estimate by scanning candidate IDs.
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_size ON pm_size.post_id = p.ID AND pm_size.meta_key='watermarks_by_size'
             INNER JOIN {$wpdb->postmeta} pm_dates ON pm_dates.post_id = p.ID AND pm_dates.meta_key='watermark_application_dates'
             WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND ($where_dates)",
            $params
        ));
        $filtered = 0;
        foreach ((array)$ids as $id) {
            $by_size = get_post_meta((int)$id, 'watermarks_by_size', true);
            if (is_array($by_size) && !empty($by_size[$size])) { $filtered++; }
        }
        return $filtered;
    }

}
