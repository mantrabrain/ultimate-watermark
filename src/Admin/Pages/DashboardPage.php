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
                            <div class="status-item">
                                <div class="status-indicator status-success"></div>
                                <div class="status-content">
                                    <div class="status-title"><?php esc_html_e('Plugin Active', 'ultimate-watermark'); ?></div>
                                    <div class="status-description"><?php esc_html_e('Ultimate Watermark is running', 'ultimate-watermark'); ?></div>
                                </div>
                            </div>
                            
                            <div class="status-item">
                                <div class="status-indicator status-success"></div>
                                <div class="status-content">
                                    <div class="status-title"><?php esc_html_e('Image Processing', 'ultimate-watermark'); ?></div>
                                    <div class="status-description"><?php esc_html_e('GD Library available', 'ultimate-watermark'); ?></div>
                                </div>
                            </div>
                            
                            <div class="status-item">
                                <div class="status-indicator status-warning"></div>
                                <div class="status-content">
                                    <div class="status-title"><?php esc_html_e('Backup Storage', 'ultimate-watermark'); ?></div>
                                    <div class="status-description"><?php esc_html_e('2.1 GB used of 5 GB', 'ultimate-watermark'); ?></div>
                                </div>
                            </div>
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
     * Get watermarked images count
     *
     * @return int
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
                    'key' => 'ulwm-is-watermarked',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);

        return count($watermarked);
    }

    /**
     * Get watermark templates count
     *
     * @return int
     */
    private function getWatermarkTemplates(): int
    {
        // For now, return a placeholder value
        return 3;
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
        ?>
        <div class="activity-item">
            <div class="activity-icon">
                <span class="dashicons dashicons-plus-alt"></span>
            </div>
            <div class="activity-content">
                <div class="activity-title"><?php esc_html_e('New watermark created', 'ultimate-watermark'); ?></div>
                <div class="activity-time"><?php esc_html_e('2 hours ago', 'ultimate-watermark'); ?></div>
            </div>
        </div>
        
        <div class="activity-item">
            <div class="activity-icon">
                <span class="dashicons dashicons-format-image"></span>
            </div>
            <div class="activity-content">
                <div class="activity-title"><?php esc_html_e('15 images watermarked', 'ultimate-watermark'); ?></div>
                <div class="activity-time"><?php esc_html_e('4 hours ago', 'ultimate-watermark'); ?></div>
            </div>
        </div>
        
        <div class="activity-item">
            <div class="activity-icon">
                <span class="dashicons dashicons-admin-settings"></span>
            </div>
            <div class="activity-content">
                <div class="activity-title"><?php esc_html_e('Settings updated', 'ultimate-watermark'); ?></div>
                <div class="activity-time"><?php esc_html_e('1 day ago', 'ultimate-watermark'); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render watermark templates
     */
    private function renderWatermarkTemplates(): void
    {
        ?>
        <div class="template-item">
            <div class="template-preview">
                <span class="dashicons dashicons-format-image"></span>
            </div>
            <div class="template-info">
                <div class="template-name"><?php esc_html_e('Logo Watermark', 'ultimate-watermark'); ?></div>
                <div class="template-usage"><?php esc_html_e('Used 12 times', 'ultimate-watermark'); ?></div>
            </div>
        </div>
        
        <div class="template-item">
            <div class="template-preview">
                <span class="dashicons dashicons-format-text"></span>
            </div>
            <div class="template-info">
                <div class="template-name"><?php esc_html_e('Text Watermark', 'ultimate-watermark'); ?></div>
                <div class="template-usage"><?php esc_html_e('Used 8 times', 'ultimate-watermark'); ?></div>
            </div>
        </div>
        
        <div class="template-item">
            <div class="template-preview">
                <span class="dashicons dashicons-admin-tools"></span>
            </div>
            <div class="template-info">
                <div class="template-name"><?php esc_html_e('Custom Template', 'ultimate-watermark'); ?></div>
                <div class="template-usage"><?php esc_html_e('Used 3 times', 'ultimate-watermark'); ?></div>
            </div>
        </div>
        <?php
    }
}