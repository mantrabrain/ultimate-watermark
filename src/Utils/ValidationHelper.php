<?php

namespace MantraBrain\UltimateWatermark\Utils;

/**
 * Validation Helper Class
 * 
 * Provides centralized validation methods to reduce code duplication
 * and ensure consistent validation across the plugin.
 *
 * @package UltimateWatermark
 * @since 2.0.3
 */
class ValidationHelper
{
    /**
     * Validate numeric range
     * 
     * @param mixed $value Value to validate
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @param string $field_name Field name for error messages
     * @return int Validated integer value
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateNumericRange($value, int $min, int $max, string $field_name): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($value === false || $value < $min || $value > $max) {
            throw new \InvalidArgumentException(
                sprintf(
                    __('Invalid value for %s. Must be between %d and %d.', 'ultimate-watermark'),
                    $field_name,
                    $min,
                    $max
                )
            );
        }

        return $value;
    }

    /**
     * Validate hex color format
     * 
     * @param string $color Color value to validate
     * @return string Validated hex color
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateColor(string $color): string
    {
        // Validate hex color
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException(
                __('Invalid color format. Use hex format (#RRGGBB).', 'ultimate-watermark')
            );
        }

        return $color;
    }

    /**
     * Validate ID (positive integer)
     * 
     * @param mixed $id ID to validate
     * @return int Validated ID
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateId($id): int
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) {
            throw new \InvalidArgumentException(
                __('Invalid ID provided.', 'ultimate-watermark')
            );
        }
        return $id;
    }

    /**
     * Sanitize text field with length limit
     * 
     * @param string $text Text to sanitize
     * @param int $max_length Maximum allowed length
     * @return string Sanitized text
     */
    public static function sanitizeText(string $text, int $max_length): string
    {
        $text = sanitize_text_field($text);
        if (strlen($text) > $max_length) {
            $text = substr($text, 0, $max_length);
        }
        return $text;
    }

    /**
     * Sanitize textarea field with length limit
     * 
     * @param string $text Text to sanitize
     * @param int $max_length Maximum allowed length
     * @return string Sanitized text
     */
    public static function sanitizeTextarea(string $text, int $max_length): string
    {
        $text = sanitize_textarea_field($text);
        if (strlen($text) > $max_length) {
            $text = substr($text, 0, $max_length);
        }
        return $text;
    }

    /**
     * Validate watermark position
     * 
     * @param string $position Position to validate
     * @return string Validated position
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validatePosition(string $position): string
    {
        $allowed_positions = [
            'top_left', 'top_center', 'top_right',
            'middle_left', 'middle_center', 'middle_right',
            'bottom_left', 'bottom_center', 'bottom_right'
        ];
        
        if (!in_array($position, $allowed_positions, true)) {
            throw new \InvalidArgumentException(
                __('Invalid position selected.', 'ultimate-watermark')
            );
        }

        return $position;
    }

    /**
     * Validate watermark type
     * 
     * @param string $type Type to validate
     * @return string Validated type
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateWatermarkType(string $type): string
    {
        $allowed_types = ['text', 'image'];
        
        if (!in_array($type, $allowed_types, true)) {
            throw new \InvalidArgumentException(
                __('Invalid watermark type.', 'ultimate-watermark')
            );
        }

        return $type;
    }

    /**
     * Validate attachment is an image
     * 
     * @param int $attachment_id Attachment ID to validate
     * @return bool True if valid image
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateImageAttachment(int $attachment_id): bool
    {
        $attachment = get_post($attachment_id);
        
        if (!$attachment || $attachment->post_type !== 'attachment') {
            throw new \InvalidArgumentException(
                __('Invalid watermark image selected.', 'ultimate-watermark')
            );
        }

        if (!wp_attachment_is_image($attachment_id)) {
            throw new \InvalidArgumentException(
                __('Selected file is not an image.', 'ultimate-watermark')
            );
        }

        return true;
    }

    /**
     * Validate file path exists and is readable
     * 
     * @param string $file_path File path to validate
     * @return bool True if valid
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateFilePath(string $file_path): bool
    {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            throw new \InvalidArgumentException(
                sprintf(
                    __('File not found or not readable: %s', 'ultimate-watermark'),
                    basename($file_path)
                )
            );
        }

        return true;
    }

    /**
     * Validate directory is writable
     * 
     * @param string $directory Directory path to validate
     * @return bool True if valid
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateWritableDirectory(string $directory): bool
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new \InvalidArgumentException(
                sprintf(
                    __('Directory is not writable: %s', 'ultimate-watermark'),
                    $directory
                )
            );
        }

        return true;
    }

    /**
     * Validate checkbox value (0 or 1)
     * 
     * @param mixed $value Value to validate
     * @return string '0' or '1'
     */
    public static function validateCheckbox($value): string
    {
        return (isset($value) && $value === '1') ? '1' : '0';
    }

    /**
     * Validate array of IDs
     * 
     * @param mixed $ids IDs to validate
     * @return array Array of validated positive integers
     */
    public static function validateIdArray($ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        return array_filter(array_map('absint', $ids), function($id) {
            return $id > 0;
        });
    }
}
