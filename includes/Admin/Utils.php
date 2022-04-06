<?php

namespace Ultimate_Watermark\Admin;
class Utils
{
    private $extensions;

    private $extension;

    public function __construct()
    {
        add_action('admin_init', array($this, 'check_extensions'));

    }

    public function get_image_backup_filepath($filepath)
    {
        // Multisite?
        /* if ( is_multisite() && ! is_main_site() ) {
          $filepath = 'sites' . DIRECTORY_SEPARATOR . get_current_blog_id() . DIRECTORY_SEPARATOR . $filepath;
          } */
        return ultimate_watermark()->get_backup_dir(true) . $filepath;
    }

    public function get_extensions()
    {
        return $this->extensions;
    }

    public function get_extension()
    {
        return $this->extension;
    }

    public function get_allowed_mime_types()
    {
        return array(
            'image/jpeg',
            'image/pjpeg',
            'image/png'
        );
    }

    public function check_extensions()
    {
        $ext = null;

        if ($this->check_imagick()) {
            $this->extensions['imagick'] = 'ImageMagick';
            $ext = 'imagick';
        }

        if ($this->check_gd()) {
            $this->extensions['gd'] = 'GD';

            if (is_null($ext))
                $ext = 'gd';
        }


        $this->extension = $ext;
    }

    private function check_imagick()
    {
        // check Imagick's extension and classes
        if (!extension_loaded('imagick') || !class_exists('Imagick', false) || !class_exists('ImagickPixel', false))
            return false;

        // check version
        if (version_compare(phpversion('imagick'), '2.2.0', '<'))
            return false;

        // check for deep requirements within Imagick
        if (!defined('Imagick::COMPRESSION_JPEG') || !defined('Imagick::COMPOSITE_OVERLAY') || !defined('Imagick::INTERLACE_PLANE') || !defined('Imagick::FILTER_CATROM') || !defined('Imagick::CHANNEL_ALL'))
            return false;

        // check methods
        if (array_diff(array('clear', 'destroy', 'valid', 'getimage', 'writeimage', 'getimagegeometry', 'getimageformat', 'setimageformat', 'setimagecompression', 'setimagecompressionquality', 'scaleimage'), get_class_methods('Imagick')))
            return false;

        return true;
    }

    public function check_gd($args = array())
    {
        // check extension
        if (!extension_loaded('gd') || !function_exists('gd_info'))
            return false;

        return true;
    }

}