<?php

namespace Ultimate_Watermark\Processor;

defined('ABSPATH') || exit;


final class ImageProcessor
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
    public function get_image_metadata($imageinfo)
    {
        $metadata = array(
            'exif' => null,
            'iptc' => null
        );

        if (is_array($imageinfo)) {
            // prepare EXIF data bytes from source file
            $exifdata = key_exists('APP1', $imageinfo) ? $imageinfo['APP1'] : null;

            if ($exifdata) {
                $exiflength = strlen($exifdata) + 2;

                // construct EXIF segment
                if ($exiflength > 0xFFFF) {
                    return $metadata;
                } else
                    $metadata['exif'] = chr(0xFF) . chr(0xE1) . chr(($exiflength >> 8) & 0xFF) . chr($exiflength & 0xFF) . $exifdata;
            }

            // prepare IPTC data bytes from source file
            $iptcdata = key_exists('APP13', $imageinfo) ? $imageinfo['APP13'] : null;

            if ($iptcdata) {
                $iptclength = strlen($iptcdata) + 2;

                // construct IPTC segment
                if ($iptclength > 0xFFFF) {
                    return $metadata;
                } else
                    $metadata['iptc'] = chr(0xFF) . chr(0xED) . chr(($iptclength >> 8) & 0xFF) . chr($iptclength & 0xFF) . $iptcdata;
            }
        }

        return $metadata;
    }

    public function save_image_metadata($metadata, $file)
    {
        try {
            $mime = wp_check_filetype($file);

            if (file_exists($file) && $mime['type'] !== 'image/png') {
                $exifdata = $metadata['exif'];
                $iptcdata = $metadata['iptc'];

                $destfilecontent = @file_get_contents($file);

                if (!$destfilecontent) {
                    return false;
                }

                if (strlen($destfilecontent) < 1) {
                    return false;
                }

                if (strlen($destfilecontent) > 0) {
                    $destfilecontent = substr($destfilecontent, 2);

                    // variable accumulates new & original IPTC application segments
                    $portiontoadd = chr(0xFF) . chr(0xD8);

                    $exifadded = !$exifdata;
                    $iptcadded = !$iptcdata;

                    if (is_string(substr($destfilecontent, 0, 2))) {
                        return false;
                    }
                    while (@(substr($destfilecontent, 0, 2) & 0xFFF0) === 0xFFE0) {
                        $segmentlen = (substr($destfilecontent, 2, 2) & 0xFFFF);

                        // last 4 bits of second byte is IPTC segment
                        $iptcsegmentnumber = (substr($destfilecontent, 1, 1) & 0x0F);

                        if ($segmentlen <= 2)
                            return false;

                        $thisexistingsegment = substr($destfilecontent, 0, $segmentlen + 2);

                        if (($iptcsegmentnumber >= 1) && (!$exifadded)) {
                            $portiontoadd .= $exifdata;
                            $exifadded = true;

                            if ($iptcsegmentnumber === 1)
                                $thisexistingsegment = '';
                        }

                        if (($iptcsegmentnumber >= 13) && (!$iptcadded)) {
                            $portiontoadd .= $iptcdata;
                            $iptcadded = true;

                            if ($iptcsegmentnumber === 13)
                                $thisexistingsegment = '';
                        }

                        $portiontoadd .= $thisexistingsegment;
                        $destfilecontent = substr($destfilecontent, $segmentlen + 2);

                        if (is_string(substr($destfilecontent, 0, 2))) {
                            return false;
                        }
                    }

                    // add EXIF data if not added already
                    if (!$exifadded) {
                        $portiontoadd .= $exifdata;
                    }

                    // add IPTC data if not added already
                    if (!$iptcadded) {
                        $portiontoadd .= $iptcdata;
                    }

                    $outputfile = fopen($file, 'w');

                    if ($outputfile) {
                        return fwrite($outputfile, $portiontoadd . $destfilecontent);
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public function get_image_resource($filepath, $mime_type)
    {
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/pjpeg':
                $image = imagecreatefromjpeg($filepath);
                break;

            case 'image/png':
                $image = imagecreatefrompng($filepath);
                break;

            default:
                $image = false;
        }

        if (is_resource($image)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $image;
    }
}
