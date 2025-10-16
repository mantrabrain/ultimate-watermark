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
        
        // Debug: Check if we're in edit mode and have data
        if ($is_edit_mode && $watermark_data) {
            // Edit mode data loaded
        }
        
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
                    // Debug: Log section rendering
                    if ($section_id === 'text_settings') {
                        error_log("Ultimate Watermark: Rendering text_settings section with condition: " . ($section_config['condition'] ?? 'none'));
                        error_log("Ultimate Watermark: Watermark data: " . print_r($watermark_data, true));
                    }
                    ?>
                    <div class="form-section" id="<?php echo esc_attr($section_id); ?>" <?php if (isset($section_config['condition'])): ?>data-condition="<?php echo esc_attr($section_config['condition']); ?>"<?php endif; ?>>
                        <h4><?php echo esc_html($section_config['label']); ?></h4>
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
                        // Debug: Log conditional field rendering
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
        
        // Debug: Log watermark data for specific fields
        if (in_array($field_name, ['watermark_font_style', 'watermark_text_decoration'])) {
            error_log("Ultimate Watermark: Watermark data for '$field_name': " . print_r($watermark_data, true));
        }
        
        // Debug: Log field rendering
        if (in_array($field_name, ['watermark_font_style', 'watermark_text_decoration'])) {
            error_log("Ultimate Watermark: Rendering field '$field_name' with value: '$value'");
            error_log("Ultimate Watermark: Field config: " . print_r($field_config, true));
        }
        
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
               <?php echo isset($field_config['required']) && $field_config['required'] ? 'required' : ''; ?>>
        <?php
    }

    /**
     * Render textarea field
     */
    private function renderTextareaField($field_name, $field_config, $value)
    {
        ?>
        <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label>
        <textarea id="<?php echo esc_attr($field_name); ?>" 
                  name="<?php echo esc_attr($field_name); ?>" 
                  placeholder="<?php echo esc_attr($field_config['placeholder'] ?? ''); ?>"
                  rows="<?php echo esc_attr($field_config['rows'] ?? 3); ?>"><?php echo esc_textarea($value); ?></textarea>
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
                        <span><?php echo esc_html($option_config['label']); ?></span>
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
    private function getFormTabsConfig()
    {
        return [
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
                                'options' => [
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
                                ],
                                'sanitize_callback' => [$this, 'sanitizeWatermarkType'],
                                'validate_callback' => [$this, 'validateWatermarkType']
                            ]
                        ]
                    ],
                    'text_settings' => [
                        'label' => __('Text Settings', 'ultimate-watermark'),
                        'condition' => 'watermark_type === "text"',
                        'fields' => [
                            'watermark_text' => [
                                'type' => 'text',
                                'label' => __('Watermark Text', 'ultimate-watermark'),
                                'placeholder' => __('Enter watermark text', 'ultimate-watermark'),
                                'default' => '© ' . get_bloginfo('name'),
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
                        ]
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
                        'label' => __('Watermark Rules', 'ultimate-watermark'),
                        'fields' => [
                            'watermark_sizes' => [
                                'type' => 'checkbox_group',
                                'label' => __('Watermark For (Image Sizes)', 'ultimate-watermark'),
                                'default' => ['thumbnail', 'medium', 'large'],
                                'options' => [
                                    'thumbnail' => __('Thumbnail', 'ultimate-watermark'),
                                    'medium' => __('Medium', 'ultimate-watermark'),
                                    'large' => __('Large', 'ultimate-watermark'),
                                    'full' => __('Full Size', 'ultimate-watermark')
                                ],
                                'column' => 'left',
                                'sanitize_callback' => [$this, 'sanitizeCheckboxGroup'],
                                'validate_callback' => [$this, 'validateCheckboxGroup']
                            ],
                            'watermark_on' => [
                                'type' => 'select',
                                'label' => __('Watermark On', 'ultimate-watermark'),
                                'default' => 'everywhere',
                                'options' => [
                                    'everywhere' => __('Everywhere', 'ultimate-watermark'),
                                    'selected_post_types' => __('Selected Custom Post Types', 'ultimate-watermark')
                                ],
                                'column' => 'right',
                                'sanitize_callback' => [$this, 'sanitizeSelect'],
                                'validate_callback' => [$this, 'validateSelect']
                            ],
                            'watermark_post_types' => [
                                'type' => 'checkbox_group',
                                'label' => __('Custom Post Types', 'ultimate-watermark'),
                                'default' => ['post', 'page'],
                                'options' => $this->getPostTypeOptions(),
                                'condition' => 'watermark_on === "selected_post_types"',
                                'description' => __('Select which post types should have watermarks applied to their images.', 'ultimate-watermark'),
                                'sanitize_callback' => [$this, 'sanitizeCheckboxGroup'],
                                'validate_callback' => [$this, 'validateCheckboxGroup']
                            ]
                        ]
                    ]
                ]
            ]
        ];
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
                    $data[$field_name] = $meta_value ?: ($field_config['default'] ?? '');
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
    public function sanitizeCheckbox($value) { return $value ? '1' : '0'; }
    public function sanitizeCheckboxGroup($value) { return is_array($value) ? array_map('sanitize_text_field', $value) : []; }

    // Validation callbacks
    public function validateWatermarkType($value) { return in_array($value, ['text', 'image']); }
    public function validateRequired($value) { return !empty($value); }
    public function validateOptional($value) { return true; }
    public function validateRange($value, $min = 0, $max = 100) { return is_numeric($value) && $value >= $min && $value <= $max; }
    public function validateColor($value) { return preg_match('/^#[a-fA-F0-9]{6}$/', $value); }
    public function validateSelect($value, $options = []) { return in_array($value, array_keys($options)); }
    public function validateMediaId($value) { return $value > 0 && wp_attachment_is_image($value); }
    public function validateCheckbox($value) { return in_array($value, ['0', '1']); }
    public function validateCheckboxGroup($value) { return is_array($value); }
}
