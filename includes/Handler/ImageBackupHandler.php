<?php

namespace Ultimate_Watermark\Handler;

use Ultimate_Watermark\Processor\ImageProcessor;

class ImageBackupHandler
{
    public function get_class()
    {
        return ImageProcessor::instance();
    }


    private function do_image_backup($data, $upload_dir, $attachment_id)
    {
        // get the filepath for the backup image we're creating
        $backup_filepath = ultimate_watermark()->utils->get_image_backup_filepath($data['file']);

        // make sure the backup isn't created yet
        if (!file_exists($backup_filepath)) {
            // the original (full size) image
            $filepath = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'];
            $mime = wp_check_filetype($filepath);


            $image = $this->get_class()->get_image_resource($filepath, $mime['type']);
            if (false !== $image) {

                wp_mkdir_p($this->get_image_backup_folder_location($data['file']));

                // save backup image
                $this->get_class()->save_image_file($image, $mime['type'], $backup_filepath, ultimate_watermark_backup_image_quality());

                // clear backup memory
                imagedestroy($image);
                $image = null;
            }
        }
    }

    private function get_image_backup_folder_location($filepath)
    {


        $path = explode('/', $filepath);

        if (count($path) < 2) {

            $path = explode(DIRECTORY_SEPARATOR, $filepath);
        }

        array_pop($path);


        $path = implode(DIRECTORY_SEPARATOR, $path);

        // Multisite?
        /* if ( is_multisite() && ! is_main_site() ) {
          $path = 'sites' . DIRECTORY_SEPARATOR . get_current_blog_id() . DIRECTORY_SEPARATOR . $path;
          } */

        return ultimate_watermark()->get_backup_dir() . $path;
    }
}