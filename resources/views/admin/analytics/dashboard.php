<?php
/**
 * Admin Analytics Dashboard View
 * File: resources/views/admin/analytics/dashboard.php
 * Purpose: Integrated analytics dashboard with templating system
 */

// Inherit from base layout if available
$layout = 'admin';
$page_title = $page_title ?? 'Analytics Dashboard';
$section = 'analytics';

// Check if base layout exists
$base_layout = __DIR__ . '/../layouts/admin.php';
if (file_exists($base_layout)) {
    ob_start(); // Start output buffering for content
}
?>

<!-- Dashboard Content Start -->
<div class="dashboard-content">
    <!-- Header Section -->
    <div class="dashboard-header bg-gradient-primary text-white py-4 mb-4">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-chart-line mr-2"></i>
                        <?= htmlspecialchars($page_title) ?>
                    </h1>
                    <p class="mb-0 opacity-75">Real-time monitoring and behavior analysis with privacy compliance</p>
                </div>
                <div class="col-md-4 text-right">
                    <div class="d-flex align-items-center justify-content-end">
                        <div class="real-time-indicator mr-2"></div>
                        <small>Live Data</small>
                        <button class="btn btn-light btn-sm ml-3" onclick="refreshDashboard()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Privacy Compliance Banner -->
        <div class="alert alert-success border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-shield-alt fa-2x text-success mr-3"></i>
                <div>
                    <h6 class="mb-1">Privacy Compliant System</h6>
                    <p class="mb-0 small">All data collection follows GDPR guidelines with explicit user consent.
                        <span class="badge badge-success ml-2">PII PROTECTED</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="card mb-4">
            <div class="card-body bg-light">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Time Range:</label>
                            <select id="timeRange" class="form-control form-control-sm">
                                <option value="1h">Last Hour</option>
                                <option value="24h" selected>Last 24 Hours</option>
                                <option value="7d">Last 7 Days</option>
                                <option value="30d">Last 30 Days</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Department:</label>
                            <select id="department" class="form-control form-control-sm">
                                <option value="">All Departments</option>
                                <option value="admin">Administration</option>
                                <option value="sales">Sales</option>
                                <option value="inventory">Inventory</option>
                                <option value="support">Support</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">User Type:</label>
                            <select id="userType" class="form-control form-control-sm">
                                <option value="">All Users</option>
                                <option value="admin">Administrators</option>
                                <option value="manager">Managers</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">&nbsp;</label>
                            <button onclick="refreshDashboard()" class="btn btn-primary btn-sm btn-block">
                                <i class="fas fa-sync mr-1"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="stat-icon text-primary mb-3">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <div class="stat-value h2 font-weight-bold text-primary" id="activeUsers">
                            <?= $active_users ?? 0 ?>
                        </div>
                        <div class="text-muted small">Active Users</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="stat-icon text-success mb-3">
                            <i class="fas fa-video fa-2x"></i>
                        </div>
                        <div class="stat-value h2 font-weight-bold text-success" id="activeSessions">
                            <?= $active_sessions ?? 0 ?>
                        </div>
                        <div class="text-muted small">Live Sessions</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="stat-icon text-info mb-3">
                            <i class="fas fa-mouse-pointer fa-2x"></i>
                        </div>
                        <div class="stat-value h2 font-weight-bold text-info" id="totalClicks">
                            <?= $total_clicks ?? 0 ?>
                        </div>
                        <div class="text-muted small">Total Clicks</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="stat-icon text-warning mb-3">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <div class="stat-value h2 font-weight-bold text-warning" id="avgSessionTime">
                            <?= $avg_session_time ?? '0m' ?>
                        </div>
                        <div class="text-muted small">Avg Session Time</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Sessions and Heatmap Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-broadcast-tower text-success mr-2"></i>
                            Live Sessions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="liveSessions" style="max-height: 400px; overflow-y: auto;">
                            <?php if (!empty($live_sessions)): ?>
                                <?php foreach ($live_sessions as $session): ?>
                                    <div class="session-card border rounded p-3 mb-2 border-left-success">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($session['user_name']) ?> 
                                                    <span class="badge badge-secondary"><?= htmlspecialchars($session['role']) ?></span>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-globe mr-1"></i><?= htmlspecialchars($session['current_page']) ?> |
                                                    <i class="fas fa-clock ml-2 mr-1"></i><?= htmlspecialchars($session['duration']) ?>
                                                </small>
                                            </div>
                                            <div>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> Active
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                                    <p>No active sessions</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-fire text-danger mr-2"></i>
                            Click Heatmap
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="heatmap-container border rounded bg-light" id="clickHeatmap" style="min-height: 300px; position: relative;">
                            <!-- Heatmap points will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-chart-area text-primary mr-2"></i>
                            User Activity Timeline
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-chart-pie text-info mr-2"></i>
                            Page Popularity
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="pageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Privacy Controls -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-user-shield text-success mr-2"></i>
                            Privacy & Consent Management
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-right">
                                    <h4 class="text-success font-weight-bold" id="consentedUsers">
                                        <?= $privacy_stats['consented_users'] ?? 0 ?>
                                    </h4>
                                    <small class="text-muted">Consented Users</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-right">
                                    <h4 class="text-danger font-weight-bold" id="declinedUsers">
                                        <?= $privacy_stats['declined_users'] ?? 0 ?>
                                    </h4>
                                    <small class="text-muted">Declined Users</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-right">
                                    <h4 class="text-info font-weight-bold" id="dataRequests">
                                        <?= $privacy_stats['data_requests'] ?? 0 ?>
                                    </h4>
                                    <small class="text-muted">Data Requests</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button onclick="exportPrivacyReport()" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-download mr-1"></i>
                                    Privacy Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Dashboard Content End -->

