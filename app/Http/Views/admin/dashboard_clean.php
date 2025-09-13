<?php
/**
 * Modern Clean Dashboard - Enterprise CIS 2.0
 * 
 * High-performance admin dashboard with real-time metrics
 * Author: GitHub Copilot
 * Created: 2025-09-13
 */

// Use the clean layout
$title = 'Dashboard';
ob_start();
?>

<!-- Dashboard Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($user['name'] ?? 'Admin') ?>. Here's what's happening with your system.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- System Status Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="metric-card">
            <div class="card-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                <i class="bi bi-cpu"></i>
            </div>
            <div class="card-title">System Health</div>
            <div class="card-value" data-metric="system_health"><?= $metrics['system_health'] ?? '98.5%' ?></div>
            <div class="card-change positive">
                <i class="bi bi-arrow-up"></i>
                <span>+2.1% from yesterday</span>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="metric-card">
            <div class="card-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                <i class="bi bi-person-check"></i>
            </div>
            <div class="card-title">Active Users</div>
            <div class="card-value" data-metric="active_users"><?= $metrics['active_users'] ?? '247' ?></div>
            <div class="card-change positive">
                <i class="bi bi-arrow-up"></i>
                <span>+12 from last hour</span>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="metric-card">
            <div class="card-icon" style="background: linear-gradient(135deg, #4ecdc4, #44a08d);">
                <i class="bi bi-speedometer2"></i>
            </div>
            <div class="card-title">Response Time</div>
            <div class="card-value" data-metric="response_time"><?= $metrics['response_time'] ?? '89ms' ?></div>
            <div class="card-change negative">
                <i class="bi bi-arrow-down"></i>
                <span>+5ms from baseline</span>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="metric-card">
            <div class="card-icon" style="background: linear-gradient(135deg, #fa709a, #fee140);">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="card-title">Security Score</div>
            <div class="card-value" data-metric="security_score"><?= $metrics['security_score'] ?? '94/100' ?></div>
            <div class="card-change positive">
                <i class="bi bi-arrow-up"></i>
                <span>Excellent</span>
            </div>
        </div>
    </div>
</div>

<!-- Performance Metrics Row -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="metric-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Performance Overview</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="timeRange" id="range24h" checked>
                    <label class="btn btn-outline-primary" for="range24h">24H</label>
                    
                    <input type="radio" class="btn-check" name="timeRange" id="range7d">
                    <label class="btn btn-outline-primary" for="range7d">7D</label>
                    
                    <input type="radio" class="btn-check" name="timeRange" id="range30d">
                    <label class="btn btn-outline-primary" for="range30d">30D</label>
                </div>
            </div>
            
            <!-- Performance Chart Placeholder -->
            <div style="height: 300px; background: linear-gradient(45deg, #f8f9fa, #e9ecef); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <div class="text-center">
                    <i class="bi bi-graph-up-arrow" style="font-size: 3rem; color: #6c757d;"></i>
                    <p class="text-muted mt-2">Real-time performance chart will load here</p>
                    <small class="text-muted">Chart.js integration ready</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="metric-card">
            <h5 class="card-title mb-3">System Resources</h5>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">CPU Usage</span>
                    <span class="fw-bold"><?= $metrics['cpu_usage'] ?? '45%' ?></span>
                </div>
                <div class="progress-bar-custom">
                    <div class="progress bg-info" style="width: <?= $metrics['cpu_usage'] ?? '45%' ?>"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Memory Usage</span>
                    <span class="fw-bold"><?= $metrics['memory_usage'] ?? '62%' ?></span>
                </div>
                <div class="progress-bar-custom">
                    <div class="progress bg-warning" style="width: <?= $metrics['memory_usage'] ?? '62%' ?>"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Disk Space</span>
                    <span class="fw-bold"><?= $metrics['disk_usage'] ?? '78%' ?></span>
                </div>
                <div class="progress-bar-custom">
                    <div class="progress bg-danger" style="width: <?= $metrics['disk_usage'] ?? '78%' ?>"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Network I/O</span>
                    <span class="fw-bold"><?= $metrics['network_io'] ?? '23%' ?></span>
                </div>
                <div class="progress-bar-custom">
                    <div class="progress bg-success" style="width: <?= $metrics['network_io'] ?? '23%' ?>"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Services Status Row -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="metric-card">
            <h5 class="card-title mb-3">Service Status</h5>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-database me-2 text-success"></i>
                    <span>Database</span>
                </div>
                <span class="status-indicator status-healthy">
                    <i class="bi bi-circle-fill"></i>
                    Healthy
                </span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-cloud me-2 text-info"></i>
                    <span>Cache Service</span>
                </div>
                <span class="status-indicator status-healthy">
                    <i class="bi bi-circle-fill"></i>
                    Online
                </span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-envelope me-2 text-warning"></i>
                    <span>Email Service</span>
                </div>
                <span class="status-indicator status-warning">
                    <i class="bi bi-circle-fill"></i>
                    Degraded
                </span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-shield-lock me-2 text-success"></i>
                    <span>Security Monitor</span>
                </div>
                <span class="status-indicator status-healthy">
                    <i class="bi bi-circle-fill"></i>
                    Active
                </span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-gear me-2 text-success"></i>
                    <span>Background Jobs</span>
                </div>
                <span class="status-indicator status-healthy">
                    <i class="bi bi-circle-fill"></i>
                    Processing
                </span>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="metric-card">
            <h5 class="card-title mb-3">Recent Activity</h5>
            
            <div class="d-flex mb-3">
                <div class="flex-shrink-0">
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-person-plus text-white" style="font-size: 0.8rem;"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-1 text-sm">New user registered</p>
                    <small class="text-muted">2 minutes ago</small>
                </div>
            </div>
            
            <div class="d-flex mb-3">
                <div class="flex-shrink-0">
                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-check-lg text-white" style="font-size: 0.8rem;"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-1 text-sm">Backup completed successfully</p>
                    <small class="text-muted">15 minutes ago</small>
                </div>
            </div>
            
            <div class="d-flex mb-3">
                <div class="flex-shrink-0">
                    <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-exclamation-triangle text-white" style="font-size: 0.8rem;"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-1 text-sm">High memory usage detected</p>
                    <small class="text-muted">1 hour ago</small>
                </div>
            </div>
            
            <div class="d-flex">
                <div class="flex-shrink-0">
                    <div class="rounded-circle bg-info d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-arrow-clockwise text-white" style="font-size: 0.8rem;"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-1 text-sm">System maintenance completed</p>
                    <small class="text-muted">3 hours ago</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Row -->
