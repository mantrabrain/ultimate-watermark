<?php

namespace MantraBrain\UltimateWatermark\Admin\Components;

/**
 * Admin Header Component
 * 
 * Shared header for all Ultimate Watermark admin pages
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class Header
{
    /**
     * Render admin header
     */
    public static function render(): void
    {
        $plugin_version = ULTIMATE_WATERMARK_VERSION;
        ?>
        <div class="ultimate-watermark-header">
            <div class="header-container">
                <div class="header-brand">
                    <div class="brand-logo">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <div class="brand-info">
                        <h1 class="brand-title"><?php esc_html_e('Ultimate Watermark', 'ultimate-watermark'); ?></h1>
                        <span class="brand-version">v<?php echo esc_html($plugin_version); ?></span>
                    </div>
                </div>
                
                <div class="header-actions">
                    <div class="header-nav">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark')); ?>" class="nav-item">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php esc_html_e('Dashboard', 'ultimate-watermark'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-watermarks')); ?>" class="nav-item">
                            <span class="dashicons dashicons-format-image"></span>
                            <?php esc_html_e('Watermarks', 'ultimate-watermark'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-settings')); ?>" class="nav-item">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php esc_html_e('Settings', 'ultimate-watermark'); ?>
                        </a>
                    </div>
                    
                    <div class="header-user">
                        <div class="user-info">
                            <span class="user-name"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
                            <span class="user-role"><?php echo esc_html(ucfirst(wp_get_current_user()->roles[0] ?? 'user')); ?></span>
                        </div>
                        <div class="user-avatar">
                            <?php echo get_avatar(get_current_user_id(), 32); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
