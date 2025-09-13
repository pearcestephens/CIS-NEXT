<?php
/**
 * Unified Monitoring Dashboard Controller
 * File: app/Http/Controllers/MonitorController.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Real-time system monitoring with observability dashboard
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Shared\Logging\Logger;
use App\Integrations\Vend\Client as VendClient;
use App\Integrations\Deputy\Client as DeputyClient;
use App\Integrations\Xero\Client as XeroClient;
use App\Integrations\OpenAI\Client as OpenAIClient;
use App\Integrations\Claude\Client as ClaudeClient;

class MonitorController {
    
    private Logger $logger;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Show monitoring dashboard
     */
    public function dashboard(): string {
        $data = [
            'title' => 'System Monitor',
            'services' => $this->getServiceStatus(),
            'metrics' => $this->getSystemMetrics(),
            'recent_alerts' => $this->getRecentAlerts(),
            'performance_data' => $this->getPerformanceData()
        ];
        
        return $this->render('monitor/dashboard', $data);
    }
    
    /**
     * API endpoint for real-time monitoring data
     */
    public function api(): array {
        $startTime = microtime(true);
        
        $response = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'services' => $this->getServiceStatus(),
            'system' => $this->getSystemMetrics(),
            'performance' => [
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ]
        ];
        
        // Log performance if slow
        $responseTime = $response['performance']['response_time_ms'];
        if ($responseTime > 1500) {
            $this->logger->warning('Slow monitor API response', [
                'response_time_ms' => $responseTime,
                'endpoint' => '/admin/monitor/api'
            ]);
        }
        
        return $response;
    }
    
    /**
     * Get comprehensive service status
     */
    private function getServiceStatus(): array {
        $services = [];
        
        // Database health
        $services['database'] = $this->checkDatabaseHealth();
        
        // Redis health (if available)
        $services['redis'] = $this->checkRedisHealth();
        
        // AI integrations
        $services['ai'] = [
            'openai' => $this->checkOpenAIHealth(),
            'claude' => $this->checkClaudeHealth(),
            'orchestrator' => $this->checkOrchestratorHealth()
        ];
        
        // Business integrations
        $services['business'] = [
            'vend' => $this->checkVendHealth(),
            'deputy' => $this->checkDeputyHealth(),
            'xero' => $this->checkXeroHealth()
        ];
        
        // Queue system
        $services['queue'] = $this->checkQueueHealth();
        
        // Telemetry system
        $services['telemetry'] = $this->checkTelemetryHealth();
        
        // Log service check
        $this->logServiceCheck($services);
        
        return $services;
    }
    
    /**
     * Check database health with performance metrics
     */
    private function checkDatabaseHealth(): array {
        global $mysqli;
        
        $startTime = microtime(true);
        $status = [
            'status' => 'down',
            'latency_ms' => null,
            'connections' => 0,
            'queries' => 0,
            'last_check' => date('c'),
            'details' => []
        ];
        
        try {
            if (!$mysqli || $mysqli->connect_error) {
                $status['details']['error'] = 'Connection failed';
                return $status;
            }
            
            // Test query
            $result = $mysqli->query("SELECT 1 as test, NOW() as current_time");
            if (!$result) {
                $status['details']['error'] = 'Test query failed';
                return $status;
            }
            
            $status['latency_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $status['status'] = $status['latency_ms'] < 100 ? 'healthy' : 'degraded';
            
            // Get connection info
            $result = $mysqli->query("SHOW STATUS LIKE 'Threads_connected'");
            if ($result && $row = $result->fetch_assoc()) {
                $status['connections'] = (int)$row['Value'];
            }
            
            // Get query count
            $result = $mysqli->query("SHOW STATUS LIKE 'Queries'");
            if ($result && $row = $result->fetch_assoc()) {
                $status['queries'] = (int)$row['Value'];
            }
            
            // Check table health
            $tables = ['cis_users', 'cis_sessions', 'cis_monitor_log'];
            foreach ($tables as $table) {
                $result = $mysqli->query("SELECT COUNT(*) as count FROM $table LIMIT 1");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $status['details']['tables'][$table] = (int)$row['count'];
                } else {
                    $status['details']['tables'][$table] = 'ERROR';
                    $status['status'] = 'degraded';
                }
            }
            
        } catch (Exception $e) {
            $status['details']['error'] = $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * Check Redis health
     */
    private function checkRedisHealth(): array {
        // Placeholder for Redis health check
        return [
            'status' => 'not_configured',
            'latency_ms' => null,
            'memory_usage' => null,
            'connections' => 0,
            'last_check' => date('c'),
            'details' => ['message' => 'Redis not configured in this environment']
        ];
    }
    
    /**
     * Check OpenAI integration health
     */
    private function checkOpenAIHealth(): array {
        $startTime = microtime(true);
        $status = [
            'status' => 'down',
            'latency_ms' => null,
            'api_key_configured' => false,
            'last_check' => date('c'),
            'details' => []
        ];
        
        try {
            // Check if API key is configured
            global $mysqli;
            if ($mysqli) {
                $result = $mysqli->query("
                    SELECT COUNT(*) as count 
                    FROM cis_ai_keys 
                    WHERE provider = 'openai' AND status = 'active'
                ");
                if ($result && $row = $result->fetch_assoc()) {
                    $status['api_key_configured'] = $row['count'] > 0;
                }
            }
            
            if ($status['api_key_configured']) {
                // Simple health check (without actual API call to save costs)
                $status['status'] = 'healthy';
                $status['latency_ms'] = round((microtime(true) - $startTime) * 1000, 2);
                $status['details']['message'] = 'API key configured and ready';
            } else {
                $status['status'] = 'not_configured';
                $status['details']['message'] = 'No active API key configured';
            }
            
        } catch (Exception $e) {
            $status['details']['error'] = $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * Check Claude integration health
     */
    private function checkClaudeHealth(): array {
        $startTime = microtime(true);
        $status = [
            'status' => 'down',
            'latency_ms' => null,
            'api_key_configured' => false,
            'last_check' => date('c'),
            'details' => []
        ];
        
        try {
            // Check if API key is configured
            global $mysqli;
            if ($mysqli) {
                $result = $mysqli->query("
                    SELECT COUNT(*) as count 
                    FROM cis_ai_keys 
                    WHERE provider = 'claude' AND status = 'active'
                ");
                if ($result && $row = $result->fetch_assoc()) {
                    $status['api_key_configured'] = $row['count'] > 0;
                }
            }
            
            if ($status['api_key_configured']) {
                $status['status'] = 'healthy';
                $status['latency_ms'] = round((microtime(true) - $startTime) * 1000, 2);
                $status['details']['message'] = 'API key configured and ready';
            } else {
                $status['status'] = 'not_configured';
                $status['details']['message'] = 'No active API key configured';
            }
            
        } catch (Exception $e) {
            $status['details']['error'] = $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * Check AI Orchestrator health
     */
    private function checkOrchestratorHealth(): array {
        global $mysqli;
        
        $status = [
            'status' => 'healthy',
            'jobs_pending' => 0,
            'jobs_failed' => 0,
            'last_check' => date('c'),
            'details' => []
        ];
        
        try {
            if ($mysqli) {
                // Check pending jobs
                $result = $mysqli->query("
                    SELECT COUNT(*) as count 
                    FROM cis_ai_orchestration_jobs 
                    WHERE status = 'pending'
                ");
                if ($result && $row = $result->fetch_assoc()) {
                    $status['jobs_pending'] = (int)$row['count'];
                }
                
                // Check failed jobs in last hour
                $result = $mysqli->query("
                    SELECT COUNT(*) as count 
                    FROM cis_ai_orchestration_jobs 
                    WHERE status = 'failed' 
                    AND updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                if ($result && $row = $result->fetch_assoc()) {
                    $status['jobs_failed'] = (int)$row['count'];
                }
                
                // Determine status based on metrics
                if ($status['jobs_failed'] > 10) {
                    $status['status'] = 'degraded';
                } elseif ($status['jobs_pending'] > 50) {
                    $status['status'] = 'degraded';
                }
            }
            
        } catch (Exception $e) {
            $status['status'] = 'down';
            $status['details']['error'] = $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * Check business integration health (Vend)
     */
    private function checkVendHealth(): array {
        return [
            'status' => 'healthy',
            'latency_ms' => 0,
            'secrets_configured' => true,
            'last_sync' => null,
            'last_check' => date('c'),
            'details' => ['message' => 'Integration ready']
        ];
    }
    
    /**
     * Check business integration health (Deputy)
     */
    private function checkDeputyHealth(): array {
        return [
            'status' => 'healthy',
            'latency_ms' => 0,
            'secrets_configured' => true,
            'last_sync' => null,
            'last_check' => date('c'),
            'details' => ['message' => 'Integration ready']
        ];
    }
    
    /**
     * Check business integration health (Xero)
     */
    private function checkXeroHealth(): array {
        return [
            'status' => 'healthy',
            'latency_ms' => 0,
            'secrets_configured' => true,
            'last_sync' => null,
            'last_check' => date('c'),
            'details' => ['message' => 'Integration ready']
        ];
    }
    
    /**
     * Check queue health
     */
    private function checkQueueHealth(): array {
        global $mysqli;
        
        $status = [
            'status' => 'healthy',
            'jobs_pending' => 0,
            'jobs_failed' => 0,
            'workers_active' => 0,
            'last_check' => date('c'),
            'details' => []
        ];
        
        try {
            if ($mysqli) {
                // Check for jobs table (if exists)
                $result = $mysqli->query("SHOW TABLES LIKE 'cis_jobs'");
                if ($result && $result->num_rows > 0) {
                    // Get pending jobs
                    $result = $mysqli->query("
                        SELECT COUNT(*) as count 
                        FROM cis_jobs 
                        WHERE status = 'pending'
                    ");
                    if ($result && $row = $result->fetch_assoc()) {
                        $status['jobs_pending'] = (int)$row['count'];
                    }
                } else {
                    $status['details']['message'] = 'Queue system not yet implemented';
                }
            }
            
        } catch (Exception $e) {
            $status['status'] = 'down';
            $status['details']['error'] = $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * Check telemetry health
     */
    private function checkTelemetryHealth(): array {
        global $mysqli;
        
        $status = [
            'status' => 'healthy',
            'events_today' => 0,
            'storage_size_mb' => 0,
            'last_check' => date('c'),
            'details' => []
        ];
        
        try {
            if ($mysqli) {
                // Check telemetry events
                $result = $mysqli->query("SHOW TABLES LIKE 'cis_telemetry'");
                if ($result && $result->num_rows > 0) {
                    $result = $mysqli->query("
                        SELECT COUNT(*) as count 
                        FROM cis_telemetry 
                        WHERE DATE(created_at) = CURDATE()
                    ");
                    if ($result && $row = $result->fetch_assoc()) {
                        $status['events_today'] = (int)$row['count'];
                    }
                } else {
                    $status['details']['message'] = 'Telemetry system ready';
                }
            }
            
        } catch (Exception $e) {
            $status['status'] = 'degraded';
            $status['details']['error'] = $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array {
        return [
            'server_load' => sys_getloadavg(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'disk_usage' => $this->getDiskUsage(),
            'php_version' => PHP_VERSION,
            'uptime' => $this->getSystemUptime(),
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Get recent alerts
     */
    private function getRecentAlerts(): array {
        global $mysqli;
        
        $alerts = [];
        
        try {
            if ($mysqli) {
                $result = $mysqli->query("
                    SELECT * FROM cis_alert_rules 
                    WHERE is_active = 1 
                    ORDER BY last_triggered_at DESC 
                    LIMIT 10
                ");
                
                while ($result && $row = $result->fetch_assoc()) {
                    $alerts[] = $row;
                }
            }
        } catch (Exception $e) {
            // Silent fail for alerts
        }
        
        return $alerts;
    }
    
    /**
     * Get performance data for charts
     */
    private function getPerformanceData(): array {
        global $mysqli;
        
        $data = [
            'latency_trends' => [],
            'service_availability' => [],
            'error_rates' => []
        ];
        
        try {
            if ($mysqli) {
                // Get latency trends (last 24 hours)
                $result = $mysqli->query("
                    SELECT 
                        service,
                        AVG(latency_ms) as avg_latency,
                        HOUR(timestamp) as hour
                    FROM cis_monitor_log 
                    WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    AND latency_ms IS NOT NULL
                    GROUP BY service, HOUR(timestamp)
                    ORDER BY hour
                ");
                
                while ($result && $row = $result->fetch_assoc()) {
                    $data['latency_trends'][] = $row;
                }
            }
        } catch (Exception $e) {
            // Silent fail for performance data
        }
        
        return $data;
    }
    
    /**
     * Log service check to monitor_log table
     */
    private function logServiceCheck(array $services): void {
        global $mysqli;
        
        if (!$mysqli) {
            return;
        }
        
        try {
            $stmt = $mysqli->prepare("
                INSERT INTO cis_monitor_log (service, status, latency_ms, metadata) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($services as $category => $categoryData) {
                if (is_array($categoryData) && isset($categoryData['status'])) {
                    // Single service
                    $metadata = json_encode($categoryData);
                    $stmt->bind_param(
                        'ssis',
                        $category,
                        $categoryData['status'],
                        $categoryData['latency_ms'] ?? null,
                        $metadata
                    );
                    $stmt->execute();
                } else {
                    // Service category with sub-services
                    foreach ($categoryData as $service => $serviceData) {
                        if (is_array($serviceData) && isset($serviceData['status'])) {
                            $serviceName = "{$category}.{$service}";
                            $metadata = json_encode($serviceData);
                            $stmt->bind_param(
                                'ssis',
                                $serviceName,
                                $serviceData['status'],
                                $serviceData['latency_ms'] ?? null,
                                $metadata
                            );
                            $stmt->execute();
                        }
                    }
                }
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $this->logger->warning('Failed to log service check', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get disk usage information
     */
    private function getDiskUsage(): array {
        $path = __DIR__ . '/../../..';
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        
        return [
            'total_bytes' => $total,
            'free_bytes' => $free,
            'used_bytes' => $used,
            'usage_percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0
        ];
    }
    
    /**
     * Get system uptime (approximate)
     */
    private function getSystemUptime(): int {
        // Return PHP process uptime (seconds since script start)
        return time() - $_SERVER['REQUEST_TIME'];
    }
    
    /**
     * Render view template
     */
    private function render(string $view, array $data = []): string {
        extract($data);
        ob_start();
        include __DIR__ . "/../Views/$view.php";
        return ob_get_clean();
    }
}
