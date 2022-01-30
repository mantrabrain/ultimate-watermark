<?php

namespace Ultimate_Watermark\Admin\Pages;
class Settings_Page
{
    public static $instance;

    public static function get_insatance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function __construct()
    {

    }

    public static function init()
    {
        self::get_insatance()->options_page_output();
    }

    public function options_page_output()
    {

        if (!current_user_can('manage_options'))
            return;

        echo '
		<div class="wrap">
			<h2>' . __('Ultimate Watermark', 'ultimate-watermark') . '</h2>';

        echo '
			<div class="ultimate-watermark-settings metabox-holder">
				<form action="options.php" method="post">
					<div id="main-sortables" class="meta-box-sortables ui-sortable">';
        settings_fields('ultimate_watermark_options');
        $this->do_settings_sections('ultimate_watermark_options');

        echo '
					<p class="submit">';
        submit_button('', 'primary', 'save_ultimate_watermark_options', false);

        echo ' ';

        submit_button(__('Reset to defaults', 'ultimate-watermark'), 'secondary', 'reset_ultimate_watermark_options', false);

        echo '
					</p>
					</div>
				</form>
			</div>
			<div class="clear"></div>
		</div>';
        ?>
        <script type="text/javascript">
            //<![CDATA[
            jQuery(document).ready(function ($) {
                // close postboxes that should be closed
                $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
                // postboxes setup
                postboxes.add_postbox_toggles('watermark-options');
            });
            //]]>
        </script>
        <?php
    }

    function do_settings_sections($page)
    {
        global $wp_settings_sections, $wp_settings_fields;

        if (!isset($wp_settings_sections[$page]))
            return;

        foreach ((array)$wp_settings_sections[$page] as $section) {
            echo '<div id="" class="postbox ' . $section['id'] . '">';
            echo '<button type="button" class="handlediv button-link" aria-expanded="true"><span class="screen-reader-text">' . __('Toggle panel', 'ultimate-watermark') . '</span><span class="toggle-indicator" aria-hidden="true"></span></button>';
            if ($section['title'])
                echo "<h3 class=\"hndle\"><span>{$section['title']}</span></h3>\n";

            if ($section['callback'])
                call_user_func($section['callback'], $section);

            if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']]))
                continue;
            echo '<div class="inside"><table class="form-table">';
            do_settings_fields($page, $section['id']);
            echo '</table></div>';
            echo '</div>';
        }
    }
}