/**
 * Ultimate Watermark Frontend JavaScript
 * 
 * Frontend functionality for image protection based on settings
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Frontend functionality
     */
    const UltimateWatermarkFrontend = {
        
        /**
         * Initialize frontend functionality
         */
        init: function() {
            this.checkSettings();
        },

        /**
         * Check settings and apply protection accordingly
         */
        checkSettings: function() {
            // Get settings from localized data
            if (typeof ultimateWatermark !== 'undefined' && ultimateWatermark.settings) {
                const settings = ultimateWatermark.settings;
                
                // Check if protection should be enabled for logged-in users
                if (settings.enable_protection_logged_in === '1' || !ultimateWatermark.isLoggedIn) {
                    if (settings.disable_rightclick === '1') {
                        this.disableRightClick();
                    }
                    
                    if (settings.disable_drag_drop === '1') {
                        this.disableDragDrop();
                    }
                }
            } else {
                // Fallback: Apply basic protection if settings not available
                this.disableRightClick();
                this.disableDragDrop();
            }
        },

        /**
         * Disable right-click on images
         */
        disableRightClick: function() {
            $(document).on('contextmenu', 'img', function(e) {
                e.preventDefault();
                return false;
            });
        },

        /**
         * Disable drag and drop
         */
        disableDragDrop: function() {
            $(document).on('dragstart', 'img', function(e) {
                e.preventDefault();
                return false;
            });
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        UltimateWatermarkFrontend.init();
    });

})(jQuery);
