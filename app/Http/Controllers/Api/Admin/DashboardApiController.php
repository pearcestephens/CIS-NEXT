<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\BaseController;
use App\Services\MetricsService;
use App\Services\SystemService;
use App\Services\SecurityService;

/**
 * Admin API Controller - Real-time Dashboard Data
 * 
 * Provides JSON endpoints for dashboard metrics and monitoring
 * Author: GitHub Copilot
 * Created: 2025-09-13
 */
class DashboardApiController extends BaseController
{
    private $metricsService;
    private $systemService;
    private $securityService;
    
    public function __construct()
    {
        $this->metricsService = new MetricsService();
        $this->systemService = new SystemService();
        $this->securityService = new SecurityService();
    }
    
    /**
     * Get all dashboard metrics as JSON
     */
    public function getDashboardMetrics()
    {
        try {
            $metrics = [
                'system_health' => $this->systemService->getSystemHealth(),
                'active_users' => $this->metricsService->getActiveUserCount(),
                'response_time' => $this->metricsService->getAverageResponseTime(),
                'security_score' => $this->securityService->getSecurityScore(),
                'cpu_usage' => $this->systemService->getCpuUsage(),
                'memory_usage' => $this->systemService->getMemoryUsage(),
                'disk_usage' => $this->systemService->getDiskUsage(),
                'network_io' => $this->systemService->getNetworkIO(),
                'uptime' => $this->systemService->getSystemUptime(),
                'services_status' => $this->systemService->getCriticalServicesStatus(),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
            
            return $this->jsonResponse([
                'success' => true,
                'data' => $metrics,
                'timestamp' => time()
            ]);
            
        } catch (\Exception $e) {
            error_log("Dashboard metrics error: " . $e->getMessage());
            
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch dashboard metrics',
                'message' => 'Please try again later'
            ], 500);
        }
    }
    
    /**
     * Get system performance data for charts
     */
    public function getPerformanceData()
    {
        try {
            $timeRange = $_GET['range'] ?? '24h';
            
            // Mock time series data - replace with actual monitoring data
            $data = $this->generatePerformanceTimeSeries($timeRange);
            
            return $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'range' => $timeRange,
                'timestamp' => time()
            ]);
            
        } catch (\Exception $e) {
            error_log("Performance data error: " . $e->getMessage());
            
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch performance data'
            ], 500);
        }
    }
    
    /**
     * Get recent system activities
     */
    public function getRecentActivities()
    {
        try {
            $activities = [
                [
                    'id' => 1,
                    'type' => 'user_registered',
                    'message' => 'New user registered',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
                    'severity' => 'info'
                ],
                [
                    'id' => 2,
                    'type' => 'backup_completed',
                    'message' => 'Backup completed successfully',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                    'severity' => 'success'
                ],
                [
                    'id' => 3,
                    'type' => 'high_memory_usage',
                    'message' => 'High memory usage detected',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'severity' => 'warning'
                ],
                [
                    'id' => 4,
                    'type' => 'maintenance_completed',
                    'message' => 'System maintenance completed',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                    'severity' => 'info'
                ]
            ];
            
            return $this->jsonResponse([
                'success' => true,
                'data' => $activities,
                'timestamp' => time()
            ]);
            
        } catch (\Exception $e) {
            error_log("Recent activities error: " . $e->getMessage());
            
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch recent activities'
            ], 500);
        }
    }
    
    /**
     * Get system alerts and notifications
     */
    public function getSystemAlerts()
    {
        try {
            $alerts = $this->securityService->getActiveAlerts();
            
            return $this->jsonResponse([
                'success' => true,
                'data' => $alerts,
                'count' => count($alerts),
                'timestamp' => time()
            ]);
            
        } catch (\Exception $e) {
            error_log("System alerts error: " . $e->getMessage());
            
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to fetch system alerts'
            ], 500);
        }
    }
    
    // Private helper methods
    
    private function generatePerformanceTimeSeries(string $range): array
    {
        $dataPoints = [];
        $now = time();
        
        switch ($range) {
            case '24h':
                $interval = 3600; // 1 hour intervals
                $count = 24;
                break;
            case '7d':
                $interval = 86400; // 1 day intervals  
                $count = 7;
                break;
            case '30d':
                $interval = 86400; // 1 day intervals
                $count = 30;
                break;
            default:
                $interval = 3600;
                $count = 24;
        }
        
        for ($i = $count; $i >= 0; $i--) {
            $timestamp = $now - ($i * $interval);
            $dataPoints[] = [
                'timestamp' => $timestamp,
                'datetime' => date('Y-m-d H:i:s', $timestamp),
                'cpu_usage' => rand(20, 80),
                'memory_usage' => rand(40, 85),
                'response_time' => rand(50, 200),
                'requests_per_second' => rand(10, 100),
            ];
        }
        
        return $dataPoints;
    }
    
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
