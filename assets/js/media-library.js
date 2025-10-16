/**
 * Ultimate Watermark - Media Library Integration
 * 
 * Handles manual watermarking in WordPress Media Library
 */
(function($) {
    'use strict';

    const MediaLibraryWatermark = {
        
        /**
         * Initialize the media library watermarking
         */
        init: function() {
            this.bindEvents();
            this.addWatermarkingStyles();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Handle bulk action form submission
            $(document).on('submit', '#posts-filter', this.handleBulkActionSubmit);
            
            // Handle button clicks directly (as backup)
            $(document).on('click', '#doaction, #doaction2', this.handleButtonClick);
        },

        /**
         * Handle bulk action form submission
         */
        handleBulkActionSubmit: function(e) {
            const $form = $(this);
            const action = $form.find('#bulk-action-selector-top, #bulk-action-selector-bottom').val();
            
            // Check if it's a watermark action
            if (action && action.indexOf('ultimate_watermark_') === 0) {
                const selectedItems = $form.find('input[name="media[]"]:checked');
                
                if (selectedItems.length === 0) {
                    this.showErrorMessage('Please select at least one media item to watermark.');
                    e.preventDefault();
                    return false;
                }
                
                // Prevent default form submission
                e.preventDefault();
                e.stopPropagation();
                
                // Show custom confirmation modal
                this.showConfirmationModal(action, selectedItems);
                
                return false;
            }
        },

        /**
         * Handle button click (backup method)
         */
        handleButtonClick: function(e) {
            const $form = $('#posts-filter');
            const action = $form.find('#bulk-action-selector-top, #bulk-action-selector-bottom').val();
            
            // Check if it's a watermark action
            if (action && action.indexOf('ultimate_watermark_') === 0) {
                e.preventDefault();
                e.stopPropagation();
                
                const selectedItems = $form.find('input[name="media[]"]:checked');
                
                if (selectedItems.length === 0) {
                    this.showErrorMessage('Please select at least one media item to watermark.');
                    return false;
                }
                
                // Show confirmation dialog
                const watermarkName = action.replace('ultimate_watermark_', '').replace(/_/g, ' ');
                const confirmMessage = `Are you sure you want to apply the watermark "${watermarkName}" to ${selectedItems.length} selected item(s)? This will modify the original images.`;
                
                if (!confirm(confirmMessage)) {
                    return false;
                }
                
                // Show processing indicator
                MediaLibraryWatermark.showProcessingIndicator();
                
                // Handle the watermarking via AJAX
                MediaLibraryWatermark.handleWatermarkingAjax(action, selectedItems);
                
                return false;
            }
        },

        /**
         * Show processing indicator
         */
        showProcessingIndicator: function() {
            const $submitButtons = $('#doaction, #doaction2');
            const originalText = $submitButtons.val();
            
            $submitButtons.val(ultimate_watermark_media.processing_text).prop('disabled', true);
            
            // Add processing overlay
            $('body').append(`
                <div id="ultimate-watermark-processing" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: white;
                        padding: 30px;
                        border-radius: 8px;
                        text-align: center;
                        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                    ">
                        <div style="
                            width: 40px;
                            height: 40px;
                            border: 4px solid #f3f3f3;
                            border-top: 4px solid #0073aa;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin: 0 auto 20px;
                        "></div>
                        <h3 style="margin: 0 0 10px; color: #333;">${ultimate_watermark_media.processing_text}</h3>
                        <p style="margin: 0; color: #666;">Please wait while we apply the watermark to your images...</p>
                    </div>
                </div>
            `);
            
            // Add CSS animation
            if (!$('#ultimate-watermark-spinner-css').length) {
                $('head').append(`
                    <style id="ultimate-watermark-spinner-css">
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                `);
            }
        },

        /**
         * Add watermarking styles
         */
        addWatermarkingStyles: function() {
            if ($('#ultimate-watermark-media-styles').length) {
                return;
            }
            
            $('head').append(`
                <style id="ultimate-watermark-media-styles">
                    .ultimate-watermark-bulk-action {
                        color: #0073aa;
                        font-weight: 500;
                    }
                    
                    .ultimate-watermark-bulk-action:hover {
                        color: #005177;
                    }
                    
                    .ultimate-watermark-processing-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.7);
                        z-index: 999999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    .ultimate-watermark-processing-content {
                        background: white;
                        padding: 40px;
                        border-radius: 12px;
                        text-align: center;
                        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                        max-width: 400px;
                        width: 90%;
                    }
                    
                    .ultimate-watermark-spinner {
                        width: 50px;
                        height: 50px;
                        border: 4px solid #f3f3f3;
                        border-top: 4px solid #0073aa;
                        border-radius: 50%;
                        animation: ultimate-watermark-spin 1s linear infinite;
                        margin: 0 auto 20px;
                    }
                    
                    @keyframes ultimate-watermark-spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    
                    .ultimate-watermark-processing-title {
                        margin: 0 0 10px;
                        color: #333;
                        font-size: 18px;
                        font-weight: 600;
                    }
                    
                    .ultimate-watermark-processing-message {
                        margin: 0;
                        color: #666;
                        font-size: 14px;
                        line-height: 1.5;
                    }
                </style>
            `);
        },

        /**
         * Show success message
         */
        showSuccessMessage: function(message) {
            this.showNotification(message, 'success');
        },

        /**
         * Show error message
         */
        showErrorMessage: function(message) {
            this.showNotification(message, 'error');
        },

        /**
         * Handle watermarking via AJAX
         */
        handleWatermarkingAjax: function(action, selectedItems) {
            const watermarkId = action.replace('ultimate_watermark_', '');
            const attachmentIds = selectedItems.map(function() {
                return $(this).val();
            }).get();
            
            // Make AJAX request
            $.ajax({
                url: ultimate_watermark_media.ajax_url,
                type: 'POST',
                data: {
                    action: 'ultimate_watermark_apply_manual',
                    watermark_id: watermarkId,
                    attachment_ids: attachmentIds,
                    nonce: ultimate_watermark_media.nonce
                },
                success: (response) => {
                    this.hideProcessingIndicator();
                    
                    if (response.success) {
                        const successCount = response.data.results.filter(r => r.success).length;
                        const errorCount = response.data.results.filter(r => !r.success).length;
                        
                        if (errorCount === 0) {
                            this.showSuccessMessage(`Successfully applied watermark to ${successCount} image(s).`);
                        } else {
                            this.showErrorMessage(`Applied watermark to ${successCount} image(s), but ${errorCount} failed.`);
                        }
                        
                        // Refresh the media library to show updated images
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showErrorMessage(response.data || 'Failed to apply watermark. Please try again.');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideProcessingIndicator();
                    this.showErrorMessage('Failed to apply watermark. Please try again.');
                }
            });
        },

        /**
         * Hide processing indicator
         */
        hideProcessingIndicator: function() {
            $('#ultimate-watermark-processing').remove();
            $('#doaction, #doaction2').val('Apply').prop('disabled', false);
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            const $notification = $(`
                <div class="notice notice-${type} is-dismissible ultimate-watermark-notification" style="
                    position: fixed;
                    top: 32px;
                    right: 20px;
                    z-index: 999999;
                    max-width: 400px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                ">
                    <p><strong>Ultimate Watermark:</strong> ${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('body').append($notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Handle manual dismiss
            $notification.find('.notice-dismiss').on('click', function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MediaLibraryWatermark.init();
    });

    // Handle URL parameters for success/error messages
    $(window).on('load', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const processed = urlParams.get('ultimate_watermark_processed');
        const errors = urlParams.get('ultimate_watermark_errors');
        const watermarkName = urlParams.get('watermark_name');
        
        if (processed !== null) {
            const processedCount = parseInt(processed) || 0;
            const errorCount = parseInt(errors) || 0;
            const watermark = watermarkName ? decodeURIComponent(watermarkName) : 'watermark';
            
            if (processedCount > 0) {
                let message = `Successfully applied "${watermark}" to ${processedCount} image(s).`;
                if (errorCount > 0) {
                    message += ` ${errorCount} image(s) could not be processed.`;
                }
                MediaLibraryWatermark.showSuccessMessage(message);
            } else {
                MediaLibraryWatermark.showErrorMessage(`Failed to apply "${watermark}" to any images. Please check your image files and try again.`);
            }
            
            // Clean up URL parameters
            const newUrl = window.location.pathname + window.location.search
                .replace(/[?&]ultimate_watermark_processed=\d+/g, '')
                .replace(/[?&]ultimate_watermark_errors=\d+/g, '')
                .replace(/[?&]watermark_name=[^&]*/g, '')
                .replace(/[?&]$/, '')
                .replace(/\?$/, '');
            
            if (newUrl !== window.location.pathname + window.location.search) {
                window.history.replaceState({}, '', newUrl);
            }
        }
    });

})(jQuery);
