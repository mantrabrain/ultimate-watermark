/**
 * Ultimate Watermark - Add Watermark Page JavaScript
 * 
 * Handles the add watermark page functionality with live preview
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
/* i18n: falls back to the original text when wp.i18n is unavailable. */
window.wp = window.wp || {};
window.wp.i18n = window.wp.i18n || {
    __: function (text) { return text; },
    _n: function (single, plural, number) { return number === 1 ? single : plural; },
    sprintf: function (format) {
        var args = Array.prototype.slice.call(arguments, 1), i = 0;
        return String(format).replace(/%(\d+\$)?[sd]/g, function (match, position) {
            return String(position ? args[parseInt(position, 10) - 1] : args[i++]);
        });
    }
};

(function($) {
    'use strict';

    const AddWatermarkPage = {
        
        // Debounce timer for preview updates
        previewUpdateTimer: null,
        
        // Debounce timer for position selector
        positionUpdateTimer: null,
        
        // Flag to prevent multiple initial previews
        initialPreviewGenerated: false,
        
        // Flag to track if initial preview is being generated
        generatingInitialPreview: false,
        
        /**
         * Initialize the add watermark page
         */
        init: function() {
            this.bindEvents();
            this.initForm();
            this.initPreview();
            this.initWatermarkPreview();
            this.initFormSubmission();
            
            // Initialize position selector with a small delay to ensure DOM is ready
            setTimeout(() => {
                this.initPositionSelector();
            }, 100);
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form tabs
            $(document).on('click', '.form-tab', function(e) {
                this.switchTab(e);
            }.bind(this));
            
            // Form interactions - single event handlers
            $(document).on('change', 'input[name="watermark_type"]', this.toggleWatermarkType.bind(this));
            $(document).on('change', '#watermark_size_type', this.toggleSizeFields.bind(this));
            $(document).on('change', '#watermark_on', this.togglePostTypesSelection.bind(this));
            // Re-evaluate data-condition rows/sections whenever a field they reference changes
            // (core fields, tiling, Pro's on-the-fly display options, …).
            $(document).on('change', '#ultimate-watermark-form input, #ultimate-watermark-form select', function(e) {
                const name = $(e.target).attr('name');
                if (!name) {
                    return;
                }
                const referenced = $('[data-condition]').filter(function() {
                    return String($(this).data('condition') || '').indexOf(name) !== -1;
                }).length > 0;
                if (referenced) {
                    this.showConditionalFields();
                }
            }.bind(this));
            $(document).on('input', '#watermark_opacity', function(e) {
                if ($(e.target).length > 0) {
                    this.updateOpacityValue.call(this, e);
                }
            }.bind(this));
            $(document).on('input', '#watermark_rotation', function(e) {
                if ($(e.target).length > 0) {
                    this.updateRotationValue.call(this, e);
                }
            }.bind(this));
            $(document).on('input', '#watermark_scale_percentage', function(e) {
                if ($(e.target).length > 0) {
                    this.updateSizePercentageValue.call(this, e);
                }
            }.bind(this));
            
            // Form field changes for live preview (debounced for performance) - removed sidebar preview
            // $(document).on('input change', '#watermark_text, #watermark_font_size, #watermark_color, #watermark_font_family, #watermark_font_weight, #watermark_position, #watermark_margin, #watermark_image_size, #watermark_scale_percentage, #watermark_quality, #watermark_offset_x, #watermark_offset_y', this.debouncedUpdatePreview.bind(this));
            
            // Settings changes
            $(document).on('input', '#backup_quality', function(e) {
                if ($(e.target).length > 0) {
                    this.updateBackupQualityValue.call(this, e);
                }
            }.bind(this));
            $(document).on('input', '#watermark_quality', function(e) {
                if ($(e.target).length > 0) {
                    this.updateWatermarkQualityValue.call(this, e);
                }
            }.bind(this));
            
            // Media library upload
            $(document).on('click', '#watermark-upload-area', this.openMediaLibrary.bind(this));
            
            // Form submission
            $(document).on('submit', '#ultimate-watermark-form', this.handleFormSubmit.bind(this));

            // A field on a hidden tab can fail the browser's validation; the
            // browser cannot show its message there and Save seems to do
            // nothing. Open that tab and show the message on the field.
            const form = document.getElementById('ultimate-watermark-form');
            if (form) {
                let handlingInvalid = false;
                form.addEventListener('invalid', (event) => {
                    if (handlingInvalid) {
                        return;
                    }
                    const $pane = $(event.target).closest('.form-tab-content');
                    if (!$pane.length || $pane.hasClass('active')) {
                        return;
                    }
                    handlingInvalid = true;
                    const tab = String($pane.attr('id') || '').replace(/^tab-/, '');
                    $('.form-tab[data-tab="' + tab + '"]').first().trigger('click');
                    window.setTimeout(() => {
                        event.target.reportValidity();
                        handlingInvalid = false;
                    }, 50);
                }, true);
            }
            
            // Save draft
        
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
        },

        /**
         * Initialize form functionality
         */
        initForm: function() {
            // Check if image field has a value on page load
            const imageField = $('input[name="watermark_image_id"]');
            const imageValue = imageField.val();
            
            // Initialize color picker
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker({
                    change: function(event, ui) {
                        // Trigger preview update when color changes
                        this.debouncePreviewUpdate();
                    }.bind(this)
                });
            }
            
            // Add change event listener for font style field
            $(document).on('change', '#watermark_font_style', function() {
                AddWatermarkPage.debouncePreviewUpdate();
            });
            
            // Initialize form field visibility with multiple attempts to ensure it works
            this.initializeConditionalFields();
            
            // Set initial preview - removed sidebar preview
            // this.updatePreview();
        },

        /**
         * Initialize form submission
         */
        initFormSubmission: function() {
            // Form submission is handled by WordPress admin form submission
            // No additional JavaScript handling needed
        },

        /**
         * Initialize conditional fields - simple and reliable
         */
        initializeConditionalFields: function() {
            // Wait for DOM to be ready, then initialize
            setTimeout(() => {
                this.showConditionalFields();
            }, 100);
        },

        /**
         * Initialize preview functionality
         */
        initPreview: function() {
            // Set initial preview values - removed sidebar preview
            // this.updatePreviewStats();
        },

        /**
         * Manual trigger for debugging - can be called from console
         */
        debugConditionalFields: function() {
            
            const conditionalFields = $('[data-condition]');
            
            conditionalFields.each(function(index) {
                const $field = $(this);
                const condition = $field.data('condition');
                const isVisible = $field.is(':visible');
                const hasHidden = $field.hasClass('hidden');
            });
            
            // Check form values
            const watermarkType = $('input[name="watermark_type"]:checked').val();
            const sizeType = $('#watermark_size_type').val();
            const watermarkOn = $('#watermark_on').val();
            
            // Specific check for scaled fields
            const scaledFields = $('[data-condition*="scaled"]');
            scaledFields.each(function(index) {
                // Scaled field processing
            });
            
            // Force re-initialization
            this.toggleWatermarkType();
            this.toggleSizeFields();
            this.togglePostTypesSelection();
        },

        /**
         * Switch form tab
         */
        switchTab: function(e) {
            // Tabs are links (so they work without JS), but every tab lives in the
            // same form: switch client-side so unsaved changes are never lost.
            const $this = $(e.currentTarget || e.target).closest('.form-tab');
            const tab = $this.attr('data-tab');

            if (!tab || !$('#tab-' + tab).length) {
                return;
            }

            e.preventDefault();

            $('.form-tab').removeClass('active').attr('aria-selected', 'false');
            $('.form-tab-content').removeClass('active');

            $this.addClass('active').attr('aria-selected', 'true');
            $('#tab-' + tab).addClass('active');

            // Keep the URL in sync so a reload reopens the same tab.
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                window.history.replaceState(null, '', url.toString());
            }

            this.showConditionalFields();
        },

        /**
         * Toggle watermark type sections
         */
        toggleWatermarkType: function() {
            this.showConditionalFields();
        },

        /**
         * Update opacity value display
         */
        updateOpacityValue: function(e) {
            const $this = $(e.target);
            if ($this.length === 0) return;
            
            const opacity = $this.val() || 0;
            $this.siblings('.range-value').text(opacity + '%');
            // Removed sidebar preview
            // this.updatePreview();
        },

        /**
         * Update rotation value display
         */
        updateRotationValue: function(e) {
            const $this = $(e.target);
            if ($this.length === 0) return;
            
            const rotation = $this.val() || 0;
            $this.siblings('.range-value').text(rotation + '°');
            // Removed sidebar preview
            // this.updatePreview();
        },

        /**
         * Update size percentage value display
         */
        updateSizePercentageValue: function(e) {
            const $this = $(e.target);
            if ($this.length === 0) return;
            const percentage = $this.val() || 0;
            $this.siblings('.range-value').text(percentage + '%');
            // Removed sidebar preview
            // this.updatePreview();
        },

        /**
         * Toggle size fields based on size type
         */
        toggleSizeFields: function() {
            this.showConditionalFields();
        },

        /**
         * Simple dynamic conditional field system
         */
        showConditionalFields: function() {
            
            // Get current form values
            const formValues = {
                watermark_type: $('input[name="watermark_type"]:checked').val(),
                watermark_size_type: $('#watermark_size_type').val(),
                watermark_on: $('#watermark_on').val(),
                enable_conditional_rules: $('input[name="enable_conditional_rules"]:checked').val() || '0'
            };

            // Any other field referenced by a condition (e.g. watermark_tile).
            $('[data-condition]').each(function() {
                const fieldName = String($(this).data('condition') || '').split(' === ')[0].trim();
                if (!fieldName || Object.prototype.hasOwnProperty.call(formValues, fieldName)) {
                    return;
                }
                const $input = $('#ultimate-watermark-form [name="' + fieldName + '"]');
                if (!$input.length) {
                    return;
                }
                const type = $input.first().attr('type');
                if (type === 'checkbox') {
                    formValues[fieldName] = $input.filter(':checked').length ? '1' : '0';
                } else if (type === 'radio') {
                    formValues[fieldName] = $input.filter(':checked').val();
                } else {
                    formValues[fieldName] = $input.val();
                }
            });
            
            // Debug: Log form values
            
            
            // Check if conditional fields exist
            const conditionalFields = $('[data-condition]');
            
            if (conditionalFields.length === 0) {
                return;
            }
            
            // Process each conditional field
            conditionalFields.each(function(index) {
                const $field = $(this);
                const condition = $field.data('condition');
                
                // Debug: Log conditional field processing
                if (condition && condition.includes('watermark_type')) {
                }
                
                // Process conditional field logic here
                if (condition) {
                    let shouldShow = false;
                    
                    // Check if condition has OR operator
                    if (condition.includes(' || ')) {
                        // Handle OR conditions: "field === 'value1' || field === 'value2'"
                        const orParts = condition.split(' || ');
                        shouldShow = orParts.some(function(orCondition) {
                            const parts = orCondition.trim().split(' === ');
                            if (parts.length === 2) {
                                const fieldName = parts[0].trim();
                                const expectedValue = parts[1].trim().replace(/['"]/g, '');
                                const currentValue = formValues[fieldName];
                                return currentValue === expectedValue;
                            }
                            return false;
                        });
                    } else {
                        // Handle simple condition: "field_name === 'value'"
                        const parts = condition.split(' === ');
                        if (parts.length === 2) {
                            const fieldName = parts[0].trim();
                            const expectedValue = parts[1].trim().replace(/['"]/g, '');
                            const currentValue = formValues[fieldName];
                            shouldShow = currentValue === expectedValue;
                        }
                    }
                    
                    // Show/hide based on condition result
                    if (shouldShow) {
                        $field.removeClass('hidden');
                    } else {
                        $field.addClass('hidden');
                    }
                }
            });
        },

        /**
         * Toggle post types selection based on watermark on setting
         */
        togglePostTypesSelection: function() {
            this.showConditionalFields();
        },

        /**
         * Update backup quality value display
         */
        updateBackupQualityValue: function(e) {
            const $this = $(e.target);
            if ($this.length === 0) return;
            
            const quality = $this.val() || 0;
            $this.siblings('.range-value').text(quality + '%');
        },

        updateWatermarkQualityValue: function(e) {
            const $this = $(e.target);
            if ($this.length === 0) return;
            
            const quality = $this.val() || 0;
            $this.siblings('.range-value').text(quality + '%');
        },

        /**
         * Open WordPress media library
         */
        openMediaLibrary: function() {
            
            // Check if wp.media is available
            if (typeof wp === 'undefined' || !wp.media) {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), wp.i18n.__('WordPress media library is not available.', 'ultimate-watermark'));
                return;
            }
            

            try {
                // Show loading state
                this.showPreviewLoading();

                // Create media frame
                const mediaFrame = wp.media({
                    title: wp.i18n.__('Select Watermark Image', 'ultimate-watermark'),
                    button: {
                        text: wp.i18n.__('Use this image', 'ultimate-watermark')
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });

                // Handle selection
                mediaFrame.on('select', function() {
                    const attachment = mediaFrame.state().get('selection').first().toJSON();
                    
                    if (attachment && attachment.id) {
                        // Store the attachment ID - use dynamic selector
                        const imageIdField = $('input[name="watermark_image_id"]');
                        imageIdField.val(attachment.id);
                        
                        
                        // Show preview with loading animation
                        const preview = $('#watermark-image-preview');
                        preview.html('<img src="' + attachment.url + '" alt="Preview">').show();
                        
                        // Update preview with animation - removed sidebar preview
                        setTimeout(() => {
                            this.hidePreviewLoading();
                            // this.updatePreview();
                        }, 300);
                    }
                }.bind(this));

                // Handle close without selection
                mediaFrame.on('close', function() {
                    this.hidePreviewLoading();
                }.bind(this));

                // Open media frame
                mediaFrame.open();
            } catch (error) {
                this.hidePreviewLoading();
            }
        },

        /**
         * Show preview loading state
         */
        showPreviewLoading: function() {
            const previewImage = $('#ultimate-watermark-preview-image');
            previewImage.addClass('preview-loading');
        },

        /**
         * Hide preview loading state
         */
        hidePreviewLoading: function() {
            const previewImage = $('#ultimate-watermark-preview-image');
            previewImage.removeClass('preview-loading');
        },



        /**
         * Debounced preview update for better performance
         */
        debouncedUpdatePreview: function() {
            clearTimeout(this.previewUpdateTimer);
            this.previewUpdateTimer = setTimeout(() => {
                this.updatePreview();
            }, 150);
        },

        /**
         * Update live preview - REMOVED SIDEBAR PREVIEW
         */
        /* updatePreview: function() {
            // Cache DOM queries for better performance
            const $watermarkType = $('input[name="watermark_type"]:checked');
            const $watermarkText = $('#watermark_text');
            const $fontSize = $('#watermark_font_size');
            const $color = $('#watermark_color');
            const $fontFamily = $('#watermark_font_family');
            const $fontWeight = $('#watermark_font_weight');
            const $position = $('#watermark_position');
            const $opacity = $('#watermark_opacity');
            const $margin = $('#watermark_margin');
            const $rotation = $('#watermark_rotation');
            const $imageSize = $('#watermark_image_size');
            const $offsetX = $('#watermark_offset_x');
            const $offsetY = $('#watermark_offset_y');
            
            const watermarkType = $watermarkType.val();
            const text = $watermarkText.val();
            const fontSize = $fontSize.val();
            const color = $color.val();
            const fontFamily = $fontFamily.val();
            const fontWeight = $fontWeight.val();
            const position = $position.val();
            const opacity = $opacity.val();
            const margin = $margin.val();
            const rotation = $rotation.val();
            const imageSize = $imageSize.val();
            const offsetX = $offsetX.val() || 0;
            const offsetY = $offsetY.val() || 0;
            
            const previewWatermark = $('#ultimate-watermark-preview-watermark');
            
            if (watermarkType === 'text') {
                previewWatermark.html('<span style="' +
                    'font-size: ' + fontSize + 'px; ' +
                    'color: ' + color + '; ' +
                    'font-family: ' + fontFamily + '; ' +
                    'font-weight: ' + fontWeight + '; ' +
                    'opacity: ' + (opacity / 100) + '; ' +
                    'transform: rotate(' + rotation + 'deg); ' +
                    'position: absolute; ' +
                    'pointer-events: none; ' +
                    'white-space: nowrap; ' +
                    'z-index: 10;' +
                    '">' + text + '</span>');
            } else {
                // For image watermarks, check if an image is selected
                const imageId = $('#watermark_image_id').val();
                const sizeType = $('#watermark_size_type').val();
                let width = imageSize;
                let height = imageSize;
                
                if (sizeType === 'custom') {
                    width = $('#watermark_custom_width').val() || 100;
                    height = $('#watermark_custom_height').val() || 100;
                } else if (sizeType === 'scaled') {
                    const scale = $('#watermark_scale_percentage').val() || 80;
                    width = (300 * scale / 100); // Assuming preview image is 300px wide
                    height = width; // Keep aspect ratio
                }
                
                if (imageId) {
                    // Get the image URL from the preview
                    const imageUrl = $('#watermark-image-preview img').attr('src');
                    if (imageUrl) {
                        previewWatermark.html('<img src="' + imageUrl + '" style="' +
                            'width: ' + width + 'px; ' +
                            'height: ' + height + 'px; ' +
                            'opacity: ' + (opacity / 100) + '; ' +
                            'transform: rotate(' + rotation + 'deg); ' +
                            'position: absolute; ' +
                            'pointer-events: none; ' +
                            'z-index: 10; ' +
                            'object-fit: contain;' +
                            '">');
                    } else {
                        previewWatermark.html('<div style="' +
                            'width: ' + width + 'px; ' +
                            'height: ' + height + 'px; ' +
                            'opacity: ' + (opacity / 100) + '; ' +
                            'transform: rotate(' + rotation + 'deg); ' +
                            'position: absolute; ' +
                            'pointer-events: none; ' +
                            'z-index: 10; ' +
                            'background: #ccc; ' +
                            'border: 1px solid #999;' +
                            '"></div>');
                    }
                } else {
                    previewWatermark.html('<div style="' +
                        'width: ' + width + 'px; ' +
                        'height: ' + height + 'px; ' +
                        'opacity: ' + (opacity / 100) + '; ' +
                        'transform: rotate(' + rotation + 'deg); ' +
                        'position: absolute; ' +
                        'pointer-events: none; ' +
                        'z-index: 10; ' +
                        'background: #ccc; ' +
                        'border: 1px solid #999;' +
                        '"></div>');
                }
            }
            
            // Position the watermark with offset
            this.positionWatermark(position, margin, offsetX, offsetY);
            
            // Update preview stats
            this.updatePreviewStats();
        }, */

        /**
         * Position the watermark - REMOVED SIDEBAR PREVIEW
         */
        /* positionWatermark: function(position, margin, offsetX = 0, offsetY = 0) {
            const previewWatermark = $('#ultimate-watermark-preview-watermark');
            const previewImage = $('#ultimate-watermark-preview-image');
            
            let top = 'auto';
            let right = 'auto';
            let bottom = 'auto';
            let left = 'auto';
            let transform = 'none';
            
            // Calculate final positions with offsets
            const finalMargin = parseInt(margin) + parseInt(offsetY);
            const finalMarginX = parseInt(margin) + parseInt(offsetX);
            
            switch (position) {
                case 'top-left':
                    top = finalMargin + 'px';
                    left = finalMarginX + 'px';
                    break;
                case 'top-center':
                    top = finalMargin + 'px';
                    left = '50%';
                    transform = 'translateX(-50%)';
                    break;
                case 'top-right':
                    top = finalMargin + 'px';
                    right = finalMarginX + 'px';
                    break;
                case 'middle-left':
                    top = '50%';
                    left = finalMarginX + 'px';
                    transform = 'translateY(-50%)';
                    break;
                case 'center':
                    top = '50%';
                    left = '50%';
                    transform = 'translate(-50%, -50%)';
                    break;
                case 'middle-right':
                    top = '50%';
                    right = finalMarginX + 'px';
                    transform = 'translateY(-50%)';
                    break;
                case 'bottom-left':
                    bottom = finalMargin + 'px';
                    left = finalMarginX + 'px';
                    break;
                case 'bottom-center':
                    bottom = finalMargin + 'px';
                    left = '50%';
                    transform = 'translateX(-50%)';
                    break;
                case 'bottom-right':
                default:
                    bottom = finalMargin + 'px';
                    right = finalMarginX + 'px';
                    break;
            }
            
            previewWatermark.css({
                'top': top,
                'right': right,
                'bottom': bottom,
                'left': left,
                'transform': transform
            });
        }, */

        /**
         * Update preview stats
         */


        /**
         * Refresh preview
         */
        refreshPreview: function(e) {
            e.preventDefault();
            this.updatePreview();
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            // Validate form
            if (!this.validateForm()) {
                return false;
            }
            
            // Show loading state
            this.showLoadingState();
            
            // Prepare form data
            const formData = this.prepareFormData();
            
            // Submit via AJAX
            $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    this.hideLoadingState();
                    
                    if (response.success) {
                        // Show success message
                        UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), response.data.message || wp.i18n.__('Watermark saved successfully!', 'ultimate-watermark'));
                        
                        // Only redirect if redirect_url is provided (new watermarks only)
                        if (response.data.redirect_url) {
                            setTimeout(() => {
                                window.location.href = response.data.redirect_url;
                            }, 1500);
                        }
                    } else {
                        // Show error message
                        UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), response.data.message || wp.i18n.__('Failed to save watermark.', 'ultimate-watermark'));
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoadingState();
                    
                    // Check if this is a watermark limit error (403 status)
                    if (xhr.status === 403 && xhr.responseJSON) {
                        const response = xhr.responseJSON;
                        
                        if (response.data && response.data.upgrade_required) {
                            // Show upgrade notice modal
                            this.showUpgradeNotice(response.data);
                            return;
                        }
                    }
                    
                    // Show generic error message
                    const errorMessage = xhr.responseJSON?.data?.message || wp.i18n.__('An error occurred while saving the watermark.', 'ultimate-watermark');
                    UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), errorMessage);
                    
                }
            });
        },

        /**
         * Show upgrade notice modal when watermark limit is reached
         */
        showUpgradeNotice: function(data) {
            const modal = `
                <div id="watermark-limit-modal" class="ultimate-watermark-modal-overlay" style="display: flex;">
                    <div class="ultimate-watermark-modal upgrade-modal">
                        <div class="modal-header">
                            <h2><span class="dashicons dashicons-lock"></span> ${data.message || wp.i18n.__('Watermark Limit Reached', 'ultimate-watermark')}</h2>
                            <button class="modal-close" onclick="jQuery('#watermark-limit-modal').remove()">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="upgrade-notice-content">
                                <div class="limit-info">
                                    <p class="limit-message">${data.upgrade_message || wp.i18n.__('You have reached the watermark limit for the free version.', 'ultimate-watermark')}</p>
                                    <div class="limit-stats">
                                        <span class="stat-item">
                                            <strong>Current:</strong> ${data.current_count || 1} watermark${data.current_count > 1 ? 's' : ''}
                                        </span>
                                        <span class="stat-item">
                                            <strong>Free Limit:</strong> ${data.limit || 1} watermark
                                        </span>
                                    </div>
                                </div>
                                <div class="pro-features">
                                    <h3>✨ Upgrade to Pro for:</h3>
                                    <ul>
                                        <li><span class="dashicons dashicons-yes"></span> <strong>Unlimited Watermarks</strong> - Create as many watermark templates as you need</li>
                                        <li><span class="dashicons dashicons-yes"></span> <strong>Advanced Rules Engine</strong> - Complex conditional watermarking</li>
                                        <li><span class="dashicons dashicons-yes"></span> <strong>WooCommerce Integration</strong> - Per-product watermarks</li>
                                        <li><span class="dashicons dashicons-yes"></span> <strong>Frontend Watermarking</strong> - Apply watermarks on frontend uploads</li>
                                        <li><span class="dashicons dashicons-yes"></span> <strong>On-the-fly Watermarking</strong> - Dynamic watermark display</li>
                                        <li><span class="dashicons dashicons-yes"></span> <strong>Priority Support</strong> - Get help when you need it</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="button" onclick="jQuery('#watermark-limit-modal').remove()">Maybe Later</button>
                            <a href="${data.upgrade_url || 'https://mantrabrain.com/plugins/ultimate-watermark#pricing'}" target="_blank" class="button button-primary button-hero uw-pro-cta">
                                <span class="dashicons dashicons-unlock"></span> Upgrade to Pro
                            </a>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#watermark-limit-modal').remove();
            
            // Append modal to body
            $('body').append(modal);
            
            // Close on overlay click
            $('#watermark-limit-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).remove();
                }
            });
            
            // Close on escape key
            $(document).on('keydown.watermarkLimit', function(e) {
                if (e.key === 'Escape') {
                    $('#watermark-limit-modal').remove();
                    $(document).off('keydown.watermarkLimit');
                }
            });
        },

        /**
         * Show loading state
         */
        showLoadingState: function() {
            const $submitBtn = $('button[type="submit"][form="ultimate-watermark-form"]');
            const originalText = $submitBtn.html();
            $submitBtn.data('original-text', originalText);
            $submitBtn.prop('disabled', true);
            $submitBtn.html('<span class="dashicons dashicons-update"></span> Saving...');
        },

        /**
         * Hide loading state
         */
        hideLoadingState: function() {
            const $submitBtn = $('button[type="submit"][form="ultimate-watermark-form"]');
            const originalText = $submitBtn.data('original-text');
            $submitBtn.prop('disabled', false);
            $submitBtn.html(originalText);
        },

        /**
         * Prepare form data for AJAX submission
         */
        prepareFormData: function() {
            const form = $('#ultimate-watermark-form')[0];
            const formData = new FormData(form);
            
            // Debug: Check if nonce field exists in the form
            const nonceField = form.querySelector('input[name="ultimate_watermark_nonce"]');
            
            // The form already contains the nonce field, so we don't need to add it manually
            // Just ensure the action is set
            formData.append('action', 'ultimate_watermark_save');
            
            // Debug: Log form data (can be removed in production)
            for (let [key, value] of formData.entries()) {
            }
            
            // Check if nonce is present
            const nonceValue = formData.get('ultimate_watermark_nonce');
            
            return formData;
        },

        /**
         * Validate form
         */
        validateForm: function() {
            const form = $('#ultimate-watermark-form')[0];
            const formData = new FormData(form);
            
            // Get required field values
            const name = formData.get('name');
            const watermarkType = formData.get('watermark_type');
            
            if (!name || name.trim() === '') {
                UWNotifications.error(wp.i18n.__('Validation Error', 'ultimate-watermark'), wp.i18n.__('Please enter a watermark name.', 'ultimate-watermark'));
                $('#name').focus();
                return false;
            }
            
            if (watermarkType === 'text') {
                const text = formData.get('watermark_text');
                if (!text || text.trim() === '') {
                    UWNotifications.error(wp.i18n.__('Validation Error', 'ultimate-watermark'), wp.i18n.__('Please enter watermark text.', 'ultimate-watermark'));
                    $('#watermark_text').focus();
                    return false;
                }
            } else {
                const imageId = formData.get('watermark_image_id');
                if (!imageId) {
                    UWNotifications.error(wp.i18n.__('Validation Error', 'ultimate-watermark'), wp.i18n.__('Please select a watermark image.', 'ultimate-watermark'));
                    $('#watermark-upload-area').click();
                    return false;
                }
            }
            
            return true;
        },

        /**
         * Detect whether we're editing an existing watermark or creating a
         * new one. Reads the hidden #watermark_id field that AddWatermarkPage
         * always emits — non-empty + > 0 means edit mode.
         */
        isEditMode: function() {
            var idVal = parseInt($('#watermark_id').val(), 10);
            return !isNaN(idVal) && idVal > 0;
        },

        /**
         * Show loading state — preserves the current submit-button label so
         * "Updating…" shows on edit and "Creating…" on create.
         */
        showLoadingState: function() {
            var $btn = $('button[type="submit"][form="ultimate-watermark-form"], #ultimate-watermark-form button[type="submit"]').first();
            if (!$btn.length) {
                $btn = $('button[type="submit"]').first();
            }
            // Cache the original markup once so we can restore it verbatim.
            if (typeof $btn.data('uw-original-html') === 'undefined') {
                $btn.data('uw-original-html', $btn.html());
            }
            var label = this.isEditMode() ? wp.i18n.__('Updating…', 'ultimate-watermark') : wp.i18n.__('Creating…', 'ultimate-watermark');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + label);
        },

        /**
         * Hide loading state — restores the cached "Create" / "Update"
         * label from when the page rendered.
         */
        hideLoadingState: function() {
            var $btn = $('button[type="submit"][form="ultimate-watermark-form"], #ultimate-watermark-form button[type="submit"]').first();
            if (!$btn.length) {
                $btn = $('button[type="submit"]').first();
            }
            var original = $btn.data('uw-original-html');
            if (typeof original === 'string' && original.length) {
                $btn.prop('disabled', false).html(original);
                return;
            }
            // Fallback: rebuild the right label from edit-mode detection.
            var label = this.isEditMode() ? wp.i18n.__('Update Watermark', 'ultimate-watermark') : wp.i18n.__('Create Watermark', 'ultimate-watermark');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + label);
        },


        /**
         * Save draft
         */
        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                e.preventDefault();
                $('#ultimate-watermark-form').trigger('submit');
            }
            
            // Ctrl/Cmd + Enter to submit
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                e.preventDefault();
                $('#ultimate-watermark-form').trigger('submit');
            }
        },

        /**
         * Initialize watermark preview system
         */
        initWatermarkPreview: function() {
            
            // Check library status
            this.checkLibraryStatus();
            
            // Generate initial preview with current form data AFTER page load via AJAX
            // This is NOT automatic watermarking - it's an AJAX call to generate preview
            setTimeout(() => {
                this.generateInitialPreview();
                
                // Bind preview update events AFTER initial preview is generated
                setTimeout(() => {
                    this.bindPreviewEvents();
                }, 100); // Small delay after initial preview
            }, 1000); // Increased delay to ensure everything is loaded
        },


        /**
         * Generate initial preview AFTER page load via AJAX
         */
        generateInitialPreview: function() {
            
            // Prevent multiple initial previews
            if (this.initialPreviewGenerated) {
                return;
            }
            
            this.initialPreviewGenerated = true;
            this.generatingInitialPreview = true;
            
            // Get current form data
            const formData = this.getFormData();
            
            // Check if AJAX variables are available
            if (typeof ultimate_watermark_ajax === 'undefined') {
                this.showPreviewError(wp.i18n.__('AJAX configuration not available', 'ultimate-watermark'));
                return;
            }
            
            if (!ultimate_watermark_ajax.ajax_url || !ultimate_watermark_ajax.nonce) {
                this.showPreviewError(wp.i18n.__('AJAX configuration incomplete', 'ultimate-watermark'));
                return;
            }
            
            // Show loading state
            this.showPreviewLoading();
            
            // Send AJAX request for initial preview (not automatic watermarking)
            const ajaxData = {
                action: 'ultimate_watermark_generate_preview',
                nonce: ultimate_watermark_ajax.nonce,
                ...formData
            };
            
            
            $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    this.generatingInitialPreview = false;
                    if (response && response.success) {
                        this.updatePreviewImage(response.data.preview_url);
                        this.updatePreviewStats(formData);
                        this.hidePreviewLoading();
                    } else {
                        this.showPreviewError(this.extractErrorMessage(response));
                    }
                },
                error: (xhr) => {
                    this.generatingInitialPreview = false;
                    this.showPreviewError(this.extractErrorMessage(xhr));
                }
            });
        },

        /**
         * Bind preview update events
         */
        bindPreviewEvents: function() {
            // Bind to ALL form fields dynamically - no static selectors needed
            $(document).on('change input keyup', '#ultimate-watermark-form input, #ultimate-watermark-form select, #ultimate-watermark-form textarea', this.debouncePreviewUpdate.bind(this));
            
            // Debug: Show which fields are being monitored
            this.debugMonitoredFields();
            
        },

        /**
         * Debug method to show which fields are being monitored
         */
        debugMonitoredFields: function() {
            const monitoredFields = [];
            $('#ultimate-watermark-form').find('input, select, textarea').each(function() {
                const $field = $(this);
                const fieldName = $field.attr('name');
                const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();
                if (fieldName) {
                    monitoredFields.push(`${fieldName} (${fieldType})`);
                }
            });
        },

        /**
         * Debounced preview update
         */
        debouncePreviewUpdate: function(event) {
            clearTimeout(this.previewUpdateTimer);
            
            // Log which field triggered the update
            if (event && event.target) {
                const fieldName = $(event.target).attr('name');
                const fieldValue = $(event.target).val();
            }
            
            // Only update preview if initial preview has been generated
            if (!this.initialPreviewGenerated) {
                return;
            }
            
            // Additional check: ensure we're not in the middle of generating initial preview
            if (this.generatingInitialPreview) {
                return;
            }
            
            // Check if this change is from position selector to avoid duplicate calls
            if (event && event.target && $(event.target).attr('name') === 'watermark_position') {
                // Check if any position option is currently processing
                if ($('.position-option.processing').length > 0) {
                    return;
                }
            }
            
            this.previewUpdateTimer = setTimeout(() => {
                this.updatePreview();
            }, 500); // 500ms delay
        },

        /**
         * Update watermark preview (for form field changes)
         */
        updatePreview: function() {
            const callId = Math.random().toString(36).substr(2, 9);
            
            // Ensure initial preview has been generated before allowing updates
            if (!this.initialPreviewGenerated) {
                return;
            }
            
            const formData = this.getFormData();
            
            // Show loading
            this.showPreviewLoading();
            
            // Send AJAX request
            const ajaxData = {
                action: 'ultimate_watermark_generate_preview',
                nonce: ultimate_watermark_ajax.nonce,
                ...formData
            };
            
            
            // Only the latest request may update the preview: an older, slower
            // response would show stale settings (or a file the server already
            // cleaned up).
            if (this.previewRequest && this.previewRequest.readyState !== 4) {
                this.previewRequest.abort();
            }
            const requestId = (this.previewRequestId = (this.previewRequestId || 0) + 1);

            this.previewRequest = $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (requestId !== this.previewRequestId) {
                        return;
                    }
                    if (response && response.success) {
                        this.updatePreviewImage(response.data.preview_url);
                        this.updatePreviewStats(formData);
                        this.hidePreviewLoading();
                    } else {
                        this.showPreviewError(this.extractErrorMessage(response));
                    }
                },
                error: (xhr, status) => {
                    if (status === 'abort' || requestId !== this.previewRequestId) {
                        return;
                    }
                    this.showPreviewError(this.extractErrorMessage(xhr));
                }
            });
        },

        /**
         * Pull a human-readable message out of whatever shape the response/xhr
         * actually has — wp_send_json_error returns {data: {message, code}},
         * jQuery hands us xhr objects on transport failure, etc.
         */
        extractErrorMessage: function(payload) {
            const fallback = (typeof ultimate_watermark_ajax !== 'undefined'
                && ultimate_watermark_ajax.strings
                && ultimate_watermark_ajax.strings.preview_error) || wp.i18n.__('Preview could not be generated. Please try again.', 'ultimate-watermark');

            if (!payload) {
                return fallback;
            }

            if (typeof payload === 'string') {
                return payload;
            }

            // jQuery xhr — try to parse the JSON body.
            if (payload.responseJSON) {
                return this.extractErrorMessage(payload.responseJSON);
            }

            if (payload.responseText) {
                try {
                    return this.extractErrorMessage(JSON.parse(payload.responseText));
                } catch (e) {
                    // ignore — fall through to other strategies
                }
            }

            if (payload.data) {
                if (typeof payload.data === 'string') {
                    return payload.data;
                }
                if (payload.data.message) {
                    return payload.data.message;
                }
            }

            if (payload.message) {
                return payload.message;
            }

            if (payload.statusText && payload.statusText !== 'error') {
                return payload.statusText;
            }

            return fallback;
        },

        /**
         * Get form data for preview - dynamically collect ALL form data
         */
        getFormData: function() {
            const formData = {};
            
            // First, ensure conditional fields are shown so we can collect their values
            this.showConditionalFields();
            
            // Collect form fields immediately (conditional fields should be shown now)
            return this.collectFormFields(formData);
        },
        
        collectFormFields: function(formData) {
            // Collect ALL form fields dynamically - INCLUDING HIDDEN FIELDS
            $('#ultimate-watermark-form').find('input, select, textarea').each(function() {
                const $field = $(this);
                const fieldName = $field.attr('name');
                const fieldType = $field.attr('type');
                const fieldTag = $field.prop('tagName').toLowerCase();
                
                // Debug: Log specific fields we're looking for
                if (fieldName && (fieldName.includes('font_style') || fieldName.includes('text_decoration'))) {
                }
                
                if (!fieldName) return; // Skip fields without names
                
                // Skip form submission fields that shouldn't be in preview data
                if (fieldName === 'action' || fieldName === 'ultimate_watermark_nonce' || fieldName === '_wp_http_referer') {
                    return;
                }
                
                let value = null;
                
                // Handle different field types
                if (fieldType === 'radio' || fieldType === 'checkbox') {
                    value = $field.is(':checked') ? $field.val() : null;
                } else if (fieldType === 'number') {
                    value = $field.val() ? parseFloat($field.val()) : null;
                } else if (fieldTag === 'select') {
                    value = $field.val();
                } else {
                    value = $field.val();
                }
                
                // Only include fields with values
                if (value !== null && value !== '') {
                    formData[fieldName] = value;
                }
            });
            
            // Only apply minimal defaults for truly required fields (not font-related)
            const criticalDefaults = {
                watermark_type: 'text',
                watermark_text: 'Watermark',
                watermark_color: '#000000',
                watermark_font_size: 24,
                watermark_font_family: 'Arial',
                watermark_position: 'bottom-right',
                watermark_offset_x: 10,
                watermark_offset_y: 10,
                watermark_opacity: 50
            };
            
            // Only apply defaults if field is completely missing
            Object.keys(criticalDefaults).forEach(key => {
                if (!(key in formData)) {
                    formData[key] = criticalDefaults[key];
                }
            });
            
            // Debug: Log form data to see what's being collected
            
            return formData;
        },

        /**
         * Update preview image
         */
        updatePreviewImage: function(previewUrl) {
            if (!previewUrl) {
                return;
            }

            const $previewImage = $('#ultimate-watermark-preview-image');
            const newSrc = previewUrl + (previewUrl.indexOf('?') > -1 ? '&' : '?') + 'v=' + Date.now();

            $previewImage.off('load.uw error.uw');
            $previewImage.on('load.uw', () => {
                $previewImage.removeClass('preview-image-loading preview-image-error');
            });
            $previewImage.on('error.uw', () => {
                $previewImage.addClass('preview-image-error');
                this.showPreviewError(this.extractErrorMessage(null));
            });

            $previewImage.addClass('preview-image-loading');
            $previewImage.attr('src', newSrc);
        },

        /**
         * Update preview statistics
         */
        updatePreviewStats: function(formData) {
            if (!formData) {
                return;
            }
            $('#preview-position').text(this.formatPosition(formData.watermark_position));
            $('#preview-opacity').text((formData.watermark_opacity || 0) + '%');
            $('#preview-size').text(this.formatSize(formData));
            $('#preview-rotation').text((formData.watermark_rotation || 0) + '°');
        },

        /**
         * Format position for display
         */
        formatPosition: function(position) {
            const positions = {
                'top-left': wp.i18n.__('Top Left', 'ultimate-watermark'),
                'top-center': wp.i18n.__('Top Center', 'ultimate-watermark'),
                'top-right': wp.i18n.__('Top Right', 'ultimate-watermark'),
                'center-left': wp.i18n.__('Center Left', 'ultimate-watermark'),
                'center': 'Center',
                'center-right': wp.i18n.__('Center Right', 'ultimate-watermark'),
                'bottom-left': wp.i18n.__('Bottom Left', 'ultimate-watermark'),
                'bottom-center': wp.i18n.__('Bottom Center', 'ultimate-watermark'),
                'bottom-right': wp.i18n.__('Bottom Right', 'ultimate-watermark')
            };
            return positions[position] || position || '—';
        },

        /**
         * Format size for display
         */
        formatSize: function(formData) {
            if ((formData.watermark_type || 'text') !== 'image') {
                return (formData.watermark_font_size || 24) + 'px';
            }
            if (formData.watermark_tile === '1' && formData.watermark_size_type === 'scaled') {
                return (formData.watermark_scale_percentage || 80) + '% · ' + wp.i18n.__('tiled', 'ultimate-watermark');
            }

            if (formData.watermark_size_type === 'scaled') {
                const percentage = formData.watermark_scale_percentage || 80;
                return percentage + '%';
            } else if (formData.watermark_size_type === 'custom') {
                const width = formData.watermark_custom_width || 100;
                const height = formData.watermark_custom_height || 100;
                return width + 'x' + height;
            } else {
                return wp.i18n.__('Original', 'ultimate-watermark');
            }
        },

        /**
         * Show preview loading
         */
        showPreviewLoading: function() {
            $('#preview-loading').show();
        },

        /**
         * Hide preview loading
         */
        hidePreviewLoading: function() {
            $('#preview-loading').hide();
        },

        /**
         * Show preview error
         */
        showPreviewError: function(message) {
            this.hidePreviewLoading();

            const finalMessage = (typeof message === 'string' && message.length)
                ? message
                : this.extractErrorMessage(message);

            if (typeof UWNotifications !== 'undefined' && UWNotifications.error) {
                UWNotifications.error(wp.i18n.__('Preview Error', 'ultimate-watermark'), finalMessage);
            } else if (typeof console !== 'undefined' && console.error) {
                console.error('[Ultimate Watermark] Preview Error:', finalMessage);
            }
        },


        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info') {
            // Remove existing toasts
            $('.ultimate-watermark-toast').remove();
            
            const toastClass = type === 'success' ? 'toast-success' : type === 'error' ? 'toast-error' : 'toast-info';
            const icon = type === 'success' ? 'dashicons-yes-alt' : type === 'error' ? 'dashicons-warning' : 'dashicons-info';
            
            const toast = $(`
                <div class="ultimate-watermark-toast ${toastClass}">
                    <span class="dashicons ${icon}"></span>
                    <span class="toast-message">${message}</span>
                    <button class="toast-close" type="button">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `);
            
            // Add to page
            $('body').append(toast);
            
            // Show with animation
            setTimeout(() => {
                toast.addClass('show');
            }, 100);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 5000);
            
            // Close button handler
            toast.find('.toast-close').on('click', () => {
                toast.removeClass('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            });
        },

        /**
         * Check library status
         */
        checkLibraryStatus: function() {
            $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ultimate_watermark_get_library_status',
                    nonce: ultimate_watermark_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.handleLibraryStatus(response.data);
                    }
                },
                error: (xhr, status, error) => {
                }
            });
        },

        /**
         * Handle library status response
         */
        handleLibraryStatus: function(data) {
            if (!data.is_available) {
                this.showLibraryWarning();
            } else {
            }
        },

        /**
         * Show library warning
         */
        showLibraryWarning: function() {
            const warningHtml = `
                <div class="notice notice-warning">
                    <p><strong>Warning:</strong> No image processing library available. Please install GD or Imagick extension to use watermark features.</p>
                </div>
            `;
            $('.preview-sidebar').prepend(warningHtml);
        },

        /**
         * Initialize position selector
         */
        initPositionSelector: function() {
            
            // Check if position selector elements exist
            const $positionOptions = $('.position-option');
            
            if ($positionOptions.length === 0) {
                const $container = $('.position-selector-container');
            } else {
                // Debug the first position option
                const $firstOption = $positionOptions.first();
            }
            
            // Remove any existing event handlers to prevent duplicates
            $(document).off('click', '.position-option');
            $('.position-option').off('click');
            
            // Bind single click event handler using event delegation
            $(document).on('click', '.position-option', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                AddWatermarkPage.handlePositionClick($(this));
            });

            // Keyboard: Enter/Space selects a position (the grid is a radio group).
            $(document).off('keydown.uwpos').on('keydown.uwpos', '.position-option', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    AddWatermarkPage.handlePositionClick($(this));
                }
            });
        },

        /**
         * Handle position click
         */
        handlePositionClick: function($option) {
            
            // Prevent multiple rapid clicks on the same element
            if ($option.hasClass('processing')) {
                return;
            }
            
            $option.addClass('processing');
            
            
            const $container = $option.closest('.position-selector-container');
            
            const $hiddenInput = $container.find('input[type="hidden"]');
            
            // Fallback: try to find the hidden input by name or ID
            let $fallbackInput = $container.find('input[name="watermark_position"]');
            if ($fallbackInput.length === 0) {
                $fallbackInput = $('#watermark_position');
            }
            
            const value = $option.data('value') || $option.attr('data-value');
            const label = $option.data('label') || $option.attr('data-label');
            
            
            
            // Use fallback input if primary not found
            const $finalInput = $hiddenInput.length > 0 ? $hiddenInput : $fallbackInput;
            
            // Remove selected class from all options
            $container.find('.position-option').removeClass('selected');
            
            // Add selected class to clicked option
            $option.addClass('selected');
            $container.find('.position-option').attr('aria-checked', 'false');
            $option.attr('aria-checked', 'true');
            
            // Update hidden input value
            if ($finalInput.length > 0) {
                $finalInput.val(value);
            } else {
            }
            
            // Trigger change event for form validation
            if ($finalInput.length > 0) {
                $finalInput.trigger('change');
            }
            
            // Update preview if watermark preview is enabled (with debouncing)
            if (typeof this.updatePreview === 'function') {
                
                // Clear existing timer
                if (this.positionUpdateTimer) {
                    clearTimeout(this.positionUpdateTimer);
                }
                
                // Set new timer
                this.positionUpdateTimer = setTimeout(() => {
                    this.updatePreview();
                    // Remove processing class after preview update with additional delay
                    setTimeout(() => {
                        $option.removeClass('processing');
                    }, 100); // Additional 100ms delay
                }, 300); // 300ms debounce
            } else {
                // Remove processing class immediately if no preview update
                $option.removeClass('processing');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AddWatermarkPage.init();
    });

    // Make AddWatermarkPage available globally
    window.AddWatermarkPage = AddWatermarkPage;
    
    // Expose test function globally
    window.testConditionalFields = function() {
        AddWatermarkPage.testConditionalFields();
    };
    
    // Expose debug function globally
    window.debugUniversalFields = function() {
        AddWatermarkPage.showConditionalFields();
    };
    
    // Expose debug function globally for console access
    window.debugWatermarkFields = function() {
        AddWatermarkPage.debugConditionalFields();
    };
    
    // Expose specific scaled field debug function
    window.debugScaledFields = function() {
        const sizeType = $('#watermark_size_type').val();
        
        const scaledFields = $('[data-condition*="scaled"]');
        
        scaledFields.each(function(index) {
            const $field = $(this);
            // Process scaled field
        });
        
        if (sizeType === 'scaled') {
            scaledFields.removeClass('hidden');
            scaledFields.each(function(index) {
                // Process scaled field visibility
            });
        }
    };

})(jQuery);
