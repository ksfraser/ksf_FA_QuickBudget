/**
 * inflation_charts.js
 *
 * Renders historical inflation data as tables and charts.
 * Supports Issue #2: FR-45, FR-46, FR-47, FR-48, FR-50.
 *
 * @since 1.1.0
 */

(function() {
    'use strict';

    var currentData = null;

    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('filter-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                loadData();
            });
        }
        // Auto-load if URL has params
        if (window.location.search.indexOf('level=') !== -1) {
            loadData();
        }
    });

    function loadData() {
        var form = document.getElementById('filter-form');
        var formData = new FormData(form);
        var params = new URLSearchParams(formData).toString();

        fetch('quickbudget_report.php?action=data&' + params)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                currentData = data;
                renderResults(data, formData.get('view') || 'table');
            })
            .catch(function(err) {
                document.getElementById('report-results').innerHTML =
                    '<div class="alert alert-danger">Error loading data: ' + err.message + '</div>';
            });
    }

    function renderResults(data, viewMode) {
        var container = document.getElementById('report-results');
        var html = '';

        // Summary card
        if (data.summary) {
            html += renderSummary(data.summary, data.trend_indicators);
        }

        // View content
        if (viewMode === 'chart') {
            html += '<div id="chart-container" class="mb-3">';
            html += '<canvas id="inflation-chart" height="300"></canvas>';
            html += '</div>';
            html += renderTable(data);
        } else {
            html += renderTable(data);
        }

        // Context card
        if (data.context) {
            html += renderContext(data.context);
        }

        container.innerHTML = html;

        if (viewMode === 'chart' && data.items && data.items.length > 0) {
            renderChart(data);
        }
    }

    function renderSummary(stats, trendIndicators) {
        var html = '<div class="row mb-3">';

        // Stats card
        html += '<div class="col-md-6">';
        html += '<div class="card">';
        html += '<div class="card-header">Summary Statistics</div>';
        html += '<div class="card-body">';
        html += '<table class="table table-sm">';

        var rows = [
            ['Mean', formatPercent(stats.mean)],
            ['Median', formatPercent(stats.median)],
            ['Mode', stats.mode !== null ? formatPercent(stats.mode) : 'N/A'],
            ['Min', formatPercent(stats.min)],
            ['Max', formatPercent(stats.max)],
            ['Std Dev', formatPercent(stats.stddev)],
            ['Data Points', stats.count]
        ];

        rows.forEach(function(r) {
            html += '<tr><td><strong>' + r[0] + '</strong></td><td>' + r[1] + '</td></tr>';
        });

        html += '</table>';
        html += '</div></div></div>';

        // Trend indicators card
        if (trendIndicators) {
            html += '<div class="col-md-6">';
            html += '<div class="card">';
            html += '<div class="card-header">Trend Indicators (CAGR)</div>';
            html += '<div class="card-body">';
            html += '<table class="table table-sm">';

            var periods = [1, 3, 5, 7, 10];
            periods.forEach(function(p) {
                var val = trendIndicators[p];
                var label = p + ' Year';
                var display = val !== null ? formatPercent(val) : 'N/A';
                html += '<tr><td><strong>' + label + '</strong></td><td>' + display + '</td></tr>';
            });

            html += '</table>';
            html += '</div></div></div>';
        }

        html += '</div>';
        return html;
    }

    function renderTable(data) {
        if (!data.items || data.items.length === 0) {
            return '<p class="text-muted">No data available for the selected filters.</p>';
        }

        // Check if we have a single item with yearly entries, or multiple items
        var firstItem = data.items[0];
        var isSingleItem = firstItem.year !== undefined;

        if (isSingleItem) {
            return renderSingleItemTable(data.items);
        } else {
            return renderMultiItemTable(data.items);
        }
    }

    function renderSingleItemTable(entries) {
        var html = '<div class="card mb-3">';
        html += '<div class="card-header">Year-by-Year Data</div>';
        html += '<div class="card-body">';
        html += '<table class="table table-sm table-striped">';
        html += '<thead><tr>';
        html += '<th>Year</th>';
        html += '<th>Prior Actual</th>';
        html += '<th>Current Actual</th>';
        html += '<th>YoY Rate</th>';
        html += '<th>Status</th>';
        html += '<th>Action</th>';
        html += '</tr></thead>';
        html += '<tbody>';

        entries.forEach(function(e) {
            if (e.yoy_rate !== null) {
                var status = getStatus(e.yoy_rate, entries);
                html += '<tr>';
                html += '<td>' + e.year + '</td>';
                html += '<td>' + formatMoney(e.actual_prior) + '</td>';
                html += '<td>' + formatMoney(e.actual_current) + '</td>';
                html += '<td>' + formatPercent(e.yoy_rate) + '</td>';
                html += '<td><span class="badge ' + status.class + '">' + status.label + '</span></td>';
                html += '<td><button class="btn btn-xs btn-outline-primary transfer-btn" ' +
                    'data-level="' + getLevelFromUrl() + '" ' +
                    'data-reference="' + getReferenceFromUrl() + '" ' +
                    'data-year="' + e.year + '" ' +
                    'data-rate="' + e.yoy_rate + '">' +
                    'Transfer</button></td>';
                html += '</tr>';
            }
        });

        html += '</tbody></table>';
        html += '<div class="mt-2">';
        html += '<button class="btn btn-sm btn-primary" id="bulk-transfer-btn">Transfer All to Config</button>';
        html += '</div>';
        html += '</div></div>';

        return html;
    }

    function renderMultiItemTable(items) {
        var html = '<div class="card mb-3">';
        html += '<div class="card-header">Summary by Item</div>';
        html += '<div class="card-body">';
        html += '<table class="table table-sm table-striped">';
        html += '<thead><tr>';
        html += '<th>ID</th>';
        html += '<th>Name</th>';
        html += '<th>Mean</th>';
        html += '<th>Median</th>';
        html += '<th>Std Dev</th>';
        html += '<th>1Y CAGR</th>';
        html += '<th>5Y CAGR</th>';
        html += '<th>Action</th>';
        html += '</tr></thead>';
        html += '<tbody>';

        items.forEach(function(item) {
            html += '<tr>';
            html += '<td>' + item.id + '</td>';
            html += '<td>' + (item.name || item.id) + '</td>';
            html += '<td>' + formatPercent(item.stats.mean) + '</td>';
            html += '<td>' + formatPercent(item.stats.median) + '</td>';
            html += '<td>' + formatPercent(item.stats.stddev) + '</td>';
            html += '<td>' + (item.trend_indicators[1] !== null ? formatPercent(item.trend_indicators[1]) : 'N/A') + '</td>';
            html += '<td>' + (item.trend_indicators[5] !== null ? formatPercent(item.trend_indicators[5]) : 'N/A') + '</td>';
            html += '<td><button class="btn btn-xs btn-outline-primary transfer-btn" ' +
                'data-level="' + getLevelFromUrl() + '" ' +
                'data-reference="' + item.id + '" ' +
                'data-stat="mean" ' +
                'data-rate="' + item.stats.mean + '">' +
                'Transfer</button></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '</div></div>';

        return html;
    }

    function renderContext(context) {
        if (!context) return '';

        var html = '<div class="card mb-3">';
        html += '<div class="card-header">Context: ' + escapeHtml(context.name) + ' (' + context.level + ')</div>';
        html += '<div class="card-body">';

        if (context.within_norm) {
            html += '<div class="alert alert-success mb-2">This item is <strong>within normal range</strong> (±1 std dev) of ' + escapeHtml(context.name) + '.</div>';
        } else {
            html += '<div class="alert alert-warning mb-2">This item is <strong>outside normal range</strong> (±1 std dev) of ' + escapeHtml(context.name) + '.</div>';
        }

        html += '<table class="table table-sm">';
        html += '<tr><th>Metric</th><th>Value</th></tr>';
        html += '<tr><td>Mean</td><td>' + formatPercent(context.stats.mean) + '</td></tr>';
        html += '<tr><td>Median</td><td>' + formatPercent(context.stats.median) + '</td></tr>';
        html += '<tr><td>Std Dev</td><td>' + formatPercent(context.stats.stddev) + '</td></tr>';
        html += '</table>';

        if (context.trend_indicators) {
            html += '<strong>Trend Indicators:</strong> ';
            var parts = [];
            [1,3,5,7,10].forEach(function(p) {
                var v = context.trend_indicators[p];
                parts.push(p + 'Y: ' + (v !== null ? formatPercent(v) : 'N/A'));
            });
            html += parts.join(' | ');
        }

        html += '</div></div>';
        return html;
    }

    function renderChart(data) {
        if (typeof Chart === 'undefined') {
            // Chart.js not loaded - skip
            return;
        }

        var canvas = document.getElementById('inflation-chart');
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        var items = data.items;

        // Determine if single item or multi-item
        var firstItem = items[0];
        if (firstItem.year !== undefined) {
            // Single item: line chart of YoY rates
            var labels = items.filter(function(e) { return e.yoy_rate !== null; }).map(function(e) { return e.year; });
            var values = items.filter(function(e) { return e.yoy_rate !== null; }).map(function(e) { return e.yoy_rate * 100; });

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'YoY Inflation Rate (%)',
                        data: values,
                        borderColor: '#007bff',
                        fill: false,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { title: { display: true, text: 'Inflation Rate (%)' } }
                    }
                }
            });
        } else {
            // Multi-item: bar chart of means
            var labels = items.map(function(i) { return i.id; });
            var values = items.map(function(i) { return i.stats.mean * 100; });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Mean YoY Inflation (%)',
                        data: values,
                        backgroundColor: '#007bff'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { title: { display: true, text: 'Mean Inflation (%)' } }
                    }
                }
            });
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    function formatPercent(val) {
        if (val === null || val === undefined) return 'N/A';
        return (val * 100).toFixed(2) + '%';
    }

    function formatMoney(val) {
        if (val === null || val === undefined) return 'N/A';
        return '$' + val.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function getStatus(rate, entries) {
        // Simple: compare to mean
        var rates = entries.filter(function(e) { return e.yoy_rate !== null; }).map(function(e) { return e.yoy_rate; });
        if (rates.length < 2) return { label: 'Normal', class: 'badge-secondary' };

        var mean = rates.reduce(function(a,b) { return a + b; }, 0) / rates.length;
        var sqDiff = rates.map(function(r) { return Math.pow(r - mean, 2); });
        var variance = sqDiff.reduce(function(a,b) { return a + b; }, 0) / rates.length;
        var stddev = Math.sqrt(variance);

        if (Math.abs(rate - mean) > stddev) {
            return { label: 'Outlier', class: 'badge-danger' };
        }
        return { label: 'Normal', class: 'badge-success' };
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function getLevelFromUrl() {
        var match = window.location.search.match(/level=([^&]+)/);
        return match ? match[1] : 'all';
    }

    function getReferenceFromUrl() {
        var match = window.location.search.match(/reference_id=([^&]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }
})();
