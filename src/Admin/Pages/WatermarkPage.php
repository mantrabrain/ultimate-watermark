<?php

namespace MantraBrain\UltimateWatermark\Admin\Pages;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\Admin\Components\Layout;
use MantraBrain\UltimateWatermark\Admin\Components\ConfirmationModal;
use MantraBrain\UltimateWatermark\PostTypes\WatermarkPostType;
use MantraBrain\UltimateWatermark\Utils\WatermarkUsageTracker;

/**
 * Watermark Page Class
 * 
 * Handles the watermark management page with list and creation form
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkPage
{
    use SingletonTrait;

    /**
     * Render watermark page
     */
    public function render(): void
    {
        $actions = '<a href="' . esc_url(admin_url('admin.php?page=ultimate-watermark-add-watermark')) . '" class="btn btn-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            ' . esc_html__('Add New Watermark', 'ultimate-watermark') . '
        </a>';

        Layout::render(
            __('Watermark Management', 'ultimate-watermark'),
            [$this, 'renderWatermarkContent'],
            [
                'subtitle' => __('Create and manage your watermark templates', 'ultimate-watermark'),
                'actions' => $actions
            ]
        );
    }

    /**
     * Render watermark content
     */
    public function renderWatermarkContent(): void
    {
        ?>
        <div class="ultimate-watermark-saas-watermarks">
            <!-- Watermarks Table -->
            <div class="watermarks-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <h2><?php esc_html_e('Watermark Templates', 'ultimate-watermark'); ?></h2>
                        <span class="table-count"><?php echo esc_html(count($this->getWatermarks())); ?> <?php esc_html_e('templates', 'ultimate-watermark'); ?></span>
                    </div>
                    <div class="table-actions">
                        <div class="search-box">
                            <input type="text" id="watermark-search" placeholder="<?php esc_attr_e('Search watermarks...', 'ultimate-watermark'); ?>">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-add-watermark')); ?>" class="btn btn-primary">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    <?php esc_html_e('Add New Watermark', 'ultimate-watermark'); ?>
                                </a>
                    </div>
                </div>

                <!-- WordPress-style Bulk Actions -->
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'ultimate-watermark'); ?></label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php esc_html_e('Bulk actions', 'ultimate-watermark'); ?></option>
                            <option value="activate"><?php esc_html_e('Activate', 'ultimate-watermark'); ?></option>
                            <option value="deactivate"><?php esc_html_e('Deactivate', 'ultimate-watermark'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'ultimate-watermark'); ?></option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'ultimate-watermark'); ?>">
                    </div>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(count($this->getWatermarks())); ?> <?php esc_html_e('items', 'ultimate-watermark'); ?></span>
                    </div>
                </div>

                <div class="watermarks-table-container">
                    <div class="table-wrapper">
                    <table class="watermarks-table">
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <input type="checkbox" id="select-all-watermarks">
                                </th>
                                <th class="name-column"><?php esc_html_e('Name', 'ultimate-watermark'); ?></th>
                                <th class="type-preview-column"><?php esc_html_e('Type & Preview', 'ultimate-watermark'); ?></th>
                                <th class="behavior-column"><?php esc_html_e('Behavior', 'ultimate-watermark'); ?></th>
                                <th class="rules-column"><?php esc_html_e('Rules', 'ultimate-watermark'); ?></th>
                                <th class="position-column"><?php esc_html_e('Position', 'ultimate-watermark'); ?></th>
                                <th class="opacity-column"><?php esc_html_e('Opacity', 'ultimate-watermark'); ?></th>
                                <th class="usage-column"><?php esc_html_e('Usage', 'ultimate-watermark'); ?></th>
                                <th class="status-column"><?php esc_html_e('Status', 'ultimate-watermark'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $this->renderWatermarkTableRows(); ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Bottom Bulk Actions -->
                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'ultimate-watermark'); ?></label>
                        <select name="action2" id="bulk-action-selector-bottom">
                            <option value="-1"><?php esc_html_e('Bulk actions', 'ultimate-watermark'); ?></option>
                            <option value="activate"><?php esc_html_e('Activate', 'ultimate-watermark'); ?></option>
                            <option value="deactivate"><?php esc_html_e('Deactivate', 'ultimate-watermark'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'ultimate-watermark'); ?></option>
                        </select>
                        <input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e('Apply', 'ultimate-watermark'); ?>">
                    </div>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(count($this->getWatermarks())); ?> <?php esc_html_e('items', 'ultimate-watermark'); ?></span>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulk-actions" style="display: none;">
                    <div class="bulk-actions-content">
                        <span class="bulk-selected-count">0 <?php esc_html_e('selected', 'ultimate-watermark'); ?></span>
                        <div class="bulk-actions-buttons">
                            <button type="button" class="btn btn-secondary btn-sm" id="bulk-activate">
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e('Activate', 'ultimate-watermark'); ?>
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="bulk-deactivate">
                                <span class="dashicons dashicons-no-alt"></span>
                                <?php esc_html_e('Deactivate', 'ultimate-watermark'); ?>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" id="bulk-delete">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e('Delete', 'ultimate-watermark'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <div class="empty-state-content">
                    <div class="empty-state-icon">
                        <span class="dashicons dashicons-format-image"></span>
                    </div>
                    <h3><?php esc_html_e('No watermarks found', 'ultimate-watermark'); ?></h3>
                    <p><?php esc_html_e('Create your first watermark template to get started protecting your images.', 'ultimate-watermark'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-add-watermark')); ?>" class="btn btn-primary">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php esc_html_e('Create Your First Watermark', 'ultimate-watermark'); ?>
                            </a>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <?php ConfirmationModal::render(
            'delete-confirmation-modal',
            __('Delete Watermark', 'ultimate-watermark'),
            __('Are you sure you want to delete this watermark? This action cannot be undone.', 'ultimate-watermark'),
            __('Delete', 'ultimate-watermark'),
            __('Cancel', 'ultimate-watermark'),
            'btn-danger'
        ); ?>

        <?php ConfirmationModal::render(
            'bulk-delete-confirmation-modal',
            __('Delete Watermarks', 'ultimate-watermark'),
            __('Are you sure you want to delete the selected watermarks? This action cannot be undone.', 'ultimate-watermark'),
            __('Delete All', 'ultimate-watermark'),
            __('Cancel', 'ultimate-watermark'),
            'btn-danger'
        ); ?>
        <?php
    }

    /**
     * Render watermark table rows
     */
    private function renderWatermarkTableRows(): void
    {
        $watermarks = $this->getWatermarks();
        
        if (empty($watermarks)) {
            ?>
            <tr class="no-watermarks">
                <td colspan="9" class="empty-cell">
                    <div class="empty-state-inline">
                        <span class="dashicons dashicons-format-image"></span>
                        <span><?php esc_html_e('No watermarks found', 'ultimate-watermark'); ?></span>
                    </div>
                </td>
            </tr>
            <?php
            return;
        }

        foreach ($watermarks as $watermark) {
            $this->renderWatermarkTableRow($watermark);
        }
    }

    /**
     * Render single watermark table row
     */
    private function renderWatermarkTableRow(array $watermark): void
    {
        $status_class = $watermark['active'] ? 'status-active' : 'status-inactive';
        $status_text = $watermark['active'] ? __('Active', 'ultimate-watermark') : __('Inactive', 'ultimate-watermark');
        $type_icon = $watermark['type'] === 'text' ? 'dashicons-format-text' : 'dashicons-format-image';
        $position_text = ucfirst(str_replace('-', ' ', $watermark['position']));
        
        ?>
        <tr class="watermark-row" data-id="<?php echo esc_attr($watermark['id']); ?>">
            <td class="checkbox-column">
                <input type="checkbox" class="watermark-checkbox" value="<?php echo esc_attr($watermark['id']); ?>">
            </td>
            <td class="name-column">
                <div class="watermark-name">
                    <strong>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-add-watermark&ID=' . $watermark['id'])); ?>" class="watermark-name-link">
                            <?php echo esc_html($watermark['name']); ?>
                        </a>
                    </strong>
                    <span class="watermark-description"><?php echo esc_html($watermark['description'] ?? ''); ?></span>
                </div>
                <div class="row-actions">
                    <span class="id">
                        <strong>ID: <?php echo esc_html($watermark['id']); ?></strong>
                    </span>
                    <span class="separator">|</span>
                    <span class="edit">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-add-watermark&ID=' . $watermark['id'])); ?>" class="watermark-edit" data-id="<?php echo esc_attr($watermark['id']); ?>"><?php esc_html_e('Edit', 'ultimate-watermark'); ?></a>
                    </span>
                    <span class="separator">|</span>
                    <span class="duplicate">
                        <a href="#" class="watermark-duplicate" data-id="<?php echo esc_attr($watermark['id']); ?>"><?php esc_html_e('Duplicate', 'ultimate-watermark'); ?></a>
                    </span>
                    <span class="separator">|</span>
                    <span class="toggle">
                        <a href="#" class="watermark-toggle" data-id="<?php echo esc_attr($watermark['id']); ?>" data-status="<?php echo esc_attr($watermark['active'] ? 'active' : 'inactive'); ?>">
                            <?php echo $watermark['active'] ? esc_html__('Deactivate', 'ultimate-watermark') : esc_html__('Activate', 'ultimate-watermark'); ?>
                        </a>
                    </span>
                    <span class="separator">|</span>
                    <span class="delete">
                        <a href="#" class="watermark-delete" data-id="<?php echo esc_attr($watermark['id']); ?>" style="color: #a00;"><?php esc_html_e('Delete', 'ultimate-watermark'); ?></a>
                    </span>
                </div>
            </td>
            <td class="type-preview-column">
                <div class="watermark-type-preview">
                    <div class="watermark-type">
                        <span class="dashicons <?php echo esc_attr($type_icon); ?>"></span>
                        <span><?php echo esc_html(ucfirst($watermark['type'])); ?></span>
                    </div>
                    <div class="watermark-preview">
                        <?php if ($watermark['type'] === 'text'): ?>
                            <div class="text-watermark-preview">
                                <span class="watermark-text" style="
                                    color: <?php echo esc_attr($watermark['watermark_color']); ?>;
                                    font-size: <?php echo esc_attr($watermark['watermark_font_size']); ?>px;
                                    font-family: <?php echo esc_attr($watermark['watermark_font_family']); ?>;
                                    font-weight: <?php echo esc_attr($watermark['watermark_font_weight']); ?>;
                                    font-style: <?php echo esc_attr($watermark['watermark_font_style']); ?>;
                                    text-decoration: <?php echo esc_attr($watermark['watermark_text_decoration']); ?>;
                                    display: inline-block;
                                    max-width: 100%;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                ">
                                    <?php echo esc_html($watermark['watermark_text']); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="image-watermark-preview">
                                <?php 
                                if ($watermark['watermark_image_id'] && !empty($watermark['image_url'])) {
                                    echo '<img src="' . esc_url($watermark['image_url']) . '" alt="' . esc_attr($watermark['name']) . '" class="watermark-image-preview">';
                                } else {
                                    echo '<div class="no-image">' . esc_html__('No image set', 'ultimate-watermark') . '</div>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td class="behavior-column">
                <div class="watermark-behaviors">
                    <?php if ($watermark['automatic_watermarking'] === '1'): ?>
                        <span class="behavior-icon automatic" title="<?php esc_attr_e('Automatic watermarking', 'ultimate-watermark'); ?>">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($watermark['manual_watermarking'] === '1'): ?>
                        <span class="behavior-icon manual" title="<?php esc_attr_e('Manual watermarking', 'ultimate-watermark'); ?>">
                            <span class="dashicons dashicons-admin-tools"></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($watermark['frontend_watermarking'] === '1'): ?>
                        <span class="behavior-icon frontend" title="<?php esc_attr_e('Frontend watermarking', 'ultimate-watermark'); ?>">
                            <span class="dashicons dashicons-upload"></span>
                        </span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="rules-column">
                <div class="watermark-rules">
                    <?php echo $this->formatWatermarkRules($watermark); ?>
                </div>
            </td>
            <td class="position-column">
                <span class="position-badge"><?php echo esc_html($position_text); ?></span>
            </td>
            <td class="opacity-column">
                <div class="opacity-indicator">
                    <div class="opacity-bar">
                        <div class="opacity-fill" style="width: <?php echo esc_attr($watermark['opacity']); ?>%"></div>
                    </div>
                    <span class="opacity-value"><?php echo esc_html($watermark['opacity']); ?>%</span>
                </div>
            </td>
            <td class="usage-column">
                <div class="usage-stats">
                    <div class="usage-count-display">
                        <span class="usage-count"><?php echo esc_html($watermark['usage_count']); ?></span>
                        <span class="usage-label"><?php esc_html_e('times used', 'ultimate-watermark'); ?></span>
                    </div>
                    <?php if ($watermark['usage_count'] > 0): ?>
                        <div class="usage-details">
                            <span class="unique-images"><?php echo esc_html($watermark['unique_images']); ?> <?php esc_html_e('images', 'ultimate-watermark'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </td>
            <td class="status-column">
                <span class="status-badge <?php echo esc_attr($status_class); ?>">
                    <span class="status-dot"></span>
                    <?php echo esc_html($status_text); ?>
                </span>
            </td>
        </tr>
        <?php
    }

    /**
     * Get watermarks from database
     *
     * @return array
     */
    private function getWatermarks(): array
    {
        // Check if we already have cached watermarks for this request
        static $cached_watermarks = null;
        if ($cached_watermarks !== null) {
            return $cached_watermarks;
        }
        
        global $wpdb;
        
        // Get all watermark IDs first
        $watermark_ids = $wpdb->get_col($wpdb->prepare("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = %s AND post_status = 'publish' 
            ORDER BY post_date DESC
        ", WatermarkPostType::POST_TYPE));
        
        if (empty($watermark_ids)) {
            return [];
        }
        
        // Create placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($watermark_ids), '%d'));
        
        // Get all meta data in one query
        $meta_data = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id IN ($placeholders)
            AND meta_key IN (
                'watermark_type', 'watermark_position', 'watermark_opacity', 'watermark_image_id',
                'automatic_watermarking', 'manual_watermarking', 'frontend_watermarking',
                'watermark_on', 'watermark_post_types', 'watermark_sizes', 'watermark_rules',
                'watermark_text', 'watermark_color', 'watermark_font_size', 'watermark_font_family',
                'watermark_font_weight', 'watermark_font_style', 'watermark_text_decoration',
                'active', 'watermark_usage_count', 'watermark_used_images'
            )
        ", $watermark_ids));
        
        // Get usage data in one query
        $usage_data = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id IN ($placeholders)
            AND meta_key = 'watermark_usage_count'
        ", $watermark_ids));
        
        // Get used images data in one query
        $used_images_data = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id IN ($placeholders)
            AND meta_key = 'watermark_used_images'
        ", $watermark_ids));
        
        // Organize meta data by post ID
        $meta_by_post = [];
        foreach ($meta_data as $meta) {
            $meta_by_post[$meta->post_id][$meta->meta_key] = $meta->meta_value;
        }
        
        // Organize usage data by post ID
        $usage_by_post = [];
        foreach ($usage_data as $usage) {
            $usage_by_post[$usage->post_id] = intval($usage->meta_value);
        }
        
        // Organize used images data by post ID
        $used_images_by_post = [];
        foreach ($used_images_data as $used_images) {
            $images = maybe_unserialize($used_images->meta_value);
            $used_images_by_post[$used_images->post_id] = is_array($images) ? $images : [];
        }
        
        // Get posts data
        $posts = get_posts([
            'post_type' => WatermarkPostType::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post__in' => $watermark_ids
        ]);
        
        $watermarks = [];
        foreach ($posts as $post) {
            $post_meta = $meta_by_post[$post->ID] ?? [];
            
            // Extract meta values with defaults
            $watermark_type = $post_meta['watermark_type'] ?? 'text';
            $watermark_position = $post_meta['watermark_position'] ?? 'bottom-right';
            $watermark_opacity = $post_meta['watermark_opacity'] ?? 50;
            $watermark_image_id = isset($post_meta['watermark_image_id']) ? intval($post_meta['watermark_image_id']) : 0;
            
            // Get behavior settings
            $automatic_watermarking = $post_meta['automatic_watermarking'] ?? '0';
            $manual_watermarking = $post_meta['manual_watermarking'] ?? '0';
            $frontend_watermarking = $post_meta['frontend_watermarking'] ?? '0';
            
            // Get rules settings
            $watermark_on = $post_meta['watermark_on'] ?? 'everywhere';
            $watermark_post_types = $post_meta['watermark_post_types'] ?? [];
            $watermark_sizes = $post_meta['watermark_sizes'] ?? [];
            $watermark_rules = $post_meta['watermark_rules'] ?? [];
            
            // Get text watermark settings for preview
            $watermark_text = $post_meta['watermark_text'] ?? 'Watermark';
            $watermark_color = $post_meta['watermark_color'] ?? '#000000';
            $watermark_font_size = $post_meta['watermark_font_size'] ?? 24;
            $watermark_font_family = $post_meta['watermark_font_family'] ?? 'Arial';
            $watermark_font_weight = $post_meta['watermark_font_weight'] ?? 'normal';
            $watermark_font_style = $post_meta['watermark_font_style'] ?? 'normal';
            $watermark_text_decoration = $post_meta['watermark_text_decoration'] ?? 'none';
            
            // Ensure arrays are properly formatted (batch query returns raw serialized strings)
            if (is_string($watermark_post_types)) {
                $watermark_post_types = maybe_unserialize($watermark_post_types) ?: [];
            }
            if (is_string($watermark_rules)) {
                $watermark_rules = maybe_unserialize($watermark_rules) ?: [];
            }
            if (is_string($watermark_sizes)) {
                $watermark_sizes = maybe_unserialize($watermark_sizes) ?: [];
            }
            
            // Get preview URL (simplified - no database calls)
            $preview_url = $this->getWatermarkPreviewUrl($post->ID, $watermark_type, $watermark_image_id);
            
            // Pre-generate image URL for image watermarks to avoid additional queries
            $image_url = '';
            if ($watermark_type === 'image' && $watermark_image_id > 0) {
                $image_url = wp_get_attachment_image_url($watermark_image_id, 'thumbnail');
            }
            
            // Get usage count from pre-fetched data
            $usage_count = $usage_by_post[$post->ID] ?? 0;
            
            // Get unique images count from pre-fetched data
            $unique_images = 0;
            if ($usage_count > 0) {
                $used_images = $used_images_by_post[$post->ID] ?? [];
                $unique_images = count($used_images);
            }
            
            // Check if watermark is active
            $active_meta = $post_meta['active'] ?? false;
            $active = ($active_meta === '1' || $active_meta === 'true' || $active_meta === true);
            
            
            $watermarks[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'description' => $post->post_content,
                'type' => $watermark_type,
                'position' => $watermark_position,
                'opacity' => $watermark_opacity,
                'active' => $active,
                'usage_count' => $usage_count,
                'unique_images' => $unique_images,
                'preview_url' => $preview_url,
                'created_at' => $post->post_date,
                'automatic_watermarking' => $automatic_watermarking,
                'manual_watermarking' => $manual_watermarking,
                'frontend_watermarking' => $frontend_watermarking,
                'watermark_on' => $watermark_on,
                'watermark_post_types' => $watermark_post_types,
                'watermark_sizes' => $watermark_sizes,
                'watermark_rules' => $watermark_rules,
                // Preview data
                'watermark_text' => $watermark_text,
                'watermark_color' => $watermark_color,
                'watermark_font_size' => $watermark_font_size,
                'watermark_font_family' => $watermark_font_family,
                'watermark_font_weight' => $watermark_font_weight,
                'watermark_font_style' => $watermark_font_style,
                'watermark_text_decoration' => $watermark_text_decoration,
                'watermark_image_id' => $watermark_image_id,
                'image_url' => $image_url
            ];
        }
        
        // Cache the results for this request
        $cached_watermarks = $watermarks;
        
        return $watermarks;
    }

    /**
     * Clear watermarks cache
     * Call this when watermarks are modified
     */
    public static function clearWatermarksCache(): void
    {
        // The static cache will be automatically cleared on the next page load
        // For immediate clearing, we could use WordPress transients, but static cache is sufficient for this use case
    }

    /**
     * Get only active watermarks for watermarking operations
     */
    public static function getActiveWatermarks(): array
    {
        // Use the cached watermarks and filter for active ones
        $instance = self::getInstance();
        $all_watermarks = $instance->getWatermarks();
        
        // Filter for active watermarks only
        $active_watermarks = [];
        foreach ($all_watermarks as $watermark) {
            if ($watermark['active']) {
                $active_watermarks[] = [
                    'id' => $watermark['id'],
                    'name' => $watermark['name'],
                    'type' => $watermark['type'],
                    'position' => $watermark['position'],
                    'opacity' => $watermark['opacity']
                ];
            }
        }
        
        return $active_watermarks;
    }

    /**
     * Format watermark rules for display
     */
    private function formatWatermarkRules(array $watermark): string
    {
        $watermark_rules = $watermark['watermark_rules'] ?? [];
        
        // If no unified rules, fall back to legacy display
        if (empty($watermark_rules) || !is_array($watermark_rules)) {
            return $this->formatLegacyRules($watermark);
        }
        
        // Check if any rule has conditions
        $has_conditions = false;
        foreach ($watermark_rules as $rule) {
            if (!empty($rule['conditions']) && is_array($rule['conditions'])) {
                $has_conditions = true;
                break;
            }
        }
        
        // If no conditions defined, show as "No restrictions"
        if (!$has_conditions) {
            return '<span class="rule-item no-restrictions">' . __('No restrictions', 'ultimate-watermark') . '</span>';
        }
        
        $rule_summaries = [];
        foreach ($watermark_rules as $rule) {
            if (empty($rule['conditions']) || !is_array($rule['conditions'])) {
                continue;
            }
            
            $condition_parts = [];
            foreach ($rule['conditions'] as $condition) {
                $type = $condition['type'] ?? '';
                $operator = $condition['operator'] ?? '';
                $value = $condition['value'] ?? '';
                
                if (empty($type)) continue;
                
                // Get readable labels
                $type_label = $this->getConditionTypeLabel($type);
                $operator_label = $this->getOperatorLabel($operator);
                $value_label = $this->getConditionValueLabel($type, $value);
                
                $condition_parts[] = sprintf('%s %s <strong>%s</strong>', $type_label, $operator_label, $value_label);
            }
            
            if (!empty($condition_parts)) {
                $rule_name = !empty($rule['name']) ? esc_html($rule['name']) : __('Rule', 'ultimate-watermark');
                $logic = strtoupper($rule['logic_operator'] ?? 'AND');
                $conditions_text = implode(' <span class="logic-operator">' . $logic . '</span> ', $condition_parts);
                
                $rule_summaries[] = sprintf(
                    '<div class="rule-summary">' .
                    '<div class="rule-name">%s</div>' .
                    '<div class="rule-conditions">%s</div>' .
                    '</div>',
                    $rule_name,
                    $conditions_text
                );
            }
        }
        
        if (empty($rule_summaries)) {
            return '<span class="rule-item no-restrictions">' . __('No restrictions', 'ultimate-watermark') . '</span>';
        }
        
        return implode('', $rule_summaries);
    }
    
    /**
     * Format legacy rules (fallback for watermarks without unified rules)
     */
    private function formatLegacyRules(array $watermark): string
    {
        $rules = [];
        
        // Where to apply
        if ($watermark['watermark_on'] === 'everywhere') {
            $rules[] = '<span class="rule-item everywhere">' . __('Everywhere', 'ultimate-watermark') . '</span>';
        } else {
            $post_types = $watermark['watermark_post_types'];
            if (!empty($post_types)) {
                // Ensure it's an array
                if (is_string($post_types)) {
                    $post_types = maybe_unserialize($post_types);
                }
                if (is_array($post_types) && !empty($post_types)) {
                    $post_type_labels = [];
                    foreach ($post_types as $post_type) {
                        $post_type_obj = get_post_type_object($post_type);
                        $post_type_labels[] = $post_type_obj ? $post_type_obj->label : ucfirst($post_type);
                    }
                    $rules[] = '<span class="rule-item post-types">' . implode(', ', $post_type_labels) . '</span>';
                }
            }
        }
        
        // Image sizes
        $sizes = $watermark['watermark_sizes'];
        if (!empty($sizes)) {
            // Ensure it's an array
            if (is_string($sizes)) {
                $sizes = maybe_unserialize($sizes);
            }
            if (is_array($sizes) && !empty($sizes)) {
                $size_labels = [];
                foreach ($sizes as $size) {
                    $size_labels[] = ucfirst(str_replace('-', ' ', $size));
                }
                $rules[] = '<span class="rule-item sizes">' . implode(', ', $size_labels) . '</span>';
            } else {
                $rules[] = '<span class="rule-item sizes">' . __('All sizes', 'ultimate-watermark') . '</span>';
            }
        } else {
            $rules[] = '<span class="rule-item sizes">' . __('All sizes', 'ultimate-watermark') . '</span>';
        }
        
        return implode('<br>', $rules);
    }
    
    /**
     * Get condition type label
     */
    private function getConditionTypeLabel(string $type): string
    {
        $labels = [
            'image_size' => __('Image Size', 'ultimate-watermark'),
            'post_type' => __('Post Type', 'ultimate-watermark'),
            // Pro-only condition types
            'file_type' => __('File Type', 'ultimate-watermark'),
            'file_size' => __('File Size', 'ultimate-watermark'),
            'image_width' => __('Image Width', 'ultimate-watermark'),
            'image_height' => __('Image Height', 'ultimate-watermark'),
            'user_role' => __('User Role', 'ultimate-watermark'),
            'post_category' => __('Post Category', 'ultimate-watermark'),
            'product_cat' => __('Product Category', 'ultimate-watermark'),
            'product_tag' => __('Product Tag', 'ultimate-watermark'),
            'image_orientation' => __('Image Orientation', 'ultimate-watermark'),
            'date_range' => __('Upload Date', 'ultimate-watermark'),
            'image_aspect_ratio' => __('Aspect Ratio', 'ultimate-watermark'),
        ];
        
        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
    
    /**
     * Get operator label
     */
    private function getOperatorLabel(string $operator): string
    {
        $labels = [
            'is' => __('is', 'ultimate-watermark'),
            'is_not' => __('is not', 'ultimate-watermark'),
            'greater_than' => __('>', 'ultimate-watermark'),
            'less_than' => __('<', 'ultimate-watermark'),
            'equals' => __('=', 'ultimate-watermark'),
            'after' => __('after', 'ultimate-watermark'),
            'before' => __('before', 'ultimate-watermark'),
        ];
        
        return $labels[$operator] ?? $operator;
    }
    
    /**
     * Get condition value label
     */
    private function getConditionValueLabel(string $type, string $value): string
    {
        // Handle special cases for select options
        switch ($type) {
            case 'image_size':
                return ucfirst(str_replace('-', ' ', $value));
                
            case 'post_type':
                $post_type_obj = get_post_type_object($value);
                return $post_type_obj ? $post_type_obj->label : ucfirst($value);
                
            // Pro-only condition types
            case 'file_type':
                return strtoupper($value);
                
            case 'file_size':
                return sprintf('%s KB', number_format($value));
                
            case 'image_width':
            case 'image_height':
                return sprintf('%s px', number_format($value));
                
            case 'user_role':
                $role_names = wp_roles()->get_names();
                return $role_names[$value] ?? ucfirst($value);
                
            case 'post_category':
                $category = get_term_by('slug', $value, 'category');
                return $category ? $category->name : ucfirst($value);
                
            case 'product_cat':
                $term = get_term_by('slug', $value, 'product_cat');
                return $term ? $term->name : ucfirst($value);
                
            case 'product_tag':
                $term = get_term_by('slug', $value, 'product_tag');
                return $term ? $term->name : ucfirst($value);
                
            case 'image_orientation':
                return ucfirst($value);
                
            case 'date_range':
                return date('M j, Y', strtotime($value));
                
            case 'image_aspect_ratio':
                return number_format($value, 2);
                
            default:
                return esc_html($value);
        }
    }

    /**
     * Get watermark preview URL
     *
     * @param int $watermark_id
     * @param string $watermark_type
     * @param int $watermark_image_id
     * @return string
     */
    private function getWatermarkPreviewUrl(int $watermark_id, string $watermark_type, int $watermark_image_id = 0): string
    {
        // Check if we have a cached preview
        $cached_preview = get_post_meta($watermark_id, 'preview_url', true);
        if ($cached_preview && file_exists(str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $cached_preview))) {
            return $cached_preview;
        }

        // Generate preview based on type
        if ($watermark_type === 'image' && $watermark_image_id > 0) {
            $image_url = wp_get_attachment_url($watermark_image_id);
            if ($image_url) {
                return $image_url;
            }
        }

        // For text watermarks or when no image is available, generate a preview
        return $this->generateWatermarkPreview($watermark_id);
    }

    /**
     * Generate watermark preview image
     *
     * @param int $watermark_id
     * @return string
     */
    private function generateWatermarkPreview(int $watermark_id): string
    {
        // Get watermark data
        $watermark_type = get_post_meta($watermark_id, 'watermark_type', true) ?: 'text';
        $watermark_text = get_post_meta($watermark_id, 'watermark_text', true) ?: 'Watermark';
        $watermark_color = get_post_meta($watermark_id, 'watermark_color', true) ?: '#000000';
        $watermark_font_size = get_post_meta($watermark_id, 'watermark_font_size', true) ?: 24;
        $watermark_position = get_post_meta($watermark_id, 'watermark_position', true) ?: 'bottom-right';
        $watermark_opacity = get_post_meta($watermark_id, 'watermark_opacity', true) ?: 50;
        $watermark_rotation = get_post_meta($watermark_id, 'watermark_rotation', true) ?: 0;
        $watermark_image_id = get_post_meta($watermark_id, 'watermark_image_id', true);
        $watermark_image_id = $watermark_image_id ? intval($watermark_image_id) : 0;

        // Create preview directory
        $upload_dir = wp_upload_dir();
        $preview_dir = $upload_dir['basedir'] . '/ultimate-watermark';
        if (!file_exists($preview_dir)) {
            wp_mkdir_p($preview_dir);
        }

        // Generate preview filename
        $preview_filename = 'watermark-preview-' . $watermark_id . '.jpg';
        $preview_path = $preview_dir . '/' . $preview_filename;
        $preview_url = $upload_dir['baseurl'] . '/ultimate-watermark/' . $preview_filename;

        // Check if preview already exists and is recent (less than 24 hours old)
        if (file_exists($preview_path) && (time() - filemtime($preview_path)) < 86400) {
            return $preview_url;
        }

        // Use the base preview image
        $base_image_path = ULTIMATE_WATERMARK_DIR . 'assets/images/preview-image.jpg';
        if (!file_exists($base_image_path)) {
            // Fallback to default image
            return ULTIMATE_WATERMARK_URL . 'assets/images/preview-image.jpg';
        }

        // Prepare watermark data for preview generation
        $watermark_data = [
            'watermark_type' => $watermark_type,
            'watermark_text' => $watermark_text,
            'watermark_color' => $watermark_color,
            'watermark_font_size' => $watermark_font_size,
            'watermark_position' => $watermark_position,
            'watermark_opacity' => $watermark_opacity,
            'watermark_rotation' => $watermark_rotation,
            'watermark_image_id' => $watermark_image_id,
            'preview_mode' => true
        ];

        // Generate preview using WatermarkService
        try {
            if (class_exists('MantraBrain\UltimateWatermark\Watermark\WatermarkService')) {
                $preview_result = \MantraBrain\UltimateWatermark\Watermark\WatermarkService::generatePreview($base_image_path, $watermark_data);
                if ($preview_result && file_exists($preview_result)) {
                    // Copy to our preview directory
                    if (copy($preview_result, $preview_path)) {
                        // Cache the preview URL
                        update_post_meta($watermark_id, 'preview_url', $preview_url);
                        return $preview_url;
                    }
                }
            }
        } catch (\Exception $e) {
            // Watermark preview generation failed
        }

        // Fallback to default preview
        return ULTIMATE_WATERMARK_URL . 'assets/images/preview-image.jpg';
    }
}