<?php

namespace Ultimate_Watermark;

class Assets
{
    public function __construct()
    {
        add_action('wp_enqueue_media', array($this, 'wp_enqueue_media'));
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function wp_enqueue_media($page)
    {
        wp_enqueue_style('watermark-style', ULTIMATE_WATERMARK_URL . '/assets/css/ultimate-watermark.css', array(), ULTIMATE_WATERMARK_VERSION);
    }

    public function wp_enqueue_scripts()
    {
        $right_click = true;

        if ((ultimate_watermark()->options['image_protection']['forlogged'] == 0 && is_user_logged_in()) || (ultimate_watermark()->options['image_protection']['draganddrop'] == 0 && ultimate_watermark()->options['image_protection']['rightclick'] == 0))
            $right_click = false;

        if (apply_filters('ulwm_block_right_click', (bool)$right_click) === true) {
            wp_enqueue_script('ulwm-no-right-click', plugins_url('js/no-right-click.js', __FILE__), array(), ULTIMATE_WATERMARK_VERSION);

            wp_localize_script(
                'ulwm-no-right-click', 'ulwmNRCargs', array(
                    'rightclick' => (ultimate_watermark()->options['image_protection']['rightclick'] == 1 ? 'Y' : 'N'),
                    'draganddrop' => (ultimate_watermark()->options['image_protection']['draganddrop'] == 1 ? 'Y' : 'N')
                )
            );
        }
    }
}