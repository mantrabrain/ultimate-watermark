/**
 * Ultimate Watermark - Analytics Page JavaScript
 */

(function($) {
    'use strict';

    /**
     * Analytics Dashboard Handler
     */
    const UltimateWatermarkAnalytics = {
        charts: {},
        chartData: {},

        init: function() {
            console.log('Ultimate Watermark Analytics: Initializing...');
            this.loadChartData('30'); // Load initial 30-day data
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('change', '.timeframe-selector', this.handleTimeframeChange.bind(this));
        },

        handleTimeframeChange: function(e) {
            const $selector = $(e.currentTarget);
            const timeframe = $selector.val();
            const chartId = $selector.data('chart');
            this.loadChartData(timeframe, chartId);
        },

        loadChartData: function(timeframe, chartId = null) {
            // Use cached data if available
            if (this.chartData[timeframe]) {
                this.renderCharts(this.chartData[timeframe], chartId);
                return;
            }

            // Fetch data via AJAX
            $.ajax({
                url: ultimateWatermarkAnalytics.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ultimate_watermark_get_analytics_data',
                    nonce: ultimateWatermarkAnalytics.nonce,
                    timeframe: timeframe
                },
                beforeSend: function() {
                    // Show loading indicators
                    $('.chart-card .card-content').each(function() {
                        $(this).append('<div class="chart-loading-overlay"><span class="dashicons dashicons-update spin"></span></div>');
                    });
                },
                success: function(response) {
                    console.log('Ultimate Watermark Analytics: Data received:', response);
                    if (response.success) {
                        UltimateWatermarkAnalytics.chartData[timeframe] = response.data;
                        UltimateWatermarkAnalytics.renderCharts(response.data, chartId);
                    } else {
                        console.error('Error fetching analytics data:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                },
                complete: function() {
                    // Hide loading indicators
                    $('.chart-loading-overlay').remove();
                }
            });
        },

        renderCharts: function(data, chartId = null) {
            if (!chartId || chartId === 'usage-over-time') {
                this.renderWatermarkUsageChart(data.watermark_usage_over_time);
            }
            if (!chartId || chartId === 'image-protection') {
                this.renderImageProtectionChart(data.image_protection_trends);
            }
            if (!chartId || chartId === 'template-performance') {
                this.renderTemplatePerformanceChart(data.template_performance);
            }
            if (!chartId || chartId === 'image-size-distribution') {
                this.renderImageSizeDistributionChart(data.image_size_distribution);
            }
        },

        renderWatermarkUsageChart: function(chartData) {
            const ctx = document.getElementById('usage-chart');
            console.log('Ultimate Watermark Analytics: Rendering usage chart, canvas found:', !!ctx, 'Data:', chartData);
            if (!ctx) return;

            if (this.charts.watermarkUsageChart) {
                this.charts.watermarkUsageChart.destroy();
            }

            this.charts.watermarkUsageChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Watermarks Applied',
                        data: chartData.data,
                        backgroundColor: 'rgba(99, 102, 241, 0.2)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        },

        renderImageProtectionChart: function(chartData) {
            const ctx = document.getElementById('protection-chart');
            if (!ctx) return;

            if (this.charts.imageProtectionChart) {
                this.charts.imageProtectionChart.destroy();
            }

            this.charts.imageProtectionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Protected', 'Unprotected'],
                    datasets: [{
                        data: [chartData.protected, chartData.unprotected],
                        backgroundColor: ['rgba(76, 175, 80, 0.8)', 'rgba(255, 152, 0, 0.8)'],
                        borderColor: ['#ffffff', '#ffffff'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(tooltipItem) {
                                    return tooltipItem.label + ': ' + tooltipItem.raw;
                                }
                            }
                        }
                    }
                }
            });
        },

        renderTemplatePerformanceChart: function(chartData) {
            const ctx = document.getElementById('templates-chart');
            if (!ctx) return;

            if (this.charts.templatePerformanceChart) {
                this.charts.templatePerformanceChart.destroy();
            }

            this.charts.templatePerformanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Usage Count',
                        data: chartData.data,
                        backgroundColor: 'rgba(255, 99, 132, 0.8)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        },

        renderImageSizeDistributionChart: function(chartData) {
            const ctx = document.getElementById('sizes-chart');
            if (!ctx) return;

            if (this.charts.imageSizeDistributionChart) {
                this.charts.imageSizeDistributionChart.destroy();
            }

            this.charts.imageSizeDistributionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Watermarked Images',
                        data: chartData.data,
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
    };

    $(document).ready(function() {
        UltimateWatermarkAnalytics.init();
    });

})(jQuery);