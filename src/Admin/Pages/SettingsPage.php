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
     * Get settings configuration
     * 
     * @return array
     */
    private function getSettingsConfig(): array
    {
        $config = [
            'backup' => [
                'title' => __('Backup Settings', 'ultimate-watermark'),
                'fields' => [
                    'backup_image' => [
                        'type' => 'checkbox',
                        'label' => __('Enable backup functionality', 'ultimate-watermark'),
                        'description' => __('Enable backup functionality before applying watermarks.', 'ultimate-watermark'),
                        'default' => '0',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            return in_array($value, ['0', '1']) ? $value : '0';
                        }
                    ],
                    'backup_strategy' => [
                        'type' => 'select',
                        'label' => __('Backup Strategy', 'ultimate-watermark'),
                        'description' => __('Choose which images to backup: full size only or full size plus the sizes that had watermarks applied.', 'ultimate-watermark'),
                        'default' => 'full_size',
                        'options' => [
                            'full_size' => __('Backup full size image only', 'ultimate-watermark'),
                            'watermarked_sizes' => __('Backup full size + watermarked sizes', 'ultimate-watermark')
                        ],
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            return in_array($value, ['full_size', 'watermarked_sizes']) ? $value : 'full_size';
                        },
                        'conditional' => [
                            'target' => 'backup_image',
                            'show_when' => '1'
                        ]
                    ],
                    'backup_quality' => [
                        'type' => 'range',
                        'label' => __('Backup Image Quality', 'ultimate-watermark'),
                        'default' => 90,
                        'min' => 1,
                        'max' => 100,
                        'suffix' => '%',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => function($value) {
                            $value = absint($value);
                            return ($value >= 1 && $value <= 100) ? $value : 90;
                        }
                    ]
                ]
            ],
            'protection' => [
                'title' => __('Image Protection', 'ultimate-watermark'),
                'fields' => [
                    'disable_rightclick' => [
                        'type' => 'checkbox',
                        'label' => __('Disable right click on images', 'ultimate-watermark'),
                        'description' => __('Disable right mouse click on images.', 'ultimate-watermark'),
                        'default' => '0',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            return in_array($value, ['0', '1']) ? $value : '0';
                        }
                    ],
                    'disable_drag_drop' => [
                        'type' => 'checkbox',
                        'label' => __('Prevent drag and drop', 'ultimate-watermark'),
                        'description' => __('Prevent drag and drop of images.', 'ultimate-watermark'),
                        'default' => '0',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            return in_array($value, ['0', '1']) ? $value : '0';
                        }
                    ],
                    'enable_protection_logged_in' => [
                        'type' => 'checkbox',
                        'label' => __('Enable protection for logged-in users', 'ultimate-watermark'),
                        'description' => __('Enable image protection for logged-in users also.', 'ultimate-watermark'),
                        'default' => '0',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function($value) {
                            return in_array($value, ['0', '1']) ? $value : '0';
                        }
                    ]
                ]
            ]
        ];

        // Allow Pro version to extend settings
        $config = apply_filters('ultimate_watermark_settings_config', $config);

        return $config;
    }

    /**
     * Get all field keys from settings configuration
     * 
     * @return array
     */
    public function getAllFieldKeys(): array
    {
        $config = $this->getSettingsConfig();
        $field_keys = [];
        
        foreach ($config as $section) {
            foreach ($section['fields'] as $field_key => $field) {
                $field_keys[] = $field_key;
            }
        }
        
        return $field_keys;
    }

    /**
     * Get field configuration by key
     * 
     * @param string $field_key
     * @return array|null
     */
    public function getFieldConfig(string $field_key): ?array
    {
        $config = $this->getSettingsConfig();
        
        foreach ($config as $section) {
            if (isset($section['fields'][$field_key])) {
                return $section['fields'][$field_key];
            }
        }
        
        return null;
    }

    /**
     * Sanitize field value
     * 
     * @param string $field_key
     * @param mixed $value
     * @return mixed
     */
    public function sanitizeFieldValue(string $field_key, $value)
    {
        $field_config = $this->getFieldConfig($field_key);
        
        if (!$field_config) {
            return $value;
        }
        
        // Apply sanitize callback if exists
        if (isset($field_config['sanitize_callback']) && is_callable($field_config['sanitize_callback'])) {
            $value = call_user_func($field_config['sanitize_callback'], $value);
        }
        
        // Apply validate callback if exists
        if (isset($field_config['validate_callback']) && is_callable($field_config['validate_callback'])) {
            $value = call_user_func($field_config['validate_callback'], $value);
        }
        
        return $value;
    }

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
        $settings_config = $this->getSettingsConfig();
        ?>
        <div class="ultimate-watermark-settings">
            <div class="settings-layout">
                <!-- Left Side - Settings Content -->
                <div class="settings-content">
                    <form id="ultimate-watermark-settings-form" method="post" action="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-settings')); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('ultimate_watermark_settings', 'ultimate_watermark_settings_nonce'); ?>
                        
                        <div class="settings-sections">
                            <?php foreach ($settings_config as $section_key => $section): ?>
                                <div class="settings-section">
                                    <h3><?php echo esc_html($section['title']); ?></h3>
                                    
                                    <div class="form-columns">
                                        <?php 
                                        $column_count = 0;
                                        $fields_per_column = ceil(count($section['fields']) / 2);
                                        $current_column = 0;
                                        ?>
                                        
                                        <?php foreach ($section['fields'] as $field_key => $field): ?>
                                            <?php if ($column_count % $fields_per_column === 0): ?>
                                                <div class="form-column">
                                            <?php endif; ?>
                                            
                                            <div class="form-row" <?php echo $this->getFieldConditionalAttributes($field); ?>>
                                                <?php $this->renderField($field_key, $field); ?>
                                            </div>
                                            
                                            <?php 
                                            $column_count++;
                                            if ($column_count % $fields_per_column === 0): ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        
                                        <?php if ($column_count % $fields_per_column !== 0): ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php $this->renderConditionalJavaScript($settings_config); ?>
        });
        </script>
        <?php
    }

    /**
     * Render individual field
     */
    private function renderField(string $field_key, array $field): void
    {
        $value = $this->getSetting($field_key, $field['default'] ?? '');
        
        switch ($field['type']) {
            case 'checkbox':
                $this->renderCheckboxField($field_key, $field, $value);
                break;
            case 'select':
                $this->renderSelectField($field_key, $field, $value);
                break;
            case 'range':
                $this->renderRangeField($field_key, $field, $value);
                break;
            case 'text':
                $this->renderTextField($field_key, $field, $value);
                break;
            case 'textarea':
                $this->renderTextareaField($field_key, $field, $value);
                break;
        }
    }

    /**
     * Render checkbox field
     */
    private function renderCheckboxField(string $field_key, array $field, $value): void
    {
        ?>
        <label>
            <input type="checkbox" id="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($field_key); ?>" value="1" <?php checked($value, '1'); ?>>
            <?php echo esc_html($field['label']); ?>
        </label>
        <?php if (!empty($field['description'])): ?>
            <p class="description"><?php echo esc_html($field['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render select field
     */
    private function renderSelectField(string $field_key, array $field, $value): void
    {
        ?>
        <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field['label']); ?></label>
        <select id="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($field_key); ?>">
            <?php foreach ($field['options'] as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($field['description'])): ?>
            <p class="description"><?php echo esc_html($field['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render range field
     */
    private function renderRangeField(string $field_key, array $field, $value): void
    {
        ?>
        <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field['label']); ?></label>
        <div class="range-input">
            <input type="range" id="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($field_key); ?>" 
                   value="<?php echo esc_attr($value); ?>" 
                   min="<?php echo esc_attr($field['min'] ?? 0); ?>" 
                   max="<?php echo esc_attr($field['max'] ?? 100); ?>">
            <span class="range-value"><?php echo esc_html($value); ?><?php echo esc_html($field['suffix'] ?? ''); ?></span>
        </div>
        <?php if (!empty($field['description'])): ?>
            <p class="description"><?php echo esc_html($field['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render text field
     */
    private function renderTextField(string $field_key, array $field, $value): void
    {
        ?>
        <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field['label']); ?></label>
        <input type="text" id="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($field_key); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               <?php echo !empty($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : ''; ?>>
        <?php if (!empty($field['description'])): ?>
            <p class="description"><?php echo esc_html($field['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render textarea field
     */
    private function renderTextareaField(string $field_key, array $field, $value): void
    {
        ?>
        <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field['label']); ?></label>
        <textarea id="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($field_key); ?>" 
                  rows="<?php echo esc_attr($field['rows'] ?? 4); ?>"
                  <?php echo !empty($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : ''; ?>><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($field['description'])): ?>
            <p class="description"><?php echo esc_html($field['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Get conditional attributes for field
     */
    private function getFieldConditionalAttributes(array $field): string
    {
        if (empty($field['conditional'])) {
            return '';
        }

        $conditional = $field['conditional'];
        $target_value = $this->getSetting($conditional['target'], '0');
        $should_show = ($target_value === $conditional['show_when']);
        
        return $should_show ? '' : 'style="display: none;"';
    }

    /**
     * Render conditional JavaScript
     */
    private function renderConditionalJavaScript(array $settings_config): void
    {
        $conditional_fields = [];
        
        foreach ($settings_config as $section) {
            foreach ($section['fields'] as $field_key => $field) {
                if (!empty($field['conditional'])) {
                    $conditional_fields[] = [
                        'field' => $field_key,
                        'target' => $field['conditional']['target'],
                        'show_when' => $field['conditional']['show_when']
                    ];
                }
            }
        }

        if (!empty($conditional_fields)) {
            ?>
            // Conditional field logic
            <?php foreach ($conditional_fields as $conditional): ?>
                const <?php echo esc_js($conditional['field']); ?>Field = document.getElementById('<?php echo esc_js($conditional['field']); ?>');
                const <?php echo esc_js($conditional['target']); ?>Field = document.getElementById('<?php echo esc_js($conditional['target']); ?>');
                
                function toggle<?php echo esc_js(ucfirst($conditional['field'])); ?>() {
                    if (<?php echo esc_js($conditional['target']); ?>Field.checked && <?php echo esc_js($conditional['target']); ?>Field.value === '<?php echo esc_js($conditional['show_when']); ?>') {
                        <?php echo esc_js($conditional['field']); ?>Field.closest('.form-row').style.display = 'block';
                    } else {
                        <?php echo esc_js($conditional['field']); ?>Field.closest('.form-row').style.display = 'none';
                    }
                }
                
                // Initial state
                toggle<?php echo esc_js(ucfirst($conditional['field'])); ?>();
                
                // Toggle on change
                <?php echo esc_js($conditional['target']); ?>Field.addEventListener('change', toggle<?php echo esc_js(ucfirst($conditional['field'])); ?>);
            <?php endforeach; ?>
            <?php
        }
    }
    
}