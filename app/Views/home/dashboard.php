<?php
/**
 * Dashboard View
 * 
 * Authenticated user dashboard for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */
?>

<div class="container-fluid">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0"><?= htmlspecialchars($title ?? 'Dashboard') ?></h1>
                    <p class="text-muted">Welcome back, <?= htmlspecialchars($user ?? 'User') ?></p>
                </div>
                <div>
                    <span class="badge badge-success">Online</span>
                    <span class="text-muted ml-2"><?= date('Y-m-d H:i:s') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="row mb-4">
        <?php if (isset($metrics) && is_array($metrics)): ?>
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= htmlspecialchars($metrics['total_users'] ?? '0') ?></h4>
                                <p class="mb-0">Total Users</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= htmlspecialchars($metrics['active_sessions'] ?? '0') ?></h4>
                                <p class="mb-0">Active Sessions</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-check fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= htmlspecialchars($metrics['system_health'] ?? 'Good') ?></h4>
                                <p class="mb-0">System Health</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-heartbeat fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= htmlspecialchars($metrics['last_backup'] ?? 'Never') ?></h4>
                                <p class="mb-0">Last Backup</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-database fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history mr-2"></i>Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($recent_activity) && is_array($recent_activity) && !empty($recent_activity)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>User</th>
                                        <th>Time</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-dot-circle text-success mr-2"></i>
                                                <?= htmlspecialchars($activity['action'] ?? 'Unknown action') ?>
                                            </td>
                                            <td><?= htmlspecialchars($activity['user'] ?? 'Unknown user') ?></td>
                                            <td><?= htmlspecialchars($activity['timestamp'] ?? 'Unknown time') ?></td>
                                            <td>
                                                <code><?= htmlspecialchars($activity['ip'] ?? 'Unknown IP') ?></code>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie mr-2"></i>Quick Stats
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>CPU Usage</span>
                            <span>45%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: 45%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Memory Usage</span>
                            <span>62%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: 62%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Disk Usage</span>
                            <span>28%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: 28%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Network I/O</span>
                            <span>15%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" style="width: 15%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Actions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools mr-2"></i>System Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/admin" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-cog mr-2"></i>Admin Panel
                        </a>
                        <a href="/metrics" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-chart-line mr-2"></i>View Metrics
                        </a>
                        <a href="/health" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-heartbeat mr-2"></i>Health Check
                        </a>
                        <button class="btn btn-outline-danger btn-sm" onclick="confirmLogout()">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmLogout() {
    if (confirm('Are you sure you want to logout?')) {
        // Create a form to POST logout (CSRF protection)
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/logout';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = window.csrfToken;
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
