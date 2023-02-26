<?php

namespace Ultimate_Watermark\Admin\FieldItems;


use Ultimate_Watermark\Admin\Fields\HTML;

class Image
{
	public static function render($field, $field_id, $value, $group_id = null)
	{
		$class = $field['class'] ?? '';

		$after = $field['after'] ?? '';

		$field_name = !(is_null($group_id)) ? $group_id . '[' . $field_id . ']' : $field_id;

		$field_group_id = !is_null($group_id) ? $group_id . '[' . $field_id . ']' : $field_id;

		$child_fields = $field['fields'] ?? array();

		$image_id_field = isset($field['image_id_field']) ? $field['image_id_field'] : '';

		$image_id = isset($value[$image_id_field]) ? $value[$image_id_field] : 0;


		?>
		<div class="ultimate-watermark-fieldset">
			<div class="ultimate-watermark-image-field-wrap">
				<a class="ultimate-watermark-image-field-add <?php echo $image_id > 1 ? 'ultimate-watermark-hide' : ''; ?>" href="#"
				   data-uploader-title="Add new image"
				   data-uploader-button-text="Add new image">
					<img src="<?php echo esc_url(ULTIMATE_WATERMARK_URI) ?>/assets/images/upload-image.png">
					<h3>Drop your file here, or <span>browse</span></h3>
					<p>Supports: JPG, JPEG, PNG</p>
				</a>
				<div class="image-container<?php echo $image_id < 1 ? ' ultimate-watermark-hide' : ''; ?>">
					<?php

					if ($image_id > 0) {
						$image_src = wp_get_attachment_image_url($image_id, 'full');

						?>
						<div class="image-wrapper" data-url="<?php echo esc_url_raw($image_src) ?>">
							<div class="image-content"><img
										src="<?php echo esc_url_raw($image_src) ?>"
										alt="">
								<div class="image-overlay"><a
											class="ultimate-watermark-image-delete remove dashicons dashicons-trash"></a>
								</div>
							</div>
						</div>
					<?php } ?>
				</div>
				<?php
				if (count($child_fields) > 0) {

					$class = 'field-container';
					$class .= $image_id < 1 ? ' ultimate-watermark-hide' : '';

					echo '<div class="' . esc_attr($class) . '">';

					foreach ($child_fields as $child_field_item_id => $child_field_item) {

						$default = $child_field_item['default'] ?? null;

						if (!is_array($value)) {

							$item_value = $default;
						} else {
							$item_value = $value[$child_field_item_id] ?? '';
						}
						HTML::render_item($child_field_item, $child_field_item_id, $item_value, $field_group_id);

					}
					echo '</div>';
				}
				?>

			</div>
		</div>
		<?php
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
