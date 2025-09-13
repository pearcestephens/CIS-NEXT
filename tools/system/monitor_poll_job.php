<?php
/**
 * Monitor Poll Job
 * File: tools/system/monitor_poll_job.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Lightweight polling job for system health checks and metrics collection
 */

declare(strict_types=1);

require_once __DIR__ . '/../../functions/config.php';
require_once __DIR__ . '/../../app/Shared/Bootstrap.php';

use App\Shared\Bootstrap;
use App\Shared\Logging\Logger;
use App\Infra\Cache\RedisCache;

class MonitorPollJob
{
    private Logger $logger;
    private RedisCache $cache;
    private array $config;
    private string $hostname;
    private array $endpoints;
    
    public function __construct()
    {
        // Initialize application
        Bootstrap::init(__DIR__ . '/../..');
        
        $this->logger = Logger::getInstance();
        $this->cache = RedisCache::getInstance();
        $this->hostname = gethostname() ?: 'unknown';
        
        $this->config = [
            'poll_interval' => 30, // 30 seconds
            'timeout' => 5, // 5 second timeout
            'max_response_time' => 2000, // 2 seconds
            'health_check_urls' => [
                'main' => 'https://staff.vapeshed.co.nz/_health',
                'api' => 'https://staff.vapeshed.co.nz/api/health',
                'cache' => 'https://staff.vapeshed.co.nz/admin/cache/status'
            ],
            'metrics_retention' => 3600 // 1 hour
        ];
        
        $this->endpoints = [];
    }
    
    /**
     * Run single poll cycle
     */
    public function poll(): array
    {
        $pollStart = microtime(true);
        
        $results = [
            'timestamp' => time(),
            'hostname' => $this->hostname,
            'poll_id' => uniqid('poll_', true),
            'health_checks' => [],
            'system_metrics' => [],
            'performance_metrics' => [],
            'alerts' => []
        ];
        
        try {
            // Health checks
            $results['health_checks'] = $this->performHealthChecks();
            
            // System metrics
            $results['system_metrics'] = $this->collectQuickMetrics();
            
            // Performance metrics
            $results['performance_metrics'] = $this->collectPerformanceMetrics();
            
            // Generate alerts
            $results['alerts'] = $this->generateAlerts($results);
            
            $results['poll_duration'] = round((microtime(true) - $pollStart) * 1000, 2);
            $results['success'] = true;
            
            // Store results
            $this->storeResults($results);
            
            $this->logger->debug('Poll completed', [
                'component' => 'monitor_poll',
                'poll_id' => $results['poll_id'],
                'duration_ms' => $results['poll_duration'],
                'health_status' => $this->calculateOverallHealth($results)
            ]);
            
        } catch (\Throwable $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            $results['poll_duration'] = round((microtime(true) - $pollStart) * 1000, 2);
            
            $this->logger->error('Poll failed', [
                'component' => 'monitor_poll',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'duration_ms' => $results['poll_duration']
            ]);
        }
        
        return $results;
    }
    
    /**
     * Perform health checks on configured endpoints
     */
    private function performHealthChecks(): array
    {
        $healthChecks = [];
        
        foreach ($this->config['health_check_urls'] as $name => $url) {
            $checkStart = microtime(true);
            
            $healthCheck = [
                'name' => $name,
                'url' => $url,
                'status' => 'unknown',
                'response_time' => 0,
                'http_code' => 0,
                'response_size' => 0,
                'error' => null
            ];
            
            try {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => $this->config['timeout'],
                        'ignore_errors' => true,
                        'user_agent' => 'CIS Monitor Poll Job/1.0'
                    ]
                ]);
                
                $response = file_get_contents($url, false, $context);
                $responseTime = round((microtime(true) - $checkStart) * 1000, 2);
                
