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

    public function get_watermark_image_size()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_size', true);
    }

    public function get_watermark_image_size_x()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_size_x', true);
    }

    public function get_watermark_image_size_y()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_size_y', true);

    }

    public function get_watermark_image_scale()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_scale_image_width', true);
    }

    public function get_watermark_opacity()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_image_transparent', true);

    }

    public function get_watermark_image_quality()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_image_quality', true);
    }

    public function get_watermark_image_format()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_image_format', true);

    }
}