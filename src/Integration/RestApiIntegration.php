<?php

namespace MantraBrain\UltimateWatermark\Integration;

use MantraBrain\UltimateWatermark\Utils\WatermarkHelper;

/**
 * REST API Integration
 * 
 * Handles watermark application for images uploaded via REST API (page/post editor)
 * This runs regardless of is_admin() status since REST API requests don't have is_admin() = true
 */
class RestApiIntegration
{
    /**
     * Initialize the REST API integration
     */
    public function init(): void
    {
        // Hook into add_attachment (fires for ALL attachment creation, including REST API)
        add_action('add_attachment', [$this, 'markForWatermarking']);
        
        // Hook into REST API attachment creation (for page/post editor uploads)
        add_action('rest_insert_attachment', [$this, 'handleRestApiAttachmentUpload'], 10, 3);
        add_action('rest_after_insert_attachment', [$this, 'handleRestApiAttachmentUpload'], 10, 3);
        
        // Hook into wp_insert_post for attachments as ultimate fallback
        // This fires for ALL post creation including REST API
        add_action('wp_insert_post', [$this, 'handleAttachmentPostInsert'], 10, 3);
        
        // Hook into metadata generation to apply watermarks after thumbnails are created
        add_filter('wp_generate_attachment_metadata', [$this, 'processAfterMetadataGeneration'], 10, 2);
    }

