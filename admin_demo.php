<?php
/**
 * Admin Demo - Show CIS Admin Interface
 * Quick demo to showcase admin features
 */

// Simple HTML output to showcase admin features
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS 2.0 Admin Dashboard Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .feature-card { transition: transform 0.2s; }
        .feature-card:hover { transform: translateY(-2px); }
        .metric-card { background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); }
        .admin-nav { background: #2c3e50; }
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .live-metric { font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body class="bg-light">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark admin-nav">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-tachometer-alt me-2"></i>
            CIS 2.0 Admin Dashboard
        </a>
        <div class="navbar-nav ms-auto">
            <span class="nav-text text-light me-3">
                <i class="fas fa-user me-1"></i> Admin User
            </span>
            <span class="nav-text text-light">
                <i class="fas fa-clock me-1"></i> <?= date('Y-m-d H:i:s') ?>
            </span>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <!-- System Status Bar -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>System Status: All systems operational</strong>
                <div class="ms-auto">
                    <span class="badge bg-success status-good">
                        <i class="fas fa-heartbeat me-1"></i> Healthy
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-Time Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card metric-card text-white">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h3 class="live-metric"><?= rand(15, 25) ?></h3>
                    <p class="mb-0">Active Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card text-white">
                <div class="card-body text-center">
                    <i class="fas fa-server fa-2x mb-2"></i>
                    <h3 class="live-metric"><?= rand(65, 85) ?>%</h3>
                    <p class="mb-0">System Health</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card text-white">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h3 class="live-metric"><?= rand(120, 250) ?>ms</h3>
                    <p class="mb-0">Response Time</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card text-white">
                <div class="card-body text-center">
                    <i class="fas fa-shield-alt fa-2x mb-2"></i>
                    <h3 class="live-metric"><?= rand(85, 95) ?>%</h3>
                    <p class="mb-0">Security Score</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Feature Modules -->
    <div class="row">
        <div class="col-md-8">
            <h2 class="mb-3">
                <i class="fas fa-cogs me-2"></i>
                Available Admin Modules
            </h2>
            
            <!-- System Management -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-server me-2"></i>
                        System Management
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $systemModules = [
                            ['icon' => 'fas fa-database', 'name' => 'Database Tools', 'desc' => 'Schema management & queries', 'status' => 'active'],
                            ['icon' => 'fas fa-memory', 'name' => 'Cache Manager', 'desc' => 'Redis monitoring & clearing', 'status' => 'active'],
                            ['icon' => 'fas fa-tasks', 'name' => 'Queue Manager', 'desc' => 'Background job monitoring', 'status' => 'active'],
                            ['icon' => 'fas fa-clock', 'name' => 'Cron Jobs', 'desc' => 'Scheduled task management', 'status' => 'pending'],
                            ['icon' => 'fas fa-shield-halved', 'name' => 'Backups', 'desc' => 'Automated backup system', 'status' => 'active'],
                            ['icon' => 'fas fa-plug', 'name' => 'Integrations', 'desc' => 'Third-party API management', 'status' => 'active']
                        ];
                        
                        foreach ($systemModules as $module): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card feature-card h-100">
                                    <div class="card-body text-center">
                                        <i class="<?= $module['icon'] ?> fa-2x text-primary mb-2"></i>
                                        <h6 class="card-title"><?= $module['name'] ?></h6>
                                        <p class="card-text small text-muted"><?= $module['desc'] ?></p>
                                        <span class="badge bg-<?= $module['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($module['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Monitoring & Analytics -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Monitoring & Analytics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $monitoringModules = [
                            ['icon' => 'fas fa-gauge-high', 'name' => 'Overview Dashboard', 'desc' => 'Real-time metrics & charts', 'status' => 'active'],
                            ['icon' => 'fas fa-chart-bar', 'name' => 'Analytics', 'desc' => 'User behavior & system analytics', 'status' => 'active'],
                            ['icon' => 'fas fa-shield', 'name' => 'Security Dashboard', 'desc' => 'Threat monitoring & alerts', 'status' => 'active'],
                            ['icon' => 'fas fa-heartbeat', 'name' => 'System Monitor', 'desc' => 'Server health & performance', 'status' => 'active'],
                            ['icon' => 'fas fa-file-lines', 'name' => 'Logs', 'desc' => 'Centralized log viewing', 'status' => 'active']
                        ];
                        
                        foreach ($monitoringModules as $module): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card feature-card h-100">
                                    <div class="card-body text-center">
                                        <i class="<?= $module['icon'] ?> fa-2x text-info mb-2"></i>
                                        <h6 class="card-title"><?= $module['name'] ?></h6>
                                        <p class="card-text small text-muted"><?= $module['desc'] ?></p>
                                        <span class="badge bg-<?= $module['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($module['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- User Management -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        User Management
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $userModules = [
                            ['icon' => 'fas fa-users', 'name' => 'Users', 'desc' => 'User accounts & profiles', 'status' => 'active'],
                            ['icon' => 'fas fa-user-shield', 'name' => 'Roles & Permissions', 'desc' => 'RBAC system', 'status' => 'active'],
                            ['icon' => 'fas fa-gear', 'name' => 'Settings', 'desc' => 'System configuration', 'status' => 'active'],
                            ['icon' => 'fas fa-tools', 'name' => 'Tools', 'desc' => 'Development & maintenance', 'status' => 'active']
                        ];
                        
                        foreach ($userModules as $module): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card feature-card h-100">
                                    <div class="card-body text-center">
                                        <i class="<?= $module['icon'] ?> fa-2x text-success mb-2"></i>
                                        <h6 class="card-title"><?= $module['name'] ?></h6>
                                        <p class="card-text small text-muted"><?= $module['desc'] ?></p>
                                        <span class="badge bg-<?= $module['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($module['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar - Quick Stats -->
        <div class="col-md-4">
            <h2 class="mb-3">
                <i class="fas fa-info-circle me-2"></i>
                System Information
            </h2>

            <!-- System Stats -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">System Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>PHP Version:</span>
                        <strong><?= PHP_VERSION ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Memory Usage:</span>
                        <strong><?= number_format(memory_get_usage(true) / 1024 / 1024, 1) ?>MB</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Server Time:</span>
                        <strong><?= date('H:i:s') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Database:</span>
                        <strong class="status-good">Connected</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Cache:</span>
                        <strong class="status-good">Redis Active</strong>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <?php
                    $activities = [
                        ['icon' => 'fas fa-user', 'text' => 'Admin user logged in', 'time' => '2 minutes ago'],
                        ['icon' => 'fas fa-cog', 'text' => 'System settings updated', 'time' => '15 minutes ago'],
                        ['icon' => 'fas fa-shield', 'text' => 'Security scan completed', 'time' => '1 hour ago'],
                        ['icon' => 'fas fa-database', 'text' => 'Database backup created', 'time' => '2 hours ago'],
                        ['icon' => 'fas fa-chart-line', 'text' => 'Analytics report generated', 'time' => '3 hours ago']
                    ];
                    
                    foreach ($activities as $activity): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <i class="<?= $activity['icon'] ?> text-muted"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="small"><?= $activity['text'] ?></div>
                                <div class="text-muted small"><?= $activity['time'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- API Status -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">API Endpoints</h6>
                </div>
                <div class="card-body">
                    <?php
                    $endpoints = [
                        ['name' => '/api/admin/metrics', 'status' => 'online'],
                        ['name' => '/api/admin/users', 'status' => 'online'],
                        ['name' => '/api/admin/system', 'status' => 'online'],
                        ['name' => '/api/health', 'status' => 'online'],
                        ['name' => '/api/auth/login', 'status' => 'online']
                    ];
                    
                    foreach ($endpoints as $endpoint): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <code class="small"><?= $endpoint['name'] ?></code>
                            <span class="badge bg-<?= $endpoint['status'] === 'online' ? 'success' : 'danger' ?>">
                                <?= ucfirst($endpoint['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="mt-5 py-4 bg-dark text-light text-center">
    <div class="container">
        <p class="mb-0">
            <strong>CIS 2.0 Enterprise Admin Platform</strong> - 
            Built with <i class="fas fa-heart text-danger"></i> using Bootstrap 5, PHP 8, and Modern Architecture
        </p>
        <p class="small text-muted mb-0">
            Features: Real-time monitoring, Security layers, API management, User controls, System automation
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Simple live metrics simulation
    setInterval(() => {
        document.querySelectorAll('.live-metric').forEach(metric => {
            const current = parseInt(metric.textContent);
            const variation = Math.floor(Math.random() * 10) - 5;
            let newValue = current + variation;
            
            // Keep values in reasonable ranges
            if (metric.textContent.includes('%')) {
                newValue = Math.max(60, Math.min(95, newValue));
                metric.textContent = newValue + '%';
            } else if (metric.textContent.includes('ms')) {
                newValue = Math.max(100, Math.min(300, newValue));
                metric.textContent = newValue + 'ms';
            } else {
                newValue = Math.max(10, Math.min(30, newValue));
                metric.textContent = newValue;
            }
        });
    }, 3000);

    // Add some hover effects
    document.querySelectorAll('.feature-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.boxShadow = '';
        });
    });
</script>

</body>
</html>
