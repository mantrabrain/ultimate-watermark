<?php

namespace Ultimate_Watermark\Admin\Fields;

class WatermarkGeneralFields extends Base
{
	public function get_settings()
	{
		return [
			'ultimate_watermark_popup_show_on' => [
				'title' => __('Marker Popup Shows on', 'ultimate-watermark'),
				'desc' => __("You can select whether marker popup shows on mouse hover or on click.", 'ultimate-watermark'),
				'type' => 'select',
				'class' => 'ultimate-watermark-popup-show-on',
				'options' => array(
					'click' => __('On Mouse Click', 'ultimate-watermark'),
					'mouseover' => __('On Mouse Over', 'ultimate-watermark')
				),
			],
			'ultimate_watermark_marker_image' => [
				'type' => 'image',
				'title' => __('Default Marker Image', 'ultimate-watermark'),
				'class' => 'ultimate-watermark-marker-image',
				'desc' => __("No need to add any image if you want to use default marker (which is red marker ). You can override this marker image by adding individual marker item image from Map Markers.", 'ultimate-watermark'),
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
			'ultimate_watermark_map_scroll_wheel_zoom' => [
				'type' => 'checkbox',
				'title' => __('Scroll wheel zoom', 'ultimate-watermark'),
				'class' => 'ultimate-watermark-marker-scroll-wheel-zoom',
				'desc' => __("Enable this to zoom on mouse scroll wheel.", 'ultimate-watermark')
			],
			'ultimate_watermark_map_control_position' => [
				'title' => __('Map Control Position', 'ultimate-watermark'),
				'desc' => __("Show or hide maps control or change the position of the control.", 'ultimate-watermark'),
				'type' => 'select',
				'class' => 'ultimate-watermark-map-control-position',
				'options' => array(
					'topright' => __('Top Right', 'ultimate-watermark'),
					'topleft' => __('Top Left', 'ultimate-watermark'),
					'bottomright' => __('Bottom Right', 'ultimate-watermark'),
					'bottomleft' => __('Bottom Left', 'ultimate-watermark'),
					'hide' => __('Hide', 'ultimate-watermark')
				),
			],

		];
	}

	public function render()
	{
		$this->output();
	}


	public function nonce_id()
	{
		return 'ultimate_watermark_map_general_setting_fields';
	}
}
