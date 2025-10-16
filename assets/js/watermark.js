/**
 * Ultimate Watermark Page JavaScript
 * 
 * Watermark management page specific functionality
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Watermark page functionality
     */
    const UltimateWatermarkPage = {
        
        /**
         * Media uploader instance
         */
        mediaUploader: null,
        
        /**
         * Current watermark being edited
         */
        currentWatermark: null,
        
        /**
         * Initialize watermark page functionality
         */
        init: function() {
            this.bindEvents();
            this.initMediaUploader();
            this.initColorPickers();
            this.initRangeSliders();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add watermark button
            $(document).on('click', '.ultimate-watermark-add-btn', this.showForm);
            
            // Cancel form
            $(document).on('click', '.ultimate-watermark-cancel-btn', this.hideForm);
            
            // Form submission
            $(document).on('submit', '#ultimate-watermark-form', this.handleFormSubmit);
            
            // Image upload
            $(document).on('click', '.ultimate-watermark-upload-btn', this.handleImageUpload);
            $(document).on('click', '.ultimate-watermark-remove-btn', this.handleImageRemove);
            
            // Watermark type change
            $(document).on('change', '#watermark_type', this.handleTypeChange);
            
            // Range slider changes
            $(document).on('input', '.ultimate-watermark-range', this.handleRangeChange);
            
            // Test watermark
            $(document).on('click', '.ultimate-watermark-test-btn', this.testWatermark);
            
            // Edit watermark
            $(document).on('click', '.ultimate-watermark-edit-btn', this.editWatermark);
            
            // Delete watermark
            $(document).on('click', '.ultimate-watermark-delete-btn', this.deleteWatermark);
        },

        /**
         * Initialize media uploader
         */
        initMediaUploader: function() {
            if (typeof wp !== 'undefined' && wp.media) {
                this.mediaUploader = wp.media({
                    title: 'Select Watermark Image',
                    button: {
                        text: 'Use Image'
                    },
                    multiple: false
                });
            }
        },

        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.ultimate-watermark-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        UltimateWatermarkPage.updatePreview();
                    }
                });
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
         * Show watermark form
         */
        showForm: function(e) {
            e.preventDefault();
            
            $('.ultimate-watermark-form-section').slideDown(300);
            $('html, body').animate({
                scrollTop: $('.ultimate-watermark-form-section').offset().top - 100
            }, 500);
        },

        /**
         * Hide watermark form
         */
        hideForm: function(e) {
            e.preventDefault();
            
            $('.ultimate-watermark-form-section').slideUp(300);
            UltimateWatermarkPage.resetForm();
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const formData = UltimateWatermarkPage.getFormData();
            
            if (!UltimateWatermarkPage.validateForm(formData)) {
                return;
            }
            
            UltimateWatermarkPage.showLoading();
            
            // Simulate API call
            setTimeout(() => {
                UltimateWatermarkPage.hideLoading();
                UltimateWatermarkPage.showNotice('Watermark saved successfully!', 'success');
                UltimateWatermarkPage.hideForm();
                UltimateWatermarkPage.resetForm();
                // In real implementation, you would reload the watermark list here
            }, 1500);
        },

        /**
         * Handle image upload
         */
        handleImageUpload: function(e) {
            e.preventDefault();
            
            if (UltimateWatermarkPage.mediaUploader) {
                UltimateWatermarkPage.mediaUploader.open();
                
                UltimateWatermarkPage.mediaUploader.on('select', function() {
                    const attachment = UltimateWatermarkPage.mediaUploader.state().get('selection').first().toJSON();
                    const $field = $(e.target).closest('.ultimate-watermark-image-upload');
                    const $input = $field.find('input[type="hidden"]');
                    const $preview = $field.find('.ultimate-watermark-image-preview');
                    const $removeBtn = $field.find('.ultimate-watermark-remove-btn');
                    
                    $input.val(attachment.id);
                    $preview.find('img').attr('src', attachment.url);
                    $preview.show();
                    $removeBtn.show();
                    
                    UltimateWatermarkPage.updatePreview();
                });
            }
        },

        /**
         * Handle image remove
         */
        handleImageRemove: function(e) {
            e.preventDefault();
            
            const $field = $(e.target).closest('.ultimate-watermark-image-upload');
            const $input = $field.find('input[type="hidden"]');
            const $preview = $field.find('.ultimate-watermark-image-preview');
            const $removeBtn = $field.find('.ultimate-watermark-remove-btn');
            
            $input.val('');
            $preview.hide();
            $removeBtn.hide();
            
            UltimateWatermarkPage.updatePreview();
        },

        /**
         * Handle watermark type change
         */
        handleTypeChange: function() {
            const type = $(this).val();
            const $textFields = $('.ultimate-watermark-text-fields');
            const $imageField = $('.ultimate-watermark-image-upload');
            
            if (type === 'text') {
                $textFields.show();
                $imageField.hide();
            } else {
                $textFields.hide();
                $imageField.show();
            }
            
            UltimateWatermarkPage.updatePreview();
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
            
            UltimateWatermarkPage.updatePreview();
        },

        /**
         * Test watermark
         */
        testWatermark: function(e) {
            e.preventDefault();
            
            UltimateWatermarkPage.showNotice('Testing watermark on preview image...', 'info');
            
            // Simulate watermark test
            setTimeout(() => {
                UltimateWatermarkPage.showNotice('Watermark test completed!', 'success');
            }, 2000);
        },

        /**
         * Edit watermark
         */
        editWatermark: function(e) {
            e.preventDefault();
            
            const watermarkId = $(this).data('id');
            
            // Load watermark data and populate form
            UltimateWatermarkPage.loadWatermarkData(watermarkId);
            UltimateWatermarkPage.showForm();
        },

        /**
         * Delete watermark
         */
        deleteWatermark: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this watermark?')) {
                return;
            }
            
            const watermarkId = $(this).data('id');
            
            UltimateWatermarkPage.showLoading();
            
            // Simulate API call
            setTimeout(() => {
                UltimateWatermarkPage.hideLoading();
                UltimateWatermarkPage.showNotice('Watermark deleted successfully!', 'success');
                // In real implementation, you would remove the item from the list
            }, 1000);
        },

        /**
         * Get form data
         */
        getFormData: function() {
            const form = document.getElementById('ultimate-watermark-form');
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            return data;
        },

        /**
         * Validate form
         */
        validateForm: function(data) {
            if (!data.watermark_name) {
                this.showNotice('Please enter a watermark name.', 'error');
                return false;
            }
            
            if (data.watermark_type === 'image' && !data.watermark_image) {
                this.showNotice('Please select a watermark image.', 'error');
                return false;
            }
            
            if (data.watermark_type === 'text' && !data.watermark_text) {
                this.showNotice('Please enter watermark text.', 'error');
                return false;
            }
            
            return true;
        },

        /**
         * Reset form
         */
        resetForm: function() {
            document.getElementById('ultimate-watermark-form').reset();
            $('.ultimate-watermark-text-fields').hide();
            $('.ultimate-watermark-image-preview').hide();
            $('.ultimate-watermark-remove-btn').hide();
            this.currentWatermark = null;
        },

        /**
         * Load watermark data for editing
         */
        loadWatermarkData: function(watermarkId) {
            // This would load data from the server
            // Loading watermark data
        },

        /**
         * Update preview
         */
        updatePreview: function() {
            // This would update the preview panel
            // Updating preview
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('.ultimate-watermark-page').addClass('ultimate-watermark-loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('.ultimate-watermark-page').removeClass('ultimate-watermark-loading');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible ultimate-watermark-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.ultimate-watermark-page h1').after($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        UltimateWatermarkPage.init();
    });

    /**
     * Expose to global scope
     */
    window.UltimateWatermarkPage = UltimateWatermarkPage;

})(jQuery);
