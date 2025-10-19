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
                        const message = response.data?.message || 'Your settings have been saved successfully!';
                        
                        if (typeof UWNotifications !== 'undefined') {
                            UWNotifications.success('Settings Saved', message, 3000);
                        } else {
                            alert('Settings saved successfully!');
                        }
                        
                        // Refresh the page after a short delay to ensure settings are reflected
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        const message = response.data?.message || 'Failed to save settings. Please try again.';
                        
                        if (typeof UWNotifications !== 'undefined') {
                            UWNotifications.error('Save Failed', message, 5000);
                        } else {
                            alert('Failed to save settings. Please try again.');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    SettingsPage.hideLoadingState();
                    
                    if (typeof UWNotifications !== 'undefined') {
                        UWNotifications.error('Save Failed', 'An error occurred while saving settings. Please try again.', 5000);
                    } else {
                        alert('An error occurred while saving settings. Please try again.');
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
