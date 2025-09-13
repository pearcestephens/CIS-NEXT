<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Shared\Config\Config;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Profiler Middleware
 * Captures request and database query performance metrics
 */
class Profiler
{
    private float $startTime;
    private int $startMemory;
    
    public function handle(array $request, callable $next): mixed
    {
        if (!Config::get('PROFILER_ENABLED')) {
            return $next($request);
        }
        
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        
        // Clear query log for this request
        try {
            Database::getInstance()->clearQueryLog();
        } catch (\Exception $e) {
            // Database might not be available
        }
        
        $response = $next($request);
        
        $this->recordProfileData($request);
        
        return $response;
    }
    
    private function recordProfileData(array $request): void
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $totalTime = ($endTime - $this->startTime) * 1000; // Convert to milliseconds
        $memoryPeak = memory_get_peak_usage(true);
        
        $requestId = $request['HTTP_X_REQUEST_ID'] ?? 'unknown';
        $route = $this->extractRoute($request);
        $method = $request['REQUEST_METHOD'] ?? 'GET';
        $uri = $request['REQUEST_URI'] ?? '/';
        $statusCode = http_response_code() ?: 200;
        $userId = $_REQUEST['_user']['id'] ?? null;
        
        try {
            $db = Database::getInstance();
            $queryLog = $db->getQueryLog();
            
            // Insert profiling request record
            $stmt = $db->prepare("
                INSERT INTO profiling_request (
                    request_id, route, method, user_id, uri, status_code, 
                    total_ms, memory_peak, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $requestId,
                $route,
                $method,
                $userId,
                $uri,
                $statusCode,
                round($totalTime, 2),
                $memoryPeak,
            ]);
            
            // Insert query profiling records
            foreach ($queryLog as $index => $query) {
                $stmt = $db->prepare("
                    INSERT INTO profiling_query (
                        request_id, sequence_no, sql_text, params_json, 
                        rows, ms, explain_json, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $explainData = null;
                if ($query['time_ms'] > 100) { // Only EXPLAIN slow queries
                    $explainData = $this->getExplainData($query['sql']);
                }
                
                $stmt->execute([
                    $requestId,
                    $index + 1,
                    $query['sql'],
                    json_encode($query['params']),
                    $query['rows'],
                    $query['time_ms'],
                    $explainData ? json_encode($explainData) : null,
                ]);
            }
            
            // Update slow query log
            $this->updateSlowQueryLog($queryLog);
            
        } catch (\Exception $e) {
            // Silently fail profiling to not break the application
            error_log("Profiler error: " . $e->getMessage());
        }
    }
    
    private function extractRoute(array $request): string
    {
        $uri = $request['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Simple route extraction - could be enhanced with actual route matching
        return $uri;
    }
    
    private function getExplainData(string $sql): ?array
    {
        try {
            // Only EXPLAIN SELECT statements
            if (!preg_match('/^\s*SELECT/i', trim($sql))) {
                return null;
            }
            
            $db = Database::getInstance();
            $stmt = $db->prepare("EXPLAIN FORMAT=JSON " . $sql);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result ? json_decode($result['EXPLAIN'], true) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function updateSlowQueryLog(array $queryLog): void
    {
        $slowQueryThreshold = (float) Config::get('SLOW_QUERY_THRESHOLD', 500);
        
        foreach ($queryLog as $query) {
            if ($query['time_ms'] < $slowQueryThreshold) {
                continue;
            }
            
            $sqlHash = md5(trim($query['sql']));
            
            try {
                $db = Database::getInstance();
                
                // Update or insert slow query record
                $stmt = $db->prepare("
                    INSERT INTO slow_query_log (sql_hash, sample_sql, avg_ms, max_ms, calls, last_seen_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE
                        avg_ms = (avg_ms * calls + VALUES(avg_ms)) / (calls + 1),
                        max_ms = GREATEST(max_ms, VALUES(max_ms)),
                        calls = calls + 1,
                        last_seen_at = NOW()
                ");
                
                $stmt->execute([
                    $sqlHash,
                    $query['sql'],
                    $query['time_ms'],
                    $query['time_ms'],
                ]);
                
            } catch (\Exception $e) {
                // Continue silently
            }
        }
    }
}
