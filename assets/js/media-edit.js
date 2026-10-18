/**
 * Ultimate Watermark - Media Edit Page JavaScript
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
            
            // Handle size card clicks for expanding/collapsing watermark lists
            $(document).on('click', '.size-card', this.handleSizeCardClick.bind(this));
            
            // Handle expand all button
            $(document).on('click', '.expand-all-btn', this.handleExpandAllClick.bind(this));
            
            // Handle image preview clicks
            $(document).on('click', '.size-preview-image', this.handleImagePreviewClick.bind(this));
        },


        /**
         * Handle remove all watermarks button click
         */
        handleRemoveAllWatermarks: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const attachmentId = $button.data('attachment-id');
            
            // Show confirmation
            UWNotifications.confirm({
                title: wp.i18n.__('Remove All Watermarks', 'ultimate-watermark'),
                message: ultimate_watermark_media_edit.strings.confirm_remove_all || wp.i18n.__('Are you sure you want to remove ALL watermarks from this image? This will restore the original image from backup.', 'ultimate-watermark'),
                type: 'warning',
                confirmText: wp.i18n.__('Remove All', 'ultimate-watermark'),
                cancelText: wp.i18n.__('Cancel', 'ultimate-watermark'),
                confirmButtonType: 'danger'
            }).then(action => {
                // UWNotifications.confirm() resolves with the data-action of
                // the clicked button ('confirm' / 'cancel') — NOT a boolean.
                // The previous truthy check (`if (!confirmed) return;`) treated
                // the string 'cancel' as truthy and let the AJAX run on Cancel.
                if (action !== 'confirm') return;

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
                            UWNotifications.success(wp.i18n.__('Success', 'ultimate-watermark'), ultimate_watermark_media_edit.strings.removed_all || wp.i18n.__('All watermarks removed successfully', 'ultimate-watermark'));
                            
                            // Update the UI to show "no watermarks" state
                            setTimeout(() => {
                                $('#ultimate-watermark-info .watermark-status.has-watermarks').replaceWith(`
                                    <div class="watermark-status no-watermarks">
                                        <span class="dashicons dashicons-format-image"></span>
                                        <span class="status-text">${ultimate_watermark_media_edit.strings.no_watermarks || wp.i18n.__('No watermarks applied', 'ultimate-watermark')}</span>
                                    </div>
                                `);
                            }, 2000);
                            
                        } else {
                            this.showErrorStateAll();
                            UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), (response.data && response.data.message) || ultimate_watermark_media_edit.strings.error);
                        }
                    },
                    error: (xhr, status, error) => {
                        this.hideLoadingStateAll($button);
                        this.showErrorStateAll();
                        UWNotifications.error(wp.i18n.__('Error', 'ultimate-watermark'), ultimate_watermark_media_edit.strings.error);
                    }
                });
            });
        },


        /**
         * Show loading state for remove all
         */
        showLoadingStateAll: function($button) {
            $button.prop('disabled', true);
            $button.find('.dashicons').remove();
            $button.text(ultimate_watermark_media_edit.strings.removing || wp.i18n.__('Removing...', 'ultimate-watermark'));
        },

        /**
         * Hide loading state for remove all
         */
        hideLoadingStateAll: function($button) {
            $button.prop('disabled', false);
            $button.html('<span class="dashicons dashicons-trash"></span>' + (ultimate_watermark_media_edit.strings.remove_all || wp.i18n.__('Remove All Watermarks', 'ultimate-watermark')));
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
         * Handle size card click for expanding/collapsing watermark lists
         */
        handleSizeCardClick: function(e) {
            // Let links and buttons inside the card (e.g. "edit watermark") work normally.
            if ($(e.target).closest('a, button').length) {
                return;
            }
            e.preventDefault();
            
            const $card = $(e.currentTarget);
            const size = $card.data('size');
            const $watermarkDetails = $card.find('.watermark-details');
            
            if ($watermarkDetails.length) {
                $watermarkDetails.toggleClass('expanded');
                
                // Update card appearance
                if ($watermarkDetails.hasClass('expanded')) {
                    $card.addClass('expanded');
                } else {
                    $card.removeClass('expanded');
                }
            }
        },

        /**
         * Handle expand all button click
         */
        handleExpandAllClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $allDetails = $('.watermark-details');
            const $allCards = $('.size-card');
            
            if ($allDetails.length === 0) return;
            
            const isExpanded = $allDetails.first().hasClass('expanded');
            
            if (isExpanded) {
                // Collapse all
                $allDetails.removeClass('expanded');
                $allCards.removeClass('expanded');
                $button.html('<span class="dashicons dashicons-arrow-down-alt2"></span>Show Details');
            } else {
                // Expand all
                $allDetails.addClass('expanded');
                $allCards.addClass('expanded');
                $button.html('<span class="dashicons dashicons-arrow-up-alt2"></span>Hide Details');
            }
        },

        /**
         * Handle image preview click
         */
        handleImagePreviewClick: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $img = $(e.currentTarget);
            const imageUrl = $img.attr('src');
            const size = $img.data('size');
            const attachmentId = $img.data('attachment-id');
            
            // Try to find the actual img tag inside wp_attachment_image
            let $mainImage = $('.wp_attachment_image .thumbnail');
            if (!$mainImage.length) {
                $mainImage = $('.wp_attachment_image img');
            }
            if (!$mainImage.length) {
                $mainImage = $('#attachment-details .attachment-media-view .thumbnail img');
            }
            if (!$mainImage.length) {
                $mainImage = $('.attachment-media-view .thumbnail img');
            }
            if (!$mainImage.length) {
                $mainImage = $('.attachment-details img[src*="' + attachmentId + '"]');
            }
            if (!$mainImage.length) {
                $mainImage = $('img[src*="' + attachmentId + '"]').first();
            }
            
            if ($mainImage.length && imageUrl) {
                // Store original src for potential restoration
                if (!$mainImage.data('original-src')) {
                    $mainImage.data('original-src', $mainImage.attr('src'));
                }
                
                // Update the main image
                $mainImage.attr('src', imageUrl);
                
                // Add a subtle highlight effect
                $mainImage.addClass('preview-highlight');
                setTimeout(() => {
                    $mainImage.removeClass('preview-highlight');
                }, 1000);
            } else {
                // Fallback: try to update any image in the attachment area
                const $anyImage = $('#attachment-details img').first();
                if ($anyImage.length && imageUrl) {
                    $anyImage.attr('src', imageUrl);
                }
            }
        }

    };

    // Initialize when document is ready
    $(document).ready(function() {
        MediaEditWatermark.init();
    });

})(jQuery);
