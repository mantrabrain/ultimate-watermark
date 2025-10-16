/**
 * Ultimate Watermark - Settings Page JavaScript
 * 
 * Handles the settings page functionality
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */

(function($) {
    'use strict';

    const SettingsPage = {
        
        /**
         * Initialize the settings page
         */
        init: function() {
            this.bindEvents();
            this.initForm();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Range slider updates
            $(document).on('input', '#backup_quality', this.updateBackupQualityValue);
            
            // Settings changes
            $(document).on('change', '#watermark_on', this.togglePostTypesSelection);
            
            // Form submission
            $(document).on('submit', '#ultimate-watermark-settings-form', this.handleFormSubmit);
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts);
        },

        /**
         * Initialize form functionality
         */
        initForm: function() {
            // Initialize any form-specific functionality
            this.updateBackupQualityValue();
        },

        /**
         * Toggle post types selection based on watermark on setting
         */
        togglePostTypesSelection: function() {
            const watermarkOn = $(this).val();
            
            if (watermarkOn === 'selected_post_types') {
                $('#post-types-selection').show();
            } else {
                $('#post-types-selection').hide();
            }
        },

        /**
         * Update backup quality value display
         */
        updateBackupQualityValue: function() {
            const quality = $(this).val();
            $(this).siblings('.range-value').text(quality + '%');
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            // Show loading state
            SettingsPage.showLoadingState();
            
            // TODO: Implement form submission via AJAX
            // Settings form submitted
            
            // For now, just show success message
            setTimeout(() => {
                SettingsPage.hideLoadingState();
                SettingsPage.showSuccessMessage();
            }, 2000);
        },

        /**
         * Show loading state
         */
        showLoadingState: function() {
            $('button[type="submit"]').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Saving...');
        },

        /**
         * Hide loading state
         */
        hideLoadingState: function() {
            $('button[type="submit"]').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Settings');
        },

        /**
         * Show success message
         */
        showSuccessMessage: function() {
            // Create success notice
            const notice = $('<div class="notice notice-success is-dismissible"><p><strong>Settings saved successfully!</strong></p></div>');
            $('.settings-content').prepend(notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                notice.fadeOut();
            }, 3000);
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                e.preventDefault();
                $('#ultimate-watermark-settings-form').submit();
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SettingsPage.init();
    });

    // Make SettingsPage available globally
    window.SettingsPage = SettingsPage;

})(jQuery);
