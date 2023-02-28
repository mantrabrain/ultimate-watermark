<?php

namespace Ultimate_Watermark\Admin\FieldItems;


class Multicheckbox
{
    public static function render($field, $field_id, $value, $group_id = null)
    {
        $class = $field['class'] ?? '';

        $after = $field['after'] ?? '';

        $field_name = !(is_null($group_id)) ? $group_id . '[' . $field_id . ']' : $field_id;

        $options = $field['options'] ?? array();

        foreach ($options as $option) {

            $option_id = $option['id'] ?? '';

            $multi_checkbox_option_value = is_array($value) && isset($value[$option_id]) ? $value[$option_id] : '';

            $option_label = $option['title'] ?? '';

            $option_field_name = $field_name . '[' . $option_id . ']';
            ?>

            <div class="ultimate-watermark-fieldset">
                <label for="<?php echo esc_attr($option_field_name); ?>">
                    <input name="<?php echo esc_attr($option_field_name); ?>"
                        <?php checked(1, $multi_checkbox_option_value); ?>
                           id="<?php echo esc_attr($option_field_name); ?>" type="checkbox"
                           class="<?php echo esc_attr($class); ?>" value="1">
                    <?php echo wp_kses($option_label, array('strong' => array(), 'span' => array('style' => array()), 'a' => array('target' => array(), 'href' => array()))) ?>
                </label>
                <?php echo wp_kses($after, array(
                    'a' => array('href' => array(), 'class' => array(), 'target' => array()),
                    'h2' => array('class' => array()),
                    'div' => array('class' => array())
                )); ?>
            </div>

            <?php
        }
    }

    public static function sanitize($field, $raw_value, $field_id)
    {
        $options = $field['options'] ?? array();

        $final_value = array();

        foreach ($options as $option) {

            $id = isset($option['id']) ? sanitize_text_field($option['id']) : '';

            if (is_array($raw_value) && isset($raw_value[$id])) {
                $final_value[$id] = (boolean)$raw_value[$id];
            }
        }
        return $final_value;
    }

}
