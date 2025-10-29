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
                title: 'Remove All Watermarks',
                message: ultimate_watermark_media_edit.strings.confirm_remove_all || 'Are you sure you want to remove ALL watermarks from this image? This will restore the original image from backup.',
                type: 'warning',
                confirmText: 'Remove All',
                cancelText: 'Cancel',
                confirmButtonType: 'danger'
            }).then(confirmed => {
                if (!confirmed) return;
                
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
                            UWNotifications.success('Success', ultimate_watermark_media_edit.strings.removed_all || 'All watermarks removed successfully');
                            
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
                            UWNotifications.error('Error', response.data || ultimate_watermark_media_edit.strings.error);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Ultimate Watermark: AJAX error:', xhr, status, error);
                        this.hideLoadingStateAll($button);
                        this.showErrorStateAll();
                        UWNotifications.error('Error', ultimate_watermark_media_edit.strings.error);
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
         * Handle size card click for expanding/collapsing watermark lists
         */
        handleSizeCardClick: function(e) {
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
