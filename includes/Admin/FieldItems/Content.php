<?php

namespace Ultimate_Watermark\Admin\FieldItems;


class Content
{
	public static function render($field, $field_id, $value, $group_id = null)
	{
		$content = $field['content'] ?? '';

		echo '<div class="ultimate-watermark-map-render-element-wrap">';
		echo "<div id='{$group_id}' class='ultimate-watermark-marker-content-wrap'>";
		echo wp_kses($content, array(
			'a' => array('href' => array(), 'class' => array(), 'target' => array()),
			'h2' => array('class' => array()),
			'div' => array('class' => array())
		));
		echo '</div>';
		echo '</div>';
	}

	public static function sanitize($field, $raw_value, $field_id)
	{

		return '';
	}
}
