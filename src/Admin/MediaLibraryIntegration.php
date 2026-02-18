<?php

namespace MantraBrain\UltimateWatermark\Admin;

use MantraBrain\UltimateWatermark\Utils\WatermarkHelper;
use MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker;
use MantraBrain\UltimateWatermark\Utils\BackupManager;
use MantraBrain\UltimateWatermark\Utils\RulesEvaluator;

/**
 * Media Library Integration
 * 
 * Handles manual watermarking in WordPress Media Library
 */
class MediaLibraryIntegration
{
    /**
     * Initialize the media library integration
     */
    public function init(): void
    {
        add_action('admin_init', [$this, 'addBulkActions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_ajax_ultimate_watermark_apply_manual', [$this, 'handleManualWatermarking']);
        add_action('wp_ajax_ultimate_watermark_apply_automatic', [$this, 'handleAutomaticWatermarking']);
        add_action('wp_ajax_ultimate_watermark_remove', [$this, 'handleRemoveWatermarking']);
        
        // Add upload toggle - try multiple hooks for better compatibility
        add_action('post-upload-ui', [$this, 'addUploadToggle']);
        add_action('upload-ui-top', [$this, 'addUploadToggle']);
        add_action('wp_upload_tabs', [$this, 'addUploadToggle']);
        
        // Hook into upload process - try multiple hooks for better compatibility
        add_action('wp_handle_upload', [$this, 'processUploadedImage'], 10, 2);
        add_filter('wp_handle_upload_prefilter', [$this, 'processUploadedImagePrefilter']);
        
        // Add toggle state to plupload upload parameters (most reliable method)
        add_filter('upload_post_params', [$this, 'addToggleToUploadParams']);
        
        // AJAX handler to temporarily store selected watermark IDs (called before upload)
        add_action('wp_ajax_ultimate_watermark_set_temp_selected_ids', [$this, 'handleSetTempSelectedIds']);
        
        // NOTE: add_attachment and REST API hooks moved to RestApiIntegration class
        // This ensures they work even when is_admin() is false (REST API requests)
        // For admin uploads, we still check toggle in MediaLibraryIntegration
        
        // Add JavaScript to move the toggle after the browse button
        add_action('admin_footer', [$this, 'addUploadToggleScript']);
        
        // Also output toggle HTML in admin footer for media popup (it will be hidden and moved by JS)
        add_action('admin_footer', [$this, 'addUploadToggleToFooter'], 999);
        
        // Add confirmation modal to media library
        add_action('admin_footer', [$this, 'addConfirmationModal']);
        
        // Add cleanup hooks for usage tracking
        add_action('delete_post', [$this, 'cleanupWatermarkUsage']);
        add_action('delete_attachment', [$this, 'cleanupImageUsage']);
        
        // MediaLibraryIntegration hooks registered
        
        // Add watermark protection
        $this->initWatermarkProtection();
    }

    /**
     * Add bulk actions to media library
     */
    public function addBulkActions(): void
    {
        // Add filter for bulk actions dropdown
        add_filter('bulk_actions-upload', [$this, 'addWatermarkBulkActions']);
        
        // Handle the bulk action
        add_filter('handle_bulk_actions-upload', [$this, 'handleWatermarkBulkAction'], 10, 3);
    }

    /**
     * Add watermark options to bulk actions dropdown
     */
    public function addWatermarkBulkActions(array $bulk_actions): array
    {
        // Get ALL active manual watermarks WITHOUT rule filtering
        // Rules will be enforced when actually applying the watermark, not when showing options
        $all_active = WatermarkHelper::getActiveWatermarks();
        
        // Filter only by manual watermarking behavior (not by rules)
        $manual_watermarks = array_filter($all_active, function($watermark) {
            return $watermark['manual_watermarking'] === '1' || (boolean)$watermark['manual_watermarking'] === true;
        });
        
        foreach ($manual_watermarks as $watermark) {
            $action_key = 'ultimate_watermark_' . $watermark['id'];
            $action_label = sprintf(
                __('Apply Watermark: %s', 'ultimate-watermark'),
                $watermark['name']
            );
            
            $bulk_actions[$action_key] = $action_label;
        }
        
        // Add Remove Watermark action
        $bulk_actions['ultimate_watermark_remove'] = __('Remove Watermark', 'ultimate-watermark');
        
        return $bulk_actions;
    }

    /**
     * Handle watermark bulk action
     */
    public function handleWatermarkBulkAction(string $redirect_to, string $doaction, array $post_ids): string
    {
        // Check if it's a watermark action
        if (strpos($doaction, 'ultimate_watermark_') !== 0) {
            return $redirect_to;
        }

        // Handle Remove Watermark action
        if ($doaction === 'ultimate_watermark_remove') {
            return $this->handleRemoveWatermarkAction($redirect_to, $post_ids);
        }

        // Extract watermark ID from action
        $watermark_id = (int) str_replace('ultimate_watermark_', '', $doaction);
        
        if (!$watermark_id) {
            return $redirect_to;
        }

        // Verify watermark exists and is active
        if (!WatermarkHelper::isWatermarkActive($watermark_id)) {
            return $redirect_to;
        }

        // Process each selected media item
        $processed_count = 0;
        $errors = [];

        foreach ($post_ids as $attachment_id) {
            if ($this->applyWatermarkToAttachment($attachment_id, $watermark_id)) {
                $processed_count++;
            } else {
                $errors[] = $attachment_id;
            }
        }

        // Get watermark name for redirect message
        $watermark_name = get_the_title($watermark_id) ?: 'Watermark';
        
        // Add result message to redirect URL
        $redirect_to = add_query_arg([
            'ultimate_watermark_processed' => $processed_count,
            'ultimate_watermark_errors' => count($errors),
            'watermark_name' => urlencode($watermark_name)
        ], $redirect_to);

        return $redirect_to;
    }

    /**
     * Handle Remove Watermark bulk action
     */
    private function handleRemoveWatermarkAction(string $redirect_to, array $post_ids): string
    {
        $processed_count = 0;
        $errors = [];

        foreach ($post_ids as $attachment_id) {
            if ($this->removeWatermarkFromAttachment($attachment_id)) {
                $processed_count++;
            } else {
                $errors[] = $attachment_id;
            }
        }

        // Add result message to redirect URL
        $redirect_to = add_query_arg([
            'ultimate_watermark_removed' => $processed_count,
            'ultimate_watermark_errors' => count($errors),
            'action_type' => 'remove'
        ], $redirect_to);

        return $redirect_to;
    }

    /**
     * Remove watermark from attachment by restoring from backup
     */
    private function removeWatermarkFromAttachment(int $attachment_id): bool
    {
        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return false;
        }

        // Get the current file path
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        // Get applied watermarks before removal
        $applied_watermarks = WatermarkUsageTracker::getAppliedWatermarks($attachment_id);
        
        try {
            // Restore original from backup using BackupManager
            if (BackupManager::restoreFromBackup($file_path, $attachment_id)) {
                // Track watermark usage removal for all applied watermarks
                foreach ($applied_watermarks as $watermark_id) {
                    WatermarkUsageTracker::decrementUsage($watermark_id, $attachment_id);
                }
                
                return true;
            }
        } catch (\Exception $e) {
        }

        return false;
    }


    /**
     * Determine image size context for watermarking
     * 
     * @param string $file_path
     * @return string
     */
    private function determineImageSizeContext(string $file_path): string
    {
        // For now, we'll assume we're processing the full size during uploads
        // In the future, this could be enhanced to detect specific image sizes
        return 'full';
    }

    /**
     * Apply watermark to attachment
     * This method applies watermarks manually (from bulk actions or AJAX)
     * It MUST respect all rules (post types and image sizes)
     */
    private function applyWatermarkToAttachment(int $attachment_id, int $watermark_id): array
    {
        $result = [
            'success' => false,
            'applied_sizes' => [],
            'skipped_sizes' => [],
            'failed_sizes' => [],
            'message' => '',
        ];

        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            $result['message'] = __('Attachment is not an image.', 'ultimate-watermark');
            return $result;
        }

        // Get original image path
        $original_path = get_attached_file($attachment_id);
        if (!$original_path || !file_exists($original_path)) {
            $result['message'] = __('Original image file not found on disk.', 'ultimate-watermark');
            return $result;
        }

        // Prevent automatic watermarking from triggering during metadata regeneration
        // wp_generate_attachment_metadata fires the filter that processAfterMetadataGeneration hooks into
        update_post_meta($attachment_id, '_ulwm_skip_watermarking', true);
        
