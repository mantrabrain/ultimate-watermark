<?php

namespace MantraBrain\UltimateWatermark\Core;

/**
 * Plugin Activator Class
 * 
 * Handles plugin activation tasks
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class Activator
{
    /**
     * Activate plugin
     */
    public static function activate(): void
    {
        // Create database tables if needed
        self::createTables();
        
        // Set default options
        self::setDefaultOptions();
        
        // Create upload directories
        self::createDirectories();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create watermark logs table
        $table_name = $wpdb->prefix . 'ultimate_watermark_logs';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) NOT NULL,
            action varchar(20) NOT NULL,
            status varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default options
     */
    private static function setDefaultOptions(): void
    {
        $default_options = [
            'enable_watermarking' => '0',
            'watermark_image' => '',
            'watermark_position' => 'bottom-right',
            'watermark_transparency' => 50,
            'watermark_image_sizes' => ['medium', 'large'],
            'disable_right_click' => '0',
            'disable_drag_drop' => '0',
            'backup_image' => '1', // Enable backup by default
            'backup_quality' => 90, // Default backup quality
        ];

        add_option('ultimate_watermark_options', $default_options);
        
        // Update existing options to include new backup settings
        self::updateExistingOptions();
    }

    /**
     * Update existing options to include new backup settings
     */
    private static function updateExistingOptions(): void
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
     * Create necessary directories
     */
    private static function createDirectories(): void
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/ultimate-watermark-backup/';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
            
            // Create .htaccess file
            $htaccess_content = "deny from all\n";
            file_put_contents($backup_dir . '.htaccess', $htaccess_content);
            
            // Create index.html file
            file_put_contents($backup_dir . 'index.html', '');
        }
    }
}
