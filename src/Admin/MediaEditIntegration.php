<?php

namespace MantraBrain\UltimateWatermark\Admin;

use MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker;
use MantraBrain\UltimateWatermark\Utils\WatermarkHelper;
use MantraBrain\UltimateWatermark\Utils\BackupManager;

/**
 * Media Edit Integration
 * 
 * Handles watermark information display and removal on single media edit pages
 */
class MediaEditIntegration
{
    /**
     * Initialize the media edit integration
     */
    public function init(): void
    {
        // Add watermark information to the attachment details sidebar
        add_action('attachment_fields_to_edit', [$this, 'addWatermarkFields'], 10, 2);
        
        // Add watermark info to the attachment details sidebar
        add_action('add_meta_boxes', [$this, 'addWatermarkMetaBox']);
        
        // Handle remove all watermarks from single media page
        add_action('wp_ajax_ultimate_watermark_remove_all', [$this, 'handleRemoveAllWatermarks']);
        
        // Enqueue scripts and styles for media edit page
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    /**
     * Add watermark fields to the media edit form
     */
    public function addWatermarkFields(array $form_fields, \WP_Post $post): array
    {
        // Only show for images
        if (!wp_attachment_is_image($post->ID)) {
            return $form_fields;
        }
        return $form_fields;
    }

    /**
     * Add watermark meta box to attachment edit page
     */
    public function addWatermarkMetaBox(): void
    {
        // Only add to attachment edit pages
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'attachment') {
            return;
        }

        // Get the current post ID
        $post_id = absint($_GET['post'] ?? 0);
        if (!$post_id) {
            return;
        }

        // Only add for images
        if (!wp_attachment_is_image($post_id)) {
            return;
        }

        add_meta_box(
            'ultimate-watermark-info',
            __('Applied Watermark', 'ultimate-watermark'),
            [$this, 'renderWatermarkMetaBox'],
            'attachment',
            'side',
            'high'
        );
    }

    /**
     * Render watermark meta box content
     */
    public function renderWatermarkMetaBox(\WP_Post $post): void
    {
        // Get applied watermarks for this image
        $applied_watermarks = WatermarkUsageTracker::getAppliedWatermarks($post->ID);
        
        if (empty($applied_watermarks)) {
            // No watermarks applied
            echo $this->renderNoWatermarkInfo();
        } else {
            // Watermarks applied
            echo $this->renderWatermarkInfo($applied_watermarks, $post->ID);
        }
    }

    /**
     * Render no watermark information
     */
    private function renderNoWatermarkInfo(): string
    {
        return '
            <div class="watermark-status no-watermarks">
                <span class="dashicons dashicons-format-image"></span>
                <span class="status-text">' . esc_html__('No watermarks applied', 'ultimate-watermark') . '</span>
            </div>
        ';
    }

    /**
     * Render watermark information with removal options
     */
    private function renderWatermarkInfo(array $watermark_ids, int $attachment_id): string
    {
        $html = '<div class="watermark-status has-watermarks">';
        
        // List of applied watermarks
        $html .= '<div class="applied-watermarks-list">';
        
        foreach ($watermark_ids as $watermark_id) {
            $watermark = get_post($watermark_id);
            if (!$watermark) {
                continue;
            }

            $watermark_data = WatermarkHelper::getActiveWatermarkById($watermark_id);
            if (!$watermark_data) {
                continue;
            }

            $type_icon = $watermark_data['watermark_type'] === 'text' ? 'dashicons-format-text' : 'dashicons-format-image';
            $type_text = ucfirst($watermark_data['watermark_type']);
            
            // Create edit link
            $edit_link = admin_url('admin.php?page=ultimate-watermark-add-watermark&ID=' . $watermark_id);
            
            $html .= '
                <div class="applied-watermark-item" data-watermark-id="' . esc_attr($watermark_id) . '" data-attachment-id="' . esc_attr($attachment_id) . '">
                    <div class="watermark-item-header">
                        <span class="dashicons ' . esc_attr($type_icon) . '"></span>
                        <a href="' . esc_url($edit_link) . '" class="watermark-name-link" target="_blank">
                            ' . esc_html($watermark->post_title) . '
                        </a>
                        <span class="watermark-type">(' . esc_html($type_text) . ')</span>
                    </div>
                </div>
            ';
        }
        
        $html .= '</div>'; // Close applied-watermarks-list
        
        // Add "Remove All" button
        $html .= '
            <div class="watermark-remove-all">
                <button type="button" class="button button-secondary remove-all-watermarks-btn" 
                        data-attachment-id="' . esc_attr($attachment_id) . '">
                    <span class="dashicons dashicons-trash"></span>
                    ' . esc_html__('Remove All Watermarks', 'ultimate-watermark') . '
                </button>
            </div>
        ';
        $html .= '</div>'; // Close watermark-status
        
        return $html;
    }

