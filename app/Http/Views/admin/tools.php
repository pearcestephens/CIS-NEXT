<?php
declare(strict_types=1);

/**
 * Admin Tools Interface - Complete Development & Testing Control Panel
 * 
 * Centralized admin interface for all Phase 1.4 automation tools with browser-based
 * execution, real-time monitoring, and comprehensive reporting.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T14:50:00Z
 */

use App\Shared\Config\ConfigService;
use App\Models\User;

// Get current user
$currentUser = $_SESSION['user'] ?? null;

// Tool configurations
$tools = [
    'automation' => [
        'name' => 'Ultimate Automation Suite',
        'description' => 'Comprehensive testing framework with route discovery, auth testing, security validation',
        'path' => '/tools/automation/ultimate_automation_suite.php',
        'icon' => 'fas fa-robot',
        'status' => ConfigService::get('tools.automation.enabled', true),
        'category' => 'Testing & QA'
    ],
    'browser_test' => [
        'name' => 'Browserless Test Client',
        'description' => 'HTTP client testing with session management and CSRF handling',
        'path' => '/tools/test_client_demo.php',
        'icon' => 'fas fa-globe',
        'status' => true,
        'category' => 'Testing & QA'
    ],
    'route_discovery' => [
        'name' => 'Route Discovery',
        'description' => 'Static route analysis and endpoint discovery',
        'path' => '/tools/route_discovery_demo.php',
        'icon' => 'fas fa-route',
        'status' => true,
        'category' => 'Analysis'
    ],
    'performance' => [
        'name' => 'Performance Monitor',
        'description' => 'Real-time performance metrics and profiling',
        'path' => '/admin/performance',
        'icon' => 'fas fa-tachometer-alt',
        'status' => ConfigService::get('profiling.enabled', true),
        'category' => 'Monitoring'
    ],
    'database' => [
        'name' => 'Database Tools',
        'description' => 'Migration runner, schema inspector, query analyzer',
        'path' => '/tools/database/',
        'icon' => 'fas fa-database',
        'status' => true,
        'category' => 'Database'
    ],
    'system' => [
        'name' => 'System Monitor',
        'description' => 'Server health, resource usage, error tracking',
        'path' => '/admin/system',
        'icon' => 'fas fa-server',
        'status' => true,
        'category' => 'System'
    ],
    'security' => [
        'name' => 'Security Scanner',
        'description' => 'Security header validation, vulnerability checks',
        'path' => '/tools/security_scanner.php',
        'icon' => 'fas fa-shield-alt',
        'status' => ConfigService::get('security.scanning_enabled', true),
        'category' => 'Security'
    ],
    'reports' => [
        'name' => 'Report Viewer',
        'description' => 'View automation reports, test results, performance data',
        'path' => '/var/reports/',
        'icon' => 'fas fa-chart-bar',
        'status' => true,
        'category' => 'Reporting'
    ]
];

// Group tools by category
$toolsByCategory = [];
foreach ($tools as $key => $tool) {
    $toolsByCategory[$tool['category']][$key] = $tool;
}

// Get system status
$systemStatus = [
    'automation_enabled' => ConfigService::get('tools.automation.enabled', true),
    'testing_mode' => ConfigService::get('app.testing_mode', false),
    'performance_monitoring' => ConfigService::get('profiling.enabled', true),
    'debug_mode' => ConfigService::get('app.debug', false),
    'cache_enabled' => ConfigService::get('cache.enabled', true)
];

$statusColor = function($status) {
    return $status ? 'success' : 'danger';
};

