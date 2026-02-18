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
            $(document).on('submit', '#posts-filter', this.handleBulkActionSubmit.bind(this));
            
            // Handle button clicks directly (as backup)
            $(document).on('click', '#doaction, #doaction2', this.handleButtonClick.bind(this));
            
            // Handle confirmation modal events
            $(document).on('click', '.modal-cancel, .modal-close, .modal-overlay', this.closeConfirmationModal.bind(this));
            $(document).on('click', '.modal-confirm', this.confirmWatermarkAction.bind(this));
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
                    UWNotifications.error('Error', 'Please select at least one media item to watermark.');
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
                    UWNotifications.error('Error', 'Please select at least one media item to watermark.');
                    return false;
                }
                
                // Show custom confirmation modal
                this.showConfirmationModal(action, selectedItems);
                
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
         * Show confirmation modal
         */
        showConfirmationModal: function(action, selectedItems) {
            const isRemoveAction = action === 'ultimate_watermark_remove';
            const itemCount = selectedItems.length;
            
            let title, message, confirmText, confirmClass;
            
            if (isRemoveAction) {
                title = 'Remove Watermark';
                message = `Are you sure you want to remove watermarks from ${itemCount} selected image(s)? This will restore the original images from backup.`;
                confirmText = 'Remove Watermark';
                confirmClass = 'btn-warning';
            } else {
                const watermarkName = action.replace('ultimate_watermark_', '').replace(/_/g, ' ');
                title = 'Apply Watermark';
                message = `Are you sure you want to apply the watermark "${watermarkName}" to ${itemCount} selected image(s)? This will modify the original images.`;
                confirmText = 'Apply Watermark';
                confirmClass = 'btn-primary';
            }
            
            // Create modal if it doesn't exist
            if (!$('#watermark-confirmation-modal').length) {
                $('body').append(`
                    <div id="watermark-confirmation-modal" class="confirmation-modal" style="display: none;">
                        <div class="modal-overlay"></div>
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>${title}</h3>
                                <button type="button" class="modal-close">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p>${message}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary modal-cancel">
                                    Cancel
                                </button>
                                <button type="button" class="btn ${confirmClass} modal-confirm">
                                    ${confirmText}
                                </button>
                            </div>
                        </div>
                    </div>
                `);
            } else {
                // Update existing modal content
                $('#watermark-confirmation-modal .modal-header h3').text(title);
                $('#watermark-confirmation-modal .modal-body p').text(message);
                $('#watermark-confirmation-modal .modal-confirm').text(confirmText).removeClass('btn-primary btn-warning').addClass(confirmClass);
            }
            
            UWNotifications.confirm({
                title: title,
                message: message,
                type: isRemoveAction ? 'warning' : 'info',
                confirmText: confirmText,
                cancelText: 'Cancel',
                confirmButtonType: confirmClass
            }).then(confirmed => {
                if (confirmed) {
                    this.confirmWatermarkAction(action, selectedItems);
                }
            });
        },

        /**
         * Close confirmation modal
         */
        closeConfirmationModal: function() {
            $('#watermark-confirmation-modal').hide();
        },

        /**
         * Confirm watermark action
         */
        confirmWatermarkAction: function(action, selectedItems) {
            this.closeConfirmationModal();
            
            // Show processing indicator
            this.showProcessingIndicator();
            
            // Handle the watermarking via AJAX
            this.handleWatermarkingAjax(action, selectedItems);
        },

        /**
         * Handle watermarking via AJAX
         */
        handleWatermarkingAjax: function(action, selectedItems) {
            const attachmentIds = selectedItems.map(function() {
                return $(this).val();
            }).get();
            
            // Determine if this is an apply or remove action
            const isRemoveAction = action === 'ultimate_watermark_remove';
            const ajaxAction = isRemoveAction ? 'ultimate_watermark_remove' : 'ultimate_watermark_apply_manual';
            
            
            // Prepare AJAX data
            const ajaxData = {
                action: ajaxAction,
                attachment_ids: attachmentIds,
                nonce: ultimate_watermark_media.nonce
            };
            
            // Add watermark_id only for apply actions
            if (!isRemoveAction) {
                const watermarkId = action.replace('ultimate_watermark_', '');
                ajaxData.watermark_id = watermarkId;
            }
            
            
            // Make AJAX request
            $.ajax({
                url: ultimate_watermark_media.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    this.hideProcessingIndicator();
                    
                    if (response.success) {
                        const data = response.data;
                        const successCount = data.results.filter(r => r.success).length;
                        const errorCount = data.results.filter(r => !r.success).length;
                        
                        // Build detailed message
                        let detailHtml = data.message || '';
                        
                        if (data.details && data.details.length > 0) {
                            detailHtml += '\n\n' + data.details.join('\n');
                        }
                        
                        if (isRemoveAction) {
                            if (errorCount === 0) {
                                UWNotifications.success('Success', `Successfully removed watermark from ${successCount} image(s).`);
                            } else {
                                UWNotifications.error('Partial Success', `Removed watermark from ${successCount} image(s), but ${errorCount} failed.`);
                            }
                        } else {
                            if (errorCount === 0 && successCount > 0) {
                                UWNotifications.success('Success', detailHtml);
                            } else if (successCount > 0) {
                                UWNotifications.error('Partial Success', detailHtml);
                            } else {
                                UWNotifications.error('Watermark Not Applied', detailHtml);
                            }
                        }
                        
                        // Refresh the media library to show updated images
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        const actionText = isRemoveAction ? 'remove watermark' : 'apply watermark';
                        const errMsg = (response.data && response.data.message) ? response.data.message : `Failed to ${actionText}. Please try again.`;
                        UWNotifications.error('Error', errMsg);
                    }
                },
                error: (xhr, status, error) => {
                    this.hideProcessingIndicator();
                    const actionText = isRemoveAction ? 'remove watermark' : 'apply watermark';
                    UWNotifications.error('Error', `Failed to ${actionText}. Please try again.`);
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
                UWNotifications.success('Success', message);
            } else {
                UWNotifications.error('Error', `Failed to apply "${watermark}" to any images. Please check your image files and try again.`);
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
