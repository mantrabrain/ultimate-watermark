<?php

namespace Ultimate_Watermark\Lib;

class Imagick
{
    public function process_watermark_image($watermark_path, $watermark_image_setting)
    {

        $watermark = new \Imagick($watermark_path);

        // alpha channel exists?
        if ($watermark->getImageAlphaChannel() > 0) {
            $watermark->evaluateImage(\Imagick::EVALUATE_MULTIPLY, round((float)($watermark_image_setting->get_watermark_opacity() / 100), 2), \Imagick::CHANNEL_ALPHA);
            // no alpha channel
        } else {
            $watermark->setImageOpacity(round((float)($watermark_image_setting->get_watermark_opacity() / 100), 2));
        }

        $watermark_dim = $watermark->getImageGeometry();

        // calculate watermark new dimensions
        list($width, $height) = $this->calculate_watermark_dimensions($image_dim['width'], $image_dim['height'], $watermark_dim['width'], $watermark_dim['height']);


        return $watermark;
    }

    public function process_media_image()
    {

    }


}