<!-- Required Styles -->
<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.stat-card {
    transition: transform 0.2s ease;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.real-time-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #28a745;
    border-radius: 50%;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.heatmap-point {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(220, 53, 69, 0.8) 0%, rgba(220, 53, 69, 0.2) 100%);
    pointer-events: none;
    animation: heatmapPulse 2s infinite;
}

@keyframes heatmapPulse {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.2); opacity: 1; }
}

.session-card {
    transition: all 0.2s ease;
}

.session-card:hover {
    background-color: #f8f9fa !important;
    border-left-color: #007bff !important;
}
</style>

<!-- Dashboard JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Analytics Dashboard
    const AnalyticsDashboard = {
        refreshInterval: 30000, // 30 seconds
        charts: {},
        
        init: function() {
            console.log('Initializing Analytics Dashboard...');
            this.loadInitialData();
            this.setupCharts();
            this.startRealTimeUpdates();
            this.setupEventListeners();
        },
        
        loadInitialData: function() {
            this.loadStatistics();
            this.loadLiveSessions();
            this.loadHeatmapData();
            this.loadPrivacyStats();
        },
        
        loadStatistics: function() {
            fetch('/api/analytics/statistics')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('activeUsers').textContent = data.active_users || 0;
                    document.getElementById('activeSessions').textContent = data.active_sessions || 0;
                    document.getElementById('totalClicks').textContent = data.total_clicks || 0;
                    document.getElementById('avgSessionTime').textContent = data.avg_session_time || '0m';
                })
                .catch(err => {
                    console.error('Failed to load statistics:', err);
                    // Keep existing values on error
                });
        },
        
        loadLiveSessions: function() {
            fetch('/api/analytics/live-sessions')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('liveSessions');
                    
                    if (data.sessions && data.sessions.length > 0) {
                        let html = '';
                        data.sessions.forEach(session => {
                            html += `
                                <div class="session-card border rounded p-3 mb-2 border-left-success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">${session.user_name} 
                                                <span class="badge badge-secondary">${session.role}</span>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-globe mr-1"></i>${session.current_page} |
                                                <i class="fas fa-clock ml-2 mr-1"></i>${session.duration}
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> Active
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    }
                })
                .catch(err => console.error('Failed to load live sessions:', err));
        },
        
        loadHeatmapData: function() {
            fetch('/api/analytics/heatmap-data')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('clickHeatmap');
                    container.innerHTML = ''; // Clear existing points
                    
                    if (data.clicks && data.clicks.length > 0) {
                        data.clicks.forEach(click => {
                            const point = document.createElement('div');
                            point.className = 'heatmap-point';
                            point.style.left = (click.x / click.page_width * 100) + '%';
                            point.style.top = (click.y / click.page_height * 100) + '%';
                            point.style.opacity = Math.min(click.intensity / 10, 1);
                            container.appendChild(point);
                        });
                    }
                })
                .catch(err => console.error('Failed to load heatmap data:', err));
        },
        
        loadPrivacyStats: function() {
            fetch('/api/analytics/privacy-stats')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('consentedUsers').textContent = data.consented_users || 0;
                    document.getElementById('declinedUsers').textContent = data.declined_users || 0;
                    document.getElementById('dataRequests').textContent = data.data_requests || 0;
                })
                .catch(err => console.error('Failed to load privacy stats:', err));
        },
        
        setupCharts: function() {
            // Activity Timeline Chart
            const activityCtx = document.getElementById('activityChart');
            if (activityCtx) {
                this.charts.activity = new Chart(activityCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Active Users',
                            data: [],
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Page Popularity Chart
            const pageCtx = document.getElementById('pageChart');
            if (pageCtx) {
                this.charts.pages = new Chart(pageCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: [],
                        datasets: [{
                            data: [],
                            backgroundColor: [
                                '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
                                '#fd7e14', '#20c997', '#e83e8c'
                            ]
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
            
            this.loadChartData();
        },
        
        loadChartData: function() {
            // Load activity timeline data
            fetch('/api/analytics/activity-timeline')
                .then(response => response.json())
                .then(data => {
                    if (this.charts.activity && data.labels && data.values) {
                        this.charts.activity.data.labels = data.labels;
                        this.charts.activity.data.datasets[0].data = data.values;
                        this.charts.activity.update();
                    }
                })
                .catch(err => console.error('Failed to load activity timeline:', err));
            
            // Load page popularity data
            fetch('/api/analytics/page-popularity')
                .then(response => response.json())
                .then(data => {
                    if (this.charts.pages && data.labels && data.values) {
                        this.charts.pages.data.labels = data.labels;
                        this.charts.pages.data.datasets[0].data = data.values;
                        this.charts.pages.update();
                    }
                })
                .catch(err => console.error('Failed to load page popularity:', err));
        },
        
        startRealTimeUpdates: function() {
            // Update statistics every 30 seconds
            setInterval(() => {
                this.loadStatistics();
                this.loadLiveSessions();
                this.loadPrivacyStats();
            }, this.refreshInterval);
            
            // Update charts less frequently (every minute)
            setInterval(() => {
                this.loadChartData();
                this.loadHeatmapData();
            }, this.refreshInterval * 2);
        },
        
        setupEventListeners: function() {
            // Filter change handlers
            ['timeRange', 'department', 'userType'].forEach(filterId => {
                const element = document.getElementById(filterId);
                if (element) {
                    element.addEventListener('change', () => {
                        console.log(`Filter changed: ${filterId}`);
                        this.loadInitialData();
                        this.loadChartData();
                    });
                }
            });
        }
    };
    
    // Global functions
    window.refreshDashboard = function() {
        console.log('Manual refresh triggered');
        AnalyticsDashboard.loadInitialData();
        AnalyticsDashboard.loadChartData();
    };
    
    window.exportPrivacyReport = function() {
        const link = document.createElement('a');
        link.href = '/admin/analytics/privacy-report';
        link.download = 'privacy_report_' + new Date().toISOString().slice(0, 10) + '.pdf';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };
    
    // Initialize the dashboard
    AnalyticsDashboard.init();
});
</script>

<?php 
// If using layout system, end output buffering and include layout
if (file_exists($base_layout)) {
    $content = ob_get_clean();
    include $base_layout;
} 
?>
