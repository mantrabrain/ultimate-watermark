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
        currentTimeframe: '30',
        forceRefresh: false,

        init: function() {
            this.currentTimeframe = $('#analytics-timeframe').val() || 'today';
            this.loadChartData(this.currentTimeframe); // Load initial data
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('change', '#analytics-timeframe', this.handleTimeframeChange.bind(this));
            $(document).on('click', '#refresh-analytics', this.handleRefreshClick.bind(this));
            $(document).on('click', '.chart-btn', this.handleChartTypeChange.bind(this));
        },

        handleTimeframeChange: function(e) {
            const $selector = $(e.currentTarget);
            const timeframe = $selector.val();
            this.currentTimeframe = timeframe;
            
            // Clear cached data for this timeframe to force refresh
            delete this.chartData[timeframe];
            this.loadChartData(timeframe);
        },

        handleRefreshClick: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const $icon = $btn.find('.dashicons');
            const timeframe = $('#analytics-timeframe').val();
            this.currentTimeframe = timeframe;
            
            // Add loading state
            $btn.prop('disabled', true);
            $icon.addClass('dashicons-update').removeClass('dashicons-update').addClass('dashicons-update');
            
            // Clear all cached data to force refresh
            this.chartData = {};
            this.forceRefresh = true;
            
            // Reload data
            this.loadChartData(timeframe);
            
            // Remove loading state after a short delay
            setTimeout(() => {
                $btn.prop('disabled', false);
                $icon.removeClass('dashicons-update');
            }, 1000);
        },

        handleChartTypeChange: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const chartType = $btn.data('chart');
            const $container = $btn.closest('.chart-container');
            
            // Update active button
            $container.find('.chart-btn').removeClass('active');
            $btn.addClass('active');
            
            // Re-render the usage chart with new type
            if ($container.find('#usage-chart').length) {
                const currentData = this.chartData[this.currentTimeframe] || this.chartData[Object.keys(this.chartData)[0]];
                if (currentData && currentData.watermark_usage_over_time) {
                    this.renderWatermarkUsageChart(currentData.watermark_usage_over_time, chartType);
                }
            }
        },

        loadChartData: function(timeframe, chartId = null) {
            // Use cached data if available
            if (this.chartData[timeframe]) {
                this.renderCharts(this.chartData[timeframe], chartId);
                return;
            }

            // Fetch data via AJAX
            const resolvedAjaxUrl = (window.ultimateWatermarkAnalytics && (ultimateWatermarkAnalytics.ajaxUrl || ultimateWatermarkAnalytics.ajaxurl)) || (window.ajaxurl || '');
            $.ajax({
                url: resolvedAjaxUrl,
                type: 'POST',
                data: {
                    action: 'ultimate_watermark_get_analytics_data',
                    nonce: window.ultimateWatermarkAnalytics ? ultimateWatermarkAnalytics.nonce : undefined,
                    timeframe: timeframe,
                    force: UltimateWatermarkAnalytics.forceRefresh ? '1' : '0'
                },
                beforeSend: function() {
                    // Show loading indicators
                    $('.chart-container .chart-content').each(function() {
                        if (!$(this).find('.chart-loading-overlay').length) {
                            $(this).append('<div class="chart-loading-overlay"><span class="dashicons dashicons-update spin"></span></div>');
                        }
                    });
                },
                success: function(response) {
                    if (response.success) {
                        UltimateWatermarkAnalytics.chartData[timeframe] = response.data;
                        UltimateWatermarkAnalytics.updateMetricCards(response.data);
                        UltimateWatermarkAnalytics.renderCharts(response.data, chartId);
                    } else {
                        // no-op
                    }
                },
                error: function(xhr, status, error) {},
                complete: function() {
                    // Hide loading indicators
                    $('.chart-loading-overlay').remove();
                    UltimateWatermarkAnalytics.forceRefresh = false;
                }
            });
        },

        updateMetricCards: function(data) {
            if (!data || !data.image_protection_trends) return;
            const prot = data.image_protection_trends;
            const total = (parseInt(prot.protected || 0, 10) + parseInt(prot.unprotected || 0, 10)) || 0;
            const protectedCount = parseInt(prot.protected || 0, 10);
            const rate = total > 0 ? Math.round((protectedCount / total) * 100) : 0;

            const $totalEl = $('#total-images');
            const $protectedEl = $('#protected-images');
            const $rateEl = $('#protection-rate');
            if ($totalEl.length) $totalEl.text(total);
            if ($protectedEl.length) $protectedEl.text(protectedCount);
            if ($rateEl.length) $rateEl.text(rate + '%');
        },

        renderCharts: function(data, chartId = null) {
            if (!chartId || chartId === 'usage-over-time') {
                this.renderWatermarkUsageChart(data.watermark_usage_over_time, 'auto');
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

        renderWatermarkUsageChart: function(chartData, chartType = 'line') {
            const ctx = document.getElementById('usage-chart');
            if (!ctx) return;

            if (this.charts.watermarkUsageChart) {
                // Update in place
                const effectiveType = (chartType === 'auto') ? ((Array.isArray(chartData.labels) && chartData.labels.length === 1) ? 'bar' : 'line') : chartType;
                this.charts.watermarkUsageChart.config.type = effectiveType;
                this.charts.watermarkUsageChart.data.labels = chartData.labels;
                this.charts.watermarkUsageChart.data.datasets[0].data = chartData.data;
                const ds = this.charts.watermarkUsageChart.data.datasets[0];
                if (effectiveType === 'bar') {
                    ds.backgroundColor = 'rgba(34, 197, 94, 0.8)';
                    ds.borderColor = 'rgba(34, 197, 94, 1)';
                    ds.borderWidth = 1;
                    ds.fill = false;
                    ds.tension = 0;
                } else {
                    ds.backgroundColor = 'rgba(99, 102, 241, 0.2)';
                    ds.borderColor = 'rgba(99, 102, 241, 1)';
                    ds.borderWidth = 2;
                    ds.fill = true;
                    ds.tension = 0.3;
                }
                this.charts.watermarkUsageChart.update();
                return;
            }

            // Different colors for different chart types
            const colors = chartType === 'bar' ? {
                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                borderColor: 'rgba(34, 197, 94, 1)',
                borderWidth: 1
            } : {
                backgroundColor: 'rgba(99, 102, 241, 0.2)',
                borderColor: 'rgba(99, 102, 241, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            };

            const effectiveType = (chartType === 'auto') ? ((Array.isArray(chartData.labels) && chartData.labels.length === 1) ? 'bar' : 'line') : chartType;
            this.charts.watermarkUsageChart = new Chart(ctx, {
                type: effectiveType,
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Watermarks Applied',
                        data: chartData.data,
                        ...colors
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
                this.charts.imageProtectionChart.data.labels = ['Protected', 'Unprotected'];
                this.charts.imageProtectionChart.data.datasets[0].data = [chartData.protected, chartData.unprotected];
                this.charts.imageProtectionChart.update();
                return;
            }

            this.charts.imageProtectionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Protected', 'Unprotected'],
                    datasets: [{
                        data: [chartData.protected, chartData.unprotected],
                        backgroundColor: ['#3b82f6', '#ef4444'], // Blue for protected, Red for unprotected (matching legend)
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
                this.charts.templatePerformanceChart.data.labels = chartData.labels;
                this.charts.templatePerformanceChart.data.datasets[0].data = chartData.data;
                this.charts.templatePerformanceChart.update();
                return;
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
                this.charts.imageSizeDistributionChart.data.labels = chartData.labels;
                this.charts.imageSizeDistributionChart.data.datasets[0].data = chartData.data;
                this.charts.imageSizeDistributionChart.update();
                return;
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