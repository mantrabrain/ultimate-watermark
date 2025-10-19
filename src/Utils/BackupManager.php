<?php

namespace MantraBrain\UltimateWatermark\Utils;

/**
 * Backup Manager
 * 
 * Handles all backup operations for watermarked images
 * Integrates with plugin settings for backup configuration
 * 
 * @package UltimateWatermark
 * @since 2.0.0
 */
class BackupManager
{
    const BACKUP_OPTION_KEY = 'ultimate_watermark_options';
    
    /**
     * Get backup settings
     * 
     * @return array
     */
    public static function getBackupSettings(): array
    {
        $settings = get_option(self::BACKUP_OPTION_KEY, []);
        
        return [
            'backup_enabled' => isset($settings['backup_image']) ? (bool) $settings['backup_image'] : true,
            'backup_quality' => isset($settings['backup_quality']) ? (int) $settings['backup_quality'] : 90,
            'backup_strategy' => isset($settings['backup_strategy']) ? $settings['backup_strategy'] : 'full_size',
        ];
    }
    
    /**
     * Check if backup is enabled
     * 
     * @return bool
     */
    public static function isBackupEnabled(): bool
    {
        $settings = self::getBackupSettings();
        return $settings['backup_enabled'];
    }
    
    /**
     * Create backup of image before watermarking
     * 
     * @param string $file_path Original image path
     * @param int $attachment_id WordPress attachment ID
     * @param array $watermarked_sizes Array of image sizes that had watermarks applied
     * @return bool|string|array Backup path(s) on success, false on failure
     */
    public static function createBackup(string $file_path, int $attachment_id = 0, array $watermarked_sizes = []): bool|string|array
    {
        if (!self::isBackupEnabled()) {
            return false;
        }
        
        // Check if backup already exists for this attachment
        if ($attachment_id > 0) {
            $existing_backup = get_post_meta($attachment_id, '_ultimate_watermark_backup_path', true);
            if ($existing_backup && file_exists($existing_backup)) {
                return $existing_backup; // Return existing backup path
            }
        }
        
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $settings = self::getBackupSettings();
        $file_info = pathinfo($file_path);
        
        // Create centralized backup directory structure: uploads/ulwm-backup/YYYY/MM/
        $upload_dir = wp_upload_dir();
        $backup_base_dir = $upload_dir['basedir'] . '/ulwm-backup';
        $year_month = date('Y/m', current_time('timestamp'));
        $backup_dir = $backup_base_dir . '/' . $year_month;
        
        // Create backup directory if it doesn't exist
        if (!file_exists($backup_dir)) {
            if (!wp_mkdir_p($backup_dir)) {
                return false;
            }
        }
        
        // Handle different backup strategies
        if ($settings['backup_strategy'] === 'watermarked_sizes' && !empty($watermarked_sizes)) {
            // Backup full size + watermarked sizes
            return self::createMultiSizeBackup($file_path, $attachment_id, $watermarked_sizes, $backup_dir, $settings);
        } else {
            // Backup full size only (default behavior)
            return self::createSingleBackup($file_path, $attachment_id, $backup_dir, $settings);
        }
    }
    
    /**
     * Create single backup (full size only)
     * 
     * @param string $file_path Original image path
     * @param int $attachment_id WordPress attachment ID
     * @param string $backup_dir Backup directory path
     * @param array $settings Backup settings
     * @return bool|string Backup path on success, false on failure
     */
    private static function createSingleBackup(string $file_path, int $attachment_id, string $backup_dir, array $settings): bool|string
    {
        $file_info = pathinfo($file_path);
        $backup_filename = $attachment_id . '_' . $file_info['filename'] . '_original.' . $file_info['extension'];
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        // Create backup with quality settings
        if (self::copyImageWithQuality($file_path, $backup_path, $settings['backup_quality'])) {
            
            // Store backup info in attachment meta
            if ($attachment_id > 0) {
                update_post_meta($attachment_id, '_ultimate_watermark_backup_path', $backup_path);
                update_post_meta($attachment_id, '_ultimate_watermark_backup_created', current_time('mysql'));
                update_post_meta($attachment_id, '_ultimate_watermark_backup_strategy', 'single');
            }
            
            return $backup_path;
        } else {
            return false;
        }
    }
    
