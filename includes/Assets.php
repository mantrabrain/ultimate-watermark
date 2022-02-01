<?php

namespace Ultimate_Watermark;

class Assets
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

    }
    
    public function wp_enqueue_scripts()
    {
        $right_click = true;

        if ((!ultimate_watermark_enable_protection_for_logged_in_users() && is_user_logged_in()) || (!ultimate_watermark_disable_drag_and_drop() && !ultimate_watermark_disable_rightclick()))
            $right_click = false;

        if (apply_filters('ulwm_block_right_click', (bool)$right_click) === true) {
            wp_enqueue_script('ulwm-no-right-click', ULTIMATE_WATERMARK_URI . '/assets/js/no-right-click.js', array(), ULTIMATE_WATERMARK_VERSION);

            wp_localize_script(
                'ulwm-no-right-click', 'ulwmNRCargs', array(
                    'rightclick' => (ultimate_watermark_disable_rightclick() ? 'Y' : 'N'),
                    'draganddrop' => (ultimate_watermark_disable_drag_and_drop() ? 'Y' : 'N')
                )
            );
        }
    }
}