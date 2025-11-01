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
        
        // Query all published watermarks from database
        $posts = get_posts([
            'post_type' => WatermarkPostType::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
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
            $is_automatic = $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true ;
            return $is_automatic;
        });
        
        
        
        // Then filter by rules (post types and image sizes)
        $filtered_watermarks = self::filterWatermarksByRules($automatic_watermarks, $context, $post_id, $image_size);
        
        return $filtered_watermarks;
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
        
        // Normalize watermark_on value
        $watermark_on = strval($watermark_on);
        
        // If set to "everywhere", apply to all contexts
        if ($watermark_on === 'everywhere' || $watermark_on === '') {
            return true;
        }
        
        // If set to "selected_post_types", check the specific post types
        if ($watermark_on === 'selected_post_types') {
            $allowed_post_types = $watermark['watermark_post_types'] ?? [];
            
            // Fix double-serialized data
            if (is_string($allowed_post_types)) {
                $allowed_post_types = maybe_unserialize($allowed_post_types);
                // If still a string, try to unserialize again
                if (is_string($allowed_post_types)) {
                    $allowed_post_types = maybe_unserialize($allowed_post_types);
                }
            }
            
            // Ensure it's an array
            if (!is_array($allowed_post_types)) {
                $allowed_post_types = [];
            }
            
            // Normalize post types to strings
            $allowed_post_types = array_map('strval', array_values($allowed_post_types));
            
            // If no post types selected, don't apply (safer than applying everywhere)
            if (empty($allowed_post_types)) {
                return false;
            }
            
            // For upload context, check both 'attachment' (for media library uploads) 
            // and parent post type (for uploads from page/post editor)
            if ($context === 'upload') {
                // First check if 'attachment' is in allowed post types (for standalone media library uploads)
                if (in_array('attachment', $allowed_post_types, true)) {
                    return true;
                }
                
                // If we have a post_id (attachment_id), check its parent post type
                // This handles cases where image is uploaded to a page/post
                if ($post_id && $post_id > 0) {
                    // Get parent post_id from stored meta (saved during upload)
                    $parent_post_id = get_post_meta($post_id, '_ulwm_uploaded_to_post_id', true);
                    
                    // Fallback: check attachment's post_parent
                    if (!$parent_post_id || $parent_post_id <= 0) {
                        $attachment = get_post($post_id);
                        if ($attachment && $attachment->post_parent > 0) {
                            $parent_post_id = $attachment->post_parent;
                        }
                    }
                    
                    // Also check $_POST/$_REQUEST as immediate fallback during upload
                    if ((!$parent_post_id || $parent_post_id <= 0)) {
                        if (isset($_POST['post_id']) && $_POST['post_id'] > 0) {
                            $parent_post_id = absint($_POST['post_id']);
                        } elseif (isset($_REQUEST['post_id']) && $_REQUEST['post_id'] > 0) {
                            $parent_post_id = absint($_REQUEST['post_id']);
                        }
                    }
                    
                    // Check parent post type if we found a parent
                    if ($parent_post_id > 0) {
                        $parent_post = get_post($parent_post_id);
                        if ($parent_post) {
                            $parent_post_type = $parent_post->post_type;
                            // Check if parent post type is in allowed types
                            if (in_array(strval($parent_post_type), $allowed_post_types, true)) {
                                return true;
                            }
                        }
                    }
                }
                
                // No match found - return false
                return false;
            }
            
            // For manual context, assume it's for media library (attachment post type)
            if ($context === 'manual') {
                return in_array('attachment', $allowed_post_types, true);
            }
            
            // For frontend context, check the post type of the post_id
            if ($context === 'frontend' && $post_id) {
                $post_type = get_post_type($post_id);
                if (!$post_type) {
                    return false;
                }
                return in_array(strval($post_type), $allowed_post_types, true);
            }
            
            // For other contexts, default to false (safer)
            return false;
        }
        
        // Unknown watermark_on value, default to false (safer)
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
            // If still a string, try to unserialize again (fix double-serialization)
            if (is_string($watermark_sizes)) {
                $watermark_sizes = maybe_unserialize($watermark_sizes);
            }
        }
        
        // Ensure it's an array
        if (!is_array($watermark_sizes)) {
            $watermark_sizes = [];
        }
        
        // Normalize array keys (remove numeric keys if any, ensure values are strings)
        $watermark_sizes = array_map('strval', array_values($watermark_sizes));
        $image_size = strval($image_size);
        
        // If no sizes specified, apply to all sizes
        if (empty($watermark_sizes)) {
            return true;
        }
        
        // Check if the current image size is in the allowed sizes (strict comparison)
        $result = in_array($image_size, $watermark_sizes, true);
        
        // Special case: if watermark_sizes contains 'full', also allow 'scaled' variants
        // WordPress sometimes creates scaled images (e.g., image-scaled-2048x1536.jpg) for large images
        if (!$result && in_array('full', $watermark_sizes, true)) {
            // Check if this is a scaled variant of full size
            if (strpos($image_size, 'scaled') !== false || preg_match('/-\d+x\d+$/', $image_size)) {
                return true;
            }
        }
        
        return $result;
    }

    /**
     * Filter watermarks based on rules
     * 
     * This is the core method that applies ALL rule filtering:
     * 1. Post type rules (watermark_on + watermark_post_types)
     * 2. Image size rules (watermark_sizes)
     * 
     * Both conditions must pass for a watermark to be included.
     * 
     * @param array $watermarks Array of watermark data
     * @param string $context Context where watermark is being applied ('upload', 'manual', 'frontend')
     * @param int|null $post_id Post/Attachment ID if applicable (for frontend context)
     * @param string $image_size Image size being processed ('full', 'thumbnail', 'medium', etc.)
     * @return array Filtered watermarks that match all rules
     */
    public static function filterWatermarksByRules(array $watermarks, string $context = 'upload', ?int $post_id = null, string $image_size = 'full'): array
    {
        // Validate context
        $valid_contexts = ['upload', 'manual', 'frontend'];
        if (!in_array($context, $valid_contexts, true)) {
            $context = 'upload'; // Default fallback
        }
        
        // Normalize image size
        $image_size = strval($image_size);
        if (empty($image_size)) {
            $image_size = 'full'; // Default fallback
        }
        
        return array_filter($watermarks, function($watermark) use ($context, $post_id, $image_size) {
            // Validate watermark data structure
            if (!is_array($watermark) || empty($watermark)) {
                return false;
            }
            
            // STEP 1: Check post type rules FIRST (most restrictive filter)
            $post_type_check = self::shouldApplyWatermarkByPostType($watermark, $context, $post_id);
            
            if (!$post_type_check) {
                return false; // Watermark doesn't match post type rules, exclude it
            }
            
            // STEP 2: Check image size rules (size-specific filter)
            $size_check = self::shouldApplyWatermarkByImageSize($watermark, $image_size);
            
            if (!$size_check) {
                return false; // Watermark doesn't match size rules, exclude it
            }
            
            // Both checks passed - include this watermark
            return true;
        });
    }
}