    /**
     * Create multi-size backup (full size + watermarked sizes)
     * 
     * @param string $file_path Original image path
     * @param int $attachment_id WordPress attachment ID
     * @param array $watermarked_sizes Array of watermarked image sizes
     * @param string $backup_dir Backup directory path
     * @param array $settings Backup settings
     * @return bool|array Backup paths on success, false on failure
     */
    private static function createMultiSizeBackup(string $file_path, int $attachment_id, array $watermarked_sizes, string $backup_dir, array $settings): bool|array
    {
        $file_info = pathinfo($file_path);
        $backup_paths = [];
        
        // Always backup full size
        $full_backup_filename = $attachment_id . '_' . $file_info['filename'] . '_original.' . $file_info['extension'];
        $full_backup_path = $backup_dir . '/' . $full_backup_filename;
        
        if (self::copyImageWithQuality($file_path, $full_backup_path, $settings['backup_quality'])) {
            $backup_paths['original'] = $full_backup_path;
        }
        
        // Backup watermarked sizes
        foreach ($watermarked_sizes as $size_name) {
            $size_file = self::getImageSizePath($file_path, $size_name);
            if ($size_file && file_exists($size_file)) {
                $size_backup_filename = $attachment_id . '_' . $file_info['filename'] . '_' . $size_name . '.' . $file_info['extension'];
                $size_backup_path = $backup_dir . '/' . $size_backup_filename;
                
                if (self::copyImageWithQuality($size_file, $size_backup_path, $settings['backup_quality'])) {
                    $backup_paths[$size_name] = $size_backup_path;
                }
            }
        }
        
        if (!empty($backup_paths)) {
            // Store backup info in attachment meta
            if ($attachment_id > 0) {
                update_post_meta($attachment_id, '_ultimate_watermark_backup_paths', $backup_paths);
                update_post_meta($attachment_id, '_ultimate_watermark_backup_created', current_time('mysql'));
                update_post_meta($attachment_id, '_ultimate_watermark_backup_strategy', 'multi');
                update_post_meta($attachment_id, '_ultimate_watermark_backup_sizes', array_keys($backup_paths));
            }
            
            return $backup_paths;
        } else {
            return false;
        }
    }
    
    /**
     * Get image size file path
     * 
     * @param string $file_path Original image path
     * @param string $size_name Image size name
     * @return string|null Image size path if exists, null otherwise
     */
    private static function getImageSizePath(string $file_path, string $size_name): ?string
    {
        $file_info = pathinfo($file_path);
        $size_file = $file_info['dirname'] . '/' . $file_info['filename'] . '-' . $size_name . '.' . $file_info['extension'];
        
        return file_exists($size_file) ? $size_file : null;
    }
    
