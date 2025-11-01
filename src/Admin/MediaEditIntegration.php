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
        // Use multiple hooks to ensure it works
        add_action('add_meta_boxes', [$this, 'addWatermarkMetaBox'], 10, 2);
        add_action('add_meta_boxes_attachment', [$this, 'addWatermarkMetaBox'], 10, 1);
        
        // Also try using current_screen hook as a fallback
        add_action('current_screen', [$this, 'maybeAddMetaBoxOnScreen']);
        
        // Handle remove all watermarks from single media page
        add_action('wp_ajax_ultimate_watermark_remove_all', [$this, 'handleRemoveAllWatermarks']);
        
        // Enqueue scripts and styles for media edit page
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    /**
     * Alternative method using current_screen hook
     */
    public function maybeAddMetaBoxOnScreen($screen): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: maybeAddMetaBoxOnScreen called');
            error_log('Screen: ' . ($screen ? $screen->base : 'null'));
        }
        
        // Only on post.php screen
        if (!$screen || $screen->base !== 'post') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Not post screen, base: ' . ($screen ? $screen->base : 'null'));
            }
            return;
        }
        
        // Only when editing
        if ($screen->action !== 'edit') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Not edit action, action: ' . ($screen->action ?? 'null'));
            }
            return;
        }
        
        // Get post ID
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (!$post_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: No post ID in GET');
            }
            return;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'attachment') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Not attachment, post_type: ' . ($post ? $post->post_type : 'null'));
            }
            return;
        }
        
        if (!wp_attachment_is_image($post_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Not an image attachment');
            }
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: Adding meta box via current_screen hook for attachment ' . $post_id);
        }
        
        // Add meta box directly with core priority
        add_meta_box(
            'ultimate-watermark-info',
            __('Applied Watermark', 'ultimate-watermark'),
            [$this, 'renderWatermarkMetaBox'],
            'attachment',
            'side',
            'core', // Use 'core' instead of 'high' to make it a core meta box
            [
                '__back_compat_meta_box' => true,
                '__block_editor_compatible_meta_box' => true
            ]
        );
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
     * 
     * Note: This method handles two different hook signatures:
     * - add_meta_boxes: passes ($post_type, $post)
     * - add_meta_boxes_attachment: passes ($post) only
     * 
     * @param string|\WP_Post $post_type_or_post Either post type string or WP_Post object
     * @param \WP_Post|null $post The post object (when called from add_meta_boxes)
     */
    public function addWatermarkMetaBox($post_type_or_post = null, $post = null): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: addWatermarkMetaBox called');
            error_log('First param type: ' . gettype($post_type_or_post) . ', Second param type: ' . gettype($post));
        }
        
        $current_post = null;
        
        // Handle different hook signatures
        if ($post_type_or_post instanceof \WP_Post) {
            // Called from add_meta_boxes_attachment - first param is the post
            $current_post = $post_type_or_post;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Called from add_meta_boxes_attachment hook');
            }
        } elseif (is_string($post_type_or_post)) {
            // Called from add_meta_boxes - first param is post_type
            // Only proceed if it's attachment
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Called from add_meta_boxes hook, post_type: ' . $post_type_or_post);
            }
            if ($post_type_or_post !== 'attachment') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Not attachment post type, skipping');
                }
                return;
            }
            if ($post instanceof \WP_Post) {
                $current_post = $post;
            }
        }
        
        // If we still don't have a post, try to get it from globals
        if (!$current_post) {
            global $post;
            if (isset($post) && $post instanceof \WP_Post) {
                $current_post = $post;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Ultimate Watermark: Got post from global');
                }
            }
        }
        
        // Try GET parameter as last fallback
        if (!$current_post && isset($_GET['post'])) {
            $post_id = absint($_GET['post']);
            $current_post = get_post($post_id);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Got post from GET parameter: ' . $post_id);
            }
        }
        
        // Validate we have a post
        if (!$current_post || !($current_post instanceof \WP_Post)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: No valid post found');
            }
            return;
        }
        
        // Only add for attachment post type
        if ($current_post->post_type !== 'attachment') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Post is not attachment, type: ' . $current_post->post_type);
            }
            return;
        }

        // Only add for images
        if (!wp_attachment_is_image($current_post->ID)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Post is not an image: ' . $current_post->ID);
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: Adding meta box for attachment ' . $current_post->ID);
        }

        // Register the meta box with 'core' context to make it harder to hide
        add_meta_box(
            'ultimate-watermark-info',
            __('Applied Watermark', 'ultimate-watermark'),
            [$this, 'renderWatermarkMetaBox'],
            'attachment',
            'side',
            'core', // Use 'core' instead of 'high' to make it a core meta box
            [
                '__back_compat_meta_box' => true,
                '__block_editor_compatible_meta_box' => true
            ]
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Ultimate Watermark: Meta box registered successfully');
        }
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
        
        // Get watermarks by size
        $watermarks_by_size = WatermarkUsageTracker::getAllWatermarksBySize($attachment_id);
        
        // Get image metadata to get available sizes
        $metadata = wp_get_attachment_metadata($attachment_id);
        $available_sizes = ['full']; // Always include full size
        if ($metadata && isset($metadata['sizes'])) {
            $available_sizes = array_merge($available_sizes, array_keys($metadata['sizes']));
        }
        
        // Sort sizes by priority: full first, then by dimensions
        $sorted_sizes = $this->sortSizesByPriority($available_sizes, $metadata);
        
        // Header with total count
        $total_watermarks = count($watermark_ids);
        $html .= '<div class="watermark-header">';
        $html .= '<div class="watermark-title">';
        $html .= '<span class="dashicons dashicons-format-image"></span>';
        $html .= '<h4>' . esc_html__('Watermark Status', 'ultimate-watermark') . '</h4>';
        $html .= '</div>';
        $html .= '<div class="watermark-summary">';
        $html .= '<span class="total-count">' . $total_watermarks . '</span>';
        $html .= '<span class="total-label">' . esc_html__('Applied', 'ultimate-watermark') . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Size breakdown cards
        $html .= '<div class="watermark-sizes-grid">';
        foreach ($sorted_sizes as $size) {
            $size_watermarks = $watermarks_by_size[$size] ?? [];
            $watermark_count = count($size_watermarks);
            
            // Get size info
            $size_info = $this->getSizeInfo($size, $metadata);
            
            $html .= '<div class="size-card" data-size="' . esc_attr($size) . '">';
            
            // Card header with image preview
            $html .= '<div class="size-card-header">';
            $html .= '<div class="size-info">';
            $html .= '<div class="size-image-preview">';
            $html .= $this->getSizeImagePreview($attachment_id, $size, $size_info);
            $html .= '</div>';
            $html .= '<div class="size-details">';
            $html .= '<span class="size-name">' . esc_html($size_info['name']) . '</span>';
            $html .= '<span class="size-dimensions">' . esc_html($size_info['dimensions']) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
            
            // Status badge
            if ($watermark_count > 0) {
                $html .= '<div class="status-badge has-watermarks">';
                $html .= '<span class="count">' . $watermark_count . '</span>';
                $html .= '<span class="label">' . esc_html__('Applied', 'ultimate-watermark') . '</span>';
                $html .= '</div>';
            } else {
                $html .= '<div class="status-badge no-watermarks">';
                $html .= '<span class="label">' . esc_html__('None', 'ultimate-watermark') . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>'; // Close size-card-header
            
            // Watermark list (collapsible)
            if (!empty($size_watermarks)) {
                $html .= '<div class="watermark-details" data-size="' . esc_attr($size) . '">';
                $html .= '<div class="watermark-list">';
                foreach ($size_watermarks as $watermark_id) {
                    $html .= $this->renderWatermarkItem($watermark_id, $attachment_id, $size);
                }
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>'; // Close size-card
        }
        $html .= '</div>'; // Close watermark-sizes-grid
        
        // Action buttons
        $html .= '<div class="watermark-actions">';
        $html .= '<button type="button" class="button button-primary expand-all-btn">';
        $html .= '<span class="dashicons dashicons-arrow-down-alt2"></span>';
        $html .= esc_html__('Show Details', 'ultimate-watermark');
        $html .= '</button>';
        $html .= '<button type="button" class="button button-secondary remove-all-watermarks-btn" 
                        data-attachment-id="' . esc_attr($attachment_id) . '">';
        $html .= '<span class="dashicons dashicons-trash"></span>';
        $html .= esc_html__('Remove All', 'ultimate-watermark');
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</div>'; // Close watermark-status
        
        return $html;
    }

    /**
     * Render individual watermark item
     */
    private function renderWatermarkItem(int $watermark_id, int $attachment_id, string $size): string
    {
        $watermark = get_post($watermark_id);
        if (!$watermark) {
            return '';
        }

        $watermark_data = WatermarkHelper::getActiveWatermarkById($watermark_id);
        if (!$watermark_data) {
            return '';
        }

        $type_icon = $watermark_data['watermark_type'] === 'text' ? 'dashicons-format-text' : 'dashicons-format-image';
        $type_text = ucfirst($watermark_data['watermark_type']);
        
        // Create edit link
        $edit_link = admin_url('admin.php?page=ultimate-watermark-add-watermark&ID=' . $watermark_id);
        
        return '
            <div class="watermark-item" data-watermark-id="' . esc_attr($watermark_id) . '" data-attachment-id="' . esc_attr($attachment_id) . '" data-size="' . esc_attr($size) . '">
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
    
    /**
     * Sort sizes by priority (full first, then by dimensions)
     */
    private function sortSizesByPriority(array $sizes, array $metadata = null): array
    {
        $sorted = [];
        $size_data = [];
        
        // Separate full size
        if (in_array('full', $sizes)) {
            $sorted[] = 'full';
        }
        
        // Get dimensions for other sizes
        foreach ($sizes as $size) {
            if ($size === 'full') continue;
            
            $width = 0;
            $height = 0;
            if ($metadata && isset($metadata['sizes'][$size])) {
                $width = $metadata['sizes'][$size]['width'] ?? 0;
                $height = $metadata['sizes'][$size]['height'] ?? 0;
            }
            
            $size_data[$size] = [
                'width' => $width,
                'height' => $height,
                'area' => $width * $height
            ];
        }
        
        // Sort by area (largest first)
        uasort($size_data, function($a, $b) {
            return $b['area'] - $a['area'];
        });
        
        // Add sorted sizes
        foreach ($size_data as $size => $data) {
            $sorted[] = $size;
        }
        
        return $sorted;
    }
    
    /**
     * Get size information
     */
    private function getSizeInfo(string $size, array $metadata = null): array
    {
        if ($size === 'full') {
            return [
                'name' => __('Original Image', 'ultimate-watermark'),
                'dimensions' => __('Full Resolution', 'ultimate-watermark')
            ];
        }
        
        $width = 0;
        $height = 0;
        if ($metadata && isset($metadata['sizes'][$size])) {
            $width = $metadata['sizes'][$size]['width'] ?? 0;
            $height = $metadata['sizes'][$size]['height'] ?? 0;
        }
        
        $name = ucfirst(str_replace('_', ' ', $size));
        $dimensions = $width && $height ? $width . ' × ' . $height : __('Unknown Size', 'ultimate-watermark');
        
        return [
            'name' => $name,
            'dimensions' => $dimensions
        ];
    }
    
    /**
     * Get size image preview
     */
    private function getSizeImagePreview(int $attachment_id, string $size, array $size_info): string
    {
        $image_url = '';
        $image_path = '';
        
        if ($size === 'full') {
            $image_url = wp_get_attachment_url($attachment_id);
            $image_path = get_attached_file($attachment_id);
        } else {
            $image_url = wp_get_attachment_image_url($attachment_id, $size);
            $image_path = $this->getImagePathForSize($attachment_id, $size);
        }
        
        // Check if image file exists
        if (!$image_url || !$image_path || !file_exists($image_path)) {
            // Fallback to icon if image doesn't exist
            return '<div class="size-icon-fallback"><span class="dashicons dashicons-format-image"></span></div>';
        }
        
        // Get image dimensions for the preview
        $metadata = wp_get_attachment_metadata($attachment_id);
        $width = 0;
        $height = 0;
        
        if ($size === 'full') {
            $width = $metadata['width'] ?? 0;
            $height = $metadata['height'] ?? 0;
        } elseif (isset($metadata['sizes'][$size])) {
            $width = $metadata['sizes'][$size]['width'] ?? 0;
            $height = $metadata['sizes'][$size]['height'] ?? 0;
        }
        
        return sprintf(
            '<img src="%s" alt="%s" class="size-preview-image" data-size="%s" data-attachment-id="%d" data-width="%d" data-height="%d" />',
            esc_url($image_url),
            esc_attr($size_info['name']),
            esc_attr($size),
            $attachment_id,
            $width,
            $height
        );
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
        if (!$metadata || empty($metadata['sizes'][$size]['file'])) {
            return null;
        }
        
        $original_path = get_attached_file($attachment_id);
        if (!$original_path) {
            return null;
        }
        
        $dir = pathinfo($original_path, PATHINFO_DIRNAME);
        $size_filename = $metadata['sizes'][$size]['file'];
        
        return $dir . '/' . $size_filename;
    }
    
    /**
     * Get size icon (legacy method)
     */
    private function getSizeIcon(string $size): string
    {
        $icons = [
            'full' => '<span class="dashicons dashicons-format-image"></span>',
            'large' => '<span class="dashicons dashicons-format-image"></span>',
            'medium' => '<span class="dashicons dashicons-format-image"></span>',
            'thumbnail' => '<span class="dashicons dashicons-format-image"></span>',
            'medium_large' => '<span class="dashicons dashicons-format-image"></span>'
        ];
        
        return $icons[$size] ?? '<span class="dashicons dashicons-format-image"></span>';
    }
    
    /**
     * Get size label with dimensions (legacy method)
     */
    private function getSizeLabel(string $size, array $metadata = null): string
    {
        if ($size === 'full') {
            return __('Full Size', 'ultimate-watermark');
        }
        
        if ($metadata && isset($metadata['sizes'][$size])) {
            $size_data = $metadata['sizes'][$size];
            $width = $size_data['width'] ?? 0;
            $height = $size_data['height'] ?? 0;
            return ucfirst($size) . ' (' . $width . 'x' . $height . ')';
        }
        
        return ucfirst($size);
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
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $attachment_id = absint($_POST['attachment_id'] ?? 0);
        $watermark_id = absint($_POST['watermark_id'] ?? 0);

        if (!$attachment_id || !$watermark_id) {
            wp_send_json_error(['message' => __('Invalid attachment or watermark ID.', 'ultimate-watermark')]);
            return;
        }

        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => __('Attachment is not an image.', 'ultimate-watermark')]);
            return;
        }

        // Check if watermark is actually applied to this image
        $applied_watermarks = WatermarkUsageTracker::getAppliedWatermarks($attachment_id);
        if (!in_array($watermark_id, $applied_watermarks)) {
            wp_send_json_error(['message' => __('Watermark is not applied to this image.', 'ultimate-watermark')]);
            return;
        }

        // Remove watermark
        $success = $this->removeWatermarkFromAttachment($attachment_id);

        if ($success) {
            wp_send_json_success([
                'message' => __('Watermark removed successfully.', 'ultimate-watermark'),
                'watermark_id' => $watermark_id,
                'attachment_id' => $attachment_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove watermark. Backup file may not exist.', 'ultimate-watermark')]);
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark: Error removing watermark from attachment ' . $attachment_id . ': ' . $e->getMessage());
            }
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
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $attachment_id = absint($_POST['attachment_id'] ?? 0);

        if (!$attachment_id) {
            wp_send_json_error(['message' => __('Invalid attachment ID.', 'ultimate-watermark')]);
            return;
        }

        // Check if attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => __('Attachment is not an image.', 'ultimate-watermark')]);
            return;
        }

        // Get applied watermarks
        $applied_watermarks = WatermarkUsageTracker::getAppliedWatermarks($attachment_id);
        
        if (empty($applied_watermarks)) {
            wp_send_json_error(['message' => __('No watermarks applied to this image.', 'ultimate-watermark')]);
            return;
        }

        // Remove all watermarks by restoring from backup
        $success = $this->removeWatermarkFromAttachment($attachment_id);

        if ($success) {
            wp_send_json_success([
                'message' => __('All watermarks removed successfully.', 'ultimate-watermark'),
                'attachment_id' => $attachment_id,
                'removed_count' => count($applied_watermarks)
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove watermarks. Backup file may not exist.', 'ultimate-watermark')]);
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
            '2.0.3'
        );

        // Enqueue scripts
        wp_enqueue_script(
            'ultimate-watermark-media-edit',
            ULTIMATE_WATERMARK_URL . 'assets/js/media-edit.js',
            ['jquery'],
            '2.0.3',
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
        
        // Add inline script to ensure meta box is always visible and cannot be hidden
        wp_add_inline_script('ultimate-watermark-media-edit', '
            (function() {
                function ensureMetaBoxVisible() {
                    var metaBox = document.getElementById("ultimate-watermark-info");
                    if (metaBox) {
                        // Force meta box to be visible
                        metaBox.style.display = "";
                        metaBox.classList.remove("hide-if-js");
                        var parent = metaBox.closest(".postbox");
                        if (parent) {
                            parent.style.display = "";
                            parent.classList.remove("closed");
                        }
                        
                        // Check the Screen Options checkbox to ensure it stays visible
                        var checkbox = document.querySelector(\'input[value="ultimate-watermark-info"]\');
                        if (checkbox) {
                            checkbox.checked = true;
                            checkbox.disabled = true; // Prevent unchecking
                        }
                    }
                }
                
                // Run on page load
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", ensureMetaBoxVisible);
                } else {
                    ensureMetaBoxVisible();
                }
                
                // Also run after WordPress meta box initialization
                if (typeof jQuery !== "undefined") {
                    jQuery(document).ready(function($) {
                        ensureMetaBoxVisible();
                        setTimeout(ensureMetaBoxVisible, 100);
                        setTimeout(ensureMetaBoxVisible, 500);
                        setTimeout(ensureMetaBoxVisible, 1000);
                    });
                }
            })();
        ');
    }
}
