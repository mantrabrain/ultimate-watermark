<?php

namespace MantraBrain\UltimateWatermark\Ajax;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;

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
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        if (!$watermark_id) {
            wp_send_json_error('Invalid watermark ID');
            return;
        }

        // Get original watermark
        $original_post = get_post($watermark_id);
        if (!$original_post || $original_post->post_type !== 'ultimate_watermark') {
            wp_send_json_error('Watermark not found');
            return;
        }

        // Create duplicate
        $duplicate_data = [
            'post_title' => $original_post->post_title . ' (Copy)',
            'post_content' => $original_post->post_content,
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish'
        ];

        $duplicate_id = wp_insert_post($duplicate_data);
        if (is_wp_error($duplicate_id)) {
            wp_send_json_error('Failed to create duplicate');
            return;
        }

        // Copy all meta data
        $meta_data = get_post_meta($watermark_id);
        foreach ($meta_data as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($duplicate_id, $key, $value);
            }
        }

        wp_send_json_success([
            'message' => 'Watermark duplicated successfully',
            'duplicate_id' => $duplicate_id
        ]);
    }

    /**
     * Handle delete watermark
     */
    public function handleDelete(): void
    {
        // Add debugging
        error_log('Ultimate Watermark Delete - POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            error_log('Ultimate Watermark Delete - Invalid nonce');
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            error_log('Ultimate Watermark Delete - Insufficient permissions');
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        error_log('Ultimate Watermark Delete - Watermark ID: ' . $watermark_id);
        
        if (!$watermark_id) {
            error_log('Ultimate Watermark Delete - Invalid watermark ID');
            wp_send_json_error('Invalid watermark ID');
            return;
        }

        // Check if post exists
        $post = get_post($watermark_id);
        if (!$post) {
            error_log('Ultimate Watermark Delete - Post not found');
            wp_send_json_error('Watermark not found');
            return;
        }

        // Clean up preview images before deleting the watermark
        \MantraBrain\UltimateWatermark\Utils\PreviewManager::cleanupWatermarkPreviews($watermark_id);
        
        // Delete the watermark
        $result = wp_delete_post($watermark_id, true);
        error_log('Ultimate Watermark Delete - Delete result: ' . print_r($result, true));
        
        if (!$result) {
            error_log('Ultimate Watermark Delete - Failed to delete');
            wp_send_json_error('Failed to delete watermark');
            return;
        }

        error_log('Ultimate Watermark Delete - Success');
        wp_send_json_success([
            'message' => 'Watermark deleted successfully'
        ]);
    }

    /**
     * Handle toggle watermark status
     */
    public function handleToggle(): void
    {
        // Add debugging
        error_log('Ultimate Watermark Toggle - POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            error_log('Ultimate Watermark Toggle - Invalid nonce');
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            error_log('Ultimate Watermark Toggle - Insufficient permissions');
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $watermark_id = absint($_POST['watermark_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        error_log('Ultimate Watermark Toggle - Watermark ID: ' . $watermark_id);
        error_log('Ultimate Watermark Toggle - Status: ' . $status);
        
        if (!$watermark_id) {
            error_log('Ultimate Watermark Toggle - Invalid watermark ID');
            wp_send_json_error('Invalid watermark ID');
            return;
        }
        
        if (!in_array($status, ['active', 'inactive'])) {
            error_log('Ultimate Watermark Toggle - Invalid status: ' . $status);
            wp_send_json_error('Invalid status parameter');
            return;
        }

        // Update watermark status - always use update_post_meta
        $meta_value = $status === 'active' ? '1' : '0';
        
        error_log('Ultimate Watermark Toggle - Meta value: ' . $meta_value);
        
        // Check if post exists and get its details
        $post = get_post($watermark_id);
        if (!$post) {
            error_log('Ultimate Watermark Toggle - Post not found for ID: ' . $watermark_id);
            wp_send_json_error('Watermark not found');
            return;
        }
        
        error_log('Ultimate Watermark Toggle - Post found: ' . $post->post_title . ' (Type: ' . $post->post_type . ')');
        
        // Check existing meta value
        $existing_meta = get_post_meta($watermark_id, 'active', true);
        error_log('Ultimate Watermark Toggle - Existing meta value: ' . var_export($existing_meta, true));
        
        // Try direct database update as last resort
        global $wpdb;
        $table_name = $wpdb->postmeta;
        
        // Check if meta exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$table_name} WHERE post_id = %d AND meta_key = %s",
            $watermark_id,
            'active'
        ));
        
        error_log('Ultimate Watermark Toggle - Existing meta ID: ' . ($existing ? $existing : 'NULL'));
        
        if ($existing) {
            // Update existing meta
            $result = $wpdb->update(
                $table_name,
                array('meta_value' => $meta_value),
                array('post_id' => $watermark_id, 'meta_key' => 'active'),
                array('%s'),
                array('%d', '%s')
            );
            error_log('Ultimate Watermark Toggle - Direct update result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'));
        } else {
            // Insert new meta
            $result = $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $watermark_id,
                    'meta_key' => 'active',
                    'meta_value' => $meta_value
                ),
                array('%d', '%s', '%s')
            );
            error_log('Ultimate Watermark Toggle - Direct insert result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'));
        }
        
        // Convert to boolean for consistency
        $result = ($result !== false);
        
        if ($result === false) {
            error_log('Ultimate Watermark Toggle - Failed to update meta');
            wp_send_json_error('Failed to update watermark status');
            return;
        }

        wp_send_json_success([
            'message' => 'Watermark status updated successfully',
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
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $watermark_ids = array_map('absint', $_POST['watermark_ids'] ?? []);
        if (empty($watermark_ids)) {
            wp_send_json_error('No watermarks selected');
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
        }

        wp_send_json_success([
            'message' => "{$deleted_count} watermarks deleted successfully",
            'deleted_count' => $deleted_count
        ]);
    }

    /**
     * Handle bulk activate watermarks
     */
    public function handleBulkActivate(): void
    {
        $this->handleBulkStatusUpdate($_POST['watermark_ids'] ?? [], 'active');
    }

    /**
     * Handle bulk deactivate watermarks
     */
    public function handleBulkDeactivate(): void
    {
        $this->handleBulkStatusUpdate($_POST['watermark_ids'] ?? [], 'inactive');
    }

    /**
     * Handle bulk status update
     */
    private function handleBulkStatusUpdate(array $watermark_ids, string $status): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $watermark_ids = array_map('absint', $watermark_ids);
        if (empty($watermark_ids)) {
            wp_send_json_error('No watermarks selected');
            return;
        }

        $updated_count = 0;
        $meta_value = $status === 'active' ? '1' : '0';
        
        foreach ($watermark_ids as $watermark_id) {
            // Always use update_post_meta - it will add if not exists, update if exists
            $result = update_post_meta($watermark_id, 'active', $meta_value);
            
            if ($result !== false) {
                $updated_count++;
            }
        }

        wp_send_json_success([
            'message' => "{$updated_count} watermarks {$status}d successfully",
            'updated_count' => $updated_count
        ]);
    }

}
