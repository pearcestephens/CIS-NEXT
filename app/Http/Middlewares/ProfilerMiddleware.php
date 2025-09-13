<?php
declare(strict_types=1);

/**
 * ProfilerMiddleware.php - Performance profiling middleware
 * 
 * Tracks request performance, database queries, and memory usage
 * for observability and optimization.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.1
 * @last_modified 2024-12-30T08:30:00Z
 */

namespace App\Http\Middlewares;

use App\Http\MiddlewareInterface;
use App\Shared\Logging\Logger;

class ProfilerMiddleware implements MiddlewareInterface
{
    private Logger $logger;
    private array $startMetrics;
    private array $dbQueries = [];
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Start performance profiling before request processing
     */
    public function before($request): void
    {
        $this->startMetrics = [
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'included_files' => count(get_included_files())
        ];
        
        // Add database query tracking if available
        if (function_exists('mysql_query') || extension_loaded('mysqli') || extension_loaded('pdo_mysql')) {
            $this->startQueryTracking();
        }
        
        $this->logger->info('Profiler started', [
            'component' => 'profiler',
            'action' => 'start_profiling',
            'initial_memory' => $this->formatBytes($this->startMetrics['memory']),
            'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
        ]);
    }
    
    /**
     * Complete profiling and log performance metrics
     */
    public function after($request, $response): void
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $metrics = [
            'execution_time' => round(($endTime - $this->startMetrics['time']) * 1000, 2), // ms
            'memory_used' => $endMemory - $this->startMetrics['memory'],
            'peak_memory' => $peakMemory,
            'files_loaded' => count(get_included_files()) - $this->startMetrics['included_files'],
            'db_queries' => count($this->dbQueries),
            'response_size' => strlen($response->body ?? ''),
            'status_code' => $response->statusCode ?? 200
        ];
        
        // Performance thresholds (configurable via config)
        $slowThreshold = 1000; // 1 second
        $memoryThreshold = 50 * 1024 * 1024; // 50MB
        
        $logLevel = 'info';
        $alerts = [];
        
        if ($metrics['execution_time'] > $slowThreshold) {
            $logLevel = 'warning';
            $alerts[] = 'slow_request';
        }
        
        if ($metrics['memory_used'] > $memoryThreshold) {
            $logLevel = 'warning';  
            $alerts[] = 'high_memory_usage';
        }
        
        $this->logger->{$logLevel}('Request profiled', [
            'component' => 'profiler',
            'action' => 'request_completed',
            'request_id' => $request->headers['X-Request-ID'] ?? 'unknown',
            'method' => $request->method ?? 'GET',
            'uri' => $request->uri ?? '/',
            'metrics' => array_merge($metrics, [
                'memory_used_formatted' => $this->formatBytes($metrics['memory_used']),
                'peak_memory_formatted' => $this->formatBytes($metrics['peak_memory'])
            ]),
            'alerts' => $alerts,
            'db_queries' => array_slice($this->dbQueries, 0, 10), // Top 10 queries
            'timestamp' => date('c')
        ]);
        
        // Add performance headers for debugging
        if (defined('APP_ENV') && APP_ENV === 'development') {
            header("X-Execution-Time: {$metrics['execution_time']}ms");
            header("X-Memory-Usage: " . $this->formatBytes($metrics['memory_used']));
            header("X-DB-Queries: {$metrics['db_queries']}");
        }
    }
    
    /**
     * Track database queries for performance analysis
     */
    private function startQueryTracking(): void
    {
        // This would integrate with your query builder/DB layer
        // For now, we'll set up the structure
        $this->dbQueries = [];
    }
    
    /**
     * Add database query to tracking
     */
    public function trackQuery(string $query, float $executionTime, array $bindings = []): void
    {
        $this->dbQueries[] = [
            'query' => $query,
            'execution_time' => round($executionTime * 1000, 2), // ms
            'bindings' => $bindings,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        } elseif ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
    
    /**
     * Get current performance snapshot
     */
    public function getSnapshot(): array
    {
        return [
            'current_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - ($this->startMetrics['time'] ?? microtime(true)),
            'db_queries' => count($this->dbQueries)
        ];
    }
}
