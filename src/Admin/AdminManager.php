<?php

namespace MantraBrain\UltimateWatermark\Admin;

use MantraBrain\UltimateWatermark\Admin\Pages\DashboardPage;
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
        add_action('admin_init', [$this, 'updateBackupSettings']);
        add_action('wp_ajax_ultimate_watermark_delete_backup', [$this, 'handleDeleteBackup']);
        add_action('wp_ajax_ultimate_watermark_restore_backup', [$this, 'handleRestoreBackup']);
        add_action('wp_ajax_ultimate_watermark_bulk_restore_backup', [$this, 'handleBulkRestoreBackup']);
        add_action('wp_ajax_ultimate_watermark_bulk_delete_backup', [$this, 'handleBulkDeleteBackup']);
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
            'backup' => new BackupPage(),
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
                case 'backup_image':
                case 'disable_rightclick':
                case 'disable_drag_drop':
                case 'enable_protection_logged_in':
                    $sanitized[$key] = isset($value) ? '1' : '0';
                    break;
                case 'watermark_image':
                    $sanitized[$key] = absint($value);
                    break;
                case 'watermark_size_type':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                case 'watermark_transparency':
                case 'watermark_quality':
                case 'backup_quality':
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

    public function renderBackupPage(): void
    {
        $this->pages['backup']->render();
    }

    /**
     * Update backup settings for existing installations
     */
    public function updateBackupSettings(): void
    {
        $existing_options = get_option('ultimate_watermark_options', []);
        
        // Add backup settings if they don't exist
        if (!isset($existing_options['backup_image'])) {
            $existing_options['backup_image'] = '1';
        }
        if (!isset($existing_options['backup_quality'])) {
            $existing_options['backup_quality'] = 90;
        }
        
        // Update the options
        update_option('ultimate_watermark_options', $existing_options);
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
        $deleted = \MantraBrain\UltimateWatermark\Utils\BackupManager::deleteBackup($attachment_id);
        
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
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID.']);
            return;
        }

        // Restore the backup using BackupManager
        $restored = \MantraBrain\UltimateWatermark\Utils\BackupManager::restoreFromBackup($attachment_id);
        
        if ($restored) {
            wp_send_json_success(['message' => 'Image restored successfully from backup.']);
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

        $attachment_ids = json_decode($_POST['attachment_ids'] ?? '[]', true);
        
        if (empty($attachment_ids) || !is_array($attachment_ids)) {
            wp_send_json_error(['message' => 'Invalid attachment IDs.']);
            return;
        }

        $restored_count = 0;
        $errors = [];

        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = intval($attachment_id);
            if ($attachment_id > 0) {
                $restored = \MantraBrain\UltimateWatermark\Utils\BackupManager::restoreFromBackup($attachment_id);
                if ($restored) {
                    $restored_count++;
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

        $attachment_ids = json_decode($_POST['attachment_ids'] ?? '[]', true);
        
        if (empty($attachment_ids) || !is_array($attachment_ids)) {
            wp_send_json_error(['message' => 'Invalid attachment IDs.']);
            return;
        }

        $deleted_count = 0;
        $errors = [];

        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = intval($attachment_id);
            if ($attachment_id > 0) {
                $deleted = \MantraBrain\UltimateWatermark\Utils\BackupManager::deleteBackup($attachment_id);
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
        // Only load on our plugin pages
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
                ['jquery'],
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
}
