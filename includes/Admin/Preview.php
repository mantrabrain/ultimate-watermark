<?php

namespace Ultimate_Watermark\Admin;


use Ultimate_Watermark\Handler\ImageWatermarkHandler;

class Preview
{
    public static function init()
    {
        $self = new self();

        add_action('ultimate_watermark_placeholder_preview', array($self, 'preview_image_placeholder'));
    }

    public function preview_image_placeholder($watermark_id)
    {

        $image_url = (ULTIMATE_WATERMARK_DIR) . 'assets/images/preview-placeholder.png';


        $watermark = ultimate_watermark_get_watermark($watermark_id);

        $watermark_image = $watermark->get_watermark_image();

        $watermark_general = $watermark->get_general();

        if ($watermark_image->get_watermark_image() > 0 && $watermark_general->is_enabled() && $watermark_general->get_watermark_type() === "image") {

            $watermark_file = wp_get_attachment_metadata($watermark_image->get_watermark_image(), true);

            $upload_dir = wp_upload_dir();

            $watermark_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];

            $watermark_handler = new ImageWatermarkHandler(0, $watermark);

            $watermark_handler->do_watermark($image_url, $watermark_path, [], false);
        }

        ultimate_watermark_print_image($image_url);
    }

}
