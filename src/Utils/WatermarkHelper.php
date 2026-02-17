<?php

namespace MantraBrain\UltimateWatermark\Utils;

use MantraBrain\UltimateWatermark\PostTypes\WatermarkPostType;

/**
 * Watermark Helper Utility
 * 
 * Provides utility methods for watermark operations with comprehensive error handling,
 * caching, and performance optimizations for enterprise-level applications.
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkHelper
{
    /**
     * Cache key prefix for watermark data
     */
    private const CACHE_PREFIX = 'ultimate_watermark_';
    
    /**
     * Cache expiration time in seconds (1 hour)
     */
    private const CACHE_EXPIRATION = 3600;

    /**
     * Get all active watermarks with caching and validation
     * 
     * @param bool $use_cache Whether to use cached data
     * @return array
     */
    public static function getActiveWatermarks(bool $use_cache = true): array
    {
        try {
            $cache_key = self::CACHE_PREFIX . 'active_watermarks';
            
            // Try to get from cache first
            if ($use_cache) {
                $cached = wp_cache_get($cache_key, 'ultimate-watermark');
                if (is_array($cached)) {
                    return $cached;
                }
            }

            $watermarks = [];
            
            // Query all published watermarks from database with error handling
            $posts = get_posts([
                'post_type' => WatermarkPostType::POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'suppress_filters' => false // Allow plugins to modify query
            ]);
            
            if (!is_array($posts)) {
                error_log('Ultimate Watermark: Failed to query watermarks from database');
                return [];
            }

            foreach ($posts as $post) {
                try {
                    $watermark_data = self::buildWatermarkData($post);
                    if ($watermark_data !== null) {
                        $watermarks[] = $watermark_data;
                    }
                } catch (\Exception $e) {
                    error_log('Ultimate Watermark: Error building watermark data for post ' . $post->ID . ': ' . $e->getMessage());
                    continue;
                }
            }
            
            // Cache the results
            if ($use_cache) {
                wp_cache_set($cache_key, $watermarks, 'ultimate-watermark', self::CACHE_EXPIRATION);
            }
            
            return $watermarks;

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Critical error in getActiveWatermarks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build watermark data from post with validation
     */
    private static function buildWatermarkData(\WP_Post $post): ?array
    {
        if (!isset($post->ID) || $post->post_type !== WatermarkPostType::POST_TYPE) {
            return null;
        }

        // Get and validate all watermark metadata
        $metadata = self::getWatermarkMetadata($post->ID);
        
        if (!is_array($metadata)) {
            return null;
        }

        // Build watermark data array with proper defaults and validation
        $watermark_data = [
            'id' => $post->ID,
            'name' => sanitize_text_field($post->post_title),
            'description' => sanitize_textarea_field($post->post_content),
            'type' => $metadata['watermark_type'],
            'watermark_type' => $metadata['watermark_type'], // For compatibility
            'position' => $metadata['watermark_position'],
            'watermark_position' => $metadata['watermark_position'], // For compatibility
            'opacity' => $metadata['watermark_opacity'],
            'watermark_opacity' => $metadata['watermark_opacity'], // For compatibility
            'active' => true, // All watermarks returned by this method are active
            
            // Text watermark settings
            'watermark_text' => $metadata['watermark_text'],
            'watermark_color' => $metadata['watermark_color'],
            'watermark_font_size' => $metadata['watermark_font_size'],
            'watermark_font_family' => $metadata['watermark_font_family'],
            'watermark_font_weight' => $metadata['watermark_font_weight'],
            
            // Image watermark settings
            'watermark_image_id' => $metadata['watermark_image_id'],
            
            // Size settings
            'watermark_size_type' => $metadata['watermark_size_type'],
            'watermark_scale_percentage' => $metadata['watermark_scale_percentage'],
            'watermark_custom_width' => $metadata['watermark_custom_width'],
            'watermark_custom_height' => $metadata['watermark_custom_height'],
            
            // Offset settings
            'watermark_offset_x' => $metadata['watermark_offset_x'],
            'watermark_offset_y' => $metadata['watermark_offset_y'],
            'watermark_offset_unit' => $metadata['watermark_offset_unit'],
            
            // Rotation and quality
            'watermark_rotation' => $metadata['watermark_rotation'],
            'watermark_quality' => $metadata['watermark_quality'],
            'image_format' => $metadata['image_format'],
            
            // Behavior settings
            'automatic_watermarking' => $metadata['automatic_watermarking'],
            'manual_watermarking' => $metadata['manual_watermarking'],
            'frontend_watermarking' => $metadata['frontend_watermarking'],
            'watermark_on' => $metadata['watermark_on'],
            'watermark_post_types' => $metadata['watermark_post_types'],
            'watermark_sizes' => $metadata['watermark_sizes'],
            
            // Unified rules (conditions-based)
            'watermark_rules' => $metadata['watermark_rules']
        ];

        // Apply filters for extensibility
        return apply_filters('ultimate_watermark_build_data', $watermark_data, $post);
    }

    /**
     * Get and validate watermark metadata
     */
    private static function getWatermarkMetadata(int $post_id): array
    {
        $defaults = [
            'watermark_type' => 'text',
            'watermark_position' => 'bottom-right',
            'watermark_opacity' => 50,
            'watermark_image_id' => 0,
            'watermark_text' => 'Watermark',
            'watermark_color' => '#000000',
            'watermark_font_size' => 24,
            'watermark_font_family' => 'Arial',
            'watermark_font_weight' => 'normal',
            'watermark_size_type' => 'original',
            'watermark_scale_percentage' => 80,
            'watermark_custom_width' => 100,
            'watermark_custom_height' => 100,
            'watermark_offset_x' => 0,
            'watermark_offset_y' => 0,
            'watermark_offset_unit' => 'pixels',
            'watermark_rotation' => 0,
            'watermark_quality' => 90,
            'image_format' => 'baseline',
            'automatic_watermarking' => '0',
            'manual_watermarking' => '0',
            'frontend_watermarking' => '0',
            'watermark_on' => 'everywhere',
            'watermark_post_types' => [],
            'watermark_sizes' => [],
            'watermark_rules' => []
        ];

        $metadata = [];
        
        foreach ($defaults as $key => $default) {
            $value = get_post_meta($post_id, $key, true);
            
            if ($value === '' || $value === false) {
                $metadata[$key] = $default;
            } else {
                // Validate and sanitize based on type
                $metadata[$key] = self::validateMetadataValue($key, $value, $default);
            }
        }

        return $metadata;
    }

    /**
     * Validate and sanitize metadata value
     */
    private static function validateMetadataValue(string $key, $value, $default)
    {
        switch ($key) {
            case 'watermark_image_id':
                return is_numeric($value) ? absint($value) : $default;
            
            case 'watermark_opacity':
            case 'watermark_font_size':
            case 'watermark_scale_percentage':
            case 'watermark_custom_width':
            case 'watermark_custom_height':
            case 'watermark_offset_x':
            case 'watermark_offset_y':
            case 'watermark_rotation':
            case 'watermark_quality':
                return is_numeric($value) ? max(0, min(9999, intval($value))) : $default;
            
            case 'watermark_color':
                return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $default;
            
            case 'watermark_type':
            case 'watermark_position':
            case 'watermark_font_family':
            case 'watermark_font_weight':
            case 'watermark_size_type':
            case 'watermark_offset_unit':
            case 'image_format':
            case 'watermark_on':
                return in_array($value, self::getValidOptions($key), true) ? $value : $default;
            
            case 'automatic_watermarking':
            case 'manual_watermarking':
            case 'frontend_watermarking':
                return $value === '1' || $value === 'true' || $value === true ? '1' : '0';
            
            case 'watermark_post_types':
            case 'watermark_sizes':
                return self::validateArrayMetadata($value);
            
            case 'watermark_rules':
                // Rules are stored as serialized array; ensure we return an array
                if (is_string($value)) {
                    $unserialized = maybe_unserialize($value);
                    return is_array($unserialized) ? $unserialized : [];
                }
                return is_array($value) ? $value : [];
            
            case 'watermark_text':
                return sanitize_text_field($value);
            
            default:
                return $value;
        }
    }

    /**
     * Get valid options for metadata fields
     */
    private static function getValidOptions(string $key): array
    {
        $options = [
            'watermark_type' => ['text', 'image'],
            'watermark_position' => ['top_left', 'top_center', 'top_right', 'middle_left', 'middle_center', 'middle_right', 'bottom_left', 'bottom_center', 'bottom_right'],
            'watermark_font_family' => ['Arial', 'Times New Roman', 'Georgia', 'Verdana', 'Helvetica', 'Courier New'],
            'watermark_font_weight' => ['normal', 'bold', 'lighter'],
            'watermark_size_type' => ['original', 'custom', 'scaled'],
            'watermark_offset_unit' => ['pixels', 'percentage'],
            'image_format' => ['baseline', 'progressive'],
            'watermark_on' => ['everywhere', 'selected_post_types']
        ];

        return $options[$key] ?? [];
    }

    /**
     * Validate array metadata with proper unserialization
     */
    private static function validateArrayMetadata($value): array
    {
        if (is_array($value)) {
            return array_filter($value, 'is_string');
        }

        if (is_string($value)) {
            // Handle double-serialized data
            $unserialized = maybe_unserialize($value);
            if (is_string($unserialized)) {
                $unserialized = maybe_unserialize($unserialized);
            }
            
            if (is_array($unserialized)) {
                return array_filter($unserialized, 'is_string');
            }
        }

        return [];
    }

    /**
     * Get active watermarks for automatic watermarking with caching
     * 
     * @param string $context Context where watermark is being applied
     * @param int|null $post_id Post ID if applicable
     * @param string $image_size Image size being processed
     * @return array
     */
    public static function getActiveAutomaticWatermarks(string $context = 'upload', ?int $post_id = null, string $image_size = 'full'): array
    {
        try {
            $all_active = self::getActiveWatermarks();
            
            // Filter by behavior (automatic watermarking)
            $automatic_watermarks = array_filter($all_active, function($watermark) {
                $is_automatic = $watermark['automatic_watermarking'] === '1' || (boolean)$watermark['automatic_watermarking'] === true;
                return $is_automatic;
            });
            
            // Filter by rules (post types and image sizes)
            return self::filterWatermarksByRules($automatic_watermarks, $context, $post_id, $image_size);

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error in getActiveAutomaticWatermarks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get active watermarks for manual watermarking with caching
     * 
     * @param string $context Context where watermark is being applied
     * @param int|null $post_id Post ID if applicable
     * @param string $image_size Image size being processed
     * @return array
     */
    public static function getActiveManualWatermarks(string $context = 'manual', ?int $post_id = null, string $image_size = 'full'): array
    {
        try {
            $all_active = self::getActiveWatermarks();
            
            // Filter by behavior (manual watermarking)
            $manual_watermarks = array_filter($all_active, function($watermark) {
                return $watermark['manual_watermarking'] === '1';
            });
            
            // Filter by rules (post types and image sizes)
            return self::filterWatermarksByRules($manual_watermarks, $context, $post_id, $image_size);

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error in getActiveManualWatermarks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get active watermarks for frontend watermarking with caching
     * 
     * @param string $context Context where watermark is being applied
     * @param int|null $post_id Post ID if applicable
     * @param string $image_size Image size being processed
     * @return array
     */
    public static function getActiveFrontendWatermarks(string $context = 'frontend', ?int $post_id = null, string $image_size = 'full'): array
    {
        try {
            $all_active = self::getActiveWatermarks();
            
            // Filter by behavior (frontend watermarking)
            $frontend_watermarks = array_filter($all_active, function($watermark) {
                return $watermark['frontend_watermarking'] === '1';
            });
            
            // Filter by rules (post types and image sizes)
            return self::filterWatermarksByRules($frontend_watermarks, $context, $post_id, $image_size);

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error in getActiveFrontendWatermarks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a watermark is active with validation
     * 
     * @param int $watermark_id
     * @return bool
     */
    public static function isWatermarkActive(int $watermark_id): bool
    {
        try {
            if ($watermark_id <= 0) {
                return false;
            }

            $active_meta = get_post_meta($watermark_id, 'active', true);
            return ($active_meta === '1' || $active_meta === 'true' || $active_meta === true);

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error checking watermark activation status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get watermark data by ID (only if active) with caching
     * 
     * @param int $watermark_id
     * @return array|null
     */
    public static function getActiveWatermarkById(int $watermark_id): ?array
    {
        try {
            if (!self::isWatermarkActive($watermark_id)) {
                return null;
            }

            // Check cache first
            $cache_key = self::CACHE_PREFIX . 'watermark_' . $watermark_id;
            $cached = wp_cache_get($cache_key, 'ultimate-watermark');
            if (is_array($cached)) {
                return $cached;
            }

            $post = get_post($watermark_id);
            if (!$post || $post->post_type !== WatermarkPostType::POST_TYPE) {
                return null;
            }

            $watermark_data = self::buildWatermarkData($post);
            
            if ($watermark_data !== null) {
                // Cache the result
                wp_cache_set($cache_key, $watermark_data, 'ultimate-watermark', self::CACHE_EXPIRATION);
            }

            return $watermark_data;

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error getting watermark by ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if watermark should be applied based on post type rules with enhanced validation
     * 
     * @param array $watermark
     * @param string $context Context where watermark is being applied ('upload', 'manual', 'frontend')
     * @param int|null $post_id Post ID if applicable
     * @return bool
     */
    public static function shouldApplyWatermarkByPostType(array $watermark, string $context = 'upload', ?int $post_id = null): bool
    {
        try {
            $watermark_on = $watermark['watermark_on'] ?? 'everywhere';
            
            // Validate and normalize watermark_on value
            $watermark_on = strval($watermark_on);
            if (!in_array($watermark_on, ['everywhere', 'selected_post_types'], true)) {
                $watermark_on = 'everywhere'; // Default fallback
            }
            
            // If set to "everywhere", apply to all contexts
            if ($watermark_on === 'everywhere' || $watermark_on === '') {
                return true;
            }
            
            // If set to "selected_post_types", check the specific post types
            if ($watermark_on === 'selected_post_types') {
                $allowed_post_types = $watermark['watermark_post_types'] ?? [];
                
                // Ensure we have a valid array of post types
                if (!is_array($allowed_post_types) || empty($allowed_post_types)) {
                    return false;
                }
                
                // Normalize post types to strings and validate
                $allowed_post_types = array_map('strval', array_filter($allowed_post_types, 'is_string'));
                
                if (empty($allowed_post_types)) {
                    return false;
                }
                
                return self::checkPostTypeMatch($context, $post_id, $allowed_post_types);
            }
            
            return false;

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error in shouldApplyWatermarkByPostType: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check post type match for different contexts
     */
    private static function checkPostTypeMatch(string $context, ?int $post_id, array $allowed_post_types): bool
    {
        switch ($context) {
            case 'upload':
                return self::checkUploadPostTypeMatch($post_id, $allowed_post_types);
            
            case 'manual':
                return in_array('attachment', $allowed_post_types, true);
            
            case 'frontend':
                return self::checkFrontendPostTypeMatch($post_id, $allowed_post_types);
            
            default:
                return false;
        }
    }

    /**
     * Check post type match for upload context
     */
    private static function checkUploadPostTypeMatch(?int $post_id, array $allowed_post_types): bool
    {
        // Check if 'attachment' is in allowed post types
        if (in_array('attachment', $allowed_post_types, true)) {
            return true;
        }
        
        // Check parent post type if we have a post_id
        if ($post_id && $post_id > 0) {
            $parent_post_id = self::getParentPostId($post_id);
            
            if ($parent_post_id > 0) {
                $parent_post = get_post($parent_post_id);
                if ($parent_post) {
                    return in_array(strval($parent_post->post_type), $allowed_post_types, true);
                }
            }
        }
        
        return false;
    }

    /**
     * Check post type match for frontend context
     */
    private static function checkFrontendPostTypeMatch(?int $post_id, array $allowed_post_types): bool
    {
        if (!$post_id) {
            return false;
        }

        $post_type = get_post_type($post_id);
        if (!$post_type) {
            return false;
        }

        return in_array(strval($post_type), $allowed_post_types, true);
    }

    /**
     * Get parent post ID from various sources
     */
    private static function getParentPostId(int $attachment_id): int
    {
        // Get from stored meta first
        $parent_post_id = get_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', true);
        
        if ($parent_post_id && $parent_post_id > 0) {
            return absint($parent_post_id);
        }

        // Fallback to attachment's post_parent
        $attachment = get_post($attachment_id);
        if ($attachment && $attachment->post_parent > 0) {
            return absint($attachment->post_parent);
        }

        // Check $_POST/$_REQUEST as immediate fallback during upload
        if (isset($_POST['post_id']) && $_POST['post_id'] > 0) {
            return absint($_POST['post_id']);
        }
        
        if (isset($_REQUEST['post_id']) && $_REQUEST['post_id'] > 0) {
            return absint($_REQUEST['post_id']);
        }

        return 0;
    }

    /**
     * Check if watermark should be applied based on image size rules with validation
     * 
     * @param array $watermark
     * @param string $image_size Image size being processed
     * @return bool
     */
    public static function shouldApplyWatermarkByImageSize(array $watermark, string $image_size = 'full'): bool
    {
        try {
            $watermark_sizes = $watermark['watermark_sizes'] ?? [];
            
            // Ensure we have a valid array
            if (!is_array($watermark_sizes)) {
                $watermark_sizes = self::validateArrayMetadata($watermark_sizes);
            }
            
            // Normalize image size
            $image_size = strval($image_size);
            if (empty($image_size)) {
                $image_size = 'full';
            }
            
            // If no sizes specified, apply to all sizes
            if (empty($watermark_sizes)) {
                return true;
            }
            
            // Normalize array values to strings
            $watermark_sizes = array_map('strval', array_values($watermark_sizes));
            
            // Check if the current image size is in the allowed sizes
            if (in_array($image_size, $watermark_sizes, true)) {
                return true;
            }
            
            // Special case: if watermark_sizes contains 'full', also allow 'scaled' variants
            if (in_array('full', $watermark_sizes, true)) {
                if (strpos($image_size, 'scaled') !== false || preg_match('/-\d+x\d+$/', $image_size)) {
                    return true;
                }
            }
            
            return false;

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error in shouldApplyWatermarkByImageSize: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Filter watermarks based on rules with comprehensive validation
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
        try {
            // Validate inputs
            if (!is_array($watermarks)) {
                return [];
            }

            $valid_contexts = ['upload', 'manual', 'frontend'];
            if (!in_array($context, $valid_contexts, true)) {
                $context = 'upload'; // Default fallback
            }
            
            $image_size = strval($image_size);
            if (empty($image_size)) {
                $image_size = 'full'; // Default fallback
            }
            
            return array_filter($watermarks, function($watermark) use ($context, $post_id, $image_size) {
                // Validate watermark data structure
                if (!is_array($watermark) || empty($watermark)) {
                    return false;
                }
                
                // Check unified watermark_rules conditions first (if any exist with conditions)
                $rules = $watermark['watermark_rules'] ?? [];
                if (!empty($rules) && is_array($rules)) {
                    // Check if any rule actually has conditions defined
                    $has_conditions = false;
                    foreach ($rules as $rule) {
                        if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                            $has_conditions = true;
                            break;
                        }
                    }
                    
                    if ($has_conditions) {
                        // Build context for evaluation
                        $eval_context = [
                            'image_size' => $image_size,
                            'post_type' => '',
                        ];
                        
                        // Resolve post type from context
                        if ($post_id) {
                            $parent_post_id = self::getParentPostId($post_id);
                            if ($parent_post_id > 0) {
                                $parent = get_post($parent_post_id);
                                if ($parent) {
                                    $eval_context['post_type'] = $parent->post_type;
                                    $categories = wp_get_post_categories($parent_post_id, ['fields' => 'slugs']);
                                    if (!empty($categories) && !is_wp_error($categories)) {
                                        $eval_context['post_category'] = $categories[0];
                                    }
                                }
                            }
                            
                            // File info from attachment
                            $file_path = get_attached_file($post_id);
                            if ($file_path && file_exists($file_path)) {
                                $eval_context['file_path'] = $file_path;
                                $eval_context['mime_type'] = wp_check_filetype($file_path)['type'] ?? '';
                                $eval_context['file_size_kb'] = round(filesize($file_path) / 1024);
                            }
                        }
                        
                        // Evaluate unified rules
                        if (!RulesEvaluator::evaluate($rules, $eval_context)) {
                            return false;
                        }
                        
                        // Unified rules passed — skip legacy checks since rules cover everything
                        return true;
                    }
                }
                
                // Fallback: legacy post type and image size checks
                $post_type_check = self::shouldApplyWatermarkByPostType($watermark, $context, $post_id);
                if (!$post_type_check) {
                    return false;
                }
                
                // Check image size rules
                $size_check = self::shouldApplyWatermarkByImageSize($watermark, $image_size);
                if (!$size_check) {
                    return false;
                }
                
                // Both checks passed
                return true;
            });

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error in filterWatermarksByRules: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear watermark cache
     */
    public static function clearCache(): void
    {
        try {
            wp_cache_delete(self::CACHE_PREFIX . 'active_watermarks', 'ultimate-watermark');
            
            // Clear individual watermark caches
            $watermarks = self::getActiveWatermarks(false); // Don't use cache
            foreach ($watermarks as $watermark) {
                if (isset($watermark['id'])) {
                    wp_cache_delete(self::CACHE_PREFIX . 'watermark_' . $watermark['id'], 'ultimate-watermark');
                }
            }

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error clearing cache: ' . $e->getMessage());
        }
    }

    /**
     * Get watermark statistics
     */
    public static function getWatermarkStatistics(): array
    {
        try {
            $watermarks = self::getActiveWatermarks();
            
            $stats = [
                'total' => count($watermarks),
                'by_type' => ['text' => 0, 'image' => 0],
                'by_behavior' => [
                    'automatic' => 0,
                    'manual' => 0,
                    'frontend' => 0
                ],
                'by_position' => [],
                'active' => 0,
                'inactive' => 0
            ];

            foreach ($watermarks as $watermark) {
                // Count by type
                if (isset($stats['by_type'][$watermark['type']])) {
                    $stats['by_type'][$watermark['type']]++;
                }

                // Count by behavior
                if ($watermark['automatic_watermarking'] === '1') {
                    $stats['by_behavior']['automatic']++;
                }
                if ($watermark['manual_watermarking'] === '1') {
                    $stats['by_behavior']['manual']++;
                }
                if ($watermark['frontend_watermarking'] === '1') {
                    $stats['by_behavior']['frontend']++;
                }

                // Count by position
                $position = $watermark['position'] ?? 'unknown';
                $stats['by_position'][$position] = ($stats['by_position'][$position] ?? 0) + 1;

                // Count active/inactive
                if ($watermark['active']) {
                    $stats['active']++;
                } else {
                    $stats['inactive']++;
                }
            }

            return apply_filters('ultimate_watermark_statistics', $stats);

        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error getting statistics: ' . $e->getMessage());
            return [];
        }
    }
}
