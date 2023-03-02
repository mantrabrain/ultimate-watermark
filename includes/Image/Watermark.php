<?php

namespace Ultimate_Watermark\Image;

use Ultimate_Watermark\Processor\ImageProcessor;

class Watermark
{
    public function get_class()
    {
        return ImageProcessor::instance();
    }

    public function apply_watermark($data, $attachment_id, $method = '')
    {
        $post = get_post((int)$attachment_id);

        $post_id = (!empty($post) ? (int)$post->post_parent : 0);

        if ($attachment_id == ultimate_watermark_watermark_image()) {
            // this is the current watermark, do not apply
            return array('error' => __('Watermark prevented, this is your selected watermark image', 'ultimate-watermark'));
        }

        if ($method !== 'manual') {

            if (is_admin()) {

                if (
                    !((ultimate_watermark_watermark_on() === 'everywhere') ||
                        ($post_id > 0 && ultimate_watermark_watermark_on() === 'selected_custom_post_types' && in_array(get_post_type($post_id), array_keys(ultimate_watermark_watermark_on_custom_post_type())))
                    )) {
                    return $data;
                }
            }

        }

        if (apply_filters('ulwm_watermark_display', $attachment_id) === false)
            return $data;

        // get upload dir data
        $upload_dir = wp_upload_dir();

        // assign original (full) file
        $original_file = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'];

        // is this really an image?
        if (getimagesize($original_file, $original_image_info) !== false) {
            $metadata = $this->get_class()->get_image_metadata($original_image_info);

            // remove the watermark if this image was already watermarked
            if ((int)get_post_meta($attachment_id, 'ulwm-is-watermarked', true) === 1)
                $this->remove_watermark($data, $attachment_id, 'manual');

            // create a backup if this is enabled
            if (ultimate_watermark_backup_image())
                $this->do_backup($data, $upload_dir, $attachment_id);

            // loop through active image sizes
            foreach (ultimate_watermark_watermark_on_image_size() as $image_size => $active_size) {
                if ($active_size === 'yes') {
                    switch ($image_size) {
                        case 'full':
                            $filepath = $original_file;
                            break;

                        default:
                            if (!empty($data['sizes']) && array_key_exists($image_size, $data['sizes']))
                                $filepath = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . dirname($data['file']) . DIRECTORY_SEPARATOR . $data['sizes'][$image_size]['file'];
                            else
                                // early getaway
                                continue 2;
                    }

                    do_action('ulwm_before_apply_watermark', $attachment_id, $image_size);

                    // apply watermark
                    $this->do_watermark($attachment_id, $filepath, $image_size, $upload_dir, $metadata);

                    // save metadata
                    $this->get_class()->save_image_metadata($metadata, $filepath);

                    do_action('ulwm_after_apply_watermark', $attachment_id, $image_size);
                }
            }

            // update watermark status
            update_post_meta($attachment_id, 'ulwm-is-watermarked', 1);
        }

        // pass forward attachment metadata
        return $data;
    }



    public function do_watermark($attachment_id, $image_path, $image_size, $upload_dir, $metadata = array())
    {

        // get image mime type
        $mime = wp_check_filetype($image_path);

        // get watermark path
        $watermark_file = wp_get_attachment_metadata(ultimate_watermark_watermark_image(), true);
        $watermark_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];

