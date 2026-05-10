<?php

namespace MantraBrain\UltimateWatermark\Admin\Components;

/**
 * Admin Layout Component
 *
 * Shared wrapper for every Ultimate Watermark admin page. The plugin
 * shows a single sticky header bar (brand + page title + nav) and a
 * separate right-aligned action toolbar below it whenever the page
 * needs primary actions (e.g. "Save", "Refresh", a timeframe select).
 *
 * @package UltimateWatermark
 * @since   2.0.9
 */
class Layout
{
    /**
     * Render page with header, optional action toolbar, body, and footer.
     *
     * @param string   $page_title       Title for the current page.
     * @param callable $content_callback Callable that prints the page body.
     * @param array    $args             Optional: 'subtitle' string, 'actions' HTML.
     */
    public static function render(string $page_title, callable $content_callback, array $args = []): void
    {
        wp_enqueue_style(
            'ultimate-watermark-upgrade-modal',
            ULTIMATE_WATERMARK_URL . 'assets/css/upgrade-modal.css',
            ['ultimate-watermark-admin'],
            ULTIMATE_WATERMARK_VERSION
        );

        $subtitle = $args['subtitle'] ?? '';
        $actions  = $args['actions']  ?? '';
        ?>
        <div class="ultimate-watermark-layout">
            <?php Header::render($page_title, ['subtitle' => $subtitle]); ?>

            <?php if ($actions !== ''): ?>
                <div class="page-toolbar" role="toolbar" aria-label="<?php esc_attr_e('Page actions', 'ultimate-watermark'); ?>">
                    <div class="page-actions"><?php echo $actions; ?></div>
                </div>
            <?php endif; ?>

            <div class="ultimate-watermark-content">
                <div class="content-body">
                    <?php call_user_func($content_callback); ?>
                </div>
            </div>

            <?php Footer::render(); ?>
        </div>
        <?php
    }
}
