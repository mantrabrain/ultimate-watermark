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

if (!function_exists('ultimate_watermark_get_image_sizes')) {
    function ultimate_watermark_get_image_sizes($key_value = true)
    {

        $image_sizes = get_intermediate_image_sizes();

        $image_sizes[] = 'full';

        sort($image_sizes, SORT_STRING);

        if ($key_value) {
            $new_image_sizes = array();
            foreach ($image_sizes as $size) {
                $new_image_sizes[] = array(
                    'id' => $size,
                    'title' => $size
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