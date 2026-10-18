/**
 * Ultimate Watermark - Settings Page JavaScript
 * 
 * Handles the settings page functionality
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
            // Initialize backup quality range input if it exists
            const backupQualityInput = $('#backup_quality');
            if (backupQualityInput.length > 0) {
                backupQualityInput.each(function() {
                    SettingsPage.updateBackupQualityValue.call(this);
                });
            }
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
            if (!this || !$(this).length) {
                return;
            }
            const quality = $(this).val();
            if (quality !== undefined) {
                $(this).siblings('.range-value').text(quality + '%');
            }
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            // Show loading state
            SettingsPage.showLoadingState();
            
            // Collect form data manually to include unchecked checkboxes
            const formData = SettingsPage.collectFormData();
            
            // Submit form via AJAX
            $.ajax({
                url: ultimateWatermarkSettings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ultimate_watermark_save_settings',
                    nonce: ultimateWatermarkSettings.nonce,
                    form_data: formData
                },
                success: function(response) {
                    SettingsPage.hideLoadingState();
                    
                    if (response.success) {
                        const message = response.data?.message || wp.i18n.__('Your settings have been saved successfully!', 'ultimate-watermark');
                        
                        if (typeof UWNotifications !== 'undefined') {
                            UWNotifications.success(wp.i18n.__('Settings Saved', 'ultimate-watermark'), message, 3000);
                        } else {
                            alert(wp.i18n.__('Settings saved successfully!', 'ultimate-watermark'));
                        }
                        
                        // Refresh the page after a short delay to ensure settings are reflected
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        const message = response.data?.message || wp.i18n.__('Failed to save settings. Please try again.', 'ultimate-watermark');
                        
                        if (typeof UWNotifications !== 'undefined') {
                            UWNotifications.error(wp.i18n.__('Save Failed', 'ultimate-watermark'), message, 5000);
                        } else {
                            alert(wp.i18n.__('Failed to save settings. Please try again.', 'ultimate-watermark'));
                        }
                    }
                },
                error: function(xhr, status, error) {
                    SettingsPage.hideLoadingState();
                    
                    if (typeof UWNotifications !== 'undefined') {
                        UWNotifications.error(wp.i18n.__('Save Failed', 'ultimate-watermark'), wp.i18n.__('An error occurred while saving settings. Please try again.', 'ultimate-watermark'), 5000);
                    } else {
                        alert(wp.i18n.__('An error occurred while saving settings. Please try again.', 'ultimate-watermark'));
                    }
                }
            });
        },

        /**
         * Collect form data including unchecked checkboxes
         */
        collectFormData: function() {
            const form = $('#ultimate-watermark-settings-form');
            const formData = {};
            
            // Get all form elements
            form.find('input, select, textarea').each(function() {
                const $this = $(this);
                const name = $this.attr('name');
                const type = $this.attr('type');
                
                if (name) {
                    if (type === 'checkbox') {
                        // For checkboxes, explicitly set to '1' or '0'
                        const isChecked = $this.is(':checked');
                        formData[name] = isChecked ? '1' : '0';
                        
                    } else if (type === 'radio') {
                        // For radio buttons, only include if checked
                        if ($this.is(':checked')) {
                            formData[name] = $this.val();
                        }
                    } else {
                        // For other inputs, use the value
                        formData[name] = $this.val();
                    }
                }
            });
            
            return formData;
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
