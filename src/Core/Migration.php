<?php

namespace MantraBrain\UltimateWatermark\Core;

/**
 * Migration Class
 * 
 * Handles migration from old Ultimate Watermark (v1.x) to new version (v2.x)
 * Converts options-based settings to CPT-based watermark system
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class Migration
{
    /**
     * Migration version option key
     */
    const MIGRATION_VERSION_KEY = 'ultimate_watermark_migration_version';
    
    /**
     * Current migration version
     */
    const CURRENT_MIGRATION_VERSION = '2.0.0';
    
    /**
     * Old plugin option keys to migrate
     */
    private static $old_option_keys = [
        'ultimate_watermark_automatic_watermarking',
        'ultimate_watermark_manual_watermarking',
        'ultimate_watermark_watermark_on',
        'ultimate_watermark_watermark_on_custom_post_type',
        'ultimate_watermark_watermark_on_image_size',
        'ultimate_watermark_watermark_alignment',
        'ultimate_watermark_offset_width',
        'ultimate_watermark_offset_height',
        'ultimate_watermark_watermark_offset_unit',
        'ultimate_watermark_watermark_image',
        'ultimate_watermark_watermark_size_type',
        'ultimate_watermark_absolute_width',
        'ultimate_watermark_absolute_height',
        'ultimate_watermark_scaled_image_width',
        'ultimate_watermark_image_transparent',
        'ultimate_watermark_image_quality',
        'ultimate_watermark_image_format',
        'ultimate_watermark_backup_image',
        'ultimate_watermark_backup_image_quality',
        'ultimate_watermark_frontend_watermarking',
        'ultimate_watermark_disable_rightclick',
        'ultimate_watermark_disable_drag_and_drop',
        'ultimate_watermark_enable_protection_for_logged_in_users',
    ];

    /**
     * Run migration if needed
     */
    public static function run(): void
    {
        $current_migration = get_option(self::MIGRATION_VERSION_KEY, '0.0.0');
        
        // Check if migration is needed
        if (version_compare($current_migration, self::CURRENT_MIGRATION_VERSION, '>=')) {
            return; // Already migrated
        }
        
        // Check if old plugin data exists
        if (!self::hasOldPluginData()) {
            // No old data to migrate, mark as migrated
            update_option(self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION);
            return;
        }
        
        // Run migration
        self::migrateFromV1ToV2();
        
        // Update migration version
        update_option(self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION);
        
        // Log migration completion
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: Migration from v1.x to v2.x completed successfully');
        }
    }
    
    /**
     * Check if old plugin data exists
     */
    private static function hasOldPluginData(): bool
    {
        // Check for old plugin version option
        $old_version = get_option('ultimate_watermark_plugin_version', false);
        
        if ($old_version && version_compare($old_version, '2.0.0', '<')) {
            return true;
        }
        
        // Check if any old options exist
        foreach (self::$old_option_keys as $key) {
            if (get_option($key, null) !== null) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Migrate from v1.x to v2.x
     */
    private static function migrateFromV1ToV2(): void
    {
        // Get all old settings
        $old_settings = self::getOldSettings();
        
        if (empty($old_settings)) {
            return;
        }
        
        // Check if watermark already exists (avoid duplicate migration)
        $existing_watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_migrated_from_v1',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        if (!empty($existing_watermarks)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Migration skipped - watermark already migrated');
            }
            return;
        }
        
        // Create new watermark post from old settings
        $watermark_id = self::createWatermarkFromOldSettings($old_settings);
        
        if ($watermark_id) {
            // Migrate global settings
            self::migrateGlobalSettings($old_settings);
            
            // Mark as migrated
            update_post_meta($watermark_id, '_migrated_from_v1', '1');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Created watermark #' . $watermark_id . ' from v1.x settings');
            }
        }
    }
    
    /**
     * Get all old settings
     */
    private static function getOldSettings(): array
    {
        $settings = [];
        
        foreach (self::$old_option_keys as $key) {
            $value = get_option($key, null);
            if ($value !== null) {
                $settings[$key] = $value;
            }
        }
        
        return $settings;
    }
    
    /**
     * Create watermark post from old settings
     */
    private static function createWatermarkFromOldSettings(array $old_settings): int
    {
        // Create watermark post
        $post_data = [
            'post_title' => __('Migrated Watermark (v1.x)', 'ultimate-watermark'),
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'post_content' => __('This watermark was automatically migrated from Ultimate Watermark v1.x settings.', 'ultimate-watermark'),
        ];
        
        $watermark_id = wp_insert_post($post_data);
        
        if (is_wp_error($watermark_id) || !$watermark_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Failed to create watermark post during migration');
            }
            return 0;
        }
        
        // Determine watermark type (image only in v1.x)
        $watermark_type = 'image';
        update_post_meta($watermark_id, 'watermark_type', $watermark_type);
        
        // Migrate watermark image
        $watermark_image_id = absint($old_settings['ultimate_watermark_watermark_image'] ?? 0);
        if ($watermark_image_id) {
            update_post_meta($watermark_id, 'watermark_image_id', $watermark_image_id);
        }
        
        // Migrate position (convert old format to new)
        $old_alignment = $old_settings['ultimate_watermark_watermark_alignment'] ?? 'bottom_right';
        $new_position = self::convertAlignment($old_alignment);
        update_post_meta($watermark_id, 'watermark_position', $new_position);
        
        // Migrate offsets
        $offset_x = absint($old_settings['ultimate_watermark_offset_width'] ?? 0);
        $offset_y = absint($old_settings['ultimate_watermark_offset_height'] ?? 0);
        $offset_unit = $old_settings['ultimate_watermark_watermark_offset_unit'] ?? 'pixels';
        
        update_post_meta($watermark_id, 'watermark_offset_x', $offset_x);
        update_post_meta($watermark_id, 'watermark_offset_y', $offset_y);
        update_post_meta($watermark_id, 'watermark_offset_unit', $offset_unit === 'pixels' ? 'px' : '%');
        
        // Migrate size settings
        $size_type = $old_settings['ultimate_watermark_watermark_size_type'] ?? 'original';
        $scale_mode = 'original';
        
        if ($size_type === 'custom') {
            $scale_mode = 'custom';
            $width = absint($old_settings['ultimate_watermark_absolute_width'] ?? 0);
            $height = absint($old_settings['ultimate_watermark_absolute_height'] ?? 0);
            update_post_meta($watermark_id, 'watermark_width', $width);
            update_post_meta($watermark_id, 'watermark_height', $height);
        } elseif ($size_type === 'scaled') {
            $scale_mode = 'scale';
            $scale_percentage = absint($old_settings['ultimate_watermark_scaled_image_width'] ?? 80);
            update_post_meta($watermark_id, 'watermark_scale', $scale_percentage);
        }
        
        update_post_meta($watermark_id, 'watermark_scale_mode', $scale_mode);
        
        // Migrate opacity (convert from transparency to opacity)
        $transparency = absint($old_settings['ultimate_watermark_image_transparent'] ?? 50);
        $opacity = 100 - $transparency; // Convert transparency to opacity
        update_post_meta($watermark_id, 'watermark_opacity', $opacity);
        
        // Migrate quality
        $quality = absint($old_settings['ultimate_watermark_image_quality'] ?? 90);
        update_post_meta($watermark_id, 'watermark_quality', $quality);
        
        // Migrate image format
        $format = $old_settings['ultimate_watermark_image_format'] ?? 'baseline';
        update_post_meta($watermark_id, 'image_format', $format);
        
        // Migrate automatic watermarking setting
        $automatic = ($old_settings['ultimate_watermark_automatic_watermarking'] ?? 'no') === 'yes';
        update_post_meta($watermark_id, 'automatic_watermarking', $automatic ? '1' : '0');
        
        // Set as active
        update_post_meta($watermark_id, 'active', '1');
        
        // Migrate watermark_on (where to apply)
        $watermark_on = $old_settings['ultimate_watermark_watermark_on'] ?? 'everywhere';
        update_post_meta($watermark_id, 'watermark_on', $watermark_on);
        
        // Migrate post types
        $post_types = $old_settings['ultimate_watermark_watermark_on_custom_post_type'] ?? [];
        if (!empty($post_types) && is_array($post_types)) {
            update_post_meta($watermark_id, 'watermark_post_types', $post_types);
        }
        
        // Migrate image sizes
        $image_sizes = $old_settings['ultimate_watermark_watermark_on_image_size'] ?? [];
        if (!empty($image_sizes) && is_array($image_sizes)) {
            update_post_meta($watermark_id, 'watermark_sizes', $image_sizes);
        }
        
        // Create basic rules for backward compatibility
        if ($watermark_on !== 'everywhere' || !empty($post_types) || !empty($image_sizes)) {
            $rules = self::createRulesFromOldSettings($watermark_on, $post_types, $image_sizes);
            update_post_meta($watermark_id, 'watermark_rules', wp_json_encode($rules));
        }
        
        return $watermark_id;
    }
    
    /**
     * Convert old alignment format to new position format
     */
    private static function convertAlignment(string $old_alignment): string
    {
        $alignment_map = [
            'top_left' => 'top-left',
            'top_center' => 'top-center',
            'top_right' => 'top-right',
            'middle_left' => 'center-left',
            'middle_center' => 'center',
            'middle_right' => 'center-right',
            'bottom_left' => 'bottom-left',
            'bottom_center' => 'bottom-center',
            'bottom_right' => 'bottom-right',
        ];
        
        return $alignment_map[$old_alignment] ?? 'bottom-right';
    }
    
    /**
     * Create rules from old settings
     */
    private static function createRulesFromOldSettings(string $watermark_on, array $post_types, array $image_sizes): array
    {
        $rules = [];
        
        // Create a single rule group with all conditions
        $rule = [
            'id' => 'migrated_rule_1',
            'logic' => 'and',
            'conditions' => []
        ];
        
        // Add post type condition if not everywhere
        if ($watermark_on !== 'everywhere' && !empty($post_types)) {
            $rule['conditions'][] = [
                'type' => 'post_type',
                'operator' => 'is',
                'value' => $post_types
            ];
        }
        
        // Add image size condition if specified
        if (!empty($image_sizes)) {
            $rule['conditions'][] = [
                'type' => 'image_size',
                'operator' => 'is',
                'value' => $image_sizes
            ];
        }
        
        if (!empty($rule['conditions'])) {
            $rules[] = $rule;
        }
        
        return $rules;
    }
    
    /**
     * Migrate global settings
     */
    private static function migrateGlobalSettings(array $old_settings): void
    {
        // Migrate backup settings
        $backup_enabled = ($old_settings['ultimate_watermark_backup_image'] ?? 'yes') === 'yes';
        $backup_quality = absint($old_settings['ultimate_watermark_backup_image_quality'] ?? 90);
        
        update_option('ultimate_watermark_backup_enabled', $backup_enabled ? 'yes' : 'no');
        update_option('ultimate_watermark_backup_quality', $backup_quality);
        
        // Migrate manual watermarking setting
        $manual_enabled = ($old_settings['ultimate_watermark_manual_watermarking'] ?? 'yes') === 'yes';
        update_option('ultimate_watermark_manual_watermarking_enabled', $manual_enabled ? 'yes' : 'no');
        
        // Note: Frontend watermarking, right-click protection, etc. are Pro features in v2.x
        // These settings are preserved but not actively used in free version
        $frontend_enabled = ($old_settings['ultimate_watermark_frontend_watermarking'] ?? 'no') === 'yes';
        if ($frontend_enabled) {
            update_option('ultimate_watermark_v1_had_frontend_watermarking', 'yes');
        }
        
        // Migrate backup files from old structure to new structure
        self::migrateBackupFiles();
    }
    
    /**
     * Migrate backup files from old structure to new structure
     * Old: /uploads/ulwm-backup/{original_filepath}
     * New: /uploads/ulwm-backup/YYYY/MM/{attachment_id}_filename_original.ext
     */
    private static function migrateBackupFiles(): void
    {
        $upload_dir = wp_upload_dir();
        $old_backup_base = $upload_dir['basedir'] . '/ulwm-backup';
        
        // Check if old backup directory exists
        if (!is_dir($old_backup_base)) {
            return;
        }
        
        $migrated_count = 0;
        $failed_count = 0;
        
        // Get all image attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        foreach ($attachments as $attachment_id) {
            try {
                // Get original file path relative to uploads
                $relative_path = get_post_meta($attachment_id, '_wp_attached_file', true);
                if (!$relative_path) {
                    continue;
                }
                
                // Old backup path (flat structure mirroring uploads)
                $old_backup_path = $old_backup_base . '/' . $relative_path;
                
                // Check if old backup exists
                if (!file_exists($old_backup_path)) {
                    continue;
                }
                
                // Get file info
                $file_info = pathinfo($relative_path);
                $attachment_date = get_post_time('Y/m', false, $attachment_id);
                
                // New backup directory structure
                $new_backup_dir = $old_backup_base . '/' . $attachment_date;
                
                // Create new directory if needed
                if (!file_exists($new_backup_dir)) {
                    if (!wp_mkdir_p($new_backup_dir)) {
                        $failed_count++;
                        continue;
                    }
                }
                
                // New backup filename format
                $new_backup_filename = $attachment_id . '_' . $file_info['filename'] . '_original.' . $file_info['extension'];
                $new_backup_path = $new_backup_dir . '/' . $new_backup_filename;
                
                // Move backup file to new location
                if (rename($old_backup_path, $new_backup_path)) {
                    // Update attachment meta with new backup path
                    update_post_meta($attachment_id, '_ulwm_backup_path', $new_backup_path);
                    update_post_meta($attachment_id, '_ulwm_backup_migrated', '1');
                    $migrated_count++;
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Ultimate Watermark: Migrated backup for attachment #{$attachment_id}");
                    }
                } else {
                    // If rename fails, try copy
                    if (copy($old_backup_path, $new_backup_path)) {
                        update_post_meta($attachment_id, '_ulwm_backup_path', $new_backup_path);
                        update_post_meta($attachment_id, '_ulwm_backup_migrated', '1');
                        @unlink($old_backup_path); // Try to delete old file
                        $migrated_count++;
                    } else {
                        $failed_count++;
                    }
                }
                
                // Clean up old directory structure if empty
                $old_dir = dirname($old_backup_path);
                if (is_dir($old_dir) && $old_dir !== $old_backup_base) {
                    @rmdir($old_dir); // Remove if empty
                }
                
            } catch (\Exception $e) {
                $failed_count++;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Ultimate Watermark: Backup migration failed for attachment #{$attachment_id}: " . $e->getMessage());
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Ultimate Watermark: Backup migration complete. Migrated: {$migrated_count}, Failed: {$failed_count}");
        }
        
        // Store migration stats
        update_option('ultimate_watermark_backup_migration_stats', [
            'migrated' => $migrated_count,
            'failed' => $failed_count,
            'date' => current_time('mysql')
        ]);
    }
    
    /**
     * Rollback migration (for testing/debugging)
     */
    public static function rollback(): void
    {
        // Delete migrated watermarks
        $migrated_watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_migrated_from_v1',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        foreach ($migrated_watermarks as $watermark) {
            wp_delete_post($watermark->ID, true);
        }
        
        // Reset migration version
        delete_option(self::MIGRATION_VERSION_KEY);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: Migration rolled back');
        }
    }
    
    /**
     * Get migration status
     */
    public static function getStatus(): array
    {
        $current_migration = get_option(self::MIGRATION_VERSION_KEY, '0.0.0');
        $has_old_data = self::hasOldPluginData();
        $needs_migration = version_compare($current_migration, self::CURRENT_MIGRATION_VERSION, '<') && $has_old_data;
        
        $migrated_watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_migrated_from_v1',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        // Get backup migration stats
        $backup_stats = get_option('ultimate_watermark_backup_migration_stats', [
            'migrated' => 0,
            'failed' => 0,
            'date' => null
        ]);
        
        return [
            'current_version' => $current_migration,
            'target_version' => self::CURRENT_MIGRATION_VERSION,
            'needs_migration' => $needs_migration,
            'has_old_data' => $has_old_data,
            'migrated_watermarks_count' => count($migrated_watermarks),
            'backup_migration' => $backup_stats,
            'is_complete' => !$needs_migration
        ];
    }
}