        // imagick extension
        if (ultimate_watermark()->utils->get_extension() === 'imagick') {
            // create image resource
            $image = new \Imagick($image_path);

            // create watermark resource
            $watermark = new \Imagick($watermark_path);

            // alpha channel exists?
            if ($watermark->getImageAlphaChannel() > 0)
                $watermark->evaluateImage(\Imagick::EVALUATE_MULTIPLY, round((float)(ultimate_watermark_image_transparent() / 100), 2), \Imagick::CHANNEL_ALPHA);
            // no alpha channel
            else
                $watermark->setImageOpacity(round((float)(ultimate_watermark_image_transparent() / 100), 2));

            // set compression quality
            if ($mime['type'] === 'image/jpeg') {
                $image->setImageCompressionQuality(ultimate_watermark_image_quality());
                $image->setImageCompression(\Imagick::COMPRESSION_JPEG);
            } else
                $image->setImageCompressionQuality(ultimate_watermark_image_quality());

            // set image output to progressive
            if (ultimate_watermark_image_format() === 'progressive')
                $image->setImageInterlaceScheme(\Imagick::INTERLACE_PLANE);

            // get image dimensions
            $image_dim = $image->getImageGeometry();

            // get watermark dimensions
            $watermark_dim = $watermark->getImageGeometry();

            // calculate watermark new dimensions
            list($width, $height) = $this->calculate_watermark_dimensions($image_dim['width'], $image_dim['height'], $watermark_dim['width'], $watermark_dim['height']);

            // resize watermark
            $watermark->resizeImage($width, $height, \Imagick::FILTER_CATROM, 1);

            // calculate image coordinates
            list($dest_x, $dest_y) = $this->calculate_image_coordinates($image_dim['width'], $image_dim['height'], $width, $height);

            // combine two images together
            $image->compositeImage($watermark, \Imagick::COMPOSITE_DEFAULT, $dest_x, $dest_y, \Imagick::CHANNEL_ALL);

            // save watermarked image
            $image->writeImage($image_path);

            // clear image memory
            $image->clear();
            $image->destroy();
            $image = null;

            // clear watermark memory
            $watermark->clear();
            $watermark->destroy();
            $watermark = null;
            // gd extension
        } else {
            // get image resource
            $image = $this->get_class()->get_image_resource($image_path, $mime['type']);

            if ($image !== false) {
                // add watermark image to image
                $image = $this->add_watermark_image($image, $upload_dir);

                if ($image !== false) {
                    // save watermarked image
                    $this->get_class()->save_image_file($image, $mime['type'], $image_path, ultimate_watermark_image_quality());

                    // clear watermark memory
                    imagedestroy($image);

                    $image = null;
                }
            }
        }
    }


    private function do_backup($data, $upload_dir, $attachment_id)
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

    public function remove_watermark($data, $attachment_id, $method = '')
    {
        if ($method !== 'manual')
            return $data;

        $upload_dir = wp_upload_dir();

        // is this really an image?
        if (getimagesize($upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file']) !== false) {
            // live file path (probably watermarked)
            $filepath = get_attached_file($attachment_id);

            // backup file path (not watermarked)
            $backup_filepath = ultimate_watermark()->utils->get_image_backup_filepath(get_post_meta($attachment_id, '_wp_attached_file', true));

            // replace the image in uploads with our backup if one exists
            if (file_exists($backup_filepath)) {
                if (!copy($backup_filepath, $filepath)) {
                    // Failed to copy
                }
            }

            // if no backup exists, use the current full-size image to regenerate
            // if the "full" size is enabled for watermarks and no backup has been made the removal of watermarks can't be done

            // regenerate metadata (and thumbs)
            $metadata = wp_generate_attachment_metadata($attachment_id, $filepath);

            // update attachment metadata with new metadata
            wp_update_attachment_metadata($attachment_id, $metadata);

            // update watermark status
            update_post_meta($attachment_id, 'ulwm-is-watermarked', 0);

            // ureturn the attachment metadata
            return wp_get_attachment_metadata($attachment_id);
        }

        return false;
    }

    private function calculate_watermark_dimensions($image_width, $image_height, $watermark_width, $watermark_height)
    {
        // custom
        if (ultimate_watermark_watermark_size_type() === 'custom') {
            $width = ultimate_watermark_absolute_width();
            $height = ultimate_watermark_absolute_height();
            // scale
        } elseif (ultimate_watermark_watermark_size_type() === 'scaled') {
            $ratio = $image_width * ultimate_watermark_scaled_image_width() / 100 / $watermark_width;

            $width = (int)($watermark_width * $ratio);
            $height = (int)($watermark_height * $ratio);

            // if watermark scaled height is bigger then image watermark
            if ($height > $image_height) {
                $width = (int)($image_height * $width / $height);
                $height = $image_height;
            }
            // original
        } else {
            $width = $watermark_width;
            $height = $watermark_height;
        }

        return array($width, $height);
    }


    private function calculate_image_coordinates($image_width, $image_height, $watermark_width, $watermark_height)
    {
        switch (ultimate_watermark_watermark_alignment()) {
            case 'top_left':
                $dest_x = $dest_y = 0;
                break;

            case 'top_center':
                $dest_x = ($image_width / 2) - ($watermark_width / 2);
                $dest_y = 0;
                break;

            case 'top_right':
                $dest_x = $image_width - $watermark_width;
                $dest_y = 0;
                break;

            case 'middle_left':
                $dest_x = 0;
                $dest_y = ($image_height / 2) - ($watermark_height / 2);
                break;

            case 'middle_right':
                $dest_x = $image_width - $watermark_width;
                $dest_y = ($image_height / 2) - ($watermark_height / 2);
                break;

            case 'bottom_left':
                $dest_x = 0;
                $dest_y = $image_height - $watermark_height;
                break;

            case 'bottom_center':
                $dest_x = ($image_width / 2) - ($watermark_width / 2);
                $dest_y = $image_height - $watermark_height;
                break;

            case 'bottom_right':
                $dest_x = $image_width - $watermark_width;
                $dest_y = $image_height - $watermark_height;
                break;

            case 'middle_center':
            default:
                $dest_x = ($image_width / 2) - ($watermark_width / 2);
                $dest_y = ($image_height / 2) - ($watermark_height / 2);
        }

        $offset_width = ultimate_watermark_offset_width();
        $offset_height = ultimate_watermark_offset_height();

        if (ultimate_watermark_watermark_offset_unit() === 'pixels') {
            $dest_x += $offset_width;
            $dest_y += $offset_height;
        } else {
            $dest_x += (($image_width * $offset_width) / 100);
            $dest_y += (($image_width * $offset_height) / 100);
        }

        return array($dest_x, $dest_y);
    }

    private function add_watermark_image($image, $upload_dir)
    {
        $watermark_file = wp_get_attachment_metadata(ultimate_watermark_watermark_image(), true);
        $url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];
        $watermark_file_info = getimagesize($url);

        switch ($watermark_file_info['mime']) {
            case 'image/jpeg':
            case 'image/pjpeg':
                $watermark = imagecreatefromjpeg($url);
                break;

            case 'image/gif':
                $watermark = imagecreatefromgif($url);
                break;

            case 'image/png':
                $watermark = imagecreatefrompng($url);
                break;

            default:
                return false;
        }

        // get image dimensions
        $image_width = imagesx($image);
        $image_height = imagesy($image);

        // calculate watermark new dimensions
        list($w, $h) = $this->calculate_watermark_dimensions($image_width, $image_height, imagesx($watermark), imagesy($watermark));

        // calculate image coordinates
        list($dest_x, $dest_y) = $this->calculate_image_coordinates($image_width, $image_height, $w, $h);

        // combine two images together
        $this->get_class()->imagecopymerge_alpha($image, $this->get_class()->resize($watermark, $w, $h, $watermark_file_info), $dest_x, $dest_y, 0, 0, $w, $h, ultimate_watermark_image_transparent());

        if (ultimate_watermark_image_format() === 'progressive')
            imageinterlace($image, true);

        return $image;
    }


}