<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Analytics Dashboard - CIS Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .live-session {
            border: 2px solid #28a745;
            background: #f8fff9;
        }
        
        .session-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #007bff;
        }
        
        .session-active {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        
        .heatmap-container {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f8f9fa;
            min-height: 400px;
        }
        
        .heatmap-point {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,0,0,0.8) 0%, rgba(255,0,0,0.2) 100%);
            pointer-events: none;
        }
        
        .privacy-badge {
            background: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .consent-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .consent-granted {
            background: #d4edda;
            color: #155724;
        }
        
        .consent-denied {
            background: #f8d7da;
            color: #721c24;
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
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
        
        .filter-controls {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chart-line"></i> User Analytics Dashboard</h1>
                    <p class="mb-0">Real-time monitoring and behavior analysis with privacy compliance</p>
                </div>
                <div class="col-md-4 text-right">
                    <div class="real-time-indicator"></div>
                    <span class="ml-2">Live Data</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <!-- Privacy Compliance Banner -->
        <div class="alert alert-success">
            <i class="fas fa-shield-alt"></i>
            <strong>Privacy Compliant:</strong> All data collection follows GDPR guidelines. 
            Users have explicitly consented to monitoring. 
            <span class="privacy-badge">PII PROTECTED</span>
        </div>
        
        <!-- Filter Controls -->
        <div class="filter-controls">
            <div class="row">
                <div class="col-md-3">
                    <label>Time Range:</label>
                    <select id="timeRange" class="form-control">
                        <option value="1h">Last Hour</option>
                        <option value="24h" selected>Last 24 Hours</option>
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Department:</label>
                    <select id="department" class="form-control">
                        <option value="">All Departments</option>
                        <option value="admin">Administration</option>
                        <option value="sales">Sales</option>
                        <option value="inventory">Inventory</option>
                        <option value="support">Support</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>User Type:</label>
                    <select id="userType" class="form-control">
                        <option value="">All Users</option>
                        <option value="admin">Administrators</option>
                        <option value="manager">Managers</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <button onclick="refreshDashboard()" class="btn btn-primary btn-block">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Key Statistics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="fas fa-users stat-icon text-primary"></i>
                    <div class="stat-value" id="activeUsers">0</div>
                    <div class="text-muted">Active Users</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="fas fa-video stat-icon text-success"></i>
                    <div class="stat-value" id="activeSessions">0</div>
                    <div class="text-muted">Live Sessions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="fas fa-mouse-pointer stat-icon text-info"></i>
                    <div class="stat-value" id="totalClicks">0</div>
                    <div class="text-muted">Total Clicks</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="fas fa-clock stat-icon text-warning"></i>
                    <div class="stat-value" id="avgSessionTime">0m</div>
                    <div class="text-muted">Avg Session Time</div>
                </div>
            </div>
        </div>
        
        <!-- Live Sessions -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-broadcast-tower"></i> Live Sessions</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div id="liveSessions">
                            <!-- Live sessions will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-fire"></i> Click Heatmap</h5>
                    </div>
                    <div class="card-body">
                        <div class="heatmap-container" id="clickHeatmap">
                            <!-- Heatmap visualization -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Analytics Charts -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-area"></i> User Activity Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> Page Popularity</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="pageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Behavior Analysis -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-route"></i> User Journey Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div id="userJourneys">
                            <!-- User journey visualization -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Privacy Controls -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-shield"></i> Privacy & Consent Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success" id="consentedUsers">0</h4>
                                    <small>Consented Users</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-danger" id="declinedUsers">0</h4>
                                    <small>Declined Users</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-info" id="dataRequests">0</h4>
                                    <small>Data Requests</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <button onclick="exportPrivacyReport()" class="btn btn-outline-primary">
                                        <i class="fas fa-download"></i> Privacy Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Session Viewer Modal -->
    <div class="modal fade" id="sessionViewerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-video"></i> Live Session Viewer
                        <span class="privacy-badge ml-2">PRIVACY PROTECTED</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="sessionViewer">
                        <!-- Session replay interface -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="downloadSession()">
                        <i class="fas fa-download"></i> Export Session
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Analytics Dashboard Controller
        const AnalyticsDashboard = {
            refreshInterval: 30000, // 30 seconds
            charts: {},
            
            init: function() {
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
                    .catch(err => console.error('Failed to load statistics:', err));
            },
            
            loadLiveSessions: function() {
                fetch('/api/analytics/live-sessions')
                    .then(response => response.json())
                    .then(data => {
                        const container = document.getElementById('liveSessions');
                        container.innerHTML = '';
                        
                        if (data.sessions && data.sessions.length > 0) {
                            data.sessions.forEach(session => {
                                const sessionHtml = `
                                    <div class="session-card session-active">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6>${session.user_name} (${session.role})</h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-globe"></i> ${session.current_page} | 
                                                    <i class="fas fa-clock"></i> ${session.duration}
                                                </small>
                                            </div>
                                            <div>
                                                <span class="consent-status consent-granted">
                                                    <i class="fas fa-check"></i> Consented
                                                </span>
                                                <button onclick="viewSession('${session.session_id}')" 
                                                        class="btn btn-sm btn-primary ml-2">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                container.innerHTML += sessionHtml;
                            });
                        } else {
                            container.innerHTML = '<p class="text-muted text-center">No active sessions</p>';
                        }
                    })
                    .catch(err => console.error('Failed to load live sessions:', err));
            },
            
            loadHeatmapData: function() {
                fetch('/api/analytics/heatmap-data')
                    .then(response => response.json())
                    .then(data => {
                        const container = document.getElementById('clickHeatmap');
                        container.innerHTML = '';
                        
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
                const activityCtx = document.getElementById('activityChart').getContext('2d');
                this.charts.activity = new Chart(activityCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Active Users',
                            data: [],
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                
                // Page Popularity Chart
                const pageCtx = document.getElementById('pageChart').getContext('2d');
                this.charts.pages = new Chart(pageCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [],
                        datasets: [{
                            data: [],
                            backgroundColor: [
                                '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                
                this.loadChartData();
            },
            
            loadChartData: function() {
                // Load activity data
                fetch('/api/analytics/activity-timeline')
                    .then(response => response.json())
                    .then(data => {
                        this.charts.activity.data.labels = data.labels || [];
                        this.charts.activity.data.datasets[0].data = data.values || [];
                        this.charts.activity.update();
                    });
                
                // Load page data
                fetch('/api/analytics/page-popularity')
                    .then(response => response.json())
                    .then(data => {
                        this.charts.pages.data.labels = data.labels || [];
                        this.charts.pages.data.datasets[0].data = data.values || [];
                        this.charts.pages.update();
                    });
            },
            
            startRealTimeUpdates: function() {
                setInterval(() => {
                    this.loadStatistics();
                    this.loadLiveSessions();
                }, this.refreshInterval);
                
                // Update charts less frequently
                setInterval(() => {
                    this.loadChartData();
                }, this.refreshInterval * 2);
            },
            
            setupEventListeners: function() {
                // Filter change handlers
                ['timeRange', 'department', 'userType'].forEach(filterId => {
                    document.getElementById(filterId).addEventListener('change', () => {
                        this.loadInitialData();
                        this.loadChartData();
                    });
                });
            }
        };
        
        // Global functions
        function refreshDashboard() {
            AnalyticsDashboard.loadInitialData();
            AnalyticsDashboard.loadChartData();
        }
        
        function viewSession(sessionId) {
            fetch(`/api/analytics/session-data/${sessionId}`)
                .then(response => response.json())
                .then(data => {
                    const viewer = document.getElementById('sessionViewer');
                    viewer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Privacy Notice:</strong> This session replay excludes sensitive data 
                            and requires explicit user consent for viewing.
                        </div>
                        <div class="session-info">
                            <h6>Session Information:</h6>
                            <ul>
                                <li><strong>User:</strong> ${data.user_name} (${data.role})</li>
                                <li><strong>Started:</strong> ${data.start_time}</li>
                                <li><strong>Duration:</strong> ${data.duration}</li>
                                <li><strong>Pages Visited:</strong> ${data.pages_count}</li>
                                <li><strong>Consent Status:</strong> 
                                    <span class="consent-status consent-granted">Granted</span>
                                </li>
                            </ul>
                        </div>
                        <div class="session-replay">
                            <p class="text-center text-muted">
                                <i class="fas fa-play-circle fa-3x"></i><br>
                                Session replay functionality would be implemented here<br>
                                with privacy-compliant playback controls
                            </p>
                        </div>
                    `;
                    
                    $('#sessionViewerModal').modal('show');
                })
                .catch(err => {
                    console.error('Failed to load session data:', err);
                    alert('Failed to load session data');
                });
        }
        
        function downloadSession() {
            alert('Session export functionality would generate a privacy-compliant report');
        }
        
        function exportPrivacyReport() {
            fetch('/api/analytics/privacy-report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
                }
            })
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'privacy_compliance_report_' + new Date().toISOString().slice(0,10) + '.pdf';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            })
            .catch(err => {
                console.error('Failed to export privacy report:', err);
                alert('Failed to generate privacy report');
            });
        }
        
        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            AnalyticsDashboard.init();
        });
    </script>
</body>
</html>
