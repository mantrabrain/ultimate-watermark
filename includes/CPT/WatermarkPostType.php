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

    public function get_value($meta_id, $watermark_id)
    {
        return get_post_meta($watermark_id, $meta_id, true);
    }

    public function columns($columns)
    {
        $columns['title'] = __('Watermark Title', 'yatra');
        $columns['watermark_status'] = __('Status', 'ultimate-watermark');
        $columns['watermark_type'] = __('Type', 'ultimate-watermark');
        $columns['watermark_for'] = __('For', 'ultimate-watermark');
        $columns['watermark_content'] = __('Watermark Content', 'ultimate-watermark');
        unset($columns['date']);
        $columns['date'] = __('Created Date', 'yatra');


        return $columns;
    }

    public function coupons_manage_columns($column_name, $watermark_id)
    {
        echo '<span class="ultimate-watermark-column-' . esc_attr($column_name) . '">';
        switch ($column_name) {
            case "watermark_status":
                ultimate_watermark_html(
                    [
                        'value' => $this->get_value('ultimate_watermark_enable_this_watermark', $watermark_id),
                        'name' => 'ultimate_watermark_enable_this_watermark',
                        'type' => 'switch',
                    ]
                );

                break;
            case "coupon_type":
                echo esc_html(ucwords($this->get_value('yatra_coupon_type', $watermark_id, 'percentage')));
                break;
            case "discount_value":
                echo esc_html($this->get_value('yatra_coupon_value', $watermark_id));
                break;
            case "usage_count":
                $usage_limit = ($this->get_value('yatra_coupon_using_limit', $watermark_id));
                $usage_count_array = ($this->get_value('yatra_coupon_usages_bookings', $watermark_id));
                $usage_count_array = is_array($usage_count_array) ? $usage_count_array : array();
                $usage_count = count($usage_count_array);
                printf(
                /* translators: 1: count 2: limit */
                    __('%1$s / %2$s', 'yatra'),
                    esc_html($usage_count),
                    $usage_limit ? esc_html($usage_limit) : '&infin;'
                );
                break;
            case "expire_date":
                echo esc_html($this->get_value('yatra_coupon_expiry_date', $watermark_id));
                break;
        }
        echo '</span>';
    }


    public function __construct()
    {
        add_filter('post_row_actions', array($this, 'remove'));
        add_filter('manage_edit-ultimate-watermark_columns', array($this, 'columns'));
        add_action('manage_ultimate-watermark_posts_custom_column', array($this, 'coupons_manage_columns'), 10, 2);


    }

}