    /**
     * Render watermark preview
     */
    private function renderWatermarkPreview(array $watermark_data): string
    {
        if ($watermark_data['watermark_type'] === 'text') {
            return '
                <div class="text-watermark-preview">
                    <span class="watermark-text" style="
                        color: ' . esc_attr($watermark_data['watermark_color']) . ';
                        font-size: ' . esc_attr($watermark_data['watermark_font_size']) . 'px;
                        font-family: ' . esc_attr($watermark_data['watermark_font_family']) . ';
                        font-weight: ' . esc_attr($watermark_data['watermark_font_weight']) . ';
                        font-style: ' . esc_attr($watermark_data['watermark_font_style']) . ';
                        text-decoration: ' . esc_attr($watermark_data['watermark_text_decoration']) . ';
                    ">
                        ' . esc_html($watermark_data['watermark_text']) . '
                    </span>
                </div>
            ';
        } else {
            $image_url = '';
            if ($watermark_data['watermark_image_id'] > 0) {
                $image_url = wp_get_attachment_image_url($watermark_data['watermark_image_id'], 'thumbnail');
            }
            
            if ($image_url) {
                return '
                    <div class="image-watermark-preview">
                        <img src="' . esc_url($image_url) . '" alt="' . esc_attr($watermark_data['watermark_text']) . '" class="watermark-image">
                    </div>
                ';
            } else {
                return '
                    <div class="image-watermark-preview no-image">
                        <span class="dashicons dashicons-format-image"></span>
                        <span>' . esc_html__('No image set', 'ultimate-watermark') . '</span>
                    </div>
                ';
            }
        }
    }

    /**
     * Handle single watermark removal via AJAX
     */
    public function handleSingleWatermarkRemoval(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_media_edit')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $attachment_id = absint($_POST['attachment_id'] ?? 0);
        $watermark_id = absint($_POST['watermark_id'] ?? 0);

        if (!$attachment_id || !$watermark_id) {
            wp_send_json_error('Invalid attachment or watermark ID');
            return;
        }

        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error('Attachment is not an image');
            return;
        }

        // Check if watermark is actually applied to this image
        $applied_watermarks = WatermarkUsageTracker::getAppliedWatermarks($attachment_id);
        if (!in_array($watermark_id, $applied_watermarks)) {
            wp_send_json_error('Watermark is not applied to this image');
            return;
        }

        // Remove watermark
        $success = $this->removeWatermarkFromAttachment($attachment_id);

        if ($success) {
            wp_send_json_success([
                'message' => 'Watermark removed successfully',
                'watermark_id' => $watermark_id,
                'attachment_id' => $attachment_id
            ]);
        } else {
            wp_send_json_error('Failed to remove watermark. Backup file may not exist.');
        }
    }

    /**
     * Remove watermark from attachment by restoring from backup
     */
    private function removeWatermarkFromAttachment(int $attachment_id): bool
    {
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
            error_log('Ultimate Watermark: Error removing watermark from attachment ' . $attachment_id . ': ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Handle remove all watermarks via AJAX
     */
    public function handleRemoveAllWatermarks(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_media_edit')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $attachment_id = absint($_POST['attachment_id'] ?? 0);

        if (!$attachment_id) {
            wp_send_json_error('Invalid attachment ID');
            return;
        }

        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error('Attachment is not an image');
            return;
        }

        // Get applied watermarks
        $applied_watermarks = WatermarkUsageTracker::getAppliedWatermarks($attachment_id);
        
        if (empty($applied_watermarks)) {
            wp_send_json_error('No watermarks applied to this image');
            return;
        }

        // Remove all watermarks by restoring from backup
        $success = $this->removeWatermarkFromAttachment($attachment_id);

        if ($success) {
            wp_send_json_success([
                'message' => 'All watermarks removed successfully',
                'attachment_id' => $attachment_id,
                'removed_count' => count($applied_watermarks)
            ]);
        } else {
            wp_send_json_error('Failed to remove watermarks. Backup file may not exist.');
        }
    }


    /**
     * Enqueue scripts and styles for media edit page
     */
    public function enqueueScripts(string $hook): void
    {
        // Only load on media edit pages
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        // Check if we're editing an attachment
        $post_id = absint($_GET['post'] ?? 0);
        if (!$post_id) {
            return;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'attachment') {
            return;
        }

        // Only load for images
        if (!wp_attachment_is_image($post_id)) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'ultimate-watermark-media-edit',
            ULTIMATE_WATERMARK_URL . 'assets/css/media-edit.css',
            [],
            '1.0.0'
        );

        // Enqueue scripts
        wp_enqueue_script(
            'ultimate-watermark-media-edit',
            ULTIMATE_WATERMARK_URL . 'assets/js/media-edit.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Localize script
        wp_localize_script('ultimate-watermark-media-edit', 'ultimate_watermark_media_edit', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ultimate_watermark_media_edit'),
            'strings' => [
                'confirm_remove' => __('Are you sure you want to remove this watermark? This will restore the original image from backup.', 'ultimate-watermark'),
                'confirm_remove_all' => __('Are you sure you want to remove ALL watermarks from this image? This will restore the original image from backup.', 'ultimate-watermark'),
                'removing' => __('Removing watermark...', 'ultimate-watermark'),
                'removed' => __('Watermark removed successfully', 'ultimate-watermark'),
                'removed_all' => __('All watermarks removed successfully', 'ultimate-watermark'),
                'remove_all' => __('Remove All Watermarks', 'ultimate-watermark'),
                'no_watermarks' => __('No watermarks applied', 'ultimate-watermark'),
                'error' => __('Failed to remove watermark', 'ultimate-watermark')
            ]
        ]);
    }
}