<div class="row">
    <div class="col-12">
        <div class="metric-card">
            <h5 class="card-title mb-3">Quick Actions</h5>
            <div class="row">
                <div class="col-md-2 col-sm-4 col-6 mb-3">
                    <a href="/admin/users" class="quick-action">
                        <i class="bi bi-people text-primary"></i>
                        <div class="fw-semibold">Manage Users</div>
                        <small class="text-muted">User accounts</small>
                    </a>
                </div>
                
                <div class="col-md-2 col-sm-4 col-6 mb-3">
                    <a href="/admin/tools" class="quick-action">
                        <i class="bi bi-archive text-success"></i>
                        <div class="fw-semibold">Admin Tools</div>
                        <small class="text-muted">System tools</small>
                    </a>
                </div>
                
                <div class="col-md-2 col-sm-4 col-6 mb-3">
                    <a href="/admin/system" class="quick-action">
                        <i class="bi bi-shield-lock text-warning"></i>
                        <div class="fw-semibold">System Monitor</div>
                        <small class="text-muted">System status</small>
                    </a>
                </div>
                
                <div class="col-md-2 col-sm-4 col-6 mb-3">
                    <a href="/admin/analytics" class="quick-action">
                        <i class="bi bi-speedometer2 text-info"></i>
                        <div class="fw-semibold">Analytics</div>
                        <small class="text-muted">Performance metrics</small>
                    </a>
                </div>
                
                <div class="col-md-2 col-sm-4 col-6 mb-3">
                    <a href="/admin/logs" class="quick-action">
                        <i class="bi bi-journal-text text-secondary"></i>
                        <div class="fw-semibold">View Logs</div>
                        <small class="text-muted">System logs</small>
                    </a>
                </div>
                
                <div class="col-md-2 col-sm-4 col-6 mb-3">
                    <a href="/admin/settings" class="quick-action">
                        <i class="bi bi-gear text-dark"></i>
                        <div class="fw-semibold">Settings</div>
                        <small class="text-muted">Configuration</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshDashboard() {
    // Add loading state
    document.body.classList.add('loading');
    
    // Refresh metrics
    fetch('/api/admin/dashboard-metrics', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateDashboardMetrics(data.data);
        }
    })
    .catch(error => {
        console.error('Error refreshing dashboard:', error);
    })
    .finally(() => {
        document.body.classList.remove('loading');
    });
}

function updateDashboardMetrics(metrics) {
    for (const [key, value] of Object.entries(metrics)) {
        const element = document.querySelector(`[data-metric="${key}"]`);
        if (element) {
            element.textContent = value;
        }
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout_clean.php';
?>
