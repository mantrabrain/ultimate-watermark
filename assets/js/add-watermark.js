/**
 * Ultimate Watermark - Add Watermark Page JavaScript
 * 
 * Handles the add watermark page functionality with live preview
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */

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
            
            // Save draft
            $(document).on('click', '#save-draft', this.saveDraft.bind(this));
        
            
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
                console.log('Ultimate Watermark: Font style field changed to:', $(this).val());
                AddWatermarkPage.debouncePreviewUpdate();
            });
            
            // Initialize form field visibility with multiple attempts to ensure it works
            this.initializeConditionalFields();
            
            // Set initial preview - removed sidebar preview
            // this.updatePreview();
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
            const $this = $(e.target);
            const tab = $this.attr('data-tab');
            
            if (!tab) {
                return;
            }
            
            // If it's a link, let it navigate naturally
            if ($this.is('a')) {
                return; // Let the browser handle the navigation
            }
            
            // For button tabs (fallback), prevent default and switch
            e.preventDefault();
            
            $('.form-tab').removeClass('active');
            $('.form-tab-content').removeClass('active');
            
            $this.addClass('active');
            $('#tab-' + tab).addClass('active');
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
                watermark_on: $('#watermark_on').val()
            };
            
            // Debug: Log form values
            console.log('Ultimate Watermark: Form values for conditional fields:', formValues);
            console.log('Ultimate Watermark: Current watermark type:', formValues.watermark_type);
            
            
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
                    console.log('Ultimate Watermark: Processing conditional field:', condition, 'Field:', $field);
                    console.log('Ultimate Watermark: Field visibility before:', $field.is(':visible'));
                }
                
                // Process conditional field logic here
                if (condition) {
                    // Parse condition: "field_name === 'value'"
                    const parts = condition.split(' === ');
                    if (parts.length === 2) {
                        const fieldName = parts[0].trim();
                        const expectedValue = parts[1].trim().replace(/['"]/g, '');
                        const currentValue = formValues[fieldName];
                        
                        // Show/hide based on condition
                        if (currentValue === expectedValue) {
                            $field.removeClass('hidden');
                            if (condition.includes('watermark_type')) {
                                console.log('Ultimate Watermark: Showing field for condition:', condition);
                            }
                        } else {
                            $field.addClass('hidden');
                            if (condition.includes('watermark_type')) {
                                console.log('Ultimate Watermark: Hiding field for condition:', condition);
                            }
                        }
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
                UWNotifications.error('Error', 'WordPress media library is not available.');
                return;
            }
            

            try {
                // Show loading state
                this.showPreviewLoading();

                // Create media frame
                const mediaFrame = wp.media({
                    title: 'Select Watermark Image',
                    button: {
                        text: 'Use this image'
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
        updatePreviewStats: function() {
            const position = $('#watermark_position option:selected').text();
            const opacity = $('#watermark_opacity').val();
            const rotation = $('#watermark_rotation').val();
            const watermarkType = $('input[name="watermark_type"]:checked').val();
            
            let size = '';
            if (watermarkType === 'text') {
                size = $('#watermark_font_size').val() + 'px';
            } else {
                const sizeType = $('#watermark_size_type').val();
                if (sizeType === 'custom') {
                    const width = $('#watermark_custom_width').val() || 100;
                    const height = $('#watermark_custom_height').val() || 100;
                    size = width + '×' + height + 'px';
                } else if (sizeType === 'scaled') {
                    const scale = $('#watermark_scale_percentage').val() || 80;
                    size = scale + '%';
                } else {
                    size = 'Original';
                }
            }
            
            $('#preview-position').text(position);
            $('#preview-opacity').text(opacity + '%');
            $('#preview-size').text(size);
            $('#preview-rotation').text(rotation + '°');
        },


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
                        UWNotifications.success('Success', response.data.message || 'Watermark saved successfully!');
                        
                        // Only redirect if redirect_url is provided (new watermarks only)
                        if (response.data.redirect_url) {
                            setTimeout(() => {
                                window.location.href = response.data.redirect_url;
                            }, 1500);
                        }
                    } else {
                        // Show error message
                        UWNotifications.error('Error', response.data.message || 'Failed to save watermark.');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoadingState();
                    
                    // Show error message
                    UWNotifications.error('Error', 'An error occurred while saving the watermark.');
                    
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
                UWNotifications.error('Validation Error', 'Please enter a watermark name.');
                $('#name').focus();
                return false;
            }
            
            if (watermarkType === 'text') {
                const text = formData.get('watermark_text');
                if (!text || text.trim() === '') {
                    UWNotifications.error('Validation Error', 'Please enter watermark text.');
                    $('#watermark_text').focus();
                    return false;
                }
            } else {
                const imageId = formData.get('watermark_image_id');
                if (!imageId) {
                    UWNotifications.error('Validation Error', 'Please select a watermark image.');
                    $('#watermark-upload-area').click();
                    return false;
                }
            }
            
            return true;
        },

        /**
         * Show loading state
         */
        showLoadingState: function() {
            $('button[type="submit"]').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Creating...');
        },

        /**
         * Hide loading state
         */
        hideLoadingState: function() {
            $('button[type="submit"]').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Create Watermark');
        },


        /**
         * Save draft
         */
        saveDraft: function(e) {
            e.preventDefault();
            
            // TODO: Implement save draft functionality
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                e.preventDefault();
                $('#save-draft').click();
            }
            
            // Ctrl/Cmd + Enter to submit
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                e.preventDefault();
                $('#ultimate-watermark-form').submit();
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
                this.showPreviewError('AJAX configuration not available');
                return;
            }
            
            if (!ultimate_watermark_ajax.ajax_url || !ultimate_watermark_ajax.nonce) {
                this.showPreviewError('AJAX configuration incomplete');
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
                    this.generatingInitialPreview = false; // Clear flag
                    if (response.success) {
                        this.updatePreviewImage(response.data.preview_url);
                        this.updatePreviewStats(formData);
                        this.hidePreviewLoading();
                    } else {
                        this.showPreviewError(response.data || 'Initial preview generation failed');
                    }
                },
                error: (xhr, status, error) => {
                    this.generatingInitialPreview = false; // Clear flag
                    this.showPreviewError('Initial preview generation failed. Please try again.');
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
            
            
            $.ajax({
                url: ultimate_watermark_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        this.updatePreviewImage(response.data.preview_url);
                        this.updatePreviewStats(formData);
                        this.hidePreviewLoading();
                    } else {
                        this.showPreviewError(response.data || 'Preview generation failed');
                    }
                },
                error: (xhr, status, error) => {
                    this.showPreviewError('Preview generation failed. Please try again.');
                }
            });
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
                    console.log('Ultimate Watermark: Found field:', fieldName, 'Value:', $field.val(), 'Type:', fieldType, 'Tag:', fieldTag, 'Visible:', $field.is(':visible'), 'Parent visible:', $field.closest('.form-section').is(':visible'), 'Has class hidden:', $field.hasClass('hidden'));
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
            console.log('Ultimate Watermark: Form data collected:', JSON.stringify(formData, null, 2));
            
            // Debug: Specifically check font style and text decoration fields
            console.log('Ultimate Watermark: Font style field value:', formData.watermark_font_style);
            console.log('Ultimate Watermark: Text decoration field value:', formData.watermark_text_decoration);
            
            return formData;
        },

        /**
         * Update preview image
         */
        updatePreviewImage: function(previewUrl) {
            
            const $previewImage = $('#ultimate-watermark-preview-image');
            
            const newSrc = previewUrl + '?t=' + Date.now();
            
            $previewImage.attr('src', newSrc);
            
            // Check if image loaded successfully
            $previewImage.on('load', function() {
            });
            
            $previewImage.on('error', function() {
            });
        },

        /**
         * Update preview statistics
         */
        updatePreviewStats: function(formData) {
            if (!formData) {
                console.log('Ultimate Watermark: updatePreviewStats called without formData');
                return;
            }
            $('#preview-position').text(this.formatPosition(formData.watermark_position));
            $('#preview-opacity').text(formData.watermark_opacity + '%');
            $('#preview-size').text(this.formatSize(formData));
        },

        /**
         * Format position for display
         */
        formatPosition: function(position) {
            const positions = {
                'top-left': 'Top Left',
                'top-right': 'Top Right',
                'bottom-left': 'Bottom Left',
                'bottom-right': 'Bottom Right',
                'center': 'Center'
            };
            return positions[position] || position;
        },

        /**
         * Format size for display
         */
        formatSize: function(formData) {
            
            if (formData.watermark_size_type === 'scaled') {
                const percentage = formData.watermark_scale_percentage || 80;
                return percentage + '%';
            } else if (formData.watermark_size_type === 'custom') {
                const width = formData.watermark_custom_width || 100;
                const height = formData.watermark_custom_height || 100;
                return width + 'x' + height;
            } else {
                return 'Original';
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
            UWNotifications.error('Preview Error', message);
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