        // Regenerate all image sizes from original AND save the metadata
        // This ensures ALL currently registered sizes (including WooCommerce sizes
        // that may have been added after original upload) are in the metadata
        $metadata = wp_generate_attachment_metadata($attachment_id, $original_path);
        if (!empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        
        // Remove the skip flag
        delete_post_meta($attachment_id, '_ulwm_skip_watermarking');
        
        // Create backup BEFORE applying watermarks (same as upload flow)
        $this->createBackupForWatermarking($attachment_id);
        
        // Get watermark data
        $watermark_data = WatermarkHelper::getActiveWatermarkById($watermark_id);
        if (!$watermark_data) {
            $result['message'] = __('Watermark not found or inactive.', 'ultimate-watermark');
            return $result;
        }
        
        // For bulk/manual actions, user explicitly selected the watermark
        // So we skip post type rules (user's explicit choice overrides rules)
        // But we still respect watermark_rules conditions (image_size, product_cat, etc.)
        
        // Get all registered image sizes
        $image_sizes = get_intermediate_image_sizes();
        $image_sizes[] = 'full';
        
        // Extract watermark_rules for per-size evaluation
        $rules = $watermark_data['watermark_rules'] ?? [];
        $has_rule_conditions = false;
        if (!empty($rules) && is_array($rules)) {
            foreach ($rules as $rule) {
                if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                    $has_rule_conditions = true;
                    break;
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark [Manual]: Attachment ' . $attachment_id . ', Watermark ' . $watermark_id);
            error_log('Ultimate Watermark [Manual]: has_rule_conditions=' . ($has_rule_conditions ? 'YES' : 'NO'));
            error_log('Ultimate Watermark [Manual]: Rules data: ' . print_r($rules, true));
            error_log('Ultimate Watermark [Manual]: Image sizes to process: ' . implode(', ', $image_sizes));
        }
        
        $success_count = 0;
        
        // Apply watermarks to each size, evaluating rules per-size
        foreach ($image_sizes as $size) {
            // Check legacy size rules ONLY if no unified rules exist
            // If watermark has unified rules with conditions, skip legacy filter
            if (!$has_rule_conditions) {
                if (!WatermarkHelper::shouldApplyWatermarkByImageSize($watermark_data, $size)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Ultimate Watermark [Manual]: Size "' . $size . '" SKIPPED by legacy shouldApplyWatermarkByImageSize');
                    }
                    continue;
                }
            }
            
            // Check modern watermark_rules conditions (image_size, product_cat, etc.)
            // These are evaluated PER SIZE so image_size conditions work correctly
            if ($has_rule_conditions) {
                $eval_context = RulesEvaluator::buildContext($attachment_id, $size, 0);
                $rule_result = RulesEvaluator::evaluate($rules, $eval_context);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark [Manual]: Size "' . $size . '" rules evaluate=' . ($rule_result ? 'PASS' : 'FAIL') . ', context image_size=' . ($eval_context['image_size'] ?? 'N/A'));
                }
                if (!$rule_result) {
                    continue; // Rules don't match for this size
                }
            }
            
            // All rules passed, apply watermark
            $image_path = $this->getImagePathForSize($attachment_id, $size);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark [Manual]: Size "' . $size . '" path=' . ($image_path ?: 'NULL') . ', exists=' . ($image_path && file_exists($image_path) ? 'YES' : 'NO'));
            }
            if (!$image_path || !file_exists($image_path)) {
                $result['skipped_sizes'][] = [
                    'size' => $size,
                    'reason' => sprintf(
                        /* translators: %s: image size name */
                        __('Size "%s" does not exist for this image (image may be too small to generate this size).', 'ultimate-watermark'),
                        $size
                    ),
                ];
                continue;
            }
            try {
                // Set attachment context for Pro plugin placeholder resolution
                \MantraBrain\UltimateWatermark\Watermark\WatermarkService::setAttachmentContext([
                    'attachment_id' => $attachment_id,
                    '_source_image_path' => $image_path,
                ]);
                $apply_success = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($image_path, $watermark_id, $image_path);
                // Clear context after use
                \MantraBrain\UltimateWatermark\Watermark\WatermarkService::setAttachmentContext([]);
                if ($apply_success) {
                    $success_count++;
                    $result['applied_sizes'][] = $size;
                    WatermarkUsageTracker::incrementUsage($watermark_id, $attachment_id, $size);
                } else {
                    $result['failed_sizes'][] = [
                        'size' => $size,
                        'reason' => sprintf(
                            /* translators: %s: image size name */
                            __('Watermark processor failed for size "%s".', 'ultimate-watermark'),
                            $size
                        ),
                    ];
                }
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark [Manual]: Size "' . $size . '" watermark apply=' . ($apply_success ? 'SUCCESS' : 'FAILED'));
                }
            } catch (\Exception $e) {
                \MantraBrain\UltimateWatermark\Watermark\WatermarkService::setAttachmentContext([]);
                $result['failed_sizes'][] = [
                    'size' => $size,
                    'reason' => sprintf(
                        /* translators: 1: image size name, 2: error message */
                        __('Error applying watermark to size "%1$s": %2$s', 'ultimate-watermark'),
                        $size,
                        $e->getMessage()
                    ),
                ];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark [Manual]: Size "' . $size . '" EXCEPTION: ' . $e->getMessage());
                }
            }
        }
        
        $result['success'] = $success_count > 0;
        
        // Build user-friendly summary message
        if ($success_count > 0) {
            $result['message'] = sprintf(
                /* translators: 1: number of sizes, 2: comma-separated list of size names */
                __('Watermark applied to %1$d size(s): %2$s.', 'ultimate-watermark'),
                $success_count,
                implode(', ', $result['applied_sizes'])
            );
        } else {
            $result['message'] = __('Watermark was not applied to any size.', 'ultimate-watermark');
        }
        
        if (!empty($result['skipped_sizes'])) {
            $skipped_names = array_column($result['skipped_sizes'], 'size');
            $result['message'] .= ' ' . sprintf(
                /* translators: %s: comma-separated list of skipped size names */
                __('Skipped sizes (not available): %s.', 'ultimate-watermark'),
                implode(', ', $skipped_names)
            );
        }
        
        return $result;
    }


    /**
     * Enqueue scripts for media library
     */
    public function enqueueScripts(string $hook): void
    {
        if ($hook !== 'upload.php') {
            return;
        }

        wp_enqueue_script(
            'ultimate-watermark-media',
            ULTIMATE_WATERMARK_URL . 'assets/js/media-library.js',
            ['jquery'],
            ULTIMATE_WATERMARK_VERSION,
            true
        );

        wp_localize_script('ultimate-watermark-media', 'ultimate_watermark_media', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ultimate_watermark_media'),
            'processing_text' => __('Processing...', 'ultimate-watermark'),
            'success_text' => __('Watermark applied successfully!', 'ultimate-watermark'),
            'error_text' => __('Error applying watermark.', 'ultimate-watermark')
        ]);
    }

    /**
     * Handle AJAX manual watermarking
     */
    public function handleManualWatermarking(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_media')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        $attachment_ids = array_map('absint', $_POST['attachment_ids'] ?? []);

        if (!$watermark_id || empty($attachment_ids)) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'ultimate-watermark')]);
            return;
        }

        // Verify watermark exists and is active
        if (!WatermarkHelper::isWatermarkActive($watermark_id)) {
            wp_send_json_error(['message' => __('Watermark not found or inactive.', 'ultimate-watermark')]);
            return;
        }

        // Process attachments - pass watermark ID directly to processors
        $results = [];
        $total_success = 0;
        $total_failed = 0;
        $all_messages = [];
        
        foreach ($attachment_ids as $attachment_id) {
            $detail = $this->applyWatermarkToAttachment($attachment_id, $watermark_id);
            $results[] = [
                'id' => $attachment_id,
                'success' => $detail['success'],
                'message' => $detail['message'],
                'applied_sizes' => $detail['applied_sizes'],
                'skipped_sizes' => $detail['skipped_sizes'],
                'failed_sizes' => $detail['failed_sizes'],
            ];
            if ($detail['success']) {
                $total_success++;
            } else {
                $total_failed++;
            }
            $all_messages[] = sprintf(
                /* translators: 1: attachment ID, 2: detail message */
                __('Attachment #%1$d: %2$s', 'ultimate-watermark'),
                $attachment_id,
                $detail['message']
            );
        }
        
        $summary = sprintf(
            /* translators: 1: success count, 2: total count */
            __('%1$d of %2$d attachment(s) watermarked successfully.', 'ultimate-watermark'),
            $total_success,
            count($attachment_ids)
        );

        wp_send_json_success([
            'message' => $summary,
            'details' => $all_messages,
            'results' => $results
        ]);
    }

    /**
     * Add upload toggle to upload page
     */
    public function addUploadToggle(): void
    {
        // Prevent duplicate output (this method is hooked to multiple actions)
        static $already_output = false;
        if ($already_output) {
            return;
        }
        $already_output = true;
        
        // Only show on upload page, not on media edit page
        $screen = get_current_screen();
        if ($screen) {
            // Don't show on post.php (media edit page)
            if ($screen->id === 'attachment' || ($screen->base === 'post' && isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit')) {
                $post = get_post(absint($_GET['post'] ?? 0));
                if ($post && $post->post_type === 'attachment') {
                    return; // Skip on media edit page
                }
            }
        }
        
        // Also check via global $pagenow
        global $pagenow;
        if ($pagenow === 'post.php' && isset($_GET['action']) && $_GET['action'] === 'edit') {
            $post = get_post(absint($_GET['post'] ?? 0));
            if ($post && $post->post_type === 'attachment') {
                return; // Skip on media edit page
            }
        }
        
        // Get ALL active automatic watermarks WITHOUT rule filtering
        // Rules will be enforced when actually applying the watermark, not when showing options
        $all_active = WatermarkHelper::getActiveWatermarks();
        
        // Filter only by automatic watermarking behavior (not by rules)
        $automatic_watermarks = array_filter($all_active, function($watermark) {
            return $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
        });
        
        // Always show the toggle, but with different messages based on availability
        $has_automatic_watermarks = !empty($automatic_watermarks);
        

        ?>
        <div id="ultimate-watermark-upload-toggle" style="
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 20px auto 0 auto;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            width: 250px;
            display: block;
            text-align: center;
        ">
            <div style="display: flex; align-items: center; justify-content: center; gap: 12px; text-align: center;">
                <div class="uw-toggle-wrapper" style="display: flex; align-items: center; gap: 12px;">
                    <div class="uw-toggle-container" style="
                        position: relative;
                        display: inline-block;
                        width: 90px;
                        height: 26px;
                    ">
                        <input type="checkbox" id="ultimate-watermark-auto-apply" name="ultimate_watermark_auto_apply" value="1"
                               style="opacity: 0; width: 0; height: 0; position: absolute;">
                        <label for="ultimate-watermark-auto-apply" class="uw-toggle-track" style="
                            display: block;
                            width: 100%;
                            height: 100%;
                            background: #e2e8f0;
                            border-radius: 13px;
                            cursor: pointer;
                            position: relative;
                            transition: all 0.3s ease;
                            border: 2px solid #d1d5db;
                        ">
                            <span class="uw-toggle-thumb" style="
                                position: absolute;
                                top: 3px;
                                left: 2px;
                                width: 20px;
                                height: 20px;
                                background: white;
                                border-radius: 50%;
                                transition: all 0.3s ease;
                                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                            "></span>
                        </label>
                    </div>
                    <label for="ultimate-watermark-auto-apply" style="font-size: 13px; line-height: 1.4; cursor: pointer; font-weight: 500;">
                        <?php esc_html_e('Apply automatic watermarks to uploaded images', 'ultimate-watermark'); ?>
                    </label>
                </div>
            </div>
            
                    <div id="ultimate-watermark-info" style="
                        margin-top: 10px;
                        padding: 12px;
                        background: #e7f3ff;
                        border: 1px solid #b3d9ff;
                        border-radius: 4px;
                        font-size: 12px;
                        color: #0066cc;
                        display: none;
                    ">
                <?php 
                if ($has_automatic_watermarks) {
                    $watermark_count = count($automatic_watermarks);
                    echo '<div style="margin-bottom: 10px; font-weight: 600;">';
                    printf(
                        esc_html__('Select which watermarks to apply (%d available):', 'ultimate-watermark'),
                        $watermark_count
                    );
                    echo '</div>';
                    
                    echo '<div id="ultimate-watermark-list" style="max-height: 200px; overflow-y: auto;">';
                    foreach ($automatic_watermarks as $watermark) {
                        $watermark_id = absint($watermark['id']);
                        $watermark_type = $watermark['type'] === 'text' ? __('Text', 'ultimate-watermark') : __('Image', 'ultimate-watermark');
                        $watermark_name = esc_html($watermark['name']);
                        $edit_url = admin_url('admin.php?page=ultimate-watermark-add-watermark&ID=' . $watermark_id);
                        
                        echo '<div class="uw-watermark-item" style="display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid rgba(0, 102, 204, 0.1);">';
                        echo '<input type="checkbox" class="uw-watermark-checkbox" id="uw-wm-' . esc_attr($watermark_id) . '" 
                                     name="ultimate_watermark_ids[]" value="' . esc_attr($watermark_id) . '" 
                                     data-watermark-id="' . esc_attr($watermark_id) . '"
                                     style="margin: 0; cursor: pointer;">';
                        echo '<label for="uw-wm-' . esc_attr($watermark_id) . '" style="flex: 1; cursor: pointer; margin: 0; display: flex; align-items: center; gap: 6px;">';
                        echo '<span style="font-weight: 500;">' . esc_html($watermark_name) . '</span>';
                        echo '<span style="color: #6b7280; font-size: 11px;">(' . esc_html($watermark_type) . ')</span>';
                        echo '</label>';
                        echo '<a href="' . esc_url($edit_url) . '" target="_blank" 
                                 style="color: #0066cc; text-decoration: none; font-size: 11px; padding: 2px 6px;" 
                                 title="' . esc_attr__('Edit watermark', 'ultimate-watermark') . '">';
                        echo '✎';
                        echo '</a>';
                        echo '</div>';
                    }
                    echo '</div>';
                    
                    echo '<div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(0, 102, 204, 0.2); display: flex; gap: 8px;">';
                    echo '<button type="button" class="uw-select-all" style="padding: 4px 8px; font-size: 11px; cursor: pointer; border: 1px solid #0066cc; background: white; color: #0066cc; border-radius: 3px;">';
                    esc_html_e('Select All', 'ultimate-watermark');
                    echo '</button>';
                    echo '<button type="button" class="uw-deselect-all" style="padding: 4px 8px; font-size: 11px; cursor: pointer; border: 1px solid #0066cc; background: white; color: #0066cc; border-radius: 3px;">';
                    esc_html_e('Deselect All', 'ultimate-watermark');
                    echo '</button>';
                    echo '</div>';
                } else {
                    echo '<div style="margin-bottom: 8px; color: #d97706;">';
                    esc_html_e('No automatic watermarks are currently configured.', 'ultimate-watermark');
                    echo '</div>';
                    echo '<div style="font-size: 11px; color: #6b7280;">';
                    esc_html_e('To enable automatic watermarking, create a watermark and enable "Automatic Watermarking" in its settings.', 'ultimate-watermark');
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <style>
        /* Proper Toggle Switch Styles */
        
        /* Unchecked state - thumb on the left */
        .uw-toggle-container .uw-toggle-thumb {
            left: 2px !important;
        }
        
        /* Checked state - thumb on the right */
        .uw-toggle-container input[type="checkbox"]:checked + .uw-toggle-track {
            background: #10b981 !important;
            border-color: #059669 !important;
        }
        
        .uw-toggle-container input[type="checkbox"]:checked + .uw-toggle-track .uw-toggle-thumb {
            left: 33px !important;
            background: white !important;
        }
        
        .uw-toggle-container:hover .uw-toggle-track {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .uw-toggle-container input[type="checkbox"]:checked:hover + .uw-toggle-track {
            background: #059669 !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .uw-toggle-container input[type="checkbox"]:focus + .uw-toggle-track {
           
        }
        
        .uw-toggle-track:active .uw-toggle-thumb {
            transform: scale(0.95);
        }
        
        .uw-toggle-container input[type="checkbox"]:checked + .uw-toggle-track:active .uw-toggle-thumb {
            left: 64px !important;
            transform: scale(0.95) !important;
        }
        
        /* Info tooltip styles */
        #ultimate-watermark-info a:hover {
            text-decoration: underline !important;
            color: #004499 !important;
        }
        
        #ultimate-watermark-info a:visited {
            color: #0066cc !important;
        }
        </style>

        <script>
        (function($) {
            'use strict';
            
            // Global function to update toggle state
            window.ultimateWatermarkUpdateToggleState = function(forceUpdate = false) {
                // Check both regular toggle and popup toggle, prefer the one that's checked or visible
                let $toggle = $('#ultimate-watermark-auto-apply:checked');
                if ($toggle.length === 0) {
                    $toggle = $('#ultimate-watermark-auto-apply-popup:checked');
                }
                if ($toggle.length === 0) {
                    $toggle = $('#ultimate-watermark-auto-apply').first();
                }
                if ($toggle.length === 0) {
                    $toggle = $('#ultimate-watermark-auto-apply-popup').first();
                }
                if ($toggle.length === 0) return;
                
                const isEnabled = $toggle.is(':checked');
                const currentState = localStorage.getItem('ultimate_watermark_toggle_state');
                
                // Only update if state has changed or force update is requested
                if (!forceUpdate && currentState === (isEnabled ? '1' : '0')) {
                    return; // No change needed
                }
                
                // Save to localStorage for persistence across page reloads
                localStorage.setItem('ultimate_watermark_toggle_state', isEnabled ? '1' : '0');
                
                // Also save to sessionStorage for upload hooks
                sessionStorage.setItem('ultimate_watermark_auto_apply', isEnabled ? '1' : '0');
                
                // Send to server via AJAX to store in option (asynchronous for better performance)
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    async: true, // Changed to asynchronous for better performance
                    data: {
                        action: 'ultimate_watermark_update_toggle_state',
                        enabled: isEnabled ? '1' : '0',
                        nonce: '<?php echo wp_create_nonce('ultimate_watermark_toggle'); ?>'
                    }
                });
            };
            
            // Load toggle state from localStorage on page load
            window.loadToggleState = function() {
                try {
                    const saved = localStorage.getItem('ultimate_watermark_toggle_state');
                    const $toggles = $('#ultimate-watermark-auto-apply, #ultimate-watermark-auto-apply-popup');
                    const $infos = $('#ultimate-watermark-info, #ultimate-watermark-info-popup');
                    
                    if (saved === '1') {
                        // Restore toggle to ON state
                        $toggles.prop('checked', true);
                        // Show info boxes when toggle is ON
                        $infos.show();
                    } else if (saved === '0') {
                        // Restore toggle to OFF state
                        $toggles.prop('checked', false);
                        // Hide info boxes when toggle is OFF
                        $infos.hide();
                    } else {
                        // Default: OFF
                        $toggles.prop('checked', false);
                        $infos.hide();
                    }
                } catch (e) {
                    // If error, default to OFF
                    $('#ultimate-watermark-auto-apply, #ultimate-watermark-auto-apply-popup').prop('checked', false);
                    $('#ultimate-watermark-info, #ultimate-watermark-info-popup').hide();
                }
            };
            
            // Save selected watermark IDs to localStorage
            window.saveWatermarkSelections = function(sourceInstance) {
                const selectedIds = [];
                // Get unique selected IDs from all checkboxes (avoid duplicates)
                const seenIds = {};
                
                // CRITICAL: Only check checkboxes from VISIBLE instances
                // This prevents duplicate checkboxes (main + popup) from interfering
                // If sourceInstance is provided, use that; otherwise check all visible info boxes
                let $checkboxesToCheck;
                if (sourceInstance) {
                    // Use the specific instance that triggered the save
                    $checkboxesToCheck = sourceInstance.find('.uw-watermark-checkbox');
                } else {
                    // Check only visible info boxes (user is interacting with visible ones)
                    $checkboxesToCheck = $('#ultimate-watermark-info:visible, #ultimate-watermark-info-popup:visible').find('.uw-watermark-checkbox');
                    
                    // If no visible ones, fall back to checking the first instance (main)
                    if ($checkboxesToCheck.length === 0) {
                        $checkboxesToCheck = $('#ultimate-watermark-info').find('.uw-watermark-checkbox');
                    }
                }
                
                // IMPORTANT: Check each checkbox individually to get current state
                $checkboxesToCheck.each(function() {
                    const $checkbox = $(this);
                    // Check the actual checked property
                    if ($checkbox.is(':checked') || $checkbox.prop('checked') === true) {
                        let watermarkId = $checkbox.data('watermark-id');
                        // Convert to string for consistent comparison
                        if (watermarkId) {
                            watermarkId = String(watermarkId);
                            if (!seenIds[watermarkId]) {
                                selectedIds.push(watermarkId);
                                seenIds[watermarkId] = true;
                            }
                        }
                    }
                });
                
                // Always save - even if empty array (user unchecked all)
                // This REPLACES the old value completely, doesn't append
                localStorage.setItem('ultimate_watermark_selected_ids', JSON.stringify(selectedIds));
                
                // CRITICAL: Trigger event to update multipart_params in all plupload instances
                $(document).trigger('ultimate-watermark-selection-changed');
                
                // IMPORTANT: Sync all checkbox instances after saving
                // This ensures main and popup instances stay in sync
                const savedIds = selectedIds.map(function(id) { return String(id); });
                $('.uw-watermark-checkbox').each(function() {
                    const $checkbox = $(this);
                    const watermarkId = String($checkbox.data('watermark-id'));
                    if (savedIds.indexOf(watermarkId) !== -1) {
                        $checkbox.prop('checked', true);
                    } else {
                        $checkbox.prop('checked', false);
                    }
                });
                
            };
            
            // Load saved watermark selections from localStorage
            window.loadWatermarkSelections = function() {
                try {
                    const saved = localStorage.getItem('ultimate_watermark_selected_ids');
                    if (saved) {
                        const selectedIds = JSON.parse(saved);
                        
                        // Convert all saved IDs to strings for consistent comparison
                        const selectedIdsAsStrings = selectedIds.map(function(id) {
                            return String(id);
                        });
                        
                        
                        // IMPORTANT: Temporarily disable change handlers to prevent saving during load
                        // This prevents the save function from firing while we're setting checkbox states
                        $('.uw-watermark-checkbox').off('change.uw-selection');
                        
                        // First uncheck ALL checkboxes (both main and popup instances)
                        // Use both prop and removeAttr for maximum compatibility
                        $('.uw-watermark-checkbox').each(function() {
                            const $checkbox = $(this);
                            $checkbox.prop('checked', false).removeAttr('checked');
                        });
                        
                        // Then check only the saved ones - compare as strings
                        let checkedCount = 0;
                        $('.uw-watermark-checkbox').each(function() {
                            const $checkbox = $(this);
                            let watermarkId = $checkbox.data('watermark-id');
                            
                            // Convert to string for comparison
                            if (watermarkId) {
                                watermarkId = String(watermarkId);
                                if (selectedIdsAsStrings.indexOf(watermarkId) !== -1) {
                                    $checkbox.prop('checked', true);
                                    checkedCount++;
                                } else {
                                    // Explicitly uncheck if not in saved list
                                    $checkbox.prop('checked', false).removeAttr('checked');
                                }
                            } else {
                                // No ID, uncheck it
                                $checkbox.prop('checked', false).removeAttr('checked');
                            }
                        });
                        
                        // Re-enable change handlers AFTER loading is complete
                        // This ensures user changes after load will be saved
                        // Handlers will be reattached in initToggle() for each instance
                        
                    } else {
                        // No saved selections - leave all unchecked
                        // User must explicitly select which watermarks to apply
                        $('.uw-watermark-checkbox').prop('checked', false);
                    }
                } catch (e) {
                    // If error parsing, uncheck all - don't auto-check on error
                    $('.uw-watermark-checkbox').each(function() {
                        $(this).prop('checked', false).removeAttr('checked');
                    });
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Ultimate Watermark: Error loading selections:', e);
                    }
                }
            };
            
            // Initialize toggle functionality - works everywhere
            window.initToggle = function() {
                // Handle both regular toggle and popup toggle
                const $toggle = $('#ultimate-watermark-auto-apply, #ultimate-watermark-auto-apply-popup');
                const $info = $('#ultimate-watermark-info, #ultimate-watermark-info-popup');
                
                if ($toggle.length === 0) return;
                
                // Load toggle state from localStorage first
                window.loadToggleState();
                
                // Initialize each toggle instance separately
                $toggle.each(function() {
                    const $thisToggle = $(this);
                    const toggleId = $thisToggle.attr('id');
                    const isPopup = toggleId && toggleId.indexOf('-popup') !== -1;
                    const $thisInfo = isPopup ? $('#ultimate-watermark-info-popup') : $('#ultimate-watermark-info');
                    
                    if ($thisInfo.length === 0) return;
                
                    // Get checkboxes reference
                    const $checkboxes = $thisInfo.find('.uw-watermark-checkbox');
                    
                    // Load saved checkbox selections for THIS specific instance
                    // Do this BEFORE setting up event handlers
                    if ($checkboxes.length > 0 && !window.watermarkSelectionsLoaded) {
                        // Temporarily show info box to ensure checkboxes are accessible
                        const wasHidden = !$thisInfo.is(':visible');
                        if (wasHidden) {
                            $thisInfo.show();
                        }
                        
                        // Load selections from localStorage
                        window.loadWatermarkSelections();
                        window.watermarkSelectionsLoaded = true;
                        
                        // Restore visibility based on toggle state
                        if (wasHidden && !$thisToggle.is(':checked')) {
                            $thisInfo.hide();
                        }
                    }
                
                    // Show/hide info based on toggle state
                    $thisToggle.off('change.ultimate-watermark').on('change.ultimate-watermark', function() {
                        if ($(this).is(':checked')) {
                            $thisInfo.slideDown(200);
                            // When toggled ON, ensure selections are loaded
                            if (!window.watermarkSelectionsLoaded && $checkboxes.length > 0) {
                                window.loadWatermarkSelections();
                                window.watermarkSelectionsLoaded = true;
                            }
                        } else {
                            $thisInfo.slideUp(200);
                        }
                        window.ultimateWatermarkUpdateToggleState();
                    });
                    
                    // Initialize visibility based on toggle state - use multiple checks
                    function setVisibility() {
                        if ($thisToggle.is(':checked')) {
                            $thisInfo.show().css('display', ''); // Remove any inline display:none
                        } else {
                            $thisInfo.hide();
                        }
                    }
                    
                    // Set visibility immediately and again after delays
                    setVisibility();
                    setTimeout(setVisibility, 50);
                    setTimeout(setVisibility, 200);
                    setTimeout(setVisibility, 500);
                    
                    // Handle checkbox changes - save selection immediately
                    $checkboxes.off('change.uw-selection').on('change.uw-selection', function(e) {
                        const $checkbox = $(this);
                        const watermarkId = $checkbox.data('watermark-id');
                        const isChecked = $checkbox.is(':checked') || $checkbox.prop('checked') === true;
                        
                        
                        // Small delay to ensure checkbox state has fully updated in DOM
                        setTimeout(function() {
                            // Pass THIS instance to save function so it knows which instance triggered the save
                            window.saveWatermarkSelections($thisInfo);
                            // Update status indicator immediately
                            if (typeof updateStatusIndicator === 'function') {
                                updateStatusIndicator();
                            }
                        }, 50);
                    });
                    
                    // Handle Select All button
                    $thisInfo.find('.uw-select-all').off('click').on('click', function() {
                        $thisInfo.find('.uw-watermark-checkbox').prop('checked', true);
                        // Save with this instance context
                        setTimeout(function() {
                            window.saveWatermarkSelections($thisInfo);
                        }, 10);
                    });
                    
                    // Handle Deselect All button
                    $thisInfo.find('.uw-deselect-all').off('click').on('click', function() {
                        $thisInfo.find('.uw-watermark-checkbox').prop('checked', false);
                        // Save with this instance context
                        setTimeout(function() {
                            window.saveWatermarkSelections($thisInfo);
                        }, 10);
                    });
                    
                    // Initialize state based on toggle (already set above, but ensure it's correct)
                    // This is redundant but ensures visibility is correct
                    
                    // Add visual indicator if not exists (one per toggle instance)
                    const statusId = isPopup ? 'toggle-status-popup' : 'toggle-status';
                    if ($('#' + statusId).length === 0) {
                        const $statusIndicator = $('<div id="' + statusId + '" style="margin-top: 10px; padding: 5px; border-radius: 4px; font-size: 12px; text-align: center;"></div>');
                        $thisInfo.after($statusIndicator);
                        
                        function updateStatusIndicator() {
                            const isEnabled = $thisToggle.is(':checked');
                            const selectedCount = $thisInfo.find('.uw-watermark-checkbox:checked').length;
                            const totalCount = $thisInfo.find('.uw-watermark-checkbox').length;
                            if (isEnabled && selectedCount > 0) {
                                $statusIndicator.text('✅ ' + selectedCount + ' watermark(s) selected')
                                    .css('background', '#d4edda')
                                    .css('color', '#155724');
                            } else {
                                $statusIndicator.text('❌ Watermarking DISABLED')
                                    .css('background', '#f8d7da')
                                    .css('color', '#721c24');
                            }
                        }
                        
                        $thisToggle.on('change', updateStatusIndicator);
                        // Use the same namespace for consistency
                        $checkboxes.on('change.uw-status', updateStatusIndicator);
                        updateStatusIndicator();
                    }
                });
                
                // Update toggle state once (after initializing all toggles) - force update to ensure server sync
                window.ultimateWatermarkUpdateToggleState(true);
            };
            
            // NOTE: We don't patch FormData.append because plupload uses moxie's polyfill
            // which has different behavior. Instead, we ensure multipart_params values are correct.
            
            // CRITICAL: Monkey-patch plupload functions IMMEDIATELY (before plupload initializes)
            // This intercepts plupload's internal FormData creation to inject watermark IDs
            (function() {
                if (typeof plupload !== 'undefined') {
                    // Patch plupload.extend (used to merge args with multipart_params)
                    if (plupload.extend && !plupload.extend._uwPatched) {
                        const originalExtend = plupload.extend;
                        plupload.extend = function(target) {
                            const result = originalExtend.apply(this, arguments);
                            
                            // Check if merging multipart_params and inject toggle state + watermark IDs from localStorage
                            if (result && typeof result === 'object' && 'ultimate_watermark_auto_apply' in result) {
                                const toggleState = localStorage.getItem('ultimate_watermark_toggle_state');
                                if (toggleState === '1') {
                                    result['ultimate_watermark_auto_apply'] = '1';
                                }
                                try {
                                    const savedIds = localStorage.getItem('ultimate_watermark_selected_ids');
                                    if (savedIds) {
                                        const parsed = JSON.parse(savedIds);
                                        if (Array.isArray(parsed) && parsed.length > 0) {
                                            result['ultimate_watermark_ids'] = parsed.join(',');
                                        }
                                    }
                                } catch (e) {
                                    // Silent fail
                                }
                            }
                            
                            return result;
                        };
                        plupload.extend._uwPatched = true;
                    }
                    
                    // Patch plupload.each (used to iterate over merged params when creating FormData)
                    if (plupload.each && !plupload.each._uwPatched) {
                        const originalEach = plupload.each;
                        plupload.each = function(obj, callback) {
                            // Check if iterating over multipart_params and inject toggle state + watermark IDs
                            if (obj && typeof obj === 'object' && 'ultimate_watermark_auto_apply' in obj) {
                                const toggleState = localStorage.getItem('ultimate_watermark_toggle_state');
                                if (toggleState === '1') {
                                    obj['ultimate_watermark_auto_apply'] = '1';
                                }
                                try {
                                    const savedIds = localStorage.getItem('ultimate_watermark_selected_ids');
                                    if (savedIds) {
                                        const parsed = JSON.parse(savedIds);
                                        if (Array.isArray(parsed) && parsed.length > 0) {
                                            obj['ultimate_watermark_ids'] = parsed.join(',');
                                        }
                                    }
                                } catch (e) {
                                    // Silent fail
                                }
                            }
                            return originalEach.apply(this, arguments);
                        };
                        plupload.each._uwPatched = true;
                    }
                }
            })();
            
            // Initialize on document ready
            $(document).ready(function() {

                initToggle();
                
                // Also initialize when media modal opens (for media popup)
                if (typeof wp !== 'undefined' && wp.media) {
                    wp.media.view.UploaderWindow = wp.media.view.UploaderWindow.extend({
                        ready: function() {
                            wp.media.view.UploaderWindow.__super__.ready.apply(this, arguments);
                            
                            // Initialize toggle if it exists in the uploader
                            setTimeout(function() {
                                initToggle();
                                
                                // Hook into plupload to send toggle state and selected IDs
                                if (this.uploader && this.uploader.uploader) {
                                    const uploaderInstance = this.uploader.uploader;
                                    
                                    // Use the global updateMultipartParamsForUploader function
                                    // Update params immediately when uploader is available
                                    if (typeof updateMultipartParamsForUploader === 'function') {
                                        updateMultipartParamsForUploader(uploaderInstance);
                                    }
                                    
                                    // Update when files are added
                                    uploaderInstance.bind('FilesAdded', function(up, files) {
                                        if (typeof updateMultipartParamsForUploader === 'function') {
                                            updateMultipartParamsForUploader(up);
                                        }
                                    });
                                    
                                    // Update right before upload (as fallback)
                                    uploaderInstance.bind('BeforeUpload', function(up, file) {
                                        if (typeof updateMultipartParamsForUploader === 'function') {
                                            updateMultipartParamsForUploader(up);
                                        }
                                        // Remove unnecessary call - already handled during initialization
                                        // window.ultimateWatermarkUpdateToggleState();
                                    });
                                }
                            }.bind(this), 100);
                        }
                    });
                }
            });
            
            // Store reference to all active plupload instances (initialize once)
            window.ultimateWatermarkUploaders = window.ultimateWatermarkUploaders || [];
            
            // Function to update multipart_params for any plupload instance
            function updateMultipartParamsForUploader(up) {
                if (!up || !up.settings) {
                    return;
                }
                
                // Check if toggle is ON
                const $toggle = $('#ultimate-watermark-auto-apply:checked, #ultimate-watermark-auto-apply-popup:checked');
                
                if ($toggle.length) {
                    // Ensure multipart_params exists
                    if (!up.settings.multipart_params) {
                        up.settings.multipart_params = {};
                    }
                    
                    // Set toggle state
                    up.settings.multipart_params['ultimate_watermark_auto_apply'] = '1';
                    
                    // Get selected watermark IDs from localStorage (primary source)
                    const selectedIds = [];
                    try {
                        const savedIds = localStorage.getItem('ultimate_watermark_selected_ids');
                        if (savedIds) {
                            const parsed = JSON.parse(savedIds);
                            if (Array.isArray(parsed) && parsed.length > 0) {
                                parsed.forEach(function(id) {
                                    if (id) {
                                        selectedIds.push(String(id));
                                    }
                                });
                            }
                        }
                    } catch (e) {
                        // Silent fail
                    }
                    
                    // Fallback: If localStorage empty, check checkboxes
                    if (selectedIds.length === 0) {
                        $('.uw-watermark-checkbox:checked').each(function() {
                            const id = $(this).val() || $(this).data('watermark-id');
                            if (id) {
                                selectedIds.push(String(id));
                            }
                        });
                        
                        // Update localStorage with checkbox values
                        if (selectedIds.length > 0) {
                            try {
                                localStorage.setItem('ultimate_watermark_selected_ids', JSON.stringify(selectedIds));
                            } catch (e) {
                                // Silent fail
                            }
                        }
                    }
                    
                    // Set watermark IDs parameter
                    const idsString = selectedIds.length > 0 ? selectedIds.join(',') : '';
                    if (up.settings.multipart_params) {
                        delete up.settings.multipart_params['ultimate_watermark_ids'];
                        if (idsString) {
                            up.settings.multipart_params['ultimate_watermark_ids'] = String(idsString);
                        }
                    }
                } else {
                    // Toggle is OFF - ensure params are not set
                    if (up.settings.multipart_params) {
                        delete up.settings.multipart_params['ultimate_watermark_auto_apply'];
                        delete up.settings.multipart_params['ultimate_watermark_ids'];
                    }
                }
            }
            
            // Hook into plupload events globally (works for media popup)
            $(document).on('plupload:init', function(e, uploader) {
                // Store reference to this uploader instance
                if (window.ultimateWatermarkUploaders.indexOf(uploader) === -1) {
                    window.ultimateWatermarkUploaders.push(uploader);
                }
                
                // Update params when uploader is initialized
                updateMultipartParamsForUploader(uploader);
                
                // Also update when files are added (before upload starts)
                uploader.bind('FilesAdded', function(up, files) {
                    updateMultipartParamsForUploader(up);
                });
                
                // Also update right before upload (backup - primary injection is via monkey-patched plupload.extend/each)
                uploader.bind('BeforeUpload', function(up, file) {
                    updateMultipartParamsForUploader(up);
                });
            });
            
            // Listen for checkbox changes to update params in real-time
            $(document).on('ultimate-watermark-selection-changed', function() {
                // Update all known plupload instances
                if (window.ultimateWatermarkUploaders && window.ultimateWatermarkUploaders.length > 0) {
                    window.ultimateWatermarkUploaders.forEach(function(up) {
                        if (up && up.settings) {
                            updateMultipartParamsForUploader(up);
                        }
                    });
                }
            });
            
            // Fallback: Watch for DOM changes (for dynamically added toggles in media popup)
            if (typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            const $toggle = $('#ultimate-watermark-auto-apply');
                            if ($toggle.length && !$toggle.data('ultimate-watermark-initialized')) {
                                $toggle.data('ultimate-watermark-initialized', true);
                                initToggle();
                            }
                        }
                    });
                });
                
                $(document).ready(function() {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                });
            }
            
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Process uploaded image for automatic watermarking
     */
    public function processUploadedImage(array $upload, string $context): array
    {
        // Only process in 'upload' context (not during import, etc.)
        if ($context !== 'upload') {
            return $upload;
        }

        // Check if auto-apply is enabled
        $auto_apply_enabled = false;
        
        // Check POST data first (this should be set by the upload form)
        if (isset($_POST['ultimate_watermark_auto_apply']) && $_POST['ultimate_watermark_auto_apply'] === '1') {
            $auto_apply_enabled = true;
        } else {
            return $upload;
        }

        // Check if it's an image
        if (!wp_attachment_is_image($upload['file'])) {
            return $upload;
        }

        // DON'T apply watermarks here - just return the upload
        // Watermarks will be applied later by RestApiIntegration
        // This ensures backup is created BEFORE any watermarking
        
        return $upload;
    }

    /**
     * Add toggle state to plupload upload parameters
     * This is the most reliable method to pass toggle state to uploads
     */
    public function addToggleToUploadParams(array $params): array
    {
        // ALWAYS include the toggle param (even as '0') so that:
        // 1. The plupload monkey-patches can detect it and inject watermark IDs
        // 2. JavaScript can dynamically update it to '1' when toggle is ON
        // Previously only added when option was '1', which meant on page load with default '0'
        // the param was missing entirely, breaking the monkey-patch detection.
        $option_value = get_option('ultimate_watermark_auto_apply_toggle', '0');
        $params['ultimate_watermark_auto_apply'] = ($option_value === '1') ? '1' : '0';
        
        // NOTE: We don't set ultimate_watermark_ids here because this filter runs on page load,
        // not per-upload. JavaScript will set it dynamically in multipart_params before upload.
        
        return $params;
    }

    /**
     * Handle AJAX request to temporarily store selected watermark IDs
     * This allows JavaScript to pass selected IDs to PHP before upload starts
     * Same pattern as the toggle - JavaScript stores it, PHP reads it via upload_post_params filter
     */
    public function handleSetTempSelectedIds(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ultimate_watermark_temp_ids')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }
        
        // Store the IDs in a temporary option (one-time use, read by upload_post_params filter)
        $ids_string = isset($_POST['ids']) ? sanitize_text_field($_POST['ids']) : '';
        
        // Always log for debugging
        error_log('Ultimate Watermark: handleSetTempSelectedIds - Received IDs: [' . $ids_string . ']');
        
        update_option('ultimate_watermark_temp_selected_ids', $ids_string, false);
        
        // Verify it was stored
        $stored = get_option('ultimate_watermark_temp_selected_ids', '');
        error_log('Ultimate Watermark: handleSetTempSelectedIds - Stored and verified: [' . $stored . ']');
        
        wp_send_json_success(['message' => 'IDs stored temporarily', 'ids' => $ids_string, 'stored' => $stored]);
    }

    /**
     * Process uploaded image using prefilter hook
     */
    public function processUploadedImagePrefilter(array $file): array
    {
        
        return $file;
    }
    
    /**
     * Process new attachment after upload
     */
    public function markForWatermarking(int $attachment_id): void
    {
        // Check if auto-apply is enabled via toggle
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
        
        // If toggle is OFF, don't apply watermarks
        if (!$auto_apply_enabled) {
            return;
        }
        
        // CRITICAL: Capture parent post_id if image is uploaded to a page/post
        // This is needed for rule checking - when watermark is set for "page" post type,
        // we need to check the parent page, not the attachment itself
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
        
        // Mark this attachment for watermarking only if toggle is ON
        update_post_meta($attachment_id, '_ulwm_watermarked', true);
        
        // Reset the toggle after use to prevent accidental watermarking of subsequent uploads
        update_option('ultimate_watermark_auto_apply_toggle', '0');
        
        return;
    }

    /**
     * Apply watermark to specific attachment size
     */
    private function applyWatermarkToAttachmentSize(int $attachment_id, int $watermark_id, string $size): bool
    {
        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return false;
        }

        // Get the image path for the specific size
        $image_path = $this->getImagePathForSize($attachment_id, $size);
        if (!$image_path || !file_exists($image_path)) {
            // If full size might be a scaled image in WordPress, try resolving the scaled variant
            if ($size === 'full') {
                $alt = $this->getScaledImagePathIfExists($attachment_id);
                if ($alt && file_exists($alt)) {
                    $image_path = $alt;
                } else {
                    // Try WebP/AVIF variants if original not present
                    $variant = $this->findExistingVariantPathForAttachment($attachment_id, $size);
                    if ($variant) {
                        $image_path = $variant;
                    } else {
                        return false;
                    }
                }
            } else {
                // Try WebP/AVIF variants if original not present
                $variant = $this->findExistingVariantPathForAttachment($attachment_id, $size);
                if ($variant) {
                    $image_path = $variant;
                } else {
                    return false;
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
                // Apply to alternative format variants (e.g., .webp/.avif) if present
                foreach ($this->getAlternativeFormatPaths($image_path) as $variantPath) {
                    if (file_exists($variantPath) && is_writable($variantPath)) {
                        \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($variantPath, $watermark_id, $variantPath);
                    }
                }
                // Track watermark usage for this specific size (count once)
                WatermarkUsageTracker::incrementUsage($watermark_id, $attachment_id, $size);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get image path for specific size
     */
    private function getImagePathForSize(int $attachment_id, string $size): ?string
    {
        if ($size === 'full') {
            return get_attached_file($attachment_id);
        }
        
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata || !isset($metadata['sizes'][$size]['file'])) {
            return null;
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/' . $metadata['sizes'][$size]['file'];
        
        return file_exists($file_path) ? $file_path : null;
    }

    // Resolve WP big image scaled path if present
    private function getScaledImagePathIfExists(int $attachment_id): ?string
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata || empty($metadata['file'])) {
            return null;
        }
        $upload_dir = wp_upload_dir();
        $scaled_path = $upload_dir['basedir'] . '/' . $metadata['file'];
        return file_exists($scaled_path) ? $scaled_path : null;
    }

    // Given one full path, return the alternate counterpart (original vs scaled)
    private function getAlternateFullImagePath(int $attachment_id, string $currentFullPath): ?string
    {
        $upload_dir = wp_upload_dir();
        $basedir = trailingslashit($upload_dir['basedir']);
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata) { return null; }

        // If current path equals metadata file (scaled), alternate is original_image
        $metaFile = !empty($metadata['file']) ? $basedir . $metadata['file'] : null;
        $originalRel = !empty($metadata['original_image']) ? dirname($metadata['file']) . '/' . $metadata['original_image'] : null;
        $originalPath = $originalRel ? $basedir . $originalRel : null;

        if ($metaFile && realpath($currentFullPath) === realpath($metaFile)) {
            return ($originalPath && file_exists($originalPath)) ? $originalPath : null;
        }
        // If current is original, alternate is metaFile (scaled)
        if ($metaFile && file_exists($metaFile)) {
            return $metaFile;
        }
        return null;
    }

    // Return alternative format files (same basename, different extension)
    private function getAlternativeFormatPaths(string $path): array
    {
        $candidates = [];
        $info = pathinfo($path);
        if (empty($info['dirname']) || empty($info['filename'])) { return $candidates; }
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

    // Find existing variant path for an attachment/size if the canonical file is missing
    private function findExistingVariantPathForAttachment(int $attachment_id, string $size): ?string
    {
        $primary = $this->getImagePathForSize($attachment_id, $size);
        if ($primary && file_exists($primary)) { return $primary; }
        if ($size === 'full') {
            $alt = $this->getScaledImagePathIfExists($attachment_id);
            if ($alt && file_exists($alt)) { return $alt; }
        }
        // Try sibling alternative formats based on where the canonical would be
        if ($primary) {
            foreach ($this->getAlternativeFormatPaths($primary) as $variant) {
                if (file_exists($variant)) { return $variant; }
            }
        }
        return null;
    }

    /**
     * Apply watermark to file sequentially (for stacking multiple watermarks)
     */
    private function applyWatermarkToFileSequentially(string $file_path, array $watermark, int $attachment_id, string $size): bool
    {
        
        if (!file_exists($file_path)) {
            return false;
        }

        try {
            $watermark_id = $watermark['id'] ?? 0;
            
            // Check file permissions
            if (!is_writable($file_path)) {
                return false;
            }
            
            // Apply watermark using the same service as manual watermarking
            $success = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermark($file_path, $watermark);
            
            if ($success) {
                // Track watermark usage
                WatermarkUsageTracker::incrementUsage($watermark_id, $attachment_id);
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Apply watermark to file
     */
    private function applyWatermarkToFile(string $file_path, array $watermark): bool
    {
        
        if (!file_exists($file_path)) {
            return false;
        }

        // Create temporary watermarked file
        $temp_dir = wp_upload_dir()['basedir'] . '/temp-watermark';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $file_info = pathinfo($file_path);
        $temp_file = $temp_dir . '/' . uniqid() . '_watermarked.' . $file_info['extension'];

        // Apply watermark using WatermarkService
        try {
            $success = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermark($file_path, $watermark, $temp_file);
            
            if ($success && file_exists($temp_file)) {
                // Security: Validate temp file path
                $upload_dir = wp_upload_dir();
                $normalized_temp = wp_normalize_path($temp_file);
                if (strpos($normalized_temp, $upload_dir['basedir']) === 0 && is_file($normalized_temp)) {
                    // Replace original with watermarked version
                    copy($temp_file, $file_path);
                    unlink($temp_file); // Clean up temp file
                    return true;
                }
            }
        } catch (\Exception $e) {
            // Silent fail for production
        }

        return false;
    }

    /**
     * Handle AJAX automatic watermarking
     */
    public function handleAutomaticWatermarking(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_media')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        $attachment_ids = array_map('absint', $_POST['attachment_ids'] ?? []);

        if (!$watermark_id || empty($attachment_ids)) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'ultimate-watermark')]);
            return;
        }

        // Verify watermark exists and is active
        if (!WatermarkHelper::isWatermarkActive($watermark_id)) {
            wp_send_json_error(['message' => __('Watermark not found or inactive.', 'ultimate-watermark')]);
            return;
        }

        // Process attachments
        $results = [];
        foreach ($attachment_ids as $attachment_id) {
            $success = $this->applyWatermarkToAttachment($attachment_id, $watermark_id);
            $results[] = [
                'id' => $attachment_id,
                'success' => $success
            ];
        }

        wp_send_json_success([
            'message' => 'Automatic watermarking completed',
            'results' => $results
        ]);
    }

    /**
     * Handle AJAX remove watermarking
     */
    public function handleRemoveWatermarking(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_media')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $attachment_ids = array_map('absint', $_POST['attachment_ids'] ?? []);

        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => __('No attachments selected.', 'ultimate-watermark')]);
            return;
        }

        // Process attachments
        $results = [];
        foreach ($attachment_ids as $attachment_id) {
            $success = $this->removeWatermarkFromAttachment($attachment_id);
            $results[] = [
                'id' => $attachment_id,
                'success' => $success
            ];
        }

        wp_send_json_success([
            'message' => 'Watermark removal completed',
            'results' => $results
        ]);
    }

    /**
     * Add JavaScript to move toggle after browse button
     * This needs to run on ALL admin pages to catch media popups from page/post editors
     */
    public function addUploadToggleScript(): void
    {
        // Remove screen restriction - we need this on all admin pages for media popup support
        // Media popups can open from any admin page (page editor, post editor, etc.)
        ?>
        <script>
        (function($) {
            'use strict';
            
            // === Essential toggle + plupload integration ===
            // These functions are needed on ALL admin pages (not just upload page)
            // because media popups can open from any admin page (post editor, page editor, etc.)
            // Guard: if addUploadToggle already defined these (on upload page), skip.
            if (!window.initToggle) {
                
                window.ultimateWatermarkUpdateToggleState = function(forceUpdate) {
                    forceUpdate = forceUpdate || false;
                    var $toggle = $('#ultimate-watermark-auto-apply:checked, #ultimate-watermark-auto-apply-popup:checked');
                    if ($toggle.length === 0) $toggle = $('#ultimate-watermark-auto-apply, #ultimate-watermark-auto-apply-popup').first();
                    if ($toggle.length === 0) return;
                    var isEnabled = $toggle.is(':checked');
                    var currentState = localStorage.getItem('ultimate_watermark_toggle_state');
                    if (!forceUpdate && currentState === (isEnabled ? '1' : '0')) return;
                    localStorage.setItem('ultimate_watermark_toggle_state', isEnabled ? '1' : '0');
                    sessionStorage.setItem('ultimate_watermark_auto_apply', isEnabled ? '1' : '0');
                    $.ajax({
                        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        async: true,
                        data: {
                            action: 'ultimate_watermark_update_toggle_state',
                            enabled: isEnabled ? '1' : '0',
                            nonce: '<?php echo wp_create_nonce('ultimate_watermark_toggle'); ?>'
                        }
                    });
                };
                
                window.loadToggleState = function() {
                    try {
                        var saved = localStorage.getItem('ultimate_watermark_toggle_state');
                        var $toggles = $('#ultimate-watermark-auto-apply, #ultimate-watermark-auto-apply-popup');
                        var $infos = $('#ultimate-watermark-info, #ultimate-watermark-info-popup');
                        if (saved === '1') {
                            $toggles.prop('checked', true);
                            $infos.show();
                        } else {
                            $toggles.prop('checked', false);
                            $infos.hide();
                        }
                    } catch (e) {}
                };
                
                window.saveWatermarkSelections = function(sourceInstance) {
                    var selectedIds = [];
                    var seenIds = {};
                    var $checkboxesToCheck;
                    if (sourceInstance) {
                        $checkboxesToCheck = sourceInstance.find('.uw-watermark-checkbox');
                    } else {
                        $checkboxesToCheck = $('#ultimate-watermark-info:visible, #ultimate-watermark-info-popup:visible').find('.uw-watermark-checkbox');
                        if ($checkboxesToCheck.length === 0) {
                            $checkboxesToCheck = $('#ultimate-watermark-info').find('.uw-watermark-checkbox');
                        }
                    }
                    $checkboxesToCheck.each(function() {
                        var $cb = $(this);
                        if ($cb.is(':checked')) {
                            var wid = String($cb.data('watermark-id'));
                            if (wid && !seenIds[wid]) {
                                selectedIds.push(wid);
                                seenIds[wid] = true;
                            }
                        }
                    });
                    localStorage.setItem('ultimate_watermark_selected_ids', JSON.stringify(selectedIds));
                    $(document).trigger('ultimate-watermark-selection-changed');
                    var savedIds = selectedIds.map(String);
                    $('.uw-watermark-checkbox').each(function() {
                        var $cb = $(this);
                        $cb.prop('checked', savedIds.indexOf(String($cb.data('watermark-id'))) !== -1);
                    });
                };
                
                window.loadWatermarkSelections = function() {
                    try {
                        var saved = localStorage.getItem('ultimate_watermark_selected_ids');
                        if (saved) {
                            var ids = JSON.parse(saved).map(String);
                            $('.uw-watermark-checkbox').each(function() {
                                $(this).prop('checked', ids.indexOf(String($(this).data('watermark-id'))) !== -1);
                            });
                        } else {
                            $('.uw-watermark-checkbox').prop('checked', false);
                        }
                    } catch (e) {
                        $('.uw-watermark-checkbox').prop('checked', false);
                    }
                };
                
                window.initToggle = function() {
                    var $toggle = $('#ultimate-watermark-auto-apply, #ultimate-watermark-auto-apply-popup');
                    var $info = $('#ultimate-watermark-info, #ultimate-watermark-info-popup');
                    if ($toggle.length === 0) return;
                    window.loadToggleState();
                    $toggle.each(function() {
                        var $thisToggle = $(this);
                        var toggleId = $thisToggle.attr('id');
                        var isPopup = toggleId && toggleId.indexOf('-popup') !== -1;
                        var $thisInfo = isPopup ? $('#ultimate-watermark-info-popup') : $('#ultimate-watermark-info');
                        if ($thisInfo.length === 0) return;
                        var $checkboxes = $thisInfo.find('.uw-watermark-checkbox');
                        if ($checkboxes.length > 0 && !window.watermarkSelectionsLoaded) {
                            var wasHidden = !$thisInfo.is(':visible');
                            if (wasHidden) $thisInfo.show();
                            window.loadWatermarkSelections();
                            window.watermarkSelectionsLoaded = true;
                            if (wasHidden && !$thisToggle.is(':checked')) $thisInfo.hide();
                        }
                        $thisToggle.off('change.ultimate-watermark').on('change.ultimate-watermark', function() {
                            if ($(this).is(':checked')) {
                                $thisInfo.slideDown(200);
                                if (!window.watermarkSelectionsLoaded && $checkboxes.length > 0) {
                                    window.loadWatermarkSelections();
                                    window.watermarkSelectionsLoaded = true;
                                }
                            } else {
                                $thisInfo.slideUp(200);
                            }
                            window.ultimateWatermarkUpdateToggleState();
                        });
                        function setVisibility() {
                            if ($thisToggle.is(':checked')) {
                                $thisInfo.show().css('display', '');
                            } else {
                                $thisInfo.hide();
                            }
                        }
                        setVisibility();
                        setTimeout(setVisibility, 50);
                        setTimeout(setVisibility, 200);
                        $checkboxes.off('change.uw-selection').on('change.uw-selection', function() {
                            setTimeout(function() {
                                window.saveWatermarkSelections($thisInfo);
                            }, 50);
                        });
                        $thisInfo.find('.uw-select-all').off('click').on('click', function() {
                            $thisInfo.find('.uw-watermark-checkbox').prop('checked', true);
                            setTimeout(function() { window.saveWatermarkSelections($thisInfo); }, 10);
                        });
                        $thisInfo.find('.uw-deselect-all').off('click').on('click', function() {
                            $thisInfo.find('.uw-watermark-checkbox').prop('checked', false);
                            setTimeout(function() { window.saveWatermarkSelections($thisInfo); }, 10);
                        });
                    });
                    window.ultimateWatermarkUpdateToggleState(true);
                };
                
                // Plupload integration - inject toggle state + watermark IDs into upload params
                window.ultimateWatermarkUploaders = window.ultimateWatermarkUploaders || [];
                
                function updateMultipartParamsForUploader(up) {
                    if (!up || !up.settings) return;
                    var $toggle = $('#ultimate-watermark-auto-apply:checked, #ultimate-watermark-auto-apply-popup:checked');
                    if ($toggle.length) {
                        if (!up.settings.multipart_params) up.settings.multipart_params = {};
                        up.settings.multipart_params['ultimate_watermark_auto_apply'] = '1';
                        var selectedIds = [];
                        try {
                            var savedIds = localStorage.getItem('ultimate_watermark_selected_ids');
                            if (savedIds) {
                                var parsed = JSON.parse(savedIds);
                                if (Array.isArray(parsed) && parsed.length > 0) {
                                    parsed.forEach(function(id) { if (id) selectedIds.push(String(id)); });
                                }
                            }
                        } catch (e) {}
                        if (selectedIds.length === 0) {
                            $('.uw-watermark-checkbox:checked').each(function() {
                                var id = $(this).val() || $(this).data('watermark-id');
                                if (id) selectedIds.push(String(id));
                            });
                        }
                        var idsString = selectedIds.join(',');
                        delete up.settings.multipart_params['ultimate_watermark_ids'];
                        if (idsString) up.settings.multipart_params['ultimate_watermark_ids'] = String(idsString);
                    } else {
                        if (up.settings.multipart_params) {
                            delete up.settings.multipart_params['ultimate_watermark_auto_apply'];
                            delete up.settings.multipart_params['ultimate_watermark_ids'];
                        }
                    }
                }
                
                $(document).on('plupload:init', function(e, uploader) {
                    if (window.ultimateWatermarkUploaders.indexOf(uploader) === -1) {
                        window.ultimateWatermarkUploaders.push(uploader);
                    }
                    updateMultipartParamsForUploader(uploader);
                    uploader.bind('FilesAdded', function(up) { updateMultipartParamsForUploader(up); });
                    uploader.bind('BeforeUpload', function(up) { updateMultipartParamsForUploader(up); });
                });
                
                $(document).on('ultimate-watermark-selection-changed', function() {
                    if (window.ultimateWatermarkUploaders) {
                        window.ultimateWatermarkUploaders.forEach(function(up) {
                            if (up && up.settings) updateMultipartParamsForUploader(up);
                        });
                    }
                });
                
                // Monkey-patch plupload to inject watermark IDs into FormData
                if (typeof plupload !== 'undefined') {
                    if (plupload.extend && !plupload.extend._uwPatched) {
                        var origExtend = plupload.extend;
                        plupload.extend = function(target) {
                            var result = origExtend.apply(this, arguments);
                            if (result && typeof result === 'object' && 'ultimate_watermark_auto_apply' in result) {
                                var toggleState = localStorage.getItem('ultimate_watermark_toggle_state');
                                if (toggleState === '1') {
                                    result['ultimate_watermark_auto_apply'] = '1';
                                }
                                try {
                                    var savedIds = localStorage.getItem('ultimate_watermark_selected_ids');
                                    if (savedIds) {
                                        var parsed = JSON.parse(savedIds);
                                        if (Array.isArray(parsed) && parsed.length > 0) {
                                            result['ultimate_watermark_ids'] = parsed.join(',');
                                        }
                                    }
                                } catch (e) {}
                            }
                            return result;
                        };
                        plupload.extend._uwPatched = true;
                    }
                    if (plupload.each && !plupload.each._uwPatched) {
                        var origEach = plupload.each;
                        plupload.each = function(obj, callback) {
                            if (obj && typeof obj === 'object' && 'ultimate_watermark_auto_apply' in obj) {
                                var toggleState = localStorage.getItem('ultimate_watermark_toggle_state');
                                if (toggleState === '1') {
                                    obj['ultimate_watermark_auto_apply'] = '1';
                                }
                                try {
                                    var savedIds = localStorage.getItem('ultimate_watermark_selected_ids');
                                    if (savedIds) {
                                        var parsed = JSON.parse(savedIds);
                                        if (Array.isArray(parsed) && parsed.length > 0) {
                                            obj['ultimate_watermark_ids'] = parsed.join(',');
                                        }
                                    }
                                } catch (e) {}
                            }
                            return origEach.apply(this, arguments);
                        };
                        plupload.each._uwPatched = true;
                    }
                }
                
            } // end if (!window.initToggle)
            
            // Inject toggle into media popup if it doesn't exist
            function injectToggleIntoMediaPopup() {
                // Check if toggle already exists (in visible area, not hidden footer)
                if ($('#ultimate-watermark-upload-toggle-popup').length || 
                    $('#ultimate-watermark-upload-toggle').closest('.uploader-inline, .plupload_dropbox, .upload-ui').length) {
                    return;
                }
                
                // Check if we're in a media modal uploader
                // Support both classic (.plupload_dropbox) and modern (.uploader-inline, .upload-ui) selectors
                const $uploadArea = $('.uploader-inline, .uploader-inline-content, .uploader-window-content, .uploader-editor-content, .plupload_dropbox');
                // Modern WP media modal uses .browser.button, classic uses .plupload-browse-button
                const $browseButton = $('.uploader-inline .browser, .plupload-browse-button, .upload-ui .browser').first();
                
                if ($uploadArea.length && $browseButton.length) {
                    // Try to find toggle from footer (hidden source)
                    let $toggle = $('#ultimate-watermark-upload-toggle-footer #ultimate-watermark-upload-toggle');
                    
                    // Fallback: try to find it elsewhere on the page
                    if ($toggle.length === 0) {
                        $toggle = $('#ultimate-watermark-upload-toggle').not('#ultimate-watermark-upload-toggle-popup');
                    }
                    
                    if ($toggle.length > 0) {
                        // Clone existing toggle
                        $toggle = $toggle.first().clone();
                        $toggle.attr('id', 'ultimate-watermark-upload-toggle-popup');
                        
                        // Ensure all IDs are unique within the clone
                        $toggle.find('[id], [for]').each(function() {
                            const $el = $(this);
                            const oldId = $el.attr('id') || $el.attr('for');
                            if (oldId && oldId.indexOf('-popup') === -1) {
                                const newId = oldId + '-popup';
                                if ($el.attr('id')) {
                                    $el.attr('id', newId);
                                }
                                if ($el.attr('for')) {
                                    $el.attr('for', newId);
                                }
                            }
                        });
                    } else {
                        // Toggle doesn't exist anywhere, skip injection
                        return;
                    }
                    
                    // Insert after browse button
                    $browseButton.after($toggle);
                    
                    // Style it
                    $toggle.css({
                        'margin': '20px auto 0 auto',
                        'position': 'relative',
                        'width': '250px',
                        'background': 'rgba(255, 255, 255, 0.95)',
                        'border': '1px solid #ddd',
                        'border-radius': '8px',
                        'box-shadow': '0 2px 8px rgba(0, 0, 0, 0.1)',
                        'padding': '15px',
                        'text-align': 'center',
                        'display': 'block',
                        'left': 'auto',
                        'right': 'auto'
                    });
                    
                    // Initialize the toggle functionality
                    if (window.initToggle) {
                        setTimeout(function() {
                            window.initToggle();
                        }, 100);
                    }
                }
            }
            
            // Move toggle after browse button (for upload page)
            function moveWatermarkToggle() {
                // Support both classic and modern selectors
                const $browseButton = $('.plupload-browse-button, .uploader-inline .browser, .upload-ui .browser').first();
                const $watermarkToggle = $('#ultimate-watermark-upload-toggle');
                const $uploadArea = $('.plupload_dropbox, .uploader-inline, .upload-ui').first();
                
                if ($browseButton.length && $watermarkToggle.length && $uploadArea.length) {
                    // Move the watermark toggle inside the upload area, after the browse button
                    $browseButton.after($watermarkToggle);
                    
                    // Style it to fit nicely within the upload area
                    $watermarkToggle.css({
                        'margin': '20px auto 0 auto',
                        'position': 'relative',
                        'width': '250px',
                        'background': 'rgba(255, 255, 255, 0.95)',
                        'border': '1px solid #ddd',
                        'border-radius': '8px',
                        'box-shadow': '0 2px 8px rgba(0, 0, 0, 0.1)',
                        'padding': '15px',
                        'text-align': 'center',
                        'display': 'block',
                        'left': 'auto',
                        'right': 'auto'
                    });
                }
            }
            
            $(document).ready(function() {
                // Try to move immediately (for upload page)
                moveWatermarkToggle();
                
                // Inject into media popup when it opens
                if (typeof wp !== 'undefined' && wp.media) {
                    $(document).on('click', '.insert-media, .add_media', function() {
                        setTimeout(function() {
                            injectToggleIntoMediaPopup();
                            moveWatermarkToggle();
                        }, 500);
                    });
                    
                    // Also watch for media modal open event
                    wp.media.controller.Library = wp.media.controller.Library.extend({
                        activate: function() {
                            wp.media.controller.Library.__super__.activate.apply(this, arguments);
                            setTimeout(function() {
                                injectToggleIntoMediaPopup();
                                moveWatermarkToggle();
                            }, 500);
                        }
                    });
                }
                
                // Also try after delays in case plupload loads later
                setTimeout(moveWatermarkToggle, 500);
                setTimeout(injectToggleIntoMediaPopup, 1000);
                
                // Watch for DOM changes (in case plupload loads dynamically)
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            // Support both classic and modern selectors
                            const $browseButton = $('.plupload-browse-button, .uploader-inline .browser, .upload-ui .browser').first();
                            const $watermarkToggle = $('#ultimate-watermark-upload-toggle, #ultimate-watermark-upload-toggle-popup');
                            
                            // Move toggle if it exists
                            if ($browseButton.length && $watermarkToggle.length && 
                                !$browseButton.next().is($watermarkToggle)) {
                                moveWatermarkToggle();
                            }
                            
                            // Inject toggle if it doesn't exist in media popup
                            if ($browseButton.length && $watermarkToggle.length === 0) {
                                injectToggleIntoMediaPopup();
                            }
                        }
                    });
                });
                
                // Start observing
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Add toggle HTML to admin footer (hidden, will be moved/injected by JS)
     * This ensures the toggle HTML is always available for media popup
     */
    public function addUploadToggleToFooter(): void
    {
        // Don't show on media edit page
        global $pagenow;
        if ($pagenow === 'post.php' && isset($_GET['action']) && $_GET['action'] === 'edit') {
            $post = get_post(absint($_GET['post'] ?? 0));
            if ($post && $post->post_type === 'attachment') {
                return; // Skip on media edit page
            }
        }
        
        // Only add if toggle doesn't already exist on the page
        // Check this via JavaScript to avoid duplicate output
        ?>
        <div id="ultimate-watermark-upload-toggle-footer" style="display: none !important;">
            <?php $this->addUploadToggle(); ?>
        </div>
        <?php
    }

    /**
     * Add confirmation modal to media library
     */
    public function addConfirmationModal(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'upload') {
            return;
        }
        ?>
        <!-- Watermark Confirmation Modal -->
        <div id="watermark-confirmation-modal" class="confirmation-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title"><?php esc_html_e('Confirm Action', 'ultimate-watermark'); ?></h3>
                    <button type="button" class="modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="modal-message"><?php esc_html_e('Are you sure you want to proceed?', 'ultimate-watermark'); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">
                        <?php esc_html_e('Cancel', 'ultimate-watermark'); ?>
                    </button>
                    <button type="button" class="btn btn-danger modal-confirm">
                        <?php esc_html_e('Confirm', 'ultimate-watermark'); ?>
                    </button>
                </div>
            </div>
        </div>

        <style>
        /* Confirmation Modal Styles */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .confirmation-modal .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .confirmation-modal .modal-content {
            position: relative;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .confirmation-modal .modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .confirmation-modal .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .confirmation-modal .modal-close {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            color: #6c757d;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .confirmation-modal .modal-close:hover {
            background: #f8f9fa;
            color: #495057;
        }

        .confirmation-modal .modal-body {
            padding: 24px;
        }

        .confirmation-modal .modal-body p {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
            color: #495057;
        }

        .confirmation-modal .modal-footer {
            padding: 16px 24px 20px;
            border-top: 1px solid #e1e5e9;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .confirmation-modal .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .confirmation-modal .btn-secondary {
            background: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
        }

        .confirmation-modal .btn-secondary:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .confirmation-modal .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }

        .confirmation-modal .btn-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }

        .confirmation-modal .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        </style>
        <?php
    }

    /**
     * Clean up watermark usage when watermark is deleted
     */
    public function cleanupWatermarkUsage(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'ultimate_watermark') {
            return;
        }

        WatermarkUsageTracker::cleanupWatermarkUsage($post_id);
    }

    /**
     * Clean up image usage when image is deleted
     */
    public function cleanupImageUsage(int $attachment_id): void
    {
        WatermarkUsageTracker::cleanupImageUsage($attachment_id);
    }
    
    /**
     * Initialize watermark protection to prevent regeneration
     */
    private function initWatermarkProtection(): void
    {
        // No longer needed - we let WordPress generate thumbnails naturally
    }
    
    /**
     * Create backup for watermarking
     */
    private function createBackupForWatermarking(int $attachment_id): void
    {
        
        // Get all registered image sizes
        $image_sizes = get_intermediate_image_sizes();
        $image_sizes[] = 'full';
        
        // Create backups of ALL original images BEFORE any watermarking
        $backup_paths = [];
        foreach ($image_sizes as $size) {
            $original_path = $this->getImagePathForSize($attachment_id, $size);
            if ($original_path && file_exists($original_path)) {
                $backup_paths[$size] = $original_path;
            }
        }
        
        // Create backup for all sizes at once
        if (!empty($backup_paths)) {
            $full_size_path = $backup_paths['full'] ?? reset($backup_paths);
            
            $result = BackupManager::createBackup($full_size_path, $attachment_id, 0, $backup_paths);
            if ($result) {
                // Backup created successfully
            }
        } else {
            // No image paths found for backup
        }
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
                $watermark_id = isset($watermark['ID']) ? $watermark['ID'] : $watermark['id'];
                
                // Verify the size rule one more time (double-check)
                if (!WatermarkHelper::shouldApplyWatermarkByImageSize($watermark, $size)) {
                    continue;
                }
                
                // Apply watermark to this specific size
                $this->applyWatermarkToAttachmentSize($attachment_id, $watermark_id, $size);
            }
        }
    }
}
