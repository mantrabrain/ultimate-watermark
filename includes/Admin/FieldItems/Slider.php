<?php

namespace Ultimate_Watermark\Admin\FieldItems;


class Slider
{
    public static function render($field, $field_id, $value, $group_id = null)
    {
        $class = $field['class'] ?? '';

        $after = $field['after'] ?? '';

        $field_name = !(is_null($group_id)) ? $group_id . '[' . $field_id . ']' : $field_id;

        $data = $field['data'] ?? array();

        $max = isset($data['max']) ? absint($data['max']) : 100;

        $min = isset($data['min']) ? absint($data['min']) : 1;

        $step = isset($data['step']) ? absint($data['step']) : 1;

        ?>
        <div class="ultimate-watermark-fieldset">
            <div class="slider-wrap">
                <input
                        name="<?php echo esc_attr($field_name); ?>"
                        id="<?php echo esc_attr($field_name); ?>"
                        type="hidden"
                        value="<?php echo esc_attr($value); ?>"
                        class="<?php echo esc_attr($class); ?>"/>

                <div class="ultimate-watermark-range-slider" data-max="<?php echo absint($max) ?>"
                     data-min="<?php echo absint($min) ?>" data-value="<?php echo absint($value) ?>"
                     data-step="<?php echo absint($step); ?>">
                    <div class="handle ui-slider-handle"></div>
                </div>

            </div>
            <?php
            echo wp_kses($after, array(
                'a' => array('href' => array(), 'class' => array(), 'target' => array()),
                'h2' => array('class' => array()),
                'div' => array('class' => array())
            )); ?>
        </div>
        <?php
    }

    public static function sanitize($field, $raw_value, $field_id)
    {

        return absint($raw_value);
    }

}
