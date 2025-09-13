<?php
/**
 * Background Monitor Job
 * File: tools/monitor_poll.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Background job to poll system health every 5 minutes
 */

require_once __DIR__ . '/../functions/config.php';
require_once __DIR__ . '/../app/Http/Controllers/MonitorController.php';

use App\Http\Controllers\MonitorController;
use App\Shared\Logging\Logger;

class MonitorPoll {
    
    private Logger $logger;
    private MonitorController $monitor;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->monitor = new MonitorController();
    }
    
    /**
     * Run monitoring poll cycle
     */
    public function run(): void {
        $startTime = microtime(true);
        
        $this->logger->info('Monitor poll started', [
            'timestamp' => date('c'),
            'pid' => getmypid()
        ]);
        
        try {
            // Get current system status
            $monitorData = $this->monitor->api();
            
            // Store health snapshots
            $this->storeHealthSnapshots($monitorData['services']);
            
            // Check for alerts
            $this->checkAlertConditions($monitorData['services']);
            
            // Clean up old monitor logs
            $this->cleanupOldLogs();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Monitor poll completed', [
                'duration_ms' => $duration,
                'services_checked' => $this->countServices($monitorData['services']),
                'timestamp' => date('c')
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Monitor poll failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Store health snapshots in database
     */
    private function storeHealthSnapshots(array $services): void {
        global $mysqli;
        
        if (!$mysqli) {
            return;
        }
        
        try {
            $stmt = $mysqli->prepare("
                INSERT INTO cis_monitor_log (service, status, latency_ms, metadata, checked_by) 
                VALUES (?, ?, ?, ?, 'monitor.poll')
            ");
            
            $this->storeServiceSnapshots($stmt, $services, '');
            
            $stmt->close();
            
        } catch (Exception $e) {
            $this->logger->error('Failed to store health snapshots', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Recursively store service snapshots
     */
    private function storeServiceSnapshots($stmt, array $services, string $prefix): void {
        foreach ($services as $serviceName => $serviceData) {
            $fullServiceName = $prefix ? "{$prefix}.{$serviceName}" : $serviceName;
            
            if (isset($serviceData['status'])) {
                // This is a service with status
                $metadata = json_encode([
                    'latency_ms' => $serviceData['latency_ms'] ?? null,
                    'details' => $serviceData['details'] ?? [],
                    'last_check' => $serviceData['last_check'] ?? date('c'),
                    'additional_metrics' => $this->extractAdditionalMetrics($serviceData)
                ]);
                
                $stmt->bind_param(
                    'ssis',
                    $fullServiceName,
                    $serviceData['status'],
                    $serviceData['latency_ms'] ?? null,
                    $metadata
                );
                
                $stmt->execute();
                
            } elseif (is_array($serviceData)) {
                // This is a service category, recurse into it
                $this->storeServiceSnapshots($stmt, $serviceData, $fullServiceName);
            }
        }
    }
    
    /**
     * Extract additional metrics from service data
     */
    private function extractAdditionalMetrics(array $serviceData): array {
        $metrics = [];
        
        // Extract relevant metrics based on service type
        $metricKeys = [
            'jobs_pending', 'jobs_failed', 'connections', 'queries',
            'memory_usage', 'events_today', 'api_key_configured',
            'secrets_configured', 'workers_active', 'error_count'
        ];
        
        foreach ($metricKeys as $key) {
            if (isset($serviceData[$key])) {
                $metrics[$key] = $serviceData[$key];
            }
        }
        
        return $metrics;
    }
    
    /**
     * Check alert conditions and trigger notifications
     */
    private function checkAlertConditions(array $services): void {
        global $mysqli;
        
        if (!$mysqli) {
            return;
        }
        
        try {
            // Get active alert rules
            $result = $mysqli->query("
                SELECT * FROM cis_alert_rules 
                WHERE is_active = 1 
                ORDER BY severity DESC
            ");
            
            while ($result && $rule = $result->fetch_assoc()) {
                $this->checkAlertRule($rule, $services);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to check alert conditions', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check individual alert rule
     */
    private function checkAlertRule(array $rule, array $services): void {
        $servicePattern = $rule['service_pattern'];
        $conditionType = $rule['condition_type'];
        $conditionValue = $rule['condition_value'];
        $cooldownSeconds = $rule['cooldown_minutes'] * 60;
        
        // Check if rule is in cooldown
        if ($rule['last_triggered_at']) {
            $lastTriggered = strtotime($rule['last_triggered_at']);
            if ((time() - $lastTriggered) < $cooldownSeconds) {
                return; // Still in cooldown
            }
        }
        
        // Find matching services
        $matchingServices = $this->findMatchingServices($services, $servicePattern);
        
        foreach ($matchingServices as $serviceName => $serviceData) {
            if ($this->evaluateCondition($serviceData, $conditionType, $conditionValue)) {
                $this->triggerAlert($rule, $serviceName, $serviceData);
                break; // Only trigger once per rule per poll
            }
        }
    }
    
    /**
     * Find services matching pattern
     */
    private function findMatchingServices(array $services, string $pattern): array {
        $matches = [];
        
        if ($pattern === '*') {
            // Match all services
            $this->flattenServices($services, $matches, '');
        } else {
            // Match specific services
            $patterns = explode(',', $pattern);
            $this->flattenServices($services, $allServices, '');
            
            foreach ($patterns as $p) {
                $p = trim($p);
                foreach ($allServices as $serviceName => $serviceData) {
                    if (strpos($serviceName, $p) !== false) {
                        $matches[$serviceName] = $serviceData;
                    }
                }
            }
        }
        
        return $matches;
    }
    
    /**
     * Flatten nested service structure
     */
    private function flattenServices(array $services, array &$result, string $prefix): void {
        foreach ($services as $name => $data) {
            $fullName = $prefix ? "{$prefix}.{$name}" : $name;
            
            if (isset($data['status'])) {
                $result[$fullName] = $data;
            } elseif (is_array($data)) {
                $this->flattenServices($data, $result, $fullName);
            }
        }
    }
    
    /**
     * Evaluate alert condition
     */
    private function evaluateCondition(array $serviceData, string $conditionType, string $conditionValue): bool {
        switch ($conditionType) {
            case 'status_change':
                return $serviceData['status'] === $conditionValue;
                
            case 'latency_threshold':
                $threshold = (float)$conditionValue;
                $latency = $serviceData['latency_ms'] ?? 0;
                return $latency > $threshold;
                
            case 'error_rate':
                $threshold = (float)$conditionValue;
                $errorCount = $serviceData['error_count'] ?? 0;
                return $errorCount > $threshold;
                
            case 'availability':
                return $serviceData['status'] !== 'healthy';
                
            default:
                return false;
        }
    }
    
    /**
     * Trigger alert notification
     */
    private function triggerAlert(array $rule, string $serviceName, array $serviceData): void {
        global $mysqli;
        
        $this->logger->warning('Alert triggered', [
            'rule_name' => $rule['rule_name'],
            'service' => $serviceName,
            'condition' => $rule['condition_type'],
            'value' => $rule['condition_value'],
            'service_status' => $serviceData['status'],
            'service_latency' => $serviceData['latency_ms'] ?? null
        ]);
        
        // Update last triggered time
        if ($mysqli) {
            $stmt = $mysqli->prepare("
                UPDATE cis_alert_rules 
                SET last_triggered_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param('i', $rule['id']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Send notifications (placeholder for email/webhook integrations)
        $this->sendAlertNotifications($rule, $serviceName, $serviceData);
    }
    
    /**
     * Send alert notifications
     */
    private function sendAlertNotifications(array $rule, string $serviceName, array $serviceData): void {
        $channels = json_decode($rule['notification_channels'], true) ?: [];
        
        $alertData = [
            'rule_name' => $rule['rule_name'],
            'service' => $serviceName,
            'severity' => $rule['severity'],
            'status' => $serviceData['status'],
            'latency_ms' => $serviceData['latency_ms'] ?? null,
            'timestamp' => date('c'),
            'details' => $serviceData['details'] ?? []
        ];
        
        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    $this->sendEmailAlert($alertData);
                    break;
                case 'webhook':
                    $this->sendWebhookAlert($alertData);
                    break;
                case 'slack':
                    $this->sendSlackAlert($alertData);
                    break;
            }
        }
    }
    
    /**
     * Send email alert (placeholder)
     */
    private function sendEmailAlert(array $alertData): void {
        // Placeholder for email integration
        $this->logger->info('Email alert would be sent', $alertData);
    }
    
    /**
     * Send webhook alert (placeholder)
     */
    private function sendWebhookAlert(array $alertData): void {
        // Placeholder for webhook integration
        $this->logger->info('Webhook alert would be sent', $alertData);
    }
    
    /**
     * Send Slack alert (placeholder)
     */
    private function sendSlackAlert(array $alertData): void {
        // Placeholder for Slack integration
        $this->logger->info('Slack alert would be sent', $alertData);
    }
    
    /**
     * Clean up old monitor logs
     */
    private function cleanupOldLogs(): void {
        global $mysqli;
        
        if (!$mysqli) {
            return;
        }
        
        try {
            // Keep logs for 7 days
            $result = $mysqli->query("
                DELETE FROM cis_monitor_log 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            
            if ($result) {
                $deletedRows = $mysqli->affected_rows;
                if ($deletedRows > 0) {
                    $this->logger->info('Cleaned up old monitor logs', [
                        'deleted_rows' => $deletedRows
                    ]);
                }
            }
            
        } catch (Exception $e) {
            $this->logger->warning('Failed to cleanup old logs', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Count total services
     */
    private function countServices(array $services): int {
        $count = 0;
        foreach ($services as $service) {
            if (isset($service['status'])) {
                $count++;
            } elseif (is_array($service)) {
                $count += $this->countServices($service);
            }
        }
        return $count;
    }
}

// Execute monitor poll if run directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $monitor = new MonitorPoll();
    $monitor->run();
}
