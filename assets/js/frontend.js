/**
 * Ultimate Watermark Frontend JavaScript
 * 
 * Basic frontend functionality for image protection
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
            this.disableRightClick();
            this.disableDragDrop();
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
