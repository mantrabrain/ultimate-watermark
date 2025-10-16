<?php

namespace MantraBrain\UltimateWatermark\Admin\Pages;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\Admin\Components\Layout;
use MantraBrain\UltimateWatermark\Admin\Components\ConfirmationModal;
use MantraBrain\UltimateWatermark\PostTypes\WatermarkPostType;

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
                        <span class="displaying-num"><?php echo count($this->getWatermarks()); ?> <?php esc_html_e('items', 'ultimate-watermark'); ?></span>
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
                                <th class="type-column"><?php esc_html_e('Type', 'ultimate-watermark'); ?></th>
                                <th class="preview-column"><?php esc_html_e('Preview', 'ultimate-watermark'); ?></th>
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
                        <span class="displaying-num"><?php echo count($this->getWatermarks()); ?> <?php esc_html_e('items', 'ultimate-watermark'); ?></span>
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
                <td colspan="10" class="empty-cell">
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
            <td class="type-column">
                <div class="watermark-type">
                    <span class="dashicons <?php echo esc_attr($type_icon); ?>"></span>
                    <span><?php echo esc_html(ucfirst($watermark['type'])); ?></span>
                </div>
            </td>
            <td class="preview-column">
                <div class="watermark-preview">
                    <img src="<?php echo esc_url($watermark['preview_url']); ?>" alt="<?php echo esc_attr($watermark['name']); ?>" class="preview-thumbnail">
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
                    <span class="usage-count"><?php echo esc_html($watermark['usage_count']); ?></span>
                    <span class="usage-label"><?php esc_html_e('times used', 'ultimate-watermark'); ?></span>
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
        $watermarks = [];
        
        // Query watermarks from database
        $posts = get_posts([
            'post_type' => WatermarkPostType::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        foreach ($posts as $post) {
            $watermark_type = get_post_meta($post->ID, 'watermark_type', true) ?: 'text';
            $watermark_position = get_post_meta($post->ID, 'watermark_position', true) ?: 'bottom-right';
            $watermark_opacity = get_post_meta($post->ID, 'watermark_opacity', true) ?: 50;
            $watermark_image_id = get_post_meta($post->ID, 'watermark_image_id', true);
            $watermark_image_id = $watermark_image_id ? intval($watermark_image_id) : 0;
            
            // Get behavior settings
            $automatic_watermarking = get_post_meta($post->ID, 'automatic_watermarking', true) ?: '0';
            $manual_watermarking = get_post_meta($post->ID, 'manual_watermarking', true) ?: '0';
            $frontend_watermarking = get_post_meta($post->ID, 'frontend_watermarking', true) ?: '0';
            
            // Get rules settings
            $watermark_on = get_post_meta($post->ID, 'watermark_on', true) ?: 'everywhere';
            $watermark_post_types = get_post_meta($post->ID, 'watermark_post_types', true) ?: [];
            $watermark_sizes = get_post_meta($post->ID, 'watermark_sizes', true) ?: [];
            
            // Ensure arrays are properly formatted
            if (is_string($watermark_post_types)) {
                $watermark_post_types = maybe_unserialize($watermark_post_types) ?: [];
            }
            if (is_string($watermark_sizes)) {
                $watermark_sizes = maybe_unserialize($watermark_sizes) ?: [];
            }
            
            // Get preview URL
            $preview_url = $this->getWatermarkPreviewUrl($post->ID, $watermark_type, $watermark_image_id);
            
            // Get usage count (placeholder for now)
            $usage_count = get_post_meta($post->ID, 'usage_count', true) ?: 0;
            
            // Check if watermark is active
            $active_meta = get_post_meta($post->ID, 'active', true);
            $active = ($active_meta === '1' || $active_meta === 'true' || $active_meta === true);
            
            // Debug logging
            // Watermark active status checked
            
            $watermarks[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'description' => $post->post_content,
                'type' => $watermark_type,
                'position' => $watermark_position,
                'opacity' => $watermark_opacity,
                'active' => $active,
                'usage_count' => $usage_count,
                'preview_url' => $preview_url,
                'created_at' => $post->post_date,
                'automatic_watermarking' => $automatic_watermarking,
                'manual_watermarking' => $manual_watermarking,
                'frontend_watermarking' => $frontend_watermarking,
                'watermark_on' => $watermark_on,
                'watermark_post_types' => $watermark_post_types,
                'watermark_sizes' => $watermark_sizes
            ];
        }
        
        return $watermarks;
    }

    /**
     * Get only active watermarks for watermarking operations
     */
    public static function getActiveWatermarks(): array
    {
        $watermarks = [];
        
        // Query only active watermarks from database
        $posts = get_posts([
            'post_type' => WatermarkPostType::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'active',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        foreach ($posts as $post) {
            $watermark_type = get_post_meta($post->ID, 'watermark_type', true) ?: 'text';
            $watermark_position = get_post_meta($post->ID, 'watermark_position', true) ?: 'bottom-right';
            $watermark_opacity = get_post_meta($post->ID, 'watermark_opacity', true) ?: 50;
            $watermark_image_id = get_post_meta($post->ID, 'watermark_image_id', true);
            $watermark_image_id = $watermark_image_id ? intval($watermark_image_id) : 0;
            
            // Get behavior settings
            $automatic_watermarking = get_post_meta($post->ID, 'automatic_watermarking', true) ?: '0';
            $manual_watermarking = get_post_meta($post->ID, 'manual_watermarking', true) ?: '0';
            $frontend_watermarking = get_post_meta($post->ID, 'frontend_watermarking', true) ?: '0';
            
            // Get rules settings
            $watermark_on = get_post_meta($post->ID, 'watermark_on', true) ?: 'everywhere';
            $watermark_post_types = get_post_meta($post->ID, 'watermark_post_types', true) ?: [];
            $watermark_sizes = get_post_meta($post->ID, 'watermark_sizes', true) ?: [];
            
            // Ensure arrays are properly formatted
            if (is_string($watermark_post_types)) {
                $watermark_post_types = maybe_unserialize($watermark_post_types) ?: [];
            }
            if (is_string($watermark_sizes)) {
                $watermark_sizes = maybe_unserialize($watermark_sizes) ?: [];
            }
            
            $watermarks[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'description' => $post->post_content,
                'type' => $watermark_type,
                'position' => $watermark_position,
                'opacity' => $watermark_opacity,
                'active' => true, // All watermarks returned by this method are active
                'automatic_watermarking' => $automatic_watermarking,
                'manual_watermarking' => $manual_watermarking,
                'frontend_watermarking' => $frontend_watermarking,
                'watermark_on' => $watermark_on,
                'watermark_post_types' => $watermark_post_types,
                'watermark_sizes' => $watermark_sizes
            ];
        }
        
        return $watermarks;
    }

    /**
     * Format watermark rules for display
     */
    private function formatWatermarkRules(array $watermark): string
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
     * Get watermark preview URL
     *
     * @param int $watermark_id
     * @param string $watermark_type
     * @param int $watermark_image_id
     * @return string
     */
    private function getWatermarkPreviewUrl(int $watermark_id, string $watermark_type, int $watermark_image_id = 0): string
    {
        // Debug logging
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

        // Generate preview using WatermarkManager
        try {
            if (class_exists('MantraBrain\UltimateWatermark\Watermark\WatermarkManager')) {
                $preview_result = \MantraBrain\UltimateWatermark\Watermark\WatermarkManager::generatePreview($base_image_path, $watermark_data);
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