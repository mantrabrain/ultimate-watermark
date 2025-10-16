<?php

namespace MantraBrain\UltimateWatermark\Admin\Components;

/**
 * Admin Footer Component
 * 
 * Shared footer for all Ultimate Watermark admin pages
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class Footer
{
    /**
     * Render admin footer
     */
    public static function render(): void
    {
        $plugin_version = ULTIMATE_WATERMARK_VERSION;
        $current_year = date('Y');
        ?>
        <div class="ultimate-watermark-footer">
            <div class="footer-container">
                <div class="footer-content">
                    <div class="footer-brand">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <span class="brand-text"><?php esc_html_e('Ultimate Watermark', 'ultimate-watermark'); ?></span>
                        <span class="version-badge">v<?php echo esc_html($plugin_version); ?></span>
                    </div>
                    
                    <div class="footer-links">
                        <a href="https://mantrabrain.com" target="_blank" class="footer-link">
                            <?php esc_html_e('MantraBrain', 'ultimate-watermark'); ?>
                        </a>
                        <a href="https://mantrabrain.com/support" target="_blank" class="footer-link">
                            <?php esc_html_e('Support', 'ultimate-watermark'); ?>
                        </a>
                        <a href="https://mantrabrain.com/docs" target="_blank" class="footer-link">
                            <?php esc_html_e('Documentation', 'ultimate-watermark'); ?>
                        </a>
                    </div>
                    
                    <div class="footer-info">
                        <span class="copyright">© <?php echo esc_html($current_year); ?> MantraBrain. All rights reserved.</span>
                        <span class="powered-by"><?php esc_html_e('Powered by WordPress', 'ultimate-watermark'); ?></span>
                    </div>
                </div>
                
                <div class="footer-stats">
                    <div class="stat-item">
                        <span class="stat-label"><?php esc_html_e('Memory Usage:', 'ultimate-watermark'); ?></span>
                        <span class="stat-value"><?php echo esc_html(size_format(memory_get_usage(true))); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php esc_html_e('Load Time:', 'ultimate-watermark'); ?></span>
                        <span class="stat-value"><?php echo esc_html(timer_stop(0, 3)); ?>s</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
