<?php
/**
 * Integrations Dashboard View
 * File: app/Http/Views/integrations/dashboard.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Main dashboard for business system integrations
 */

$title = $title ?? 'Integrations Dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - CIS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .integration-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid #dee2e6;
        }
        .integration-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .integration-card.healthy {
            border-left-color: #28a745;
        }
        .integration-card.degraded {
            border-left-color: #ffc107;
        }
        .integration-card.unhealthy {
            border-left-color: #dc3545;
        }
        .integration-card.unknown {
            border-left-color: #6c757d;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-healthy { background-color: #28a745; }
        .status-degraded { background-color: #ffc107; }
        .status-unhealthy { background-color: #dc3545; }
        .status-unknown { background-color: #6c757d; }
        
        .health-check-btn {
            min-width: 120px;
        }
        
        .last-check {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .metric-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                            <i class="fas fa-plug me-2"></i>
                            Business Integrations Dashboard
                        </h1>
                        <p class="text-muted mb-0">Monitor and manage connections to Vend, Deputy, and Xero</p>
                    </div>
                    <div>
                        <button onclick="refreshAllHealth()" class="btn btn-outline-primary">
                            <i class="fas fa-sync me-2"></i>
                            Refresh All
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integration Cards -->
        <div class="row">
            <!-- Vend POS Integration -->
            <div class="col-md-4 mb-4">
                <div class="card integration-card" id="vend-card" data-integration="vend">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-cash-register me-2 text-primary"></i>
                                    Vend POS
                                </h5>
                                <p class="card-text text-muted">Point of Sale System</p>
                            </div>
                            <div class="text-end">
                                <span class="status-indicator status-unknown" id="vend-status"></span>
                                <span class="fw-bold" id="vend-status-text">UNKNOWN</span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h6 mb-0" id="vend-response-time">-</div>
                                    <small class="text-muted">Response Time</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h6 mb-0" id="vend-last-sync">-</div>
                                    <small class="text-muted">Last Sync</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button onclick="checkHealth('vend')" class="btn btn-outline-primary health-check-btn">
                                <i class="fas fa-heartbeat me-2"></i>
                                Check Health
                            </button>
                            <div class="btn-group" role="group">
                                <button onclick="viewData('vend', 'products')" class="btn btn-sm btn-outline-secondary">
                                    Products
                                </button>
                                <button onclick="viewData('vend', 'inventory')" class="btn btn-sm btn-outline-secondary">
                                    Inventory
                                </button>
                                <button onclick="syncData('vend', 'products')" class="btn btn-sm btn-outline-info">
                                    Sync
                                </button>
                            </div>
                        </div>
                        
                        <div class="last-check mt-2">
                            <small id="vend-last-check">Last checked: Never</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deputy Workforce Integration -->
            <div class="col-md-4 mb-4">
                <div class="card integration-card" id="deputy-card" data-integration="deputy">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-users me-2 text-warning"></i>
                                    Deputy Workforce
                                </h5>
                                <p class="card-text text-muted">Staff Management</p>
                            </div>
                            <div class="text-end">
                                <span class="status-indicator status-unknown" id="deputy-status"></span>
                                <span class="fw-bold" id="deputy-status-text">UNKNOWN</span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h6 mb-0" id="deputy-response-time">-</div>
                                    <small class="text-muted">Response Time</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h6 mb-0" id="deputy-last-sync">-</div>
                                    <small class="text-muted">Last Sync</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button onclick="checkHealth('deputy')" class="btn btn-outline-primary health-check-btn">
                                <i class="fas fa-heartbeat me-2"></i>
                                Check Health
                            </button>
                            <div class="btn-group" role="group">
                                <button onclick="viewData('deputy', 'employees')" class="btn btn-sm btn-outline-secondary">
                                    Employees
                                </button>
                                <button onclick="viewData('deputy', 'timesheets')" class="btn btn-sm btn-outline-secondary">
                                    Timesheets
                                </button>
                                <button onclick="syncData('deputy', 'employees')" class="btn btn-sm btn-outline-info">
                                    Sync
                                </button>
                            </div>
                        </div>
                        
                        <div class="last-check mt-2">
                            <small id="deputy-last-check">Last checked: Never</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Xero Accounting Integration -->
            <div class="col-md-4 mb-4">
                <div class="card integration-card" id="xero-card" data-integration="xero">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-calculator me-2 text-success"></i>
                                    Xero Accounting
                                </h5>
                                <p class="card-text text-muted">Financial Management</p>
                            </div>
                            <div class="text-end">
                                <span class="status-indicator status-unknown" id="xero-status"></span>
                                <span class="fw-bold" id="xero-status-text">UNKNOWN</span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h6 mb-0" id="xero-response-time">-</div>
                                    <small class="text-muted">Response Time</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h6 mb-0" id="xero-last-sync">-</div>
                                    <small class="text-muted">Last Sync</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button onclick="checkHealth('xero')" class="btn btn-outline-primary health-check-btn">
                                <i class="fas fa-heartbeat me-2"></i>
                                Check Health
                            </button>
                            <div class="btn-group" role="group">
                                <button onclick="viewData('xero', 'transactions')" class="btn btn-sm btn-outline-secondary">
                                    Transactions
                                </button>
                                <button onclick="viewData('xero', 'invoices')" class="btn btn-sm btn-outline-secondary">
                                    Invoices
                                </button>
                                <button onclick="syncData('xero', 'transactions')" class="btn btn-sm btn-outline-info">
                                    Sync
                                </button>
                            </div>
                        </div>
                        
                        <div class="last-check mt-2">
                            <small id="xero-last-check">Last checked: Never</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integration Management Panel -->
        <div class="row mt-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs me-2"></i>
                            Integration Management
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Quick Actions</h6>
                                <div class="d-grid gap-2">
                                    <a href="/admin/integrations/secrets" class="btn btn-outline-primary">
                                        <i class="fas fa-key me-2"></i>
                                        Manage API Keys
                                    </a>
                                    <a href="/admin/integrations/sync" class="btn btn-outline-info">
                                        <i class="fas fa-sync me-2"></i>
                                        Sync Dashboard
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>System Status</h6>
                                <div id="system-status">
                                    <div class="d-flex justify-content-between">
                                        <span>Overall Health:</span>
                                        <span class="badge bg-secondary" id="overall-status">Checking...</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <span>Active Integrations:</span>
                                        <span id="active-count">0/3</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        const integrations = ['vend', 'deputy', 'xero'];
        let healthData = {};
        
        // Check health for specific integration
        async function checkHealth(integration) {
            const btn = document.querySelector(`button[onclick="checkHealth('${integration}')"]`);
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking...';
            btn.disabled = true;
            
            try {
                const response = await fetch(`/admin/integrations/${integration}/health`);
                const data = await response.json();
                
                updateIntegrationStatus(integration, data);
                healthData[integration] = data;
                
                // Update last check time
                document.getElementById(`${integration}-last-check`).textContent = 
                    `Last checked: ${new Date().toLocaleTimeString()}`;
                    
            } catch (error) {
                console.error(`Health check failed for ${integration}:`, error);
                updateIntegrationStatus(integration, {
                    ok: false,
                    error: 'Health check failed',
                    response_time_ms: 0
                });
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
                updateOverallStatus();
            }
        }
        
        // Update integration status display
        function updateIntegrationStatus(integration, data) {
            const card = document.getElementById(`${integration}-card`);
            const statusIndicator = document.getElementById(`${integration}-status`);
            const statusText = document.getElementById(`${integration}-status-text`);
            const responseTime = document.getElementById(`${integration}-response-time`);
            
            // Remove existing status classes
            card.classList.remove('healthy', 'degraded', 'unhealthy', 'unknown');
            statusIndicator.classList.remove('status-healthy', 'status-degraded', 'status-unhealthy', 'status-unknown');
            
            if (data.ok) {
                const status = data.response_time_ms > 2000 ? 'degraded' : 'healthy';
                card.classList.add(status);
                statusIndicator.classList.add(`status-${status}`);
                statusText.textContent = status.toUpperCase();
                responseTime.textContent = `${data.response_time_ms}ms`;
            } else {
                card.classList.add('unhealthy');
                statusIndicator.classList.add('status-unhealthy');
                statusText.textContent = 'UNHEALTHY';
                responseTime.textContent = 'Error';
            }
        }
        
        // Update overall system status
        function updateOverallStatus() {
            const activeCount = Object.values(healthData).filter(data => data.ok).length;
            const totalCount = integrations.length;
            
            document.getElementById('active-count').textContent = `${activeCount}/${totalCount}`;
            
            const overallStatusBadge = document.getElementById('overall-status');
            if (activeCount === totalCount) {
                overallStatusBadge.className = 'badge bg-success';
                overallStatusBadge.textContent = 'All Systems Operational';
            } else if (activeCount > 0) {
                overallStatusBadge.className = 'badge bg-warning';
                overallStatusBadge.textContent = 'Partial Service';
            } else {
                overallStatusBadge.className = 'badge bg-danger';
                overallStatusBadge.textContent = 'Service Degraded';
            }
        }
        
        // Refresh all health checks
        async function refreshAllHealth() {
            for (const integration of integrations) {
                await checkHealth(integration);
                // Small delay to avoid overwhelming the server
                await new Promise(resolve => setTimeout(resolve, 500));
            }
        }
        
        // View integration data
        function viewData(integration, dataType) {
            window.open(`/admin/integrations/${integration}/${dataType}`, '_blank');
        }
        
        // Sync integration data
        async function syncData(integration, dataType) {
            if (!confirm(`Start synchronization of ${integration} ${dataType}?`)) {
                return;
            }
            
            try {
                const response = await fetch(`/admin/integrations/sync/${integration}/${dataType}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Synchronization started for ${integration} ${dataType}`);
                } else {
                    alert(`Synchronization failed: ${result.error}`);
                }
            } catch (error) {
                alert(`Synchronization error: ${error.message}`);
            }
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                refreshAllHealth();
            }
        }, 30000);
        
        // Initial health check on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(refreshAllHealth, 1000);
        });
    </script>
</body>
</html>
