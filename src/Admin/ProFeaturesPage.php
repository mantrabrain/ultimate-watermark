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
                <a href="<?php echo esc_url('https://store.mantrabrain.com'); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero">
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
                                <td><?php echo $row[1] ? '<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>' : '<span class="dashicons dashicons-minus" style="color:#ddd;"></span>'; ?></td>
                                <td><?php echo $row[2] ? '<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>' : '<span class="dashicons dashicons-minus" style="color:#ddd;"></span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
            .ulwm-pro-features-page {
                max-width: 1200px;
                margin: 20px auto;
            }
            .ulwm-pro-hero {
                text-align: center;
                padding: 40px 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 8px;
                margin-bottom: 40px;
            }
            .ulwm-pro-hero h1 {
                color: white;
                font-size: 32px;
                margin-bottom: 10px;
            }
            .ulwm-pro-subtitle {
                font-size: 18px;
                opacity: 0.9;
            }
            .ulwm-pro-features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
            }
            .ulwm-pro-feature-card {
                background: white;
                padding: 30px;
                border-radius: 8px;
                border: 1px solid #ddd;
                text-align: center;
            }
            .ulwm-pro-feature-icon {
                width: 60px;
                height: 60px;
                margin: 0 auto 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .ulwm-pro-feature-icon .dashicons {
                font-size: 30px;
                color: white;
            }
            .ulwm-pro-feature-card h3 {
                margin-bottom: 10px;
                font-size: 18px;
            }
            .ulwm-pro-cta {
                text-align: center;
                padding: 60px 20px;
                background: #f9f9f9;
                border-radius: 8px;
                margin-bottom: 40px;
            }
            .ulwm-pro-cta h2 {
                margin-bottom: 10px;
            }
            .ulwm-pro-cta p {
                font-size: 16px;
                margin-bottom: 20px;
            }
            .ulwm-pro-comparison {
                background: white;
                padding: 30px;
                border-radius: 8px;
                border: 1px solid #ddd;
            }
            .ulwm-pro-comparison h2 {
                text-align: center;
                margin-bottom: 20px;
            }
            .ulwm-pro-comparison table {
                margin-top: 20px;
            }
            .ulwm-pro-comparison th {
                text-align: center;
            }
            .ulwm-pro-comparison td {
                text-align: center;
                padding: 12px;
            }
        </style>
        <?php
    }

    /**
     * Get feature definitions
     */
    private static function getFeatures(): array
    {
        return [
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
                'title'       => __('Batch Processing', 'ultimate-watermark'),
                'icon'        => 'dashicons-images-alt2',
                'description' => __('Apply or remove watermarks across your entire media library with progress tracking.', 'ultimate-watermark'),
            ],
            [
                'title'       => __('Template Library', 'ultimate-watermark'),
                'icon'        => 'dashicons-layout',
                'description' => __('20+ pre-built watermark templates for photography, e-commerce, and business use.', 'ultimate-watermark'),
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
            [__('Multiple Watermark Profiles', 'ultimate-watermark'), true, true],
            [__('Auto-Apply on Upload', 'ultimate-watermark'), true, true],
            [__('Backup & Restore', 'ultimate-watermark'), true, true],
            [__('Dynamic Content Placeholders', 'ultimate-watermark'), false, true],
            [__('WooCommerce Integration', 'ultimate-watermark'), false, true],
            [__('On-the-fly Display', 'ultimate-watermark'), false, true],
            [__('Batch Processing', 'ultimate-watermark'), false, true],
            [__('Template Library', 'ultimate-watermark'), false, true],
            [__('Priority Support', 'ultimate-watermark'), false, true],
        ];
    }
}
