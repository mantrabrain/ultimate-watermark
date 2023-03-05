<?php

namespace Ultimate_Watermark\Watermark;

class WatermarkPosition
{
    private $watermark_id;

    public function __construct($watermark_id)
    {
        $this->watermark_id = absint($watermark_id);
    }

    public function get_watermark_alignment()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_alignment', true);
    }

    public function get_watermark_offset_width()
    {
        return intval(get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_offset_width', true));
    }

    public function get_watermark_offset_height()
    {
        return intval(get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_offset_height', true));
    }

    public function get_watermark_offset_unit()
    {
        return get_post_meta($this->watermark_id, 'ultimate_watermark_watermark_offset_unit', true);

    }
}