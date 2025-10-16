/**
 * Ultimate Watermark Dashboard JavaScript
 * 
 * Dashboard page specific functionality
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Dashboard functionality
     */
    const UltimateWatermarkDashboard = {
        
        /**
         * Initialize dashboard functionality
         */
        init: function() {
            this.bindEvents();
            this.loadDashboardData();
            this.initCharts();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Quick action buttons
            $(document).on('click', '.ultimate-watermark-quick-action', this.handleQuickAction);
            
            // Refresh data
            $(document).on('click', '.ultimate-watermark-refresh', this.refreshData);
        },

        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            this.showLoading();
            
            // Simulate API call
            setTimeout(() => {
                this.updateStats();
                this.updateRecentActivity();
                this.hideLoading();
            }, 1000);
        },

        /**
         * Update statistics
         */
        updateStats: function() {
            const stats = {
                totalImages: this.getRandomNumber(50, 200),
                watermarkedImages: this.getRandomNumber(10, 50),
                totalWatermarks: this.getRandomNumber(3, 10),
                lastWatermark: this.getRandomDate()
            };

            $('.stat-value').each(function(index) {
                const $this = $(this);
                const value = Object.values(stats)[index];
                
                if (typeof value === 'number') {
                    $this.text(value.toLocaleString());
                } else {
                    $this.text(value);
                }
            });
        },

        /**
         * Update recent activity
         */
        updateRecentActivity: function() {
            const activities = [
                {
                    action: 'Watermark Applied',
                    image: 'sample-image-1.jpg',
                    time: '2 minutes ago'
                },
                {
                    action: 'New Watermark Created',
                    image: 'company-logo.png',
                    time: '1 hour ago'
                },
                {
                    action: 'Watermark Removed',
                    image: 'product-photo.jpg',
                    time: '3 hours ago'
                }
            ];

            // This would populate a recent activity section if it exists
            // Recent activities loaded
        },

        /**
         * Initialize charts (placeholder for future implementation)
         */
        initCharts: function() {
            // Chart.js or other charting library would be initialized here
            // Charts initialized
        },

        /**
         * Handle quick action clicks
         */
        handleQuickAction: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const action = $button.data('action');
            
            UltimateWatermarkDashboard.showLoading();
            
            // Simulate action
            setTimeout(() => {
                UltimateWatermarkDashboard.hideLoading();
                UltimateWatermarkDashboard.showNotice(
                    `Quick action "${action}" completed successfully!`,
                    'success'
                );
            }, 1500);
        },

        /**
         * Refresh dashboard data
         */
        refreshData: function() {
            UltimateWatermarkDashboard.loadDashboardData();
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('.ultimate-watermark-dashboard').addClass('ultimate-watermark-loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('.ultimate-watermark-dashboard').removeClass('ultimate-watermark-loading');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible ultimate-watermark-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.ultimate-watermark-dashboard h1').after($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },

        /**
         * Get random number
         */
        getRandomNumber: function(min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        },

        /**
         * Get random date
         */
        getRandomDate: function() {
            const now = new Date();
            const hours = Math.floor(Math.random() * 24);
            const minutes = Math.floor(Math.random() * 60);
            
            now.setHours(now.getHours() - hours);
            now.setMinutes(now.getMinutes() - minutes);
            
            return now.toLocaleString();
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        UltimateWatermarkDashboard.init();
    });

    /**
     * Expose to global scope
     */
    window.UltimateWatermarkDashboard = UltimateWatermarkDashboard;

})(jQuery);
