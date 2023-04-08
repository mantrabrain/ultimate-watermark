<?php

namespace Ultimate_Watermark\Handler;


use Ultimate_Watermark\Watermark\WatermarkConditions;
use Ultimate_Watermark\Watermark\WatermarkGeneral;
use Ultimate_Watermark\Watermark\WatermarkImage;
use Ultimate_Watermark\Watermark\WatermarkPosition;

class WatermarkHandler
{
    private $attachment_id;

    /** @var WatermarkConditions */
    private $watermark_conditions;

    /** @var WatermarkImage */
    private $watermark_image;

    /** @var WatermarkPosition */
    private $watermark_position;

    /** @var WatermarkGeneral */
    private $watermark_general;

    /**
     * apply watermark
     *
     * @param int $attachment_id Attachment ID.
     * @param \Ultimate_Watermark\Watermark_Test $watermark Watermark.
     */
    public function __construct($attachment_id, $watermark)
    {
        $this->attachment_id = $attachment_id;
        $this->watermark_conditions = $watermark->get_conditions();
        $this->watermark_image = $watermark->get_watermark_image();
        $this->watermark_position = $watermark->get_watermark_position();
        $this->watermark_general = $watermark->get_general();
    }

    public function apply_watermark()
    {
        if (!$this->watermark_general->is_enabled()) {
            return false;
        }
        if ($this->watermark_general->get_watermark_type() === "image") {
            // Image Watermark Goes Here
        }

        do_action('ultimate_watermark_apply_the_watermark', $this);
    }

}