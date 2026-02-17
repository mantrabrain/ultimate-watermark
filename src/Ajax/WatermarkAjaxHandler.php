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
            $this->verifyNonce($_POST['ultimate_watermark_nonce'] ?? '', 'ultimate_watermark_nonce');

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

        } catch (\InvalidArgumentException $e) {
            // Check if this is a security-related exception
            if (strpos($e->getMessage(), 'Security exception:') === 0) {
                $this->sendSecurityError($e->getMessage());
            } else {
                $this->sendValidationError($e->getMessage());
            }
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
            throw new \InvalidArgumentException('Security exception: missing nonce');
        }

        if (!wp_verify_nonce($nonce, $action)) {
            // Log security violation
            $this->logSecurityViolation('invalid_nonce', [
                'user_id' => get_current_user_id(),
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            
            throw new \InvalidArgumentException('Security exception: invalid nonce');
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
        $checkbox_fields = ['active', 'automatic_watermarking', 'manual_watermarking', 'frontend_watermarking'];
        foreach ($checkbox_fields as $field) {
            $sanitized[$field] = isset($data[$field]) && $data[$field] === '1' ? '1' : '0';
        }

        // Handle watermark rules (JSON-encoded from hidden field)
        if (!empty($data['watermark_rules'])) {
            $rules_raw = $data['watermark_rules'];
            if (is_string($rules_raw)) {
                $rules_raw = json_decode(stripslashes($rules_raw), true);
            }
            if (is_array($rules_raw)) {
                $sanitized['watermark_rules'] = $this->sanitizeWatermarkRules($rules_raw);
            }
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
        $validated['watermark_font_size'] = $this->validateNumericRange(
            $data['watermark_font_size'] ?? 24,
            1,
            200,
            'watermark_font_size'
        );

        // Validate color
        $validated['watermark_color'] = $this->validateColor($data['watermark_color'] ?? '#ffffff');

        // Font family
        $allowed_fonts = ['Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana', 'Courier New'];
        $validated['watermark_font_family'] = in_array($data['watermark_font_family'] ?? '', $allowed_fonts)
            ? $data['watermark_font_family']
            : 'Arial';

        // Font weight
        $allowed_weights = ['normal', 'bold', 'lighter'];
        $validated['watermark_font_weight'] = in_array($data['watermark_font_weight'] ?? '', $allowed_weights)
            ? $data['watermark_font_weight']
            : 'normal';

        // Font style
        $allowed_styles = ['normal', 'italic', 'oblique'];
        $validated['watermark_font_style'] = in_array($data['watermark_font_style'] ?? '', $allowed_styles)
            ? $data['watermark_font_style']
            : 'normal';

        // Text decoration
        $allowed_decorations = ['none', 'underline', 'overline', 'line-through'];
        $validated['watermark_text_decoration'] = in_array($data['watermark_text_decoration'] ?? '', $allowed_decorations)
            ? $data['watermark_text_decoration']
            : 'none';

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

        // Validate position (form field: watermark_position)
        // Form uses hyphen format: top-left, center-right, bottom-right, etc.
        $allowed_positions = ['top-left', 'top-center', 'top-right', 'center-left', 'center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right'];
        $validated['watermark_position'] = $data['watermark_position'] ?? 'bottom-right';
        if (!in_array($validated['watermark_position'], $allowed_positions)) {
            throw new \InvalidArgumentException('Invalid position selected.');
        }

        // Validate offsets (form fields: watermark_offset_x, watermark_offset_y)
        $validated['watermark_offset_x'] = $this->validateNumericRange(
            $data['watermark_offset_x'] ?? 0,
            -1000,
            1000,
            'watermark_offset_x'
        );

        $validated['watermark_offset_y'] = $this->validateNumericRange(
            $data['watermark_offset_y'] ?? 0,
            -1000,
            1000,
            'watermark_offset_y'
        );

        // Validate offset unit (form field: watermark_offset_unit)
        $allowed_units = ['pixels', 'percentage'];
        $raw_unit = $data['watermark_offset_unit'] ?? $data['offset_unit'] ?? 'pixels';
        $validated['watermark_offset_unit'] = in_array($raw_unit, $allowed_units) ? $raw_unit : 'pixels';

        // Validate rotation (form field: watermark_rotation)
        $validated['watermark_rotation'] = $this->validateNumericRange(
            $data['watermark_rotation'] ?? 0,
            -360,
            360,
            'watermark_rotation'
        );

        // Validate opacity (form field: watermark_opacity)
        $validated['watermark_opacity'] = $this->validateNumericRange(
            $data['watermark_opacity'] ?? 50,
            0,
            100,
            'watermark_opacity'
        );

        return $validated;
    }

    /**
     * Validate settings data
     */
    private function validateSettings(array $data): array
    {
        $validated = [];

        // Validate size settings (form field: watermark_size_type)
        $validated['watermark_size_type'] = $data['watermark_size_type'] ?? 'original';
        $allowed_size_types = ['original', 'custom', 'scaled'];
        if (!in_array($validated['watermark_size_type'], $allowed_size_types)) {
            $validated['watermark_size_type'] = 'original';
        }

        // Always validate size fields so they are saved (form fields: watermark_custom_width, watermark_custom_height)
        $validated['watermark_custom_width'] = $this->validateNumericRange(
            $data['watermark_custom_width'] ?? 100,
            1,
            5000,
            'watermark_custom_width'
        );
        $validated['watermark_custom_height'] = $this->validateNumericRange(
            $data['watermark_custom_height'] ?? 100,
            1,
            5000,
            'watermark_custom_height'
        );

        // Scale percentage (form field: watermark_scale_percentage)
        $validated['watermark_scale_percentage'] = $this->validateNumericRange(
            $data['watermark_scale_percentage'] ?? 80,
            1,
            100,
            'watermark_scale_percentage'
        );

        // Validate quality (form field: watermark_quality)
        $validated['watermark_quality'] = $this->validateNumericRange(
            $data['watermark_quality'] ?? 90,
            1,
            100,
            'watermark_quality'
        );

        // Image format (form field: image_format)
        $allowed_formats = ['baseline', 'progressive'];
        $validated['image_format'] = in_array($data['image_format'] ?? '', $allowed_formats)
            ? $data['image_format']
            : 'baseline';

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
     * Sanitize watermark rules data
     *
     * @param array $rules Raw rules array
     * @return array Sanitized rules
     */
    private function sanitizeWatermarkRules(array $rules): array
    {
        $sanitized = [];
        $allowed_operators = ['is', 'is_not', 'greater_than', 'less_than', 'equals'];

        foreach ($rules as $rule_id => $rule) {
            if (!is_array($rule) || empty($rule['name'])) {
                continue;
            }

            $sanitized_rule = [
                'name' => sanitize_text_field($rule['name']),
                'logic_operator' => in_array($rule['logic_operator'] ?? 'and', ['and', 'or']) ? $rule['logic_operator'] : 'and',
                'conditions' => [],
                'is_default' => !empty($rule['is_default']),
            ];

            if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                foreach ($rule['conditions'] as $condition) {
                    if (!is_array($condition)) continue;

                    $type = sanitize_text_field($condition['type'] ?? '');
                    $operator = sanitize_text_field($condition['operator'] ?? '');
                    $value = sanitize_text_field($condition['value'] ?? '');

                    if (empty($type) || empty($operator) || $value === '') continue;
                    if (!in_array($operator, $allowed_operators)) continue;

                    $sanitized_rule['conditions'][] = [
                        'type' => $type,
                        'operator' => $operator,
                        'value' => $value,
                    ];
                }
            }

            $sanitized[sanitize_key($rule_id)] = $sanitized_rule;
        }

        return $sanitized;
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