    /**
     * Handle REST API attachment upload (for page/post editor)
     * This is called when image is uploaded via REST API from page/post editor
     */
    public function handleRestApiAttachmentUpload(\WP_Post $attachment, \WP_REST_Request $request, bool $creating): void
    {
        if (!$creating || !wp_attachment_is_image($attachment->ID)) {
            return;
        }
        
        // Check if attachment has a parent post (uploaded to a page/post)
        $parent_post_id = $attachment->post_parent;
        
        // Also check REST request parameter 'post'
        if ($parent_post_id <= 0) {
            $post_param = $request->get_param('post');
            if ($post_param && is_numeric($post_param) && $post_param > 0) {
                $parent_post_id = absint($post_param);
            }
        }
        
        // If we have a parent post, check watermark rules
        if ($parent_post_id > 0) {
            // Store parent post_id for later use
            update_post_meta($attachment->ID, '_ulwm_uploaded_to_post_id', $parent_post_id);
            
            // Get parent post to check its post type
            $parent_post = get_post($parent_post_id);
            if ($parent_post) {
                $parent_post_type = $parent_post->post_type;
                
                // Get all active automatic watermarks
                $all_active = WatermarkHelper::getActiveWatermarks();
                
                // Filter by automatic watermarking behavior
                $automatic_watermarks = array_filter($all_active, function($watermark) {
                    return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                });
                
                // Check each watermark's rules against the parent post type
                foreach ($automatic_watermarks as $watermark) {
                    $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
                    
                    if ($watermark_on === 'everywhere' || $watermark_on === '') {
                        // Watermark applies to all post types - mark it
                        update_post_meta($attachment->ID, '_ulwm_watermarked', true);
                        break;
                    } elseif ($watermark_on === 'selected_post_types') {
                        $allowed_post_types = $watermark['watermark_post_types'] ?? [];
                        
                        // Fix double-serialized data
                        if (is_string($allowed_post_types)) {
                            $allowed_post_types = maybe_unserialize($allowed_post_types);
                            if (is_string($allowed_post_types)) {
                                $allowed_post_types = maybe_unserialize($allowed_post_types);
                            }
                        }
                        if (!is_array($allowed_post_types)) {
                            $allowed_post_types = [];
                        }
                        $allowed_post_types = array_map('strval', array_values($allowed_post_types));
                        
                        // Check if parent post type is in allowed types
                        if (in_array($parent_post_type, $allowed_post_types, true)) {
                            // This watermark matches - mark for watermarking
                            update_post_meta($attachment->ID, '_ulwm_watermarked', true);
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Handle attachment insert via wp_insert_post (catches ALL methods including REST API)
     */
    public function handleAttachmentPostInsert(int $post_id, \WP_Post $post, bool $update): void
    {
        // Only process new attachments (not updates) that are images
        if ($update || $post->post_type !== 'attachment' || !wp_attachment_is_image($post_id)) {
            return;
        }
        
        // Check if attachment has a parent post (uploaded to a page/post)
        if ($post->post_parent > 0) {
            // Store parent post_id
            update_post_meta($post_id, '_ulwm_uploaded_to_post_id', $post->post_parent);
            
            // Get parent post to check its post type
            $parent_post = get_post($post->post_parent);
            if ($parent_post) {
                $parent_post_type = $parent_post->post_type;
                
                // Get all active automatic watermarks
                $all_active = WatermarkHelper::getActiveWatermarks();
                
                // Filter by automatic watermarking behavior
                $automatic_watermarks = array_filter($all_active, function($watermark) {
                    return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                });
                
                // Check each watermark's rules against the parent post type
                foreach ($automatic_watermarks as $watermark) {
                    $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
                    
                    if ($watermark_on === 'everywhere' || $watermark_on === '') {
                        // Watermark applies to all post types - mark it
                        update_post_meta($post_id, '_ulwm_watermarked', true);
                        break;
                    } elseif ($watermark_on === 'selected_post_types') {
                        $allowed_post_types = $watermark['watermark_post_types'] ?? [];
                        
                        // Fix double-serialized data
                        if (is_string($allowed_post_types)) {
                            $allowed_post_types = maybe_unserialize($allowed_post_types);
                            if (is_string($allowed_post_types)) {
                                $allowed_post_types = maybe_unserialize($allowed_post_types);
                            }
                        }
                        if (!is_array($allowed_post_types)) {
                            $allowed_post_types = [];
                        }
                        $allowed_post_types = array_map('strval', array_values($allowed_post_types));
                        
                        // Check if parent post type is in allowed types
                        if (in_array($parent_post_type, $allowed_post_types, true)) {
                            // This watermark matches - mark for watermarking
                            update_post_meta($post_id, '_ulwm_watermarked', true);
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Process new attachment after upload (for REST API uploads)
     */
    public function markForWatermarking(int $attachment_id): void
    {
        // Check if auto-apply is enabled via toggle (for media library uploads)
        // For REST API uploads, we'll check watermark rules based on parent post type
        $auto_apply_enabled = false;
        
        // Check POST data first (this should be set by the upload form when toggle is ON)
        if (isset($_POST['ultimate_watermark_auto_apply']) && $_POST['ultimate_watermark_auto_apply'] === '1') {
            $auto_apply_enabled = true;
        } else {
            // Check WordPress option as fallback (set by JavaScript)
            $option_value = get_option('ultimate_watermark_auto_apply_toggle', '0');
            if ($option_value === '1') {
                $auto_apply_enabled = true;
            }
        }
        
        // If toggle is ON, mark for watermarking (media library uploads)
        if ($auto_apply_enabled) {
            // CRITICAL: Capture parent post_id if image is uploaded to a page/post
            $parent_post_id = 0;
            
            // 1. Check $_POST['post_id'] (standard form upload)
            if (isset($_POST['post_id']) && $_POST['post_id'] > 0) {
                $parent_post_id = absint($_POST['post_id']);
            }
            // 2. Check $_REQUEST['post_id'] (fallback)
            elseif (isset($_REQUEST['post_id']) && $_REQUEST['post_id'] > 0) {
                $parent_post_id = absint($_REQUEST['post_id']);
            }
            // 3. Check attachment's post_parent (if already set)
            else {
                $attachment = get_post($attachment_id);
                if ($attachment && $attachment->post_parent > 0) {
                    $parent_post_id = $attachment->post_parent;
                }
            }
            
            // Store parent post_id in attachment meta for rule checking later
            if ($parent_post_id > 0) {
                update_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', $parent_post_id);
            }
            
            // Mark this attachment for watermarking
            update_post_meta($attachment_id, '_ulwm_watermarked', true);
            
            // Reset the toggle after use to prevent accidental watermarking of subsequent uploads
            update_option('ultimate_watermark_auto_apply_toggle', '0');
        }
        // If toggle is OFF, don't mark - let REST API hooks or wp_insert_post handle it
    }

    /**
     * Process after metadata generation to apply watermarks
     */
    public function processAfterMetadataGeneration($metadata, $attachment_id)
    {
        // Check if this image should be watermarked
        $should_watermark = get_post_meta($attachment_id, '_ulwm_watermarked', true);
        
        // If not marked, check if it was uploaded to a page/post via REST API
        if (!$should_watermark && wp_attachment_is_image($attachment_id)) {
            $attachment = get_post($attachment_id);
            if ($attachment && $attachment->post_parent > 0) {
                $parent_post = get_post($attachment->post_parent);
                if ($parent_post) {
                    $parent_post_type = $parent_post->post_type;
                    
                    // Get automatic watermarks and check rules
                    $all_active = WatermarkHelper::getActiveWatermarks();
                    $automatic_watermarks = array_filter($all_active, function($watermark) {
                        return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                    });
                    
                    foreach ($automatic_watermarks as $watermark) {
                        $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
                        
                        if ($watermark_on === 'everywhere' || $watermark_on === '') {
                            $should_watermark = true;
                            break;
                        } elseif ($watermark_on === 'selected_post_types') {
                            $allowed_post_types = $watermark['watermark_post_types'] ?? [];
                            if (is_string($allowed_post_types)) {
                                $allowed_post_types = maybe_unserialize($allowed_post_types);
                                if (is_string($allowed_post_types)) {
                                    $allowed_post_types = maybe_unserialize($allowed_post_types);
                                }
                            }
                            if (!is_array($allowed_post_types)) {
                                $allowed_post_types = [];
                            }
                            $allowed_post_types = array_map('strval', array_values($allowed_post_types));
                            
                            if (in_array($parent_post_type, $allowed_post_types, true)) {
                                $should_watermark = true;
                                break;
                            }
                        }
                    }
                    
                    if ($should_watermark) {
                        update_post_meta($attachment_id, '_ulwm_watermarked', true);
                    }
                }
            }
        }
        
        if ($should_watermark) {
            try {
                // Increase memory limit for watermark processing
                $original_memory_limit = ini_get('memory_limit');
                ini_set('memory_limit', '256M');
                
                // Create backup BEFORE applying watermarks
                $this->createBackupForWatermarking($attachment_id);
                
                // Apply watermarks to the generated thumbnails
                $this->applyWatermarksToGeneratedImages($attachment_id);
                
                // Restore original memory limit
                ini_set('memory_limit', $original_memory_limit);
                
                // Remove the flag to prevent re-processing
                delete_post_meta($attachment_id, '_ulwm_watermarked');
                
            } catch (\Exception $e) {
                // Log error for debugging in production
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark Error: ' . $e->getMessage());
                }
                // Restore original memory limit
                ini_set('memory_limit', $original_memory_limit);
            }
        }
        
        return $metadata;
    }

    /**
     * Create backup for watermarking
     */
    private function createBackupForWatermarking(int $attachment_id): void
    {
        $backup_manager = new \MantraBrain\UltimateWatermark\Utils\BackupManager();
        $backup_manager->createBackup($attachment_id);
    }

    /**
     * Apply watermarks to generated images
     */
    private function applyWatermarksToGeneratedImages(int $attachment_id): void
    {
        // Get all registered image sizes
        $image_sizes = get_intermediate_image_sizes();
        $image_sizes[] = 'full';
        
        // Apply watermarks to each size based on their rules
        // We need to check rules for EACH size separately because rules can specify specific sizes
        foreach ($image_sizes as $size) {
            // Get active automatic watermarks filtered by rules for THIS specific size
            $automatic_watermarks = WatermarkHelper::getActiveAutomaticWatermarks('upload', $attachment_id, $size);
            
            if (empty($automatic_watermarks)) {
                continue; // No watermarks for this size, skip
            }
            
            // Apply each watermark that passed the rules for this size
            foreach ($automatic_watermarks as $watermark) {
                $this->applyWatermarkToAttachmentSize($attachment_id, $watermark, $size);
            }
        }
    }

    /**
     * Apply watermark to a specific attachment size
     */
    private function applyWatermarkToAttachmentSize(int $attachment_id, array $watermark, string $size): void
    {
        // Get watermark ID
        $watermark_id = isset($watermark['ID']) ? $watermark['ID'] : ($watermark['id'] ?? 0);
        
        if (!$watermark_id) {
            return;
        }
        
        // Get the image path for the specific size
        $image_path = $this->getImagePathForSize($attachment_id, $size);
        
        if (!$image_path || !file_exists($image_path)) {
            // If full size might be a scaled image in WordPress, try resolving the scaled variant
            if ($size === 'full') {
                $scaled_path = $this->getScaledImagePathIfExists($attachment_id);
                if ($scaled_path && file_exists($scaled_path)) {
                    $image_path = $scaled_path;
                } else {
                    // Try WebP/AVIF variants if original not present
                    $variant = $this->findExistingVariantPathForAttachment($attachment_id, $size);
                    if ($variant) {
                        $image_path = $variant;
                    } else {
                        return;
                    }
                }
            } else {
                // Try WebP/AVIF variants if original not present
                $variant = $this->findExistingVariantPathForAttachment($attachment_id, $size);
                if ($variant) {
                    $image_path = $variant;
                } else {
                    return;
                }
            }
        }
        
        // Apply watermark using WatermarkService
        try {
            $success = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($image_path, $watermark_id, $image_path);
            
            if ($success) {
                // Also apply to alternative scaled/original counterpart without double-counting
                if ($size === 'full') {
                    $altPath = $this->getAlternateFullImagePath($attachment_id, $image_path);
                    if ($altPath && file_exists($altPath)) {
                        // Best-effort apply; ignore result for counting to avoid double increments
                        \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($altPath, $watermark_id, $altPath);
                    }
                }
                
                // Apply to ALL format variants (both original formats AND WebP/AVIF)
                // This ensures watermark is visible regardless of which format WordPress serves
                $format_variants = $this->getAllFormatVariants($image_path);
                foreach ($format_variants as $variantPath) {
                    if (file_exists($variantPath) && is_writable($variantPath)) {
                        \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($variantPath, $watermark_id, $variantPath);
                    }
                }
                
                // Track watermark usage for this specific size (count once)
                \MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker::incrementUsage($watermark_id, $attachment_id, $size);
            }
        } catch (\Exception $e) {
            // Silent fail for production
        }
    }

    /**
     * Get image file path for a specific size
     * Prefers original formats (jpg, png) over WebP/AVIF variants
     */
    private function getImagePathForSize(int $attachment_id, string $size): ?string
    {
        if ($size === 'full') {
            // First, try to get the primary attached file
            $file_path = get_attached_file($attachment_id);
            
            // Prefer original formats (jpg, png) over WebP/AVIF
            if ($file_path) {
                $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                // If it's a WebP/AVIF, try to find the original format
                if (in_array($ext, ['webp', 'avif'])) {
                    $base = pathinfo($file_path, PATHINFO_DIRNAME) . '/' . pathinfo($file_path, PATHINFO_FILENAME);
                    // Try common original formats
                    foreach (['jpg', 'jpeg', 'png'] as $original_ext) {
                        $original_path = $base . '.' . $original_ext;
                        if (file_exists($original_path)) {
                            return $original_path;
                        }
                    }
                }
                
                // If original format found or not WebP/AVIF, return it
                if (file_exists($file_path)) {
                    return $file_path;
                }
            }
            
            // Also check for scaled versions (WordPress 5.3+ big image feature)
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (!empty($metadata['file'])) {
                $upload_dir = wp_upload_dir();
                $scaled_path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/' . basename($metadata['file']);
                if (file_exists($scaled_path)) {
                    return $scaled_path;
                }
                
                // Check for original_image if exists
                if (!empty($metadata['original_image'])) {
                    $original_path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/' . $metadata['original_image'];
                    if (file_exists($original_path)) {
                        return $original_path;
                    }
                }
            }
            return null;
        }
        
        $image_path = image_get_intermediate_size($attachment_id, $size);
        if ($image_path && isset($image_path['path'])) {
            $upload_dir = wp_upload_dir();
            $full_path = $upload_dir['basedir'] . '/' . $image_path['path'];
            
            // Prefer original formats over WebP/AVIF
            $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
            if (in_array($ext, ['webp', 'avif'])) {
                $base = pathinfo($full_path, PATHINFO_DIRNAME) . '/' . pathinfo($full_path, PATHINFO_FILENAME);
                // Try common original formats
                foreach (['jpg', 'jpeg', 'png'] as $original_ext) {
                    $original_path = $base . '.' . $original_ext;
                    if (file_exists($original_path)) {
                        return $original_path;
                    }
                }
            }
            
            return $full_path;
        }
        
        return null;
    }

    /**
     * Get scaled image path if exists (WordPress 5.3+ big image feature)
     */
    private function getScaledImagePathIfExists(int $attachment_id): ?string
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (empty($metadata['file'])) {
            return null;
        }
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/' . dirname($metadata['file']);
        $original_file = basename($metadata['file']);
        
        // Check for scaled version (e.g., image-scaled-2048x1536.jpg)
        $scaled_pattern = $base_dir . '/' . pathinfo($original_file, PATHINFO_FILENAME) . '-scaled.' . pathinfo($original_file, PATHINFO_EXTENSION);
        if (file_exists($scaled_pattern)) {
            return $scaled_pattern;
        }
        
        // Also check original_image if exists
        if (!empty($metadata['original_image'])) {
            $original_path = $base_dir . '/' . $metadata['original_image'];
            if (file_exists($original_path)) {
                return $original_path;
            }
        }
        return null;
    }

    /**
     * Get alternate full image path (scaled/original counterpart)
     */
    private function getAlternateFullImagePath(int $attachment_id, string $currentFullPath): ?string
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (empty($metadata['file'])) {
            return null;
        }
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/' . dirname($metadata['file']);
        $original_file = basename($metadata['file']);
        
        // If current path is scaled, try original
        if (strpos($currentFullPath, '-scaled.') !== false) {
            $original_path = $base_dir . '/' . $original_file;
            if (file_exists($original_path)) {
                return $original_path;
            }
            if (!empty($metadata['original_image'])) {
                $metaFile = $base_dir . '/' . $metadata['original_image'];
                if (file_exists($metaFile)) {
                    return $metaFile;
                }
            }
        }
        
        // If current path is original, try scaled
        $scaled_pattern = $base_dir . '/' . pathinfo($original_file, PATHINFO_FILENAME) . '-scaled.' . pathinfo($original_file, PATHINFO_EXTENSION);
        if (file_exists($scaled_pattern)) {
            return $scaled_pattern;
        }
        return null;
    }

    /**
     * Get alternative format paths (WebP, AVIF variants)
     */
    private function getAlternativeFormatPaths(string $path): array
    {
        $candidates = [];
        $info = pathinfo($path);
        if (empty($info['dirname']) || empty($info['filename'])) {
            return $candidates;
        }
        $base = $info['dirname'] . '/' . $info['filename'];
        $extensions = ['webp', 'avif'];
        foreach ($extensions as $ext) {
            $alt = $base . '.' . $ext;
            if (strtolower($info['extension'] ?? '') !== $ext && file_exists($alt)) {
                $candidates[] = $alt;
            }
        }
        return $candidates;
    }

    /**
     * Get ALL format variants (both original formats and WebP/AVIF)
     * This ensures watermarks are applied to all formats WordPress might serve
     */
    private function getAllFormatVariants(string $path): array
    {
        $candidates = [];
        $info = pathinfo($path);
        if (empty($info['dirname']) || empty($info['filename'])) {
            return $candidates;
        }
        $base = $info['dirname'] . '/' . $info['filename'];
        $current_ext = strtolower($info['extension'] ?? '');
        
        // All possible formats
        $all_formats = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
        
        foreach ($all_formats as $ext) {
            if ($ext !== $current_ext) {
                $variant = $base . '.' . $ext;
                if (file_exists($variant)) {
                    $candidates[] = $variant;
                }
            }
        }
        
        return $candidates;
    }

    /**
     * Find existing variant path for an attachment/size if the canonical file is missing
     */
    private function findExistingVariantPathForAttachment(int $attachment_id, string $size): ?string
    {
        $primary = $this->getImagePathForSize($attachment_id, $size);
        if ($primary && file_exists($primary)) {
            return $primary;
        }
        if ($size === 'full') {
            $alt = $this->getScaledImagePathIfExists($attachment_id);
            if ($alt && file_exists($alt)) {
                return $alt;
            }
        }
        // Try sibling alternative formats based on where the canonical would be
        if ($primary) {
            foreach ($this->getAlternativeFormatPaths($primary) as $variant) {
                if (file_exists($variant)) {
                    return $variant;
                }
            }
        }
        return null;
    }
}

