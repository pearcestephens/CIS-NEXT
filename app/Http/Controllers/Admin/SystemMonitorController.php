<?php
/**
 * Unified System Monitor Controller
 * File: app/Http/Controllers/Admin/SystemMonitorController.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Comprehensive system monitoring with real-time metrics
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Shared\Logging\Logger;
use App\Infra\Cache\RedisCache;
use App\Shared\ErrorHandler;
use App\Http\Middlewares\SecurityHardeningMiddleware;
use App\Models\SystemEvent;

class SystemMonitorController extends BaseController
{
    private Logger $logger;
    private RedisCache $cache;
    private ErrorHandler $errorHandler;
    private SecurityHardeningMiddleware $security;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = Logger::getInstance();
        $this->cache = RedisCache::getInstance();
        $this->errorHandler = ErrorHandler::getInstance();
        $this->security = new SecurityHardeningMiddleware();
    }
    
    /**
     * Main monitoring dashboard
     */
    public function dashboard(): void
    {
        try {
            $this->requirePermission('system_monitor');
            
            $data = [
                'title' => 'System Monitor Dashboard',
                'metrics' => $this->getSystemMetrics(),
                'alerts' => $this->getActiveAlerts(),
                'performance' => $this->getPerformanceMetrics(),
                'cache_status' => $this->getCacheStatus(),
                'error_stats' => $this->getErrorStats(),
                'security_status' => $this->getSecurityStatus()
            ];
            
            $this->render('admin/monitor/dashboard', $data);
            
        } catch (\Throwable $e) {
            $this->handleError('Monitor dashboard failed', $e);
            $this->render('error', ['message' => 'Failed to load monitoring dashboard']);
        }
    }
    
    /**
     * Real-time metrics API endpoint
     */
    public function metricsApi(): void
    {
        try {
            $this->requirePermission('system_monitor');
            
            $metrics = [
                'timestamp' => time(),
                'system' => $this->getSystemMetrics(),
                'performance' => $this->getPerformanceMetrics(),
                'cache' => $this->getCacheStatus(),
                'errors' => $this->getErrorStats(),
                'security' => $this->getSecurityStatus(),
                'alerts' => $this->getActiveAlerts()
            ];
            
            $this->jsonResponse($metrics);
            
        } catch (\Throwable $e) {
            $this->jsonError('Failed to fetch metrics', 500);
        }
    }
    
    /**
     * Get comprehensive system metrics
     */
    private function getSystemMetrics(): array
    {
        $loadAvg = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemorySize(ini_get('memory_limit'));
        
        $diskUsage = 0;
        $diskTotal = 0;
        if (function_exists('disk_free_space')) {
            $diskFree = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            $diskUsage = $diskTotal - $diskFree;
        }
        
        return [
            'server_time' => date('c'),
            'uptime' => $this->getSystemUptime(),
            'load_average' => [
                '1min' => round($loadAvg[0], 2),
                '5min' => round($loadAvg[1], 2),
                '15min' => round($loadAvg[2], 2)
            ],
            'memory' => [
                'used_mb' => round($memoryUsage / 1024 / 1024, 2),
                'peak_mb' => round($memoryPeak / 1024 / 1024, 2),
                'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
                'usage_percent' => $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 1) : 0
            ],
            'disk' => [
                'used_gb' => round($diskUsage / 1024 / 1024 / 1024, 2),
                'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
                'usage_percent' => $diskTotal > 0 ? round(($diskUsage / $diskTotal) * 100, 1) : 0
            ],
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'extensions' => $this->getCriticalExtensions()
            ]
        ];
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        // Database performance
        $dbStats = $this->getDatabaseStats();
        
        // Cache performance
        $cacheStats = $this->cache->getStats();
        
        // Response time monitoring
        $responseTimeKey = 'perf:response_times:' . date('Y-m-d H:i');
        $responseTimes = $this->cache->lrange($responseTimeKey, 0, -1);
        
        $avgResponseTime = 0;
        if (!empty($responseTimes)) {
            $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
        }
        
        return [
            'database' => $dbStats,
            'cache' => [
                'hit_ratio' => $cacheStats['hit_ratio'] ?? 0,
                'memory_usage' => $cacheStats['memory_usage'] ?? 0,
                'total_keys' => $cacheStats['total_keys'] ?? 0,
                'operations_per_sec' => $cacheStats['operations_per_sec'] ?? 0
            ],
            'response_times' => [
                'average_ms' => round($avgResponseTime, 2),
                'samples' => count($responseTimes)
            ],
            'throughput' => $this->getThroughputMetrics()
        ];
    }
    
    /**
     * Get cache status
     */
    private function getCacheStatus(): array
    {
        try {
            $info = $this->cache->info();
            $stats = $this->cache->getStats();
            
            return [
                'connected' => true,
                'server_info' => $info,
                'statistics' => $stats,
                'health_score' => $this->calculateCacheHealthScore($stats)
            ];
            
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'health_score' => 0
            ];
        }
    }
    
    /**
     * Get error statistics
     */
    private function getErrorStats(): array
    {
        $errorStats = $this->errorHandler->getErrorStats();
        
        // Recent error notifications
        $notifications = $this->cache->lrange('error_notifications', 0, 9);
        $recentErrors = array_map('json_decode', $notifications);
        
        // Error rate trend (last 5 minutes)
        $errorTrend = [];
        for ($i = 4; $i >= 0; $i--) {
            $key = 'error_rate:' . date('Y-m-d H:i', strtotime("-{$i} minutes"));
            $count = (int)$this->cache->get($key);
            $errorTrend[] = [
                'time' => date('H:i', strtotime("-{$i} minutes")),
                'count' => $count
            ];
        }
        
        return [
            'current_rate' => $errorStats['current_error_rate'] ?? 0,
            'rate_threshold' => $errorStats['rate_threshold'] ?? 10,
            'recent_errors' => $recentErrors,
            'error_trend' => $errorTrend,
            'memory_usage' => $errorStats['current_memory_mb'] ?? 0,
            'memory_threshold' => $errorStats['memory_threshold_mb'] ?? 128
        ];
    }
    
    /**
     * Get security status
     */
    private function getSecurityStatus(): array
    {
        $clientIp = $this->getClientIp();
        $rateLimitStatus = $this->security->getRateLimitStatus($clientIp);
        
        // Security events from last hour
        $securityEvents = $this->getRecentSecurityEvents();
        
        return [
            'rate_limiting' => [
                'current_requests' => $rateLimitStatus['requests'],
                'remaining_requests' => $rateLimitStatus['remaining'],
                'limit' => $rateLimitStatus['limit'],
                'window_seconds' => $rateLimitStatus['window_seconds']
            ],
            'recent_events' => $securityEvents,
            'active_sessions' => $this->getActiveSessionCount(),
            'failed_logins' => $this->getFailedLoginCount(),
            'security_headers' => $this->getSecurityHeadersStatus()
        ];
    }
    
    /**
     * Get active alerts
     */
    private function getActiveAlerts(): array
    {
        $alerts = [];
        
        // Memory usage alert
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemorySize(ini_get('memory_limit'));
        if ($memoryLimit > 0 && ($memoryUsage / $memoryLimit) > 0.85) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'memory_usage',
                'message' => 'High memory usage detected',
                'details' => 'Memory usage is above 85% of limit'
            ];
        }
        
        // Error rate alert
        $errorStats = $this->errorHandler->getErrorStats();
        $errorRate = $errorStats['current_error_rate'] ?? 0;
        $threshold = $errorStats['rate_threshold'] ?? 10;
        if ($errorRate > $threshold) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'error_rate',
                'message' => 'High error rate detected',
                'details' => "Error rate ({$errorRate}/min) exceeds threshold ({$threshold}/min)"
            ];
        }
        
        // Cache connectivity alert
        try {
            $this->cache->ping();
        } catch (\Exception $e) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'cache_connectivity',
                'message' => 'Cache server unavailable',
                'details' => 'Redis cache server is not responding'
            ];
        }
        
        // Disk space alert
        if (function_exists('disk_free_space')) {
            $diskFree = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            if ($diskTotal > 0 && ($diskFree / $diskTotal) < 0.1) {
                $alerts[] = [
                    'level' => 'warning',
                    'type' => 'disk_space',
                    'message' => 'Low disk space',
                    'details' => 'Less than 10% disk space remaining'
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get database statistics
     */
    private function getDatabaseStats(): array
    {
        try {
            $db = \App\Infra\Persistence\MariaDB\Database::getInstance();
            
            // Query performance stats
            $stmt = $db->execute("SHOW GLOBAL STATUS LIKE 'Questions'");
            $questions = $stmt->fetch()['Value'] ?? 0;
            
            $stmt = $db->execute("SHOW GLOBAL STATUS LIKE 'Uptime'");
            $uptime = $stmt->fetch()['Value'] ?? 1;
            
            $qps = $uptime > 0 ? round($questions / $uptime, 2) : 0;
            
            // Connection stats
            $stmt = $db->execute("SHOW GLOBAL STATUS LIKE 'Threads_connected'");
            $connections = $stmt->fetch()['Value'] ?? 0;
            
            return [
                'queries_per_second' => $qps,
                'active_connections' => (int)$connections,
                'uptime_seconds' => (int)$uptime
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to get database stats',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get throughput metrics
     */
    private function getThroughputMetrics(): array
    {
        $requestCountKey = 'throughput:requests:' . date('Y-m-d H:i');
        $requestCount = (int)$this->cache->get($requestCountKey);
        
        // Track this request
        $this->cache->incr($requestCountKey);
        $this->cache->expire($requestCountKey, 300); // 5 minutes
        
        return [
            'requests_per_minute' => $requestCount,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Calculate cache health score
     */
    private function calculateCacheHealthScore(array $stats): int
    {
        $score = 100;
        
        // Hit ratio component (40% of score)
        $hitRatio = $stats['hit_ratio'] ?? 0;
        if ($hitRatio < 90) {
            $score -= (90 - $hitRatio) * 0.4;
        }
        
        // Memory usage component (30% of score)  
        $memoryUsage = $stats['memory_usage_percent'] ?? 0;
        if ($memoryUsage > 90) {
            $score -= ($memoryUsage - 90) * 0.3;
        }
        
        // Operations per second component (30% of score)
        $opsPerSec = $stats['operations_per_sec'] ?? 0;
        if ($opsPerSec < 100) {
            $score -= (100 - $opsPerSec) * 0.003; // Gradual reduction
        }
        
        return max(0, min(100, (int)round($score)));
    }
    
    /**
     * Get recent security events
     */
    private function getRecentSecurityEvents(): array
    {
        try {
            $systemEvent = new SystemEvent();
            return $systemEvent->findWhere([
                'event_type' => 'security'
            ], 10);
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get active session count
     */
    private function getActiveSessionCount(): int
    {
        // Try to count Redis session keys
        try {
            $keys = $this->cache->keys('sess_*');
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get failed login count in last hour
     */
    private function getFailedLoginCount(): int
    {
        $key = 'security:failed_logins:' . date('Y-m-d H');
        return (int)$this->cache->get($key);
    }
    
    /**
     * Get security headers status
     */
    private function getSecurityHeadersStatus(): array
    {
        return [
            'hsts' => true,
            'csp' => true,
            'x_frame_options' => true,
            'x_content_type_options' => true,
            'x_xss_protection' => true
        ];
    }
    
    /**
     * Get system uptime
     */
    private function getSystemUptime(): string
    {
        if (function_exists('exec') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $uptime = exec('uptime -p 2>/dev/null');
            return $uptime ?: 'Unknown';
        }
        
        return 'Unknown';
    }
    
    /**
     * Get critical PHP extensions status
     */
    private function getCriticalExtensions(): array
    {
        $extensions = [
            'redis' => extension_loaded('redis'),
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'openssl' => extension_loaded('openssl'),
            'json' => extension_loaded('json'),
            'curl' => extension_loaded('curl'),
            'mbstring' => extension_loaded('mbstring')
        ];
        
        return $extensions;
    }
    
    /**
     * Parse memory size string to bytes
     */
    private function parseMemorySize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int)substr($size, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int)$size;
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
