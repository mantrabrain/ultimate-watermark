<?php

namespace MantraBrain\UltimateWatermark\Ajax;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\Utils\WatermarkHelper;

/**
 * Watermark Actions AJAX Handler
 * 
 * Handles AJAX requests for watermark actions like duplicate, delete, toggle status
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkActionsHandler
{
    use SingletonTrait;

    /**
     * Initialize AJAX handlers
     */
    public function init(): void
    {
        add_action('wp_ajax_ultimate_watermark_duplicate', [$this, 'handleDuplicate']);
        add_action('wp_ajax_ultimate_watermark_delete', [$this, 'handleDelete']);
        add_action('wp_ajax_ultimate_watermark_toggle', [$this, 'handleToggle']);
        add_action('wp_ajax_ultimate_watermark_bulk_delete', [$this, 'handleBulkDelete']);
        add_action('wp_ajax_ultimate_watermark_bulk_activate', [$this, 'handleBulkActivate']);
        add_action('wp_ajax_ultimate_watermark_bulk_deactivate', [$this, 'handleBulkDeactivate']);
        
    }

    /**
     * Handle duplicate watermark
     */
    public function handleDuplicate(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        if (!$watermark_id) {
            wp_send_json_error(['message' => __('Invalid watermark ID.', 'ultimate-watermark')]);
            return;
        }

        // Get original watermark
        $original_post = get_post($watermark_id);
        if (!$original_post || $original_post->post_type !== 'ultimate_watermark') {
            wp_send_json_error(['message' => __('Watermark not found.', 'ultimate-watermark')]);
            return;
        }

        // Check if Pro is active
        $is_pro_active = defined('ULTIMATE_WATERMARK_PRO_VERSION');
        
        // Count existing watermarks
        $watermark_count = wp_count_posts('ultimate_watermark');
        $total_watermarks = isset($watermark_count->publish) ? intval($watermark_count->publish) : 0;
        
        // Restrict duplicate if user has 1+ watermarks and Pro is not active
        if (!$is_pro_active && $total_watermarks >= 1) {
            wp_send_json_error([
                'message' => __('Upgrade to Pro to duplicate watermarks', 'ultimate-watermark'),
                'upgrade_required' => true,
                'upgrade_url' => 'https://store.mantrabrain.com/checkout/?edd_action=add_to_cart&download_id=36499&edd_options[price_id]=1'
            ]);
            return;
        }

        // Create duplicate - sanitize post data before insertion
        $duplicate_data = [
            'post_title' => sanitize_text_field($original_post->post_title . ' (Copy)'),
            'post_content' => wp_kses_post($original_post->post_content),
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish'
        ];

        $duplicate_id = wp_insert_post($duplicate_data);
        if (is_wp_error($duplicate_id)) {
            wp_send_json_error(['message' => __('Failed to create duplicate.', 'ultimate-watermark')]);
            return;
        }

        // Copy every setting, including ones added by Pro or later versions;
        // skip private keys and usage statistics of the original.
        $excluded_meta = ['watermark_usage_count', 'watermark_used_images', 'watermark_count', 'preview_url'];
        $meta_fields = array_values(array_filter(array_keys((array) get_post_meta($watermark_id)), static function ($key) use ($excluded_meta) {
            return is_string($key) && $key !== '' && $key[0] !== '_' && !in_array($key, $excluded_meta, true);
        }));

        // Copy each metadata field properly
        foreach ($meta_fields as $meta_key) {
            $meta_value = get_post_meta($watermark_id, $meta_key, true);
            
            // Skip if meta doesn't exist
            if ($meta_value === '' || $meta_value === false) {
                continue;
            }
            
            // For watermark_rules, ensure it's properly handled as an array
            if ($meta_key === 'watermark_rules') {
                // If it's a JSON string, decode it
                if (is_string($meta_value)) {
                    $decoded = json_decode($meta_value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $meta_value = $decoded;
                    }
                }
                // Store as array (WordPress will serialize it)
                update_post_meta($duplicate_id, $meta_key, $meta_value);
            }
            // For array fields, ensure they're properly handled
            elseif (in_array($meta_key, ['watermark_post_types', 'watermark_sizes'])) {
                // Ensure it's an array
                if (!is_array($meta_value)) {
                    $meta_value = maybe_unserialize($meta_value);
                }
                update_post_meta($duplicate_id, $meta_key, $meta_value);
            }
            // For all other fields, copy directly
            else {
                update_post_meta($duplicate_id, $meta_key, $meta_value);
            }
        }

        WatermarkHelper::clearCache();

        wp_send_json_success([
            'message' => __('Watermark duplicated successfully', 'ultimate-watermark'),
            'duplicate_id' => $duplicate_id
        ]);
    }

    /**
     * Handle delete watermark
     */
    public function handleDelete(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        
        if (!$watermark_id) {
            wp_send_json_error(['message' => __('Invalid watermark ID.', 'ultimate-watermark')]);
            return;
        }

        // Only watermark posts may be deleted here.
        if (!$this->isWatermarkPost($watermark_id)) {
            wp_send_json_error(['message' => __('Watermark not found.', 'ultimate-watermark')]);
            return;
        }

        // Clean up preview images before deleting the watermark
        \MantraBrain\UltimateWatermark\Utils\PreviewManager::cleanupWatermarkPreviews($watermark_id);
        
        // Delete the watermark
        $result = wp_delete_post($watermark_id, true);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to delete watermark.', 'ultimate-watermark')]);
            return;
        }

        WatermarkHelper::clearCache($watermark_id);

        wp_send_json_success([
            'message' => __('Watermark deleted successfully.', 'ultimate-watermark')
        ]);
    }

    /**
     * Handle toggle watermark status
     */
    public function handleToggle(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        if (!$watermark_id) {
            wp_send_json_error(['message' => __('Invalid watermark ID.', 'ultimate-watermark')]);
            return;
        }
        
        if (!in_array($status, ['active', 'inactive'])) {
            wp_send_json_error(['message' => __('Invalid status parameter.', 'ultimate-watermark')]);
            return;
        }

        // Update watermark status - always use update_post_meta
        $meta_value = $status === 'active' ? '1' : '0';
        
        if (!$this->isWatermarkPost($watermark_id)) {
            wp_send_json_error(['message' => __('Watermark not found.', 'ultimate-watermark')]);
            return;
        }

        update_post_meta($watermark_id, 'active', $meta_value);

        if (get_post_meta($watermark_id, 'active', true) !== $meta_value) {
            wp_send_json_error(['message' => __('Failed to update watermark status.', 'ultimate-watermark')]);
            return;
        }

        WatermarkHelper::clearCache($watermark_id);

        wp_send_json_success([
            'message' => __('Watermark status updated successfully.', 'ultimate-watermark'),
            'status' => $status
        ]);
    }

    /**
     * Handle bulk delete watermarks
     */
    public function handleBulkDelete(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $watermark_ids = array_filter(array_map('absint', (array) ($_POST['watermark_ids'] ?? [])), [$this, 'isWatermarkPost']);
        if (empty($watermark_ids)) {
            wp_send_json_error(['message' => __('No watermarks selected.', 'ultimate-watermark')]);
            return;
        }

        $deleted_count = 0;
        foreach ($watermark_ids as $watermark_id) {
            // Clean up preview images before deleting the watermark
            \MantraBrain\UltimateWatermark\Utils\PreviewManager::cleanupWatermarkPreviews($watermark_id);
            
            $result = wp_delete_post($watermark_id, true);
            if ($result) {
                $deleted_count++;
            }
            WatermarkHelper::clearCache($watermark_id);
        }

        wp_send_json_success([
            /* translators: %d: number of watermarks */
            'message' => sprintf(_n('%d watermark deleted.', '%d watermarks deleted.', $deleted_count, 'ultimate-watermark'), $deleted_count),
            'deleted_count' => $deleted_count
        ]);
    }

    /**
     * Handle bulk activate watermarks
     */
    public function handleBulkActivate(): void
    {
        $this->handleBulkStatusUpdate((array) ($_POST['watermark_ids'] ?? []), 'active');
    }

    /**
     * Handle bulk deactivate watermarks
     */
    public function handleBulkDeactivate(): void
    {
        $this->handleBulkStatusUpdate((array) ($_POST['watermark_ids'] ?? []), 'inactive');
    }

    /**
     * Handle bulk status update
     */
    private function handleBulkStatusUpdate(array $watermark_ids, string $status): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ultimate-watermark')]);
            return;
        }

        $watermark_ids = array_filter(array_map('absint', $watermark_ids), [$this, 'isWatermarkPost']);
        if (empty($watermark_ids)) {
            wp_send_json_error(['message' => __('No watermarks selected.', 'ultimate-watermark')]);
            return;
        }

        $updated_count = 0;
        $meta_value = $status === 'active' ? '1' : '0';
        
        foreach ($watermark_ids as $watermark_id) {
            // Always use update_post_meta - it will add if not exists, update if exists
            update_post_meta($watermark_id, 'active', $meta_value);
            if (get_post_meta($watermark_id, 'active', true) === $meta_value) {
                $updated_count++;
            }
            WatermarkHelper::clearCache($watermark_id);
        }

        $message = $status === 'active'
            /* translators: %d: number of watermarks */
            ? sprintf(_n('%d watermark activated.', '%d watermarks activated.', $updated_count, 'ultimate-watermark'), $updated_count)
            /* translators: %d: number of watermarks */
            : sprintf(_n('%d watermark deactivated.', '%d watermarks deactivated.', $updated_count, 'ultimate-watermark'), $updated_count);

        wp_send_json_success([
            'message' => $message,
            'updated_count' => $updated_count
        ]);
    }

    /**
     * Whether an ID belongs to a watermark post.
     */
    public function isWatermarkPost($id): bool
    {
        return (int) $id > 0 && get_post_type((int) $id) === 'ultimate_watermark';
    }
}
