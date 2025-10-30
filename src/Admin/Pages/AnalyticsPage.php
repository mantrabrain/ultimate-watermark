<?php

namespace MantraBrain\UltimateWatermark\Admin\Pages;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\Admin\Components\Layout;

/**
 * Analytics Page Class
 * 
 * Handles the analytics page display with detailed charts and insights
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class AnalyticsPage
{
    use SingletonTrait;

    /**
     * Render analytics page
     */
    public function render(): void
    {
        $actions = '<div class="analytics-actions">
            <select id="analytics-timeframe" class="analytics-select">
                <option value="today" selected>' . esc_html__('Today', 'ultimate-watermark') . '</option>
                <option value="yesterday">' . esc_html__('Yesterday', 'ultimate-watermark') . '</option>
                <option value="7">' . esc_html__('Last 7 days', 'ultimate-watermark') . '</option>
                <option value="30">' . esc_html__('Last 30 days', 'ultimate-watermark') . '</option>
                <option value="90">' . esc_html__('Last 90 days', 'ultimate-watermark') . '</option>
                <option value="365">' . esc_html__('Last year', 'ultimate-watermark') . '</option>
            </select>
            <button id="refresh-analytics" class="btn btn-secondary">
                <span class="dashicons dashicons-update"></span>
                ' . esc_html__('Refresh', 'ultimate-watermark') . '
            </button>
        </div>';

        Layout::render(
            __('Analytics', 'ultimate-watermark'),
            [$this, 'renderAnalyticsContent'],
            [
                'subtitle' => __('Deep insights into your watermark protection and usage', 'ultimate-watermark'),
                'actions' => $actions
            ]
        );
    }

    /**
     * Render analytics content
     */
    public function renderAnalyticsContent(): void
    {
        ?>
        <div class="ultimate-watermark-analytics">
            <!-- Key Metrics Overview -->
            <div class="analytics-overview">
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-format-image"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number" id="total-images"><?php echo esc_html($this->getTotalImages()); ?></div>
                        <div class="metric-label"><?php esc_html_e('Total Images', 'ultimate-watermark'); ?></div>
                        <div class="metric-change" id="images-change">
                            <?php 
                            $images_change = $this->getImagesChangePercentage();
                            $change_class = $images_change >= 0 ? 'positive' : 'negative';
                            $change_icon = $images_change >= 0 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';
                            ?>
                            <span class="change-icon dashicons <?php echo esc_attr($change_icon); ?>"></span>
                            <span class="change-value <?php echo esc_attr($change_class); ?>"><?php echo esc_html($images_change >= 0 ? '+' : '') . esc_html($images_change); ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number" id="protected-images"><?php echo esc_html($this->getWatermarkedImages()); ?></div>
                        <div class="metric-label"><?php esc_html_e('Protected Images', 'ultimate-watermark'); ?></div>
                        <div class="metric-change" id="protected-change">
                            <?php 
                            $protected_change = $this->getProtectedImagesChangePercentage();
                            $change_class = $protected_change >= 0 ? 'positive' : 'negative';
                            $change_icon = $protected_change >= 0 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';
                            ?>
                            <span class="change-icon dashicons <?php echo esc_attr($change_icon); ?>"></span>
                            <span class="change-value <?php echo esc_attr($change_class); ?>"><?php echo esc_html($protected_change >= 0 ? '+' : '') . esc_html($protected_change); ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number" id="protection-rate"><?php echo esc_html($this->getProtectionRate()); ?>%</div>
                        <div class="metric-label"><?php esc_html_e('Protection Rate', 'ultimate-watermark'); ?></div>
                        <div class="metric-change" id="rate-change">
                            <?php 
                            $rate_change = $this->getProtectionRateChangePercentage();
                            $change_class = $rate_change >= 0 ? 'positive' : 'negative';
                            $change_icon = $rate_change >= 0 ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';
                            ?>
                            <span class="change-icon dashicons <?php echo esc_attr($change_icon); ?>"></span>
                            <span class="change-value <?php echo esc_attr($change_class); ?>"><?php echo esc_html($rate_change >= 0 ? '+' : '') . esc_html($rate_change); ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-admin-tools"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number" id="watermark-templates"><?php echo esc_html($this->getWatermarkTemplates()); ?></div>
                        <div class="metric-label"><?php esc_html_e('Active Templates', 'ultimate-watermark'); ?></div>
                        <div class="metric-change" id="templates-change">
                            <span class="change-icon dashicons dashicons-minus"></span>
                            <span class="change-value">0%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="analytics-charts">
                <!-- Watermark Usage Over Time -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><?php esc_html_e('Watermark Usage Over Time', 'ultimate-watermark'); ?></h3>
                        <div class="chart-controls">
                            <button class="chart-btn active" data-chart="line"><?php esc_html_e('Line', 'ultimate-watermark'); ?></button>
                            <button class="chart-btn" data-chart="bar"><?php esc_html_e('Bar', 'ultimate-watermark'); ?></button>
                        </div>
                    </div>
                    <div class="chart-content">
                        <canvas id="usage-chart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Image Protection Trends -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><?php esc_html_e('Image Protection Trends', 'ultimate-watermark'); ?></h3>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color" style="background: #3b82f6;"></span>
                                <span><?php esc_html_e('Protected', 'ultimate-watermark'); ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color" style="background: #ef4444;"></span>
                                <span><?php esc_html_e('Unprotected', 'ultimate-watermark'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="chart-content">
                        <canvas id="protection-chart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Watermark Template Performance -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><?php esc_html_e('Watermark Template Performance', 'ultimate-watermark'); ?></h3>
                    </div>
                    <div class="chart-content">
                        <canvas id="templates-chart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Image Size Distribution -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><?php esc_html_e('Image Size Distribution', 'ultimate-watermark'); ?></h3>
                    </div>
                    <div class="chart-content">
                        <canvas id="sizes-chart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="analytics-details">
                <!-- Top Watermarks -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('Most Used Watermarks', 'ultimate-watermark'); ?></h3>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-watermarks')); ?>" class="card-action"><?php esc_html_e('View All', 'ultimate-watermark'); ?></a>
                    </div>
                    <div class="card-content">
                        <div class="watermark-stats">
                            <?php $this->renderTopWatermarks(); ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('Recent Watermark Activity', 'ultimate-watermark'); ?></h3>
                        <a href="#" class="card-action"><?php esc_html_e('View All', 'ultimate-watermark'); ?></a>
                    </div>
                    <div class="card-content">
                        <div class="activity-timeline">
                            <?php $this->renderActivityTimeline(); ?>
                        </div>
                    </div>
                </div>

                <!-- System Performance -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><?php esc_html_e('System Performance', 'ultimate-watermark'); ?></h3>
                    </div>
                    <div class="card-content">
                        <div class="performance-metrics">
                            <?php $this->renderPerformanceMetrics(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // Analytics data for JavaScript
        window.ultimateWatermarkAnalytics = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('ultimate_watermark_analytics'); ?>'
        };
        </script>
        <?php
    }

    /**
     * Get total images count
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
     * Get watermarked images count
     */
    private function getWatermarkedImages(): int
    {
        $watermarked = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'meta_query' => [
                [
                    'key' => 'applied_watermarks',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        return count($watermarked);
    }

    /**
     * Get watermark templates count
     */
    private function getWatermarkTemplates(): int
    {
        $watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => 'active',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);

        return count($watermarks);
    }

    /**
     * Get protection rate percentage
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
     * Get usage data for charts
     */
    private function getUsageData(): array
    {
        // Get data for last 30 days
        $days = 30;
        $data = [];
        $labels = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($date));
            
            // Count watermarked images created on this date
            $count = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'date_query' => [
                    [
                        'year' => date('Y', strtotime($date)),
                        'month' => date('n', strtotime($date)),
                        'day' => date('j', strtotime($date)),
                    ]
                ],
                'meta_query' => [
                    [
                        'key' => 'applied_watermarks',
                        'compare' => 'EXISTS'
                    ]
                ],
                'numberposts' => -1,
                'fields' => 'ids'
            ]);
            
            $data[] = count($count);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('Watermarked Images', 'ultimate-watermark'),
                    'data' => $data,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4
                ]
            ]
        ];
    }

    /**
     * Get protection data for charts
     */
    private function getProtectionData(): array
    {
        $total = $this->getTotalImages();
        $protected = $this->getWatermarkedImages();
        $unprotected = $total - $protected;
        
        return [
            'labels' => [__('Protected', 'ultimate-watermark'), __('Unprotected', 'ultimate-watermark')],
            'datasets' => [
                [
                    'data' => [$protected, $unprotected],
                    'backgroundColor' => ['#10b981', '#ef4444'],
                    'borderWidth' => 0
                ]
            ]
        ];
    }

    /**
     * Get templates data for charts
     */
    private function getTemplatesData(): array
    {
        $watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);
        
        $labels = [];
        $data = [];
        
        foreach ($watermarks as $watermark) {
            $usage_count = get_post_meta($watermark->ID, 'watermark_usage_count', true) ?: 0;
            $labels[] = $watermark->post_title ?: __('Untitled', 'ultimate-watermark');
            $data[] = intval($usage_count);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('Usage Count', 'ultimate-watermark'),
                    'data' => $data,
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
                    ],
                    'borderWidth' => 0
                ]
            ]
        ];
    }

    /**
     * Get sizes data for charts
     */
    private function getSizesData(): array
    {
        global $wpdb;
        
        // Get all image sizes and their counts
        $sizes = get_intermediate_image_sizes();
        $sizes[] = 'full';
        
        $labels = [];
        $data = [];
        
        foreach ($sizes as $size) {
            // Get all images with watermarks_by_size meta
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = 'watermarks_by_size'
                AND pm.meta_value != ''
                AND p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
            "));
            
            $count = 0;
            foreach ($results as $result) {
                $watermarks_by_size = maybe_unserialize($result->meta_value);
                if (is_array($watermarks_by_size) && isset($watermarks_by_size[$size]) && !empty($watermarks_by_size[$size])) {
                    $count++;
                }
            }
            
            $labels[] = ucfirst($size);
            $data[] = $count;
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('Watermarked Images', 'ultimate-watermark'),
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => '#3b82f6',
                    'borderWidth' => 1
                ]
            ]
        ];
    }

    /**
     * Render top watermarks
     */
    private function renderTopWatermarks(): void
    {
        $watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'numberposts' => 5,
            'orderby' => 'meta_value_num',
            'meta_key' => 'watermark_usage_count',
            'order' => 'DESC'
        ]);
        
        if (empty($watermarks)) {
            echo '<div class="empty-state">
                <span class="dashicons dashicons-info"></span>
                <p>' . esc_html__('No watermarks found', 'ultimate-watermark') . '</p>
            </div>';
            return;
        }
        
        foreach ($watermarks as $watermark) {
            $usage_count = get_post_meta($watermark->ID, 'watermark_usage_count', true) ?: 0;
            $watermark_type = get_post_meta($watermark->ID, 'watermark_type', true) ?: 'text';
            $active = get_post_meta($watermark->ID, 'active', true) === '1';
            
            ?>
            <div class="watermark-stat-item">
                <div class="watermark-info">
                    <div class="watermark-name">
                        <?php echo esc_html($watermark->post_title ?: __('Untitled Watermark', 'ultimate-watermark')); ?>
                        <?php if (!$active): ?>
                            <span class="status-badge inactive"><?php esc_html_e('Inactive', 'ultimate-watermark'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="watermark-type">
                        <span class="dashicons <?php echo $watermark_type === 'image' ? 'dashicons-format-image' : 'dashicons-format-text'; ?>"></span>
                        <?php echo $watermark_type === 'image' ? esc_html__('Image Watermark', 'ultimate-watermark') : esc_html__('Text Watermark', 'ultimate-watermark'); ?>
                    </div>
                </div>
                <div class="watermark-usage">
                    <div class="usage-number"><?php echo esc_html($usage_count); ?></div>
                    <div class="usage-label"><?php esc_html_e('uses', 'ultimate-watermark'); ?></div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render activity timeline
     */
    private function renderActivityTimeline(): void
    {
        $activities = $this->getRecentActivities();
        
        if (empty($activities)) {
            echo '<div class="empty-state">
                <span class="dashicons dashicons-info"></span>
                <p>' . esc_html__('No recent activity', 'ultimate-watermark') . '</p>
            </div>';
            return;
        }
        
        foreach ($activities as $activity) {
            ?>
            <div class="timeline-item">
                <div class="timeline-marker">
                    <span class="dashicons <?php echo esc_attr($activity['icon']); ?>"></span>
                </div>
                <div class="timeline-content">
                    <div class="timeline-title"><?php echo esc_html($activity['title']); ?></div>
                    <div class="timeline-time"><?php echo esc_html($activity['time']); ?></div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render performance metrics
     */
    private function renderPerformanceMetrics(): void
    {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_percentage = ($memory_usage / wp_convert_hr_to_bytes($memory_limit)) * 100;
        
        $upload_dir = wp_upload_dir();
        $backup_size = $this->getBackupStorageSize();
        
        ?>
        <div class="performance-item">
            <div class="performance-label"><?php esc_html_e('Memory Usage', 'ultimate-watermark'); ?></div>
            <div class="performance-bar">
                <div class="performance-fill" style="width: <?php echo esc_attr($memory_percentage); ?>%"></div>
            </div>
            <div class="performance-value"><?php echo esc_html(size_format($memory_usage) . ' / ' . $memory_limit); ?></div>
        </div>
        
        <div class="performance-item">
            <div class="performance-label"><?php esc_html_e('Backup Storage', 'ultimate-watermark'); ?></div>
            <div class="performance-bar">
                <div class="performance-fill" style="width: 25%"></div>
            </div>
            <div class="performance-value"><?php echo esc_html(size_format($backup_size)); ?></div>
        </div>
        
        <div class="performance-item">
            <div class="performance-label"><?php esc_html_e('Image Processing', 'ultimate-watermark'); ?></div>
            <div class="performance-value">
                <?php 
                if (extension_loaded('imagick')) {
                    esc_html_e('Imagick (Recommended)', 'ultimate-watermark');
                } elseif (extension_loaded('gd')) {
                    esc_html_e('GD Library', 'ultimate-watermark');
                } else {
                    esc_html_e('Not Available', 'ultimate-watermark');
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(): array
    {
        $activities = [];
        
        // Get recent watermarks
        $recent_watermarks = get_posts([
            'post_type' => 'ultimate_watermark',
            'post_status' => 'publish',
            'numberposts' => 5,
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
        
        return array_slice($activities, 0, 10);
    }

    /**
     * Get backup storage size
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

    /**
     * Get images change percentage (last 7 days vs previous 7 days)
     */
    private function getImagesChangePercentage(): int
    {
        $current_period = $this->getImagesCountForPeriod(7);
        $previous_period = $this->getImagesCountForPeriod(14, 7);
        
        if ($previous_period === 0) {
            return $current_period > 0 ? 100 : 0;
        }
        
        return round((($current_period - $previous_period) / $previous_period) * 100);
    }

    /**
     * Get protected images change percentage (last 7 days vs previous 7 days)
     */
    private function getProtectedImagesChangePercentage(): int
    {
        $current_period = $this->getWatermarkedImagesCountForPeriod(7);
        $previous_period = $this->getWatermarkedImagesCountForPeriod(14, 7);
        
        if ($previous_period === 0) {
            return $current_period > 0 ? 100 : 0;
        }
        
        return round((($current_period - $previous_period) / $previous_period) * 100);
    }

    /**
     * Get protection rate change percentage (last 7 days vs previous 7 days)
     */
    private function getProtectionRateChangePercentage(): int
    {
        $current_images = $this->getImagesCountForPeriod(7);
        $current_watermarked = $this->getWatermarkedImagesCountForPeriod(7);
        $current_rate = $current_images > 0 ? ($current_watermarked / $current_images) * 100 : 0;
        
        $previous_images = $this->getImagesCountForPeriod(14, 7);
        $previous_watermarked = $this->getWatermarkedImagesCountForPeriod(14, 7);
        $previous_rate = $previous_images > 0 ? ($previous_watermarked / $previous_images) * 100 : 0;
        
        if ($previous_rate === 0) {
            return $current_rate > 0 ? 100 : 0;
        }
        
        return round($current_rate - $previous_rate);
    }

    /**
     * Get images count for a specific period
     */
    private function getImagesCountForPeriod(int $days_back, int $days_forward = 0): int
    {
        $end_date = date('Y-m-d', strtotime("-{$days_forward} days"));
        $start_date = date('Y-m-d', strtotime("-{$days_back} days"));
        
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'date_query' => [
                [
                    'after' => $start_date,
                    'before' => $end_date,
                    'inclusive' => true,
                ]
            ]
        ]);

        return count($attachments);
    }

    /**
     * Get watermarked images count for a specific period
     */
    private function getWatermarkedImagesCountForPeriod(int $days_back, int $days_forward = 0): int
    {
        $end_date = date('Y-m-d', strtotime("-{$days_forward} days"));
        $start_date = date('Y-m-d', strtotime("-{$days_back} days"));
        
        $watermarked = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'date_query' => [
                [
                    'after' => $start_date,
                    'before' => $end_date,
                    'inclusive' => true,
                ]
            ],
            'meta_query' => [
                [
                    'key' => 'applied_watermarks',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        return count($watermarked);
    }

}
