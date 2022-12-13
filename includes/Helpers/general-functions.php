<?php
if (!function_exists('ultimate_watermark_tippy_tooltip')) {
    function ultimate_watermark_tippy_tooltip($content, $echo = true)
    {
        $tippy_content = '<span class="ultimate-watermark-tippy-tooltip dashicons dashicons-editor-help" data-tippy-content="' . esc_attr($content) . '"></span>';

        if ($echo) {
            echo $tippy_content;
        }
        return $tippy_content;
    }
}

function ultimate_watermark_get_all_image_sizes()
{
    global $_wp_additional_image_sizes;

    $default_image_sizes = get_intermediate_image_sizes();

    foreach ($default_image_sizes as $size) {
        $image_sizes[$size]['width'] = intval(get_option("{$size}_size_w"));
        $image_sizes[$size]['height'] = intval(get_option("{$size}_size_h"));
        $image_sizes[$size]['crop'] = get_option("{$size}_crop") ? get_option("{$size}_crop") : false;
    }

    if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
        $image_sizes = array_merge($image_sizes, $_wp_additional_image_sizes);
    }

    return $image_sizes;
}

if (!function_exists('ultimate_watermark_get_image_sizes')) {
    function ultimate_watermark_get_image_sizes($key_value = true)
    {

        $image_sizes = get_intermediate_image_sizes();

        $all_image_sizes = (ultimate_watermark_get_all_image_sizes());

        $image_sizes[] = 'full';

        sort($image_sizes, SORT_STRING);

        if ($key_value) {
            $new_image_sizes = array();
            foreach ($image_sizes as $size) {
                $size_array = isset($all_image_sizes[$size]) ? $all_image_sizes[$size] : array();
                $size_string = $size;
                if (isset($size_array['height']) && isset($size_array['width'])) {
                    $size_string .= ' <strong>[ ' . $size_array['width'] . ' x ' . $size_array['height'] . ' ]</strong>';
                }
                if ($size === "full") {
                    $size_string = "<strong>Full/Original Image</strong> [ <span style='color:red'>If you watermark this you will not be able to remove watermark unless you enable backup images on the <a target='_blank' href='" . admin_url('admin.php?page=ultimate-watermark&tab=image-watermark&section=image-protection') . "'><strong>image protection & backup</strong></a> tab.</span> ]";
                }
                $new_image_sizes[] = array(
                    'id' => $size,
                    'title' => $size_string
                );
            }
            return $new_image_sizes;
        }
        return $image_sizes;

    }
}

if (!function_exists('ultimate_watermark_get_post_types')) {
    function ultimate_watermark_get_post_types($key_value = true)
    {
        $post_types = array_merge(array('post', 'page'), get_post_types(array('_builtin' => false), 'names'));

        $new_post_types = array();
        foreach ($post_types as $post_type) {
            $new_post_types[] = array(
                'id' => $post_type,
                'title' => $post_type
            );
        }
        return $new_post_types;
    }
}
if (!function_exists('ultimate_watermark_is_premium')) {
    function ultimate_watermark_is_premium()
    {
        return apply_filters('ultimate_watermark_is_premium', false);
    }
}