                // Parse HTTP response code
                $httpCode = 0;
                if (isset($http_response_header)) {
                    foreach ($http_response_header as $header) {
                        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                            $httpCode = (int)$matches[1];
                            break;
                        }
                    }
                }
                
                $healthCheck['response_time'] = $responseTime;
                $healthCheck['http_code'] = $httpCode;
                $healthCheck['response_size'] = strlen($response ?: '');
                
                // Determine status
                if ($httpCode >= 200 && $httpCode < 300) {
                    $healthCheck['status'] = 'healthy';
                } elseif ($httpCode >= 300 && $httpCode < 400) {
                    $healthCheck['status'] = 'warning';
                } else {
                    $healthCheck['status'] = 'unhealthy';
                }
                
                // Check response time threshold
                if ($responseTime > $this->config['max_response_time']) {
                    $healthCheck['status'] = 'warning';
                }
                
                // Parse JSON response for additional health info
                if ($response && $name === 'main') {
                    $healthData = json_decode($response, true);
                    if ($healthData && isset($healthData['ok'])) {
                        $healthCheck['health_data'] = $healthData;
                    }
                }
                
            } catch (\Throwable $e) {
                $healthCheck['status'] = 'unhealthy';
                $healthCheck['error'] = $e->getMessage();
                $healthCheck['response_time'] = round((microtime(true) - $checkStart) * 1000, 2);
            }
            
            $healthChecks[] = $healthCheck;
        }
        
        return $healthChecks;
    }
    
    /**
     * Collect quick system metrics
     */
    private function collectQuickMetrics(): array
    {
        $metrics = [
            'timestamp' => time(),
            'memory' => [],
            'load' => [],
            'connections' => []
        ];
        
        // Memory usage
        $metrics['memory'] = [
            'used_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit_mb' => round($this->parseMemorySize(ini_get('memory_limit')) / 1024 / 1024, 2)
        ];
        
        // Load average
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $metrics['load'] = [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2)
            ];
        }
        
        // Redis connection status
        try {
            $this->cache->ping();
            $info = $this->cache->info();
            
            $metrics['connections']['redis'] = [
                'status' => 'connected',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory_mb' => round(($info['used_memory'] ?? 0) / 1024 / 1024, 2)
            ];
            
        } catch (\Exception $e) {
            $metrics['connections']['redis'] = [
                'status' => 'disconnected',
                'error' => $e->getMessage()
            ];
        }
        
        // Database connection status
        try {
            $db = \App\Infra\Persistence\MariaDB\Database::getInstance();
            $stmt = $db->execute('SELECT 1');
            
            $metrics['connections']['database'] = [
                'status' => 'connected',
                'test_query' => $stmt->fetchColumn() == 1
            ];
            
        } catch (\Exception $e) {
            $metrics['connections']['database'] = [
                'status' => 'disconnected',
                'error' => $e->getMessage()
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Collect performance metrics
     */
    private function collectPerformanceMetrics(): array
    {
        $metrics = [];
        
        // Cache performance
        try {
            $cacheStats = $this->cache->getStats();
            $metrics['cache'] = [
                'hit_ratio' => $cacheStats['hit_ratio'] ?? 0,
                'operations_per_sec' => $cacheStats['operations_per_sec'] ?? 0,
                'memory_usage_percent' => $cacheStats['memory_usage_percent'] ?? 0
            ];
        } catch (\Exception $e) {
            $metrics['cache'] = ['error' => $e->getMessage()];
        }
        
        // Request rate
        $requestKey = 'poll:requests:' . date('Y-m-d H:i');
        $requestCount = (int)$this->cache->get($requestKey);
        $this->cache->incr($requestKey);
        $this->cache->expire($requestKey, 300);
        
        $metrics['requests'] = [
            'per_minute' => $requestCount,
            'timestamp' => date('c')
        ];
        
        // Error rate
        $errorKey = 'poll:errors:' . date('Y-m-d H:i');
        $errorCount = (int)$this->cache->get($errorKey);
        
        $metrics['errors'] = [
            'per_minute' => $errorCount,
            'timestamp' => date('c')
        ];
        
        return $metrics;
    }
    
    /**
     * Generate alerts based on poll results
     */
    private function generateAlerts(array $results): array
    {
        $alerts = [];
        
        // Health check alerts
        foreach ($results['health_checks'] as $check) {
            if ($check['status'] === 'unhealthy') {
                $alerts[] = [
                    'type' => 'health_check_failed',
                    'severity' => 'critical',
                    'message' => "Health check failed: {$check['name']}",
                    'details' => [
                        'url' => $check['url'],
                        'http_code' => $check['http_code'],
                        'error' => $check['error']
                    ]
                ];
            } elseif ($check['status'] === 'warning') {
                $alerts[] = [
                    'type' => 'health_check_slow',
                    'severity' => 'warning',
                    'message' => "Slow response from: {$check['name']}",
                    'details' => [
                        'url' => $check['url'],
                        'response_time' => $check['response_time'],
                        'threshold' => $this->config['max_response_time']
                    ]
                ];
            }
        }
        
        // System metrics alerts
        $metrics = $results['system_metrics'];
        
        // High memory usage
        if (isset($metrics['memory']['limit_mb']) && $metrics['memory']['limit_mb'] > 0) {
            $memoryPercent = ($metrics['memory']['used_mb'] / $metrics['memory']['limit_mb']) * 100;
            if ($memoryPercent > 85) {
                $alerts[] = [
                    'type' => 'high_memory_usage',
                    'severity' => 'warning',
                    'message' => 'High memory usage detected',
                    'details' => [
                        'usage_percent' => round($memoryPercent, 1),
                        'used_mb' => $metrics['memory']['used_mb'],
                        'limit_mb' => $metrics['memory']['limit_mb']
                    ]
                ];
            }
        }
        
        // High load average
        if (isset($metrics['load']['1min']) && $metrics['load']['1min'] > 5.0) {
            $alerts[] = [
                'type' => 'high_load_average',
                'severity' => 'warning',
                'message' => 'High system load detected',
                'details' => [
                    'load_1min' => $metrics['load']['1min'],
                    'threshold' => 5.0
                ]
            ];
        }
        
        // Connection failures
        if (isset($metrics['connections']['redis']['status']) && 
            $metrics['connections']['redis']['status'] === 'disconnected') {
            
            $alerts[] = [
                'type' => 'redis_connection_failed',
                'severity' => 'critical',
                'message' => 'Redis connection failed',
                'details' => ['error' => $metrics['connections']['redis']['error'] ?? 'Unknown']
            ];
        }
        
        if (isset($metrics['connections']['database']['status']) && 
            $metrics['connections']['database']['status'] === 'disconnected') {
            
            $alerts[] = [
                'type' => 'database_connection_failed',
                'severity' => 'critical',
                'message' => 'Database connection failed',
                'details' => ['error' => $metrics['connections']['database']['error'] ?? 'Unknown']
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Store poll results
     */
    private function storeResults(array $results): void
    {
        // Store latest results
        $this->cache->set('poll:latest', json_encode($results), $this->config['metrics_retention']);
        
        // Store historical data
        $historyKey = 'poll:history:' . date('Y-m-d H:i:s');
        $this->cache->set($historyKey, json_encode($results), $this->config['metrics_retention']);
        
        // Maintain history list
        $this->cache->lpush('poll:history_list', $historyKey);
        $this->cache->ltrim('poll:history_list', 0, 119); // Keep last 120 polls (1 hour at 30s intervals)
        
        // Store alerts separately for quick access
        if (!empty($results['alerts'])) {
            foreach ($results['alerts'] as $alert) {
                $alertKey = 'poll:alert:' . uniqid();
                $alertData = array_merge($alert, [
                    'timestamp' => $results['timestamp'],
                    'poll_id' => $results['poll_id'],
                    'hostname' => $results['hostname']
                ]);
                
                $this->cache->set($alertKey, json_encode($alertData), 3600); // 1 hour
                $this->cache->lpush('poll:alerts', json_encode($alertData));
                $this->cache->ltrim('poll:alerts', 0, 99); // Keep last 100 alerts
            }
        }
        
        // Update health score
        $healthScore = $this->calculateOverallHealth($results);
        $this->cache->set('poll:health_score', $healthScore, $this->config['metrics_retention']);
        
        // Store performance trend
        $performanceKey = 'poll:performance:' . date('Y-m-d H:i');
        $performanceData = [
            'timestamp' => $results['timestamp'],
            'response_times' => array_column($results['health_checks'], 'response_time', 'name'),
            'memory_usage_mb' => $results['system_metrics']['memory']['used_mb'] ?? 0,
            'load_average' => $results['system_metrics']['load']['1min'] ?? 0,
            'cache_hit_ratio' => $results['performance_metrics']['cache']['hit_ratio'] ?? 0
        ];
        
        $this->cache->set($performanceKey, json_encode($performanceData), 3600); // 1 hour
    }
    
    /**
     * Calculate overall system health score
     */
    private function calculateOverallHealth(array $results): int
    {
        $score = 100;
        
        // Health checks (40% weight)
        $healthyChecks = 0;
        $totalChecks = count($results['health_checks']);
        
        foreach ($results['health_checks'] as $check) {
            if ($check['status'] === 'healthy') {
                $healthyChecks++;
            } elseif ($check['status'] === 'warning') {
                $healthyChecks += 0.5;
            }
        }
        
        if ($totalChecks > 0) {
            $healthRatio = $healthyChecks / $totalChecks;
            $score = $score * 0.6 + ($healthRatio * 40);
        }
        
        // System metrics (30% weight)
        $metrics = $results['system_metrics'];
        
        // Memory usage impact
        if (isset($metrics['memory']['limit_mb']) && $metrics['memory']['limit_mb'] > 0) {
            $memoryPercent = ($metrics['memory']['used_mb'] / $metrics['memory']['limit_mb']) * 100;
            if ($memoryPercent > 90) {
                $score -= 20;
            } elseif ($memoryPercent > 80) {
                $score -= 10;
            }
        }
        
        // Load average impact
        if (isset($metrics['load']['1min'])) {
            if ($metrics['load']['1min'] > 10) {
                $score -= 20;
            } elseif ($metrics['load']['1min'] > 5) {
                $score -= 10;
            }
        }
        
        // Connection status impact (30% weight)
        if (isset($metrics['connections']['redis']['status']) && 
            $metrics['connections']['redis']['status'] === 'disconnected') {
            $score -= 15;
        }
        
        if (isset($metrics['connections']['database']['status']) && 
            $metrics['connections']['database']['status'] === 'disconnected') {
            $score -= 15;
        }
        
        return max(0, min(100, (int)round($score)));
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
     * Start continuous polling
     */
    public function start(): void
    {
        $this->logger->info('Monitor poll job started', [
            'component' => 'monitor_poll',
            'hostname' => $this->hostname,
            'pid' => getmypid(),
            'poll_interval' => $this->config['poll_interval']
        ]);
        
        while (true) {
            $this->poll();
            sleep($this->config['poll_interval']);
        }
    }
    
    /**
     * Get latest poll results
     */
    public function getLatestResults(): ?array
    {
        try {
            $data = $this->cache->get('poll:latest');
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get health score
     */
    public function getHealthScore(): int
    {
        try {
            return (int)$this->cache->get('poll:health_score') ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $args = $argv ?? [];
    
    $pollJob = new MonitorPollJob();
    
    if (in_array('--start', $args) || in_array('start', $args)) {
        $pollJob->start();
    } else {
        // Single poll
        $results = $pollJob->poll();
        echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
        exit($results['success'] ? 0 : 1);
    }
}

// Web interface
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $pollJob = new MonitorPollJob();
    
    switch ($_GET['action']) {
        case 'poll':
            $results = $pollJob->poll();
            echo json_encode($results);
            break;
            
        case 'latest':
            $results = $pollJob->getLatestResults();
            echo json_encode($results ?: ['error' => 'No data available']);
            break;
            
        case 'health':
            $score = $pollJob->getHealthScore();
            echo json_encode(['health_score' => $score]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Poll Job</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-heartbeat mr-2"></i>Monitor Poll Job</h4>
                    </div>
                    <div class="card-body">
                        <p>Lightweight system health monitoring with endpoint polling and metrics collection.</p>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <button id="runPoll" class="btn btn-primary btn-block">
                                    <i class="fas fa-play mr-2"></i>Run Single Poll
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button id="getLatest" class="btn btn-info btn-block">
                                    <i class="fas fa-clock mr-2"></i>Get Latest Results
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button id="getHealth" class="btn btn-success btn-block">
                                    <i class="fas fa-heartbeat mr-2"></i>Health Score
                                </button>
                            </div>
                        </div>
                        
                        <div id="results" class="mt-4"></div>
                        
                        <h6 class="mt-4">CLI Usage:</h6>
                        <pre class="bg-dark text-light p-3">
# Run single poll
php monitor_poll_job.php

# Start continuous polling
php monitor_poll_job.php --start

# Run in background
nohup php monitor_poll_job.php --start > /dev/null 2>&1 &
                        </pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
    function makeRequest(action, buttonId) {
        const button = $(buttonId);
        const originalText = button.text();
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading...');
        
        $.ajax({
            url: '?action=' + action,
            method: 'GET',
            success: function(data) {
                let html = '<div class="card"><div class="card-header"><h6>' + action.toUpperCase() + ' Results</h6></div>';
                html += '<div class="card-body"><pre class="bg-light p-3" style="max-height: 400px; overflow-y: auto;">';
                html += JSON.stringify(data, null, 2);
                html += '</pre></div></div>';
                
                $('#results').html(html);
            },
            error: function() {
                $('#results').html('<div class="alert alert-danger">Request failed</div>');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    $('#runPoll').click(() => makeRequest('poll', '#runPoll'));
    $('#getLatest').click(() => makeRequest('latest', '#getLatest'));
    $('#getHealth').click(() => makeRequest('health', '#getHealth'));
    </script>
</body>
</html>
