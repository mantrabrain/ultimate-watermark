<?php

namespace Ultimate_Watermark\Admin\FieldItems;


class Select
{
	public static function render($field, $field_id, $value, $group_id = null)
	{
		$class = $field['class'] ?? '';

		$after = $field['after'] ?? '';

		$field_name = !(is_null($group_id)) ? $group_id . '[' . $field_id . ']' : $field_id;

		$options = $field['options'] ?? array();

		?>
		<div class="ultimate-watermark-fieldset">
			<select id="<?php echo esc_attr($field_name) ?>" class="<?php echo esc_attr($class) ?>"
					name="<?php echo esc_attr($field_name) ?>">
				<?php foreach ($options as $option_id => $option) {
					?>
					<option value="<?php echo esc_attr($option_id) ?>"
							<?php selected($value, $option_id) ?>><?php echo esc_html($option) ?></option>
					<?php
				}
				?>
			</select>
		</div>
		<?php
	}

	public static function sanitize($field, $raw_value, $field_id)
	{
		$options = $field['options'] ?? array();

		if (isset($options[$raw_value])) {

			return sanitize_text_field($raw_value);
		}
		return null;
	}

}
