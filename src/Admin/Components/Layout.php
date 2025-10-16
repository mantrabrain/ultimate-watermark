<?php

namespace MantraBrain\UltimateWatermark\Admin\Components;

/**
 * Admin Layout Component
 * 
 * Shared layout wrapper for all Ultimate Watermark admin pages
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class Layout
{
    /**
     * Render page with header and footer
     *
     * @param string $page_title
     * @param callable $content_callback
     * @param array $args Additional arguments
     */
    public static function render(string $page_title, callable $content_callback, array $args = []): void
    {
        ?>
        <div class="ultimate-watermark-layout">
            <?php Header::render(); ?>
            
            <div class="ultimate-watermark-content">
                <div class="content-header">
                    <div class="content-title">
                        <div class="title-wrapper">
                            <div class="title-icon">
                                <?php if (strpos($page_title, 'Edit') !== false): ?>
                                    <span class="dashicons dashicons-edit"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                <?php endif; ?>
                            </div>
                            <div class="title-content">
                                <h1><?php echo esc_html($page_title); ?></h1>
                                <?php if (!empty($args['subtitle'])): ?>
                                    <p class="content-subtitle"><?php echo esc_html($args['subtitle']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($args['actions'])): ?>
                        <div class="content-actions">
                            <?php echo $args['actions']; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="content-body">
                    <?php call_user_func($content_callback); ?>
                </div>
            </div>
            
            <?php Footer::render(); ?>
        </div>
        <?php
    }
}
