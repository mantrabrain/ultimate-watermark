<?php

namespace MantraBrain\UltimateWatermark\Utils;

/**
 * Rules Evaluator
 * 
 * Evaluates unified watermark_rules conditions against the current context
 * to determine whether a watermark should be applied.
 * 
 * @package UltimateWatermark
 * @since 2.1.0
 */
class RulesEvaluator
{
    /**
     * Evaluate all rules for a watermark against the given context.
     * 
     * If ANY rule passes (rules are OR'd at the top level), the watermark applies.
     * Within a rule, conditions are combined using the rule's logic_operator (AND/OR).
     * If no rules have conditions, watermark applies (no restrictions).
     *
     * @param array $rules      The watermark_rules array (keyed by rule_id)
     * @param array $context    Contextual data for evaluation
     * @return bool
     */
    public static function evaluate(array $rules, array $context): bool
    {
        if (empty($rules)) {
            return true; // No rules = no restrictions
        }

        // Check if ALL rules have zero conditions — treat as unrestricted
        $has_any_conditions = false;
        foreach ($rules as $rule) {
            if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                $has_any_conditions = true;
                break;
            }
        }

        if (!$has_any_conditions) {
            return true; // All rules have empty conditions = no restrictions
        }

        // Evaluate each rule: if ANY rule passes, watermark applies
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $conditions = $rule['conditions'] ?? [];
            if (empty($conditions) || !is_array($conditions)) {
                // Rule with no conditions always passes
                return true;
            }

            $logic = strtolower($rule['logic_operator'] ?? 'and');
            $rule_passes = self::evaluateConditions($conditions, $logic, $context);

