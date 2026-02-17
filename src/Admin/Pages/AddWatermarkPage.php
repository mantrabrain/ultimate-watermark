<?php

namespace MantraBrain\UltimateWatermark\Admin\Pages;

use MantraBrain\UltimateWatermark\Core\Traits\SingletonTrait;
use MantraBrain\UltimateWatermark\Admin\Components\Layout;
use MantraBrain\UltimateWatermark\PostTypes\WatermarkPostType;

/**
 * Add Watermark Page Class
 * 
 * Handles the add watermark page with form and live preview
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class AddWatermarkPage
{
    use SingletonTrait;

    /**
     * Initialize the add watermark page
     */
    public function init()
    {
        //add_action('save_post', [$this, 'saveWatermarkData']);
    }

    /**
     * Render add watermark page
     */
    public function render(): void
    {
        $watermark_id = isset($_GET['ID']) ? intval($_GET['ID']) : 0;
        $is_edit_mode = $watermark_id > 0;
        $watermark_data = $is_edit_mode ? $this->getWatermarkData($watermark_id) : null;
        
        $page_title = $is_edit_mode ? __('Edit Watermark', 'ultimate-watermark') : __('Add New Watermark', 'ultimate-watermark');
        $page_subtitle = $is_edit_mode ? __('Edit your watermark template with live preview', 'ultimate-watermark') : __('Create a new watermark template with live preview', 'ultimate-watermark');
        
        $actions = '<a href="' . esc_url(admin_url('admin.php?page=ultimate-watermark-watermarks')) . '" class="btn btn-secondary">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            ' . esc_html__('Back to Watermarks', 'ultimate-watermark') . '
        </a>';

        Layout::render(
            $page_title,
            [$this, 'renderAddWatermarkContent'],
            [
                'subtitle' => $page_subtitle,
                'actions' => $actions,
                'watermark_data' => $watermark_data,
                'is_edit_mode' => $is_edit_mode
            ]
        );
    }

    /**
     * Render add watermark content
     */
    public function renderAddWatermarkContent($args = []): void
    {
        $watermark_id = isset($_GET['ID']) ? intval($_GET['ID']) : 0;
        $is_edit_mode = $watermark_id > 0;
        $watermark_data = $is_edit_mode ? $this->getWatermarkData($watermark_id) : null;
        
        
        $tabs_config = $this->getFormTabsConfig();
        ?>
        <div class="ultimate-watermark-add-watermark">
            <div class="add-watermark-layout">
                <!-- Left Side - Form Content -->
                <div class="form-content">
                    <form id="ultimate-watermark-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('ultimate_watermark_nonce', 'ultimate_watermark_nonce'); ?>
                        <input type="hidden" name="action" value="ultimate_watermark_save">
                        <input type="hidden" name="watermark_id" id="watermark_id" value="<?php echo esc_attr($watermark_id); ?>">
                        
                        <?php $this->renderFormTabs($tabs_config, $watermark_data); ?>

                        <?php $this->renderFormContent($tabs_config, $watermark_data); ?>
                        

                    </form>
                </div>

                <!-- Right Side - Preview -->
                <div class="preview-sidebar">
                    <div class="preview-header">
                        <h4><?php esc_html_e('Live Preview', 'ultimate-watermark'); ?></h4>
                        <div class="preview-library-indicator">
                            <?php echo $this->getLibraryIndicator(); ?>
                        </div>
                    </div>
                    
                    <div class="preview-image-container">
                        <div class="preview-image">
                            <img id="ultimate-watermark-preview-image" src="<?php echo esc_url(ULTIMATE_WATERMARK_URL . 'assets/images/preview-image.jpg'); ?>" alt="Preview">
                            <div id="ultimate-watermark-preview-watermark" class="preview-watermark-overlay"></div>
                            <div id="preview-loading" class="preview-loading" style="display: none;">
                                <div class="loading-spinner"></div>
                                <span>Generating preview...</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-stats">
                        <div class="preview-stat">
                            <div class="preview-stat-label"><?php esc_html_e('Position', 'ultimate-watermark'); ?></div>
                            <div class="preview-stat-value" id="preview-position"><?php esc_html_e('Bottom Right', 'ultimate-watermark'); ?></div>
                        </div>
                        <div class="preview-stat">
                            <div class="preview-stat-label"><?php esc_html_e('Opacity', 'ultimate-watermark'); ?></div>
                            <div class="preview-stat-value" id="preview-opacity">50%</div>
                        </div>
                        <div class="preview-stat">
                            <div class="preview-stat-label"><?php esc_html_e('Size', 'ultimate-watermark'); ?></div>
                            <div class="preview-stat-value" id="preview-size">24px</div>
                        </div>
                        <div class="preview-stat">
                            <div class="preview-stat-label"><?php esc_html_e('Rotation', 'ultimate-watermark'); ?></div>
                            <div class="preview-stat-value" id="preview-rotation">0°</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions - Outside Layout -->
            <div class="form-actions">
                <div class="actions-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-watermark-watermarks')); ?>" class="btn btn-secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php esc_html_e('Back to Watermarks', 'ultimate-watermark'); ?>
                    </a>
                </div>
                <div class="actions-right">
                    <button type="button" class="btn btn-secondary" id="save-draft">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Draft', 'ultimate-watermark'); ?>
                    </button>
                    <button type="submit" form="ultimate-watermark-form" class="btn btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php echo $is_edit_mode ? esc_html__('Update Watermark', 'ultimate-watermark') : esc_html__('Create Watermark', 'ultimate-watermark'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render form tabs
     */
    private function renderFormTabs($tabs_config, $watermark_data)
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'basic';
        $current_url = admin_url('admin.php?page=ultimate-watermark-add-watermark');
        if (isset($_GET['ID'])) {
            $current_url = add_query_arg('ID', intval($_GET['ID']), $current_url);
        }
        ?>
        <div class="form-tabs">
            <?php foreach ($tabs_config as $tab_id => $tab_config): ?>
                <?php 
                $tab_url = add_query_arg('tab', $tab_id, $current_url);
                $is_active = ($current_tab === $tab_id);
                ?>
                <a href="<?php echo esc_url($tab_url); ?>" class="form-tab <?php echo $is_active ? 'active' : ''; ?>" data-tab="<?php echo esc_attr($tab_id); ?>">
                    <span class="dashicons <?php echo esc_attr($tab_config['icon']); ?>"></span>
                    <?php echo esc_html($tab_config['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render form content
     */
    private function renderFormContent($tabs_config, $watermark_data)
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'basic';
        ?>
        <?php foreach ($tabs_config as $tab_id => $tab_config): ?>
            <?php $is_active = ($current_tab === $tab_id); ?>
            <div class="form-tab-content <?php echo $is_active ? 'active' : ''; ?>" id="tab-<?php echo esc_attr($tab_id); ?>">
                <?php foreach ($tab_config['sections'] as $section_id => $section_config): ?>
                    <?php 
                    ?>
                    <div class="form-section" id="<?php echo esc_attr($section_id); ?>" <?php if (isset($section_config['condition'])): ?>data-condition="<?php echo esc_attr($section_config['condition']); ?>"<?php endif; ?>>
                        <h4><?php echo wp_kses_post($section_config['label']); ?></h4>
                        <?php if (isset($section_config['description'])): ?>
                            <p class="description"><?php echo esc_html($section_config['description']); ?></p>
                        <?php endif; ?>
                        
                        <?php $this->renderSectionFields($section_config['fields'], $watermark_data); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php
    }

    /**
     * Render section fields
     */
    private function renderSectionFields($fields, $watermark_data)
    {
        // Special handling for position settings section
        if (isset($fields['watermark_position']) && isset($fields['watermark_rotation']) && isset($fields['watermark_opacity'])) {
            $this->renderPositionSettingsFields($fields, $watermark_data);
            return;
        }
        
        // Group fields by column, but keep conditional fields with their parent
        $left_fields = [];
        $center_fields = [];
        $right_fields = [];
        $full_width_fields = [];
        $conditional_fields = [];
        
        foreach ($fields as $field_name => $field_config) {
            if (isset($field_config['condition'])) {
                // Store conditional fields separately
                $conditional_fields[$field_name] = $field_config;
            } elseif (isset($field_config['column'])) {
                if ($field_config['column'] === 'left') {
                    $left_fields[$field_name] = $field_config;
                } elseif ($field_config['column'] === 'center') {
                    $center_fields[$field_name] = $field_config;
                } elseif ($field_config['column'] === 'right') {
                    $right_fields[$field_name] = $field_config;
                }
            } else {
                $full_width_fields[$field_name] = $field_config;
            }
        }
        
        // If we have both column-specified fields and full-width fields, 
        // render full-width fields first, then column fields, then conditional fields
        $has_column_fields = !empty($left_fields) || !empty($center_fields) || !empty($right_fields);
        $has_full_width_fields = !empty($full_width_fields);
        $has_conditional_fields = !empty($conditional_fields);
        
        ?>
        <div class="form-columns">
            <?php if ($has_full_width_fields): ?>
                <!-- Render full-width fields first -->
                <?php $column_count = 0; ?>
                <?php foreach ($full_width_fields as $field_name => $field_config): ?>
                    <?php if ($column_count % 2 === 0): ?>
                        <div class="form-column">
                    <?php endif; ?>
                    
                    <div class="form-row" <?php echo isset($field_config['condition']) ? 'data-condition="' . esc_attr($field_config['condition']) . '"' : ''; ?>>
                        <?php $this->renderField($field_name, $field_config, $watermark_data); ?>
                    </div>
                    
                    <?php $column_count++; ?>
                    <?php if ($column_count % 2 === 0): ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if ($column_count % 2 !== 0): ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($has_column_fields): ?>
                <!-- Three-column layout for specified fields -->
                <div class="form-column">
                    <?php foreach ($left_fields as $field_name => $field_config): ?>
                        <div class="form-row" <?php echo isset($field_config['condition']) ? 'data-condition="' . esc_attr($field_config['condition']) . '"' : ''; ?>>
                            <?php $this->renderField($field_name, $field_config, $watermark_data); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-column">
                    <?php foreach ($center_fields as $field_name => $field_config): ?>
                        <div class="form-row" <?php echo isset($field_config['condition']) ? 'data-condition="' . esc_attr($field_config['condition']) . '"' : ''; ?>>
                            <?php $this->renderField($field_name, $field_config, $watermark_data); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-column">
                    <?php foreach ($right_fields as $field_name => $field_config): ?>
                        <div class="form-row" <?php echo isset($field_config['condition']) ? 'data-condition="' . esc_attr($field_config['condition']) . '"' : ''; ?>>
                            <?php $this->renderField($field_name, $field_config, $watermark_data); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($has_conditional_fields): ?>
                <!-- Conditional fields - render after all other fields -->
                <?php foreach ($conditional_fields as $field_name => $field_config): ?>
                    <div class="form-row" data-condition="<?php echo esc_attr($field_config['condition']); ?>">
                        <?php 
                        // Rendering conditional field
                        $this->renderField($field_name, $field_config, $watermark_data); 
                        ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render individual field
     */
    private function renderField($field_name, $field_config, $watermark_data)
    {
        $value = $watermark_data ? ($watermark_data[$field_name] ?? $field_config['default'] ?? '') : ($field_config['default'] ?? '');
        
        
        switch ($field_config['type']) {
            case 'text':
                $this->renderTextField($field_name, $field_config, $value);
                break;
            case 'textarea':
                $this->renderTextareaField($field_name, $field_config, $value);
                break;
            case 'number':
                $this->renderNumberField($field_name, $field_config, $value);
                break;
            case 'range':
                $this->renderRangeField($field_name, $field_config, $value);
                break;
            case 'select':
                $this->renderSelectField($field_name, $field_config, $value);
                break;
            case 'radio':
                $this->renderRadioField($field_name, $field_config, $value);
                break;
            case 'checkbox':
                $this->renderCheckboxField($field_name, $field_config, $value);
                break;
            case 'checkbox_group':
                $this->renderCheckboxGroupField($field_name, $field_config, $value);
                break;
            case 'color':
                $this->renderColorField($field_name, $field_config, $value);
                break;
            case 'media':
                $this->renderMediaField($field_name, $field_config, $value);
                break;
            case 'position_selector':
                $this->renderPositionSelectorField($field_name, $field_config, $value);
                break;
            case 'custom':
                if (isset($field_config['custom_render']) && is_callable($field_config['custom_render'])) {
                    call_user_func($field_config['custom_render']);
                }
                break;
        }
    }

    /**
     * Render text field
     */
    private function renderTextField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <input type="text" 
               id="<?php echo esc_attr($field_name); ?>" 
               name="<?php echo esc_attr($field_name); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="<?php echo esc_attr($field_config['placeholder'] ?? ''); ?>"
               <?php echo isset($field_config['readonly']) && $field_config['readonly'] ? 'readonly' : ''; ?>
               <?php echo isset($field_config['required']) && $field_config['required'] ? 'required' : ''; ?>>
        <?php if (isset($field_config['description'])): ?>
            <p class="description"><?php echo wp_kses_post($field_config['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render textarea field
     */
    private function renderTextareaField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <div style="position: relative;">
            <textarea id="<?php echo esc_attr($field_name); ?>" 
                      name="<?php echo esc_attr($field_name); ?>" 
                      placeholder="<?php echo esc_attr($field_config['placeholder'] ?? ''); ?>"
                      rows="<?php echo esc_attr($field_config['rows'] ?? 3); ?>"><?php echo esc_textarea($value); ?></textarea>
            <?php 
            // Allow Pro plugin to add inline controls (e.g., placeholder selector)
            do_action('ultimate_watermark_after_textarea_field', $field_name, $field_config, $value);
            ?>
        </div>
        <?php if (isset($field_config['description'])): ?>
            <p class="description"><?php echo wp_kses_post($field_config['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render number field
     */
    private function renderNumberField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <input type="number" 
               id="<?php echo esc_attr($field_name); ?>" 
               name="<?php echo esc_attr($field_name); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               min="<?php echo esc_attr($field_config['min'] ?? ''); ?>"
               max="<?php echo esc_attr($field_config['max'] ?? ''); ?>">
        <?php if (isset($field_config['unit'])): ?>
            <span class="unit"><?php echo esc_html($field_config['unit']); ?></span>
        <?php endif; ?>
        <?php
    }

    /**
     * Render range field
     */
    private function renderRangeField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <div class="range-input">
            <input type="range" 
                   id="<?php echo esc_attr($field_name); ?>" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="<?php echo esc_attr($value); ?>" 
                   min="<?php echo esc_attr($field_config['min'] ?? 0); ?>"
                   max="<?php echo esc_attr($field_config['max'] ?? 100); ?>">
            <span class="range-value"><?php echo esc_html($value); ?></span>
        </div>
        <?php if (isset($field_config['description'])): ?>
            <p class="description"><?php echo esc_html($field_config['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render select field
     */
    private function renderSelectField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <select id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>">
            <?php foreach ($field_config['options'] as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($field_config['description'])): ?>
            <p class="description"><?php echo esc_html($field_config['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render radio field
     */
    private function renderRadioField($field_name, $field_config, $value)
    {
        ?>
        <div class="type-selection inline">
            <?php foreach ($field_config['options'] as $option_value => $option_config): ?>
                <label class="type-option inline">
                    <input type="radio" 
                           name="<?php echo esc_attr($field_name); ?>" 
                           value="<?php echo esc_attr($option_value); ?>" 
                           <?php checked($value, $option_value); ?>>
                    <div class="type-card">
                        <span class="dashicons <?php echo esc_attr($option_config['icon']); ?>"></span>
                        <span><?php echo wp_kses_post($option_config['label']); ?></span>
                        <p><?php echo esc_html($option_config['description']); ?></p>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render checkbox field
     */
    private function renderCheckboxField($field_name, $field_config, $value)
    {
        ?>
        <label>
            <input type="checkbox" 
                   id="<?php echo esc_attr($field_name); ?>" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="1" 
                   <?php checked($value, '1'); ?>>
            <?php echo esc_html($field_config['label']); ?>
        </label>
        <?php if (isset($field_config['description'])): ?>
            <p class="description"><?php echo esc_html($field_config['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render checkbox group field
     */
    private function renderCheckboxGroupField($field_name, $field_config, $value)
    {
        ?>
        <label><?php echo esc_html($field_config['label']); ?></label>
        <div class="checkbox-group">
            <?php foreach ($field_config['options'] as $option_value => $option_label): ?>
                <label>
                    <input type="checkbox" 
                           name="<?php echo esc_attr($field_name); ?>[]" 
                           value="<?php echo esc_attr($option_value); ?>" 
                           <?php echo is_array($value) && in_array($option_value, $value) ? 'checked' : ''; ?>>
                    <?php echo esc_html($option_label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (isset($field_config['description'])): ?>
            <p class="description"><?php echo esc_html($field_config['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render color field
     */
    private function renderColorField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <input type="color" 
               id="<?php echo esc_attr($field_name); ?>" 
               name="<?php echo esc_attr($field_name); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="color-picker">
        <?php
    }

    /**
     * Render media field
     */
    private function renderMediaField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <div class="image-upload-layout">
            <input type="hidden" id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($value); ?>">
            
            <!-- Left side - Upload area -->
            <div class="upload-section">
                <div class="upload-area" id="watermark-upload-area">
                    <span class="dashicons dashicons-upload"></span>
                    <span><?php esc_html_e('Click to select from Media Library', 'ultimate-watermark'); ?></span>
                    <p><?php echo esc_html($field_config['description'] ?? ''); ?></p>
                </div>
            </div>
            
            <!-- Right side - Image preview -->
            <div class="preview-section">
                <div class="image-preview" id="watermark-image-preview">
                    <?php if ($value): ?>
                        <?php 
                        $image_url = wp_get_attachment_url($value);
                        if ($image_url): ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="Preview">
                        <?php else: ?>
                            <p>Image not found (ID: <?php echo esc_html($value); ?>)</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No image selected</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get library indicator for preview
     *
     * @return string
     */
    private function getLibraryIndicator(): string
    {
        if (extension_loaded('imagick')) {
            return '<span class="library-indicator imagick" title="Using ImageMagick library">Imagick</span>';
        } elseif (extension_loaded('gd')) {
            return '<span class="library-indicator gd" title="Using GD library">GD</span>';
        } else {
            return '<span class="library-indicator none" title="No image library available">None</span>';
        }
    }

    /**
     * Get form tabs configuration
     *
     * @return array
     */
    public function getFormTabsConfig()
    {
        $config = [
            'basic' => [
                'label' => __('General', 'ultimate-watermark'),
                'icon' => 'dashicons-admin-settings',
                'sections' => [
                    'watermark_type' => [
                        'label' => __('Watermark Type', 'ultimate-watermark'),
                        'fields' => [
                            'watermark_type' => [
                                'type' => 'radio',
                                'label' => '',
                                'default' => 'text',
                                'options' => apply_filters('ultimate_watermark_type_options', [
                                    'text' => [
                                        'label' => __('Text Watermark', 'ultimate-watermark'),
                                        'description' => __('Add text-based watermarks', 'ultimate-watermark'),
                                        'icon' => 'dashicons-format-text'
                                    ],
                                    'image' => [
                                        'label' => __('Image Watermark', 'ultimate-watermark'),
                                        'description' => __('Upload image watermarks', 'ultimate-watermark'),
                                        'icon' => 'dashicons-format-image'
                                    ]
                                ]),
                                'sanitize_callback' => [$this, 'sanitizeWatermarkType'],
                                'validate_callback' => [$this, 'validateWatermarkType']
                            ]
                        ]
                    ],
                    'text_settings' => [
                        'label' => __('Text Settings', 'ultimate-watermark'),
                        'condition' => 'watermark_type === "text"',
                        'fields' => apply_filters('ultimate_watermark_text_settings_fields', [
                            'watermark_text' => [
                                'type' => 'textarea',
                                'label' => __('Watermark Text', 'ultimate-watermark'),
                                'placeholder' => __('Enter watermark text', 'ultimate-watermark'),
                                'default' => '© ' . get_bloginfo('name'),
                                'rows' => 3,
                                'sanitize_callback' => [$this, 'sanitizeText'],
                                'validate_callback' => [$this, 'validateRequired']
                            ],
                            'watermark_font_size' => [
                                'type' => 'number',
                                'label' => __('Font Size', 'ultimate-watermark'),
                                'default' => 24,
                                'min' => 8,
                                'max' => 72,
                                'unit' => 'px',
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'watermark_color' => [
                                'type' => 'color',
                                'label' => __('Text Color', 'ultimate-watermark'),
                                'default' => '#ffffff',
                                'sanitize_callback' => [$this, 'sanitizeColor'],
                                'validate_callback' => [$this, 'validateColor']
                            ],
                            'watermark_font_family' => [
                                'type' => 'select',
                                'label' => __('Font Family', 'ultimate-watermark'),
                                'default' => 'Arial',
                                'options' => [
                                    'Arial' => 'Arial',
                                    'Helvetica' => 'Helvetica',
                                    'Times New Roman' => 'Times New Roman',
                                    'Georgia' => 'Georgia',
                                    'Verdana' => 'Verdana',
                                    'Courier New' => 'Courier New'
                                ],
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ],
                            'watermark_font_weight' => [
                                'type' => 'select',
                                'label' => __('Font Weight', 'ultimate-watermark'),
                                'default' => 'normal',
                                'options' => [
                                    'normal' => __('Normal', 'ultimate-watermark'),
                                    'bold' => __('Bold', 'ultimate-watermark'),
                                    'lighter' => __('Light', 'ultimate-watermark')
                                ],
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ],
                            'watermark_font_style' => [
                                'type' => 'select',
                                'label' => __('Font Style', 'ultimate-watermark'),
                                'default' => 'normal',
                                'options' => [
                                    'normal' => __('Normal', 'ultimate-watermark'),
                                    'italic' => __('Italic', 'ultimate-watermark'),
                                    'oblique' => __('Oblique', 'ultimate-watermark')
                                ],
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ],
                            'watermark_text_decoration' => [
                                'type' => 'select',
                                'label' => __('Text Decoration', 'ultimate-watermark'),
                                'default' => 'none',
                                'options' => [
                                    'none' => __('None', 'ultimate-watermark'),
                                    'underline' => __('Underline', 'ultimate-watermark'),
                                    'overline' => __('Overline', 'ultimate-watermark'),
                                    'line-through' => __('Line Through', 'ultimate-watermark')
                                ],
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ]
                        ])
                    ],
                    'image_upload' => [
                        'label' => __('Watermark Image', 'ultimate-watermark'),
                        'condition' => 'watermark_type === "image"',
                        'fields' => [
                            'watermark_image_id' => [
                                'type' => 'media',
                                'label' => __('Select Watermark Image', 'ultimate-watermark'),
                                'description' => __('Recommended: PNG with transparency', 'ultimate-watermark'),
                                'sanitize_callback' => [$this, 'sanitizeMediaId'],
                                'validate_callback' => [$this, 'validateMediaId']
                            ]
                        ]
                    ],
                    'image_settings' => [
                        'label' => __('Image Settings', 'ultimate-watermark'),
                        'condition' => 'watermark_type === "image"',
                        'fields' => [
                            'watermark_size_type' => [
                                'type' => 'select',
                                'label' => __('Watermark Size Type', 'ultimate-watermark'),
                                'default' => 'scaled',
                                'options' => [
                                    'original' => __('Original Size', 'ultimate-watermark'),
                                    'custom' => __('Custom Size', 'ultimate-watermark'),
                                    'scaled' => __('Scaled', 'ultimate-watermark')
                                ],
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ],
                            'watermark_custom_width' => [
                                'type' => 'number',
                                'label' => __('Custom Width (px)', 'ultimate-watermark'),
                                'default' => 100,
                                'min' => 10,
                                'max' => 1000,
                                'condition' => 'watermark_size_type === "custom"',
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'watermark_custom_height' => [
                                'type' => 'number',
                                'label' => __('Custom Height (px)', 'ultimate-watermark'),
                                'default' => 100,
                                'min' => 10,
                                'max' => 1000,
                                'condition' => 'watermark_size_type === "custom"',
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'watermark_scale_percentage' => [
                                'type' => 'range',
                                'label' => __('Watermark scale', 'ultimate-watermark'),
                                'default' => 80,
                                'min' => 1,
                                'max' => 100,
                                'condition' => 'watermark_size_type === "scaled"',
                                'description' => __('Enter a number ranging from 0 to 100. 100 makes width of watermark image equal to width of the image it is applied to.', 'ultimate-watermark'),
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'watermark_quality' => [
                                'type' => 'range',
                                'label' => __('Image Quality', 'ultimate-watermark'),
                                'default' => 90,
                                'min' => 1,
                                'max' => 100,
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'image_format' => [
                                'type' => 'select',
                                'label' => __('Image Format', 'ultimate-watermark'),
                                'default' => 'baseline',
                                'options' => [
                                    'baseline' => __('Baseline', 'ultimate-watermark'),
                                    'progressive' => __('Progressive', 'ultimate-watermark')
                                ],
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ]
                        ]
                    ],
                    'template_name' => [
                        'label' => __('Template Name', 'ultimate-watermark'),
                        'fields' => [
                            'name' => [
                                'type' => 'text',
                                'label' => __('Name', 'ultimate-watermark'),
                                'placeholder' => __('Enter a name for this watermark template', 'ultimate-watermark'),
                                'required' => true,
                                'column' => 'left',
                                'sanitize_callback' => [$this, 'sanitizeText'],
                                'validate_callback' => [$this, 'validateRequired']
                            ],
                            'description' => [
                                'type' => 'textarea',
                                'label' => __('Description', 'ultimate-watermark'),
                                'placeholder' => __('Optional description for this watermark', 'ultimate-watermark'),
                                'rows' => 3,
                                'column' => 'right',
                                'sanitize_callback' => [$this, 'sanitizeTextarea'],
                                'validate_callback' => [$this, 'validateOptional']
                            ]
                        ]
                    ],
                    'watermarking_behavior' => [
                        'label' => __('Watermarking Behavior', 'ultimate-watermark'),
                        'fields' => [
                            'active' => [
                                'type' => 'checkbox',
                                'label' => __('Active', 'ultimate-watermark'),
                                'description' => __('Enable this watermark. When inactive, this watermark will not be applied to any images.', 'ultimate-watermark'),
                                'default' => '1',
                                'sanitize_callback' => [$this, 'sanitizeCheckbox'],
                                'validate_callback' => [$this, 'validateCheckbox']
                            ],
                            'automatic_watermarking' => [
                                'type' => 'checkbox',
                                'label' => __('Automatic watermarking', 'ultimate-watermark'),
                                'description' => __('When there is no way to choose watermark, you can use this watermark as automatic watermark.', 'ultimate-watermark'),
                                'default' => '1',
                                'sanitize_callback' => [$this, 'sanitizeCheckbox'],
                                'validate_callback' => [$this, 'validateCheckbox']
                            ],
                            'manual_watermarking' => [
                                'type' => 'checkbox',
                                'label' => __('Manual watermarking', 'ultimate-watermark'),
                                'description' => __('If you want to apply manual watermark, it should show in the list on the media so that user can choose this watermark.', 'ultimate-watermark'),
                                'default' => '1',
                                'sanitize_callback' => [$this, 'sanitizeCheckbox'],
                                'validate_callback' => [$this, 'validateCheckbox']
                            ],
                            'frontend_watermarking' => [
                                'type' => 'checkbox',
                                'label' => __('Frontend watermarking', 'ultimate-watermark'),
                                'description' => __('When images are uploaded from frontend, this watermark (checked) should be applied there.', 'ultimate-watermark'),
                                'default' => '0',
                                'sanitize_callback' => [$this, 'sanitizeCheckbox'],
                                'validate_callback' => [$this, 'validateCheckbox']
                            ]
                        ]
                    ]
                ]
            ],
            'appearance' => [
                'label' => __('Appearance', 'ultimate-watermark'),
                'icon' => 'dashicons-admin-appearance',
                'sections' => [
                    'position_settings' => [
                        'label' => __('Position Settings', 'ultimate-watermark'),
                        'fields' => [
                            'watermark_position' => [
                                'type' => 'position_selector',
                                'label' => __('Position', 'ultimate-watermark'),
                                'default' => 'bottom-right',
                                'options' => [
                                    'top-left' => __('Top Left', 'ultimate-watermark'),
                                    'top-center' => __('Top Center', 'ultimate-watermark'),
                                    'top-right' => __('Top Right', 'ultimate-watermark'),
                                    'center-left' => __('Center Left', 'ultimate-watermark'),
                                    'center' => __('Center', 'ultimate-watermark'),
                                    'center-right' => __('Center Right', 'ultimate-watermark'),
                                    'bottom-left' => __('Bottom Left', 'ultimate-watermark'),
                                    'bottom-center' => __('Bottom Center', 'ultimate-watermark'),
                                    'bottom-right' => __('Bottom Right', 'ultimate-watermark')
                                ],
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ],
                            'watermark_rotation' => [
                                'type' => 'range',
                                'label' => __('Rotation', 'ultimate-watermark'),
                                'default' => 0,
                                'min' => -180,
                                'max' => 180,
                                'column' => 'right',
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'watermark_opacity' => [
                                'type' => 'range',
                                'label' => __('Opacity', 'ultimate-watermark'),
                                'default' => 50,
                                'min' => 1,
                                'max' => 100,
                                'column' => 'right',
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'watermark_offset_x' => [
                                'type' => 'number',
                                'label' => __('Watermark offset [X]', 'ultimate-watermark'),
                                'default' => 0,
                                'min' => -100,
                                'max' => 100,
                                'description' => __('Enter watermark offset value for X (ie offset width)', 'ultimate-watermark'),
                                'column' => 'left',
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'watermark_offset_y' => [
                                'type' => 'number',
                                'label' => __('Watermark offset [Y]', 'ultimate-watermark'),
                                'default' => 0,
                                'min' => -100,
                                'max' => 100,
                                'description' => __('Enter watermark offset value for Y (ie offset height)', 'ultimate-watermark'),
                                'column' => 'center',
                                'sanitize_callback' => [$this, 'sanitizeNumber'],
                                'validate_callback' => [$this, 'validateRange']
                            ],
                            'offset_unit' => [
                                'type' => 'select',
                                'label' => __('Offset unit', 'ultimate-watermark'),
                                'default' => 'pixels',
                                'options' => [
                                    'pixels' => __('Pixels', 'ultimate-watermark'),
                                    'percentage' => __('Percentage', 'ultimate-watermark')
                                ],
                                'description' => __('Select the watermark offset unit', 'ultimate-watermark'),
                                'column' => 'right',
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ]
                        ]
                    ]
                ]
            ],
            'rules' => [
                'label' => __('Rules', 'ultimate-watermark'),
                'icon' => 'dashicons-admin-generic',
                'sections' => [
                    'watermark_rules' => [
                        'label' => __('Rules Management', 'ultimate-watermark'),
                        'fields' => [
                            'rules_manager' => [
                                'type' => 'custom',
                                'label' => __('Rules Manager', 'ultimate-watermark'),
                                'description' => __('Create and manage watermark rules with names and conditions', 'ultimate-watermark'),
                                'custom_render' => [$this, 'renderRulesManager']
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // Allow Pro plugin to add sections to the basic tab
        $pro_sections = apply_filters('ultimate_watermark_add_form_sections', []);
        if (!empty($pro_sections)) {
            $config['basic']['sections'] = array_merge($config['basic']['sections'], $pro_sections);
        }
        
        // Allow Pro plugin to add sections to the rules tab
        $pro_rules_sections = apply_filters('ultimate_watermark_rules_sections', []);
        if (!empty($pro_rules_sections)) {
            $config['rules']['sections'] = array_merge($config['rules']['sections'], $pro_rules_sections);
        }
        
        return $config;
    }

    /**
     * Get post type options for watermark rules
     */
    private function getPostTypeOptions()
    {
        $options = [
            'post' => __('Posts', 'ultimate-watermark'),
            'page' => __('Pages', 'ultimate-watermark'),
            'attachment' => __('Attachments', 'ultimate-watermark')
        ];

        $custom_post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
        foreach ($custom_post_types as $post_type) {
            if ($post_type->name !== 'attachment') {
                $options[$post_type->name] = $post_type->label;
            }
        }

        return $options;
    }

    /**
     * Get default condition types for the rule builder
     *
     * @return array
     */
    public function getDefaultConditionTypes(): array
    {
        return [
            'image_size' => [
                'label' => __('Image Size', 'ultimate-watermark'),
                'operators' => [
                    'is' => __('is', 'ultimate-watermark'),
                    'is_not' => __('is not', 'ultimate-watermark'),
                ],
                'valueType' => 'select',
                'values' => $this->getImageSizeOptions(),
            ],
            'post_type' => [
                'label' => __('Post Type', 'ultimate-watermark'),
                'operators' => [
                    'is' => __('is', 'ultimate-watermark'),
                    'is_not' => __('is not', 'ultimate-watermark'),
                ],
                'valueType' => 'select',
                'values' => $this->getPostTypeOptions(),
            ],
        ];
    }

    /**
     * Render rules manager with list/table interface
     */
    public function renderRulesManager(): void
    {
        // Get watermark ID from URL (consistent with render() method)
        $watermark_id = isset($_GET['ID']) ? intval($_GET['ID']) : 0;
        
        // Load saved rules from post meta
        $existing_rules = [];
        if ($watermark_id) {
            $saved_rules = get_post_meta($watermark_id, 'watermark_rules', true);
            if (!empty($saved_rules) && is_array($saved_rules)) {
                $existing_rules = $saved_rules;
            }
        }
        
        // No default rule needed - start with empty rules if none exist
        // Users can create their own rules as needed
        
        // Allow Pro to extend condition types
        $condition_types = apply_filters('uwm_condition_types', $this->getDefaultConditionTypes());
        
        ?>
        <div class="rules-manager">
            <!-- Hidden field to store rules JSON - submitted with main form -->
            <input type="hidden" name="watermark_rules" id="watermark-rules-data" value="<?php echo esc_attr(!empty($existing_rules) ? wp_json_encode($existing_rules) : '{}'); ?>">

            <!-- Rules List Header -->
            <div class="rules-header">
                <div class="rules-title">
                    <h3><?php esc_html_e('Watermark Rules', 'ultimate-watermark'); ?></h3>
                    <p class="description"><?php esc_html_e('Create and manage rules for when and where this watermark should be applied.', 'ultimate-watermark'); ?></p>
                </div>
                <div class="rules-actions">
                    <button type="button" class="button button-primary" id="add-new-rule" onclick="showRuleModal()">
                        <span class="dashicons dashicons-plus"></span>
                        <?php esc_html_e('Add New Rule', 'ultimate-watermark'); ?>
                    </button>
                </div>
            </div>

            <!-- Rules List Table (tbody rendered by JS from state) -->
            <div class="rules-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Rule Name', 'ultimate-watermark'); ?></th>
                            <th scope="col"><?php esc_html_e('Conditions', 'ultimate-watermark'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'ultimate-watermark'); ?></th>
                            <th scope="col"><?php esc_html_e('Actions', 'ultimate-watermark'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rendered dynamically by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Rule Form Modal -->
            <div id="rule-form-modal" class="rule-modal" style="display: none;">
                <div class="rule-modal-content">
                    <div class="rule-modal-header">
                        <h3 id="rule-modal-title"><?php esc_html_e('Add New Rule', 'ultimate-watermark'); ?></h3>
                        <button type="button" class="rule-modal-close">&times;</button>
                    </div>
                    <div class="rule-modal-body">
                        <input type="hidden" id="rule-id" name="rule_id">
                        
                        <!-- Rule Name -->
                        <div class="form-row">
                            <label for="rule-name"><?php esc_html_e('Rule Name', 'ultimate-watermark'); ?></label>
                            <input type="text" id="rule-name" placeholder="<?php esc_html_e('Enter rule name', 'ultimate-watermark'); ?>">
                            <p class="description"><?php esc_html_e('Give this rule a descriptive name.', 'ultimate-watermark'); ?></p>
                        </div>

                        <!-- Logic Operator -->
                        <div class="form-row">
                            <label for="rule-logic-operator"><?php esc_html_e('Logic Operator', 'ultimate-watermark'); ?></label>
                            <select id="rule-logic-operator" name="rule_logic_operator">
                                <option value="and"><?php esc_html_e('AND - All conditions must match', 'ultimate-watermark'); ?></option>
                                <option value="or"><?php esc_html_e('OR - Any condition can match', 'ultimate-watermark'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('How should multiple conditions be evaluated together.', 'ultimate-watermark'); ?></p>
                        </div>

                        <!-- Unified Conditions Builder -->
                        <div class="conditions-builder">
                            <div class="conditions-builder-header">
                                <label><?php esc_html_e('Conditions', 'ultimate-watermark'); ?></label>
                                <button type="button" class="button button-secondary button-small" id="add-condition">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    <?php esc_html_e('Add Condition', 'ultimate-watermark'); ?>
                                </button>
                            </div>

                            <div class="conditions-list" id="rule-conditions-list">
                                <!-- Conditions added dynamically -->
                            </div>

                            <div class="conditions-empty" id="rule-conditions-empty">
                                <div class="empty-state">
                                    <span class="dashicons dashicons-filter"></span>
                                    <p><?php esc_html_e('No conditions added yet.', 'ultimate-watermark'); ?></p>
                                    <p class="description"><?php esc_html_e('Click "Add Condition" to define when this rule applies.', 'ultimate-watermark'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rule-modal-footer">
                        <button type="button" class="button button-secondary rule-modal-cancel"><?php esc_html_e('Cancel', 'ultimate-watermark'); ?></button>
                        <button type="button" class="button button-primary rule-modal-save"><?php esc_html_e('Save Rule', 'ultimate-watermark'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function($) {
            'use strict';

            // ========== STATE ==========
            var rawRules = JSON.parse($('#watermark-rules-data').val() || '{}');
            // CRITICAL: Ensure rulesState is always a plain object, never an Array
            // PHP json_encode([]) produces "[]" which JS parses as Array.
            // JSON.stringify(Array) ignores non-numeric keys, losing all rules.
            var rulesState = (Array.isArray(rawRules) || typeof rawRules !== 'object' || rawRules === null) ? {} : rawRules;
            var conditionIndex = 0;
            var editingRuleId = null;

            // Condition types (extensible via wp.hooks)
            var conditionTypes = <?php echo wp_json_encode($condition_types); ?>;
            if (window.wp && wp.hooks) {
                conditionTypes = wp.hooks.applyFilters('uwm_condition_types', conditionTypes);
            }

            // ========== SYNC STATE → HIDDEN FIELD ==========
            function syncState() {
                $('#watermark-rules-data').val(JSON.stringify(rulesState));
            }

            // ========== GENERATE UNIQUE ID ==========
            function generateId() {
                return 'rule_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
            }

            // ========== RENDER TABLE ==========
            function renderTable() {
                var $tbody = $('.rules-manager .rules-list table tbody');
                $tbody.empty();

                var ruleIds = Object.keys(rulesState);
                if (ruleIds.length === 0) {
                    $tbody.append('<tr class="no-rules"><td colspan="4" style="text-align:center;padding:20px;color:#94a3b8;"><?php esc_html_e('No rules defined. Click "Add New Rule" to create one.', 'ultimate-watermark'); ?></td></tr>');
                    return;
                }

                ruleIds.forEach(function(ruleId) {
                    var rule = rulesState[ruleId];
                    var condSummary = buildConditionSummary(rule.conditions || []);
                    var logicOp = (rule.logic_operator || 'and').toUpperCase();

                    var row = '<tr data-rule-id="' + ruleId + '">' +
                        '<td><strong>' + escHtml(rule.name) + '</strong></td>' +
                        '<td>' + condSummary +
                        '<div class="rule-logic"><small><?php esc_html_e('Logic:', 'ultimate-watermark'); ?> <strong>' + logicOp + '</strong></small></div></td>' +
                        '<td><span class="status-active"><?php esc_html_e('Active', 'ultimate-watermark'); ?></span></td>' +
                        '<td><div class="rule-actions">' +
                        '<button type="button" class="button button-small edit-rule" data-rule-id="' + ruleId + '"><span class="dashicons dashicons-edit"></span> <?php esc_html_e('Edit', 'ultimate-watermark'); ?></button>' +
                        ' <button type="button" class="button button-small button-link-delete delete-rule" data-rule-id="' + ruleId + '"><span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete', 'ultimate-watermark'); ?></button>' +
                        '</div></td>' +
                        '</tr>';
                    $tbody.append(row);
                });
            }

            function buildConditionSummary(conditions) {
                if (!conditions || conditions.length === 0) {
                    return '<em><?php esc_html_e('No conditions', 'ultimate-watermark'); ?></em>';
                }
                var parts = [];
                conditions.forEach(function(c) {
                    var typeLabel = conditionTypes[c.type] ? conditionTypes[c.type].label : c.type;
                    var opLabel = c.operator;
                    if (conditionTypes[c.type] && conditionTypes[c.type].operators[c.operator]) {
                        opLabel = conditionTypes[c.type].operators[c.operator];
                    }
                    var valLabel = c.value;
                    if (conditionTypes[c.type] && conditionTypes[c.type].values && conditionTypes[c.type].values[c.value]) {
                        valLabel = conditionTypes[c.type].values[c.value];
                    }
                    parts.push('<span class="condition-tag">' + escHtml(typeLabel) + ' ' + escHtml(opLabel) + ' <strong>' + escHtml(valLabel) + '</strong></span>');
                });
                return parts.join(' ');
            }

            function escHtml(str) {
                if (!str) return '';
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            // ========== MODAL: OPEN FOR ADD ==========
            function openAddModal() {
                editingRuleId = null;
                $('#rule-modal-title').text('<?php esc_html_e('Add New Rule', 'ultimate-watermark'); ?>');
                $('#rule-name').val('');
                $('#rule-logic-operator').val('and');
                $('#rule-conditions-list').empty();
                conditionIndex = 0;
                updateEmptyState();
                $('#rule-form-modal').show();
            }

            // ========== MODAL: OPEN FOR EDIT ==========
            function openEditModal(ruleId) {
                var rule = rulesState[ruleId];
                if (!rule) return;

                editingRuleId = ruleId;
                $('#rule-modal-title').text('<?php esc_html_e('Edit Rule', 'ultimate-watermark'); ?>');
                $('#rule-name').val(rule.name);
                $('#rule-logic-operator').val(rule.logic_operator || 'and');
                $('#rule-conditions-list').empty();
                conditionIndex = 0;

                if (rule.conditions && rule.conditions.length > 0) {
                    rule.conditions.forEach(function(cond) {
                        addConditionCard(conditionIndex, cond.type, cond.operator, cond.value);
                        conditionIndex++;
                    });
                }
                updateEmptyState();
                $('#rule-form-modal').show();
            }

            // ========== MODAL: SAVE ==========
            function saveRule() {
                var name = $.trim($('#rule-name').val());
                if (!name) {
                    alert('<?php esc_html_e('Please enter a rule name.', 'ultimate-watermark'); ?>');
                    $('#rule-name').focus();
                    return;
                }

                var logicOp = $('#rule-logic-operator').val() || 'and';
                var conditions = [];

                $('#rule-conditions-list .condition-card').each(function() {
                    var type = $(this).find('.condition-type').val();
                    var operator = $(this).find('.condition-operator').val();
                    var value = $(this).find('.condition-value').val();
                    if (type && operator && value) {
                        conditions.push({ type: type, operator: operator, value: value });
                    }
                });

                if (editingRuleId && rulesState[editingRuleId]) {
                    // Update existing rule
                    rulesState[editingRuleId].name = name;
                    rulesState[editingRuleId].logic_operator = logicOp;
                    rulesState[editingRuleId].conditions = conditions;
                } else {
                    // Create new rule
                    var newId = generateId();
                    rulesState[newId] = {
                        name: name,
                        logic_operator: logicOp,
                        conditions: conditions
                    };
                }

                syncState();
                renderTable();
                $('#rule-form-modal').hide();
            }

            // ========== DELETE RULE ==========
            function deleteRule(ruleId) {
                var rule = rulesState[ruleId];
                if (!rule) return;

                if (!confirm('<?php esc_html_e('Are you sure you want to delete the rule', 'ultimate-watermark'); ?> "' + rule.name + '"?')) return;

                delete rulesState[ruleId];
                syncState();
                renderTable();
            }

            // ========== CONDITION CARD ==========
            function addConditionCard(index, preType, preOp, preVal) {
                var typeOptions = '<option value=""><?php esc_html_e('Select condition type...', 'ultimate-watermark'); ?></option>';
                for (var key in conditionTypes) {
                    typeOptions += '<option value="' + key + '"' + (preType === key ? ' selected' : '') + '>' + escHtml(conditionTypes[key].label) + '</option>';
                }

                var html =
                '<div class="condition-card" data-index="' + index + '">' +
                    '<div class="condition-header">' +
                        '<span class="condition-number"><?php esc_html_e('Condition', 'ultimate-watermark'); ?> #' + (index + 1) + '</span>' +
                        '<button type="button" class="remove-condition" title="<?php esc_html_e('Remove', 'ultimate-watermark'); ?>"><span class="dashicons dashicons-no-alt"></span></button>' +
                    '</div>' +
                    '<div class="condition-fields">' +
                        '<div class="condition-field"><label><?php esc_html_e('Type', 'ultimate-watermark'); ?></label><select class="condition-type">' + typeOptions + '</select></div>' +
                        '<div class="condition-field"><label><?php esc_html_e('Operator', 'ultimate-watermark'); ?></label><select class="condition-operator" disabled><option value=""><?php esc_html_e('Select...', 'ultimate-watermark'); ?></option></select></div>' +
                        '<div class="condition-field condition-value-wrap"><label><?php esc_html_e('Value', 'ultimate-watermark'); ?></label><input type="text" class="condition-value" placeholder="<?php esc_html_e('Select type first', 'ultimate-watermark'); ?>" disabled></div>' +
                    '</div>' +
                '</div>';

                $('#rule-conditions-list').append(html);
                $('#rule-conditions-empty').hide();

                if (preType) {
                    var $card = $('#rule-conditions-list .condition-card[data-index="' + index + '"]');
                    // Trigger type change to populate operator/value fields
                    $card.find('.condition-type').trigger('change');
                    // Use longer delays to guarantee DOM has updated
                    setTimeout(function() {
                        if (preOp) {
                            $card.find('.condition-operator').val(preOp).prop('disabled', false).trigger('change');
                        }
                        setTimeout(function() {
                            if (preVal) {
                                $card.find('.condition-value').val(preVal).prop('disabled', false);
                            }
                        }, 100);
                    }, 100);
                }
            }

            function updateEmptyState() {
                $('#rule-conditions-empty').toggle($('#rule-conditions-list .condition-card').length === 0);
            }

            // ========== EVENT BINDINGS ==========
            $(document).ready(function() {
                // Force full width
                var $rm = $('.rules-manager'), $p = $rm.closest('.form-column, .form-columns');
                if ($p.length) {
                    $p.css({ 'grid-column': '1 / -1', 'width': '100%', 'max-width': 'none' });
                    $rm.css({ 'width': '100%', 'max-width': 'none', 'grid-column': '1 / -1' });
                }

                // Initial render
                renderTable();

                // Add New Rule
                $(document).on('click', '#add-new-rule', function(e) { e.preventDefault(); openAddModal(); });

                // Edit Rule
                $(document).on('click', '.edit-rule', function(e) { e.preventDefault(); openEditModal($(this).data('rule-id')); });

                // Delete Rule
                $(document).on('click', '.delete-rule', function(e) { e.preventDefault(); deleteRule($(this).data('rule-id')); });

                // Modal close
                $(document).on('click', '.rule-modal-close, .rule-modal-cancel', function(e) { e.preventDefault(); $('#rule-form-modal').hide(); });

                // Modal save
                $(document).on('click', '.rule-modal-save', function(e) { e.preventDefault(); saveRule(); });

                // Add Condition
                $(document).on('click', '#add-condition', function(e) { e.preventDefault(); addConditionCard(conditionIndex); conditionIndex++; updateEmptyState(); });

                // Remove Condition
                $(document).on('click', '.remove-condition', function() {
                    $(this).closest('.condition-card').fadeOut(200, function() { $(this).remove(); updateEmptyState(); });
                });

                // Condition type changed
                $(document).on('change', '.condition-type', function() {
                    var $card = $(this).closest('.condition-card'), type = $(this).val();
                    var $opSelect = $card.find('.condition-operator'), $valWrap = $card.find('.condition-value-wrap');
                    $opSelect.html('<option value=""><?php esc_html_e('Select...', 'ultimate-watermark'); ?></option>').prop('disabled', true);

                    if (type && conditionTypes[type]) {
                        var ct = conditionTypes[type];
                        for (var op in ct.operators) { $opSelect.append('<option value="' + op + '">' + ct.operators[op] + '</option>'); }
                        $opSelect.prop('disabled', false);
                        var valueHtml = '';
                        if (ct.valueType === 'select') {
                            valueHtml = '<label><?php esc_html_e('Value', 'ultimate-watermark'); ?></label><select class="condition-value" disabled><option value=""><?php esc_html_e('Select...', 'ultimate-watermark'); ?></option>';
                            for (var v in ct.values) { valueHtml += '<option value="' + v + '">' + escHtml(ct.values[v]) + '</option>'; }
                            valueHtml += '</select>';
                        } else if (ct.valueType === 'number') {
                            valueHtml = '<label><?php esc_html_e('Value', 'ultimate-watermark'); ?></label><input type="number" class="condition-value" placeholder="<?php esc_html_e('Enter value...', 'ultimate-watermark'); ?>" disabled>';
                        } else {
                            valueHtml = '<label><?php esc_html_e('Value', 'ultimate-watermark'); ?></label><input type="text" class="condition-value" placeholder="<?php esc_html_e('Enter value...', 'ultimate-watermark'); ?>" disabled>';
                        }
                        $valWrap.html(valueHtml);
                    } else {
                        $valWrap.html('<label><?php esc_html_e('Value', 'ultimate-watermark'); ?></label><input type="text" class="condition-value" placeholder="<?php esc_html_e('Select type first', 'ultimate-watermark'); ?>" disabled>');
                    }
                });

                // Operator changed → enable value
                $(document).on('change', '.condition-operator', function() {
                    $(this).closest('.condition-card').find('.condition-value').prop('disabled', !$(this).val());
                });

            }); // Close document.ready

            // Expose globally for inline onclick
            window.showRuleModal = openAddModal;

        })(jQuery);
        </script>

        <style>
        /* Rules Manager Styles - Force full width with high specificity */
        .ultimate-watermark-add-watermark .form-column .rules-manager,
        .ultimate-watermark-add-watermark .form-columns .rules-manager,
        .form-column .rules-manager,
        .form-columns .rules-manager {
            grid-column: 1 / -1 !important; /* Span all columns */
            width: 100% !important;
            max-width: none !important;
            box-sizing: border-box !important;
            flex: 1 1 100% !important;
        }

        .rules-manager {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            width: 100% !important;
            max-width: none !important;
            box-sizing: border-box !important;
            position: relative !important;
        }

        /* Override any parent container constraints */
        #watermark_rules .rules-manager,
        #watermark_rules .form-column .rules-manager,
        #watermark_rules .form-columns .rules-manager {
            width: 100% !important;
            max-width: none !important;
            grid-column: 1 / -1 !important;
        }

        /* Force parent containers to allow full width */
        #watermark_rules .form-column,
        #watermark_rules .form-columns {
            grid-template-columns: 1fr !important;
        }

        #watermark_rules .form-column .rules-manager {
            width: 100% !important;
            max-width: 100% !important;
        }

        .rules-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #c3c4c7;
        }

        .rules-title h3 {
            margin: 0 0 5px 0;
            font-size: 20px;
            font-weight: 600;
            color: #1d2327;
        }

        .rules-title .description {
            margin: 0;
            color: #646970;
            font-size: 14px;
        }

        .rules-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .rules-actions .button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .rules-actions .button .dashicons {
            font-size: 16px;
            line-height: 1;
        }

        .rules-actions .delete-rule {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }

        .rules-actions .delete-rule:hover {
            background: #c82333;
            border-color: #c82333;
            color: #fff;
        }

        .rules-actions .delete-rule:focus {
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.5);
        }

        .rules-list {
            margin-top: 20px;
            width: 100%;
        }

        .rules-list table {
            width: 100%;
            border-collapse: collapse;
        }

        .rule-conditions,
        .rule-image-sizes,
        .rule-targeting {
            font-size: 13px;
            color: #50575e;
        }

        .rule-logic {
            margin-top: 4px;
            color: #646970;
            font-style: italic;
        }

        .rule-logic strong {
            color: #374151;
            font-style: normal;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .badge-default {
            background: #f0f6fc;
            color: #2271b1;
        }

        .status-active {
            color: #00a32a;
            font-weight: 500;
        }

        .rule-actions {
            display: flex;
            gap: 5px;
        }

        .rule-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rule-modal-content {
            background: #fff;
            border-radius: 8px;
            width: 95%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .rule-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .rule-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .rule-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #646970;
        }

        .rule-modal-body {
            padding: 20px;
        }

        .rule-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
            width: 100%;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
        }

        .checkbox-item label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .checkbox-item input[type="checkbox"] {
            margin-right: 8px;
        }

        .form-row {
            margin-bottom: 20px;
        }

        .form-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-row input,
        .form-row select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
        }

        .form-row .description {
            font-size: 13px;
            color: #646970;
            margin-top: 5px;
        }

        .conditional-field {
            display: none;
        }

        /* PRO Badge */
        .uwm-pro-badge {
            background: #2271b1;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 6px;
        }

        /* Unified Conditions Builder */
        .conditions-builder {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin-top: 8px;
        }

        .conditions-builder-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .conditions-builder-header label {
            font-size: 14px;
            font-weight: 600;
            color: #1d2327;
            margin: 0;
        }

        .conditions-builder-header .button {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .conditions-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .condition-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            transition: all 0.2s ease;
        }

        .condition-card:hover {
            border-color: #667eea;
            box-shadow: 0 1px 4px rgba(102, 126, 234, 0.15);
        }

        .condition-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .condition-number {
            font-size: 12px;
            font-weight: 600;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .condition-fields {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .condition-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .condition-field label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .condition-field select,
        .condition-field input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 13px;
            background: #fff;
        }

        .condition-field select:focus,
        .condition-field input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.15);
        }

        .condition-field select:disabled,
        .condition-field input:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .conditions-empty {
            margin-top: 0;
        }

        .empty-state {
            text-align: center;
            padding: 30px 16px;
            background: #fff;
            border: 2px dashed #cbd5e0;
            border-radius: 6px;
        }

        .empty-state .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
            color: #cbd5e0;
            margin-bottom: 12px;
            display: block;
        }

        .empty-state p {
            margin: 0 0 4px 0;
            color: #64748b;
            font-size: 14px;
        }

        .empty-state .description {
            color: #94a3b8;
            font-size: 13px;
        }

        .remove-condition {
            background: none;
            color: #ef4444;
            border: none;
            padding: 2px;
            border-radius: 4px;
            cursor: pointer;
            line-height: 1;
            display: flex;
            align-items: center;
        }

        .remove-condition:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .remove-condition .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .condition-tag {
            display: inline-block;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #4338ca;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin: 2px 2px 2px 0;
        }

        .condition-tag strong {
            color: #312e81;
        }

        .button-link-delete {
            color: #b91c1c !important;
            border-color: #fca5a5 !important;
        }

        .button-link-delete:hover {
            color: #7f1d1d !important;
            background: #fef2f2 !important;
            border-color: #f87171 !important;
        }

        .rules-list table td {
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            .condition-fields {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }

    /**
     * Sanitize checkbox value
     */
    public function sanitizeCheckbox($value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * Validate checkbox value
     */
    public function validateCheckbox($value): bool
    {
        return in_array($value, ['0', '1'], true);
    }

    /**
     * Get watermark data for editing
     */
    private function getWatermarkData($watermark_id)
    {
        $post = get_post($watermark_id);
        if (!$post || $post->post_type !== 'ultimate_watermark') {
            return null;
        }

        $data = [
            'name' => $post->post_title,
            'description' => $post->post_content
        ];

        // Get all form field values from post meta (excluding name and description which are post data)
        $tabs_config = $this->getFormTabsConfig();
        foreach ($tabs_config as $tab_config) {
            foreach ($tab_config['sections'] as $section_config) {
                foreach ($section_config['fields'] as $field_name => $field_config) {
                    // Skip name and description as they are already set from post data
                    if (in_array($field_name, ['name', 'description'])) {
                        continue;
                    }
                    
                    $meta_value = get_post_meta($watermark_id, $field_name, true);
                    
                    // Handle checkbox fields specially - '0' is a valid value
                    if ($field_config['type'] === 'checkbox') {
                        $data[$field_name] = $meta_value !== '' ? $meta_value : ($field_config['default'] ?? '0');
                    } else {
                        $data[$field_name] = $meta_value ?: ($field_config['default'] ?? '');
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Render position selector field
     */
    private function renderPositionSelectorField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <div class="position-selector-container">
            <div class="position-selector-grid" id="<?php echo esc_attr($field_name); ?>-grid">
                <?php foreach ($field_config['options'] as $option_value => $option_label): ?>
                    <div class="position-option <?php echo $value === $option_value ? 'selected' : ''; ?>" 
                         data-value="<?php echo esc_attr($option_value); ?>"
                         data-label="<?php echo esc_attr($option_label); ?>">
                        <div class="position-indicator"></div>
                        <span class="position-label"><?php echo esc_html($option_label); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" 
                   id="<?php echo esc_attr($field_name); ?>" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   value="<?php echo esc_attr($value); ?>">
        </div>
        <?php if (isset($field_config['description'])): ?>
            <p class="description"><?php echo esc_html($field_config['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render position settings fields with special layout
     */
    private function renderPositionSettingsFields($fields, $watermark_data)
    {
        ?>
        <div class="form-columns">
            <!-- Left column: Position grid -->
            <div class="form-column">
                <?php 
                $position_field = $fields['watermark_position'];
                $this->renderField('watermark_position', $position_field, $watermark_data);
                ?>
            </div>
            
            <!-- Right column: Rotation and Opacity -->
            <div class="form-column">
                <?php 
                $rotation_field = $fields['watermark_rotation'];
                $this->renderField('watermark_rotation', $rotation_field, $watermark_data);
                ?>
                
                <?php 
                $opacity_field = $fields['watermark_opacity'];
                $this->renderField('watermark_opacity', $opacity_field, $watermark_data);
                ?>
            </div>
        </div>
        
        <!-- Bottom row: X, Y, Unit fields -->
        <div class="form-columns">
            <div class="form-column">
                <?php 
                $x_field = $fields['watermark_offset_x'];
                $this->renderField('watermark_offset_x', $x_field, $watermark_data);
                ?>
            </div>
            <div class="form-column">
                <?php 
                $y_field = $fields['watermark_offset_y'];
                $this->renderField('watermark_offset_y', $y_field, $watermark_data);
                ?>
            </div>
            <div class="form-column">
                <?php 
                $unit_field = $fields['offset_unit'];
                $this->renderField('offset_unit', $unit_field, $watermark_data);
                ?>
            </div>
        </div>
        <?php
    }

    // Sanitization callbacks
    public function sanitizeWatermarkType($value) { return sanitize_text_field($value); }
    public function sanitizeText($value) { return sanitize_text_field($value); }
    public function sanitizeTextarea($value) { return sanitize_textarea_field($value); }
    public function sanitizeNumber($value) { return absint($value); }
    public function sanitizeColor($value) { return sanitize_hex_color($value); }
    public function sanitizeSelect($value) { return sanitize_text_field($value); }
    public function sanitizeMediaId($value) { return absint($value); }
    public function sanitizeCheckboxGroup($value) { return is_array($value) ? array_map('sanitize_text_field', $value) : []; }

    // Validation callbacks
    public function validateWatermarkType($value) { return in_array($value, ['text', 'image']); }
    /**
     * Get all available WordPress image sizes as options
     * 
     * @return array Array of size name => label
     */
    private function getImageSizeOptions(): array
    {
        $options = [];
        
        // Get all intermediate image sizes (includes default sizes and custom sizes added by themes/plugins)
        $image_sizes = get_intermediate_image_sizes();
        
        // Get size information for labels (dimensions)
        $size_info = wp_get_registered_image_subsizes();
        
        // Process each size
        foreach ($image_sizes as $size_name) {
            // Format label with dimensions if available
            $label = ucwords(str_replace(['-', '_'], ' ', $size_name));
            
            // Add dimensions if available
            if (isset($size_info[$size_name])) {
                $width = $size_info[$size_name]['width'] ?? 0;
                $height = $size_info[$size_name]['height'] ?? 0;
                
                if ($width > 0 || $height > 0) {
                    if ($width > 0 && $height > 0) {
                        $label .= sprintf(' (%d × %d)', $width, $height);
                    } elseif ($width > 0) {
                        $label .= sprintf(' (%dpx width)', $width);
                    } elseif ($height > 0) {
                        $label .= sprintf(' (%dpx height)', $height);
                    }
                }
            }
            
            $options[$size_name] = $label;
        }
        
        // Always add 'full' size (original image)
        $options['full'] = __('Full Size (Original Image)', 'ultimate-watermark');
        
        // Sort options: put 'full' at the end, others alphabetically
        $full_option = ['full' => $options['full']];
        unset($options['full']);
        ksort($options);
        $options = array_merge($options, $full_option);
        
        return $options;
    }

    public function validateRequired($value) { return !empty($value); }
    public function validateOptional($value) { return true; }
    public function validateRange($value, $min = 0, $max = 100) { return is_numeric($value) && $value >= $min && $value <= $max; }
    public function validateColor($value) { return preg_match('/^#[a-fA-F0-9]{6}$/', $value); }
    public function validateSelect($value, $options = []) { return in_array($value, array_keys($options)); }
    public function validateMediaId($value) { return $value > 0 && wp_attachment_is_image($value); }
    public function validateCheckboxGroup($value) { return is_array($value); }
}