$statusText = function($status) {
    return $status ? 'Enabled' : 'Disabled';
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tools - CIS V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .tool-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .tool-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-left-color: #007bff;
        }
        .tool-card.disabled {
            opacity: 0.6;
        }
        .category-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .quick-actions {
            position: sticky;
            top: 20px;
            z-index: 100;
        }
        .system-status {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .execution-modal .modal-body {
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            border-radius: 5px;
            padding: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">
                <i class="fas fa-tools mr-2"></i>CIS V2 Admin Tools
            </a>
            <div class="navbar-nav ml-auto">
                <span class="navbar-text mr-3">
                    <i class="fas fa-user mr-1"></i><?= htmlspecialchars($currentUser['name'] ?? 'Admin') ?>
                </span>
                <a href="/dashboard" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Quick Actions Sidebar -->
            <div class="col-md-3">
                <div class="quick-actions">
                    <div class="system-status">
                        <h5><i class="fas fa-heartbeat mr-2"></i>System Status</h5>
                        <div class="row">
                            <div class="col-12">
                                <?php foreach ($systemStatus as $key => $status): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small><?= str_replace('_', ' ', ucwords($key)) ?></small>
                                    <span class="badge badge-<?= $statusColor($status) ?> badge-pill">
                                        <?= $statusText($status) ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-rocket mr-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary btn-sm mb-2" onclick="runTool('automation')">
                                    <i class="fas fa-play mr-2"></i>Run Full Suite
                                </button>
                                <button class="btn btn-outline-primary btn-sm mb-2" onclick="viewReports()">
                                    <i class="fas fa-chart-bar mr-2"></i>View Reports
                                </button>
                                <button class="btn btn-outline-secondary btn-sm mb-2" onclick="clearCache()">
                                    <i class="fas fa-trash mr-2"></i>Clear Cache
                                </button>
                                <a href="/_health" class="btn btn-outline-success btn-sm" target="_blank">
                                    <i class="fas fa-heartbeat mr-2"></i>Health Check
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h6><i class="fas fa-clock mr-2"></i>Recent Activity</h6>
                        </div>
                        <div class="card-body">
                            <div id="recentActivity">
                                <small class="text-muted">Loading recent activity...</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Tools Area -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-toolbox mr-2"></i>Development & Testing Tools</h2>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="refreshAllStatus()">
                            <i class="fas fa-sync mr-1"></i>Refresh Status
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#settingsModal">
                            <i class="fas fa-cog mr-1"></i>Settings
                        </button>
                    </div>
                </div>

                <!-- Tools by Category -->
                <?php foreach ($toolsByCategory as $category => $categoryTools): ?>
                <div class="mb-4">
                    <h4 class="category-header">
                        <i class="fas fa-folder-open mr-2"></i><?= htmlspecialchars($category) ?>
                    </h4>
                    
                    <div class="row">
                        <?php foreach ($categoryTools as $toolKey => $tool): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card tool-card h-100 <?= $tool['status'] ? '' : 'disabled' ?>">
                                <div class="card-body position-relative">
                                    <span class="status-badge">
                                        <span class="badge badge-<?= $tool['status'] ? 'success' : 'secondary' ?>">
                                            <?= $tool['status'] ? 'Active' : 'Disabled' ?>
                                        </span>
                                    </span>
                                    
                                    <h5 class="card-title">
                                        <i class="<?= htmlspecialchars($tool['icon']) ?> mr-2 text-primary"></i>
                                        <?= htmlspecialchars($tool['name']) ?>
                                    </h5>
                                    
                                    <p class="card-text text-muted">
                                        <?= htmlspecialchars($tool['description']) ?>
                                    </p>
                                    
                                    <div class="mt-auto">
                                        <?php if ($tool['status']): ?>
                                        <button class="btn btn-primary btn-sm mr-2" onclick="runTool('<?= $toolKey ?>')">
                                            <i class="fas fa-play mr-1"></i>Run
                                        </button>
                                        <a href="<?= htmlspecialchars($tool['path']) ?>" 
                                           class="btn btn-outline-secondary btn-sm" 
                                           target="_blank">
                                            <i class="fas fa-external-link-alt mr-1"></i>Open
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-ban mr-1"></i>Disabled
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tool Execution Modal -->
    <div class="modal fade execution-modal" id="executionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-terminal mr-2"></i>Tool Execution
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="executionOutput">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                            <p>Executing tool...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="downloadResults()" id="downloadBtn" style="display:none;">
                        <i class="fas fa-download mr-1"></i>Download Results
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-cog mr-2"></i>Tool Settings
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="settingsForm">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="automation_enabled" 
                                       <?= $systemStatus['automation_enabled'] ? 'checked' : '' ?>>
                                Enable Automation Suite
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="testing_mode" 
                                       <?= $systemStatus['testing_mode'] ? 'checked' : '' ?>>
                                Testing Mode
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="debug_mode" 
                                       <?= $systemStatus['debug_mode'] ? 'checked' : '' ?>>
                                Debug Mode
                            </label>
                        </div>
                        <div class="form-group">
                            <label for="timeout">Request Timeout (seconds)</label>
                            <input type="number" class="form-control" id="timeout" 
                                   value="<?= ConfigService::get('http.timeout', 30) ?>" min="10" max="300">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveSettings()">
                        <i class="fas fa-save mr-1"></i>Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentExecution = null;
        
        const toolConfigs = <?= json_encode($tools) ?>;
        
        function runTool(toolKey) {
            const tool = toolConfigs[toolKey];
            if (!tool || !tool.status) {
                alert('Tool is not available');
                return;
            }
            
            $('#executionModal').modal('show');
            $('#executionOutput').html(`
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>Running ${tool.name}...</p>
                </div>
            `);
            
            const startTime = Date.now();
            
            $.ajax({
                url: tool.path + (tool.path.includes('?') ? '&' : '?') + 'run=1',
                method: 'GET',
                timeout: 120000,
                success: function(data) {
                    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
                    let output;
                    
                    if (typeof data === 'string') {
                        output = data;
                    } else {
                        output = JSON.stringify(data, null, 2);
                    }
                    
                    $('#executionOutput').html(`
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>
                            Tool executed successfully in ${duration}s
                        </div>
                        <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;">${output}</pre>
                    `);
                    
                    $('#downloadBtn').show();
                    currentExecution = { tool: toolKey, data: output, duration: duration };
                    
                    updateRecentActivity(`${tool.name} completed in ${duration}s`);
                },
                error: function(xhr, status, error) {
                    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
                    
                    $('#executionOutput').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Tool execution failed after ${duration}s
                        </div>
                        <pre class="bg-light p-3 rounded">${error}\n\nStatus: ${status}\nResponse: ${xhr.responseText}</pre>
                    `);
                    
                    updateRecentActivity(`${tool.name} failed after ${duration}s`);
                }
            });
        }
        
        function downloadResults() {
            if (!currentExecution) return;
            
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const filename = `${currentExecution.tool}_results_${timestamp}.json`;
            
            const blob = new Blob([currentExecution.data], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename;
            
            document.body.appendChild(a);
            a.click();
            
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
        
        function viewReports() {
            window.open('/var/reports/', '_blank');
        }
        
        function clearCache() {
            if (!confirm('Clear all cache data? This may temporarily slow down the application.')) {
                return;
            }
            
            $.post('/api/admin/cache/clear', function(data) {
                alert('Cache cleared successfully');
                updateRecentActivity('Cache cleared');
            }).fail(function() {
                alert('Failed to clear cache');
            });
        }
        
        function refreshAllStatus() {
            location.reload();
        }
        
        function saveSettings() {
            const formData = new FormData(document.getElementById('settingsForm'));
            const settings = {};
            
            for (let [key, value] of formData.entries()) {
                settings[key] = value === 'on';
            }
            
            settings.timeout = document.getElementById('timeout').value;
            
            $.post('/api/admin/settings', settings, function(data) {
                alert('Settings saved successfully');
                $('#settingsModal').modal('hide');
                updateRecentActivity('Settings updated');
            }).fail(function() {
                alert('Failed to save settings');
            });
        }
        
        function updateRecentActivity(message) {
            const timestamp = new Date().toLocaleTimeString();
            const activity = `
                <div class="border-bottom pb-2 mb-2">
                    <small class="text-primary">${timestamp}</small><br>
                    <small>${message}</small>
                </div>
            `;
            
            $('#recentActivity').prepend(activity);
            
            // Keep only last 5 activities
            $('#recentActivity > div').slice(5).remove();
        }
        
        // Initialize recent activity
        $(document).ready(function() {
            updateRecentActivity('Admin tools interface loaded');
            
            // Auto-refresh system status every 30 seconds
            setInterval(function() {
                $.get('/_health', function(data) {
                    // Update status indicators if needed
                }).fail(function() {
                    console.warn('Health check failed');
                });
            }, 30000);
        });
    </script>
</body>
</html>
