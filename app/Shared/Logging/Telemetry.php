<?php
declare(strict_types=1);

namespace App\Shared\Logging;

use App\Infra\Persistence\MariaDB\Database;

/**
 * Telemetry Collector
 * 
 * Captures performance metrics and system events
 */
class Telemetry
{
    private static ?self $instance = null;
    private Database $database;
    private array $activeRequests = [];

    private function __construct()
    {
        $this->database = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Start request tracking
     */
    public function startRequest(string $requestId, string $route, string $method): void
    {
        $this->activeRequests[$requestId] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'route' => $route,
            'method' => $method,
            'db_queries' => 0,
            'db_time' => 0
        ];
    }

    /**
     * End request tracking and record metrics
     */
    public function endRequest(
        string $requestId, 
        int $statusCode, 
        ?int $responseSize = null
    ): void {
        if (!isset($this->activeRequests[$requestId])) {
            return;
        }

        $request = $this->activeRequests[$requestId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = ($endTime - $request['start_time']) * 1000; // Convert to ms
        $memoryUsage = ($endMemory - $request['start_memory']) / 1024 / 1024; // Convert to MB

        $this->recordEvent('http_request', 'request_completed', [
            'request_id' => $requestId,
            'route' => $request['route'],
            'method' => $request['method'],
            'status_code' => $statusCode,
            'duration_ms' => round($duration, 3),
            'memory_usage_mb' => round($memoryUsage, 2),
            'response_size_bytes' => $responseSize,
            'db_queries_count' => $request['db_queries'],
            'db_query_time_ms' => round($request['db_time'], 3)
        ]);

        unset($this->activeRequests[$requestId]);
    }

    /**
     * Record database query metrics
     */
    public function recordDbQuery(string $requestId, float $queryTime): void
    {
        if (isset($this->activeRequests[$requestId])) {
            $this->activeRequests[$requestId]['db_queries']++;
            $this->activeRequests[$requestId]['db_time'] += $queryTime;
        }
    }

    /**
     * Record custom telemetry event
     */
    public function recordEvent(
        string $eventType,
        string $eventName,
        array $data = [],
        ?string $requestId = null
    ): bool {
        try {
            $sql = "INSERT INTO cis_telemetry_events 
                    (event_type, event_name, request_id, user_id, session_id,
                     duration_ms, memory_usage_mb, route, method, status_code,
                     response_size_bytes, db_queries_count, db_query_time_ms,
                     metrics, tags, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $params = [
                $eventType,
                $eventName,
                $requestId,
                $_SESSION['user_id'] ?? null,
                session_id(),
                $data['duration_ms'] ?? null,
                $data['memory_usage_mb'] ?? null,
                $data['route'] ?? null,
                $data['method'] ?? null,
                $data['status_code'] ?? null,
                $data['response_size_bytes'] ?? null,
                $data['db_queries_count'] ?? 0,
                $data['db_query_time_ms'] ?? 0,
                json_encode($data),
                json_encode(['environment' => 'dev', 'version' => '2.0']),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ];

            $this->database->executeQuery($sql, $params);
            return true;

        } catch (\Throwable $e) {
            error_log("Telemetry recording failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get performance metrics
     */
    public function getMetrics(
        ?string $eventType = null,
        ?string $timeFrame = '24h'
    ): array {
        $conditions = [];
        $params = [];

        // Time frame filter
        switch ($timeFrame) {
            case '1h':
                $conditions[] = "created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
                break;
            case '24h':
                $conditions[] = "created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
                break;
            case '7d':
                $conditions[] = "created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
        }

        if ($eventType) {
            $conditions[] = "event_type = ?";
            $params[] = $eventType;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT 
                    event_type,
                    COUNT(*) as event_count,
                    AVG(duration_ms) as avg_duration,
                    MAX(duration_ms) as max_duration,
                    AVG(memory_usage_mb) as avg_memory,
                    MAX(memory_usage_mb) as max_memory,
                    SUM(db_queries_count) as total_db_queries,
                    AVG(db_query_time_ms) as avg_db_time
                FROM cis_telemetry_events 
                {$whereClause}
                GROUP BY event_type
                ORDER BY event_count DESC";

        $stmt = $this->database->executeQuery($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get top slow requests
     */
    public function getSlowRequests(int $limit = 10): array
    {
        $sql = "SELECT 
                    request_id, route, method, status_code,
                    duration_ms, memory_usage_mb, db_queries_count,
                    created_at
                FROM cis_telemetry_events 
                WHERE event_type = 'http_request' 
                AND duration_ms IS NOT NULL
                ORDER BY duration_ms DESC 
                LIMIT ?";

        $stmt = $this->database->executeQuery($sql, [$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Clean up old telemetry data
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        $sql = "DELETE FROM cis_telemetry_events 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $stmt = $this->database->executeQuery($sql, [$daysToKeep]);
        return $stmt->rowCount();
    }
}
