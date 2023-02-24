<?php

namespace Ultimate_Watermark;

use Ultimate_Watermark\CPT\WatermarkPostType;

defined('ABSPATH') || exit;

/**
 * Post types Class.
 */
class PostTypes
{

    /**
     * Hook in methods.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_post_types'), 5);
        add_action('init', array(__CLASS__, 'register_post_status'), 9);
        add_action('ultimate_watermark_after_register_post_type', array(__CLASS__, 'maybe_flush_rewrite_rules'));
        add_action('ultimate_watermark_flush_rewrite_rules', array(__CLASS__, 'flush_rewrite_rules'));
        self::hooks();
    }


    /**
     * Register core post types.
     */
    public static function register_post_types()
    {
        if (!is_blog_installed() || post_type_exists('tour')) {
            return;
        }

        do_action('ultimate_watermark_register_post_type');

        WatermarkPostType::register();

        do_action('ultimate_watermark_after_register_post_type');
    }

    public static function register_post_status()
    {

        //WatermarkPostType::register_post_status();
    }

    public static function maybe_flush_rewrite_rules()
    {

        if ('yes' === get_option('ultimate_watermark_queue_flush_rewrite_rules')) {
            update_option('ultimate_watermark_queue_flush_rewrite_rules', 'no');
            self::flush_rewrite_rules();
        }
    }

    public static function flush_rewrite_rules()
    {
        flush_rewrite_rules();
    }

    private static function hooks()
    {


        new WatermarkPostType();


    }


}
