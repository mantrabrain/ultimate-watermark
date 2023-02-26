<?php

namespace Ultimate_Watermark\Admin\FieldItems;


class Checkbox
{
	public static function render($field, $field_id, $value, $group_id = null)
	{
		$class = $field['class'] ?? '';

		$after = $field['after'] ?? '';

		$field_name = !(is_null($group_id)) ? $group_id . '[' . $field_id . ']' : $field_id;

        $option_label = $field['label'] ?? '';

        ?>
		<div class="ultimate-watermark-fieldset">

            <label for="<?php echo esc_attr($field_name); ?>_checkbox_item">
                <input id="<?php echo esc_attr($field_name); ?>_checkbox_item" <?php checked($value, 1); ?> type="checkbox" name="<?php echo esc_attr($field_name); ?>" value="1"
                                                    class="<?php echo esc_attr($class); ?>"/>
                <?php echo wp_kses($option_label, array('strong' => array(), 'br' => array('style'=>array()), 'a' => array('target'=>array(), 'href'=>array()))) ?>
            </label>



			<?php echo wp_kses($after, array(
					'a' => array('href' => array(), 'class' => array(), 'target' => array()),
					'h2' => array('class' => array()),
					'div' => array('class' => array())
			)); ?>
		</div>

		<?php
	}

	public static function sanitize($field, $raw_value, $field_id)
	{

		return absint($raw_value) === 1 ? 1 : 0;
	}

}
