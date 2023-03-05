<?php

namespace Ultimate_Watermark\Watermark;

class WatermarkImage
{
    private $watermark_id;

    public function __construct($watermark_id)
    {
        $this->watermark_id = absint($watermark_id);
    }

    public function get_watermark_image()
    {
        return absint(get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_image', true));
    }

    public function get_watermark_image_size_type()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_size_type', true);
    }

    public function get_watermark_image_absolute_width()
    {
        return absint(get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_absolute_width', true));
    }

    public function get_watermark_image_absolute_height()
    {
        return absint(get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_absolute_height', true));

    }

    public function get_watermark_image_scale_image_width()
    {
        return absint(get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_scale_image_width', true));
    }

    public function get_watermark_opacity()
    {
        return absint(get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_image_transparent', true));

    }

    public function get_watermark_image_quality()
    {
        return absint(get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_image_quality', true));
    }

    public function get_watermark_image_format()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_image_format', true);

    }
}