            if ($rule_passes) {
                return true; // At least one rule passed
            }
        }

        return false; // No rules passed
    }

    /**
     * Evaluate a set of conditions with the given logic operator.
     *
     * @param array  $conditions Array of condition arrays (type, operator, value)
     * @param string $logic      'and' or 'or'
     * @param array  $context    Contextual data
     * @return bool
     */
    private static function evaluateConditions(array $conditions, string $logic, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!is_array($condition) || empty($condition['type'])) {
                continue;
            }

            $result = self::evaluateSingleCondition($condition, $context);

            if ($logic === 'or' && $result) {
                return true; // OR: one true is enough
            }

            if ($logic === 'and' && !$result) {
                return false; // AND: one false is enough to fail
            }
        }

        // For AND: all passed; for OR: none passed
        return $logic === 'and';
    }

    /**
     * Evaluate a single condition against the context.
     *
     * @param array $condition  Single condition (type, operator, value)
     * @param array $context    Contextual data
     * @return bool
     */
    private static function evaluateSingleCondition(array $condition, array $context): bool
    {
        $type = $condition['type'] ?? '';
        $operator = $condition['operator'] ?? '';
        $expected = $condition['value'] ?? '';

        if (empty($type) || empty($operator)) {
            return true; // Incomplete condition, skip (treat as pass)
        }

        // Get the actual value from context based on condition type
        $actual = self::getContextValue($type, $context);

        // Allow plugins to handle custom condition types
        $custom_result = apply_filters('uwm_evaluate_condition', null, $type, $operator, $expected, $actual, $context);
        if ($custom_result !== null) {
            return (bool) $custom_result;
        }

        return self::compareValues($operator, $actual, $expected);
    }

    /**
     * Get the actual value from context for a given condition type.
     *
     * @param string $type    Condition type
     * @param array  $context Contextual data
     * @return mixed
     */
    private static function getContextValue(string $type, array $context)
    {
        switch ($type) {
            case 'image_size':
                return $context['image_size'] ?? '';

            case 'post_type':
                return $context['post_type'] ?? '';

            // Pro-only condition types
            case 'file_type':
                return $context['mime_type'] ?? '';

            case 'file_size':
                // File size in KB
                if (isset($context['file_path']) && file_exists($context['file_path'])) {
                    return round(filesize($context['file_path']) / 1024);
                }
                return $context['file_size_kb'] ?? 0;

            case 'image_width':
                if (isset($context['file_path']) && file_exists($context['file_path'])) {
                    $info = @getimagesize($context['file_path']);
                    return $info ? $info[0] : 0;
                }
                return $context['image_width'] ?? 0;

            case 'image_height':
                if (isset($context['file_path']) && file_exists($context['file_path'])) {
                    $info = @getimagesize($context['file_path']);
                    return $info ? $info[1] : 0;
                }
                return $context['image_height'] ?? 0;

            case 'user_role':
                if (isset($context['user_role'])) {
                    return $context['user_role'];
                }
                $user = wp_get_current_user();
                return !empty($user->roles) ? $user->roles[0] : '';

            case 'post_category':
                return $context['post_category'] ?? '';

            case 'image_orientation':
                if (isset($context['file_path']) && file_exists($context['file_path'])) {
                    $info = @getimagesize($context['file_path']);
                    if ($info) {
                        $w = $info[0];
                        $h = $info[1];
                        if ($w > $h) return 'landscape';
                        if ($h > $w) return 'portrait';
                        return 'square';
                    }
                }
                return $context['image_orientation'] ?? '';

            case 'image_aspect_ratio':
                if (isset($context['file_path']) && file_exists($context['file_path'])) {
                    $info = @getimagesize($context['file_path']);
                    if ($info && $info[1] > 0) {
                        return round($info[0] / $info[1], 2);
                    }
                }
                return $context['image_aspect_ratio'] ?? 0;

            case 'date_range':
                return $context['upload_date'] ?? date('Y-m-d');

            default:
                // Allow plugins to provide values for custom types
                return apply_filters('uwm_condition_context_value', null, $type, $context);
        }
    }

    /**
     * Compare actual value against expected using the given operator.
     *
     * @param string $operator  Comparison operator
     * @param mixed  $actual    Actual value from context
     * @param mixed  $expected  Expected value from condition
     * @return bool
     */
    private static function compareValues(string $operator, $actual, $expected): bool
    {
        switch ($operator) {
            case 'is':
                return strval($actual) === strval($expected);

            case 'is_not':
                return strval($actual) !== strval($expected);

            case 'greater_than':
                return floatval($actual) > floatval($expected);

            case 'less_than':
                return floatval($actual) < floatval($expected);

            case 'equals':
                return floatval($actual) == floatval($expected);

            default:
                // Allow plugins to handle custom operators
                $result = apply_filters('uwm_compare_values', null, $operator, $actual, $expected);
                return $result !== null ? (bool) $result : false;
        }
    }

    /**
     * Build context array from attachment and size info.
     * 
     * Convenience method to create the context array needed for evaluation.
     *
     * @param int    $attachment_id  Attachment ID
     * @param string $image_size     Image size name
     * @param int    $parent_post_id Optional parent post ID
     * @return array
     */
    public static function buildContext(int $attachment_id, string $image_size = 'full', int $parent_post_id = 0): array
    {
        $context = [
            'attachment_id' => $attachment_id,
            'image_size' => $image_size,
        ];

        // Get file path
        $file_path = get_attached_file($attachment_id);
        if ($file_path && file_exists($file_path)) {
            $context['file_path'] = $file_path;
            $context['mime_type'] = wp_check_filetype($file_path)['type'] ?? '';
            $context['file_size_kb'] = round(filesize($file_path) / 1024);
        }

        // Get post type from parent
        if (!$parent_post_id) {
            $parent_post_id = (int) get_post_meta($attachment_id, '_ulwm_uploaded_to_post_id', true);
        }
        if (!$parent_post_id) {
            $attachment = get_post($attachment_id);
            if ($attachment && $attachment->post_parent > 0) {
                $parent_post_id = $attachment->post_parent;
            }
        }
        if ($parent_post_id > 0) {
            $parent = get_post($parent_post_id);
            if ($parent) {
                $context['post_type'] = $parent->post_type;

                // Get categories for the parent post
                $categories = wp_get_post_categories($parent_post_id, ['fields' => 'slugs']);
                if (!empty($categories) && !is_wp_error($categories)) {
                    $context['post_category'] = $categories[0]; // First category slug
                }
            }
        }

        // Upload date
        $attachment_post = get_post($attachment_id);
        if ($attachment_post) {
            $context['upload_date'] = $attachment_post->post_date ? date('Y-m-d', strtotime($attachment_post->post_date)) : date('Y-m-d');
        }

        return $context;
    }
}