    /**
     * Get backup path for an image
     * 
     * @param string $file_path Original image path
     * @param int $attachment_id WordPress attachment ID (optional)
     * @return string|null Backup path if exists, null otherwise
     */
    public static function getBackupPath(string $file_path, int $attachment_id = 0): ?string
    {
        // First check attachment meta
        if ($attachment_id > 0) {
            $backup_path = get_post_meta($attachment_id, '_ultimate_watermark_backup_path', true);
            if ($backup_path && file_exists($backup_path)) {
                return $backup_path;
            }
        }
        
        // Fallback to file system check in centralized backup location
        $file_info = pathinfo($file_path);
        $upload_dir = wp_upload_dir();
        $backup_base_dir = $upload_dir['basedir'] . '/ulwm-backup';
        
        // Search in all year/month directories for the backup
        $backup_filename = $attachment_id . '_' . $file_info['filename'] . '_original.' . $file_info['extension'];
        
        // Check current year/month first
        $year_month = date('Y/m', current_time('timestamp'));
        $backup_path = $backup_base_dir . '/' . $year_month . '/' . $backup_filename;
        
        if (file_exists($backup_path)) {
            return $backup_path;
        }
        
        // If not found, search in all year/month directories
        $year_dirs = glob($backup_base_dir . '/*', GLOB_ONLYDIR);
        foreach ($year_dirs as $year_dir) {
            $month_dirs = glob($year_dir . '/*', GLOB_ONLYDIR);
            foreach ($month_dirs as $month_dir) {
                $backup_path = $month_dir . '/' . $backup_filename;
                if (file_exists($backup_path)) {
                    return $backup_path;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Check if an image has been watermarked (has a backup)
     * 
     * @param int $attachment_id WordPress attachment ID
     * @return bool True if image has been watermarked, false otherwise
     */
    public static function hasBeenWatermarked(int $attachment_id): bool
    {
        $backup_path = get_post_meta($attachment_id, '_ultimate_watermark_backup_path', true);
        return $backup_path && file_exists($backup_path);
    }
    
    /**
     * Restore image from backup
     * 
     * @param string $file_path Original image path
     * @param int $attachment_id WordPress attachment ID
     * @return bool Success status
     */
    public static function restoreFromBackup(string $file_path, int $attachment_id): bool
    {
        // Check if this is a multi-size backup
        $backup_paths = get_post_meta($attachment_id, '_ultimate_watermark_backup_paths', true);
        $backup_strategy = get_post_meta($attachment_id, '_ultimate_watermark_backup_strategy', true);
        
        if ($backup_strategy === 'multi' && is_array($backup_paths) && !empty($backup_paths)) {
            // Restore all related backup sizes
            return self::restoreMultiSizeBackup($file_path, $attachment_id, $backup_paths);
        } else {
            // Restore single backup (legacy behavior)
            return self::restoreSingleBackup($file_path, $attachment_id);
        }
    }
    
    /**
     * Restore single backup (legacy behavior)
     * 
     * @param string $file_path Original image path
     * @param int $attachment_id WordPress attachment ID
     * @return bool Success status
     */
    private static function restoreSingleBackup(string $file_path, int $attachment_id): bool
    {
        $backup_path = self::getBackupPath($file_path, $attachment_id);
        
        if (!$backup_path || !file_exists($backup_path)) {
            return false;
        }
        
        try {
            // Restore original from backup
            if (copy($backup_path, $file_path)) {
                // Update attachment metadata
                wp_generate_attachment_metadata($attachment_id, $file_path);
                
                // Clean up backup info from meta
                delete_post_meta($attachment_id, '_ultimate_watermark_backup_path');
                delete_post_meta($attachment_id, '_ultimate_watermark_backup_created');
                
                return true;
            }
        } catch (\Exception $e) {
        }
        
        return false;
    }
    
    /**
     * Restore multi-size backup
     * 
     * @param string $file_path Original image path
     * @param int $attachment_id WordPress attachment ID
     * @param array $backup_paths Array of backup paths
     * @return bool Success status
     */
    private static function restoreMultiSizeBackup(string $file_path, int $attachment_id, array $backup_paths): bool
    {
        $restored_count = 0;
        $file_info = pathinfo($file_path);
        
        try {
            // Restore original (full size) first
            if (isset($backup_paths['original']) && file_exists($backup_paths['original'])) {
                if (copy($backup_paths['original'], $file_path)) {
                    $restored_count++;
                }
            }
            
            // Restore additional sizes
            foreach ($backup_paths as $size_name => $backup_path) {
                if ($size_name !== 'original' && file_exists($backup_path)) {
                    $size_file = self::getImageSizePath($file_path, $size_name);
                    if ($size_file && copy($backup_path, $size_file)) {
                        $restored_count++;
                    }
                }
            }
            
            if ($restored_count > 0) {
                // Update attachment metadata
                wp_generate_attachment_metadata($attachment_id, $file_path);
                
                // Clean up backup info from meta
                delete_post_meta($attachment_id, '_ultimate_watermark_backup_paths');
                delete_post_meta($attachment_id, '_ultimate_watermark_backup_created');
                delete_post_meta($attachment_id, '_ultimate_watermark_backup_strategy');
                delete_post_meta($attachment_id, '_ultimate_watermark_backup_sizes');
                
                return true;
            }
        } catch (\Exception $e) {
        }
        
        return false;
    }
    
    /**
     * Get all backup files for an attachment
     * 
     * @param int $attachment_id WordPress attachment ID
     * @return array Array of backup file information
     */
    public static function getAttachmentBackups(int $attachment_id): array
    {
        $backups = [];
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path) {
            return $backups;
        }
        
        $file_info = pathinfo($file_path);
        $upload_dir = wp_upload_dir();
        $backup_base_dir = $upload_dir['basedir'] . '/ulwm-backup';
        
        // Search in current year/month first, then all directories
        $current_year = date('Y');
        $current_month = date('m');
        $search_dirs = [
            $backup_base_dir . '/' . $current_year . '/' . $current_month,
            $backup_base_dir
        ];
        
        $backup_files = [];
        foreach ($search_dirs as $backup_dir) {
            if (!file_exists($backup_dir)) {
                continue;
            }
            
            // Look for backup files with the new naming pattern: {attachment_id}_{original_filename}_original.{extension}
            $backup_pattern = $backup_dir . '/' . $attachment_id . '_*_original.' . $file_info['extension'];
            $found_files = glob($backup_pattern);
            $backup_files = array_merge($backup_files, $found_files);
            
            // If we found backups, break (don't search other directories)
            if (!empty($found_files)) {
                break;
            }
        }
        
        foreach ($backup_files as $backup_file) {
            $backup_info = pathinfo($backup_file);
            $backup_name = $backup_info['filename'];
            
            // Extract backup type from filename
            $backup_type = 'original';
            if (strpos($backup_name, '_original') !== false) {
                $backup_type = 'original';
            } elseif (strpos($backup_name, '_thumbnail') !== false) {
                $backup_type = 'thumbnail';
            } elseif (strpos($backup_name, '_medium') !== false) {
                $backup_type = 'medium';
            } elseif (strpos($backup_name, '_large') !== false) {
                $backup_type = 'large';
            }
            
            $backups[] = [
                'path' => $backup_file,
                'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $backup_file),
                'type' => 'original',
                'size' => filesize($backup_file),
                'created' => filemtime($backup_file),
                'filename' => $backup_info['basename']
            ];
        }
        
        return $backups;
    }
    
    /**
     * Delete backup files for an attachment
     * 
     * @param int $attachment_id WordPress attachment ID
     * @return bool Success status
     */
    public static function deleteAttachmentBackups(int $attachment_id): bool
    {
        $backups = self::getAttachmentBackups($attachment_id);
        $deleted = 0;
        
        foreach ($backups as $backup) {
            if (unlink($backup['path'])) {
                $deleted++;
            }
        }
        
        // Clean up backup directory if empty (for new centralized structure)
        $upload_dir = wp_upload_dir();
        $backup_base_dir = $upload_dir['basedir'] . '/ulwm-backup';
        
        // Check current year/month directory
        $current_year = date('Y');
        $current_month = date('m');
        $current_backup_dir = $backup_base_dir . '/' . $current_year . '/' . $current_month;
        
        if (file_exists($current_backup_dir) && count(scandir($current_backup_dir)) <= 2) { // Only . and .. entries
            rmdir($current_backup_dir);
            
            // Also remove year directory if empty
            $year_dir = $backup_base_dir . '/' . $current_year;
            if (file_exists($year_dir) && count(scandir($year_dir)) <= 2) {
                rmdir($year_dir);
            }
        }
        
        // Clean up meta
        delete_post_meta($attachment_id, '_ultimate_watermark_backup_path');
        delete_post_meta($attachment_id, '_ultimate_watermark_backup_created');
        
        return $deleted > 0;
    }
    
    /**
     * Copy image with quality settings
     * 
     * @param string $source Source image path
     * @param string $destination Destination image path
     * @param int $quality Image quality (1-100)
     * @return bool Success status
     */
    private static function copyImageWithQuality(string $source, string $destination, int $quality = 90): bool
    {
        $image_info = getimagesize($source);
        if (!$image_info) {
            return false;
        }
        
        $mime_type = $image_info['mime'];
        
        // Create image resource based on type
        switch ($mime_type) {
            case 'image/jpeg':
                $source_image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $source_image = imagecreatefromgif($source);
                break;
            case 'image/webp':
                $source_image = imagecreatefromwebp($source);
                break;
            default:
                // For unsupported formats, just copy the file
                return copy($source, $destination);
        }
        
        if (!$source_image) {
            return false;
        }
        
        // Save with quality settings
        $result = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $result = imagejpeg($source_image, $destination, $quality);
                break;
            case 'image/png':
                // PNG quality is compression level (0-9), convert from 0-100
                $png_quality = round(9 - ($quality / 100) * 9);
                $result = imagepng($source_image, $destination, $png_quality);
                break;
            case 'image/gif':
                $result = imagegif($source_image, $destination);
                break;
            case 'image/webp':
                $result = imagewebp($source_image, $destination, $quality);
                break;
        }
        
        imagedestroy($source_image);
        return $result;
    }
    
    /**
     * Get backup statistics
     * 
     * @return array Backup statistics
     */
    public static function getBackupStats(): array
    {
        global $wpdb;
        
        $stats = [
            'total_backups' => 0,
            'total_size' => 0,
            'backup_enabled_count' => 0,
            'recent_backups' => []
        ];
        
        // Get attachments with backup meta
        $backup_attachments = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm.meta_value as backup_path, pm2.meta_value as backup_created
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key = '_ultimate_watermark_backup_path'
            AND pm2.meta_key = '_ultimate_watermark_backup_created'
            ORDER BY pm2.meta_value DESC
            LIMIT 10
        ");
        
        
        $valid_backups = 0;
        
        foreach ($backup_attachments as $attachment) {
            if (file_exists($attachment->backup_path)) {
                $valid_backups++;
                $stats['total_backups']++;
                $stats['total_size'] += filesize($attachment->backup_path);
                
                $stats['recent_backups'][] = [
                    'id' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'backup_path' => $attachment->backup_path,
                    'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $attachment->backup_path),
                    'backup_created' => $attachment->backup_created,
                    'size' => filesize($attachment->backup_path)
                ];
            }
        }
        
        $stats['backup_enabled_count'] = $valid_backups;
        
        // If no valid backups found via meta, try filesystem scan as fallback
        if (empty($stats['recent_backups'])) {
            $stats = self::getBackupStatsFromFilesystem();
        }
        
        return $stats;
    }
    
    /**
     * Get backup statistics by scanning filesystem (fallback method)
     * 
     * @return array Backup statistics
     */
    private static function getBackupStatsFromFilesystem(): array
    {
        $stats = [
            'total_backups' => 0,
            'total_size' => 0,
            'backup_enabled_count' => 0,
            'recent_backups' => []
        ];
        
        $upload_dir = wp_upload_dir();
        $backup_base_dir = $upload_dir['basedir'] . '/ulwm-backup';
        
        // Find all year directories in ulwm-backup
        $year_dirs = glob($backup_base_dir . '/*', GLOB_ONLYDIR);
        
        
        foreach ($year_dirs as $year_dir) {
            // Find all month directories in each year
            $month_dirs = glob($year_dir . '/*', GLOB_ONLYDIR);
            
            foreach ($month_dirs as $month_dir) {
                // Find all backup files in each month directory
                $backup_files = glob($month_dir . '/*_original.*');
                
                foreach ($backup_files as $backup_file) {
                    if (file_exists($backup_file)) {
                        $stats['total_backups']++;
                        $stats['total_size'] += filesize($backup_file);
                        
                        // Extract attachment ID from filename (format: ID_filename_original.ext)
                        $backup_info = pathinfo($backup_file);
                        $filename_parts = explode('_', $backup_info['filename']);
                        $attachment_id = isset($filename_parts[0]) ? intval($filename_parts[0]) : 0;
                        
                        // Find attachment by ID
                        global $wpdb;
                        $attachment = $wpdb->get_row($wpdb->prepare("
                            SELECT ID, post_title 
                            FROM {$wpdb->posts} 
                            WHERE ID = %d AND post_type = 'attachment'
                        ", $attachment_id));
                        
                        $stats['recent_backups'][] = [
                            'id' => $attachment ? $attachment->ID : $attachment_id,
                            'title' => $attachment ? $attachment->post_title : 'Unknown Image',
                            'backup_path' => $backup_file,
                            'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $backup_file),
                            'backup_created' => date('Y-m-d H:i:s', filemtime($backup_file)),
                            'size' => filesize($backup_file)
                        ];
                    }
                }
            }
        }
        
        $stats['backup_enabled_count'] = $stats['total_backups'];
        
        // Sort by creation time (newest first)
        usort($stats['recent_backups'], function($a, $b) {
            return strtotime($b['backup_created']) - strtotime($a['backup_created']);
        });
        
        // Limit to 10 most recent
        $stats['recent_backups'] = array_slice($stats['recent_backups'], 0, 10);
        
        
        return $stats;
    }
}
