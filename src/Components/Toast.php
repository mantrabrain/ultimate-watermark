<?php

namespace MantraBrain\UltimateWatermark\Components;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;

/**
 * Toast Notification Component
 * 
 * Handles toast notifications throughout the plugin
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class Toast
{
    use SingletonTrait;

    /**
     * Initialize toast system
     */
    public function init()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueToastAssets']);
    }

    /**
     * Enqueue toast assets
     */
    public function enqueueToastAssets()
    {
        wp_enqueue_style(
            'ultimate-watermark-toast',
            ULTIMATE_WATERMARK_URL . 'assets/css/toast.css',
            [],
            ULTIMATE_WATERMARK_VERSION
        );

        wp_enqueue_script(
            'ultimate-watermark-toast',
            ULTIMATE_WATERMARK_URL . 'assets/js/toast.js',
            ['jquery'],
            ULTIMATE_WATERMARK_VERSION,
            true
        );

        wp_localize_script('ultimate-watermark-toast', 'ultimateWatermarkToast', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ultimate_watermark_nonce'),
        ]);
    }

    /**
     * Show success toast
     */
    public static function success($message, $duration = 5000)
    {
        self::show('success', $message, $duration);
    }

    /**
     * Show error toast
     */
    public static function error($message, $duration = 7000)
    {
        self::show('error', $message, $duration);
    }

    /**
     * Show info toast
     */
    public static function info($message, $duration = 5000)
    {
        self::show('info', $message, $duration);
    }

    /**
     * Show warning toast
     */
    public static function warning($message, $duration = 6000)
    {
        self::show('warning', $message, $duration);
    }

    /**
     * Show toast notification
     */
    private static function show($type, $message, $duration)
    {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            if (typeof UltimateWatermarkToast !== 'undefined') {
                UltimateWatermarkToast.show('<?php echo esc_js($type); ?>', '<?php echo esc_js($message); ?>', <?php echo intval($duration); ?>);
            }
        });
        </script>
        <?php
    }
}
