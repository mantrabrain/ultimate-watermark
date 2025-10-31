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
        $enabled = $settings['backup_enabled'];
        return $enabled;
    }
    
    /**
     * Create backup of image before watermarking
     * 
     * @param string $file_path Original image path
     * @param int $attachment_id WordPress attachment ID
     * @param int $watermark_id Watermark ID to get settings from
     * @return bool|string|array Backup path(s) on success, false on failure
     */
    public static function createBackup(string $file_path, int $attachment_id = 0, int $watermark_id = 0, array $additional_paths = []): bool|string|array
    {
        
        if (!self::isBackupEnabled()) {
            return false;
        }
        
        
        // If a backup already exists for this attachment, DO NOT overwrite it.
        // We only ever keep the first backup (original before any watermarking).
        if ($attachment_id > 0) {
            $existing_single = get_post_meta($attachment_id, '_ultimate_watermark_backup_path', true);
            $existing_multi = get_post_meta($attachment_id, '_ultimate_watermark_backup_paths', true);
            if (($existing_single && file_exists($existing_single)) || (!empty($existing_multi) && is_array($existing_multi))) {
                return $existing_multi ?: $existing_single;
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
        
        // Get watermark settings to determine which sizes to backup
        $watermarked_sizes = ['full']; // Default to full size only
        if (!empty($additional_paths)) {
            // Use provided additional paths
            $watermarked_sizes = array_keys($additional_paths);
            // Ensure 'full' is always included for the main image
            if (!in_array('full', $watermarked_sizes)) {
                $watermarked_sizes[] = 'full';
            }
        } elseif ($watermark_id > 0) {
            $watermark_data = \MantraBrain\UltimateWatermark\Utils\WatermarkHelper::getActiveWatermarkById($watermark_id);
            if ($watermark_data && isset($watermark_data['watermark_sizes'])) {
                $watermarked_sizes = $watermark_data['watermark_sizes'];
                // Ensure 'full' is always included for the main image
                if (!in_array('full', $watermarked_sizes)) {
                    $watermarked_sizes[] = 'full';
                }
            }
        }
        
        // Handle different backup strategies
        if ($settings['backup_strategy'] === 'watermarked_sizes' && !empty($watermarked_sizes)) {
            // Backup full size + watermarked sizes
            return self::createMultiSizeBackup($file_path, $attachment_id, $watermarked_sizes, $backup_dir, $settings, $additional_paths);
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
    private static function createMultiSizeBackup(string $file_path, int $attachment_id, array $watermarked_sizes, string $backup_dir, array $settings, array $additional_paths = []): bool|array
    {
        $file_info = pathinfo($file_path);
        $backup_paths = [];
        
        // Always backup full size
        $full_backup_filename = $attachment_id . '_' . $file_info['filename'] . '_original.' . $file_info['extension'];
        $full_backup_path = $backup_dir . '/' . $full_backup_filename;
        
        if (self::copyImageWithQuality($file_path, $full_backup_path, $settings['backup_quality'])) {
            $backup_paths['original'] = $full_backup_path;
        }
        
        // Backup original sizes that will be watermarked
        foreach ($watermarked_sizes as $size_name) {
            if ($size_name === 'full') {
                continue; // Already backed up above
            }
            
            // Use provided additional paths if available, otherwise get from registered sizes
            if (!empty($additional_paths) && isset($additional_paths[$size_name])) {
                $size_file = $additional_paths[$size_name];
            } else {
                $size_file = self::getImageSizePath($file_path, $size_name, $attachment_id);
            }
            
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
     * @param int $attachment_id WordPress attachment ID
     * @return string|null Image size path if exists, null otherwise
     */
    private static function getImageSizePath(string $file_path, string $size_name, int $attachment_id = 0): ?string
    {
        // Use WordPress function to get the correct size file path
        if ($attachment_id > 0) {
            $size_file = get_attached_file($attachment_id, true);
            if ($size_file && file_exists($size_file)) {
                // Get the metadata to find the size file
                $metadata = wp_get_attachment_metadata($attachment_id);
                if ($metadata && isset($metadata['sizes'][$size_name]['file'])) {
                    $file_info = pathinfo($file_path);
                    $size_file = $file_info['dirname'] . '/' . $metadata['sizes'][$size_name]['file'];
                    return file_exists($size_file) ? $size_file : null;
                }
            }
        }
        
        // Fallback to old method if WordPress method fails
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
        
        // Get all backup files for this attachment (main + all additional sizes)
        $backups = self::getAttachmentBackups($attachment_id);
        
        if (empty($backups)) {
            return false;
        }
        
        
        // Restore all backup files (main + all additional sizes)
        // Ensure the original (full) is restored FIRST
        usort($backups, function ($a, $b) {
            $aIsOriginal = str_ends_with($a['filename'], '_original.' . (pathinfo($a['filename'], PATHINFO_EXTENSION)));
            $bIsOriginal = str_ends_with($b['filename'], '_original.' . (pathinfo($b['filename'], PATHINFO_EXTENSION)));
            if ($aIsOriginal === $bIsOriginal) {
                return 0;
            }
            return $aIsOriginal ? -1 : 1;
        });

        $restored_count = 0;
        foreach ($backups as $backup) {
            if (self::restoreBackupFile($backup, $attachment_id)) {
                $restored_count++;
            }
        }
        
        
        // DO NOT regenerate attachment metadata after restore
        // This would regenerate thumbnails from the main image, potentially overwriting our restored files
        // The restored files should already be in the correct locations
        if ($restored_count > 0) {
        }
        
        return $restored_count > 0;
    }
    
    /**
     * Restore a single backup file
     * 
     * @param array $backup Backup file information
     * @param int $attachment_id WordPress attachment ID
     * @return bool Success status
     */
    private static function restoreBackupFile(array $backup, int $attachment_id): bool
    {
        $backup_path = $backup['path'];
        $backup_filename = $backup['filename'];
        
        
        // Check if backup file exists and get its size
        if (file_exists($backup_path)) {
            $backup_size = filesize($backup_path);
        } else {
            return false;
        }
        
        // Determine the target file path based on backup type
        $target_path = self::getTargetPathForBackup($backup_filename, $attachment_id);
        
        if (!$target_path) {
            return false;
        }
        
        
        // Check if target file exists before restore
        if (file_exists($target_path)) {
            $target_size_before = filesize($target_path);
        } else {
        }
        
        // Copy backup file to target location
        if (copy($backup_path, $target_path)) {
            $target_size_after = filesize($target_path);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Get target path for a backup file
     * 
     * @param string $backup_filename Backup filename
     * @param int $attachment_id WordPress attachment ID
     * @return string|null Target path or null if cannot determine
     */
    private static function getTargetPathForBackup(string $backup_filename, int $attachment_id): ?string
    {
        $original_path = get_attached_file($attachment_id);
        if (!$original_path) {
            return null;
        }

        // Main original backup always maps to the original path
        // Check if filename ends with _original.{extension}
        $file_info = pathinfo($backup_filename);
        $name_without_ext = $file_info['filename'];
        if (substr($name_without_ext, -9) === '_original') {
            return $original_path;
        }

        // Parse filename: {id}_{base}_{size}.{ext}
        // Example: 147_image_thumbnail.webp -> thumbnail
        
        // Remove attachment ID prefix: 147_image_thumbnail -> image_thumbnail
        $parts = explode('_', $name_without_ext, 2);
        if (count($parts) < 2) {
            return null;
        }
        $name_with_size = $parts[1]; // image_thumbnail
        
        // Find the last underscore to get the size
        $last_underscore_pos = strrpos($name_with_size, '_');
        if ($last_underscore_pos === false) {
            return null;
        }
        
        $size_key = substr($name_with_size, $last_underscore_pos + 1); // thumbnail

        // Use attachment metadata to resolve the actual resized filename
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata || empty($metadata['sizes'])) {
            return null;
        }

        if (!isset($metadata['sizes'][$size_key]['file'])) {
            return null;
        }

        $dir = pathinfo($original_path, PATHINFO_DIRNAME);
        $size_filename = $metadata['sizes'][$size_key]['file'];
        $target_path = $dir . '/' . $size_filename;
        

        return $target_path;
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
                    $size_file = self::getImageSizePath($file_path, $size_name, $attachment_id);
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
        $upload_dir = wp_upload_dir();
        $backup_base_dir = $upload_dir['basedir'] . '/ulwm-backup';
        
        
        // Search in all year/month directories for backup files
        $search_dirs = [];
        
        // First, search in current year/month
        $current_year = date('Y');
        $current_month = date('m');
        $search_dirs[] = $backup_base_dir . '/' . $current_year . '/' . $current_month;
        
        // Then search in all year directories
        if (is_dir($backup_base_dir)) {
            $year_dirs = glob($backup_base_dir . '/*', GLOB_ONLYDIR);
            foreach ($year_dirs as $year_dir) {
                if (is_dir($year_dir)) {
                    $month_dirs = glob($year_dir . '/*', GLOB_ONLYDIR);
                    foreach ($month_dirs as $month_dir) {
                        $search_dirs[] = $month_dir;
                    }
                }
            }
        }
        
        $backup_files = [];
        foreach ($search_dirs as $backup_dir) {
            if (!file_exists($backup_dir)) {
                continue;
            }
            
            
            // Look for backup files with pattern: {attachment_id}_*.* (both original and size files)
            $backup_pattern = $backup_dir . '/' . $attachment_id . '_*.*';
            $found_files = glob($backup_pattern);
            $backup_files = array_merge($backup_files, $found_files);
            
        }
        
        
        foreach ($backup_files as $backup_file) {
            $backup_info = pathinfo($backup_file);
            $backup_name = $backup_info['filename'];
            
            // Extract backup type dynamically from filename
            $backup_type = self::extractBackupTypeFromFilename($backup_name);
            
            $backups[] = [
                'path' => $backup_file,
                'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $backup_file),
                'type' => $backup_type,
                'size' => filesize($backup_file),
                'created' => filemtime($backup_file),
                'filename' => $backup_info['basename']
            ];
        }
        
        return $backups;
    }
    
    /**
     * Extract backup type from filename dynamically
     * 
     * @param string $backup_name Backup filename (without extension)
     * @return string Backup type
     */
    private static function extractBackupTypeFromFilename(string $backup_name): string
    {
        // Pattern: {attachment_id}_{original_filename}_{size} or {attachment_id}_{original_filename}_original
        // We need to extract the {size} part
        
        // First check if it's the main original file (ends with _original)
        if (strpos($backup_name, '_original') !== false && strpos($backup_name, '_original') === strlen($backup_name) - 9) {
            return 'original';
        }
        
        // Extract the size from the filename
        // Pattern: {attachment_id}_{filename}_{size}
        $parts = explode('_', $backup_name);
        
        // Remove the first part (attachment_id)
        if (count($parts) >= 2) {
            $middle_parts = array_slice($parts, 1);
            
            // The size is the last part
            if (!empty($middle_parts)) {
                $size_part = end($middle_parts);
                
                // Check if it's a known WordPress size
                $known_sizes = ['thumbnail', 'medium', 'large', 'medium_large', 'small', 'full'];
                if (in_array($size_part, $known_sizes)) {
                    return $size_part;
                }
                
                // If it's not a known size, it might be a custom size
                // Return the size part as is
                return $size_part;
            }
        }
        
        // Fallback to original if we can't determine
        return 'original';
    }
    
    /**
     * Delete backup files for an attachment
     * 
     * @param int $attachment_id WordPress attachment ID
     * @return bool Success status
     */
    public static function deleteAttachmentBackups(int $attachment_id): bool
    {
        
        // Get all backup files for this attachment (main + all additional sizes)
        $backups = self::getAttachmentBackups($attachment_id);
        
        $deleted = 0;
        foreach ($backups as $backup) {
            if (unlink($backup['path'])) {
                $deleted++;
            } else {
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
        
        // Always use filesystem scan for accurate counting of all backup files
        $stats = self::getBackupStatsFromFilesystem();
        
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
        
        // Build grouped entries per attachment so we only list the main original once
        $year_dirs = glob($backup_base_dir . '/*', GLOB_ONLYDIR);
        $groupedByAttachment = [];
        foreach ($year_dirs as $year_dir) {
            $month_dirs = glob($year_dir . '/*', GLOB_ONLYDIR);
            
            foreach ($month_dirs as $month_dir) {
                // Look for both main original files and size-specific files
                $main_files = glob($month_dir . '/*_original.*');
                $size_files = glob($month_dir . '/*_*.*');
                $backup_files = array_merge($main_files, $size_files);
                
                foreach ($backup_files as $backup_file) {
                    if (!file_exists($backup_file)) {
                        continue;
                    }
                    $backup_info = pathinfo($backup_file);
                    $filename_parts = explode('_', $backup_info['filename']);
                    $attachment_id = isset($filename_parts[0]) ? intval($filename_parts[0]) : 0;
                    if ($attachment_id <= 0) {
                        continue;
                    }
                    // Determine backup type dynamically
                    $backup_type = self::extractBackupTypeFromFilename($backup_info['filename']);

                    if (!isset($groupedByAttachment[$attachment_id])) {
                        $groupedByAttachment[$attachment_id] = [
                            'main' => null,
                            'additional' => []
                        ];
                    }

                    if ($backup_type === 'original') {
                        $groupedByAttachment[$attachment_id]['main'] = $backup_file;
                    } else {
                        $groupedByAttachment[$attachment_id]['additional'][] = [
                            'type' => $backup_type,
                            'path' => $backup_file
                        ];
                    }
                }
            }
        }

        // Compose stats from grouped attachments
        global $wpdb;
        foreach ($groupedByAttachment as $attachment_id => $entry) {
            if (empty($entry['main'])) {
                continue;
            }

            $main_path = $entry['main'];
            $main_size = file_exists($main_path) ? filesize($main_path) : 0;
            $additional_total = 0;
            $additional_count = 0;
            
            foreach ($entry['additional'] as $add) {
                if (file_exists($add['path'])) {
                    $file_size = filesize($add['path']);
                    $additional_total += $file_size;
                    $additional_count++;
                }
            }

            // Count ALL backup files (main + additional sizes)
            $stats['total_backups'] += (1 + $additional_count); // 1 for main + additional count
            $stats['total_size'] += ($main_size + $additional_total);

            $attachment = $wpdb->get_row($wpdb->prepare("
                SELECT ID, post_title 
                FROM {$wpdb->posts} 
                WHERE ID = %d AND post_type = 'attachment'
            ", $attachment_id));

            $stats['recent_backups'][] = [
                'id' => $attachment ? $attachment->ID : $attachment_id,
                'title' => $attachment ? $attachment->post_title : 'Unknown Image',
                'backup_path' => $main_path,
                'url' => str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $main_path),
                'backup_created' => date('Y-m-d H:i:s', filemtime($main_path)),
                'size' => $main_size,
                'additional_sizes' => $additional_count,
                'total_size' => $main_size + $additional_total
            ];
        }
        
        $stats['backup_enabled_count'] = count($groupedByAttachment);
        
        
        // Sort by creation time (newest first)
        usort($stats['recent_backups'], function($a, $b) {
            return strtotime($b['backup_created']) - strtotime($a['backup_created']);
        });
        
        // Limit to 10 most recent
        $stats['recent_backups'] = array_slice($stats['recent_backups'], 0, 10);
        
        
        return $stats;
    }
    
    /**
     * Get paginated backup list
     * 
     * @param int $page Current page number (1-based)
     * @param int $per_page Number of items per page
     * @return array Array with 'backups', 'total', 'total_pages', 'current_page', 'per_page'
     */
    public static function getPaginatedBackups(int $page = 1, int $per_page = 10): array
    {
        $upload_dir = wp_upload_dir();
        $backup_base_dir = $upload_dir['basedir'] . '/ulwm-backup';
        
        // Build grouped entries per attachment
        $year_dirs = glob($backup_base_dir . '/*', GLOB_ONLYDIR);
        $groupedByAttachment = [];
        
        if ($year_dirs) {
            foreach ($year_dirs as $year_dir) {
                $month_dirs = glob($year_dir . '/*', GLOB_ONLYDIR);
                
                if ($month_dirs) {
                    foreach ($month_dirs as $month_dir) {
                        $main_files = glob($month_dir . '/*_original.*');
                        $size_files = glob($month_dir . '/*_*.*');
                        $backup_files = array_merge($main_files ?: [], $size_files ?: []);
                        
                        foreach ($backup_files as $backup_file) {
                            if (!file_exists($backup_file)) {
                                continue;
                            }
                            $backup_info = pathinfo($backup_file);
                            $filename_parts = explode('_', $backup_info['filename']);
                            $attachment_id = isset($filename_parts[0]) ? intval($filename_parts[0]) : 0;
                            if ($attachment_id <= 0) {
                                continue;
                            }
                            
                            $backup_type = self::extractBackupTypeFromFilename($backup_info['filename']);
                            
                            if (!isset($groupedByAttachment[$attachment_id])) {
                                $groupedByAttachment[$attachment_id] = [
                                    'main' => null,
                                    'additional' => []
                                ];
                            }
                            
                            if ($backup_type === 'original') {
                                $groupedByAttachment[$attachment_id]['main'] = $backup_file;
                            } else {
                                $groupedByAttachment[$attachment_id]['additional'][] = [
                                    'type' => $backup_type,
                                    'path' => $backup_file
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // Compose backup list from grouped attachments
        global $wpdb;
        $backups = [];
        
        foreach ($groupedByAttachment as $attachment_id => $entry) {
            if (empty($entry['main'])) {
                continue;
            }
            
            $main_path = $entry['main'];
            $main_size = file_exists($main_path) ? filesize($main_path) : 0;
            $additional_total = 0;
            $additional_count = 0;
            
            foreach ($entry['additional'] as $add) {
                if (file_exists($add['path'])) {
                    $file_size = filesize($add['path']);
                    $additional_total += $file_size;
                    $additional_count++;
                }
            }
            
            $attachment = $wpdb->get_row($wpdb->prepare("
                SELECT ID, post_title 
                FROM {$wpdb->posts} 
                WHERE ID = %d AND post_type = 'attachment'
            ", $attachment_id));
            
            $backups[] = [
                'id' => $attachment ? $attachment->ID : $attachment_id,
                'title' => $attachment ? $attachment->post_title : 'Unknown Image',
                'backup_path' => $main_path,
                'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $main_path),
                'backup_created' => date('Y-m-d H:i:s', filemtime($main_path)),
                'size' => $main_size,
                'additional_sizes' => $additional_count,
                'total_size' => $main_size + $additional_total
            ];
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['backup_created']) - strtotime($a['backup_created']);
        });
        
        $total = count($backups);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Get paginated slice
        $paginated_backups = array_slice($backups, $offset, $per_page);
        
        return [
            'backups' => $paginated_backups,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        ];
    }
    
    /**
     * Delete all backup files for an attachment
     * 
     * @param int $attachment_id WordPress attachment ID
     * @return bool Success status
     */
    public static function deleteBackupFiles(int $attachment_id): bool
    {
        
        // Get all backup files for this attachment
        $backup_files = self::getAttachmentBackups($attachment_id);
        
        if (empty($backup_files)) {
            return true; // No files to delete is considered success
        }
        
        $deleted_count = 0;
        $total_count = count($backup_files);
        
        foreach ($backup_files as $backup) {
            $backup_path = $backup['path'];
            if (file_exists($backup_path)) {
                if (unlink($backup_path)) {
                    $deleted_count++;
                } else {
                }
            }
        }
        
        // Clean up backup metadata
        delete_post_meta($attachment_id, '_ultimate_watermark_backup_path');
        delete_post_meta($attachment_id, '_ultimate_watermark_backup_created');
        delete_post_meta($attachment_id, '_ultimate_watermark_backup_strategy');
        delete_post_meta($attachment_id, '_ultimate_watermark_backup_sizes');
        delete_post_meta($attachment_id, '_ultimate_watermark_backup_paths');
        
        
        return $deleted_count > 0;
    }
}
