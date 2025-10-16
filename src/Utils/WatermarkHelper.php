<?php

namespace MantraBrain\UltimateWatermark\Utils;

use MantraBrain\UltimateWatermark\PostTypes\WatermarkPostType;

/**
 * Watermark Helper Utility
 * 
 * Provides utility methods for watermark operations
 */
class WatermarkHelper
{
    /**
     * Get all active watermarks
     * 
     * @return array
     */
    public static function getActiveWatermarks(): array
    {
        $watermarks = [];
        
        // Query only active watermarks from database
        $posts = get_posts([
            'post_type' => WatermarkPostType::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'active',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        foreach ($posts as $post) {
            $watermark_type = get_post_meta($post->ID, 'watermark_type', true) ?: 'text';
            $watermark_position = get_post_meta($post->ID, 'watermark_position', true) ?: 'bottom-right';
            $watermark_opacity = get_post_meta($post->ID, 'watermark_opacity', true) ?: 50;
            $watermark_image_id = get_post_meta($post->ID, 'watermark_image_id', true);
            $watermark_image_id = $watermark_image_id ? intval($watermark_image_id) : 0;
            
            // Get text watermark settings
            $watermark_text = get_post_meta($post->ID, 'watermark_text', true) ?: 'Watermark';
            $watermark_color = get_post_meta($post->ID, 'watermark_color', true) ?: '#000000';
            $watermark_font_size = get_post_meta($post->ID, 'watermark_font_size', true) ?: 24;
            $watermark_font_family = get_post_meta($post->ID, 'watermark_font_family', true) ?: 'Arial';
            $watermark_font_weight = get_post_meta($post->ID, 'watermark_font_weight', true) ?: 'normal';
            
            // Get size settings
            $watermark_size_type = get_post_meta($post->ID, 'watermark_size_type', true) ?: 'original';
            $watermark_scale_percentage = get_post_meta($post->ID, 'watermark_scale_percentage', true) ?: 80;
            $watermark_custom_width = get_post_meta($post->ID, 'watermark_custom_width', true) ?: 100;
            $watermark_custom_height = get_post_meta($post->ID, 'watermark_custom_height', true) ?: 100;
            
            // Get offset settings
            $watermark_offset_x = get_post_meta($post->ID, 'watermark_offset_x', true) ?: 0;
            $watermark_offset_y = get_post_meta($post->ID, 'watermark_offset_y', true) ?: 0;
            $watermark_offset_unit = get_post_meta($post->ID, 'watermark_offset_unit', true) ?: 'pixels';
            
            // Get rotation and quality settings
            $watermark_rotation = get_post_meta($post->ID, 'watermark_rotation', true) ?: 0;
            $watermark_quality = get_post_meta($post->ID, 'watermark_quality', true) ?: 90;
            $image_format = get_post_meta($post->ID, 'image_format', true) ?: 'baseline';
            
            // Get behavior settings
            $automatic_watermarking = get_post_meta($post->ID, 'automatic_watermarking', true) ?: '0';
            $manual_watermarking = get_post_meta($post->ID, 'manual_watermarking', true) ?: '0';
            $frontend_watermarking = get_post_meta($post->ID, 'frontend_watermarking', true) ?: '0';
            
            // Get rules settings
            $watermark_on = get_post_meta($post->ID, 'watermark_on', true) ?: 'everywhere';
            $watermark_post_types = get_post_meta($post->ID, 'watermark_post_types', true) ?: [];
            $watermark_sizes = get_post_meta($post->ID, 'watermark_sizes', true) ?: [];
            
            // Ensure arrays are properly formatted and fix double-serialized data
            if (is_string($watermark_post_types)) {
                $watermark_post_types = maybe_unserialize($watermark_post_types);
                // If still a string, try to unserialize again
                if (is_string($watermark_post_types)) {
                    $watermark_post_types = maybe_unserialize($watermark_post_types);
                }
                if (!is_array($watermark_post_types)) {
                    $watermark_post_types = [];
                }
            }
            if (is_string($watermark_sizes)) {
                $watermark_sizes = maybe_unserialize($watermark_sizes);
                // If still a string, try to unserialize again
                if (is_string($watermark_sizes)) {
                    $watermark_sizes = maybe_unserialize($watermark_sizes);
                }
                if (!is_array($watermark_sizes)) {
                    $watermark_sizes = [];
                }
            }
            
            $watermarks[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'description' => $post->post_content,
                'type' => $watermark_type,
                'watermark_type' => $watermark_type, // For compatibility
                'position' => $watermark_position,
                'watermark_position' => $watermark_position, // For compatibility
                'opacity' => $watermark_opacity,
                'watermark_opacity' => $watermark_opacity, // For compatibility
                'active' => true, // All watermarks returned by this method are active
                
                // Text watermark settings
                'watermark_text' => $watermark_text,
                'watermark_color' => $watermark_color,
                'watermark_font_size' => $watermark_font_size,
                'watermark_font_family' => $watermark_font_family,
                'watermark_font_weight' => $watermark_font_weight,
                
                // Image watermark settings
                'watermark_image_id' => $watermark_image_id,
                
                // Size settings
                'watermark_size_type' => $watermark_size_type,
                'watermark_scale_percentage' => $watermark_scale_percentage,
                'watermark_custom_width' => $watermark_custom_width,
                'watermark_custom_height' => $watermark_custom_height,
                
                // Offset settings
                'watermark_offset_x' => $watermark_offset_x,
                'watermark_offset_y' => $watermark_offset_y,
                'watermark_offset_unit' => $watermark_offset_unit,
                
                // Rotation and quality
                'watermark_rotation' => $watermark_rotation,
                'watermark_quality' => $watermark_quality,
                'image_format' => $image_format,
                
                // Behavior settings
                'automatic_watermarking' => $automatic_watermarking,
                'manual_watermarking' => $manual_watermarking,
                'frontend_watermarking' => $frontend_watermarking,
                'watermark_on' => $watermark_on,
                'watermark_post_types' => $watermark_post_types,
                'watermark_sizes' => $watermark_sizes
            ];
        }
        
        return $watermarks;
    }

    /**
     * Get active watermarks for automatic watermarking
     * 
     * @param string $context Context where watermark is being applied
     * @param int|null $post_id Post ID if applicable
     * @param string $image_size Image size being processed
     * @return array
     */
    public static function getActiveAutomaticWatermarks(string $context = 'upload', ?int $post_id = null, string $image_size = 'full'): array
    {
        $all_active = self::getActiveWatermarks();
        
        // First filter by behavior (automatic watermarking)
        $automatic_watermarks = array_filter($all_active, function($watermark) {
            return $watermark['automatic_watermarking'] === '1';
        });
        
        // Then filter by rules (post types and image sizes)
        return self::filterWatermarksByRules($automatic_watermarks, $context, $post_id, $image_size);
    }

    /**
     * Get active watermarks for manual watermarking
     * 
     * @param string $context Context where watermark is being applied
     * @param int|null $post_id Post ID if applicable
     * @param string $image_size Image size being processed
     * @return array
     */
    public static function getActiveManualWatermarks(string $context = 'manual', ?int $post_id = null, string $image_size = 'full'): array
    {
        $all_active = self::getActiveWatermarks();
        
        // First filter by behavior (manual watermarking)
        $manual_watermarks = array_filter($all_active, function($watermark) {
            return $watermark['manual_watermarking'] === '1';
        });
        
        // Then filter by rules (post types and image sizes)
        return self::filterWatermarksByRules($manual_watermarks, $context, $post_id, $image_size);
    }

    /**
     * Get active watermarks for frontend watermarking
     * 
     * @param string $context Context where watermark is being applied
     * @param int|null $post_id Post ID if applicable
     * @param string $image_size Image size being processed
     * @return array
     */
    public static function getActiveFrontendWatermarks(string $context = 'frontend', ?int $post_id = null, string $image_size = 'full'): array
    {
        $all_active = self::getActiveWatermarks();
        
        // First filter by behavior (frontend watermarking)
        $frontend_watermarks = array_filter($all_active, function($watermark) {
            return $watermark['frontend_watermarking'] === '1';
        });
        
        // Then filter by rules (post types and image sizes)
        return self::filterWatermarksByRules($frontend_watermarks, $context, $post_id, $image_size);
    }

    /**
     * Check if a watermark is active
     * 
     * @param int $watermark_id
     * @return bool
     */
    public static function isWatermarkActive(int $watermark_id): bool
    {
        $active_meta = get_post_meta($watermark_id, 'active', true);
        return ($active_meta === '1' || $active_meta === 'true' || $active_meta === true);
    }

    /**
     * Get watermark data by ID (only if active)
     * 
     * @param int $watermark_id
     * @return array|null
     */
    public static function getActiveWatermarkById(int $watermark_id): ?array
    {
        if (!self::isWatermarkActive($watermark_id)) {
            return null;
        }

        $post = get_post($watermark_id);
        if (!$post || $post->post_type !== WatermarkPostType::POST_TYPE) {
            return null;
        }

        $watermark_type = get_post_meta($post->ID, 'watermark_type', true) ?: 'text';
        $watermark_position = get_post_meta($post->ID, 'watermark_position', true) ?: 'bottom-right';
        $watermark_opacity = get_post_meta($post->ID, 'watermark_opacity', true) ?: 50;
        $watermark_image_id = get_post_meta($post->ID, 'watermark_image_id', true);
        $watermark_image_id = $watermark_image_id ? intval($watermark_image_id) : 0;
        
        // Get text watermark settings
        $watermark_text = get_post_meta($post->ID, 'watermark_text', true) ?: 'Watermark';
        $watermark_color = get_post_meta($post->ID, 'watermark_color', true) ?: '#000000';
        $watermark_font_size = get_post_meta($post->ID, 'watermark_font_size', true) ?: 24;
        $watermark_font_family = get_post_meta($post->ID, 'watermark_font_family', true) ?: 'Arial';
        $watermark_font_weight = get_post_meta($post->ID, 'watermark_font_weight', true) ?: 'normal';
        $watermark_font_style = get_post_meta($post->ID, 'watermark_font_style', true) ?: 'normal';
        $watermark_text_decoration = get_post_meta($post->ID, 'watermark_text_decoration', true) ?: 'none';
        
        // Get image watermark settings
        $watermark_image_path = '';
        if ($watermark_image_id) {
            $watermark_image_path = get_attached_file($watermark_image_id);
        }
        
        // Get size settings
        $watermark_size_type = get_post_meta($post->ID, 'watermark_size_type', true) ?: 'original';
        $watermark_scale_percentage = get_post_meta($post->ID, 'watermark_scale_percentage', true) ?: 50;
        $watermark_custom_width = get_post_meta($post->ID, 'watermark_custom_width', true) ?: 100;
        $watermark_custom_height = get_post_meta($post->ID, 'watermark_custom_height', true) ?: 100;
        
        // Get offset settings
        $watermark_offset_x = get_post_meta($post->ID, 'watermark_offset_x', true) ?: 10;
        $watermark_offset_y = get_post_meta($post->ID, 'watermark_offset_y', true) ?: 10;
        $watermark_offset_unit = get_post_meta($post->ID, 'offset_unit', true) ?: 'pixels';
        
        // Get rotation and quality settings
        $watermark_rotation = get_post_meta($post->ID, 'watermark_rotation', true) ?: 0;
        $watermark_quality = get_post_meta($post->ID, 'watermark_quality', true) ?: 90;
        $image_format = get_post_meta($post->ID, 'image_format', true) ?: 'baseline';
        
        // Get behavior settings
        $automatic_watermarking = get_post_meta($post->ID, 'automatic_watermarking', true) ?: '0';
        $manual_watermarking = get_post_meta($post->ID, 'manual_watermarking', true) ?: '0';
        $frontend_watermarking = get_post_meta($post->ID, 'frontend_watermarking', true) ?: '0';
        
        // Get rules settings
        $watermark_on = get_post_meta($post->ID, 'watermark_on', true) ?: 'everywhere';
        $watermark_post_types = get_post_meta($post->ID, 'watermark_post_types', true) ?: [];
        $watermark_sizes = get_post_meta($post->ID, 'watermark_sizes', true) ?: [];
        
        // Ensure arrays are properly formatted and fix double-serialized data
        if (is_string($watermark_post_types)) {
            $watermark_post_types = maybe_unserialize($watermark_post_types);
            // If still a string, try to unserialize again
            if (is_string($watermark_post_types)) {
                $watermark_post_types = maybe_unserialize($watermark_post_types);
            }
            if (!is_array($watermark_post_types)) {
                $watermark_post_types = [];
            }
        }
        if (is_string($watermark_sizes)) {
            $watermark_sizes = maybe_unserialize($watermark_sizes);
            // If still a string, try to unserialize again
            if (is_string($watermark_sizes)) {
                $watermark_sizes = maybe_unserialize($watermark_sizes);
            }
            if (!is_array($watermark_sizes)) {
                $watermark_sizes = [];
            }
        }
        
        return [
            'id' => $post->ID,
            'name' => $post->post_title,
            'description' => $post->post_content,
            'type' => $watermark_type,
            'watermark_type' => $watermark_type, // For compatibility
            'position' => $watermark_position,
            'watermark_position' => $watermark_position, // For compatibility
            'opacity' => $watermark_opacity,
            'watermark_opacity' => $watermark_opacity, // For compatibility
            'active' => true,
            
            // Text watermark settings
            'watermark_text' => $watermark_text,
            'watermark_color' => $watermark_color,
            'watermark_font_size' => $watermark_font_size,
            'watermark_font_family' => $watermark_font_family,
            'watermark_font_weight' => $watermark_font_weight,
            'watermark_font_style' => $watermark_font_style,
            'watermark_text_decoration' => $watermark_text_decoration,
            
            // Image watermark settings
            'watermark_image_id' => $watermark_image_id,
            'watermark_image_path' => $watermark_image_path,
            
            // Size settings
            'watermark_size_type' => $watermark_size_type,
            'watermark_scale_percentage' => $watermark_scale_percentage,
            'watermark_custom_width' => $watermark_custom_width,
            'watermark_custom_height' => $watermark_custom_height,
            
            // Offset settings
            'watermark_offset_x' => $watermark_offset_x,
            'watermark_offset_y' => $watermark_offset_y,
            'offset_unit' => $watermark_offset_unit,
            
            // Rotation and quality
            'watermark_rotation' => $watermark_rotation,
            'watermark_quality' => $watermark_quality,
            'image_format' => $image_format,
            
            // Behavior settings
            'automatic_watermarking' => $automatic_watermarking,
            'manual_watermarking' => $manual_watermarking,
            'frontend_watermarking' => $frontend_watermarking,
            'watermark_on' => $watermark_on,
            'watermark_post_types' => $watermark_post_types,
            'watermark_sizes' => $watermark_sizes
        ];
    }

    /**
     * Check if watermark should be applied based on post type rules
     * 
     * @param array $watermark
     * @param string $context Context where watermark is being applied ('upload', 'manual', 'frontend')
     * @param int|null $post_id Post ID if applicable
     * @return bool
     */
    public static function shouldApplyWatermarkByPostType(array $watermark, string $context = 'upload', ?int $post_id = null): bool
    {
        $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
        
        // If set to "everywhere", apply to all contexts
        if ($watermark_on === 'everywhere') {
            return true;
        }
        
        // If set to "selected_post_types", check the specific post types
        if ($watermark_on === 'selected_post_types') {
            $allowed_post_types = $watermark['watermark_post_types'] ?? [];
            
            // For upload context, we need to determine the post type
            if ($context === 'upload' && $post_id) {
                $post_type = get_post_type($post_id);
                return in_array($post_type, $allowed_post_types);
            }
            
            // For manual context, assume it's for media library (attachment post type)
            if ($context === 'manual') {
                return in_array('attachment', $allowed_post_types);
            }
            
            // For frontend context, we'd need to determine the post type from context
            if ($context === 'frontend' && $post_id) {
                $post_type = get_post_type($post_id);
                return in_array($post_type, $allowed_post_types);
            }
        }
        
        return false;
    }

    /**
     * Check if watermark should be applied based on image size rules
     * 
     * @param array $watermark
     * @param string $image_size Image size being processed
     * @return bool
     */
    public static function shouldApplyWatermarkByImageSize(array $watermark, string $image_size = 'full'): bool
    {
        $watermark_sizes = $watermark['watermark_sizes'] ?? [];
        
        // Fix double-serialized data
        if (is_string($watermark_sizes)) {
            $watermark_sizes = maybe_unserialize($watermark_sizes);
            // If still a string, try to unserialize again
            if (is_string($watermark_sizes)) {
                $watermark_sizes = maybe_unserialize($watermark_sizes);
            }
        }
        
        // Ensure it's an array
        if (!is_array($watermark_sizes)) {
            $watermark_sizes = [];
        }
        
        // If no sizes specified, apply to all sizes
        if (empty($watermark_sizes)) {
            return true;
        }
        
        // Check if the current image size is in the allowed sizes
        return in_array($image_size, $watermark_sizes);
    }

    /**
     * Filter watermarks based on rules
     * 
     * @param array $watermarks
     * @param string $context
     * @param int|null $post_id
     * @param string $image_size
     * @return array
     */
    public static function filterWatermarksByRules(array $watermarks, string $context = 'upload', ?int $post_id = null, string $image_size = 'full'): array
    {
        return array_filter($watermarks, function($watermark) use ($context, $post_id, $image_size) {
            // Check post type rules
            if (!self::shouldApplyWatermarkByPostType($watermark, $context, $post_id)) {
                return false;
            }
            
            // Check image size rules
            if (!self::shouldApplyWatermarkByImageSize($watermark, $image_size)) {
                return false;
            }
            
            return true;
        });
    }
}
