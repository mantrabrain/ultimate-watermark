/**
 * Ultimate Watermark - Analytics Page JavaScript
 */

(function($) {
    'use strict';

    /**
     * Analytics Dashboard Handler
     */
    const AnalyticsDashboard = {
        
        charts: {},
        
        /**
         * Initialize the analytics dashboard
         */
        init: function() {
            this.bindEvents();
            this.initializeCharts();
            this.startAutoRefresh();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Timeframe selector
            $(document).on('change', '#analytics-timeframe', this.handleTimeframeChange.bind(this));
            
            // Refresh button
            $(document).on('click', '#refresh-analytics', this.refreshData.bind(this));
            
            // Chart type buttons
            $(document).on('click', '.chart-btn', this.handleChartTypeChange.bind(this));
        },

        /**
         * Initialize all charts
         */
        initializeCharts: function() {
            this.initUsageChart();
            this.initProtectionChart();
            this.initTemplatesChart();
            this.initSizesChart();
        },

        /**
         * Initialize usage over time chart
         */
        initUsageChart: function() {
            const ctx = document.getElementById('usage-chart');
            if (!ctx) return;

            this.charts.usage = new Chart(ctx, {
                type: 'line',
                data: window.ultimateWatermarkAnalytics.usageData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    elements: {
                        point: {
                            radius: 4,
                            hoverRadius: 6,
                            backgroundColor: '#3b82f6',
                            borderColor: '#fff',
                            borderWidth: 2
                        },
                        line: {
                            borderWidth: 3
                        }
                    }
                }
            });
        },

        /**
         * Initialize protection trends chart
         */
        initProtectionChart: function() {
            const ctx = document.getElementById('protection-chart');
            if (!ctx) return;

            this.charts.protection = new Chart(ctx, {
                type: 'doughnut',
                data: window.ultimateWatermarkAnalytics.protectionData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    cutout: '60%',
                    elements: {
                        arc: {
                            borderWidth: 0
                        }
                    }
                }
            });
        },

        /**
         * Initialize templates performance chart
         */
        initTemplatesChart: function() {
            const ctx = document.getElementById('templates-chart');
            if (!ctx) return;

            this.charts.templates = new Chart(ctx, {
                type: 'bar',
                data: window.ultimateWatermarkAnalytics.templatesData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 11
                                },
                                maxRotation: 45
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    elements: {
                        bar: {
                            borderRadius: 4,
                            borderSkipped: false
                        }
                    }
                }
            });
        },

        /**
         * Initialize sizes distribution chart
         */
        initSizesChart: function() {
            const ctx = document.getElementById('sizes-chart');
            if (!ctx) return;

            this.charts.sizes = new Chart(ctx, {
                type: 'bar',
                data: window.ultimateWatermarkAnalytics.sizesData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    elements: {
                        bar: {
                            borderRadius: 4,
                            borderSkipped: false
                        }
                    }
                }
            });
        },

        /**
         * Handle timeframe change
         */
        handleTimeframeChange: function(e) {
            const timeframe = $(e.target).val();
            this.loadData(timeframe);
        },

        /**
         * Handle chart type change
         */
        handleChartTypeChange: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const chartType = $btn.data('chart');
            
            // Update button states
            $btn.siblings('.chart-btn').removeClass('active');
            $btn.addClass('active');
            
            // Update chart type
            if (this.charts.usage && chartType) {
                this.charts.usage.config.type = chartType;
                this.charts.usage.update();
            }
        },

        /**
         * Refresh data
         */
        refreshData: function() {
            const $btn = $('#refresh-analytics');
            const $icon = $btn.find('.dashicons');
            
            // Add loading state
            $btn.prop('disabled', true);
            $icon.addClass('dashicons-update').removeClass('dashicons-update').addClass('dashicons-update');
            
            // Simulate refresh (in real implementation, this would make an AJAX call)
            setTimeout(() => {
                this.loadData($('#analytics-timeframe').val());
                
                // Remove loading state
                $btn.prop('disabled', false);
                $icon.removeClass('dashicons-update');
            }, 1000);
        },

        /**
         * Load data for specific timeframe
         */
        loadData: function(timeframe) {
            // In a real implementation, this would make an AJAX call
            // For now, we'll just show a loading state
            this.showLoadingState();
            
            // Simulate data loading
            setTimeout(() => {
                this.hideLoadingState();
                this.updateCharts(timeframe);
            }, 500);
        },

        /**
         * Update all charts with new data
         */
        updateCharts: function(timeframe) {
            // In a real implementation, this would update chart data
            // For now, we'll just trigger a refresh
            Object.values(this.charts).forEach(chart => {
                if (chart) {
                    chart.update('active');
                }
            });
        },

        /**
         * Show loading state
         */
        showLoadingState: function() {
            $('.chart-content').addClass('loading');
            $('.metric-number').addClass('loading');
        },

        /**
         * Hide loading state
         */
        hideLoadingState: function() {
            $('.chart-content').removeClass('loading');
            $('.metric-number').removeClass('loading');
        },

        /**
         * Start auto refresh
         */
        startAutoRefresh: function() {
            // Auto refresh every 5 minutes
            setInterval(() => {
                this.refreshData();
            }, 300000);
        },

        /**
         * Animate metric numbers
         */
        animateMetricNumbers: function() {
            $('.metric-number').each(function() {
                const $this = $(this);
                const finalValue = parseInt($this.text());
                const duration = 1000;
                const step = finalValue / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += step;
                    if (current >= finalValue) {
                        current = finalValue;
                        clearInterval(timer);
                    }
                    $this.text(Math.floor(current));
                }, 16);
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AnalyticsDashboard.init();
        
        // Animate numbers on page load
        setTimeout(() => {
            AnalyticsDashboard.animateMetricNumbers();
        }, 500);
    });

    // Add loading styles
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .chart-content.loading {
                position: relative;
            }
            .chart-content.loading::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 20px;
                height: 20px;
                margin: -10px 0 0 -10px;
                border: 2px solid #f3f4f6;
                border-top: 2px solid #3b82f6;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            .metric-number.loading {
                background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
                background-size: 200% 100%;
                animation: shimmer 1.5s infinite;
                border-radius: 4px;
                color: transparent;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            @keyframes shimmer {
                0% { background-position: -200% 0; }
                100% { background-position: 200% 0; }
            }
        `)
        .appendTo('head');

})(jQuery);
