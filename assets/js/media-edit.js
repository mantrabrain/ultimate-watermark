/**
 * Ultimate Watermark - Media Edit Page JavaScript
 */

(function($) {
    'use strict';

    /**
     * Media Edit Watermark Handler
     */
    const MediaEditWatermark = {
        
        /**
         * Initialize the media edit watermark functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle remove all watermarks button clicks
            $(document).on('click', '.remove-all-watermarks-btn', this.handleRemoveAllWatermarks.bind(this));
        },


        /**
         * Handle remove all watermarks button click
         */
        handleRemoveAllWatermarks: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const attachmentId = $button.data('attachment-id');
            
            // Show confirmation
            if (!confirm(ultimate_watermark_media_edit.strings.confirm_remove_all || 'Are you sure you want to remove ALL watermarks from this image? This will restore the original image from backup.')) {
                return;
            }
            
            // Show loading state
            this.showLoadingStateAll($button);
            
            // Make AJAX request
            $.ajax({
                url: ultimate_watermark_media_edit.ajax_url,
                type: 'POST',
                data: {
                    action: 'ultimate_watermark_remove_all',
                    attachment_id: attachmentId,
                    nonce: ultimate_watermark_media_edit.nonce
                },
                success: (response) => {
                    this.hideLoadingStateAll($button);
                    
                    if (response.success) {
                        this.showSuccessStateAll();
                        
                        // Show success message
                        this.showNotification(ultimate_watermark_media_edit.strings.removed_all || 'All watermarks removed successfully', 'success');
                        
                        // Update the UI to show "no watermarks" state
                        setTimeout(() => {
                            $('#ultimate-watermark-info .watermark-status.has-watermarks').replaceWith(`
                                <div class="watermark-status no-watermarks">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <span class="status-text">${ultimate_watermark_media_edit.strings.no_watermarks || 'No watermarks applied'}</span>
                                </div>
                            `);
                        }, 2000);
                        
                    } else {
                        this.showErrorStateAll();
                        this.showNotification(response.data || ultimate_watermark_media_edit.strings.error, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Ultimate Watermark: AJAX error:', xhr, status, error);
                    this.hideLoadingStateAll($button);
                    this.showErrorStateAll();
                    this.showNotification(ultimate_watermark_media_edit.strings.error, 'error');
                }
            });
        },


        /**
         * Show loading state for remove all
         */
        showLoadingStateAll: function($button) {
            $button.prop('disabled', true);
            $button.find('.dashicons').remove();
            $button.text(ultimate_watermark_media_edit.strings.removing || 'Removing...');
        },

        /**
         * Hide loading state for remove all
         */
        hideLoadingStateAll: function($button) {
            $button.prop('disabled', false);
            $button.html('<span class="dashicons dashicons-trash"></span>' + (ultimate_watermark_media_edit.strings.remove_all || 'Remove All Watermarks'));
        },

        /**
         * Show success state for remove all
         */
        showSuccessStateAll: function() {
            // Add success class to the entire watermark status container
            $('#ultimate-watermark-info .watermark-status').addClass('removed-all');
        },

        /**
         * Show error state for remove all
         */
        showErrorStateAll: function() {
            // Add error class to the entire watermark status container
            $('#ultimate-watermark-info .watermark-status').addClass('error-all');
            
            // Remove error state after 3 seconds
            setTimeout(() => {
                $('#ultimate-watermark-info .watermark-status').removeClass('error-all');
            }, 3000);
        },

        /**
         * Show notification message
         */
        showNotification: function(message, type) {
            // Remove existing notifications
            $('.ultimate-watermark-notification').remove();
            
            // Create notification element
            const $notification = $(`
                <div class="ultimate-watermark-notification notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            // Add to page
            $('.wrap h1').after($notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Handle manual dismiss
            $notification.on('click', '.notice-dismiss', function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MediaEditWatermark.init();
    });

})(jQuery);
