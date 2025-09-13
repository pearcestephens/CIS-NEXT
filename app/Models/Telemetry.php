<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Telemetry Model
 * 
 * Handles performance monitoring and telemetry data
 */
class Telemetry extends BaseModel
{
    protected string $table = 'cis_telemetry_events';
    protected array $fillable = [
        'event_type',
        'event_name',
        'user_id',
        'session_id',
        'request_id',
        'duration_ms',
        'memory_peak_mb',
        'query_count',
        'metadata',
        'tags'
    ];

    // Event types
    const TYPE_REQUEST = 'request';
    const TYPE_DATABASE = 'database';
    const TYPE_CACHE = 'cache';
    const TYPE_API = 'api';
    const TYPE_JOB = 'job';
    const TYPE_ERROR = 'error';
    const TYPE_CUSTOM = 'custom';

    /**
     * Record a request event
     */
    public function recordRequest(
        string $route,
        int $duration,
        int $memoryPeak,
        int $queryCount,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $requestId = null,
        array $metadata = []
    ): int {
        return $this->create([
            'event_type' => self::TYPE_REQUEST,
            'event_name' => $route,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'request_id' => $requestId,
            'duration_ms' => $duration,
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'query_count' => $queryCount,
            'metadata' => json_encode($metadata),
            'tags' => json_encode(['route' => $route])
        ]);
    }

    /**
     * Record a database query event
     */
    public function recordQuery(
        string $queryType,
        int $duration,
        ?string $table = null,
        ?string $requestId = null,
        array $metadata = []
    ): int {
        $tags = ['query_type' => $queryType];
        if ($table) {
            $tags['table'] = $table;
        }

        return $this->create([
            'event_type' => self::TYPE_DATABASE,
            'event_name' => $queryType,
            'request_id' => $requestId,
            'duration_ms' => $duration,
            'metadata' => json_encode($metadata),
            'tags' => json_encode($tags)
        ]);
    }

    /**
     * Record an API call event
     */
    public function recordApiCall(
        string $endpoint,
        int $duration,
        int $statusCode,
        ?string $requestId = null,
        array $metadata = []
    ): int {
        $metadata['status_code'] = $statusCode;
        
        return $this->create([
            'event_type' => self::TYPE_API,
            'event_name' => $endpoint,
            'request_id' => $requestId,
            'duration_ms' => $duration,
            'metadata' => json_encode($metadata),
            'tags' => json_encode([
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'success' => $statusCode < 400
            ])
        ]);
    }

    /**
     * Record a job execution event
     */
    public function recordJob(
        string $jobType,
        int $duration,
        bool $successful,
        ?string $requestId = null,
        array $metadata = []
    ): int {
        $metadata['successful'] = $successful;
        
        return $this->create([
            'event_type' => self::TYPE_JOB,
            'event_name' => $jobType,
            'request_id' => $requestId,
            'duration_ms' => $duration,
            'metadata' => json_encode($metadata),
            'tags' => json_encode([
                'job_type' => $jobType,
                'successful' => $successful
            ])
        ]);
    }

    /**
     * Record an error event
     */
    public function recordError(
        string $errorType,
        string $errorMessage,
        ?string $requestId = null,
        ?int $userId = null,
        array $metadata = []
    ): int {
        $metadata['message'] = $errorMessage;
        
        return $this->create([
            'event_type' => self::TYPE_ERROR,
            'event_name' => $errorType,
            'user_id' => $userId,
            'request_id' => $requestId,
            'metadata' => json_encode($metadata),
            'tags' => json_encode([
                'error_type' => $errorType,
                'severity' => $metadata['severity'] ?? 'error'
            ])
        ]);
    }

    /**
     * Record a custom event
     */
    public function recordCustom(
        string $eventName,
        ?int $duration = null,
        ?int $userId = null,
        ?string $requestId = null,
        array $metadata = [],
        array $tags = []
    ): int {
        return $this->create([
            'event_type' => self::TYPE_CUSTOM,
            'event_name' => $eventName,
            'user_id' => $userId,
            'request_id' => $requestId,
            'duration_ms' => $duration,
            'metadata' => json_encode($metadata),
            'tags' => json_encode($tags)
        ]);
    }

