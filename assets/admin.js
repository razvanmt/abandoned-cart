jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize charts
    let dailyChart = null;
    let conversionChart = null;
    
    // Load initial data
    loadStatistics();
    
    // Event handlers
    $('#act-refresh').on('click', function(e) {
        e.preventDefault();
        loadStatistics();
    });
    
    $('#act-period').on('change', function() {
        loadStatistics();
    });
    
    $('#act-export').on('click', function(e) {
        e.preventDefault();
        exportData();
    });
    
    /**
     * Load statistics from server
     */
    function loadStatistics() {
        const period = $('#act-period').val();
        
        // Show loading
        $('#act-loading').show();
        $('#act-stats-container').hide();
        
        $.ajax({
            url: actAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'act_get_stats',
                period: period,
                nonce: actAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboard(response.data);
                } else {
                    showError('Failed to load statistics: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showError('Ajax error: ' + error);
            },
            complete: function() {
                $('#act-loading').hide();
                $('#act-stats-container').show();
            }
        });
    }
    
    /**
     * Update dashboard with new data
     */
    function updateDashboard(data) {
        const summary = data.summary;
        const dailyStats = data.daily_stats;
        const topProducts = data.top_abandoned_products;
        
        // Update summary cards
        $('#total-carts').text(summary.total_carts.toLocaleString());
        $('#abandoned-carts').text(summary.abandoned_carts.toLocaleString());
        $('#converted-carts').text(summary.converted_carts.toLocaleString());
        $('#pending-carts').text(summary.pending_carts.toLocaleString());
        $('#abandonment-rate').text(summary.abandonment_rate);
        $('#conversion-rate').text(summary.conversion_rate);
        
        // Format and update revenue
        $('#lost-revenue').text(formatCurrencyWithSymbol(summary.lost_revenue));
        $('#recovered-revenue').text(formatCurrencyWithSymbol(summary.recovered_revenue));
        
        // Update charts
        updateDailyChart(dailyStats);
        updateConversionChart(summary);
        
        // Update tables
        updateTopProductsTable(topProducts);
        updateDailyActivityTable(dailyStats);
    }
    
    /**
     * Update daily activity chart
     */
    function updateDailyChart(dailyStats) {
        const ctx = document.getElementById('daily-chart');
        if (!ctx) return;
        
        // Prepare data
        const labels = dailyStats.map(item => formatDate(item.date)).reverse();
        const totalData = dailyStats.map(item => parseInt(item.total)).reverse();
        const abandonedData = dailyStats.map(item => parseInt(item.abandoned)).reverse();
        const convertedData = dailyStats.map(item => parseInt(item.converted)).reverse();
        
        // Destroy existing chart
        if (dailyChart) {
            dailyChart.destroy();
        }
        
        // Create new chart
        dailyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Carts',
                        data: totalData,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Abandoned',
                        data: abandonedData,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Converted',
                        data: convertedData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }
    
    /**
     * Update conversion overview chart
     */
    function updateConversionChart(summary) {
        const ctx = document.getElementById('conversion-chart');
        if (!ctx) return;
        
        // Destroy existing chart
        if (conversionChart) {
            conversionChart.destroy();
        }
        
        // Create pie chart
        conversionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Abandoned', 'Converted', 'Pending'],
                datasets: [{
                    data: [summary.abandoned_carts, summary.converted_carts, summary.pending_carts],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(255, 206, 86, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 206, 86, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    /**
     * Update top abandoned products table
     */
    function updateTopProductsTable(products) {
        const tbody = $('#top-abandoned-products');
        tbody.empty();
        
        if (products.length === 0) {
            tbody.append('<tr><td colspan="3" class="act-no-data">No abandoned products found</td></tr>');
            return;
        }
        
        products.forEach(function(product) {
            const row = $('<tr></tr>');
            row.append('<td>' + escapeHtml(product.product_name) + '</td>');
            row.append('<td>' + parseInt(product.count).toLocaleString() + '</td>');
            row.append('<td>' + formatCurrencyWithSymbol(parseFloat(product.lost_revenue || 0)) + '</td>');
            tbody.append(row);
        });
    }
    
    /**
     * Update daily activity table
     */
    function updateDailyActivityTable(dailyStats) {
        const tbody = $('#daily-activity');
        tbody.empty();
        
        if (dailyStats.length === 0) {
            tbody.append('<tr><td colspan="6" class="act-no-data">No activity data found</td></tr>');
            return;
        }
        
        dailyStats.forEach(function(day) {
            const total = parseInt(day.total);
            const abandoned = parseInt(day.abandoned);
            const converted = parseInt(day.converted);
            const pending = parseInt(day.pending);
            const rate = total > 0 ? ((abandoned / total) * 100).toFixed(2) : '0.00';
            
            const row = $('<tr></tr>');
            row.append('<td>' + formatDate(day.date) + '</td>');
            row.append('<td>' + total.toLocaleString() + '</td>');
            row.append('<td><span class="act-status-abandoned">' + abandoned.toLocaleString() + '</span></td>');
            row.append('<td><span class="act-status-converted">' + converted.toLocaleString() + '</span></td>');
            row.append('<td><span class="act-status-pending">' + pending.toLocaleString() + '</span></td>');
            row.append('<td>' + rate + '%</td>');
            tbody.append(row);
        });
    }
    
    /**
     * Export data as CSV
     */
    function exportData() {
        const form = $('<form></form>');
        form.attr('method', 'post');
        form.attr('action', actAjax.ajaxurl);
        form.append('<input type="hidden" name="action" value="act_export_data">');
        form.append('<input type="hidden" name="nonce" value="' + actAjax.nonce + '">');
        
        $('body').append(form);
        form.submit();
        form.remove();
    }
    
    /**
     * Format currency amount
     */
    function formatCurrency(amount) {
        return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    /**
     * Get currency symbol from WooCommerce settings
     */
    function getCurrencySymbol() {
        return actAjax.currency_symbol || '$';
    }
    
    /**
     * Format currency with proper symbol position
     */
    function formatCurrencyWithSymbol(amount) {
        const symbol = getCurrencySymbol();
        const formattedAmount = formatCurrency(amount);
        const position = actAjax.currency_position || 'left';
        
        switch (position) {
            case 'left':
                return symbol + formattedAmount;
            case 'right':
                return formattedAmount + symbol;
            case 'left_space':
                return symbol + ' ' + formattedAmount;
            case 'right_space':
                return formattedAmount + ' ' + symbol;
            default:
                return symbol + formattedAmount;
        }
    }
    
    /**
     * Format date for display
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        const notice = $('<div class="notice notice-error is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
        $('.act-admin-wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
});
