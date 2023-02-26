<?php

namespace Ultimate_Watermark\Admin\Fields;

class WatermarkImageFields extends Base
{
	public function get_settings()
	{
 		return [

			'ultimate_watermark_markers' => [
				'type' => 'group',
				'button_title' => __('Add New Marker', 'ultimate-watermark'),
				'repeatable' => true,
				'fields' => [
					'title' => [
						'type' => 'text',
						'title' => __('Title', 'ultimate-watermark'),
						'class' => 'ultimate-watermark-marker-title',
 					],
					'coordinates' => [
						'type' => 'fieldset',
						'title' => __('Coordinates', 'ultimate-watermark'),
						'fields' => [
							'location' => [
								'type' => 'text',
								'title' => __('Location', 'ultimate-watermark'),
								'class' => 'ultimate-watermark-marker-location',
 								'after' => '<a href="#" class="dashicons dashicons-search ultimate-watermark-location-search-button"></a>'


							],
							'ultimate_watermark_marker_map' => [
								'type' => 'content',
								'content' => '<h2>Drag The Marker</h2><div class="ultimate_watermark_marker_item_position"></div>'
							],
							'latitude' => [
								'type' => 'text',
								'title' => __('Latitude', 'ultimate-watermark'),
								'class' => 'ultimate-watermark-marker-latitude',

							],
							'longitude' => [
								'type' => 'text',
								'title' => __('Longitude', 'ultimate-watermark'),
								'class' => 'ultimate-watermark-marker-longitude',

							],
						],
					],
					'tooltip_content' => [
						'type' => 'textarea',
						'title' => __('Tooltip Content', 'ultimate-watermark'),
						'class' => 'ultimate-watermark-marker-content',
 						'allowed_html' => array(
							'a' => array(
								'href' => array(),
								'title' => array(),
								'target' => array()
							),
							'img' => array(
								'src' => array(),
								'title' => array()
							),
							'br' => array(),
							
							'strong' => array()
						),

					],
					'is_centered_marker' => [
						'type' => 'checkbox',
						'title' => __('Center Position', 'ultimate-watermark'),
						'class' => 'ultimate-watermark-marker-center-position',
						'desc' => __("Make this marker to center position on the map.", 'ultimate-watermark')

					],
					'ultimate_watermark_marker_item_image' => [
						'type' => 'image',
						'title' => __('Individual Marker Image', 'ultimate-watermark'),
						'class' => 'ultimate-watermark-marker-image',
						'desc' => __("No need to add any image if you want to use default marker.", 'ultimate-watermark'),
						'image_id_field' => 'id',
						'fields' => [
							'id' => [
								'type' => 'hidden',
								'title' => __('Height [in px]', 'ultimate-watermark'),
								'class' => 'ultimate-watermark-marker-image-id',
								'sanitize_callback' => function ($field, $raw_data, $field_id) {
									return $raw_data != '' ? absint($raw_data) : null;
								}

							],
							'height' => [
								'type' => 'number',
								'title' => __('Height [in px]', 'ultimate-watermark'),
								'class' => 'ultimate-watermark-marker-image-height',
								'default' => 40

							],
							'width' => [
								'type' => 'number',
								'title' => __('Width [in px]', 'ultimate-watermark'),
								'class' => 'ultimate-watermark-marker-image-width',
								'default' => 25

							]
						]


					],

				],
			],


		];
	}

	public function render()
	{
		$this->output();
	}

	public function nonce_id()
	{
		return 'ultimate_watermark_map_marker_fields';
	}

}
