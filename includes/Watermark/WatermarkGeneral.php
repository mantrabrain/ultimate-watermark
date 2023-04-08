<?php

namespace Ultimate_Watermark\Watermark;

class WatermarkGeneral
{
    private $watermark_id;

    public function __construct($watermark_id)
    {
        $this->watermark_id = absint($watermark_id);
    }

    public function get_watermark_type()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_type', true);
    }

    public function is_enabled()
    {
        return (boolean)get_post_meta($this->watermark_id, 'ultimate_watermark_enable_this_watermark', true);
    }
}