<?php

namespace MantraBrain\UltimateWatermark\Ajax;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\PostTypes\WatermarkPostType;

/**
 * Watermark AJAX Handler Class
 * 
 * Handles AJAX requests for watermark operations
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkAjaxHandler
{
    use SingletonTrait;

    /**
     * Initialize AJAX handlers
     */
    public function init()
    {
        add_action('wp_ajax_ultimate_watermark_save', [$this, 'handleSaveWatermark']);
    }

    /**
     * Handle save watermark AJAX request
     */
    public function handleSaveWatermark()
    {
        // Debug logging
        error_log('WatermarkAjaxHandler: Save watermark request received');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('Nonce received: ' . ($_POST['ultimate_watermark_nonce'] ?? 'none'));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['ultimate_watermark_nonce'], 'ultimate_watermark_nonce')) {
            error_log('WatermarkAjaxHandler: Nonce verification failed');
            wp_send_json_error(['message' => __('Security check failed.', 'ultimate-watermark')]);
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'ultimate-watermark')]);
        }

        try {
            $is_update = isset($_POST['watermark_id']) && intval($_POST['watermark_id']) > 0;
            $watermark_id = $this->saveWatermark($_POST);
            
            if ($watermark_id) {
                $response = [
                    'message' => $is_update ? __('Watermark updated successfully!', 'ultimate-watermark') : __('Watermark created successfully!', 'ultimate-watermark'),
                    'watermark_id' => $watermark_id
                ];
                
                // Only redirect to listing page for new watermarks, not updates
                if (!$is_update) {
                    $response['redirect_url'] = admin_url('admin.php?page=ultimate-watermark-watermarks');
                }
                
                wp_send_json_success($response);
            } else {
                wp_send_json_error(['message' => __('Failed to save watermark.', 'ultimate-watermark')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Save watermark data
     */
    private function saveWatermark($data)
    {
        $watermark_id = isset($data['watermark_id']) ? intval($data['watermark_id']) : 0;
        
        // Prepare post data
        $post_data = [
            'post_title' => sanitize_text_field($data['name']),
            'post_content' => sanitize_textarea_field($data['description']),
            'post_type' => WatermarkPostType::POST_TYPE,
            'post_status' => 'publish',
        ];

        if ($watermark_id > 0) {
            $post_data['ID'] = $watermark_id;
            $watermark_id = wp_update_post($post_data);
        } else {
            $watermark_id = wp_insert_post($post_data);
        }

        if (is_wp_error($watermark_id)) {
            throw new \Exception($watermark_id->get_error_message());
        }

        // Save meta data
        $this->saveWatermarkMeta($watermark_id, $data);
        
        // Set default active status if not provided
        if (!isset($data['active'])) {
            update_post_meta($watermark_id, 'active', '1'); // Default to active
        }
        
        // Clear cached preview to force regeneration
        delete_post_meta($watermark_id, 'preview_url');

        return $watermark_id;
    }

    /**
     * Save watermark meta data
     */
    private function saveWatermarkMeta($watermark_id, $data)
    {
        $meta_fields = [
            'watermark_type', 'watermark_text', 'watermark_font_size', 'watermark_color',
            'watermark_font_family', 'watermark_font_weight', 'watermark_font_style', 'watermark_text_decoration',
            'watermark_image_id', 'watermark_position', 'watermark_opacity', 'watermark_rotation',
            'watermark_offset_x', 'watermark_offset_y', 'offset_unit',
            'watermark_size_type', 'watermark_custom_width', 'watermark_custom_height',
            'watermark_scale_percentage', 'watermark_quality', 'image_format',
            'automatic_watermarking', 'manual_watermarking', 'frontend_watermarking',
            'watermark_on', 'watermark_post_types', 'watermark_sizes',
            'backup_full_size', 'backup_quality'
        ];

        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                if (is_array($value)) {
                    $value = array_map('sanitize_text_field', $value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                update_post_meta($watermark_id, $field, $value);
            }
        }
    }
}
