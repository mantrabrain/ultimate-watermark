<?php

namespace MantraBrain\UltimateWatermark\Admin;

use MantraBrain\UltimateWatermark\Utils\WatermarkHelper;
use MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker;
use MantraBrain\UltimateWatermark\Watermark\WatermarkManager;

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
        
        // Add upload toggle
        add_action('post-upload-ui', [$this, 'addUploadToggle']);
        
        // Hook into upload process - try multiple hooks for better compatibility
        add_action('wp_handle_upload', [$this, 'processUploadedImage'], 10, 2);
        add_filter('wp_handle_upload_prefilter', [$this, 'processUploadedImagePrefilter']);
        add_action('add_attachment', [$this, 'processNewAttachment']);
        
        // Add JavaScript to move the toggle after the browse button
        add_action('admin_footer', [$this, 'addUploadToggleScript']);
        
        // Add confirmation modal to media library
        add_action('admin_footer', [$this, 'addConfirmationModal']);
        
        // MediaLibraryIntegration hooks registered
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
        // Get active manual watermarks with rule filtering (for media library context)
        $manual_watermarks = WatermarkHelper::getActiveManualWatermarks('manual', null, 'full');
        
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

        // Check if backup exists
        $backup_path = $this->getBackupPath($file_path);
        if (!$backup_path || !file_exists($backup_path)) {
            // No backup available, cannot restore
            return false;
        }

        try {
            // Restore original from backup
            if (copy($backup_path, $file_path)) {
                // Update attachment metadata
                wp_generate_attachment_metadata($attachment_id, $file_path);
                return true;
            }
        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error removing watermark from attachment ' . $attachment_id . ': ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Get backup file path for an image
     */
    private function getBackupPath(string $file_path): ?string
    {
        $file_info = pathinfo($file_path);
        $backup_dir = $file_info['dirname'] . '/watermark-backups';
        $backup_filename = $file_info['filename'] . '_original.' . $file_info['extension'];
        
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        return file_exists($backup_path) ? $backup_path : null;
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
     */
    private function applyWatermarkToAttachment(int $attachment_id, int $watermark_id): bool
    {
        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return false;
        }

        // Get original image path
        $original_path = get_attached_file($attachment_id);
        if (!$original_path || !file_exists($original_path)) {
            return false;
        }

        // Create watermarked version
        $upload_dir = wp_upload_dir();
        $watermarked_dir = $upload_dir['basedir'] . '/watermarked';
        
        if (!file_exists($watermarked_dir)) {
            wp_mkdir_p($watermarked_dir);
        }

        $file_info = pathinfo($original_path);
        $watermarked_filename = $file_info['filename'] . '_watermarked.' . $file_info['extension'];
        $watermarked_path = $watermarked_dir . '/' . $watermarked_filename;

        // Apply watermark using WatermarkService with watermark ID
        try {
            $success = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermarkById($original_path, $watermark_id, $watermarked_path);
            
            if ($success && file_exists($watermarked_path)) {
                // Replace original with watermarked version
                copy($watermarked_path, $original_path);
                unlink($watermarked_path); // Clean up temp file
                
                // Update attachment metadata
                wp_generate_attachment_metadata($attachment_id, $original_path);
                
                return true;
            }
        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error applying watermark to attachment ' . $attachment_id . ': ' . $e->getMessage());
        }

        return false;
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
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        $attachment_ids = array_map('absint', $_POST['attachment_ids'] ?? []);

        if (!$watermark_id || empty($attachment_ids)) {
            wp_send_json_error('Invalid parameters');
            return;
        }

        // Verify watermark exists and is active
        if (!WatermarkHelper::isWatermarkActive($watermark_id)) {
            wp_send_json_error('Watermark not found or inactive');
            return;
        }

        // Process attachments - pass watermark ID directly to processors
        $results = [];
        foreach ($attachment_ids as $attachment_id) {
            $success = $this->applyWatermarkToAttachment($attachment_id, $watermark_id);
            $results[] = [
                'id' => $attachment_id,
                'success' => $success
            ];
        }

        wp_send_json_success([
            'message' => 'Watermarking completed',
            'results' => $results
        ]);
    }

    /**
     * Add upload toggle to upload page
     */
    public function addUploadToggle(): void
    {
        // Get active automatic watermarks
        $automatic_watermarks = WatermarkHelper::getActiveAutomaticWatermarks();
        
        if (empty($automatic_watermarks)) {
            return; // No automatic watermarks available
        }

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
                               style="opacity: 0; width: 0; height: 0; position: absolute;" checked>
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
                padding: 8px 12px;
                background: #e7f3ff;
                border: 1px solid #b3d9ff;
                border-radius: 4px;
                font-size: 12px;
                color: #0066cc;
                display: none;
            ">
                <?php 
                $watermark_count = count($automatic_watermarks);
                if ($watermark_count > 0) {
                    echo '<div style="margin-bottom: 8px;">';
                    if ($watermark_count > 1) {
                        printf(
                            esc_html__('All %d automatic watermarks will be applied to uploaded images:', 'ultimate-watermark'),
                            $watermark_count
                        );
                    } else {
                        esc_html_e('Automatic watermark will be applied to uploaded images:', 'ultimate-watermark');
                    }
                    echo '</div>';
                    
                    echo '<div style="margin-left: 10px;">';
                    foreach ($automatic_watermarks as $watermark) {
                        $watermark_type = $watermark['type'] === 'text' ? __('Text', 'ultimate-watermark') : __('Image', 'ultimate-watermark');
                        $watermark_name = esc_html($watermark['name']);
                        $edit_url = admin_url('admin.php?page=ultimate-watermark-add-watermark&ID=' . $watermark['id']);
                        
                        echo '<div style="margin-bottom: 4px;">';
                        echo '<a href="' . esc_url($edit_url) . '" target="_blank" style="color: #0066cc; text-decoration: none; font-weight: 500;">';
                        echo '• ' . $watermark_name . ' (' . $watermark_type . ')';
                        echo '</a>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    esc_html_e('No automatic watermarks available.', 'ultimate-watermark');
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
        jQuery(document).ready(function($) {
            const $toggle = $('#ultimate-watermark-auto-apply');
            const $info = $('#ultimate-watermark-info');
            
            // Store toggle state in sessionStorage for upload hooks
            function updateToggleState() {
                const isEnabled = $toggle.is(':checked');
                sessionStorage.setItem('ultimate_watermark_auto_apply', isEnabled ? '1' : '0');
                console.log('Ultimate Watermark: Toggle state updated to:', isEnabled);
            }
            
            // Show/hide info based on toggle state
            $toggle.on('change', function() {
                if ($(this).is(':checked')) {
                    $info.slideDown(200);
                } else {
                    $info.slideUp(200);
                }
                updateToggleState();
            });
            
            // Initialize state
            if ($toggle.is(':checked')) {
                $info.show();
            }
            updateToggleState();
            
            // Also update on page load
            updateToggleState();
        });
        </script>
        <?php
    }

    /**
     * Process uploaded image for automatic watermarking
     */
    public function processUploadedImage(array $upload, string $context): array
    {
        error_log('Ultimate Watermark: processUploadedImage called - Context: ' . $context);
        error_log('Ultimate Watermark: Upload file: ' . ($upload['file'] ?? 'N/A'));
        error_log('Ultimate Watermark: POST data: ' . print_r($_POST, true));
        
        // Only process in 'upload' context (not during import, etc.)
        if ($context !== 'upload') {
            error_log('Ultimate Watermark: Skipping - not upload context');
            return $upload;
        }

        // Check if auto-apply is enabled (check both POST and sessionStorage via AJAX)
        $auto_apply_enabled = false;
        
        // Check POST data first
        if (isset($_POST['ultimate_watermark_auto_apply']) && $_POST['ultimate_watermark_auto_apply'] === '1') {
            $auto_apply_enabled = true;
        }
        
        // If not in POST, check if we can determine from context
        if (!$auto_apply_enabled) {
            // For now, let's assume it's enabled if we're in upload context and no explicit disable
            // This is a fallback - the JavaScript should handle the real state
            $auto_apply_enabled = true;
            error_log('Ultimate Watermark: Using fallback auto-apply detection');
        }
        
        if (!$auto_apply_enabled) {
            error_log('Ultimate Watermark: Skipping - auto-apply not enabled');
            return $upload;
        }

        error_log('Ultimate Watermark: Auto-apply is enabled');

        // Check if it's an image
        if (!wp_attachment_is_image($upload['file'])) {
            error_log('Ultimate Watermark: Skipping - not an image');
            return $upload;
        }

        error_log('Ultimate Watermark: Processing image file');

        // Determine image size context
        $image_size = $this->determineImageSizeContext($upload['file']);
        
        // Get all active automatic watermarks with rule filtering
        $automatic_watermarks = WatermarkHelper::getActiveAutomaticWatermarks('upload', null, $image_size);
        error_log('Ultimate Watermark: Found ' . count($automatic_watermarks) . ' automatic watermarks (after rule filtering)');
        
        if (empty($automatic_watermarks)) {
            error_log('Ultimate Watermark: No automatic watermarks found');
            return $upload;
        }

        // Apply all automatic watermarks
        $watermarked = false;
        foreach ($automatic_watermarks as $watermark) {
            error_log('Ultimate Watermark: Applying watermark: ' . $watermark['name']);
            if ($this->applyWatermarkToFile($upload['file'], $watermark)) {
                $watermarked = true;
                error_log('Ultimate Watermark: Successfully applied watermark: ' . $watermark['name']);
            } else {
                error_log('Ultimate Watermark: Failed to apply watermark: ' . $watermark['name']);
            }
        }

        if ($watermarked) {
            // Update file size in upload array
            $upload['size'] = filesize($upload['file']);
            error_log('Ultimate Watermark: Watermarking completed successfully');
        } else {
            error_log('Ultimate Watermark: No watermarks were applied');
        }

        return $upload;
    }

    /**
     * Process uploaded image using prefilter hook
     */
    public function processUploadedImagePrefilter(array $file): array
    {
        error_log('Ultimate Watermark: processUploadedImagePrefilter called');
        error_log('Ultimate Watermark: File data: ' . print_r($file, true));
        error_log('Ultimate Watermark: POST data: ' . print_r($_POST, true));
        
        return $file;
    }

    /**
     * Process new attachment after upload
     */
    public function processNewAttachment(int $attachment_id): void
    {
        error_log('Ultimate Watermark: processNewAttachment called for ID: ' . $attachment_id);
        error_log('Ultimate Watermark: POST data: ' . print_r($_POST, true));
        
        // Check if auto-apply is enabled (for now, assume enabled if we reach this point)
        $auto_apply_enabled = true;
        
        // Check POST data first
        if (isset($_POST['ultimate_watermark_auto_apply']) && $_POST['ultimate_watermark_auto_apply'] === '1') {
            $auto_apply_enabled = true;
        }
        
        if (!$auto_apply_enabled) {
            error_log('Ultimate Watermark: Auto-apply not enabled in processNewAttachment');
            return;
        }
        
        error_log('Ultimate Watermark: Auto-apply is enabled in processNewAttachment');

        // Get attachment file path
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            error_log('Ultimate Watermark: File not found for attachment: ' . $attachment_id);
            return;
        }

        // Check if it's an image
        if (!wp_attachment_is_image($attachment_id)) {
            error_log('Ultimate Watermark: Attachment is not an image: ' . $attachment_id);
            return;
        }

        error_log('Ultimate Watermark: Processing attachment: ' . $file_path);

        // Determine image size context
        $image_size = $this->determineImageSizeContext($file_path);
        
        // Get all active automatic watermarks with rule filtering
        $automatic_watermarks = WatermarkHelper::getActiveAutomaticWatermarks('upload', $attachment_id, $image_size);
        error_log('Ultimate Watermark: Found ' . count($automatic_watermarks) . ' automatic watermarks (after rule filtering)');
        
        if (empty($automatic_watermarks)) {
            error_log('Ultimate Watermark: No automatic watermarks found');
            return;
        }

        // Apply all automatic watermarks
        $watermarked = false;
        foreach ($automatic_watermarks as $watermark) {
            error_log('Ultimate Watermark: Applying watermark: ' . $watermark['name']);
            if ($this->applyWatermarkToFile($file_path, $watermark)) {
                $watermarked = true;
                error_log('Ultimate Watermark: Successfully applied watermark: ' . $watermark['name']);
            } else {
                error_log('Ultimate Watermark: Failed to apply watermark: ' . $watermark['name']);
            }
        }

        if ($watermarked) {
            error_log('Ultimate Watermark: Watermarking completed successfully for attachment: ' . $attachment_id);
        } else {
            error_log('Ultimate Watermark: No watermarks were applied to attachment: ' . $attachment_id);
        }
    }

    /**
     * Apply watermark to file
     */
    private function applyWatermarkToFile(string $file_path, array $watermark): bool
    {
        error_log('Ultimate Watermark: applyWatermarkToFile called for: ' . $file_path);
        
        if (!file_exists($file_path)) {
            error_log('Ultimate Watermark: File does not exist: ' . $file_path);
            return false;
        }

        // Create temporary watermarked file
        $temp_dir = wp_upload_dir()['basedir'] . '/temp-watermark';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
            error_log('Ultimate Watermark: Created temp directory: ' . $temp_dir);
        }

        $file_info = pathinfo($file_path);
        $temp_file = $temp_dir . '/' . uniqid() . '_watermarked.' . $file_info['extension'];
        error_log('Ultimate Watermark: Temp file: ' . $temp_file);

        // Apply watermark using WatermarkService
        try {
            error_log('Ultimate Watermark: Calling WatermarkService::applyWatermark');
            $success = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::applyWatermark($file_path, $watermark, $temp_file);
            error_log('Ultimate Watermark: WatermarkService result: ' . ($success ? 'SUCCESS' : 'FAILED'));
            
            if ($success && file_exists($temp_file)) {
                error_log('Ultimate Watermark: Temp file created successfully, replacing original');
                // Replace original with watermarked version
                copy($temp_file, $file_path);
                unlink($temp_file); // Clean up temp file
                error_log('Ultimate Watermark: Watermark applied successfully');
                return true;
            } else {
                error_log('Ultimate Watermark: Watermark application failed or temp file not created');
            }
        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Exception in applyWatermarkToFile: ' . $e->getMessage());
            error_log('Ultimate Watermark: Exception trace: ' . $e->getTraceAsString());
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
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        $attachment_ids = array_map('absint', $_POST['attachment_ids'] ?? []);

        if (!$watermark_id || empty($attachment_ids)) {
            wp_send_json_error('Invalid parameters');
            return;
        }

        // Verify watermark exists and is active
        if (!WatermarkHelper::isWatermarkActive($watermark_id)) {
            wp_send_json_error('Watermark not found or inactive');
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
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $attachment_ids = array_map('absint', $_POST['attachment_ids'] ?? []);

        if (empty($attachment_ids)) {
            wp_send_json_error('No attachments selected');
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
     */
    public function addUploadToggleScript(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'media') {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Wait for plupload to be initialized
            function moveWatermarkToggle() {
                const $browseButton = $('.plupload-browse-button');
                const $watermarkToggle = $('#ultimate-watermark-upload-toggle');
                const $uploadArea = $('.plupload_dropbox');
                
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
            
            // Try to move immediately
            moveWatermarkToggle();
            
            // Also try after a short delay in case plupload loads later
            setTimeout(moveWatermarkToggle, 500);
            
            // Watch for DOM changes (in case plupload loads dynamically)
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        const $browseButton = $('.plupload-browse-button');
                        const $watermarkToggle = $('#ultimate-watermark-upload-toggle');
                        
                        if ($browseButton.length && $watermarkToggle.length && 
                            !$browseButton.next().is($watermarkToggle)) {
                            moveWatermarkToggle();
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
        </script>
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
}
