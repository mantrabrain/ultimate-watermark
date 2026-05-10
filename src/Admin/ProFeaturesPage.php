<?php

namespace MantraBrain\UltimateWatermark\Admin;

/**
 * Pro Features Showcase Page (Free Plugin)
 *
 * Displays essential Pro features to encourage upgrade.
 * This page is hidden when Pro plugin is active.
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class ProFeaturesPage
{
    /**
     * Render the Pro features page
     */
    public static function render(): void
    {
        ?>
        <div class="ulwm-pro-features-page">
            <div class="ulwm-pro-hero">
                <h1><?php esc_html_e('Upgrade to Ultimate Watermark Pro', 'ultimate-watermark'); ?></h1>
                <p class="ulwm-pro-subtitle">
                    <?php esc_html_e('Unlock powerful features to protect and manage your images like a pro.', 'ultimate-watermark'); ?>
                </p>
            </div>

            <div class="ulwm-pro-features-grid">
                <?php foreach (self::getFeatures() as $feature): ?>
                    <div class="ulwm-pro-feature-card">
                        <div class="ulwm-pro-feature-icon">
                            <span class="dashicons <?php echo esc_attr($feature['icon']); ?>"></span>
                        </div>
                        <h3><?php echo esc_html($feature['title']); ?></h3>
                        <p><?php echo esc_html($feature['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ulwm-pro-cta">
                <h2><?php esc_html_e('Ready to Upgrade?', 'ultimate-watermark'); ?></h2>
                <p><?php esc_html_e('Get instant access to all Pro features with a one-time purchase.', 'ultimate-watermark'); ?></p>
                <a href="<?php echo esc_url('https://mantrabrain.com/plugins/ultimate-watermark#pricing'); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero">
                    <?php esc_html_e('Get Pro Now', 'ultimate-watermark'); ?>
                </a>
            </div>

            <div class="ulwm-pro-comparison">
                <h2><?php esc_html_e('Free vs Pro', 'ultimate-watermark'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Feature', 'ultimate-watermark'); ?></th>
                            <th><?php esc_html_e('Free', 'ultimate-watermark'); ?></th>
                            <th><?php esc_html_e('Pro', 'ultimate-watermark'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (self::getComparisonRows() as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row[0]); ?></td>
                                <td><?php echo $row[1] ? '<span class="ulwm-check ulwm-check--yes dashicons dashicons-yes-alt" aria-label="' . esc_attr__('Included', 'ultimate-watermark') . '"></span>' : '<span class="ulwm-check ulwm-check--no dashicons dashicons-minus" aria-label="' . esc_attr__('Not included', 'ultimate-watermark') . '"></span>'; ?></td>
                                <td><?php echo $row[2] ? '<span class="ulwm-check ulwm-check--yes dashicons dashicons-yes-alt" aria-label="' . esc_attr__('Included', 'ultimate-watermark') . '"></span>' : '<span class="ulwm-check ulwm-check--no dashicons dashicons-minus" aria-label="' . esc_attr__('Not included', 'ultimate-watermark') . '"></span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
        // All visual styling lives in assets/css/pro-features.css.
    }

    /**
     * Get feature definitions
     */
    private static function getFeatures(): array
    {
        return [
            [
                'title'       => __('Unlimited Watermarks', 'ultimate-watermark'),
                'icon'        => 'dashicons-yes-alt',
                'description' => __('Create as many watermark templates as you need without any limitations.', 'ultimate-watermark'),
            ],
            [
                'title'       => __('Dynamic Content Placeholders', 'ultimate-watermark'),
                'icon'        => 'dashicons-editor-code',
                'description' => __('Add camera EXIF data, dates, usernames, and custom fields to your watermarks automatically.', 'ultimate-watermark'),
            ],
            [
                'title'       => __('WooCommerce Integration', 'ultimate-watermark'),
                'icon'        => 'dashicons-cart',
                'description' => __('Per-product and per-category watermarks with bulk operations for all product images.', 'ultimate-watermark'),
            ],
            [
                'title'       => __('On-the-fly Display', 'ultimate-watermark'),
                'icon'        => 'dashicons-visibility',
                'description' => __('Show watermarks to visitors without modifying original files. Role-based bypass for admins.', 'ultimate-watermark'),
            ],
            [
                'title'       => __('Priority Support', 'ultimate-watermark'),
                'icon'        => 'dashicons-sos',
                'description' => __('Get fast responses from our dedicated support team and automatic plugin updates.', 'ultimate-watermark'),
            ],
        ];
    }

    /**
     * Get comparison table rows
     */
    private static function getComparisonRows(): array
    {
        return [
            [__('Text & Image Watermarks', 'ultimate-watermark'), true, true],
            [__('Position & Opacity Control', 'ultimate-watermark'), true, true],
            [__('Watermark Limit', 'ultimate-watermark') . ' (1 vs Unlimited)', false, true],
            [__('Auto-Apply on Upload', 'ultimate-watermark'), true, true],
            [__('Backup & Restore', 'ultimate-watermark'), true, true],
            [__('Dynamic Content Placeholders', 'ultimate-watermark'), false, true],
            [__('WooCommerce Integration', 'ultimate-watermark'), false, true],
            [__('On-the-fly Display', 'ultimate-watermark'), false, true],
            [__('Priority Support', 'ultimate-watermark'), false, true],
        ];
    }
}