    /**
     * Get performance metrics for a time period
     */
    public function getPerformanceMetrics(string $eventType, int $hoursBack = 24): array
    {
        $sql = "SELECT 
                    AVG(duration_ms) as avg_duration,
                    MIN(duration_ms) as min_duration,
                    MAX(duration_ms) as max_duration,
                    PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY duration_ms) as p50_duration,
                    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration_ms) as p95_duration,
                    PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY duration_ms) as p99_duration,
                    AVG(memory_peak_mb) as avg_memory,
                    MAX(memory_peak_mb) as max_memory,
                    AVG(query_count) as avg_queries,
                    MAX(query_count) as max_queries,
                    COUNT(*) as total_events
                FROM {$this->table}
                WHERE event_type = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)";

        $stmt = $this->database->executeQuery($sql, [$eventType, $hoursBack]);
        $metrics = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Convert to appropriate types
        $metrics['avg_duration'] = round((float) $metrics['avg_duration'], 2);
        $metrics['min_duration'] = (int) $metrics['min_duration'];
        $metrics['max_duration'] = (int) $metrics['max_duration'];
        $metrics['p50_duration'] = round((float) $metrics['p50_duration'], 2);
        $metrics['p95_duration'] = round((float) $metrics['p95_duration'], 2);
        $metrics['p99_duration'] = round((float) $metrics['p99_duration'], 2);
        $metrics['avg_memory'] = round((float) $metrics['avg_memory'], 2);
        $metrics['max_memory'] = round((float) $metrics['max_memory'], 2);
        $metrics['avg_queries'] = round((float) $metrics['avg_queries'], 2);
        $metrics['max_queries'] = (int) $metrics['max_queries'];
        $metrics['total_events'] = (int) $metrics['total_events'];

        return $metrics;
    }

    /**
     * Get error rates by type
     */
    public function getErrorRates(int $hoursBack = 24): array
    {
        $sql = "SELECT 
                    event_name,
                    COUNT(*) as count,
                    COUNT(*) / ? * 100 as rate_per_hour
                FROM {$this->table}
                WHERE event_type = 'error'
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY event_name
                ORDER BY count DESC";

        $stmt = $this->database->executeQuery($sql, [$hoursBack, $hoursBack]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get slow requests (above threshold)
     */
    public function getSlowRequests(int $thresholdMs = 1000, int $hoursBack = 24): array
    {
        $sql = "SELECT 
                    event_name,
                    duration_ms,
                    memory_peak_mb,
                    query_count,
                    user_id,
                    created_at,
                    metadata
                FROM {$this->table}
                WHERE event_type = 'request'
                AND duration_ms > ?
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY duration_ms DESC
                LIMIT 100";

        $stmt = $this->database->executeQuery($sql, [$thresholdMs, $hoursBack]);
        $requests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode metadata
        foreach ($requests as &$request) {
            $request['metadata'] = json_decode($request['metadata'], true);
        }

        return $requests;
    }

    /**
     * Get telemetry dashboard data
     */
    public function getDashboardData(int $hoursBack = 24): array
    {
        // Request metrics
        $requestMetrics = $this->getPerformanceMetrics(self::TYPE_REQUEST, $hoursBack);
        
        // Database metrics
        $dbMetrics = $this->getPerformanceMetrics(self::TYPE_DATABASE, $hoursBack);
        
        // Error rates
        $errorRates = $this->getErrorRates($hoursBack);
        
        // Top slow requests
        $slowRequests = $this->getSlowRequests(500, $hoursBack);
        
        // Hourly trends
        $trendsSql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                        event_type,
                        COUNT(*) as count,
                        AVG(duration_ms) as avg_duration
                      FROM {$this->table}
                      WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                      GROUP BY hour, event_type
                      ORDER BY hour DESC";

        $stmt = $this->database->executeQuery($trendsSql, [$hoursBack]);
        $trends = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'request_metrics' => $requestMetrics,
            'database_metrics' => $dbMetrics,
            'error_rates' => $errorRates,
            'slow_requests' => $slowRequests,
            'hourly_trends' => $trends,
            'period_hours' => $hoursBack
        ];
    }

    /**
     * Get events by request ID for tracing
     */
    public function getRequestTrace(string $requestId): array
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE request_id = ?
                ORDER BY created_at ASC";

        $stmt = $this->database->executeQuery($sql, [$requestId]);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($events as &$event) {
            $event['metadata'] = json_decode($event['metadata'], true);
            $event['tags'] = json_decode($event['tags'], true);
        }

        return $events;
    }

    /**
     * Clean up old telemetry data
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $stmt = $this->database->executeQuery($sql, [$daysToKeep]);
        return $stmt->rowCount();
    }

    /**
     * Get resource usage statistics
     */
    public function getResourceUsage(int $hoursBack = 24): array
    {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    AVG(memory_peak_mb) as avg_memory,
                    MAX(memory_peak_mb) as max_memory,
                    AVG(query_count) as avg_queries,
                    MAX(query_count) as max_queries,
                    COUNT(*) as request_count
                FROM {$this->table}
                WHERE event_type = 'request'
                AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY hour
                ORDER BY hour DESC";

        $stmt = $this->database->executeQuery($sql, [$hoursBack]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get alerts based on thresholds
     */
    public function getAlerts(): array
    {
        $alerts = [];
        
        // High error rate alert (>5% in last hour)
        $errorRateSql = "SELECT 
                            (SELECT COUNT(*) FROM {$this->table} 
                             WHERE event_type = 'error' 
                             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as errors,
                            (SELECT COUNT(*) FROM {$this->table} 
                             WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as total";

        $stmt = $this->database->executeQuery($errorRateSql);
        $errorData = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($errorData['total'] > 0) {
            $errorRate = ($errorData['errors'] / $errorData['total']) * 100;
            if ($errorRate > 5) {
                $alerts[] = [
                    'type' => 'high_error_rate',
                    'severity' => 'warning',
                    'message' => "High error rate: {$errorRate}% in the last hour",
                    'value' => $errorRate
                ];
            }
        }

        // Slow requests alert (>3 requests taking >2s in last hour)
        $slowSql = "SELECT COUNT(*) as slow_count
                    FROM {$this->table}
                    WHERE event_type = 'request'
                    AND duration_ms > 2000
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";

        $stmt = $this->database->executeQuery($slowSql);
        $slowCount = $stmt->fetch(\PDO::FETCH_ASSOC)['slow_count'];
        
        if ($slowCount > 3) {
            $alerts[] = [
                'type' => 'slow_requests',
                'severity' => 'warning',
                'message' => "{$slowCount} slow requests (>2s) in the last hour",
                'value' => $slowCount
            ];
        }

        return $alerts;
    }
}
