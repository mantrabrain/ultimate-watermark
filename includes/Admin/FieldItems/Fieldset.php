<?php

namespace Ultimate_Watermark\Admin\FieldItems;

use Ultimate_Watermark\Admin\Fields\HTML;

class Fieldset
{
	public static function render($field, $field_id, $value, $group_id = null)
	{
		echo '<div class="ultimate-watermark-fieldset">';
		echo '<div class="ultimate-watermark-fieldset-content">';

		$field_group_id = !is_null($group_id) ? $group_id . '[' . $field_id . ']' : $field_id;

		$child_fields = $field['fields'] ?? array();

		if (count($child_fields) > 0) {

			foreach ($child_fields as $child_field_item_id => $child_field_item) {

				$default = $child_field_item['default'] ?? null;

				if (!is_array($value)) {

					$item_value = $default;
				} else {
					$item_value = $value[$child_field_item_id] ?? '';
				}
				HTML::render_item($child_field_item, $child_field_item_id, $item_value, $field_group_id);

			}
		}
		echo '</div>';
		echo '</div>';
	}

	public static function sanitize($field, $raw_data, $field_id)
	{

		$valid_data = array();

		$fields = $field['fields'] ?? array();

		foreach ($fields as $set_field_id => $set_field) {

			$set_raw_data = $raw_data[$set_field_id] ?? null;

			if (!is_null($set_raw_data)) {

				$valid_data[$set_field_id] = HTML::sanitize_item($set_field, $set_raw_data, $set_field_id);
			}
		}

		return $valid_data;

	}

}
