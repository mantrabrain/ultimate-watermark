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
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['ultimate_watermark_nonce'], 'ultimate_watermark_nonce')) {
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Ultimate Watermark Save Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => __('Failed to save watermark. Please try again.', 'ultimate-watermark')]);
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
        
        // Clean up old preview images for this watermark
        \MantraBrain\UltimateWatermark\Utils\PreviewManager::cleanupWatermarkPreviews($watermark_id);

        return $watermark_id;
    }

    /**
     * Save watermark meta data
     */
    private function saveWatermarkMeta($watermark_id, $data)
    {
        // Get form configuration to dynamically handle all fields
        $tabs_config = \MantraBrain\UltimateWatermark\Admin\Pages\AddWatermarkPage::getInstance()->getFormTabsConfig();
        
        foreach ($tabs_config as $tab_config) {
            foreach ($tab_config['sections'] as $section_config) {
                foreach ($section_config['fields'] as $field_name => $field_config) {
                    // Skip name and description as they are handled as post data
                    if (in_array($field_name, ['name', 'description'])) {
                        continue;
                    }
                    
                    
                    // Handle checkbox fields specially
                    if ($field_config['type'] === 'checkbox') {
                        // For checkboxes: if not set in data, it means unchecked (0)
                        $value = isset($data[$field_name]) && $data[$field_name] ? '1' : '0';
                        update_post_meta($watermark_id, $field_name, $value);
                    } elseif (isset($data[$field_name])) {
                        $value = $data[$field_name];
                        
                        // Sanitize based on field type
                        if (isset($field_config['sanitize_callback']) && is_callable($field_config['sanitize_callback'])) {
                            $value = call_user_func($field_config['sanitize_callback'], $value);
                        } else {
                            // Default sanitization
                            if (is_array($value)) {
                                $value = array_map('sanitize_text_field', $value);
                            } else {
                                $value = sanitize_text_field($value);
                            }
                        }
                        
                        update_post_meta($watermark_id, $field_name, $value);
                    }
                }
            }
        }
    }
}
