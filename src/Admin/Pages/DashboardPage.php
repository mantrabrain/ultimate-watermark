<?php

namespace MantraBrain\UltimateWatermark\Admin\Pages;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\Admin\Components\Layout;

/**
 * Dashboard Page Class
 * 
 * Handles the main dashboard page display and functionality
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class DashboardPage
{
    use SingletonTrait;

    /**
     * Render dashboard page
     */
    public function render(): void
    {
        $actions = '<a href="' . esc_url(admin_url('admin.php?page=ultimate-watermark-add-watermark')) . '" class="btn btn-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            ' . esc_html__('Create Watermark', 'ultimate-watermark') . '
        </a>';

        Layout::render(
            __('Dashboard', 'ultimate-watermark'),
            [$this, 'renderDashboardContent'],
            [
                'subtitle' => __('Monitor your watermark protection and manage your content', 'ultimate-watermark'),
                'actions' => $actions
            ]
        );
    }

    /**
     * Render dashboard content
     */
    public function renderDashboardContent(): void
    {
        ?>
        <div class="ultimate-watermark-saas-dashboard">
            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-format-image"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($this->getTotalImages()); ?></div>
                        <div class="stat-label"><?php esc_html_e('Total Images', 'ultimate-watermark'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($this->getWatermarkedImages()); ?></div>
                        <div class="stat-label"><?php esc_html_e('Protected Images', 'ultimate-watermark'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-tools"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($this->getWatermarkTemplates()); ?></div>
                        <div class="stat-label"><?php esc_html_e('Watermark Templates', 'ultimate-watermark'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($this->getProtectionRate()); ?>%</div>
                        <div class="stat-label"><?php esc_html_e('Protection Rate', 'ultimate-watermark'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="dashboard-grid">
                <!-- Recent Activity -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('Recent Activity', 'ultimate-watermark'); ?></h3>
                        <a href="#" class="card-action"><?php esc_html_e('View All', 'ultimate-watermark'); ?></a>
                    </div>
                    <div class="card-content">
                        <div class="activity-list">
                            <?php $this->renderRecentActivity(); ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('Quick Actions', 'ultimate-watermark'); ?></h3>
                    </div>
                    <div class="card-content">
                        <div class="action-grid">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-add-watermark')); ?>" class="action-item">
                                        <div class="action-icon">
                                            <span class="dashicons dashicons-plus-alt"></span>
                                        </div>
                                        <div class="action-content">
                                            <h4><?php esc_html_e('Create Watermark', 'ultimate-watermark'); ?></h4>
                                            <p><?php esc_html_e('Add a new watermark template', 'ultimate-watermark'); ?></p>
                                        </div>
                                    </a>
                            
                            <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="action-item">
                                <div class="action-icon">
                                    <span class="dashicons dashicons-upload"></span>
                                </div>
                                <div class="action-content">
                                    <h4><?php esc_html_e('Upload Images', 'ultimate-watermark'); ?></h4>
                                    <p><?php esc_html_e('Add new images to your library', 'ultimate-watermark'); ?></p>
                                </div>
                            </a>
                            
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-settings')); ?>" class="action-item">
                                <div class="action-icon">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                </div>
                                <div class="action-content">
                                    <h4><?php esc_html_e('Settings', 'ultimate-watermark'); ?></h4>
                                    <p><?php esc_html_e('Configure plugin settings', 'ultimate-watermark'); ?></p>
                                </div>
                            </a>
                            
                            <a href="#" class="action-item">
                                <div class="action-icon">
                                    <span class="dashicons dashicons-analytics"></span>
                                </div>
                                <div class="action-content">
                                    <h4><?php esc_html_e('Analytics', 'ultimate-watermark'); ?></h4>
                                    <p><?php esc_html_e('View protection statistics', 'ultimate-watermark'); ?></p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Watermark Templates -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('Watermark Templates', 'ultimate-watermark'); ?></h3>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-watermarks')); ?>" class="card-action"><?php esc_html_e('Manage', 'ultimate-watermark'); ?></a>
                    </div>
                    <div class="card-content">
                        <div class="template-list">
                            <?php $this->renderWatermarkTemplates(); ?>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('System Status', 'ultimate-watermark'); ?></h3>
                    </div>
                    <div class="card-content">
                    <div class="status-list">
                        <?php $this->renderSystemStatus(); ?>
                    </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get total images count
     *
     * @return int
     */
    private function getTotalImages(): int
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
        ]);

        return count($attachments);
    }

    /**
     * Get watermarked images count.
     *
     * Counts attachments that have either of the watermark-applied meta
     * keys: the current `_ulwm_watermarked` flag (set by the watermark
     * pipeline) or the legacy `ulwm-is-watermarked` flag (set by v1 of
     * the plugin and migrated forward but not always rewritten).
     */
    private function getWatermarkedImages(): int
    {
        global $wpdb;

        // Use a direct count query — it's an order of magnitude cheaper than
        // pulling full posts via get_posts() + count().
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
               FROM {$wpdb->posts} p
               INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
              WHERE p.post_type = 'attachment'
                AND p.post_status = 'inherit'
                AND p.post_mime_type LIKE 'image/%'
                AND (
                    (m.meta_key = '_ulwm_watermarked' AND m.meta_value IN ('1', 'true', 'b:1;'))
                    OR (m.meta_key = 'ulwm-is-watermarked' AND m.meta_value = '1')
                    OR (m.meta_key = 'watermark_count' AND CAST(m.meta_value AS UNSIGNED) > 0)
                )"
        );

        return $count;
    }

    /**
     * Get watermark templates count
     *
     * @return int
     */
    private function getWatermarkTemplates(): int
    {
        $watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);

        return count($watermarks);
    }

    /**
     * Get protection rate percentage
     *
     * @return int
     */
    private function getProtectionRate(): int
    {
        $total = $this->getTotalImages();
        $watermarked = $this->getWatermarkedImages();
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($watermarked / $total) * 100);
    }

    /**
     * Render recent activity
     */
    private function renderRecentActivity(): void
    {
        $activities = $this->getRecentActivities();
        
        if (empty($activities)) {
            echo '<div class="activity-item">
                <div class="activity-icon">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div class="activity-content">
                    <div class="activity-title">' . esc_html__('No recent activity', 'ultimate-watermark') . '</div>
                    <div class="activity-time">' . esc_html__('Start by creating a watermark', 'ultimate-watermark') . '</div>
                </div>
            </div>';
            return;
        }
        
        foreach ($activities as $activity) {
            ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <span class="dashicons <?php echo esc_attr($activity['icon']); ?>"></span>
                </div>
                <div class="activity-content">
                    <div class="activity-title"><?php echo esc_html($activity['title']); ?></div>
                    <div class="activity-time"><?php echo esc_html($activity['time']); ?></div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Get recent activities
     *
     * @return array
     */
    private function getRecentActivities(): array
    {
        $activities = [];
        
        // Get recent watermarks
        $recent_watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'numberposts' => 3,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        foreach ($recent_watermarks as $watermark) {
            $watermark_type = get_post_meta($watermark->ID, 'watermark_type', true) ?: 'text';
            $type_label = $watermark_type === 'image' ? 'Image watermark' : 'Text watermark';
            
            $activities[] = [
                'icon' => 'dashicons-plus-alt',
                'title' => sprintf(__('New %s created', 'ultimate-watermark'), $type_label),
                'time' => human_time_diff(strtotime($watermark->post_date), current_time('timestamp')) . ' ' . __('ago', 'ultimate-watermark')
            ];
        }
        
        // Get recent watermarked images
        $recent_images = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'ulwm-is-watermarked',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        if (!empty($recent_images)) {
            $activities[] = [
                'icon' => 'dashicons-format-image',
                'title' => sprintf(__('%d images watermarked', 'ultimate-watermark'), count($recent_images)),
                'time' => human_time_diff(strtotime($recent_images[0]->post_date), current_time('timestamp')) . ' ' . __('ago', 'ultimate-watermark')
            ];
        }
        
        // Sort by time (most recent first)
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        return array_slice($activities, 0, 5); // Return only 5 most recent
    }

    /**
     * Render watermark templates
     */
    private function renderWatermarkTemplates(): void
    {
        $watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'numberposts' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        if (empty($watermarks)) {
            echo '<div class="template-item">
                <div class="template-preview">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div class="template-info">
                    <div class="template-name">' . esc_html__('No watermarks created', 'ultimate-watermark') . '</div>
                    <div class="template-usage">' . esc_html__('Create your first watermark', 'ultimate-watermark') . '</div>
                </div>
            </div>';
            return;
        }
        
        foreach ($watermarks as $watermark) {
            $watermark_type = get_post_meta($watermark->ID, 'watermark_type', true) ?: 'text';
            $usage_count = get_post_meta($watermark->ID, 'watermark_usage_count', true) ?: 0;
            $active = get_post_meta($watermark->ID, 'active', true) === '1';
            
            $icon = $watermark_type === 'image' ? 'dashicons-format-image' : 'dashicons-format-text';
            $status_class = $active ? 'template-active' : 'template-inactive';
            
            ?>
            <div class="template-item <?php echo esc_attr($status_class); ?>">
                <div class="template-preview">
                    <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                </div>
                <div class="template-info">
                    <div class="template-name">
                        <?php echo esc_html($watermark->post_title ?: __('Untitled Watermark', 'ultimate-watermark')); ?>
                        <?php if (!$active): ?>
                            <span class="template-status"><?php esc_html_e('(Inactive)', 'ultimate-watermark'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="template-usage">
                        <?php 
                        if ($usage_count > 0) {
                            printf(_n('Used %d time', 'Used %d times', $usage_count, 'ultimate-watermark'), $usage_count);
                        } else {
                            esc_html_e('Not used yet', 'ultimate-watermark');
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render system status
     */
    private function renderSystemStatus(): void
    {
        // Plugin status
        $plugin_active = is_plugin_active('ultimate-watermark/ultimate-watermark.php');
        ?>
        <div class="status-item">
            <div class="status-indicator <?php echo $plugin_active ? 'status-success' : 'status-error'; ?>"></div>
            <div class="status-content">
                <div class="status-title"><?php esc_html_e('Plugin Status', 'ultimate-watermark'); ?></div>
                <div class="status-description">
                    <?php echo $plugin_active ? esc_html__('Ultimate Watermark is active', 'ultimate-watermark') : esc_html__('Plugin is inactive', 'ultimate-watermark'); ?>
                </div>
            </div>
        </div>
        <?php

        // Image processing libraries
        $gd_available = extension_loaded('gd');
        $imagick_available = extension_loaded('imagick');
        $image_lib_status = $gd_available || $imagick_available;
        $image_lib_name = $imagick_available ? 'Imagick' : ($gd_available ? 'GD Library' : 'None');
        ?>
        <div class="status-item">
            <div class="status-indicator <?php echo $image_lib_status ? 'status-success' : 'status-error'; ?>"></div>
            <div class="status-content">
                <div class="status-title"><?php esc_html_e('Image Processing', 'ultimate-watermark'); ?></div>
                <div class="status-description">
                    <?php 
                    if ($image_lib_status) {
                        printf(esc_html__('%s available', 'ultimate-watermark'), $image_lib_name);
                    } else {
                        esc_html_e('No image processing library available', 'ultimate-watermark');
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php

        // Memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);
        $memory_usage = memory_get_usage(true);
        $memory_percentage = ($memory_usage / $memory_limit_bytes) * 100;
        $memory_status = $memory_percentage > 80 ? 'status-warning' : 'status-success';
        ?>
        <div class="status-item">
            <div class="status-indicator <?php echo $memory_status; ?>"></div>
            <div class="status-content">
                <div class="status-title"><?php esc_html_e('Memory Usage', 'ultimate-watermark'); ?></div>
                <div class="status-description">
                    <?php 
                    printf(
                        esc_html__('%s used of %s (%.1f%%)', 'ultimate-watermark'),
                        size_format($memory_usage),
                        $memory_limit,
                        $memory_percentage
                    );
                    ?>
                </div>
            </div>
        </div>
        <?php

        // Upload directory writable
        $upload_dir = wp_upload_dir();
        $upload_writable = is_writable($upload_dir['basedir']);
        ?>
        <div class="status-item">
            <div class="status-indicator <?php echo $upload_writable ? 'status-success' : 'status-error'; ?>"></div>
            <div class="status-content">
                <div class="status-title"><?php esc_html_e('Upload Directory', 'ultimate-watermark'); ?></div>
                <div class="status-description">
                    <?php echo $upload_writable ? esc_html__('Writable', 'ultimate-watermark') : esc_html__('Not writable', 'ultimate-watermark'); ?>
                </div>
            </div>
        </div>
        <?php

        // Backup storage
        $backup_size = $this->getBackupStorageSize();
        $backup_status = $backup_size > 100 * 1024 * 1024 ? 'status-warning' : 'status-success'; // 100MB warning
        ?>
        <div class="status-item">
            <div class="status-indicator <?php echo $backup_status; ?>"></div>
            <div class="status-content">
                <div class="status-title"><?php esc_html_e('Backup Storage', 'ultimate-watermark'); ?></div>
                <div class="status-description">
                    <?php printf(esc_html__('%s used', 'ultimate-watermark'), size_format($backup_size)); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get backup storage size
     *
     * @return int Size in bytes
     */
    private function getBackupStorageSize(): int
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/ultimate-watermark-backups';
        
        if (!is_dir($backup_dir)) {
            return 0;
        }
        
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($backup_dir));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
}