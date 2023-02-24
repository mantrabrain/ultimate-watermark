<?php

namespace Ultimate_Watermark\CPT;

class WatermarkPostType
{
    private static $slug = 'ultimate-watermark';

    public function remove($actions)
    {
        if (get_post_type() === self::$slug) {
            unset($actions['view']);
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
    }

    public static function register()
    {
        $labels = array(
            'name' => _x('Watermarks', 'post type general name', 'yatra'),
            'singular_name' => _x('Watermark', 'post type singular name', 'yatra'),
            'menu_name' => _x('Watermarks', 'admin menu', 'yatra'),
            'name_admin_bar' => _x('Watermark', 'add new on admin bar', 'yatra'),
            'add_new' => _x('Add New', 'yatra', 'yatra'),
            'add_new_item' => __('Add New Watermark', 'yatra'),
            'new_item' => __('New Watermark', 'yatra'),
            'edit_item' => __('View Watermark', 'yatra'),
            'view_item' => __('View Watermark', 'yatra'),
            'all_items' => __('Watermarks', 'yatra'),
            'search_items' => __('Search Watermarks', 'yatra'),
            'parent_item_colon' => __('Parent Watermarks:', 'yatra'),
            'not_found' => __('No Watermarks found.', 'yatra'),
            'not_found_in_trash' => __('No Watermarks found in Trash.', 'yatra'),
        );

        $args = array(
            'labels' => $labels,
            'description' => __('Description.', 'yatra'),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,// YATRA_ADMIN_MENU_SLUG,
            'query_var' => true,
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title'),
            'menu_icon' => 'dashicons-location',
            'with_front' => true,
         );
        register_post_type(self::$slug, $args);

    }


    public function __construct()
    {
        add_filter('post_row_actions', array($this, 'remove'));
    }

}