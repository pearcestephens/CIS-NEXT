<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - CIS Monitor</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-healthy { color: #28a745; }
        .status-degraded { color: #ffc107; }
        .status-down { color: #dc3545; }
        .status-not_configured { color: #6c757d; }
        
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .service-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #dee2e6;
        }
        
        .service-card.healthy { border-left-color: #28a745; }
        .service-card.degraded { border-left-color: #ffc107; }
        .service-card.down { border-left-color: #dc3545; }
        .service-card.not_configured { border-left-color: #6c757d; }
        
        .performance-chart {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            display: none;
        }
        
        .auto-refresh-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .pulse { animation: pulse 2s infinite; }
        
        .latency-badge {
            font-size: 0.8em;
            padding: 0.2em 0.5em;
        }
        
        .system-metrics {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .alert-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h1><i class="fas fa-chart-line"></i> <?= $title ?></h1>
                    <div class="d-flex gap-2">
                        <button id="refresh-now" class="btn btn-outline-primary">
                            <i class="fas fa-sync-alt"></i> Refresh Now
                        </button>
                        <button id="export-data" class="btn btn-outline-secondary">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card text-center">
                    <h3 id="services-healthy">-</h3>
                    <p>Services Healthy</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card text-center">
                    <h3 id="avg-latency">-</h3>
                    <p>Avg Response Time</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card text-center">
                    <h3 id="memory-usage">-</h3>
                    <p>Memory Usage</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card text-center">
                    <h3 id="last-update">-</h3>
                    <p>Last Update</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Service Status -->
            <div class="col-lg-8">
                <h3><i class="fas fa-server"></i> Service Status</h3>
                
                <!-- Database & Cache -->
                <div class="service-card" id="service-database">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-database"></i> Database</h5>
                            <p class="mb-0 text-muted" id="database-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="database-status">Unknown</span>
                            <div class="latency-badge badge bg-light text-dark" id="database-latency">- ms</div>
                        </div>
                    </div>
                </div>
                
                <div class="service-card" id="service-redis">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-memory"></i> Redis Cache</h5>
                            <p class="mb-0 text-muted" id="redis-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="redis-status">Unknown</span>
                            <div class="latency-badge badge bg-light text-dark" id="redis-latency">- ms</div>
                        </div>
                    </div>
                </div>

                <!-- AI Services -->
                <h4 class="mt-4"><i class="fas fa-robot"></i> AI Integrations</h4>
                
                <div class="service-card" id="service-openai">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-brain"></i> OpenAI</h5>
                            <p class="mb-0 text-muted" id="openai-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="openai-status">Unknown</span>
                            <div class="latency-badge badge bg-light text-dark" id="openai-latency">- ms</div>
                        </div>
                    </div>
                </div>
                
                <div class="service-card" id="service-claude">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-comments"></i> Claude</h5>
                            <p class="mb-0 text-muted" id="claude-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="claude-status">Unknown</span>
                            <div class="latency-badge badge bg-light text-dark" id="claude-latency">- ms</div>
                        </div>
                    </div>
                </div>
                
                <div class="service-card" id="service-orchestrator">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-cogs"></i> AI Orchestrator</h5>
                            <p class="mb-0 text-muted" id="orchestrator-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="orchestrator-status">Unknown</span>
                            <div class="small text-muted" id="orchestrator-jobs">0 pending jobs</div>
                        </div>
                    </div>
                </div>

                <!-- Business Integrations -->
                <h4 class="mt-4"><i class="fas fa-briefcase"></i> Business Integrations</h4>
                
                <div class="service-card" id="service-vend">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-cash-register"></i> Vend POS</h5>
                            <p class="mb-0 text-muted" id="vend-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="vend-status">Unknown</span>
                            <div class="latency-badge badge bg-light text-dark" id="vend-latency">- ms</div>
                        </div>
                    </div>
                </div>
                
                <div class="service-card" id="service-deputy">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-users"></i> Deputy Workforce</h5>
                            <p class="mb-0 text-muted" id="deputy-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="deputy-status">Unknown</span>
                            <div class="latency-badge badge bg-light text-dark" id="deputy-latency">- ms</div>
                        </div>
                    </div>
                </div>
                
                <div class="service-card" id="service-xero">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-calculator"></i> Xero Accounting</h5>
                            <p class="mb-0 text-muted" id="xero-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="xero-status">Unknown</span>
                            <div class="latency-badge badge bg-light text-dark" id="xero-latency">- ms</div>
                        </div>
                    </div>
                </div>

                <!-- System Services -->
                <h4 class="mt-4"><i class="fas fa-cog"></i> System Services</h4>
                
                <div class="service-card" id="service-queue">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-tasks"></i> Job Queue</h5>
                            <p class="mb-0 text-muted" id="queue-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="queue-status">Unknown</span>
                            <div class="small text-muted" id="queue-jobs">0 jobs pending</div>
                        </div>
                    </div>
                </div>
                
                <div class="service-card" id="service-telemetry">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-chart-bar"></i> Telemetry</h5>
                            <p class="mb-0 text-muted" id="telemetry-details">Checking...</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary" id="telemetry-status">Unknown</span>
                            <div class="small text-muted" id="telemetry-events">0 events today</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- System Metrics -->
                <div class="system-metrics mb-4">
                    <h4><i class="fas fa-server"></i> System Metrics</h4>
                    <div class="row text-center">
                        <div class="col-6">
                            <strong id="system-load">-</strong>
                            <small class="d-block text-muted">Load Average</small>
                        </div>
                        <div class="col-6">
                            <strong id="disk-usage">-</strong>
                            <small class="d-block text-muted">Disk Usage</small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">PHP Version: <span id="php-version">-</span></small><br>
                        <small class="text-muted">Uptime: <span id="system-uptime">-</span></small>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <div class="performance-chart">
                    <h4><i class="fas fa-exclamation-triangle"></i> Recent Alerts</h4>
                    <div id="alerts-list">
                        <p class="text-muted">Loading alerts...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-refresh Toggle -->
    <div class="auto-refresh-toggle">
        <button id="auto-refresh" class="btn btn-success">
            <i class="fas fa-play"></i> Auto Refresh ON
        </button>
    </div>

    <!-- Refresh Indicator -->
    <div class="refresh-indicator" id="refresh-indicator">
        <i class="fas fa-sync-alt fa-spin"></i> Refreshing...
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let autoRefreshEnabled = true;
        let refreshInterval;

        // Initialize monitoring dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadMonitorData();
            startAutoRefresh();

            // Event listeners
            document.getElementById('refresh-now').addEventListener('click', loadMonitorData);
            document.getElementById('auto-refresh').addEventListener('click', toggleAutoRefresh);
            document.getElementById('export-data').addEventListener('click', exportData);
        });

        function loadMonitorData() {
            showRefreshIndicator();
            
            fetch('/admin/monitor/api')
                .then(response => response.json())
                .then(data => {
                    updateOverviewMetrics(data);
                    updateServiceStatus(data.services);
                    updateSystemMetrics(data.system);
                    hideRefreshIndicator();
                })
                .catch(error => {
                    console.error('Error loading monitor data:', error);
                    hideRefreshIndicator();
                });
        }

        function updateOverviewMetrics(data) {
            const services = data.services;
            let healthyCount = 0;
            let totalLatency = 0;
            let serviceCount = 0;

            // Count healthy services and calculate average latency
            function processServices(servicesObj) {
                for (const [key, service] of Object.entries(servicesObj)) {
                    if (service.status) {
                        serviceCount++;
                        if (service.status === 'healthy') healthyCount++;
                        if (service.latency_ms) totalLatency += service.latency_ms;
                    } else if (typeof service === 'object') {
                        processServices(service);
                    }
                }
            }

            processServices(services);

            document.getElementById('services-healthy').textContent = `${healthyCount}/${serviceCount}`;
            document.getElementById('avg-latency').textContent = serviceCount > 0 ? `${Math.round(totalLatency/serviceCount)}ms` : '-';
            document.getElementById('memory-usage').textContent = formatBytes(data.performance.memory_usage);
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString();
        }

        function updateServiceStatus(services) {
            // Database
            updateServiceCard('database', services.database);
            
            // Redis
            updateServiceCard('redis', services.redis);
            
            // AI Services
            updateServiceCard('openai', services.ai.openai);
            updateServiceCard('claude', services.ai.claude);
            updateServiceCard('orchestrator', services.ai.orchestrator);
            
            // Business Integrations
            updateServiceCard('vend', services.business.vend);
            updateServiceCard('deputy', services.business.deputy);
            updateServiceCard('xero', services.business.xero);
            
            // System Services
            updateServiceCard('queue', services.queue);
            updateServiceCard('telemetry', services.telemetry);
        }

        function updateServiceCard(serviceName, serviceData) {
            const card = document.getElementById(`service-${serviceName}`);
            const statusBadge = document.getElementById(`${serviceName}-status`);
            const detailsText = document.getElementById(`${serviceName}-details`);
            const latencyBadge = document.getElementById(`${serviceName}-latency`);

            if (!card || !statusBadge || !detailsText) return;

            // Update status
            statusBadge.textContent = serviceData.status.toUpperCase();
            statusBadge.className = `badge bg-${getStatusColor(serviceData.status)}`;

            // Update card class
            card.className = `service-card ${serviceData.status}`;

            // Update details
            let details = serviceData.details?.message || 'Service operational';
            if (serviceData.details?.error) {
                details = `Error: ${serviceData.details.error}`;
            }
            detailsText.textContent = details;

            // Update latency
            if (latencyBadge && serviceData.latency_ms !== null) {
                latencyBadge.textContent = `${serviceData.latency_ms}ms`;
                latencyBadge.className = `latency-badge badge ${getLatencyClass(serviceData.latency_ms)}`;
            }

            // Special handling for orchestrator jobs
            if (serviceName === 'orchestrator' && serviceData.jobs_pending !== undefined) {
                const jobsElement = document.getElementById('orchestrator-jobs');
                if (jobsElement) {
                    jobsElement.textContent = `${serviceData.jobs_pending} pending jobs`;
                }
            }

            // Special handling for queue jobs
            if (serviceName === 'queue' && serviceData.jobs_pending !== undefined) {
                const jobsElement = document.getElementById('queue-jobs');
                if (jobsElement) {
                    jobsElement.textContent = `${serviceData.jobs_pending} jobs pending`;
                }
            }

            // Special handling for telemetry events
            if (serviceName === 'telemetry' && serviceData.events_today !== undefined) {
                const eventsElement = document.getElementById('telemetry-events');
                if (eventsElement) {
                    eventsElement.textContent = `${serviceData.events_today} events today`;
                }
            }
        }

        function updateSystemMetrics(system) {
            document.getElementById('system-load').textContent = system.server_load ? system.server_load[0].toFixed(2) : '-';
            document.getElementById('disk-usage').textContent = system.disk_usage ? `${system.disk_usage.usage_percent}%` : '-';
            document.getElementById('php-version').textContent = system.php_version || '-';
            document.getElementById('system-uptime').textContent = formatUptime(system.uptime);
        }

        function getStatusColor(status) {
            switch (status) {
                case 'healthy': return 'success';
                case 'degraded': return 'warning';
                case 'down': return 'danger';
                case 'not_configured': return 'secondary';
                default: return 'secondary';
            }
        }

        function getLatencyClass(latency) {
            if (latency < 100) return 'bg-success text-white';
            if (latency < 500) return 'bg-warning text-dark';
            return 'bg-danger text-white';
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        function formatUptime(seconds) {
            if (!seconds) return '-';
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return `${hours}h ${minutes}m`;
        }

        function startAutoRefresh() {
            if (autoRefreshEnabled) {
                refreshInterval = setInterval(loadMonitorData, 30000); // 30 seconds
            }
        }

        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }

        function toggleAutoRefresh() {
            autoRefreshEnabled = !autoRefreshEnabled;
            const button = document.getElementById('auto-refresh');
            
            if (autoRefreshEnabled) {
                button.innerHTML = '<i class="fas fa-pause"></i> Auto Refresh ON';
                button.className = 'btn btn-success';
                startAutoRefresh();
            } else {
                button.innerHTML = '<i class="fas fa-play"></i> Auto Refresh OFF';
                button.className = 'btn btn-secondary';
                stopAutoRefresh();
            }
        }

        function showRefreshIndicator() {
            document.getElementById('refresh-indicator').style.display = 'block';
        }

        function hideRefreshIndicator() {
            document.getElementById('refresh-indicator').style.display = 'none';
        }

        function exportData() {
            fetch('/admin/monitor/api')
                .then(response => response.json())
                .then(data => {
                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `cis-monitor-${new Date().toISOString().split('T')[0]}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                });
        }
    </script>
</body>
</html>
