<?php

namespace MantraBrain\UltimateWatermark\Admin\Components;

/**
 * Admin Footer Component
 *
 * Shared footer for every Ultimate Watermark admin page.
 *
 * @package UltimateWatermark
 * @since   2.0.9
 */
class Footer
{
    /**
     * Render admin footer
     */
    public static function render(): void
    {
        $current_year = date('Y');
        ?>
        <div class="ultimate-watermark-footer">
            <div class="footer-container">
                <div class="footer-brand">
                    <span class="footer-brand-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="M12 2 4 5v6.5C4 16.5 7.4 21 12 22c4.6-1 8-5.5 8-10.5V5l-8-3z"/>
                            <path d="M9 12.5 11 14.5 15.5 10"/>
                        </svg>
                    </span>
                    <span class="footer-brand-text"><?php esc_html_e('Ultimate Watermark', 'ultimate-watermark'); ?></span>
                    <span class="copyright">© <?php echo esc_html($current_year); ?> MantraBrain</span>
                </div>

                <nav class="footer-links" aria-label="<?php esc_attr_e('Plugin links', 'ultimate-watermark'); ?>">
                    <a href="<?php echo esc_url('https://ultimate-watermark.mantrabrain.com/docs/'); ?>" target="_blank" rel="noopener noreferrer" class="footer-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5V4.5A2.5 2.5 0 0 1 6.5 2z"/>
                        </svg>
                        <?php esc_html_e('Documentation', 'ultimate-watermark'); ?>
                    </a>
                    <a href="<?php echo esc_url('https://mantrabrain.com/contact'); ?>" target="_blank" rel="noopener noreferrer" class="footer-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/>
                            <path d="M12 17h.01"/>
                        </svg>
                        <?php esc_html_e('Support', 'ultimate-watermark'); ?>
                    </a>
                    <a href="<?php echo esc_url('https://mantrabrain.com'); ?>" target="_blank" rel="noopener noreferrer" class="footer-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <path d="M15 3h6v6"/>
                            <path d="M10 14 21 3"/>
                        </svg>
                        <?php esc_html_e('MantraBrain', 'ultimate-watermark'); ?>
                    </a>
                </nav>
            </div>
        </div>
        <?php
    }
}
