<?php
if (!function_exists('ultimate_watermark_load_admin_template')) {

    function ultimate_watermark_load_admin_template($template = null, $variables = array(), $include_once = false)
    {
        $variables = (array)$variables;

        $variables = apply_filters('ultimate_watermark_load_admin_template_variables', $variables);

        extract($variables);

        $isLoad = apply_filters('should_ultimate_watermark_load_admin_template', true, $template, $variables);

        if (!$isLoad) {

            return;
        }

        do_action('ultimate_watermark_load_admin_template_before', $template, $variables);

        if ($include_once) {

            include_once ultimate_watermark_get_admin_template($template);

        } else {

            include ultimate_watermark_get_admin_template($template);
        }
        do_action('ultimate_watermark_load_admin_template_after', $template, $variables);
    }
}
if (!function_exists('ultimate_watermark_get_admin_template')) {

    function ultimate_watermark_get_admin_template($template = null)
    {
        if (!$template) {
            return false;
        }
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);

        $template_location = ULTIMATE_WATERMARK_ABSPATH . "includes/Admin/Templates/{$template}.php";

        if (!file_exists($template_location)) {

            echo '<div class="ultimate_watermark-notice-warning"> ' . __(sprintf('The file you are trying to load is not exists in your theme or ultimate_watermark plugins location, if you are a developer and extending ultimate_watermark plugin, please create a php file at location %s ', "<code>{$template_location}</code>"), 'ultimate-watermark') . ' </div>';
        }


        return apply_filters('ultimate_watermark_get_admin_template_path', $template_location, $template);
    }
}
