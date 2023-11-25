<?php

namespace Ultimate_Watermark\Abstracts;

use Ultimate_Watermark\Watermark\WatermarkConditions;
use Ultimate_Watermark\Watermark\WatermarkImage;
use Ultimate_Watermark\Watermark\WatermarkPosition;

abstract class AbstractWatermarkHandler
{
    private $attachment_id;

    /** @var WatermarkConditions */
    private $watermark_conditions;

    /** @var WatermarkImage */
    private $watermark_image;

    /** @var WatermarkPosition */
    private $watermark_position;


    private $write_on_image;
    

    /**
     * apply watermark
     *
     * @param int $attachment_id Attachment ID.
     * @param \Ultimate_Watermark\Watermark_Test $watermark Watermark.
     */
    public function __construct($attachment_id, $watermark, $write_on_image = true)
    {
        $this->attachment_id = $attachment_id;
        $this->watermark_conditions = $watermark->get_conditions();
        $this->watermark_position = $watermark->get_watermark_position();
        $this->write_on_image = $write_on_image;
    }

    abstract public function get_class();    

    abstract function apply_the_watermark();

    abstract function do_watermark();

    abstract function remove_watermark();
}