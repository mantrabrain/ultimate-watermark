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
            
            // Handle the top and bottom "Apply" buttons
            $(document).on('click', '#doaction, #doaction2', this.handleButtonClick.bind(this));
        },

        /**
         * Watermark action chosen in the bulk dropdown next to the given element
         * (top or bottom), or in whichever dropdown has a value.
         */
        getSelectedAction: function($context) {
            let action = '';
            if ($context && $context.length) {
                action = $context.closest('.bulkactions').find('select').val() || '';
            }
            if (!action || action === '-1') {
                const top = $('#bulk-action-selector-top').val();
                const bottom = $('#bulk-action-selector-bottom').val();
                action = (top && top !== '-1') ? top : (bottom || '');
            }
            return action;
        },

        /**
         * Label of the selected watermark action (the watermark's name).
         */
        getActionLabel: function(action) {
            const $option = $('#bulk-action-selector-top option, #bulk-action-selector-bottom option').filter(function() {
                return $(this).val() === action;
            }).first();
            if (!$option.length) {
                return '';
            }
            // Option text is "Apply Watermark: <name>": show just the name.
            const text = $.trim($option.text());
            const colon = text.indexOf(':');
            return colon > -1 ? $.trim(text.slice(colon + 1)) : text;
        },

        /**
         * Intercept a watermark bulk action and run it through the confirm + AJAX flow.
         */
        interceptAction: function(e, action) {
            if (!action || action.indexOf('ultimate_watermark_') !== 0) {
                return true;
            }

            e.preventDefault();
            e.stopPropagation();

            const selectedItems = $('#posts-filter').find('input[name="media[]"]:checked');
            if (selectedItems.length === 0) {
                UWNotifications.error(this.t('error', 'Error'), this.t('select_items', 'Please select at least one media item.'));
                return false;
            }

            this.showConfirmationModal(action, selectedItems);
            return false;
        },

        /**
         * Handle bulk action form submission
         */
        handleBulkActionSubmit: function(e) {
            return this.interceptAction(e, this.getSelectedAction($(document.activeElement)));
        },

        /**
         * Handle top/bottom "Apply" button clicks
         */
        handleButtonClick: function(e) {
            return this.interceptAction(e, this.getSelectedAction($(e.currentTarget)));
        },

        /**
         * Localized string helper
         */
        t: function(key, fallback) {
            const strings = (window.ultimate_watermark_media && ultimate_watermark_media.strings) || {};
            return strings[key] || fallback;
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
            
            let title, message, confirmText;
            
            if (isRemoveAction) {
                title = this.t('remove_title', 'Remove Watermark');
                message = this.t('remove_message', 'Remove watermarks from %d selected image(s)? The original images will be restored from backup.').replace('%d', itemCount);
                confirmText = this.t('remove_confirm', 'Remove Watermark');
            } else {
                const watermarkName = this.getActionLabel(action) || this.t('watermark', 'watermark');
                title = this.t('apply_title', 'Apply Watermark');
                message = this.t('apply_message', 'Apply "%1$s" to %2$d selected image(s)? This modifies the image files (a backup is kept when backups are enabled).')
                    .replace(/%(?:1\$)?s/, watermarkName)
                    .replace(/%(?:2\$)?d/, itemCount);
                confirmText = this.t('apply_confirm', 'Apply Watermark');
            }
            
            UWNotifications.confirm({
                title: title,
                message: message,
                type: isRemoveAction ? 'warning' : 'info',
                confirmText: confirmText,
                cancelText: this.t('cancel', 'Cancel'),
                confirmButtonType: isRemoveAction ? 'danger' : 'primary'
            }).then(action_result => {
                // confirm() resolves with the action string, not a boolean
                if (action_result === 'confirm') {
                    this.confirmWatermarkAction(action, selectedItems);
                }
            });
        },

        /**
         * Confirm watermark action
         */
        confirmWatermarkAction: function(action, selectedItems) {
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
                                UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), `Successfully removed watermark from ${successCount} image(s).`);
                            } else {
                                UWNotifications.error(wp.i18n.__('Partial Success', 'ultimate-watermark'), `Removed watermark from ${successCount} image(s), but ${errorCount} failed.`);
                            }
                        } else {
                            if (errorCount === 0 && successCount > 0) {
                                UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), detailHtml);
                            } else if (successCount > 0) {
                                UWNotifications.error(wp.i18n.__('Partial Success', 'ultimate-watermark'), detailHtml);
                            } else {
                                UWNotifications.error(wp.i18n.__('Watermark Not Applied', 'ultimate-watermark'), detailHtml);
                            }
                        }
                        
                        // Refresh the media library to show updated images
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        const actionText = isRemoveAction ? 'remove watermark' : 'apply watermark';
                        const errMsg = (response.data && response.data.message) ? response.data.message : `Failed to ${actionText}. Please try again.`;
                        UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), errMsg);
                    }
                },
                error: (xhr, status, error) => {
                    this.hideProcessingIndicator();
                    const actionText = isRemoveAction ? 'remove watermark' : 'apply watermark';
                    UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), `Failed to ${actionText}. Please try again.`);
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
                UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), message);
            } else {
                UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), `Failed to apply "${watermark}" to any images. Please check your image files and try again.`);
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
