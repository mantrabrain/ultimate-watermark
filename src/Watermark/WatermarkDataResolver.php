<?php

namespace MantraBrain\UltimateWatermark\Watermark;

use MantraBrain\UltimateWatermark\Utils\WatermarkHelper;

/**
 * Watermark Data Resolver
 * 
 * Handles both ID-based and form-based watermark data retrieval
 * Provides consistent data structure for all watermark operations
 * 
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkDataResolver
{
    /**
     * Resolve watermark data from various input types
     * 
     * @param int|array $input Watermark ID (int) or form data (array)
     * @return array|null Normalized watermark data or null if invalid
     */
    public static function resolve($input): ?array
    {
        if (is_int($input)) {
            return self::resolveFromId($input);
        } elseif (is_array($input)) {
            return self::resolveFromFormData($input);
        }
        
        return null;
    }

    /**
     * Resolve watermark data from ID
     * 
     * @param int $watermarkId Watermark ID
     * @return array|null Watermark data or null if not found
     */
    private static function resolveFromId(int $watermarkId): ?array
    {
        // Get watermark data from database
        $watermarkData = WatermarkHelper::getActiveWatermarkById($watermarkId);
        
        if (!$watermarkData) {
            return null;
        }

        // Convert image ID to path if provided
        if (!empty($watermarkData['watermark_image_id'])) {
            $imagePath = get_attached_file($watermarkData['watermark_image_id']);
            if ($imagePath && file_exists($imagePath)) {
                $watermarkData['watermark_image_path'] = $imagePath;
            }
        }

        // Normalize the data structure
        return self::normalizeData($watermarkData);
    }

    /**
     * Resolve watermark data from form data
     * 
     * @param array $formData Form data array
     * @return array Normalized watermark data
     */
    private static function resolveFromFormData(array $formData): array
    {
        // Sanitize and normalize form data
        $normalizedData = self::sanitizeFormData($formData);
        
        // Convert image ID to path if provided
        if (!empty($normalizedData['watermark_image_id'])) {
            $imagePath = get_attached_file($normalizedData['watermark_image_id']);
            if ($imagePath && file_exists($imagePath)) {
                $normalizedData['watermark_image_path'] = $imagePath;
            }
        }

        return self::normalizeData($normalizedData);
    }

    /**
     * Sanitize form data
     * 
     * @param array $formData Raw form data
     * @return array Sanitized form data
     */
    private static function sanitizeFormData(array $formData): array
    {
        $watermark_type = sanitize_text_field($formData['watermark_type'] ?? 'text');
        
        // Allow Pro plugin to register custom watermark types
        $watermark_type = apply_filters('ultimate_watermark_sanitize_type', $watermark_type, $formData);
        
        return [
            'watermark_type' => $watermark_type,
            'watermark_text' => sanitize_text_field($formData['watermark_text'] ?? 'Watermark'),
            'watermark_color' => sanitize_hex_color($formData['watermark_color'] ?? '#000000'),
            'watermark_font_size' => absint($formData['watermark_font_size'] ?? 24),
            'watermark_font_family' => sanitize_text_field($formData['watermark_font_family'] ?? 'Arial'),
            'watermark_font_weight' => sanitize_text_field($formData['watermark_font_weight'] ?? 'normal'),
            'watermark_font_style' => sanitize_text_field($formData['watermark_font_style'] ?? 'normal'),
            'watermark_text_decoration' => sanitize_text_field($formData['watermark_text_decoration'] ?? 'none'),
            'watermark_image_id' => absint($formData['watermark_image_id'] ?? 0),
            'watermark_position' => sanitize_text_field($formData['watermark_position'] ?? 'bottom-right'),
            'watermark_offset_x' => absint($formData['watermark_offset_x'] ?? 10),
            'watermark_offset_y' => absint($formData['watermark_offset_y'] ?? 10),
            'offset_unit' => sanitize_text_field($formData['offset_unit'] ?? 'pixels'),
            'watermark_opacity' => absint($formData['watermark_opacity'] ?? 50),
            'watermark_rotation' => absint($formData['watermark_rotation'] ?? 0),
            'watermark_size_type' => sanitize_text_field($formData['watermark_size_type'] ?? 'original'),
            'watermark_custom_width' => absint($formData['watermark_custom_width'] ?? 100),
            'watermark_custom_height' => absint($formData['watermark_custom_height'] ?? 100),
            'watermark_scale_percentage' => absint($formData['watermark_scale_percentage'] ?? 50),
            'watermark_quality' => absint($formData['watermark_quality'] ?? 90),
            'image_format' => sanitize_text_field($formData['image_format'] ?? 'baseline'),
        ];
    }

    /**
     * Normalize data structure for processors
     * 
     * @param array $data Raw watermark data
     * @return array Normalized data with all required fields
     */
    private static function normalizeData(array $data): array
    {
        // Allow Pro plugin to add custom data normalization
        $data = apply_filters('ultimate_watermark_before_normalize_data', $data);
        
        // Ensure all required fields exist with defaults
        $normalized = array_merge([
            'watermark_type' => 'text',
        ], $data);
        
        $watermarkType = $data['watermark_type'] ?? $data['type'] ?? 'text';
        
        $normalized = [
            // Core settings
            'type' => $watermarkType,
            'watermark_type' => $watermarkType,
            'position' => $data['watermark_position'] ?? $data['position'] ?? 'bottom-right',
            'watermark_position' => $data['watermark_position'] ?? $data['position'] ?? 'bottom-right',
            'opacity' => (int) ($data['watermark_opacity'] ?? $data['opacity'] ?? 50),
            'watermark_opacity' => (int) ($data['watermark_opacity'] ?? $data['opacity'] ?? 50),
            
            // Text watermark settings
            'text' => $data['watermark_text'] ?? $data['text'] ?? 'Watermark',
            'watermark_text' => $data['watermark_text'] ?? $data['text'] ?? 'Watermark',
            'color' => $data['watermark_color'] ?? $data['color'] ?? '#000000',
            'watermark_color' => $data['watermark_color'] ?? $data['color'] ?? '#000000',
            'font_size' => (int) ($data['watermark_font_size'] ?? $data['font_size'] ?? 24),
            'watermark_font_size' => (int) ($data['watermark_font_size'] ?? $data['font_size'] ?? 24),
            'font_family' => $data['watermark_font_family'] ?? $data['font_family'] ?? 'Arial',
            'watermark_font_family' => $data['watermark_font_family'] ?? $data['font_family'] ?? 'Arial',
            'font_weight' => $data['watermark_font_weight'] ?? $data['font_weight'] ?? 'normal',
            'watermark_font_weight' => $data['watermark_font_weight'] ?? $data['font_weight'] ?? 'normal',
            'font_style' => $data['watermark_font_style'] ?? $data['font_style'] ?? 'normal',
            'watermark_font_style' => $data['watermark_font_style'] ?? $data['font_style'] ?? 'normal',
            'text_decoration' => $data['watermark_text_decoration'] ?? $data['text_decoration'] ?? 'none',
            'watermark_text_decoration' => $data['watermark_text_decoration'] ?? $data['text_decoration'] ?? 'none',
            
            // Image watermark settings
            'image_id' => (int) ($data['watermark_image_id'] ?? $data['image_id'] ?? 0),
            'watermark_image_id' => (int) ($data['watermark_image_id'] ?? $data['image_id'] ?? 0),
            'image_path' => $data['watermark_image_path'] ?? $data['image_path'] ?? '',
            'watermark_image_path' => $data['watermark_image_path'] ?? $data['image_path'] ?? '',
            
            // Size settings
            'size_type' => $data['watermark_size_type'] ?? $data['size_type'] ?? 'original',
            'watermark_size_type' => $data['watermark_size_type'] ?? $data['size_type'] ?? 'original',
            'custom_width' => (int) ($data['watermark_custom_width'] ?? $data['custom_width'] ?? 100),
            'watermark_custom_width' => (int) ($data['watermark_custom_width'] ?? $data['custom_width'] ?? 100),
            'custom_height' => (int) ($data['watermark_custom_height'] ?? $data['custom_height'] ?? 100),
            'watermark_custom_height' => (int) ($data['watermark_custom_height'] ?? $data['custom_height'] ?? 100),
            'scale_percentage' => (int) ($data['watermark_scale_percentage'] ?? $data['scale_percentage'] ?? 50),
            'watermark_scale_percentage' => (int) ($data['watermark_scale_percentage'] ?? $data['scale_percentage'] ?? 50),
            'watermark_scale' => (int) ($data['watermark_scale_percentage'] ?? $data['scale_percentage'] ?? 50) / 100,
            
            // Offset settings
            'offset_x' => (int) ($data['watermark_offset_x'] ?? $data['offset_x'] ?? 10),
            'watermark_offset_x' => (int) ($data['watermark_offset_x'] ?? $data['offset_x'] ?? 10),
            'offset_y' => (int) ($data['watermark_offset_y'] ?? $data['offset_y'] ?? 10),
            'watermark_offset_y' => (int) ($data['watermark_offset_y'] ?? $data['offset_y'] ?? 10),
            'offset_unit' => $data['offset_unit'] ?? $data['watermark_offset_unit'] ?? 'pixels',
            'watermark_offset_unit' => $data['offset_unit'] ?? $data['watermark_offset_unit'] ?? 'pixels',
            
            // Rotation and quality
            'rotation' => (int) ($data['watermark_rotation'] ?? $data['rotation'] ?? 0),
            'watermark_rotation' => (int) ($data['watermark_rotation'] ?? $data['rotation'] ?? 0),
            'watermark_quality' => (int) ($data['watermark_quality'] ?? $data['quality'] ?? 90),
            'image_format' => $data['image_format'] ?? 'baseline',
        ];
        
        // Allow Pro plugin to modify normalized data
        $normalized = apply_filters('ultimate_watermark_after_normalize_data', $normalized);
        
        return $normalized;
    }
}
