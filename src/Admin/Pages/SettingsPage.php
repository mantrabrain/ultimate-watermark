<?php

namespace MantraBrain\UltimateWatermark\Admin\Pages;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\Admin\Components\Layout;

/**
 * Settings Page Class
 * 
 * Handles the plugin settings page
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class SettingsPage
{
    use SingletonTrait;

    /**
     * Get setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    private function getSetting(string $key, $default = null)
    {
        $settings = get_option('ultimate_watermark_options', []);
        return $settings[$key] ?? $default;
    }

    /**
     * Render settings page
     */
    public function render(): void
    {
        $actions = '<a href="' . esc_url(admin_url('admin.php?page=ultimate-watermark-dashboard')) . '" class="btn btn-secondary">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            ' . esc_html__('Back to Dashboard', 'ultimate-watermark') . '
        </a>';

        Layout::render(
            __('Settings', 'ultimate-watermark'),
            [$this, 'renderSettingsContent'],
            [
                'subtitle' => __('Configure global plugin settings and advanced options', 'ultimate-watermark'),
                'actions' => $actions
            ]
        );
    }

    /**
     * Render settings content
     */
    public function renderSettingsContent(): void
    {
        ?>
        <div class="ultimate-watermark-settings">
            <div class="settings-layout">
                <!-- Left Side - Settings Content -->
                <div class="settings-content">
                    <form id="ultimate-watermark-settings-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('ultimate_watermark_settings', 'ultimate_watermark_settings_nonce'); ?>
                        
                        <div class="settings-sections">
                            <div class="settings-section">
                                <h3><?php esc_html_e('Backup Settings', 'ultimate-watermark'); ?></h3>
                                
                                <div class="form-columns">
                                    <div class="form-column">
                                        <div class="form-row">
                                            <label>
                                                <input type="checkbox" id="backup_image" name="backup_image" value="1" <?php checked($this->getSetting('backup_image', true)); ?>>
                                                <?php esc_html_e('Backup full size image', 'ultimate-watermark'); ?>
                                            </label>
                                            <p class="description"><?php esc_html_e('Backup the full size image before applying watermark.', 'ultimate-watermark'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="form-column">
                                        <div class="form-row">
                                            <label for="backup_quality"><?php esc_html_e('Backup Image Quality', 'ultimate-watermark'); ?></label>
                                            <div class="range-input">
                                                <input type="range" id="backup_quality" name="backup_quality" value="<?php echo esc_attr($this->getSetting('backup_quality', 90)); ?>" min="1" max="100">
                                                <span class="range-value"><?php echo esc_html($this->getSetting('backup_quality', 90)); ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="settings-section">
                                <h3><?php esc_html_e('Image Protection', 'ultimate-watermark'); ?></h3>
                                
                                <div class="form-columns">
                                    <div class="form-column">
                                        <div class="form-row">
                                            <label>
                                                <input type="checkbox" id="disable_rightclick" name="disable_rightclick" value="1">
                                                <?php esc_html_e('Disable right click on images', 'ultimate-watermark'); ?>
                                            </label>
                                            <p class="description"><?php esc_html_e('Disable right mouse click on images.', 'ultimate-watermark'); ?></p>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label>
                                                <input type="checkbox" id="disable_drag_drop" name="disable_drag_drop" value="1">
                                                <?php esc_html_e('Prevent drag and drop', 'ultimate-watermark'); ?>
                                            </label>
                                            <p class="description"><?php esc_html_e('Prevent drag and drop of images.', 'ultimate-watermark'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="form-column">
                                        <div class="form-row">
                                            <label>
                                                <input type="checkbox" id="enable_protection_logged_in" name="enable_protection_logged_in" value="1">
                                                <?php esc_html_e('Enable protection for logged-in users', 'ultimate-watermark'); ?>
                                            </label>
                                            <p class="description"><?php esc_html_e('Enable image protection for logged-in users also.', 'ultimate-watermark'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <div class="actions-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-dashboard')); ?>" class="btn btn-secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php esc_html_e('Back to Dashboard', 'ultimate-watermark'); ?>
                    </a>
                </div>
                <div class="actions-right">
                    <button type="submit" form="ultimate-watermark-settings-form" class="btn btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'ultimate-watermark'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}