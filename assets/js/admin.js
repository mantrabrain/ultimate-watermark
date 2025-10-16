/**
 * Ultimate Watermark Admin JavaScript
 * 
 * Modern admin interface functionality
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin functionality
     */
    const UltimateWatermarkAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initRangeSliders();
            this.initColorPickers();
            
            // Only initialize image uploader if we're on a page that needs it
            if (this.needsMediaLibrary()) {
                setTimeout(() => {
                    this.initImageUploader();
                }, 500); // Increased delay to ensure media library is fully loaded
            }
        },
        
        /**
         * Check if current page needs media library
         */
        needsMediaLibrary: function() {
            // Check if we're on add/edit watermark page
            const currentUrl = window.location.href;
            return currentUrl.includes('ultimate-watermark-add-watermark') || 
                   currentUrl.includes('ultimate-watermark-edit-watermark');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Image upload/remove buttons
            $(document).on('click', '.ultimate-watermark-upload-image', this.handleImageUpload);
            $(document).on('click', '.ultimate-watermark-remove-image', this.handleImageRemove);
            
            // Range slider updates
            $(document).on('input', '.ultimate-watermark-range', this.handleRangeChange);
            
            // Form submissions
            $(document).on('submit', 'form', this.handleFormSubmit);
        },

        /**
         * Initialize image uploader
         */
        initImageUploader: function() {
            // Check if media library is available and fully loaded
            if (typeof wp !== 'undefined' && wp.media && wp.media.view && wp.media.controller) {
                try {
                    this.mediaUploader = wp.media({
                        title: ultimateWatermarkAdmin.strings.selectImage || 'Select Image',
                        button: {
                            text: ultimateWatermarkAdmin.strings.useImage || 'Use Image'
                        },
                        multiple: false,
                        library: {
                            type: 'image'
                        }
                    });
                } catch (error) {
                    // Media library initialization failed - silently handle
                }
            } else {
                // Media library not available - this is expected when wp_enqueue_media() is not called
                // Silently handle the case where media library is not available
            }
        },
        

        /**
         * Initialize range sliders
         */
        initRangeSliders: function() {
            $('.ultimate-watermark-range').each(function() {
                const $slider = $(this);
                const $value = $slider.siblings('.ultimate-watermark-range-value');
                
                if ($value.length) {
                    $value.text($slider.val() + '%');
                }
            });
        },

        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.ultimate-watermark-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        // Handle color change
                    }
                });
            }
        },

        /**
         * Handle image upload
         */
        handleImageUpload: function(e) {
            e.preventDefault();
            
            if (UltimateWatermarkAdmin.mediaUploader) {
                UltimateWatermarkAdmin.mediaUploader.open();
                
                UltimateWatermarkAdmin.mediaUploader.on('select', function() {
                    const attachment = UltimateWatermarkAdmin.mediaUploader.state().get('selection').first().toJSON();
                    const $field = $(e.target).closest('.ultimate-watermark-image-field');
                    const $input = $field.find('input[type="hidden"]');
                    const $preview = $field.find('.ultimate-watermark-image-preview');
                    const $removeBtn = $field.find('.ultimate-watermark-remove-image');
                    
                    $input.val(attachment.id);
                    $preview.find('img').attr('src', attachment.url);
                    $preview.show();
                    $removeBtn.show();
                });
            }
        },

        /**
         * Handle image remove
         */
        handleImageRemove: function(e) {
            e.preventDefault();
            
            const $field = $(e.target).closest('.ultimate-watermark-image-field');
            const $input = $field.find('input[type="hidden"]');
            const $preview = $field.find('.ultimate-watermark-image-preview');
            const $removeBtn = $field.find('.ultimate-watermark-remove-image');
            
            $input.val('');
            $preview.hide();
            $removeBtn.hide();
        },

        /**
         * Handle range change
         */
        handleRangeChange: function(e) {
            const $slider = $(e.target);
            const $value = $slider.siblings('.ultimate-watermark-range-value');
            
            if ($value.length) {
                $value.text($slider.val() + '%');
            }
        },

        /**
         * Handle form submit
         */
        handleFormSubmit: function(e) {
            const $form = $(e.target);
            
            if ($form.attr('action') && $form.attr('action').includes('options.php')) {
                // Show loading state
                $form.addClass('ultimate-watermark-loading');
                
                // Remove loading state after a short delay
                setTimeout(function() {
                    $form.removeClass('ultimate-watermark-loading');
                }, 1000);
            }
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            const $notice = $('<div class="ultimate-watermark-notice ' + type + '">' + message + '</div>');
            
            $('.wrap h1').after($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Show loading state
         */
        showLoading: function($element) {
            $element.addClass('ultimate-watermark-loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function($element) {
            $element.removeClass('ultimate-watermark-loading');
        }
    };

    /**
     * AJAX functionality
     */
    const UltimateWatermarkAJAX = {
        
        /**
         * Make AJAX request
         */
        request: function(action, data, callback) {
            data = data || {};
            data.action = action;
            data.nonce = ultimateWatermarkAdmin.nonce;
            
            $.ajax({
                url: ultimateWatermarkAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (callback) {
                        callback(response);
                    }
                },
                error: function(xhr, status, error) {
                    UltimateWatermarkAdmin.showNotice(
                        ultimateWatermarkAdmin.strings.error + ': ' + error,
                        'error'
                    );
                }
            });
        },

        /**
         * Test watermark functionality
         */
        testWatermark: function(imageId, callback) {
            this.request('ultimate_watermark_test', {
                image_id: imageId
            }, callback);
        },

        /**
         * Apply watermark to image
         */
        applyWatermark: function(imageId, callback) {
            this.request('ultimate_watermark_apply', {
                image_id: imageId
            }, callback);
        },

        /**
         * Remove watermark from image
         */
        removeWatermark: function(imageId, callback) {
            this.request('ultimate_watermark_remove', {
                image_id: imageId
            }, callback);
        }
    };

    /**
     * Utility functions
     */
    const UltimateWatermarkUtils = {
        
        /**
         * Debounce function
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Throttle function
         */
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        UltimateWatermarkAdmin.init();
    });

    /**
     * Expose to global scope
     */
    window.UltimateWatermarkAdmin = UltimateWatermarkAdmin;
    window.UltimateWatermarkAJAX = UltimateWatermarkAJAX;
    window.UltimateWatermarkUtils = UltimateWatermarkUtils;

})(jQuery);
