/**
 * Ultimate Watermark Toast Notification System
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */

(function($) {
    'use strict';

    const UltimateWatermarkToast = {
        container: null,
        defaultDuration: 5000, // 5 seconds

        /**
         * Initialize the toast container.
         */
        init: function() {
            this.container = $('#ultimate-watermark-toast-container');
            if (this.container.length === 0) {
                this.container = $('<div id="ultimate-watermark-toast-container"></div>');
                $('body').append(this.container);
            }
            this.listenForAdminNotices();
        },

        /**
         * Listen for hidden admin notices to convert them to toasts.
         */
        listenForAdminNotices: function() {
            $('.ultimate-watermark-toast-data').each(function() {
                const $this = $(this);
                const message = $this.data('message');
                const type = $this.data('type');
                UltimateWatermarkToast.show(message, type);
                $this.remove(); // Remove the hidden div after processing
            });
        },

        /**
         * Show a toast notification.
         * @param {string} message The message to display.
         * @param {string} type The type of toast (success, error, warning, info).
         * @param {number} duration The duration in milliseconds before auto-dismissing.
         */
        show: function(message, type = 'info', duration = this.defaultDuration) {
            const icon = this.getIcon(type);
            const toast = $(`
                <div class="ultimate-watermark-toast ${type}" style="--toast-duration: ${duration / 1000}s;">
                    ${icon}
                    <div class="ultimate-watermark-toast-message">${message}</div>
                    <div class="ultimate-watermark-toast-progress"></div>
                </div>
            `);

            this.container.append(toast);

            // Auto-dismiss
            setTimeout(() => {
                toast.css('opacity', '0');
                toast.slideUp(300, function() {
                    $(this).remove();
                });
            }, duration);

            // Dismiss on click
            toast.on('click', function() {
                $(this).stop(true, true).css('opacity', '0');
                $(this).slideUp(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Get Dashicon for toast type.
         * @param {string} type
         * @returns {string} HTML for the Dashicon.
         */
        getIcon: function(type) {
            switch (type) {
                case 'success':
                    return '<span class="dashicons dashicons-yes"></span>';
                case 'error':
                    return '<span class="dashicons dashicons-no-alt"></span>';
                case 'warning':
                    return '<span class="dashicons dashicons-warning"></span>';
                case 'info':
                default:
                    return '<span class="dashicons dashicons-info"></span>';
            }
        },

        // Helper methods for different toast types
        success: function(message, duration) {
            this.show(message, 'success', duration);
        },
        error: function(message, duration) {
            this.show(message, 'error', duration);
        },
        warning: function(message, duration) {
            this.show(message, 'warning', duration);
        },
        info: function(message, duration) {
            this.show(message, 'info', duration);
        }
    };

    // Initialize the toast system
    $(document).ready(function() {
        UltimateWatermarkToast.init();
    });

    // Make it globally accessible for other scripts
    window.UltimateWatermarkToast = UltimateWatermarkToast;

})(jQuery);
