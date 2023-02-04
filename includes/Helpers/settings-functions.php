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
        if (get_option('ultimate_watermark_manual_watermarking', 'yes') === 'yes') {
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
        return get_option('ultimate_watermark_frontend_watermarking', 'no') == 'yes';
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
        return intval(get_option('ultimate_watermark_offset_width', 0));
    }

}
if (!function_exists('ultimate_watermark_offset_height')) {
    function ultimate_watermark_offset_height()
    {
        return intval(get_option('ultimate_watermark_offset_height', 0));
    }

}
if (!function_exists('ultimate_watermark_watermark_offset_unit')) {
    function ultimate_watermark_watermark_offset_unit()
    {
        return get_option('ultimate_watermark_watermark_offset_unit', 'pixels');
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
if (!function_exists('ultimate_watermark_image_transparent')) {
    function ultimate_watermark_image_transparent()
    {
        return absint(get_option('ultimate_watermark_image_transparent', 50));
    }

}
if (!function_exists('ultimate_watermark_image_quality')) {
    function ultimate_watermark_image_quality()
    {
        return absint(get_option('ultimate_watermark_image_quality', 90));
    }

}
if (!function_exists('ultimate_watermark_image_format')) {
    function ultimate_watermark_image_format()
    {
        return (get_option('ultimate_watermark_image_format', 'baseline'));
    }

}
if (!function_exists('ultimate_watermark_disable_rightclick')) {
    function ultimate_watermark_disable_rightclick()
    {
        return get_option('ultimate_watermark_disable_rightclick', 'no') == 'yes';
    }

}
if (!function_exists('ultimate_watermark_disable_drag_and_drop')) {
    function ultimate_watermark_disable_drag_and_drop()
    {
        return (get_option('ultimate_watermark_disable_drag_and_drop', 'no') == 'yes');
    }

}
if (!function_exists('ultimate_watermark_enable_protection_for_logged_in_users')) {
    function ultimate_watermark_enable_protection_for_logged_in_users()
    {
        return (get_option('ultimate_watermark_enable_protection_for_logged_in_users', 'no') == 'yes');
    }

}
if (!function_exists('ultimate_watermark_backup_image')) {
    function ultimate_watermark_backup_image()
    {
        return (get_option('ultimate_watermark_backup_image', 'yes') == 'yes');
    }

}
if (!function_exists('ultimate_watermark_backup_image_quality')) {
    function ultimate_watermark_backup_image_quality()
    {
        return absint(get_option('ultimate_watermark_backup_image_quality', 90));
    }

}