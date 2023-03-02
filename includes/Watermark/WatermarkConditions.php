<?php

namespace Ultimate_Watermark\Watermark;

class WatermarkConditions
{
    private $watermark_id;
    
    public function __construct($watermark_id)
    {
        $this->watermark_id = absint($watermark_id);
    }

    public function is_automatic_watermarking()
    {
        return (boolean)get_post_meta($this->watermark_id, 'ultimate_watermark_automatic_watermarking', true);
    }

    public function is_manual_watermarking()
    {
        return (boolean)get_post_meta($this->watermark_id, 'ultimate_watermark_manual_watermarking', true);
    }

    public function is_frontend_watermarking()
    {

        return (boolean)get_post_meta($this->watermark_id, 'ultimate_watermark_frontend_watermarking', true);
    }

    public function get_image_sizes()
    {
        $image_sizes = get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_on_image_size', true);

        return is_array($image_sizes) ? $image_sizes : [];

    }

    public function watermark_on()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_on', true);
    }

    public function watermark_for()
    {
        $watermark_for = get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_on_custom_post_type', true);

        return is_array($watermark_for) ? $watermark_for : [];

    }
}