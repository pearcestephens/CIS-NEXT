<?php
/**
 * Integration Client Examples and Utilities
 * 
 * Ready-to-use client code for CIS API integration
 */

/**
 * Complete CIS API Client with Error Handling
 */
class CisIntegrationClient
{
    private string $baseUrl;
    private string $token;
    private ?string $csrfToken = null;
    private array $defaultHeaders;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->defaultHeaders = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'User-Agent: CIS-Integration-Client/2.0'
        ];
    }

    /**
     * Queue Operations
     */
    public function queueEmail(string $to, string $subject, string $template, array $data = [], int $priority = 5): array
    {
        return $this->queueJob('send_email', [
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
            'data' => $data
        ], ['priority' => $priority]);
    }

    public function queueReport(string $type, array $options = [], int $priority = 3): array
    {
        return $this->queueJob('generate_report', [
            'type' => $type,
            'format' => $options['format'] ?? 'pdf',
            'period' => $options['period'] ?? 'monthly',
            'filters' => $options['filters'] ?? []
        ], ['priority' => $priority]);
    }

    public function queueCleanup(string $directory, int $daysOld = 7, int $priority = 8): array
    {
        return $this->queueJob('cleanup_files', [
            'directory' => $directory,
            'days_old' => $daysOld
        ], ['priority' => $priority]);
    }

    public function queueJob(string $jobType, array $payload, array $options = []): array
    {
        return $this->makeRequest('POST', '/api/queue/jobs', [
            'job_type' => $jobType,
            'payload' => $payload,
            'priority' => $options['priority'] ?? 5,
            'queue' => $options['queue'] ?? 'default',
            'delay_seconds' => $options['delay_seconds'] ?? null
        ]);
    }

    public function getJobStatus(int $jobId): array
    {
        return $this->makeRequest('GET', "/api/queue/jobs/{$jobId}");
    }

    public function cancelJob(int $jobId): array
    {
        return $this->makeRequest('DELETE', "/api/queue/jobs/{$jobId}");
    }

    public function getQueueStats(?string $queue = null): array
    {
        $params = $queue ? ['queue' => $queue] : [];
        return $this->makeRequest('GET', '/api/queue/stats', null, $params);
    }

    /**
     * Telemetry Operations
     */
    public function recordEvent(string $eventType, string $eventName, array $data = []): array
    {
        return $this->makeRequest('POST', '/api/telemetry/event', [
            'event_type' => $eventType,
            'event_name' => $eventName,
            'data' => $data,
            'request_id' => $this->generateRequestId()
        ]);
    }

    public function getPerformanceMetrics(?string $eventType = null, string $timeFrame = '24h'): array
    {
        $params = ['time_frame' => $timeFrame];
        if ($eventType) $params['event_type'] = $eventType;
        
        return $this->makeRequest('GET', '/api/telemetry/metrics', null, $params);
    }

    public function getSlowRequests(int $limit = 10): array
    {
        return $this->makeRequest('GET', '/api/telemetry/slow-requests', null, ['limit' => $limit]);
    }

    /**
     * Batch Operations
     */
    public function batchQueueJobs(array $jobs): array
    {
        $results = [];
        foreach ($jobs as $job) {
            try {
                $result = $this->queueJob(
                    $job['job_type'],
                    $job['payload'],
                    $job['options'] ?? []
                );
                $results[] = ['success' => true, 'data' => $result];
            } catch (\Exception $e) {
                $results[] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /**
     * Health Check
     */
    public function healthCheck(): array
    {
        try {
            $queueStats = $this->getQueueStats();
            $metrics = $this->getPerformanceMetrics('http_request', '1h');
            
            return [
                'status' => 'healthy',
                'queue_health' => $this->assessQueueHealth($queueStats),
                'performance_health' => $this->assessPerformanceHealth($metrics),
                'checked_at' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'checked_at' => date('c')
            ];
        }
    }

    /**
     * Utility Methods
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null, array $params = []): array
    {
        $url = $this->baseUrl . $endpoint;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $headers = $this->defaultHeaders;
        if ($this->csrfToken && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $headers[] = 'X-CSRF-Token: ' . $this->csrfToken;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $message = $errorData['error']['message'] ?? "HTTP {$httpCode} error";
            throw new \Exception($message, $httpCode);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response: " . json_last_error_msg());
        }

        return $decoded;
    }

    private function generateRequestId(): string
    {
        return 'req_' . bin2hex(random_bytes(8));
    }

    private function assessQueueHealth(array $stats): array
    {
        $data = $stats['data'] ?? [];
        $statusCounts = $data['status_counts'] ?? [];
        
        $pending = $statusCounts['pending'] ?? 0;
        $failed24h = $data['failed_last_24h'] ?? 0;
        
        $status = 'healthy';
        $issues = [];
        
        if ($pending > 1000) {
            $status = 'warning';
            $issues[] = "High pending jobs count: {$pending}";
        }
        
        if ($failed24h > 50) {
            $status = 'critical';
            $issues[] = "High failure rate: {$failed24h} failures in 24h";
        }
        
        return [
            'status' => $status,
            'pending_jobs' => $pending,
            'failed_24h' => $failed24h,
            'issues' => $issues
        ];
    }

    private function assessPerformanceHealth(array $metrics): array
    {
        $data = $metrics['data'] ?? [];
        
        if (empty($data)) {
            return ['status' => 'unknown', 'reason' => 'No metrics available'];
        }
        
        $httpMetrics = null;
        foreach ($data as $metric) {
            if ($metric['event_type'] === 'http_request') {
                $httpMetrics = $metric;
                break;
            }
        }
        
        if (!$httpMetrics) {
            return ['status' => 'unknown', 'reason' => 'No HTTP request metrics'];
        }
        
        $avgDuration = $httpMetrics['avg_duration'] ?? 0;
        $maxDuration = $httpMetrics['max_duration'] ?? 0;
        
        $status = 'healthy';
        $issues = [];
        
        if ($avgDuration > 1000) {
            $status = 'warning';
            $issues[] = "High average response time: {$avgDuration}ms";
        }
        
        if ($maxDuration > 5000) {
            $status = 'critical';
            $issues[] = "Very slow requests detected: {$maxDuration}ms max";
        }
        
        return [
            'status' => $status,
            'avg_response_time' => $avgDuration,
            'max_response_time' => $maxDuration,
            'issues' => $issues
        ];
    }

    public function setCsrfToken(string $token): void
    {
        $this->csrfToken = $token;
    }
}

/**
 * Usage Examples
 */

// Example 1: Basic Queue Operations
function exampleQueueOperations()
{
    $client = new CisIntegrationClient('https://staff.vapeshed.co.nz', 'your-api-token');
    
    // Queue welcome email
    $emailResult = $client->queueEmail(
        'newuser@example.com',
        'Welcome to The Vape Shed!',
        'welcome_email',
        ['user_name' => 'John Doe', 'store_location' => 'Auckland']
    );
    
    echo "Email queued with ID: " . $emailResult['data']['job_id'] . "\n";
    
    // Generate sales report
    $reportResult = $client->queueReport('sales', [
        'period' => 'weekly',
        'format' => 'pdf',
        'filters' => ['store_id' => 5]
    ]);
    
    echo "Report queued with ID: " . $reportResult['data']['job_id'] . "\n";
    
    // Check queue health
    $health = $client->healthCheck();
    echo "System health: " . $health['status'] . "\n";
}

// Example 2: Monitoring Integration
function exampleMonitoring()
{
    $client = new CisIntegrationClient('https://staff.vapeshed.co.nz', 'your-api-token');
    
    // Record custom business event
    $client->recordEvent('business', 'sale_completed', [
        'store_id' => 5,
        'sale_amount' => 125.50,
        'payment_method' => 'card',
        'customer_type' => 'returning'
    ]);
    
    // Get performance metrics
    $metrics = $client->getPerformanceMetrics('http_request', '1h');
    
    foreach ($metrics['data'] as $metric) {
        if ($metric['avg_duration'] > 500) {
            // Alert on slow performance
            echo "ALERT: Average response time is {$metric['avg_duration']}ms\n";
        }
    }
    
    // Check for slow requests
    $slowRequests = $client->getSlowRequests(5);
    foreach ($slowRequests['data'] as $request) {
        if ($request['duration_ms'] > 2000) {
            echo "Slow request detected: {$request['route']} took {$request['duration_ms']}ms\n";
        }
    }
}

// Example 3: Batch Processing
function exampleBatchProcessing()
{
    $client = new CisIntegrationClient('https://staff.vapeshed.co.nz', 'your-api-token');
    
    // Batch queue multiple jobs
    $jobs = [
        [
            'job_type' => 'send_email',
            'payload' => ['to' => 'user1@example.com', 'template' => 'newsletter'],
            'options' => ['priority' => 2]
        ],
        [
            'job_type' => 'send_email', 
            'payload' => ['to' => 'user2@example.com', 'template' => 'newsletter'],
            'options' => ['priority' => 2]
        ],
        [
            'job_type' => 'cleanup_files',
            'payload' => ['directory' => '/tmp/uploads', 'days_old' => 1],
            'options' => ['priority' => 9]
        ]
    ];
    
    $results = $client->batchQueueJobs($jobs);
    
    $successful = array_filter($results, fn($r) => $r['success']);
    echo "Successfully queued " . count($successful) . " out of " . count($jobs) . " jobs\n";
}

/**
 * CLI Tool for Integration Testing
 */
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    if ($argc < 3) {
        echo "Usage: php integration_client.php <base_url> <token> [command]\n";
        echo "Commands: health, queue-stats, queue-test, metrics, slow-requests\n";
        exit(1);
    }
    
    $baseUrl = $argv[1];
    $token = $argv[2];
    $command = $argv[3] ?? 'health';
    
    $client = new CisIntegrationClient($baseUrl, $token);
    
    try {
        switch ($command) {
            case 'health':
                $result = $client->healthCheck();
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'queue-stats':
                $result = $client->getQueueStats();
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'queue-test':
                $result = $client->queueJob('send_email', [
                    'to' => 'test@example.com',
                    'subject' => 'Test Email',
                    'template' => 'test'
                ]);
                echo "Test job queued: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'metrics':
                $result = $client->getPerformanceMetrics();
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'slow-requests':
                $result = $client->getSlowRequests();
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
                break;
                
            default:
                echo "Unknown command: {$command}\n";
                exit(1);
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
