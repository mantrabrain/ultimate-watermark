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
        
        // If we have a parent post, store it for rule checking later
        // CRITICAL: Do NOT set _ulwm_watermarked here - that flag is ONLY for toggle-based uploads
        // Rule-based watermarking will be handled in processAfterMetadataGeneration
        if ($parent_post_id > 0) {
            // Store parent post_id for later use in processAfterMetadataGeneration
            update_post_meta($attachment->ID, '_ulwm_uploaded_to_post_id', $parent_post_id);
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
        // CRITICAL: Do NOT set _ulwm_watermarked here - that flag is ONLY for toggle-based uploads
        // Rule-based watermarking will be handled in processAfterMetadataGeneration
        if ($post->post_parent > 0) {
            // Store parent post_id for later use in processAfterMetadataGeneration
            update_post_meta($post_id, '_ulwm_uploaded_to_post_id', $post->post_parent);
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: markForWatermarking - Found toggle in $_POST');
            }
        } 
        // Check REQUEST for plupload multipart_params
        elseif (isset($_REQUEST['ultimate_watermark_auto_apply']) && $_REQUEST['ultimate_watermark_auto_apply'] === '1') {
            $auto_apply_enabled = true;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: markForWatermarking - Found toggle in $_REQUEST');
            }
        }
        // Check WordPress option as fallback (set by JavaScript)
        else {
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
            
            // Get selected watermark IDs from upload parameters
            $selected_watermark_ids = [];
            
            // Check if parameter exists (even if empty - empty string means user wants NO watermarks)
            // IMPORTANT: plupload sends multipart_params as POST data, but they might be in $_FILES context
            // Also check all possible places where the data could be
            $ids_string = '';
            
            // 1. Check $_POST first (standard location - from multipart_params)
            if (isset($_POST['ultimate_watermark_ids'])) {
                $ids_string = sanitize_text_field($_POST['ultimate_watermark_ids']);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Found in $_POST[ultimate_watermark_ids]: ' . $ids_string);
                }
            }
            // 2. Check $_REQUEST (broader scope)
            elseif (isset($_REQUEST['ultimate_watermark_ids'])) {
                $ids_string = sanitize_text_field($_REQUEST['ultimate_watermark_ids']);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Found in $_REQUEST[ultimate_watermark_ids]: ' . $ids_string);
                }
            }
            // 3. Check WordPress option (set by AJAX before upload)
            elseif (get_option('ultimate_watermark_temp_selected_ids', '') !== '') {
                $ids_string = sanitize_text_field(get_option('ultimate_watermark_temp_selected_ids', ''));
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Found in WordPress option: ' . $ids_string);
                }
                // Clear after reading (one-time use)
                delete_option('ultimate_watermark_temp_selected_ids');
            }
            // 4. Check raw input stream (for multipart/form-data)
            else {
                $raw_input = file_get_contents('php://input');
                if (preg_match('/name="ultimate_watermark_ids"[^>]*>([^<]+)</i', $raw_input, $matches)) {
                    $ids_string = sanitize_text_field($matches[1]);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Ultimate Watermark: Found in raw input via regex: ' . $ids_string);
                    }
                }
                // Also try parsing the entire raw input
                elseif (preg_match('/ultimate_watermark_ids[\'"]?\s*[:=]\s*[\'"]?([^\'"&,]+)/i', $raw_input, $matches)) {
                    $ids_string = sanitize_text_field($matches[1]);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Ultimate Watermark: Found in raw input via pattern match: ' . $ids_string);
                    }
                }
                else if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: ultimate_watermark_ids NOT FOUND in $_POST, $_REQUEST, option, or raw input');
                    error_log('Ultimate Watermark: $_POST keys: ' . implode(', ', array_keys($_POST)));
                    error_log('Ultimate Watermark: $_REQUEST keys: ' . implode(', ', array_keys($_REQUEST)));
                    error_log('Ultimate Watermark: Raw input (first 1000 chars): ' . substr($raw_input, 0, 1000));
                }
            }
            
            // Process the IDs string
            if (!empty($ids_string) && trim($ids_string) !== '') {
                $selected_watermark_ids = array_filter(array_map('absint', explode(',', trim($ids_string))));
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Processed IDs from string: ' . print_r($selected_watermark_ids, true));
                }
            } else {
                // If empty string, keep as empty array (user explicitly unchecked all)
                $selected_watermark_ids = [];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: IDs string was empty or not set, using empty array');
                }
            }
            
            // Additional debug: check raw input
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $raw_input = file_get_contents('php://input');
                if (strpos($raw_input, 'ultimate_watermark_ids') !== false) {
                    error_log('Ultimate Watermark: Found ultimate_watermark_ids in raw input (partial): ' . substr($raw_input, 0, 500));
                } else {
                    error_log('Ultimate Watermark: ultimate_watermark_ids NOT found in raw php://input');
                }
            }
            
            // Store selected watermark IDs in attachment meta (even if empty, to track user intent)
            // CRITICAL: Always store as array, never as string
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Storing to meta - IDs: ' . print_r($selected_watermark_ids, true));
            }
            update_post_meta($attachment_id, '_ulwm_selected_watermark_ids', $selected_watermark_ids);
            
            // Verify it was stored correctly (debug only)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $stored = get_post_meta($attachment_id, '_ulwm_selected_watermark_ids', true);
                error_log('Ultimate Watermark: Verified stored meta value: ' . print_r($stored, true));
                error_log('Ultimate Watermark: Stored type: ' . gettype($stored));
            }
            
            // Mark this attachment for watermarking
            update_post_meta($attachment_id, '_ulwm_watermarked', true);
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: ========================================');
                error_log('Ultimate Watermark: markForWatermarking - Toggle enabled');
                error_log('Ultimate Watermark: Attachment ID: ' . $attachment_id);
                error_log('Ultimate Watermark: Selected watermark IDs: ' . print_r($selected_watermark_ids, true));
                error_log('Ultimate Watermark: POST[ultimate_watermark_auto_apply]: ' . ($_POST['ultimate_watermark_auto_apply'] ?? 'NOT SET'));
                error_log('Ultimate Watermark: POST[ultimate_watermark_ids]: ' . ($_POST['ultimate_watermark_ids'] ?? 'NOT SET'));
                error_log('Ultimate Watermark: REQUEST[ultimate_watermark_auto_apply]: ' . ($_REQUEST['ultimate_watermark_auto_apply'] ?? 'NOT SET'));
                error_log('Ultimate Watermark: REQUEST[ultimate_watermark_ids]: ' . ($_REQUEST['ultimate_watermark_ids'] ?? 'NOT SET'));
                error_log('Ultimate Watermark: ========================================');
            }
            
            // DO NOT reset the toggle here - it will be reset after watermark is applied
            // Resetting it here causes race conditions where metadata generation happens before toggle check
        }
        // If toggle is OFF, don't mark - let REST API hooks or wp_insert_post handle it
    }

    /**
     * Process after metadata generation to apply watermarks
     */
    public function processAfterMetadataGeneration($metadata, $attachment_id)
    {
        // CRITICAL: Skip watermarking during restore operations
        // Restore sets _ulwm_skip_watermarking flag to prevent re-watermarking
        if (get_post_meta($attachment_id, '_ulwm_skip_watermarking', true)) {
            return $metadata;
        }
        
        // Check if this image should be watermarked
        // _ulwm_watermarked flag is ONLY set by toggle (media library uploads with toggle ON)
        // For REST API uploads, we check rules directly without setting this flag
        $was_toggle_enabled = get_post_meta($attachment_id, '_ulwm_watermarked', true);
        
        // Determine if we should watermark based on rules (for REST API uploads)
        $should_watermark_by_rules = false;
        
        // If not marked via toggle, check if it was uploaded to a page/post via REST API
        // Use the stored _ulwm_uploaded_to_post_id which was set during upload
        if (!$was_toggle_enabled && wp_attachment_is_image($attachment_id)) {
            // First, try to get from stored meta (set during upload)
            $parent_post_id = get_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', true);
            
            // Fallback: check attachment's post_parent
            if (!$parent_post_id || $parent_post_id <= 0) {
                $attachment = get_post($attachment_id);
                if ($attachment && $attachment->post_parent > 0) {
                    $parent_post_id = $attachment->post_parent;
                }
            }
            
            if ($parent_post_id > 0) {
                $parent_post = get_post($parent_post_id);
                if ($parent_post) {
                    // Check if any watermarks match the parent post type rules
                    // Don't set _ulwm_watermarked flag - that's only for toggle-based uploads
                    $parent_post_type = $parent_post->post_type;
                    $all_active = WatermarkHelper::getActiveWatermarks();
                    $automatic_watermarks = array_filter($all_active, function($watermark) {
                        return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                    });
                    
                    // Filter by post type rules
                    $matching_watermarks = array_filter($automatic_watermarks, function($watermark) use ($parent_post_type) {
                        $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
                        
                        if ($watermark_on === 'everywhere' || $watermark_on === '') {
                            return true;
                        }
                        
                        if ($watermark_on === 'selected_post_types') {
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
                            
                            // For uploads from page/post editor, check parent post type only
                            return in_array(strval($parent_post_type), $allowed_post_types, true);
                        }
                        
                        return false;
                    });
                    
                    // If any watermarks match the rules, watermark should be applied
                    if (!empty($matching_watermarks)) {
                        $should_watermark_by_rules = true;
                    }
                }
            }
        }
        
        // Watermark if toggle was enabled OR rules match
        if ($was_toggle_enabled || $should_watermark_by_rules) {
            try {
                // Increase memory limit for watermark processing
                $original_memory_limit = ini_get('memory_limit');
                ini_set('memory_limit', '256M');
                
                // Create backup BEFORE applying watermarks
                $this->createBackupForWatermarking($attachment_id);
                
                // Apply watermarks to the generated thumbnails
                // Pass whether toggle was enabled (affects filtering behavior)
                // If toggle was enabled, apply all; if rule-based, filter by rules
                $this->applyWatermarksToGeneratedImages($attachment_id, $was_toggle_enabled);
                
                // Restore original memory limit
                ini_set('memory_limit', $original_memory_limit);
                
                // Remove the flag to prevent re-processing (only if toggle was enabled)
                if ($was_toggle_enabled) {
                    delete_post_meta($attachment_id, '_ulwm_watermarked');
                    delete_post_meta($attachment_id, '_ulwm_selected_watermark_ids');
                    // DON'T reset the toggle option - let localStorage control it instead
                    // This allows toggle state to persist across page reloads
                    // update_option('ultimate_watermark_auto_apply_toggle', '0');
                }
                
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
        // Get the original image file path using get_attached_file
        // This should be the original unwatermarked file at this point
        $original_path = get_attached_file($attachment_id);
        if (!$original_path || !file_exists($original_path)) {
            return;
        }

        // CRITICAL: Verify this is the actual original file, not a watermarked version
        // Check metadata to get the original_image if it exists (WordPress big image feature)
        $metadata = wp_get_attachment_metadata($attachment_id);
        $upload_dir = wp_upload_dir();
        
        // If WordPress has stored an original_image (before scaling), use that for backup
        // This is the true original before any WordPress processing
        if (!empty($metadata['original_image'])) {
            $original_image_path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/' . $metadata['original_image'];
            if (file_exists($original_image_path)) {
                $original_path = $original_image_path;
            }
        }

        // Get all image sizes for multi-size backup
        $image_sizes = get_intermediate_image_sizes();
        $image_sizes[] = 'full';
        
        // Collect paths for all image sizes
        // IMPORTANT: At this point, these files should be unwatermarked
        // because we're inside processAfterMetadataGeneration which runs AFTER
        // WordPress generates thumbnails but BEFORE we apply watermarks
        $backup_paths = [];
        foreach ($image_sizes as $size) {
            if ($size === 'full') {
                // For full size, use the original path we determined above
                if (file_exists($original_path)) {
                    $backup_paths['full'] = $original_path;
                }
            } else {
                // For other sizes, get from metadata (generated by WordPress, should be unwatermarked)
                $image_path = $this->getImagePathForSize($attachment_id, $size);
                if ($image_path && file_exists($image_path)) {
                    $backup_paths[$size] = $image_path;
                }
            }
        }
        
        // Ensure we have at least the full size
        if (empty($backup_paths)) {
            $backup_paths['full'] = $original_path;
        } elseif (!isset($backup_paths['full'])) {
            $backup_paths['full'] = $original_path;
        }
        
        // Create backup with all sizes
        \MantraBrain\UltimateWatermark\Utils\BackupManager::createBackup(
            $backup_paths['full'],
            $attachment_id,
            0,
            $backup_paths
        );
    }

    /**
     * Apply watermarks to generated images
     */
    private function applyWatermarksToGeneratedImages(int $attachment_id, bool $use_toggle_rules = false): void
    {
        // If use_toggle_rules is true, this means toggle was enabled (media library upload with toggle ON)
        // In that case, apply SELECTED automatic watermarks (if specified) or ALL if none selected
        // If false, this is rule-based (REST API upload from page/post editor), so filter by rules
        $was_toggle_enabled = $use_toggle_rules;
        
        // Get selected watermark IDs from attachment meta (set during upload)
        $selected_watermark_ids = get_post_meta($attachment_id, '_ulwm_selected_watermark_ids', true);
        
        // CRITICAL: Handle all possible return types from get_post_meta
        if ($selected_watermark_ids === false || $selected_watermark_ids === '' || $selected_watermark_ids === null) {
            $selected_watermark_ids = [];
        } elseif (!is_array($selected_watermark_ids)) {
            // If it's a string (comma-separated), convert to array
            if (is_string($selected_watermark_ids) && !empty(trim($selected_watermark_ids))) {
                $selected_watermark_ids = array_filter(array_map('absint', explode(',', trim($selected_watermark_ids))));
            } else {
                $selected_watermark_ids = [];
            }
        }
        
        // Ensure all IDs are integers
        $selected_watermark_ids = array_map('intval', $selected_watermark_ids);
        $selected_watermark_ids = array_filter($selected_watermark_ids, function($id) {
            return $id > 0;
        });
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: ========================================');
            error_log('Ultimate Watermark: applyWatermarksToGeneratedImages called');
            error_log('Ultimate Watermark: Attachment ID: ' . $attachment_id);
            error_log('Ultimate Watermark: use_toggle_rules = ' . ($use_toggle_rules ? 'true' : 'false'));
            error_log('Ultimate Watermark: Raw meta value: ' . print_r(get_post_meta($attachment_id, '_ulwm_selected_watermark_ids', true), true));
            error_log('Ultimate Watermark: Processed selected watermark IDs: ' . print_r($selected_watermark_ids, true));
            error_log('Ultimate Watermark: Is array: ' . (is_array($selected_watermark_ids) ? 'YES' : 'NO'));
            error_log('Ultimate Watermark: Count: ' . (is_array($selected_watermark_ids) ? count($selected_watermark_ids) : 'N/A'));
            error_log('Ultimate Watermark: Empty check: ' . (empty($selected_watermark_ids) ? 'YES (EMPTY!)' : 'NO'));
            error_log('Ultimate Watermark: ========================================');
        }
        
        // Get parent post ID for rule checking (for REST API uploads from page/post editor)
        $parent_post_id = get_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', true);
        if (!$parent_post_id || $parent_post_id <= 0) {
            $attachment = get_post($attachment_id);
            if ($attachment && $attachment->post_parent > 0) {
                $parent_post_id = $attachment->post_parent;
            }
        }
        
        // Get all registered image sizes
        $image_sizes = get_intermediate_image_sizes();
        $image_sizes[] = 'full';
        
        // Apply watermarks to each size based on their rules
        // We need to check rules for EACH size separately because rules can specify specific sizes
        foreach ($image_sizes as $size) {
            // If toggle was enabled, get selected automatic watermarks (ONLY selected ones, not all)
            // This ensures media library uploads work with user-selected watermarks
            if ($was_toggle_enabled) {
                // If NO watermarks were selected, don't apply any
                if (empty($selected_watermark_ids)) {
                    // Skip this size - no watermarks selected by user
                    continue;
                }
                
                // Get ALL active watermarks first
                $all_active = WatermarkHelper::getActiveWatermarks();
                
                // Filter only by automatic watermarking behavior
                $automatic_watermarks = array_filter($all_active, function($watermark) {
                    return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                });
                
                // CRITICAL: Only apply the SELECTED watermarks (user checked specific ones)
                // Normalize selected IDs to integers for comparison
                $selected_ids_int = array_map('intval', $selected_watermark_ids);
                
                // Debug logging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Filtering watermarks for size ' . $size);
                    error_log('Ultimate Watermark: Selected IDs (int): ' . print_r($selected_ids_int, true));
                    error_log('Ultimate Watermark: Total automatic watermarks before filter: ' . count($automatic_watermarks));
                }
                
                $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($selected_ids_int) {
                    // CRITICAL: WatermarkHelper returns 'id' (lowercase), not 'ID'
                    $watermark_id = isset($watermark['id']) ? (int)$watermark['id'] : (isset($watermark['ID']) ? (int)$watermark['ID'] : 0);
                    
                    // Log all watermark data for debugging
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Ultimate Watermark: Watermark data - ID field: ' . print_r(['id' => $watermark['id'] ?? 'NOT SET', 'ID' => $watermark['ID'] ?? 'NOT SET', 'name' => $watermark['name'] ?? 'UNKNOWN'], true));
                    }
                    
                    // Only apply if this watermark ID is in the selected list
                    $matches = in_array($watermark_id, $selected_ids_int, true);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Ultimate Watermark: Checking watermark ID ' . $watermark_id . ' against selected [' . implode(',', $selected_ids_int) . '] - Matches: ' . ($matches ? 'YES' : 'NO'));
                    }
                    
                    return $matches;
                });
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Watermarks after ID filter: ' . count($automatic_watermarks));
                }
                
                // Then filter by size rules only (not post type rules when toggle was used)
                $size_filtered = array_filter($automatic_watermarks, function($watermark) use ($size) {
                    $passes_size = WatermarkHelper::shouldApplyWatermarkByImageSize($watermark, $size);
                    // CRITICAL: Use 'id' (lowercase) - that's what WatermarkHelper returns
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $wm_id = isset($watermark['id']) ? $watermark['id'] : (isset($watermark['ID']) ? $watermark['ID'] : 'unknown');
                        error_log('Ultimate Watermark: Watermark ID ' . $wm_id . ' for size ' . $size . ' - Passes size rule: ' . ($passes_size ? 'YES' : 'NO'));
                    }
                    return $passes_size;
                });
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Watermarks after size filter: ' . count($size_filtered));
                }
                $automatic_watermarks = $size_filtered;
            } else {
                // Normal rule-based filtering (for REST API uploads, etc.)
                // CRITICAL: Always pass attachment_id - shouldApplyWatermarkByPostType will look up parent from metadata
                // But we need to ensure _ulwm_uploaded_to_post_id is set before calling getActiveAutomaticWatermarks
                
                // Get all active automatic watermarks first (without filtering by post type yet)
                $all_active = WatermarkHelper::getActiveWatermarks();
                $automatic_watermarks = array_filter($all_active, function($watermark) {
                    return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                });
                
                // Now filter by rules: post type AND image size
                // CRITICAL: When was_toggle_enabled is false, we MUST filter by post type rules
                // We already determined in processAfterMetadataGeneration that watermarks should be applied,
                // but we need to filter which specific watermarks match the parent post type
                if (!$was_toggle_enabled && $parent_post_id && $parent_post_id > 0) {
                    $parent_post = get_post($parent_post_id);
                    if ($parent_post) {
                        $parent_post_type = $parent_post->post_type;
                        
                        // Filter by post type rules - manually check each watermark against parent post type
                        // This ensures we don't rely on shouldApplyWatermarkByPostType which checks 'attachment' first
                        // We use the EXACT same logic as in processAfterMetadataGeneration for consistency
                        $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($parent_post_type) {
                            $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
                            
                            // If watermark applies everywhere, include it
                            if ($watermark_on === 'everywhere' || $watermark_on === '') {
                                return true;
                            }
                            
                            // If set to selected_post_types, check if this post type is in the list
                            if ($watermark_on === 'selected_post_types') {
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
                                // CRITICAL: For uploads from page/post editor, we check parent post type ONLY
                                // Do NOT check 'attachment' - that's only for media library uploads
                                return in_array(strval($parent_post_type), $allowed_post_types, true);
                            }
                            
                            // Default: exclude if rules don't match
                            return false;
                        });
                    }
                } elseif (!$was_toggle_enabled) {
                    // No parent post (media library upload without toggle), use standard filtering
                    // This checks if 'attachment' is in allowed types
                    $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($attachment_id) {
                        return WatermarkHelper::shouldApplyWatermarkByPostType($watermark, 'upload', $attachment_id);
                    });
                }
                // If was_toggle_enabled is true, skip post type filtering (already handled above)
                
                // Then filter by image size rules
                $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($size) {
                    return WatermarkHelper::shouldApplyWatermarkByImageSize($watermark, $size);
                });
            }
            
            // Final filter: evaluate unified watermark_rules conditions
            $automatic_watermarks = array_filter($automatic_watermarks, function($watermark) use ($attachment_id, $size, $parent_post_id) {
                $rules = $watermark['watermark_rules'] ?? [];
                if (empty($rules) || !is_array($rules)) {
                    return true; // No unified rules = no extra restriction
                }
                // Check if any rule has conditions
                $has_conditions = false;
                foreach ($rules as $rule) {
                    if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                        $has_conditions = true;
                        break;
                    }
                }
                if (!$has_conditions) {
                    return true; // No conditions defined = no restriction
                }
                $eval_context = \MantraBrain\UltimateWatermark\Utils\RulesEvaluator::buildContext(
                    $attachment_id, $size, intval($parent_post_id ?: 0)
                );
                return \MantraBrain\UltimateWatermark\Utils\RulesEvaluator::evaluate($rules, $eval_context);
            });
            
            if (empty($automatic_watermarks)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: No watermarks to apply for size ' . $size . ' (skipping)');
                }
                continue; // No watermarks for this size, skip
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Applying ' . count($automatic_watermarks) . ' watermark(s) to size ' . $size);
                foreach ($automatic_watermarks as $watermark) {
                    // CRITICAL: WatermarkHelper returns 'id' (lowercase), check that first
                    $wm_id = isset($watermark['id']) ? $watermark['id'] : (isset($watermark['ID']) ? $watermark['ID'] : 'unknown');
                    error_log('Ultimate Watermark: - About to apply watermark ID: ' . $wm_id . ' (Name: ' . ($watermark['name'] ?? 'unknown') . ')');
                }
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
        // Get watermark ID - CRITICAL: WatermarkHelper returns 'id' (lowercase), check that first
        $watermark_id = isset($watermark['id']) ? (int)$watermark['id'] : (isset($watermark['ID']) ? (int)$watermark['ID'] : 0);
        
        error_log('Ultimate Watermark: applyWatermarkToAttachmentSize called - Watermark data keys: ' . implode(', ', array_keys($watermark)));
        error_log('Ultimate Watermark: Extracted watermark ID: ' . $watermark_id);
        
        if (!$watermark_id) {
            error_log('Ultimate Watermark: ERROR - No watermark ID found! Watermark data: ' . print_r($watermark, true));
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
            error_log('Ultimate Watermark: applyWatermarkToAttachmentSize - Attachment: ' . $attachment_id . ', Watermark ID: ' . $watermark_id . ', Size: ' . $size);
            error_log('Ultimate Watermark: Image path: ' . $image_path);
            error_log('Ultimate Watermark: File exists: ' . (file_exists($image_path) ? 'YES' : 'NO'));
            error_log('Ultimate Watermark: File writable: ' . (file_exists($image_path) && is_writable($image_path) ? 'YES' : 'NO'));
            
            $success = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($image_path, $watermark_id, $image_path);
            
            error_log('Ultimate Watermark: applyWatermarkById result for ID ' . $watermark_id . ': ' . ($success ? 'SUCCESS' : 'FAILED'));
            
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
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: applyWatermarkById returned FALSE for watermark ID ' . $watermark_id);
                }
            }
        } catch (\Exception $e) {
            // Log error for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Exception in applyWatermarkToAttachmentSize - ' . $e->getMessage());
                error_log('Ultimate Watermark: Stack trace: ' . $e->getTraceAsString());
            }
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

