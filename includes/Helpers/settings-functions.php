<?php
if (!function_exists('ultimate_watermark_automatic_watermarking')) {
    function ultimate_watermark_automatic_watermarking()
    {
        if (get_option('ultimate_watermark_automatic_watermarking', 'no') === 'yes') {
            return true;
        }
        return false;
    }
}
if (!function_exists('ultimate_watermark_manual_watermarking')) {
    function ultimate_watermark_manual_watermarking()
    {
        if (get_option('ultimate_watermark_manual_watermarking', 'no') === 'yes') {
            return true;
        }
        return false;
    }

}

if (!function_exists('ultimate_watermark_watermark_on_image_size')) {
    function ultimate_watermark_watermark_on_image_size()
    {
        return get_option('ultimate_watermark_watermark_on_image_size', array());
    }

}

if (!function_exists('ultimate_watermark_watermark_on')) {
    function ultimate_watermark_watermark_on()
    {
        return get_option('ultimate_watermark_watermark_on', 'everywhere');
    }

}
if (!function_exists('ultimate_watermark_watermark_on_custom_post_type')) {
    function ultimate_watermark_watermark_on_custom_post_type()
    {
        return get_option('ultimate_watermark_watermark_on_custom_post_type', array());
    }

}
if (!function_exists('ultimate_watermark_frontend_watermarking')) {
    function ultimate_watermark_frontend_watermarking()
    {
        return get_option('ultimate_watermark_frontend_watermarking', 'no');
    }

}

if (!function_exists('ultimate_watermark_watermark_alignment')) {
    function ultimate_watermark_watermark_alignment()
    {
        return get_option('ultimate_watermark_watermark_alignment', 'bottom_right');
    }

}

if (!function_exists('ultimate_watermark_offset_width')) {
    function ultimate_watermark_offset_width()
    {
        return absint(get_option('ultimate_watermark_offset_width', 0));
    }

}
if (!function_exists('ultimate_watermark_offset_height')) {
    function ultimate_watermark_offset_height()
    {
        return absint(get_option('ultimate_watermark_offset_height', 0));
    }

}
if (!function_exists('ultimate_watermark_watermark_offset_unit')) {
    function ultimate_watermark_watermark_offset_unit()
    {
        return absint(get_option('ultimate_watermark_watermark_offset_unit', 'pixels'));
    }

}
if (!function_exists('ultimate_watermark_watermark_image')) {
    function ultimate_watermark_watermark_image()
    {
        return absint(get_option('ultimate_watermark_watermark_image', 0));
    }

}
if (!function_exists('ultimate_watermark_watermark_size_type')) {
    function ultimate_watermark_watermark_size_type()
    {
        return (get_option('ultimate_watermark_watermark_size_type', 'original'));
    }

}
if (!function_exists('ultimate_watermark_absolute_width')) {
    function ultimate_watermark_absolute_width()
    {
        return absint(get_option('ultimate_watermark_absolute_width', 0));
    }

}
if (!function_exists('ultimate_watermark_absolute_height')) {
    function ultimate_watermark_absolute_height()
    {
        return absint(get_option('ultimate_watermark_absolute_height', 0));
    }

}
if (!function_exists('ultimate_watermark_scaled_image_width')) {
    function ultimate_watermark_scaled_image_width()
    {
        return absint(get_option('ultimate_watermark_scaled_image_width', 80));
    }

}