<?php

namespace MantraBrain\UltimateWatermark\PostTypes;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;

/**
 * Watermark Post Type Class
 * 
 * Handles the custom post type for watermarks
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkPostType
{
    use SingletonTrait;

    /**
     * Post type name
     */
    const POST_TYPE = 'ultimate_watermark';

    /**
     * Initialize the post type
     */
    public function init()
    {
        add_action('init', [$this, 'registerPostType']);
    }

    /**
     * Register the custom post type
     */
    public function registerPostType()
    {
        $labels = [
            'name'                  => _x('Watermarks', 'Post type general name', 'ultimate-watermark'),
            'singular_name'         => _x('Watermark', 'Post type singular name', 'ultimate-watermark'),
            'menu_name'             => _x('Watermarks', 'Admin Menu text', 'ultimate-watermark'),
            'name_admin_bar'        => _x('Watermark', 'Add New on Toolbar', 'ultimate-watermark'),
            'add_new'               => __('Add New', 'ultimate-watermark'),
            'add_new_item'          => __('Add New Watermark', 'ultimate-watermark'),
            'new_item'              => __('New Watermark', 'ultimate-watermark'),
            'edit_item'             => __('Edit Watermark', 'ultimate-watermark'),
            'view_item'             => __('View Watermark', 'ultimate-watermark'),
            'all_items'             => __('All Watermarks', 'ultimate-watermark'),
            'search_items'          => __('Search Watermarks', 'ultimate-watermark'),
            'parent_item_colon'     => __('Parent Watermarks:', 'ultimate-watermark'),
            'not_found'             => __('No watermarks found.', 'ultimate-watermark'),
            'not_found_in_trash'    => __('No watermarks found in Trash.', 'ultimate-watermark'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => ['title', 'editor'],
            'show_in_rest'       => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }
}
