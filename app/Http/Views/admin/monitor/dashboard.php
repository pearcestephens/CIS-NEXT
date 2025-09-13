<?php
/**
 * System Monitor Dashboard View
 * File: app/Http/Views/admin/monitor/dashboard.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Real-time system monitoring dashboard with live metrics
 */

$title = $data['title'] ?? 'System Monitor';
$metrics = $data['metrics'] ?? [];
$alerts = $data['alerts'] ?? [];
$performance = $data['performance'] ?? [];
$cache_status = $data['cache_status'] ?? [];
$error_stats = $data['error_stats'] ?? [];
$security_status = $data['security_status'] ?? [];

// Determine overall system health
$healthScore = 100;
if (!empty($alerts)) {
    $criticalAlerts = array_filter($alerts, fn($alert) => $alert['level'] === 'critical');
    $warningAlerts = array_filter($alerts, fn($alert) => $alert['level'] === 'warning');
    
    $healthScore -= count($criticalAlerts) * 20;
    $healthScore -= count($warningAlerts) * 10;
    $healthScore = max(0, $healthScore);
}

$healthClass = $healthScore >= 80 ? 'success' : ($healthScore >= 60 ? 'warning' : 'danger');
$healthStatus = $healthScore >= 80 ? 'Healthy' : ($healthScore >= 60 ? 'Warning' : 'Critical');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .metric-card {
            transition: transform 0.2s ease-in-out;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
        }
        
        .health-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .health-indicator.healthy { background-color: #28a745; }
        .health-indicator.warning { background-color: #ffc107; }
        .health-indicator.critical { background-color: #dc3545; }
        
        .progress-animated {
            animation: progress-animation 2s ease-in-out;
        }
        
        @keyframes progress-animation {
            0% { width: 0%; }
            100% { width: var(--progress-width); }
        }
        
        .alert-pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
        }
        
        .auto-refresh {
            color: #28a745;
            animation: spin 2s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" href="/admin">
        <i class="fas fa-chart-line mr-2"></i>CIS System Monitor
    </a>
    <div class="ml-auto">
        <span class="navbar-text">
            <span class="health-indicator <?= $healthScore >= 80 ? 'healthy' : ($healthScore >= 60 ? 'warning' : 'critical') ?>"></span>
            System Health: <?= $healthStatus ?> (<?= $healthScore ?>%)
        </span>
        <button class="btn btn-sm btn-outline-light ml-3" onclick="toggleAutoRefresh()">
            <i id="refreshIcon" class="fas fa-sync-alt"></i> Auto Refresh
        </button>
    </div>
</nav>

<div class="container-fluid py-4">
    
    <!-- Alerts Section -->
    <?php if (!empty($alerts)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning alert-pulse">
                <h6><i class="fas fa-exclamation-triangle mr-2"></i>Active Alerts (<?= count($alerts) ?>)</h6>
                <?php foreach ($alerts as $alert): ?>
                    <div class="alert alert-<?= $alert['level'] === 'critical' ? 'danger' : 'warning' ?> mb-2">
                        <strong><?= htmlspecialchars($alert['message']) ?></strong><br>
                        <small><?= htmlspecialchars($alert['details']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- System Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card metric-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-server fa-3x text-primary mb-3"></i>
                    <h5>System Load</h5>
                    <h3 class="text-<?= ($metrics['load_average']['1min'] ?? 0) > 2 ? 'danger' : 'success' ?>">
                        <?= number_format($metrics['load_average']['1min'] ?? 0, 2) ?>
                    </h3>
                    <small class="text-muted">1 min avg</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card metric-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-memory fa-3x text-info mb-3"></i>
                    <h5>Memory Usage</h5>
                    <h3 class="text-<?= ($metrics['memory']['usage_percent'] ?? 0) > 85 ? 'danger' : 'success' ?>">
                        <?= number_format($metrics['memory']['usage_percent'] ?? 0, 1) ?>%
                    </h3>
                    <small class="text-muted"><?= number_format($metrics['memory']['used_mb'] ?? 0, 1) ?>MB used</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card metric-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-database fa-3x text-success mb-3"></i>
                    <h5>Cache Hit Ratio</h5>
                    <h3 class="text-<?= ($cache_status['statistics']['hit_ratio'] ?? 0) > 85 ? 'success' : 'warning' ?>">
                        <?= number_format($cache_status['statistics']['hit_ratio'] ?? 0, 1) ?>%
                    </h3>
                    <small class="text-muted">Redis cache</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card metric-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                    <h5>Error Rate</h5>
                    <h3 class="text-<?= ($error_stats['current_rate'] ?? 0) > 10 ? 'danger' : 'success' ?>">
                        <?= $error_stats['current_rate'] ?? 0 ?>
                    </h3>
                    <small class="text-muted">per minute</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-line mr-2"></i>Error Rate Trend</h6>
                </div>
                <div class="card-body">
                    <canvas id="errorTrendChart" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-tachometer-alt mr-2"></i>System Resources</h6>
                </div>
                <div class="card-body">
                    <!-- Memory Progress -->
                    <div class="mb-3">
                        <label class="small">Memory Usage</label>
                        <div class="progress">
                            <div class="progress-bar bg-<?= ($metrics['memory']['usage_percent'] ?? 0) > 85 ? 'danger' : 'info' ?> progress-animated" 
                                 style="--progress-width: <?= $metrics['memory']['usage_percent'] ?? 0 ?>%; width: <?= $metrics['memory']['usage_percent'] ?? 0 ?>%">
                                <?= number_format($metrics['memory']['usage_percent'] ?? 0, 1) ?>%
                            </div>
                        </div>
                    </div>
                    
                    <!-- Disk Usage Progress -->
                    <div class="mb-3">
                        <label class="small">Disk Usage</label>
                        <div class="progress">
                            <div class="progress-bar bg-<?= ($metrics['disk']['usage_percent'] ?? 0) > 90 ? 'danger' : 'success' ?> progress-animated" 
                                 style="--progress-width: <?= $metrics['disk']['usage_percent'] ?? 0 ?>%; width: <?= $metrics['disk']['usage_percent'] ?? 0 ?>%">
                                <?= number_format($metrics['disk']['usage_percent'] ?? 0, 1) ?>%
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cache Health -->
                    <div class="mb-3">
                        <label class="small">Cache Health Score</label>
                        <div class="progress">
                            <div class="progress-bar bg-<?= ($cache_status['health_score'] ?? 0) > 80 ? 'success' : 'warning' ?> progress-animated" 
                                 style="--progress-width: <?= $cache_status['health_score'] ?? 0 ?>%; width: <?= $cache_status['health_score'] ?? 0 ?>%">
                                <?= $cache_status['health_score'] ?? 0 ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Metrics Tables -->
    <div class="row mb-4">
        <!-- Performance Metrics -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-rocket mr-2"></i>Performance Metrics</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td>Average Response Time</td>
                            <td class="text-right">
                                <span class="badge badge-<?= ($performance['response_times']['average_ms'] ?? 0) > 1000 ? 'danger' : 'success' ?>">
                                    <?= number_format($performance['response_times']['average_ms'] ?? 0, 2) ?>ms
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Requests per Minute</td>
                            <td class="text-right"><?= number_format($performance['throughput']['requests_per_minute'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td>Database QPS</td>
                            <td class="text-right"><?= number_format($performance['database']['queries_per_second'] ?? 0, 2) ?></td>
                        </tr>
                        <tr>
                            <td>Active DB Connections</td>
                            <td class="text-right"><?= $performance['database']['active_connections'] ?? 0 ?></td>
                        </tr>
                        <tr>
                            <td>Cache Operations/sec</td>
                            <td class="text-right"><?= number_format($performance['cache']['operations_per_sec'] ?? 0) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Security Status -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-shield-alt mr-2"></i>Security Status</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td>Active Sessions</td>
                            <td class="text-right"><?= $security_status['active_sessions'] ?? 0 ?></td>
                        </tr>
                        <tr>
                            <td>Failed Logins (1h)</td>
                            <td class="text-right">
                                <span class="badge badge-<?= ($security_status['failed_logins'] ?? 0) > 10 ? 'warning' : 'success' ?>">
                                    <?= $security_status['failed_logins'] ?? 0 ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Rate Limit Remaining</td>
                            <td class="text-right">
                                <?= $security_status['rate_limiting']['remaining_requests'] ?? 0 ?>/<?= $security_status['rate_limiting']['limit'] ?? 0 ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Security Headers</td>
                            <td class="text-right">
                                <span class="badge badge-success"><i class="fas fa-check"></i> Active</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Errors -->
    <?php if (!empty($error_stats['recent_errors'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-bug mr-2"></i>Recent Errors</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Level</th>
                                    <th>Message</th>
                                    <th>File</th>
                                    <th>Line</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($error_stats['recent_errors'], 0, 10) as $error): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <small><?= date('H:i:s', strtotime($error->timestamp)) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $error->level === 'CRITICAL' ? 'danger' : ($error->level === 'WARNING' ? 'warning' : 'info') ?>">
                                            <?= htmlspecialchars($error->level) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(substr($error->message, 0, 80)) ?><?= strlen($error->message) > 80 ? '...' : '' ?></td>
                                    <td class="text-nowrap"><small><?= htmlspecialchars($error->file) ?></small></td>
                                    <td><?= $error->line ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- System Information -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-info-circle mr-2"></i>System Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td>Server Time</td>
                            <td class="text-right"><?= date('Y-m-d H:i:s T') ?></td>
                        </tr>
                        <tr>
                            <td>System Uptime</td>
                            <td class="text-right"><?= htmlspecialchars($metrics['uptime'] ?? 'Unknown') ?></td>
                        </tr>
                        <tr>
                            <td>PHP Version</td>
                            <td class="text-right"><?= htmlspecialchars($metrics['php']['version'] ?? PHP_VERSION) ?></td>
                        </tr>
                        <tr>
                            <td>Server API</td>
                            <td class="text-right"><?= htmlspecialchars($metrics['php']['sapi'] ?? PHP_SAPI) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-puzzle-piece mr-2"></i>PHP Extensions</h6>
                </div>
                <div class="card-body">
                    <?php foreach (($metrics['php']['extensions'] ?? []) as $ext => $loaded): ?>
                        <span class="badge badge-<?= $loaded ? 'success' : 'danger' ?> mr-2 mb-2">
                            <?= htmlspecialchars($ext) ?> <?= $loaded ? '✓' : '✗' ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let autoRefreshInterval = null;
let errorTrendChart = null;

$(document).ready(function() {
    initializeCharts();
    startAutoRefresh();
});

function initializeCharts() {
    // Error trend chart
    const errorTrendData = <?= json_encode($error_stats['error_trend'] ?? []) ?>;
    
    const ctx = document.getElementById('errorTrendChart').getContext('2d');
    errorTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: errorTrendData.map(d => d.time),
            datasets: [{
                label: 'Errors per Minute',
                data: errorTrendData.map(d => d.count),
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
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
                    display: false
                }
            }
        }
    });
}

function toggleAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        $('#refreshIcon').removeClass('auto-refresh');
    } else {
        startAutoRefresh();
    }
}

function startAutoRefresh() {
    $('#refreshIcon').addClass('auto-refresh');
    
    autoRefreshInterval = setInterval(function() {
        refreshMetrics();
    }, 30000); // Refresh every 30 seconds
}

function refreshMetrics() {
    $.ajax({
        url: '/admin/monitor/api',
        method: 'GET',
        success: function(data) {
            updateDashboard(data);
        },
        error: function() {
            console.error('Failed to refresh metrics');
        }
    });
}

function updateDashboard(data) {
    // Update system load
    const loadAvg = data.system?.load_average?.['1min'] || 0;
    $('[data-metric="load_avg"]').text(loadAvg.toFixed(2));
    
    // Update memory usage
    const memoryPercent = data.system?.memory?.usage_percent || 0;
    $('[data-metric="memory_percent"]').text(memoryPercent.toFixed(1) + '%');
    
    // Update cache hit ratio
    const hitRatio = data.cache?.statistics?.hit_ratio || 0;
    $('[data-metric="cache_hit_ratio"]').text(hitRatio.toFixed(1) + '%');
    
    // Update error rate
    const errorRate = data.errors?.current_rate || 0;
    $('[data-metric="error_rate"]').text(errorRate);
    
    // Update error trend chart
    if (errorTrendChart && data.errors?.error_trend) {
        errorTrendChart.data.labels = data.errors.error_trend.map(d => d.time);
        errorTrendChart.data.datasets[0].data = data.errors.error_trend.map(d => d.count);
        errorTrendChart.update('none');
    }
    
    // Update timestamp
    $('.last-updated').text('Last updated: ' + new Date().toLocaleTimeString());
}

// Add timestamp display
$(function() {
    if (!$('.last-updated').length) {
        $('.container-fluid').append('<div class="text-center mt-3 last-updated text-muted">Last updated: ' + new Date().toLocaleTimeString() + '</div>');
    }
});
</script>

</body>
</html>
