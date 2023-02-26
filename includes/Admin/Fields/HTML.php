<?php

namespace Ultimate_Watermark\Admin\Fields;

class HTML
{
    public static function render_item($field, $field_id, $value, $group_id = null)
    {
        $type = $field['type'] ?? '';

        $class = 'ultimate-watermark-field ultimate-watermark-field-' . esc_attr($type);

        $title = $field['title'] ?? '';

        $desc = $field['desc'] ?? '';

        echo '<div class="' . esc_attr($class) . '" id="' . esc_attr($field_id) . '">';

        echo '<div class="ultimate-watermark-title">';

        if ($title != '') {
            echo '<h4>' . wp_kses($title, array('strong' => array(), 'br' => array())) . '</h4>';
        }
        if ($desc != '') {
            echo '<small>' . wp_kses($desc, array('strong' => array(), 'br' => array())) . '</small>';
        }
        echo '</div>';

        $type_class_name = ucwords($type);

        $class_name = "\Ultimate_Watermark\Admin\FieldItems\\" . $type_class_name;

        if (class_exists($class_name)) {

            $class_name::render($field, $field_id, $value, $group_id);
        }

        echo '<div class="clear"></div>';

        echo '</div>';
    }

    public static function render($fields, $group_id = null)
    {
        foreach ($fields as $field_id => $field) {

            $object_id = get_the_ID();

            $value = get_post_meta($object_id, $field_id, true);

            $default = $field['default'] ?? null;

            if (!metadata_exists('post', $object_id, $field_id)) {

                $value = is_null($value) || $value == '' ? $default : $value;
            }

            self::render_item($field, $field_id, $value, $group_id);

        }


    }

    public static function sanitize($settings, $post_data)
    {
        $valid_data = array();

        foreach ($settings as $field_id => $field) {

            $raw_data = $post_data[$field_id] ?? null;

            $valid_data[$field_id] = self::sanitize_item($field, $raw_data, $field_id);

        }
        return $valid_data;
    }

    public static function sanitize_item($field, $raw_data, $field_id)
    {
        $type = $field['type'] ?? '';

        $type_class_name = ucwords($type);

        $class_name = "\Ultimate_Watermark\Admin\FieldItems\\" . $type_class_name;

        $sanitize_callback = isset($field['sanitize_callback']) ? $field['sanitize_callback'] : '';

        if ($sanitize_callback != '' && is_callable($sanitize_callback)) {

            return $sanitize_callback($field, $raw_data, $field_id);

        } else if (class_exists($class_name)) {

            return $class_name::sanitize($field, $raw_data, $field_id);
        }


        return null;
    }
}
