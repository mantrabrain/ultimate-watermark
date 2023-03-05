<?php

namespace Ultimate_Watermark;

use Ultimate_Watermark\Watermark\WatermarkConditions;
use Ultimate_Watermark\Watermark\WatermarkImage;
use Ultimate_Watermark\Watermark\WatermarkPosition;

class Watermark_Test
{
    private $watermark_id;

    public function __construct($watermark_id)
    {
        $this->watermark_id = $watermark_id;
    }

    public function get_conditions()
    {
        return new WatermarkConditions($this->watermark_id);
    }

    public function get_watermark_image()
    {
        return new WatermarkImage($this->watermark_id);
    }

    public function get_watermark_position()
    {
        return new WatermarkPosition($this->watermark_id);
    }

    public function get_watermark_type()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_type', true);
    }
}