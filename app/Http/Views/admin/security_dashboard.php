<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Security Dashboard') ?> - CIS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" rel="preload">
    <style>
        .security-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .security-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .threat-level-low { border-left-color: #28a745; }
        .threat-level-medium { border-left-color: #ffc107; }
        .threat-level-high { border-left-color: #fd7e14; }
        .threat-level-critical { border-left-color: #dc3545; }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .metric-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .alert-item {
            border-left: 3px solid;
            margin-bottom: 0.5rem;
            padding: 0.75rem;
        }
        
        .alert-critical { border-left-color: #dc3545; background-color: #f8d7da; }
        .alert-high { border-left-color: #fd7e14; background-color: #fff3cd; }
        .alert-medium { border-left-color: #ffc107; background-color: #fff3cd; }
        .alert-low { border-left-color: #6c757d; background-color: #f8f9fa; }
        
        .ip-badge {
            font-family: monospace;
            font-size: 0.85rem;
        }
        
        .auto-refresh {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
        }
        
        .threat-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .status-good { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-critical { background-color: #dc3545; }
        .status-unknown { background-color: #6c757d; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin">
                <i class="fas fa-shield-alt"></i> CIS Security Dashboard
            </a>
            <div class="navbar-nav ml-auto">
                <a class="nav-link" href="/admin/security/audit">
                    <i class="fas fa-clipboard-list"></i> Audit Log
                </a>
                <a class="nav-link" href="/admin/security/reports">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a class="nav-link" href="/admin/security/manage">
                    <i class="fas fa-cog"></i> Manage
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Auto-refresh indicator -->
        <div class="auto-refresh">
            <div class="card border-info">
                <div class="card-body p-2 text-center">
                    <i class="fas fa-sync-alt text-info" id="refresh-icon"></i>
                    <small class="d-block">Auto-refresh: <span id="refresh-timer">30</span>s</small>
                </div>
            </div>
        </div>

        <!-- Main Threat Level Alert -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?= $security_metrics['threat_level'] === 'low' ? 'success' : ($security_metrics['threat_level'] === 'critical' ? 'danger' : 'warning') ?> alert-dismissible">
                    <h5>
                        <i class="fas fa-<?= $security_metrics['threat_level'] === 'low' ? 'shield-alt' : 'exclamation-triangle' ?>"></i>
                        Current Threat Level: <strong><?= strtoupper($security_metrics['threat_level']) ?></strong>
                    </h5>
                    <p class="mb-0">
                        <?php if ($security_metrics['threat_level'] === 'low'): ?>
                            All systems are operating normally. No immediate threats detected.
                        <?php elseif ($security_metrics['threat_level'] === 'medium'): ?>
                            Moderate security activity detected. Enhanced monitoring is active.
                        <?php elseif ($security_metrics['threat_level'] === 'high'): ?>
                            High security activity detected. Automatic responses are active.
                        <?php else: ?>
                            <strong>CRITICAL:</strong> Active security threats detected. Immediate attention required.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Security Metrics Grid -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-number"><?= $security_metrics['total_violations'] ?></div>
                <div class="metric-label">Total Violations</div>
            </div>
            <div class="metric-card">
                <div class="metric-number"><?= $security_metrics['blocked_ips'] ?></div>
                <div class="metric-label">Blocked IPs</div>
            </div>
            <div class="metric-card">
                <div class="metric-number"><?= $security_metrics['alerts_last_24h'] ?></div>
                <div class="metric-label">Alerts (24h)</div>
            </div>
            <div class="metric-card">
                <div class="metric-number">
                    <span class="threat-indicator status-<?= $system_status['ids_engine']['status'] === 'active' ? 'good' : 'critical' ?>"></span>
                    <?= strtoupper($system_status['ids_engine']['status']) ?>
                </div>
                <div class="metric-label">IDS Engine</div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Security Alerts -->
            <div class="col-lg-8">
                <div class="card security-card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle"></i> Recent Security Alerts</h5>
                        <small class="text-muted">Last 50 security events</small>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($recent_alerts)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-shield-alt fa-3x mb-3"></i>
                                <p>No recent security alerts</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_alerts as $alert): ?>
                                <div class="alert-item alert-<?= $alert['severity'] ?? 'low' ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= htmlspecialchars($alert['description'] ?? 'Security Event') ?></strong>
                                            <br>
                                            <span class="ip-badge badge badge-dark">
                                                <?= htmlspecialchars($alert['ip'] ?? 'Unknown IP') ?>
                                            </span>
                                            <?php if (!empty($alert['country'])): ?>
                                                <span class="badge badge-secondary"><?= htmlspecialchars($alert['country']) ?></span>
                                            <?php endif; ?>
                                            <small class="text-muted d-block">
                                                Rule: <?= htmlspecialchars($alert['rule'] ?? 'unknown') ?>
                                                | Field: <?= htmlspecialchars($alert['field'] ?? 'unknown') ?>
                                            </small>
                                        </div>
                                        <div class="text-right">
                                            <small class="text-muted">
                                                <?= date('H:i:s', strtotime($alert['timestamp'] ?? '')) ?>
                                            </small>
                                            <br>
                                            <span class="badge badge-<?= $alert['severity'] === 'critical' ? 'danger' : ($alert['severity'] === 'high' ? 'warning' : 'info') ?>">
                                                Risk: <?= $alert['risk_score'] ?? 0 ?>/100
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- System Status & Controls -->
            <div class="col-lg-4">
                <!-- System Status -->
                <div class="card security-card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-server"></i> System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>IDS Engine</span>
                                <span class="badge badge-<?= $system_status['ids_engine']['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($system_status['ids_engine']['status']) ?>
                                </span>
                            </div>
                            <small class="text-muted">Rules: <?= $system_status['ids_engine']['rules_loaded'] ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Firewall</span>
                                <span class="badge badge-<?= $system_status['firewall']['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($system_status['firewall']['status']) ?>
                                </span>
                            </div>
                            <small class="text-muted">Blocked IPs: <?= $system_status['firewall']['blocked_ips'] ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>SSL Certificate</span>
                                <span class="badge badge-<?= $system_status['ssl_certificate']['status'] === 'active' ? 'success' : 'warning' ?>">
                                    <?= strtoupper($system_status['ssl_certificate']['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>File Integrity</span>
                                <span class="badge badge-<?= $system_status['file_integrity']['status'] === 'good' ? 'success' : 'warning' ?>">
                                    <?= strtoupper($system_status['file_integrity']['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Backup Status</span>
                                <span class="badge badge-<?= $system_status['backup_status']['status'] === 'good' ? 'success' : ($system_status['backup_status']['status'] === 'warning' ? 'warning' : 'danger') ?>">
                                    <?= strtoupper($system_status['backup_status']['status']) ?>
                                </span>
                            </div>
                            <?php if (!empty($system_status['backup_status']['last_backup'])): ?>
                                <small class="text-muted">
                                    Last: <?= date('M j, H:i', strtotime($system_status['backup_status']['last_backup'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Currently Blocked IPs -->
                <div class="card security-card">
                    <div class="card-header">
                        <h5><i class="fas fa-ban"></i> Blocked IPs</h5>
                        <a href="/admin/security/manage" class="btn btn-sm btn-outline-primary float-right">
                            Manage
                        </a>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($blocked_ips)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-shield-alt fa-2x mb-2"></i>
                                <p class="mb-0">No IPs currently blocked</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($blocked_ips as $blocked): ?>
                                <div class="d-flex justify-content-between align-items-start mb-2 p-2 bg-light rounded">
                                    <div>
                                        <span class="ip-badge font-weight-bold">
                                            <?= htmlspecialchars($blocked['ip']) ?>
                                        </span>
                                        <?php if (!empty($blocked['country'])): ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($blocked['country']) ?></small>
                                        <?php endif; ?>
                                        <small class="text-muted d-block">
                                            <?= htmlspecialchars($blocked['reason']) ?>
                                        </small>
                                    </div>
                                    <div class="text-right">
                                        <small class="text-muted">
                                            <?= $blocked['remaining_hours'] ?>h left
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attack Statistics Charts -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card security-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Attack Types (24h)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="attackTypesChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card security-card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Hourly Activity (24h)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlyActivityChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <script>
        // Auto-refresh functionality
        let refreshTimer = 30;
        let refreshInterval;
        
        function startRefreshTimer() {
            refreshInterval = setInterval(() => {
                refreshTimer--;
                $('#refresh-timer').text(refreshTimer);
                
                if (refreshTimer <= 0) {
                    refreshPage();
                }
                
                // Spin icon while refreshing
                if (refreshTimer <= 3) {
                    $('#refresh-icon').addClass('fa-spin');
                } else {
                    $('#refresh-icon').removeClass('fa-spin');
                }
            }, 1000);
        }
        
        function refreshPage() {
            location.reload();
        }
        
        // Initialize charts
        const attackTypesData = <?= json_encode($security_metrics['violations_by_type'] ?? []) ?>;
        const hourlyData = <?= json_encode($security_metrics['hourly_stats'] ?? []) ?>;
        
        // Attack Types Chart
        const attackTypesCtx = document.getElementById('attackTypesChart').getContext('2d');
        new Chart(attackTypesCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(attackTypesData),
                datasets: [{
                    data: Object.values(attackTypesData),
                    backgroundColor: [
                        '#dc3545',
                        '#fd7e14', 
                        '#ffc107',
                        '#28a745',
                        '#17a2b8',
                        '#6f42c1'
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
        
        // Hourly Activity Chart  
        const hourlyCtx = document.getElementById('hourlyActivityChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: Object.keys(hourlyData).map(hour => hour + ':00'),
                datasets: [{
                    label: 'Security Events',
                    data: Object.values(hourlyData),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.4
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
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Start auto-refresh
        $(document).ready(function() {
            startRefreshTimer();
            
            // Click refresh icon to refresh immediately
            $('#refresh-icon').click(function() {
                refreshTimer = 0;
            });
        });
    </script>
</body>
</html>
