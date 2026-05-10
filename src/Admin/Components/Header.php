<?php

namespace MantraBrain\UltimateWatermark\Admin\Components;

/**
 * Admin Header Component
 *
 * Single-row unified header — brand mark, page title, nav, actions all in
 * one compact bar. The plugin name + version live on the brand logo's
 * tooltip so the bar stays uncluttered while the page title gets focus.
 *
 * @package UltimateWatermark
 * @since   2.0.9
 */
class Header
{
    /**
     * @param string|null $page_title Title for the current page.
     * @param array       $args       'subtitle' => string. (Page actions are
     *                                rendered separately below the header
     *                                via Layout::render().)
     */
    public static function render(?string $page_title = null, array $args = []): void
    {
        $plugin_version = ULTIMATE_WATERMARK_VERSION;
        $current_page   = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

        $subtitle = $args['subtitle'] ?? '';

        $brand_label = sprintf(
            /* translators: %s: plugin version */
            __('Ultimate Watermark v%s', 'ultimate-watermark'),
            $plugin_version
        );
        if (defined('ULTIMATE_WATERMARK_PRO_VERSION')) {
            $brand_label .= sprintf(
                /* translators: %s: pro plugin version */
                ' · ' . __('Pro v%s', 'ultimate-watermark'),
                ULTIMATE_WATERMARK_PRO_VERSION
            );
        }

        $nav = [
            'ultimate-watermark' => [
                'label' => __('Dashboard', 'ultimate-watermark'),
                'icon'  => self::iconDashboard(),
            ],
            'ultimate-watermark-watermarks' => [
                'label' => __('Watermarks', 'ultimate-watermark'),
                'icon'  => self::iconImage(),
            ],
            'ultimate-watermark-settings' => [
                'label' => __('Settings', 'ultimate-watermark'),
                'icon'  => self::iconSettings(),
            ],
        ];
        ?>
        <div class="ultimate-watermark-header">
            <div class="header-bar">
                <a class="header-brand"
                   href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark')); ?>"
                   title="<?php echo esc_attr($brand_label); ?>"
                   aria-label="<?php echo esc_attr($brand_label); ?>">
                    <span class="brand-logo" aria-hidden="true"><?php echo self::iconLogo(); ?></span>
                    <span class="brand-meta">
                        <span class="brand-name"><?php esc_html_e('Ultimate Watermark', 'ultimate-watermark'); ?></span>
                        <span class="brand-version">
                            <span class="brand-version-free">v<?php echo esc_html($plugin_version); ?></span>
                            <?php if (defined('ULTIMATE_WATERMARK_PRO_VERSION')): ?>
                                <span class="pro-version-badge">
                                    Pro v<?php echo esc_html(ULTIMATE_WATERMARK_PRO_VERSION); ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </span>
                </a>

                <?php if ($page_title !== null && $page_title !== ''): ?>
                    <div class="header-divider" aria-hidden="true"></div>
                    <div class="page-title-block">
                        <h1 class="page-title"><?php echo esc_html($page_title); ?></h1>
                        <?php if ($subtitle !== ''): ?>
                            <p class="page-subtitle"><?php echo esc_html($subtitle); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <nav class="header-nav" aria-label="<?php esc_attr_e('Ultimate Watermark sections', 'ultimate-watermark'); ?>">
                    <?php foreach ($nav as $slug => $item): ?>
                        <?php $is_active = ($current_page === $slug); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . rawurlencode($slug))); ?>"
                           class="nav-item<?php echo $is_active ? ' active' : ''; ?>"
                           <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                            <span class="nav-icon" aria-hidden="true"><?php echo $item['icon']; ?></span>
                            <span class="nav-label"><?php echo esc_html($item['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Inline SVG icons (Heroicons-style, currentColor for theming)
     * ------------------------------------------------------------------- */

    private static function iconLogo(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2 4 5v6.5C4 16.5 7.4 21 12 22c4.6-1 8-5.5 8-10.5V5l-8-3z"/><path d="M9 12.5 11 14.5 15.5 10"/></svg>';
    }

    private static function iconDashboard(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>';
    }

    private static function iconImage(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 16-5-5-9 9"/></svg>';
    }

    private static function iconSettings(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>';
    }
}
