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
     * Absolute path of the backup directory.
     */
    public static function getBackupDirectory(): string
    {
        return trailingslashit(wp_upload_dir(null, false)['basedir']) . 'ulwm-backup';
    }

    /**
     * Total size in bytes of all backup files.
     */
    public static function getStorageSize(): int
    {
        $dir = self::getBackupDirectory();
        if (!is_dir($dir)) {
            return 0;
        }

        $size = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && !in_array($file->getFilename(), ['.htaccess', 'index.php', 'web.config'], true)) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            return $size;
        }

        return $size;
    }

    /**
     * Random component for backup file names, so originals can't be guessed.
     */
    private static function randomToken(): string
    {
        return strtolower(wp_generate_password(12, false, false));
    }

    /**
     * Block direct web access to the backup directory (Apache / LiteSpeed / IIS).
     * Backups are previewed in wp-admin through an authenticated endpoint.
     */
    public static function protectBackupDirectory(string $backup_base_dir): void
    {
        if (!is_dir($backup_base_dir) || !is_writable($backup_base_dir)) {
            return;
        }

        $files = [
            '.htaccess'  => "# Ultimate Watermark: original images, no public access\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n",
            'index.php'  => "<?php\n// Silence is golden.\n",
            'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
        ];

        foreach ($files as $name => $contents) {
            $path = trailingslashit($backup_base_dir) . $name;
            if (!file_exists($path)) {
                @file_put_contents($path, $contents);
            }
        }
    }

    /**
     * Admin-only URL that streams a backup file (direct access is blocked).
     */
    public static function getBackupPreviewUrl(string $backup_path): string
    {
        $upload_dir = wp_upload_dir();
        $base = trailingslashit(wp_normalize_path($upload_dir['basedir'])) . 'ulwm-backup/';
        $path = wp_normalize_path($backup_path);
        if (strpos($path, $base) !== 0) {
            return '';
        }

        return add_query_arg([
            'action'   => 'ultimate_watermark_backup_preview',
            'file'     => rawurlencode(substr($path, strlen($base))),
            '_wpnonce' => wp_create_nonce('ultimate_watermark_backup_preview'),
        ], admin_url('admin-ajax.php'));
    }

    /**
     * AJAX: stream a backup image to an administrator.
     */
    public static function handleBackupPreview(): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('ultimate_watermark_backup_preview', '_wpnonce', false)) {
            status_header(403);
            exit;
        }

        $relative = isset($_GET['file']) ? rawurldecode(wp_unslash((string) $_GET['file'])) : '';
        $upload_dir = wp_upload_dir();
        $base = realpath(trailingslashit($upload_dir['basedir']) . 'ulwm-backup');
        $file = $base ? realpath($base . '/' . ltrim($relative, '/')) : false;

        if (!$base || !$file || strpos(wp_normalize_path($file), trailingslashit(wp_normalize_path($base))) !== 0 || !is_file($file)) {
            status_header(404);
            exit;
        }

        $type = wp_check_filetype($file);
        if (empty($type['type']) || strpos($type['type'], 'image/') !== 0) {
            status_header(404);
            exit;
        }

        // List thumbnails: a small cached copy instead of the full original
        // (originals can be tens of megabytes).
        if (isset($_GET['size']) && $_GET['size'] === 'thumb') {
            $thumb = self::backupThumbnail($file);
            if ($thumb !== '') {
                $file = $thumb;
                $type = wp_check_filetype($file);
            }
        }

        nocache_headers();
        header('Content-Type: ' . $type['type']);
        header('Content-Length: ' . (string) filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    /**
     * Path of the cached list thumbnail for a backup file.
     */
    private static function backupThumbnailPath(string $file): string
    {
        $base = trailingslashit(wp_normalize_path(wp_upload_dir()['basedir'])) . 'ulwm-backup/.thumbs/';
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: 'jpg';

        return $base . md5(wp_normalize_path($file)) . '.' . $extension;
    }

    /**
     * Small cached copy of a backup for the Backups list (inside the protected folder).
     *
     * @return string Thumbnail path, or '' when it cannot be made.
     */
    private static function backupThumbnail(string $file): string
    {
        $thumb = self::backupThumbnailPath($file);
        if (is_file($thumb) && filemtime($thumb) >= filemtime($file)) {
            return $thumb;
        }

        if (!wp_mkdir_p(dirname($thumb))) {
            return '';
        }

        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            return '';
        }
        $editor->resize(160, 160, false);
        $saved = $editor->save($thumb);

        return (!is_wp_error($saved) && is_file($thumb)) ? $thumb : '';
    }

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
    public static function createBackup(string $file_path, int $attachment_id = 0, int $watermark_id = 0, array $additional_paths = [])
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

            // Backup migrated from v1 (stored under a different key).
            $legacy = get_post_meta($attachment_id, '_ulwm_backup_path', true);
            if (is_string($legacy) && $legacy !== '' && file_exists($legacy)) {
                return $legacy;
            }

            // An original backup already on disk (meta lost): never replace it.
            foreach (self::getAttachmentBackups($attachment_id) as $existing_backup) {
                if ($existing_backup['type'] === 'original') {
                    return $existing_backup['path'];
                }
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
        self::protectBackupDirectory($backup_base_dir);
        
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
    private static function createSingleBackup(string $file_path, int $attachment_id, string $backup_dir, array $settings)
    {
        $file_info = pathinfo($file_path);
        $backup_filename = $attachment_id . '_' . self::randomToken() . '_' . $file_info['filename'] . '_original.' . $file_info['extension'];
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
    private static function createMultiSizeBackup(string $file_path, int $attachment_id, array $watermarked_sizes, string $backup_dir, array $settings, array $additional_paths = [])
    {
        $file_info = pathinfo($file_path);
        $backup_paths = [];
        $token = self::randomToken();
        
        // Always backup full size
        $full_backup_filename = $attachment_id . '_' . $token . '_' . $file_info['filename'] . '_original.' . $file_info['extension'];
        $full_backup_path = $backup_dir . '/' . $full_backup_filename;
        
        if (self::copyImageWithQuality($file_path, $full_backup_path, $settings['backup_quality'])) {
            $backup_paths['original'] = $full_backup_path;
        } else {
            // Without the original nothing can be restored: report failure.
            return false;
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
                $size_info = pathinfo($size_file);
                $size_backup_filename = $attachment_id . '_' . $token . '_' . $file_info['filename'] . '_' . $size_name . '.' . ($size_info['extension'] ?? $file_info['extension']);
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
        // Restored files may be watermarked again in this request (batch "Replace").
        \MantraBrain\UltimateWatermark\Watermark\WatermarkService::forgetAttachment($attachment_id);

        
        // Get all backup files for this attachment (main + all additional sizes)
        $backups = self::getAttachmentBackups($attachment_id);
        
        if (empty($backups)) {
            return false;
        }
        
        // Get applied watermarks BEFORE restoring (to clean up metadata)
        $applied_watermarks = \MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker::getAppliedWatermarks($attachment_id);
        
        // Restore the original first. If several "original" backups exist (e.g.
        // a later backup taken of an already-watermarked file), only the oldest
        // one is the true original.
        $originals = array_values(array_filter($backups, static function ($backup) {
            return $backup['type'] === 'original';
        }));
        usort($originals, static function ($a, $b) {
            return $a['created'] <=> $b['created'];
        });

        // Only the original matters: every sub-size is regenerated from it below
        // (size backups would be overwritten anyway). Without a clean original the
        // image cannot be restored, so leave files and metadata untouched.
        if (empty($originals) || !self::restoreBackupFile($originals[0], $attachment_id)) {
            return false;
        }
        $restored_count = 1;
        
        // If restoration was successful, clean up watermark metadata (same as remove watermark)
        if ($restored_count > 0) {
            // Regenerate attachment metadata to update WordPress about the restored files
            $original_path = get_attached_file($attachment_id);
            if ($original_path && file_exists($original_path)) {
                // Delete previously generated size files BEFORE regenerating, so that
                // wp_generate_attachment_metadata() recreates them clean from the
                // freshly restored original.
                //
                // We drive deletion from the saved attachment metadata rather than
                // from a glob of the upload dir — globbing on the file's basename
                // (e.g. "image-2*.*") was matching unrelated attachments such as
                // "image-22.webp", which led to those getting deleted and to the
                // confusing "image-2.webp does not exist" PHP warnings the user
                // was seeing during restore.
                $metadata = wp_get_attachment_metadata($attachment_id);
                $base_dir = dirname($original_path);

                $size_files_to_delete = [];

                if (is_array($metadata) && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                    foreach ($metadata['sizes'] as $size_data) {
                        if (empty($size_data['file'])) {
                            continue;
                        }
                        $size_files_to_delete[] = $base_dir . '/' . $size_data['file'];
                    }
                }

                // Big images: the restored backup is the unscaled original; the
                // "-scaled" attached file is rebuilt from it below.
                $source_path = function_exists('wp_get_original_image_path') ? (wp_get_original_image_path($attachment_id) ?: $original_path) : $original_path;
                if ($source_path !== $original_path) {
                    $size_files_to_delete[] = $original_path;
                }

                // De-dupe and remove the freshly restored original from the delete list.
                $size_files_to_delete = array_unique($size_files_to_delete);

                foreach ($size_files_to_delete as $size_file) {
                    if (!is_file($size_file)) {
                        continue;
                    }
                    if (wp_normalize_path($size_file) === wp_normalize_path($source_path)) {
                        continue; // never delete the file we just restored
                    }
                    // Safety: only delete inside the uploads dir.
                    if (strpos(wp_normalize_path($size_file), wp_normalize_path($base_dir)) !== 0) {
                        continue;
                    }

                    @unlink($size_file);

                    // Best-effort cleanup of co-located webp/avif siblings created
                    // by performance plugins (e.g. Cache Enabler, Optimole) that
                    // mirror sizes as <name>.webp / <name>.avif. We only target
                    // siblings keyed on this exact size's filename (no glob), so
                    // unrelated attachments cannot be touched.
                    $size_info = pathinfo($size_file);
                    $size_dir  = $size_info['dirname']  ?? $base_dir;
                    $size_stem = $size_info['filename'] ?? '';
                    $size_ext  = strtolower($size_info['extension'] ?? '');

                    foreach (['webp', 'avif'] as $variant_ext) {
                        if ($size_ext === $variant_ext) {
                            continue;
                        }
                        $variant_path = $size_dir . '/' . $size_stem . '.' . $variant_ext;
                        if ($variant_path !== $size_file
                            && wp_normalize_path($variant_path) !== wp_normalize_path($source_path)
                            && is_file($variant_path)
                            && !\MantraBrain\UltimateWatermark\Utils\WatermarkHelper::isFileOfAnotherAttachment($variant_path, $attachment_id)
                        ) {
                            @unlink($variant_path);
                        }
                    }
                }

                // Watermarked next-gen copies of the full-size file (written by us or
                // by an optimizer next to it). Sub-size siblings were removed above.
                foreach (array_unique([$original_path, $source_path]) as $full_file) {
                    $full_info = pathinfo($full_file);
                    foreach (['webp', 'avif'] as $variant_ext) {
                        if (strtolower($full_info['extension'] ?? '') === $variant_ext) {
                            continue;
                        }
                        $variant_path = ($full_info['dirname'] ?? $base_dir) . '/' . ($full_info['filename'] ?? '') . '.' . $variant_ext;
                        if (is_file($variant_path)
                            && !\MantraBrain\UltimateWatermark\Utils\WatermarkHelper::isFileOfAnotherAttachment($variant_path, $attachment_id)
                        ) {
                            @unlink($variant_path);
                        }
                    }
                }

                // CRITICAL: Prevent watermarking during restore by setting a flag
                // wp_generate_attachment_metadata triggers hooks that would apply watermarks again
                update_post_meta($attachment_id, '_ulwm_skip_watermarking', true);

                // Re-verify the original is still on disk before asking WP to
                // generate metadata from it — the deletion loop above is
                // metadata-driven now, but if a future change re-introduces a
                // glob, this guard prevents the getimagesize/exif_imagetype/
                // file_get_contents warnings the user previously hit.
                if (file_exists($source_path)) {
                    // Rebuild the "-scaled" file and every sub-size from the
                    // restored original exactly like an upload does, and save
                    // the resulting metadata.
                    if (!function_exists('wp_create_image_subsizes')) {
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                    }
                    wp_create_image_subsizes($source_path, $attachment_id);
                }

                // Remove the skip flag after regeneration
                delete_post_meta($attachment_id, '_ulwm_skip_watermarking');
            }
            
            // Track watermark usage removal for all applied watermarks
            foreach ($applied_watermarks as $watermark_id) {
                \MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker::decrementUsage($watermark_id, $attachment_id);
            }
            
            // Remove all watermark metadata
            delete_post_meta($attachment_id, 'applied_watermarks');
            delete_post_meta($attachment_id, 'watermarks_by_size');
            delete_post_meta($attachment_id, 'watermark_count');
            delete_post_meta($attachment_id, 'watermark_application_dates');
            
            // Also remove the _ulwm_watermarked flag if it exists
            delete_post_meta($attachment_id, '_ulwm_watermarked');
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
        
        // Verify backup file exists and is readable
        if (!file_exists($backup_path) || !is_file($backup_path) || !is_readable($backup_path)) {
            return false;
        }
        
        // Verify we're actually reading from a backup directory (safety check)
        $backup_dir_check = wp_normalize_path($backup_path);
        if (strpos($backup_dir_check, '/ulwm-backup/') === false) {
            return false;
        }
        
        // Determine the target file path based on backup type
        $target_path = self::getTargetPathForBackup($backup, $attachment_id);
        
        if (!$target_path) {
            return false;
        }
        
        // Ensure target directory exists and is writable
        $target_dir = dirname($target_path);
        if (!file_exists($target_dir)) {
            if (!wp_mkdir_p($target_dir)) {
                return false;
            }
        }
        
        if (!is_writable($target_dir)) {
            return false;
        }
        
 
        // Copy next to the target first and swap it in only when the copy is
        // complete, so a failed restore never loses the current file.
        $temp_path = $target_dir . '/.ulwm-restore-' . wp_generate_password(8, false, false) . '-' . basename($target_path);
        if (!@copy($backup_path, $temp_path)) {
            @unlink($temp_path);
            return false;
        }

        clearstatcache(true, $temp_path);
        if (filesize($temp_path) !== filesize($backup_path)) {
            @unlink($temp_path);
            return false;
        }

        if (!@rename($temp_path, $target_path)) {
            @unlink($temp_path);
            return false;
        }
        clearstatcache(true, $target_path);
        
        // Ensure proper permissions
        @chmod($target_path, 0644);
        
        return true;
    }
    
    /**
     * Get target path for a backup file
     * 
     * @param array $backup Backup file information (with 'type' field)
     * @param int $attachment_id WordPress attachment ID
     * @return string|null Target path or null if cannot determine
     */
    private static function getTargetPathForBackup(array $backup, int $attachment_id): ?string
    {
        $original_path = get_attached_file($attachment_id);
        if (!$original_path) {
            return null;
        }

        // The original backup holds the unscaled upload: restore it to that
        // file (for big images WordPress' attached file is the "-scaled" copy).
        if ($backup['type'] === 'original') {
            $unscaled = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($attachment_id) : false;
            return ($unscaled && $unscaled !== $original_path) ? $unscaled : $original_path;
        }

        // For size files, use the backup type (which is the size name)
        $size_key = $backup['type'];
        
        // Get metadata to find the actual size file path
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
        
        // Search in all year directories (avoids duplicates)
        if (is_dir($backup_base_dir)) {
            $year_dirs = glob($backup_base_dir . '/*', GLOB_ONLYDIR);
            foreach ($year_dirs as $year_dir) {
                if (is_dir($year_dir)) {
                    $month_dirs = glob($year_dir . '/*', GLOB_ONLYDIR);
                    foreach ($month_dirs as $month_dir) {
                        if (is_dir($month_dir)) {
                            $search_dirs[] = $month_dir;
                        }
                    }
                }
            }
        }
        
        // Remove duplicates (in case same directory appears multiple times)
        $search_dirs = array_unique($search_dirs);
        
        $backup_files = [];
        $seen_files = []; // Track files we've already found to avoid duplicates
        
        foreach ($search_dirs as $backup_dir) {
            if (!file_exists($backup_dir)) {
                continue;
            }
            
            // Look for backup files with pattern: {attachment_id}_*.* (both original and size files)
            $backup_pattern = $backup_dir . '/' . $attachment_id . '_*.*';
            $found_files = glob($backup_pattern);
            
            if ($found_files) {
                foreach ($found_files as $found_file) {
                    // Normalize path and check if we've seen this file before
                    $normalized_path = wp_normalize_path($found_file);
                    if (!isset($seen_files[$normalized_path])) {
                        $backup_files[] = $found_file;
                        $seen_files[$normalized_path] = true;
                    }
                }
            }
        }
        
        
        foreach ($backup_files as $backup_file) {
            $backup_info = pathinfo($backup_file);
            $backup_name = $backup_info['filename'];
            
            // Extract backup type dynamically from filename
            $backup_type = self::extractBackupTypeFromFilename($backup_name);
            
            $backups[] = [
                'path' => $backup_file,
                'url' => self::getBackupPreviewUrl($backup_file),
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
        $upload_dir = wp_upload_dir();
        $allowed_base = $upload_dir['basedir'];
        
        foreach ($backups as $backup) {
            $backup_path = $backup['path'];
            
            // Security: Validate path to prevent directory traversal
            if (!empty($backup_path) && strpos($backup_path, $allowed_base) === 0) {
                // Path is within allowed uploads directory
                $normalized_path = wp_normalize_path($backup_path);
                if (file_exists($normalized_path) && is_file($normalized_path)) {
                    if (unlink($normalized_path)) {
                        $deleted++;
                    }
                    $thumb = self::backupThumbnailPath($normalized_path);
                    if (is_file($thumb)) {
                        @unlink($thumb);
                    }
                }
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
        // A backup must be a byte-exact copy: re-encoding loses quality, PNG
        // transparency, EXIF/ICC data and animation, and needs GD.
        // Write under a temporary name and move into place only when complete,
        // so an interrupted copy (full disk) never leaves a truncated "backup"
        // that a later restore would trust.
        $temp = dirname($destination) . '/.ulwm-part-' . self::randomToken() . '-' . basename($destination);
        if (!@copy($source, $temp)) {
            @unlink($temp);
            return false;
        }

        clearstatcache(true, $temp);
        if (@filesize($temp) !== @filesize($source) || !@rename($temp, $destination)) {
            @unlink($temp);
            return false;
        }

        return true;
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
        // Use prepare for security best practices even with static values
        $backup_attachments = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, pm.meta_value as backup_path, pm2.meta_value as backup_created
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = %s
            AND p.post_mime_type LIKE %s
            AND pm.meta_key = %s
            AND pm2.meta_key = %s
            ORDER BY pm2.meta_value DESC
            LIMIT 10
        ", 'attachment', 'image/%', '_ultimate_watermark_backup_path', '_ultimate_watermark_backup_created'));
        
        
        
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
                    'url' => self::getBackupPreviewUrl((string) $attachment->backup_path),
                    'backup_created' => $attachment->backup_created ? get_gmt_from_date($attachment->backup_created, 'c') : '',
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
                'url' => self::getBackupPreviewUrl((string) $main_path),
                // ISO 8601 with timezone, so browsers in any timezone show the right "x ago".
                'backup_created' => gmdate('c', (int) filemtime($main_path)),
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
                'url' => self::getBackupPreviewUrl((string) $main_path),
                // ISO 8601 with timezone, so browsers in any timezone show the right "x ago".
                'backup_created' => gmdate('c', (int) filemtime($main_path)),
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
        $per_page = max(1, (int) $per_page);
        $total_pages = (int) ceil($total / $per_page);
        // Deleting the last row of the last page must not show an empty page.
        $page = max(1, min((int) $page, max(1, $total_pages)));
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
        
        $upload_dir = wp_upload_dir();
        $allowed_base = $upload_dir['basedir'];
        
        foreach ($backup_files as $backup) {
            $backup_path = $backup['path'] ?? '';
            
            // Security: Validate path to prevent directory traversal
            if (!empty($backup_path) && strpos($backup_path, $allowed_base) === 0) {
                // Path is within allowed uploads directory
                $normalized_path = wp_normalize_path($backup_path);
                if (file_exists($normalized_path) && is_file($normalized_path)) {
                    if (unlink($normalized_path)) {
                        $deleted_count++;
                    }
                    $thumb = self::backupThumbnailPath($normalized_path);
                    if (is_file($thumb)) {
                        @unlink($thumb);
                    }
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
