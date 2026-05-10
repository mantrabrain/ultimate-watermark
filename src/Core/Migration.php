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
        // Use static variable to prevent multiple runs in same request.
        static $migration_checked = false;
        if ($migration_checked) {
            return;
        }
        $migration_checked = true;

        $current_migration = get_option(self::MIGRATION_VERSION_KEY, '0.0.0');

        // Already migrated — exit silently. There's nothing to log on every
        // request just because the migration version is up to date.
        if (version_compare($current_migration, self::CURRENT_MIGRATION_VERSION, '>=')) {
            return;
        }

        // From here on we're actually doing work, so logging the steps is
        // useful for debugging a real one-time migration.
        $has_old_data = self::hasOldPluginData();

        if (!$has_old_data) {
            update_option(self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION);
            return;
        }

        // Set the version IMMEDIATELY so a second concurrent request can't
        // duplicate the migration work.
        update_option(self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark Migration: ' . $current_migration . ' → ' . self::CURRENT_MIGRATION_VERSION . ' starting…');
        }

        self::migrateFromV1ToV2();
        set_transient('ultimate_watermark_migration_complete', true, 60);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark Migration: completed.');
        }
    }

    /**
     * Display admin notice after migration
     */
    public static function showMigrationNotice(): void
    {
        if (!get_transient('ultimate_watermark_migration_complete')) {
            return;
        }

        $status = self::getStatus();
        $backup_stats = $status['backup_migration'];

        ?>
        <div class="notice notice-success is-dismissible">
            <h3><?php _e('Ultimate Watermark Migration Complete', 'ultimate-watermark'); ?></h3>
            <p>
                <?php
                printf(
                        __('Successfully migrated from v1.x to v2.x! %d watermark(s) migrated.', 'ultimate-watermark'),
                        $status['migrated_watermarks_count']
                );
                ?>
            </p>
            <?php if ($backup_stats['migrated'] > 0): ?>
                <p>
                    <?php
                    printf(
                            __('Backup files migrated: %d successful, %d failed.', 'ultimate-watermark'),
                            $backup_stats['migrated'],
                            $backup_stats['failed']
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php

        // Delete transient after showing
        delete_transient('ultimate_watermark_migration_complete');
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

        // Check for old backup directory structure (flat structure)
        $upload_dir = wp_upload_dir();
        $old_backup_dir = $upload_dir['basedir'] . '/ulwm-backup';

        if (is_dir($old_backup_dir)) {
            // Check if it has the old flat structure (files directly in subdirectories matching upload structure)
            // vs new structure (YYYY/MM/ subdirectories)
            $has_old_structure = false;

            // Look for any files that aren't in YYYY/MM format directories
            if ($handle = @opendir($old_backup_dir)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != ".." && $entry != "index.html" && $entry != ".htaccess") {
                        // Check if this is NOT a year directory (4 digits)
                        if (!preg_match('/^\d{4}$/', $entry)) {
                            // This is old structure (has files/folders that aren't year directories)
                            $has_old_structure = true;
                            break;
                        }
                    }
                }
                closedir($handle);
            }

            if ($has_old_structure) {
                return true;
            }
        }

        // Check for old install date option
        if (get_option('ultimate_watermark_install_date', false)) {
            return true;
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // No old settings to migrate — silent return is fine.
            }
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark Migration: Found old settings, checking for existing watermarks...');
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
                error_log('Ultimate Watermark Migration: Found existing migrated watermark (ID: ' . $existing_watermarks[0]->ID . '), skipping creation.');
            }
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark Migration: No existing watermark found, creating new one...');
        }

        // Create new watermark post from old settings
        $watermark_id = self::createWatermarkFromOldSettings($old_settings);

        if ($watermark_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark Migration: Created watermark ID: ' . $watermark_id);
            }

            // Mark as migrated IMMEDIATELY
            update_post_meta($watermark_id, '_migrated_from_v1', '1');
            
            // Migrate global settings
            self::migrateGlobalSettings($old_settings);

            // Migrate watermarked image metadata
            self::migrateWatermarkedImageMetadata($watermark_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark Migration: Watermark migration completed.');
            }

        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark Migration: ERROR - Failed to create watermark post.');
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

                $display_value = is_array($value) ? json_encode($value) : $value;

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

        if (is_wp_error($watermark_id)) {
            return 0;
        }

        if (!$watermark_id) {

            return 0;
        }


        // Determine watermark type (image only in v1.x)
        $watermark_type = 'image';
        update_post_meta($watermark_id, 'watermark_type', $watermark_type);


        // Migrate watermark image
        $watermark_image_id = absint($old_settings['ultimate_watermark_watermark_image'] ?? 0);
        if ($watermark_image_id) {
            update_post_meta($watermark_id, 'watermark_image_id', $watermark_image_id);

        } else {

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
        update_post_meta($watermark_id, 'watermark_offset_unit', $offset_unit === 'pixels' ? 'pixels' : 'percentage');


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

        update_post_meta($watermark_id, 'watermark_size_type', $scale_mode);


        // Migrate opacity
        // Note: Old plugin's 'image_transparent' field is actually opacity (0=transparent, 100=opaque)
        $opacity = absint($old_settings['ultimate_watermark_image_transparent'] ?? 50);
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

        // Migrate manual watermarking setting (per-watermark in v2.x)
        $manual = ($old_settings['ultimate_watermark_manual_watermarking'] ?? 'yes') === 'yes';
        update_post_meta($watermark_id, 'manual_watermarking', $manual ? '1' : '0');

        // Migrate frontend watermarking setting (per-watermark in v2.x)
        $frontend = ($old_settings['ultimate_watermark_frontend_watermarking'] ?? 'no') === 'yes';
        update_post_meta($watermark_id, 'frontend_watermarking', $frontend ? '1' : '0');

        // Set as active
        update_post_meta($watermark_id, 'active', '1');


        // Migrate watermark_on (where to apply)
        $watermark_on = $old_settings['ultimate_watermark_watermark_on'] ?? 'everywhere';
        update_post_meta($watermark_id, 'watermark_on', $watermark_on);


        // Migrate post types
        $post_types = $old_settings['ultimate_watermark_watermark_on_custom_post_type'] ?? [];


        // Old plugin stores multicheckbox as array with 'yes' values: ['post' => 'yes', 'page' => 'yes']
        // We need to extract just the keys where value is 'yes'
        if (is_array($post_types) && !empty($post_types)) {
            $filtered_post_types = [];
            foreach ($post_types as $key => $value) {
                if ($value === 'yes' || $value === '1' || $value === 1) {
                    $filtered_post_types[] = $key;
                }
            }

            if (!empty($filtered_post_types)) {
                update_post_meta($watermark_id, 'watermark_post_types', $filtered_post_types);

            }

            $post_types = $filtered_post_types; // Update for rules creation
        }

        // Migrate image sizes
        $image_sizes = $old_settings['ultimate_watermark_watermark_on_image_size'] ?? [];

        // Old plugin stores multicheckbox as array with 'yes' values: ['medium' => 'yes', 'large' => 'yes']
        // We need to extract just the keys where value is 'yes'
        if (is_array($image_sizes) && !empty($image_sizes)) {
            $filtered_image_sizes = [];
            foreach ($image_sizes as $key => $value) {
                if ($value === 'yes' || $value === '1' || $value === 1) {
                    $filtered_image_sizes[] = $key;
                }
            }

            if (!empty($filtered_image_sizes)) {
                update_post_meta($watermark_id, 'watermark_sizes', $filtered_image_sizes);
            }

            $image_sizes = $filtered_image_sizes; // Update for rules creation
        }

        // Create basic rules for backward compatibility
        if ($watermark_on !== 'everywhere' || !empty($post_types) || !empty($image_sizes)) {
            $rules = self::createRulesFromOldSettings($watermark_on, $post_types, $image_sizes);
            // Save as array, not JSON string - WordPress will serialize it automatically
            update_post_meta($watermark_id, 'watermark_rules', $rules);


        } else {

        }

        // Verify metadata was saved correctly
        self::verifyMigratedMetadata($watermark_id);

        return $watermark_id;
    }

    /**
     * Verify migrated metadata was saved correctly
     */
    private static function verifyMigratedMetadata(int $watermark_id): void
    {

        $critical_keys = [
                'watermark_type',
                'watermark_image_id',
                'watermark_position',
                'watermark_opacity',
                'watermark_size_type',
                'watermark_quality',
                'watermark_offset_unit',
                'automatic_watermarking',
                'active'
        ];

        $missing = [];
        $values = [];

        foreach ($critical_keys as $key) {
            $value = get_post_meta($watermark_id, $key, true);
            if ($value === '' || $value === false) {
                $missing[] = $key;
            } else {
                $values[$key] = $value;
            }
        }


        // Check rules
        $rules = get_post_meta($watermark_id, 'watermark_rules', true);
        if ($rules && is_string($rules)) {
            $decoded = json_decode($rules, true);

        }
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
     * 
     * Creates ONE rule with multiple conditions using OR logic
     * UI expects: associative array with rule_id as key, containing 'name', 'logic_operator', 'conditions', 'is_default'
     */
    private static function createRulesFromOldSettings(string $watermark_on, array $post_types, array $image_sizes): array
    {
        $rules = [];
        $conditions = [];
        $rule_name_parts = [];
        
        // Build conditions array
        if ($watermark_on !== 'everywhere' && !empty($post_types)) {
            foreach ($post_types as $post_type) {
                $conditions[] = [
                    'type' => 'post_type',
                    'operator' => 'is',
                    'value' => $post_type
                ];
                $rule_name_parts[] = ucfirst($post_type);
            }
        }
        
        if (!empty($image_sizes)) {
            foreach ($image_sizes as $image_size) {
                $conditions[] = [
                    'type' => 'image_size',
                    'operator' => 'is',
                    'value' => $image_size
                ];
            }
        }
        
        // Create single rule with all conditions
        if (!empty($conditions)) {
            $rule_id = 'migrated_rule_' . time() . '_' . substr(md5(uniqid()), 0, 6);
            
            // Build descriptive name
            $name = 'Migrated Rule';
            if (!empty($rule_name_parts)) {
                $name .= ': ' . implode(', ', $rule_name_parts);
            }
            if (!empty($image_sizes)) {
                $name .= ' (' . implode(', ', $image_sizes) . ')';
            }
            
            $rules[$rule_id] = [
                'name' => $name,
                'logic_operator' => 'or',
                'conditions' => $conditions,
                'is_default' => false
            ];
        }
        
        return $rules;
    }

    /**
     * Migrate global settings to ultimate_watermark_options array
     */
    private static function migrateGlobalSettings(array $old_settings): void
    {
        // Get existing options or create new array
        $options = get_option('ultimate_watermark_options', []);

        // Migrate backup settings
        $backup_enabled = ($old_settings['ultimate_watermark_backup_image'] ?? 'yes') === 'yes';
        $backup_quality = absint($old_settings['ultimate_watermark_backup_image_quality'] ?? 90);

        $options['backup_image'] = $backup_enabled ? '1' : '0';
        $options['backup_quality'] = $backup_quality;
        $options['backup_strategy'] = 'full_size'; // Default strategy for migrated sites

        // Migrate protection settings
        $disable_rightclick = ($old_settings['ultimate_watermark_disable_rightclick'] ?? 'no') === 'yes';
        $disable_drag_drop = ($old_settings['ultimate_watermark_disable_drag_and_drop'] ?? 'no') === 'yes';
        $enable_protection_logged_in = ($old_settings['ultimate_watermark_enable_protection_for_logged_in_users'] ?? 'no') === 'yes';

        $options['disable_rightclick'] = $disable_rightclick ? '1' : '0';
        $options['disable_drag_drop'] = $disable_drag_drop ? '1' : '0';
        $options['enable_protection_logged_in'] = $enable_protection_logged_in ? '1' : '0';

        // Save all settings to ultimate_watermark_options
        update_option('ultimate_watermark_options', $options);


        // Migrate manual watermarking setting (separate option)
        $manual_enabled = ($old_settings['ultimate_watermark_manual_watermarking'] ?? 'yes') === 'yes';
        update_option('ultimate_watermark_manual_watermarking_enabled', $manual_enabled ? 'yes' : 'no');

        // Note: Frontend watermarking is a Pro feature in v2.x
        $frontend_enabled = ($old_settings['ultimate_watermark_frontend_watermarking'] ?? 'no') === 'yes';
        if ($frontend_enabled) {
            update_option('ultimate_watermark_v1_had_frontend_watermarking', 'yes');
        }

        // Migrate backup files from old structure to new structure
        self::migrateBackupFiles();
    }

    /**
     * Migrate watermarked image metadata from old system
     * Old plugin used 'ulwm-is-watermarked' = 1 to mark watermarked images
     * New plugin uses '_ulwm_watermark_history' to track which watermarks were applied
     */
    private static function migrateWatermarkedImageMetadata(int $watermark_id): void
    {

        // Get all attachments that were watermarked in the old system
        $watermarked_attachments = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                        [
                                'key' => 'ulwm-is-watermarked',
                                'value' => '1',
                                'compare' => '='
                        ]
                ]
        ]);

        if (empty($watermarked_attachments)) {

            return;
        }


        $migrated_count = 0;

        foreach ($watermarked_attachments as $attachment_id) {
            // Add watermark history entry
            $history = get_post_meta($attachment_id, '_ulwm_watermark_history', true);
            if (!is_array($history)) {
                $history = [];
            }

            // Add entry for the migrated watermark
            $history[] = [
                    'watermark_id' => $watermark_id,
                    'watermark_name' => 'Migrated Watermark (v1.x)',
                    'applied_date' => current_time('mysql'),
                    'applied_sizes' => ['full'], // Old system applied to full by default
                    'migrated' => true
            ];

            update_post_meta($attachment_id, '_ulwm_watermark_history', $history);

            // Update watermark count
            $count = absint(get_post_meta($attachment_id, 'watermark_count', true));
            update_post_meta($attachment_id, 'watermark_count', $count + 1);

            // Add application date
            $dates = get_post_meta($attachment_id, 'watermark_application_dates', true);
            if (!is_array($dates)) {
                $dates = [];
            }
            $dates[] = current_time('mysql');
            update_post_meta($attachment_id, 'watermark_application_dates', $dates);

            $migrated_count++;
        }
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

            }
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
