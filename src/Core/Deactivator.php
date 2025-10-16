<?php

namespace MantraBrain\UltimateWatermark\Core;

/**
 * Plugin Deactivator Class
 * 
 * Handles plugin deactivation tasks
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class Deactivator
{
    /**
     * Deactivate plugin
     */
    public static function deactivate(): void
    {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear scheduled hooks
        wp_clear_scheduled_hook('ultimate_watermark_cleanup');
    }
}
