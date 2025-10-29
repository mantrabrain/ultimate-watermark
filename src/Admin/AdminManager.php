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
            wp_send_json_success(['message' => 'Backup deleted successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete backup.']);
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
            wp_send_json_error(['message' => 'Failed to restore image from backup.']);
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
        $raw_ids = $_POST['attachment_ids'] ?? '';
        if (is_string($raw_ids)) {
            $raw_ids_trim = trim((string) $raw_ids);
            $raw_ids_trim = function_exists('wp_unslash') ? wp_unslash($raw_ids_trim) : $raw_ids_trim;
            if ($raw_ids_trim !== '' && $raw_ids_trim[0] === '[') {
                $attachment_ids = json_decode($raw_ids_trim, true);
                if (!is_array($attachment_ids)) {
                    // Fallback: extract digits from a bracketed string like ["59","58"]
                    preg_match_all('/\\d+/', $raw_ids_trim, $m);
                    $attachment_ids = isset($m[0]) ? array_map('intval', $m[0]) : [];
                }
            } else {
                $attachment_ids = array_filter(array_map('intval', array_map('trim', explode(',', $raw_ids_trim))));
            }
        } elseif (is_array($raw_ids)) {
            $attachment_ids = array_map('intval', $raw_ids);
        } else {
            $attachment_ids = [];
        }

        // Server-side log to help diagnose if needed
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: Bulk delete raw ids: ' . print_r($raw_ids, true));
            error_log('Ultimate Watermark: Bulk delete parsed ids: ' . print_r($attachment_ids, true));
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
            wp_send_json_error(['message' => 'Failed to restore any images from backup.']);
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
        $raw_ids = $_POST['attachment_ids'] ?? ($_POST['attachment_ids'] ?? '');
        if (is_array($raw_ids)) {
            $attachment_ids = array_map('intval', $raw_ids);
        } else {
            $raw_ids_trim = trim((string) $raw_ids);
            $raw_ids_trim = function_exists('wp_unslash') ? wp_unslash($raw_ids_trim) : $raw_ids_trim;
            if ($raw_ids_trim !== '' && $raw_ids_trim[0] === '[') {
                $attachment_ids = json_decode($raw_ids_trim, true);
                if (!is_array($attachment_ids)) {
                    preg_match_all('/\\d+/', $raw_ids_trim, $m);
                    $attachment_ids = isset($m[0]) ? array_map('intval', $m[0]) : [];
                }
            } else {
                $attachment_ids = array_filter(array_map('intval', array_map('trim', explode(',', $raw_ids_trim))));
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: Bulk delete raw ids: ' . print_r($raw_ids, true));
            error_log('Ultimate Watermark: Bulk delete parsed ids: ' . print_r($attachment_ids, true));
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
            wp_send_json_error(['message' => 'Failed to delete any backups.']);
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
                wp_send_json_error(['message' => 'Security check failed']);
                return;
            }

            // Get form data directly (it's already an array)
            $form_data = $_POST['form_data'];
            
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
            
            // Use direct database approach to bypass any WordPress hooks
            // Force delete and clear all caches
            delete_option('ultimate_watermark_options');
            wp_cache_delete('ultimate_watermark_options', 'options');
            wp_cache_flush();
            
            // Direct database approach to bypass any WordPress hooks
            global $wpdb;
            $serialized_settings = maybe_serialize($settings);
            
            // First, delete the existing option completely
            $wpdb->delete($wpdb->options, ['option_name' => 'ultimate_watermark_options']);
            
            // Then insert the new one
            $db_result = $wpdb->insert(
                $wpdb->options,
                [
                    'option_name' => 'ultimate_watermark_options',
                    'option_value' => $serialized_settings,
                    'autoload' => 'yes'
                ],
                ['%s', '%s', '%s']
            );
            
            if ($db_result === false) {
                wp_send_json_error(['message' => 'Failed to save settings to database']);
                return;
            }
            
            // Clear caches to ensure fresh data
            wp_cache_flush();
            wp_cache_delete('ultimate_watermark_options', 'options');
            
            wp_send_json_success(['message' => 'Settings saved successfully']);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'An error occurred while saving settings']);
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
        $enabled = $_POST['enabled'] ?? '0';

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

        // Get timeframe
        $timeframe = $_POST['timeframe'] ?? '30';
        $days = (int) $timeframe;

        // Get analytics data
        $analytics_page = new \MantraBrain\UltimateWatermark\Admin\Pages\AnalyticsPage();
        
        $data = [
            'watermark_usage_over_time' => $this->getUsageDataForTimeframe($days),
            'image_protection_trends' => $this->getProtectionData(),
            'template_performance' => $this->getTemplatesData(),
            'image_size_distribution' => $this->getSizesData()
        ];

        wp_send_json_success($data);
    }

    /**
     * Get usage data for specific timeframe
     */
    private function getUsageDataForTimeframe(int $days): array
    {
        $data = [];
        $labels = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
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
    private function getProtectionData(): array
    {
        $total = $this->getTotalImages();
        $watermarked = $this->getWatermarkedImages();
        $unprotected = $total - $watermarked;
        
        return [
            'protected' => $watermarked,
            'unprotected' => $unprotected
        ];
    }

    /**
     * Get templates data
     */
    private function getTemplatesData(): array
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
            $usage_count = get_post_meta($watermark->ID, 'watermark_usage_count', true) ?: 0;
            $labels[] = $watermark->post_title ?: 'Untitled';
            $data[] = (int) $usage_count;
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get sizes data
     */
    private function getSizesData(): array
    {
        $sizes = ['thumbnail', 'medium', 'large', 'full'];
        $labels = [];
        $data = [];
        
        foreach ($sizes as $size) {
            $count = $this->getWatermarkedImagesBySize($size);
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
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'applied_watermarks'
            AND pm.meta_value != ''
            AND p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND DATE(p.post_date) = %s
        ", $date));
        
        return (int) $count;
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

}
