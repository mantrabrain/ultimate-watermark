<?php

namespace MantraBrain\UltimateWatermark\Admin\Templates;

/**
 * Watermark Modal Template
 * 
 * Template for the watermark creation/editing modal
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
class WatermarkModal
{
    /**
     * Render watermark modal
     */
    public static function render(): void
    {
        ?>
        <!-- Watermark Form Modal -->
        <div class="watermark-modal" id="watermark-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content watermark-modal-wide">
                <div class="modal-header">
                    <h3 id="modal-title"><?php esc_html_e('Create New Watermark', 'ultimate-watermark'); ?></h3>
                    <button type="button" class="modal-close" id="modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="watermark-form-layout">
                        <!-- Left Side - Form Content -->
                        <div class="form-content">
                            <form id="ultimate-watermark-form" method="post" enctype="multipart/form-data">
                                <?php wp_nonce_field('ultimate_watermark_form', 'ultimate_watermark_nonce'); ?>
                                
                                <div class="form-tabs">
                                    <button type="button" class="form-tab active" data-tab="basic">
                                        <span class="dashicons dashicons-admin-settings"></span>
                                        <?php esc_html_e('Basic Settings', 'ultimate-watermark'); ?>
                                    </button>
                                    <button type="button" class="form-tab" data-tab="appearance">
                                        <span class="dashicons dashicons-admin-appearance"></span>
                                        <?php esc_html_e('Appearance', 'ultimate-watermark'); ?>
                                    </button>
                                    <button type="button" class="form-tab" data-tab="advanced">
                                        <span class="dashicons dashicons-admin-tools"></span>
                                        <?php esc_html_e('Advanced', 'ultimate-watermark'); ?>
                                    </button>
                                </div>

                                <div class="form-tab-content active" id="tab-basic">
                                    <div class="form-section">
                                        <h4><?php esc_html_e('Watermark Type', 'ultimate-watermark'); ?></h4>
                                        <div class="type-selection">
                                            <label class="type-option">
                                                <input type="radio" name="watermark_type" value="text" checked>
                                                <div class="type-card">
                                                    <span class="dashicons dashicons-format-text"></span>
                                                    <span><?php esc_html_e('Text Watermark', 'ultimate-watermark'); ?></span>
                                                    <p><?php esc_html_e('Add text-based watermarks', 'ultimate-watermark'); ?></p>
                                                </div>
                                            </label>
                                            
                                            <label class="type-option">
                                                <input type="radio" name="watermark_type" value="image">
                                                <div class="type-card">
                                                    <span class="dashicons dashicons-format-image"></span>
                                                    <span><?php esc_html_e('Image Watermark', 'ultimate-watermark'); ?></span>
                                                    <p><?php esc_html_e('Upload image watermarks', 'ultimate-watermark'); ?></p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-section" id="text-settings">
                                        <h4><?php esc_html_e('Text Settings', 'ultimate-watermark'); ?></h4>
                                        <div class="form-row">
                                            <label for="watermark_text"><?php esc_html_e('Watermark Text', 'ultimate-watermark'); ?></label>
                                            <input type="text" id="watermark_text" name="watermark_text" value="© <?php echo esc_attr(get_bloginfo('name')); ?>" placeholder="<?php esc_attr_e('Enter watermark text', 'ultimate-watermark'); ?>">
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_font_size"><?php esc_html_e('Font Size', 'ultimate-watermark'); ?></label>
                                            <input type="number" id="watermark_font_size" name="watermark_font_size" value="24" min="8" max="72">
                                            <span class="unit">px</span>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_color"><?php esc_html_e('Text Color', 'ultimate-watermark'); ?></label>
                                            <input type="text" id="watermark_color" name="watermark_color" value="#ffffff" class="color-picker">
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_font_family"><?php esc_html_e('Font Family', 'ultimate-watermark'); ?></label>
                                            <select id="watermark_font_family" name="watermark_font_family">
                                                <option value="Arial">Arial</option>
                                                <option value="Helvetica">Helvetica</option>
                                                <option value="Times New Roman">Times New Roman</option>
                                                <option value="Georgia">Georgia</option>
                                                <option value="Verdana">Verdana</option>
                                                <option value="Courier New">Courier New</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_font_weight"><?php esc_html_e('Font Weight', 'ultimate-watermark'); ?></label>
                                            <select id="watermark_font_weight" name="watermark_font_weight">
                                                <option value="normal"><?php esc_html_e('Normal', 'ultimate-watermark'); ?></option>
                                                <option value="bold"><?php esc_html_e('Bold', 'ultimate-watermark'); ?></option>
                                                <option value="lighter"><?php esc_html_e('Light', 'ultimate-watermark'); ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-section" id="image-settings" style="display: none;">
                                        <h4><?php esc_html_e('Image Settings', 'ultimate-watermark'); ?></h4>
                                        <div class="form-row">
                                            <label for="watermark_image"><?php esc_html_e('Watermark Image', 'ultimate-watermark'); ?></label>
                                            <div class="image-upload">
                                                <input type="file" id="watermark_image" name="watermark_image" accept="image/*">
                                                <div class="upload-area">
                                                    <span class="dashicons dashicons-upload"></span>
                                                    <span><?php esc_html_e('Click to upload or drag and drop', 'ultimate-watermark'); ?></span>
                                                    <p><?php esc_html_e('Recommended: PNG with transparency', 'ultimate-watermark'); ?></p>
                                                </div>
                                                <div class="image-preview" id="watermark-image-preview"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_image_size"><?php esc_html_e('Image Size', 'ultimate-watermark'); ?></label>
                                            <input type="number" id="watermark_image_size" name="watermark_image_size" value="100" min="20" max="500">
                                            <span class="unit">px</span>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h4><?php esc_html_e('Template Name', 'ultimate-watermark'); ?></h4>
                                        <div class="form-row">
                                            <label for="watermark_name"><?php esc_html_e('Name', 'ultimate-watermark'); ?></label>
                                            <input type="text" id="watermark_name" name="watermark_name" placeholder="<?php esc_attr_e('Enter a name for this watermark template', 'ultimate-watermark'); ?>" required>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_description"><?php esc_html_e('Description', 'ultimate-watermark'); ?></label>
                                            <textarea id="watermark_description" name="watermark_description" placeholder="<?php esc_attr_e('Optional description for this watermark', 'ultimate-watermark'); ?>" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-tab-content" id="tab-appearance">
                                    <div class="form-section">
                                        <h4><?php esc_html_e('Position & Appearance', 'ultimate-watermark'); ?></h4>
                                        
                                        <div class="form-row">
                                            <label for="watermark_position"><?php esc_html_e('Position', 'ultimate-watermark'); ?></label>
                                            <select id="watermark_position" name="watermark_position">
                                                <option value="bottom-right"><?php esc_html_e('Bottom Right', 'ultimate-watermark'); ?></option>
                                                <option value="bottom-left"><?php esc_html_e('Bottom Left', 'ultimate-watermark'); ?></option>
                                                <option value="top-right"><?php esc_html_e('Top Right', 'ultimate-watermark'); ?></option>
                                                <option value="top-left"><?php esc_html_e('Top Left', 'ultimate-watermark'); ?></option>
                                                <option value="center"><?php esc_html_e('Center', 'ultimate-watermark'); ?></option>
                                                <option value="top-center"><?php esc_html_e('Top Center', 'ultimate-watermark'); ?></option>
                                                <option value="bottom-center"><?php esc_html_e('Bottom Center', 'ultimate-watermark'); ?></option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_opacity"><?php esc_html_e('Opacity', 'ultimate-watermark'); ?></label>
                                            <div class="range-input">
                                                <input type="range" id="watermark_opacity" name="watermark_opacity" value="50" min="10" max="100">
                                                <span class="range-value">50%</span>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_margin"><?php esc_html_e('Margin from edges', 'ultimate-watermark'); ?></label>
                                            <input type="number" id="watermark_margin" name="watermark_margin" value="20" min="0" max="100">
                                            <span class="unit">px</span>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_rotation"><?php esc_html_e('Rotation', 'ultimate-watermark'); ?></label>
                                            <div class="range-input">
                                                <input type="range" id="watermark_rotation" name="watermark_rotation" value="0" min="-45" max="45">
                                                <span class="range-value">0°</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-tab-content" id="tab-advanced">
                                    <div class="form-section">
                                        <h4><?php esc_html_e('Advanced Settings', 'ultimate-watermark'); ?></h4>
                                        
                                        <div class="form-row">
                                            <label for="watermark_quality"><?php esc_html_e('Image Quality', 'ultimate-watermark'); ?></label>
                                            <select id="watermark_quality" name="watermark_quality">
                                                <option value="90"><?php esc_html_e('High (90%)', 'ultimate-watermark'); ?></option>
                                                <option value="80" selected><?php esc_html_e('Medium (80%)', 'ultimate-watermark'); ?></option>
                                                <option value="70"><?php esc_html_e('Low (70%)', 'ultimate-watermark'); ?></option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_auto_size"><?php esc_html_e('Auto Size', 'ultimate-watermark'); ?></label>
                                            <select id="watermark_auto_size" name="watermark_auto_size">
                                                <option value="percentage"><?php esc_html_e('Percentage of image', 'ultimate-watermark'); ?></option>
                                                <option value="fixed"><?php esc_html_e('Fixed size', 'ultimate-watermark'); ?></option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label for="watermark_size_percentage"><?php esc_html_e('Size Percentage', 'ultimate-watermark'); ?></label>
                                            <div class="range-input">
                                                <input type="range" id="watermark_size_percentage" name="watermark_size_percentage" value="20" min="5" max="50">
                                                <span class="range-value">20%</span>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label>
                                                <input type="checkbox" id="watermark_repeat" name="watermark_repeat" value="1">
                                                <?php esc_html_e('Repeat watermark across image', 'ultimate-watermark'); ?>
                                            </label>
                                        </div>
                                        
                                        <div class="form-row">
                                            <label>
                                                <input type="checkbox" id="watermark_auto_apply" name="watermark_auto_apply" value="1">
                                                <?php esc_html_e('Auto-apply to new uploads', 'ultimate-watermark'); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Right Side - Preview Sidebar -->
                        <div class="preview-sidebar">
                            <div class="preview-header">
                                <h4><?php esc_html_e('Live Preview', 'ultimate-watermark'); ?></h4>
                                <div class="preview-controls">
                                    <button type="button" class="btn btn-sm btn-secondary" id="refresh-preview">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php esc_html_e('Refresh', 'ultimate-watermark'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="preview-container">
                                <div class="preview-image" id="ultimate-watermark-preview-image">
                                    <img src="<?php echo esc_url(ULTIMATE_WATERMARK_URL . 'assets/images/preview-image.jpg'); ?>" alt="<?php esc_attr_e('Preview Image', 'ultimate-watermark'); ?>">
                                    <div class="preview-watermark" id="ultimate-watermark-preview-watermark"></div>
                                </div>
                            </div>
                            
                            <div class="preview-info">
                                <div class="preview-stats">
                                    <div class="stat-item">
                                        <span class="stat-label"><?php esc_html_e('Position:', 'ultimate-watermark'); ?></span>
                                        <span class="stat-value" id="preview-position"><?php esc_html_e('Bottom Right', 'ultimate-watermark'); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label"><?php esc_html_e('Opacity:', 'ultimate-watermark'); ?></span>
                                        <span class="stat-value" id="preview-opacity">50%</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label"><?php esc_html_e('Size:', 'ultimate-watermark'); ?></span>
                                        <span class="stat-value" id="preview-size">100px</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <div class="footer-left">
                        <button type="button" class="btn btn-secondary" id="modal-cancel">
                            <?php esc_html_e('Cancel', 'ultimate-watermark'); ?>
                        </button>
                    </div>
                    <div class="footer-right">
                        <button type="button" class="btn btn-secondary" id="save-draft">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Save Draft', 'ultimate-watermark'); ?>
                        </button>
                        <button type="submit" form="ultimate-watermark-form" class="btn btn-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Create Watermark', 'ultimate-watermark'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
