<?php
/**
 * AI Admin Dashboard View
 * File: app/Http/Views/ai_admin/dashboard.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Main AI system dashboard interface
 */

$title = $page_title ?? 'AI System Dashboard';
$ai_enabled = $ai_config['enabled'] ?? false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - CIS AI Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-online { background-color: #28a745; }
        .status-offline { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
        
        .metric-card {
            transition: transform 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-2px);
        }
        
        .provider-status {
            border-left: 4px solid;
        }
        .provider-status.openai { border-left-color: #00a67e; }
        .provider-status.claude { border-left-color: #ff6b35; }
        
        .quick-action-btn {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .ai-config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-robot me-2"></i>
                            AI System Dashboard
                        </h1>
                        <p class="text-muted mb-0">Manage AI integrations, test APIs, and monitor orchestration</p>
                    </div>
                    <div>
                        <span class="status-indicator <?= $ai_enabled ? 'status-online' : 'status-offline' ?>"></span>
                        <span class="fw-bold"><?= $ai_enabled ? 'AI ENABLED' : 'AI DISABLED' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status Overview -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card provider-status openai">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="fab fa-openai me-2"></i>
                                    OpenAI
                                </h5>
                                <p class="card-text">
                                    <?php if ($openai_status['available']): ?>
                                        <span class="status-indicator status-online"></span>
                                        Available
                                        <?php if ($openai_status['has_keys']): ?>
                                            <small class="text-success">(<?= count($key_status) ?> keys)</small>
                                        <?php else: ?>
                                            <small class="text-warning">(No active keys)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-indicator status-offline"></span>
                                        Unavailable
                                        <small class="text-danger"><?= htmlspecialchars($openai_status['error'] ?? '') ?></small>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <i class="fab fa-openai fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card provider-status claude">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-brain me-2"></i>
                                    Claude (Anthropic)
                                </h5>
                                <p class="card-text">
                                    <?php if ($claude_status['available']): ?>
                                        <span class="status-indicator status-online"></span>
                                        Available
                                        <?php if ($claude_status['has_keys']): ?>
                                            <small class="text-success">(Active keys)</small>
                                        <?php else: ?>
                                            <small class="text-warning">(No active keys)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-indicator status-offline"></span>
                                        Unavailable
                                        <small class="text-danger"><?= htmlspecialchars($claude_status['error'] ?? '') ?></small>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <i class="fas fa-brain fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <h3 class="text-primary mb-0"><?= count($events_summary) ?></h3>
                        <p class="text-muted mb-0">Events (24h)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <h3 class="text-success mb-0"><?= count($job_stats) ?></h3>
                        <p class="text-muted mb-0">Orchestration Jobs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <h3 class="text-info mb-0"><?= array_sum(array_column($key_status, 'active')) ?></h3>
                        <p class="text-muted mb-0">Active API Keys</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <h3 class="text-warning mb-0">
                            <?php
                            $avg_response = 0;
                            $total_events = 0;
                            foreach ($events_summary as $event) {
                                if ($event['avg_response_time']) {
                                    $avg_response += $event['avg_response_time'] * $event['count'];
                                    $total_events += $event['count'];
                                }
                            }
                            echo $total_events > 0 ? round($avg_response / $total_events) : 0;
                            ?>ms
                        </h3>
                        <p class="text-muted mb-0">Avg Response Time</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-8">
                <!-- Recent Events -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-stream me-2"></i>
                            Recent AI Events (24h)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events_summary)): ?>
                            <p class="text-muted">No AI events in the last 24 hours.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Provider</th>
                                            <th>Status</th>
                                            <th>Count</th>
                                            <th>Avg Tokens</th>
                                            <th>Avg Response</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events_summary as $event): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?= $event['provider'] === 'openai' ? 'primary' : 'warning' ?>">
                                                        <?= htmlspecialchars(strtoupper($event['provider'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $event['status'] === 'completed' ? 'success' : ($event['status'] === 'failed' ? 'danger' : 'secondary') ?>">
                                                        <?= htmlspecialchars($event['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($event['count']) ?></td>
                                                <td><?= $event['avg_tokens'] ? number_format($event['avg_tokens'], 0) : '-' ?></td>
                                                <td><?= $event['avg_response_time'] ? number_format($event['avg_response_time'], 0) . 'ms' : '-' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <a href="/ai-admin/testing" class="btn btn-primary quick-action-btn">
                            <i class="fas fa-vial me-2"></i>
                            Test AI APIs
                        </a>
                        <a href="/ai-admin/keys" class="btn btn-info quick-action-btn">
                            <i class="fas fa-key me-2"></i>
                            Manage API Keys
                        </a>
                        <a href="/ai-admin/orchestration" class="btn btn-success quick-action-btn">
                            <i class="fas fa-project-diagram me-2"></i>
                            Test Orchestration
                        </a>
                        <a href="/ai-admin/monitoring" class="btn btn-warning quick-action-btn">
                            <i class="fas fa-chart-line me-2"></i>
                            Monitor Events
                        </a>
                        
                        <hr>
                        
                        <button onclick="refreshDashboard()" class="btn btn-outline-secondary quick-action-btn">
                            <i class="fas fa-sync me-2"></i>
                            Refresh Dashboard
                        </button>
                    </div>
                </div>
                
                <!-- AI Configuration Summary -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs me-2"></i>
                            Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>AI System:</strong>
                            <span class="badge bg-<?= $ai_config['enabled'] ? 'success' : 'danger' ?>">
                                <?= $ai_config['enabled'] ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <strong>Orchestration:</strong>
                            <span class="badge bg-info">
                                <?= htmlspecialchars($ai_config['orchestration']['mode'] ?? 'bus') ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <strong>Logging:</strong>
                            <span class="badge bg-<?= $ai_config['logging']['enabled'] ? 'success' : 'secondary' ?>">
                                <?= $ai_config['logging']['enabled'] ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <strong>Rate Limiting:</strong>
                            <span class="badge bg-<?= $ai_config['rate_limiting']['enabled'] ? 'warning' : 'secondary' ?>">
                                <?= $ai_config['rate_limiting']['enabled'] ? 'Active' : 'Disabled' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Statistics -->
        <?php if (!empty($job_stats)): ?>
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-project-diagram me-2"></i>
                            Orchestration Job Statistics (24h)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Job Type</th>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Avg Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($job_stats as $job): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars(strtoupper($job['job_type'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $job['status'] === 'completed' ? 'success' : ($job['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                                    <?= htmlspecialchars($job['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= number_format($job['count']) ?></td>
                                            <td><?= $job['avg_duration'] ? number_format($job['avg_duration'], 1) . 's' : '-' ?></td>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshDashboard() {
            window.location.reload();
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            // Only refresh if user is on the page and hasn't interacted recently
            if (document.visibilityState === 'visible') {
                const lastActivity = localStorage.getItem('lastActivity');
                const now = Date.now();
                if (!lastActivity || (now - parseInt(lastActivity)) > 30000) {
                    refreshDashboard();
                }
            }
        }, 30000);
        
        // Track user activity
        ['click', 'keypress', 'scroll'].forEach(event => {
            document.addEventListener(event, function() {
                localStorage.setItem('lastActivity', Date.now().toString());
            });
        });
    </script>
</body>
</html>
