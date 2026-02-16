<?php

namespace MantraBrain\UltimateWatermark\Ajax;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\PostTypes\WatermarkPostType;

/**
 * Watermark AJAX Handler Class
 * 
 * Handles AJAX requests for watermark operations with comprehensive security
 * measures and proper input validation for enterprise-level applications.
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkAjaxHandler
{
    use SingletonTrait;

    /**
     * Maximum allowed requests per hour per user
     */
    private const RATE_LIMIT = 100;

    /**
     * Initialize AJAX handlers with proper security measures
     */
    public function init(): void
    {
        // Register AJAX handlers with proper capabilities
        $this->registerAjaxHandlers();
        
        // Add rate limiting
        add_action('wp_ajax_ultimate_watermark_save', [$this, 'applyRateLimit'], 5);
    }

    /**
     * Register AJAX handlers
     */
    private function registerAjaxHandlers(): void
    {
        $handlers = [
            'ultimate_watermark_save' => [
                'callback' => 'handleSaveWatermark',
                'capability' => 'manage_options',
                'allow_nopriv' => false
            ],
        ];

        foreach ($handlers as $action => $config) {
            add_action("wp_ajax_{$action}", [$this, $config['callback']]);
            
            // Add capability check
            add_action("wp_ajax_{$action}", function() use ($config) {
                if (!current_user_can($config['capability'])) {
                    $this->sendSecurityError('insufficient_permissions');
                }
            }, 1);
        }
    }

    /**
     * Apply rate limiting to prevent abuse
     */
    public function applyRateLimit(): void
    {
        $user_id = get_current_user_id();
        $cache_key = "ultimate_watermark_rate_limit_{$user_id}";
        $count = get_transient($cache_key);

        if ($count === false) {
            set_transient($cache_key, 1, HOUR_IN_SECONDS);
            return;
        }

        if ($count >= self::RATE_LIMIT) {
            $this->sendSecurityError('rate_limit_exceeded');
        }

        set_transient($cache_key, $count + 1, HOUR_IN_SECONDS);
    }

    /**
     * Handle save watermark AJAX request with comprehensive validation
     */
    public function handleSaveWatermark(): void
    {
        try {
            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendSecurityError('invalid_method');
            }

            // Verify nonce with specific action
            $this->verifyNonce($_POST['nonce'] ?? '', 'ultimate_watermark_ajax');

            // Validate and sanitize input data
            $sanitized_data = $this->validateAndSanitizeInput($_POST);

            // Process the watermark save operation
            $is_update = !empty($sanitized_data['watermark_id']);
            $watermark_id = $this->saveWatermark($sanitized_data);
            
            if ($watermark_id) {
                $this->sendSuccessResponse($watermark_id, $is_update);
            } else {
                $this->sendErrorResponse('Failed to save watermark.');
            }

        } catch (\SecurityException $e) {
            $this->sendSecurityError($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->sendValidationError($e->getMessage());
        } catch (\Exception $e) {
            $this->handleUnexpectedError($e);
        }
    }

    /**
     * Verify nonce with enhanced security
     */
    private function verifyNonce(string $nonce, string $action): void
    {
        if (empty($nonce)) {
            throw new \SecurityException('missing_nonce');
        }

        if (!wp_verify_nonce($nonce, $action)) {
            // Log security violation
            $this->logSecurityViolation('invalid_nonce', [
                'user_id' => get_current_user_id(),
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            
            throw new \SecurityException('invalid_nonce');
        }
    }

    /**
     * Validate and sanitize all input data
     */
    private function validateAndSanitizeInput(array $data): array
    {
        $sanitized = [];

        // Validate required fields
        $required_fields = ['name'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing.");
            }
        }

        // Sanitize basic fields
        $sanitized['name'] = $this->sanitizeText($data['name'], 100);
        $sanitized['description'] = $this->sanitizeTextarea($data['description'] ?? '', 500);
        $sanitized['watermark_id'] = $this->validateId($data['watermark_id'] ?? 0);

        // Validate watermark type
        $allowed_types = ['text', 'image'];
        $sanitized['watermark_type'] = $data['watermark_type'] ?? 'text';
        if (!in_array($sanitized['watermark_type'], $allowed_types)) {
            throw new \InvalidArgumentException('Invalid watermark type.');
        }

        // Type-specific validation
        if ($sanitized['watermark_type'] === 'text') {
            $sanitized = array_merge($sanitized, $this->validateTextWatermarkData($data));
        } else {
            $sanitized = array_merge($sanitized, $this->validateImageWatermarkData($data));
        }

        // Validate positioning data
        $sanitized = array_merge($sanitized, $this->validatePositionData($data));

        // Validate settings
        $sanitized = array_merge($sanitized, $this->validateSettings($data));

        // Handle checkbox fields
        $checkbox_fields = ['active', 'automatic_watermarking'];
        foreach ($checkbox_fields as $field) {
            $sanitized[$field] = isset($data[$field]) && $data[$field] === '1' ? '1' : '0';
        }

        return apply_filters('ultimate_watermark_sanitized_data', $sanitized, $data);
    }

    /**
     * Validate text watermark specific data
     */
    private function validateTextWatermarkData(array $data): array
    {
        $validated = [];

        if (empty($data['watermark_text'])) {
            throw new \InvalidArgumentException('Watermark text is required for text watermarks.');
        }

        $validated['watermark_text'] = $this->sanitizeText($data['watermark_text'], 200);
        
        // Validate font size
        $validated['font_size'] = $this->validateNumericRange(
            $data['font_size'] ?? 20,
            1,
            200,
            'font_size'
        );

        // Validate color
        $validated['text_color'] = $this->validateColor($data['text_color'] ?? '#000000');

        return $validated;
    }

    /**
     * Validate image watermark specific data
     */
    private function validateImageWatermarkData(array $data): array
    {
        $validated = [];

        if (empty($data['watermark_image_id'])) {
            throw new \InvalidArgumentException('Watermark image is required for image watermarks.');
        }

        $validated['watermark_image_id'] = $this->validateId($data['watermark_image_id']);

        // Verify the attachment exists and is an image
        $attachment = get_post($validated['watermark_image_id']);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            throw new \InvalidArgumentException('Invalid watermark image selected.');
        }

        if (!wp_attachment_is_image($validated['watermark_image_id'])) {
            throw new \InvalidArgumentException('Selected file is not an image.');
        }

        return $validated;
    }

    /**
     * Validate positioning data
     */
    private function validatePositionData(array $data): array
    {
        $validated = [];

        // Validate position
        $allowed_positions = ['top_left', 'top_center', 'top_right', 'middle_left', 'middle_center', 'middle_right', 'bottom_left', 'bottom_center', 'bottom_right'];
        $validated['position'] = $data['position'] ?? 'middle_center';
        if (!in_array($validated['position'], $allowed_positions)) {
            throw new \InvalidArgumentException('Invalid position selected.');
        }

        // Validate offsets
        $validated['offset_x'] = $this->validateNumericRange(
            $data['offset_x'] ?? 0,
            -1000,
            1000,
            'offset_x'
        );

        $validated['offset_y'] = $this->validateNumericRange(
            $data['offset_y'] ?? 0,
            -1000,
            1000,
            'offset_y'
        );

        // Validate offset unit
        $allowed_units = ['pixels', 'percentage'];
        $validated['offset_unit'] = $data['offset_unit'] ?? 'pixels';
        if (!in_array($validated['offset_unit'], $allowed_units)) {
            $validated['offset_unit'] = 'pixels';
        }

        return $validated;
    }

    /**
     * Validate settings data
     */
    private function validateSettings(array $data): array
    {
        $validated = [];

        // Validate size settings
        $validated['size_type'] = $data['size_type'] ?? 'original';
        $allowed_size_types = ['original', 'custom', 'scaled'];
        if (!in_array($validated['size_type'], $allowed_size_types)) {
            $validated['size_type'] = 'original';
        }

        if ($validated['size_type'] === 'custom') {
            $validated['width'] = $this->validateNumericRange(
                $data['width'] ?? 100,
                1,
                5000,
                'width'
            );
            $validated['height'] = $this->validateNumericRange(
                $data['height'] ?? 100,
                1,
                5000,
                'height'
            );
        } elseif ($validated['size_type'] === 'scaled') {
            $validated['scale_percentage'] = $this->validateNumericRange(
                $data['scale_percentage'] ?? 50,
                1,
                100,
                'scale_percentage'
            );
        }

        // Validate transparency
        $validated['transparency'] = $this->validateNumericRange(
            $data['transparency'] ?? 100,
            0,
            100,
            'transparency'
        );

        // Validate quality
        $validated['quality'] = $this->validateNumericRange(
            $data['quality'] ?? 90,
            1,
            100,
            'quality'
        );

        return $validated;
    }

    /**
     * Validate numeric range
     */
    private function validateNumericRange($value, int $min, int $max, string $field_name): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($value === false || $value < $min || $value > $max) {
            throw new \InvalidArgumentException("Invalid value for {$field_name}. Must be between {$min} and {$max}.");
        }

        return $value;
    }

    /**
     * Validate color format
     */
    private function validateColor(string $color): string
    {
        // Validate hex color
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException('Invalid color format. Use hex format (#RRGGBB).');
        }

        return $color;
    }

    /**
     * Validate ID
     */
    private function validateId($id): int
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) {
            throw new \InvalidArgumentException('Invalid ID provided.');
        }
        return $id;
    }

    /**
     * Sanitize text field
     */
    private function sanitizeText(string $text, int $max_length): string
    {
        $text = sanitize_text_field($text);
        if (strlen($text) > $max_length) {
            $text = substr($text, 0, $max_length);
        }
        return $text;
    }

    /**
     * Sanitize textarea field
     */
    private function sanitizeTextarea(string $text, int $max_length): string
    {
        $text = sanitize_textarea_field($text);
        if (strlen($text) > $max_length) {
            $text = substr($text, 0, $max_length);
        }
        return $text;
    }

    /**
     * Save watermark data with enhanced error handling
     */
    private function saveWatermark(array $data): int
    {
        $watermark_id = $data['watermark_id'];
        
        // Prepare post data with validation
        $post_data = [
            'post_title' => $data['name'],
            'post_content' => $data['description'] ?? '',
            'post_type' => WatermarkPostType::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => $this->prepareMetaData($data)
        ];

        if ($watermark_id > 0) {
            $post_data['ID'] = $watermark_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        // Handle post-save operations
        $this->handlePostSaveOperations($result, $data);

        return $result;
    }

    /**
     * Prepare metadata for saving
     */
    private function prepareMetaData(array $data): array
    {
        $meta_data = [];
        
        // Exclude post fields from meta data
        $exclude_fields = ['watermark_id', 'name', 'description'];
        
        foreach ($data as $key => $value) {
            if (!in_array($key, $exclude_fields)) {
                $meta_data[$key] = $value;
            }
        }
        
        return $meta_data;
    }

    /**
     * Handle post-save operations
     */
    private function handlePostSaveOperations(int $watermark_id, array $data): void
    {
        // Clear cached preview
        delete_post_meta($watermark_id, 'preview_url');
        
        // Clean up old preview images
        if (class_exists('\MantraBrain\UltimateWatermark\Utils\PreviewManager')) {
            \MantraBrain\UltimateWatermark\Utils\PreviewManager::cleanupWatermarkPreviews($watermark_id);
        }
        
        // Fire action for other components
        do_action('ultimate_watermark_saved', $watermark_id, $data);
    }

    /**
     * Send success response
     */
    private function sendSuccessResponse(int $watermark_id, bool $is_update): void
    {
        $response = [
            'message' => $is_update 
                ? __('Watermark updated successfully!', 'ultimate-watermark')
                : __('Watermark created successfully!', 'ultimate-watermark'),
            'watermark_id' => $watermark_id,
            'success' => true
        ];
        
        // Add redirect for new watermarks
        if (!$is_update) {
            $response['redirect_url'] = admin_url('admin.php?page=ultimate-watermark-watermarks');
        }
        
        wp_send_json_success($response);
    }

    /**
     * Send error response
     */
    private function sendErrorResponse(string $message, array $extra = []): void
    {
        wp_send_json_error(array_merge(['message' => $message], $extra));
    }

    /**
     * Send validation error
     */
    private function sendValidationError(string $message): void
    {
        $this->sendErrorResponse($message, ['type' => 'validation_error']);
    }

    /**
     * Send security error
     */
    private function sendSecurityError(string $error_code): void
    {
        $messages = [
            'missing_nonce' => __('Security token missing.', 'ultimate-watermark'),
            'invalid_nonce' => __('Security check failed.', 'ultimate-watermark'),
            'insufficient_permissions' => __('You do not have permission to perform this action.', 'ultimate-watermark'),
            'rate_limit_exceeded' => __('Too many requests. Please try again later.', 'ultimate-watermark'),
            'invalid_method' => __('Invalid request method.', 'ultimate-watermark'),
        ];

        $message = $messages[$error_code] ?? __('Security error occurred.', 'ultimate-watermark');
        
        $this->sendErrorResponse($message, [
            'type' => 'security_error',
            'code' => $error_code
        ]);
    }

    /**
     * Handle unexpected errors
     */
    private function handleUnexpectedError(\Exception $e): void
    {
        error_log('Ultimate Watermark AJAX Error: ' . $e->getMessage());
        
        $message = defined('WP_DEBUG') && WP_DEBUG 
            ? $e->getMessage()
            : __('An unexpected error occurred. Please try again.', 'ultimate-watermark');
            
        $this->sendErrorResponse($message, ['type' => 'unexpected_error']);
    }

    /**
     * Log security violations
     */
    private function logSecurityViolation(string $type, array $context): void
    {
        error_log("Ultimate Watermark Security Violation: {$type} - " . json_encode($context));
        
        // Hook for additional security logging
        do_action('ultimate_watermark_security_violation', $type, $context);
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Security Exception Class
 */
class SecurityException extends \Exception
{
    // Custom security exception for better error handling
}
