<?php

namespace Ultimate_Watermark\Image;

defined('ABSPATH') || exit;


final class ImageWatermark
{
    protected static $_instance = null;

    private static function is_instantiated()
    {
        if (!empty(self::$_instance) && (self::$_instance instanceof self)) {
            return true;
        }

        return false;
    }


    public static function instance()
    {
        if (self::is_instantiated()) {
            return self::$_instance;
        }
        self::setup_instance();

        return self::$_instance;
    }

    private static function setup_instance()
    {
        self::$_instance = new self;
    }

    public function save_image_file($image, $mime_type, $filepath, $quality)
    {

        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/pjpeg':
                imagejpeg($image, $filepath, $quality);

                break;

            case 'image/png':
                imagepng($image, $filepath, (int)round(9 - (9 * $quality / 100), 0));
                header('Content-Type: image/png');

                break;
        }
    }
    public function resize($image, $width, $height, $info)
    {
        $new_image = imagecreatetruecolor($width, $height);

        // check if this image is PNG, then set if transparent
        if ($info[2] === 3) {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            imagefilledrectangle($new_image, 0, 0, $width, $height, imagecolorallocatealpha($new_image, 255, 255, 255, 127));
        }

        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);

        return $new_image;
    }
    public function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
    {
        // create a cut resource
        $cut = imagecreatetruecolor($src_w, $src_h);

        // copy relevant section from background to the cut resource
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);

        // copy relevant section from watermark to the cut resource
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);

        // insert cut resource to destination image
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
    }
}
