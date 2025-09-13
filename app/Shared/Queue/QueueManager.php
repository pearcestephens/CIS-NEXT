<?php
declare(strict_types=1);

namespace App\Shared\Queue;

use App\Shared\Logging\Logger;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Queue System for Background Processing
 * 
 * Handles job queuing, processing, and retry logic
 * 
 * @package App\Shared\Queue
 */
class QueueManager
{
    private static ?self $instance = null;
    private Logger $logger;
    private Database $database;
    
    // Queue statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    private function __construct()
    {
        $this->logger = Logger::getInstance();
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
     * Add job to queue
     */
    public function enqueue(
        string $jobType,
        array $payload,
        int $priority = 5,
        ?string $queue = 'default',
        ?int $delaySeconds = null
    ): int {
        $executeAt = $delaySeconds ? 
            date('Y-m-d H:i:s', time() + $delaySeconds) : 
            date('Y-m-d H:i:s');

        $sql = "INSERT INTO cis_queue_jobs 
                (job_type, queue_name, priority, payload, status, execute_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $jobType,
            $queue,
            $priority,
            json_encode($payload),
            self::STATUS_PENDING,
            $executeAt
        ];

        $stmt = $this->database->executeQuery($sql, $params);
        $jobId = (int) $this->database->lastInsertId();

        $this->logger->info('Job enqueued', [
            'job_id' => $jobId,
            'job_type' => $jobType,
            'queue' => $queue,
            'priority' => $priority,
            'execute_at' => $executeAt
        ]);

        return $jobId;
    }

    /**
     * Get next job from queue
     */
    public function dequeue(?string $queue = null, array $jobTypes = []): ?array
    {
        $conditions = ["status = ?", "execute_at <= NOW()"];
        $params = [self::STATUS_PENDING];

        if ($queue) {
            $conditions[] = "queue_name = ?";
            $params[] = $queue;
        }

        if (!empty($jobTypes)) {
            $placeholders = str_repeat('?,', count($jobTypes) - 1) . '?';
            $conditions[] = "job_type IN ($placeholders)";
            $params = array_merge($params, $jobTypes);
        }

        $whereClause = implode(' AND ', $conditions);
        
        // Use SELECT ... FOR UPDATE to prevent race conditions
        $sql = "SELECT id, job_type, queue_name, priority, payload, attempts, max_attempts, created_at 
                FROM cis_queue_jobs 
                WHERE {$whereClause}
                ORDER BY priority ASC, created_at ASC 
                LIMIT 1 
                FOR UPDATE";

        $stmt = $this->database->executeQuery($sql, $params);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($job) {
            // Mark as processing
            $this->updateJobStatus((int) $job['id'], self::STATUS_PROCESSING);
            
            // Decode payload
            $job['payload'] = json_decode($job['payload'], true);
            
            $this->logger->info('Job dequeued', [
                'job_id' => $job['id'],
                'job_type' => $job['job_type'],
                'queue' => $job['queue_name']
            ]);
        }

        return $job;
    }

    /**
     * Mark job as completed
     */
    public function markCompleted(int $jobId, ?array $result = null): bool
    {
        $sql = "UPDATE cis_queue_jobs 
                SET status = ?, result = ?, completed_at = NOW() 
                WHERE id = ?";

        $params = [
            self::STATUS_COMPLETED,
            $result ? json_encode($result) : null,
            $jobId
        ];

        $stmt = $this->database->executeQuery($sql, $params);
        $success = $stmt->rowCount() > 0;

        if ($success) {
            $this->logger->info('Job completed', [
                'job_id' => $jobId,
                'has_result' => !empty($result)
            ]);
        }

        return $success;
    }

    /**
     * Mark job as failed and handle retry logic
     */
    public function markFailed(int $jobId, string $error, bool $retry = true): bool
    {
        // Get current job info
        $job = $this->getJob($jobId);
        if (!$job) {
            return false;
        }

        $attempts = (int) $job['attempts'] + 1;
        $maxAttempts = (int) $job['max_attempts'];
        
        // Determine if we should retry
        $shouldRetry = $retry && $attempts < $maxAttempts;
        
        if ($shouldRetry) {
            // Calculate exponential backoff delay
            $delay = min(pow(2, $attempts) * 60, 3600); // Max 1 hour
            $nextExecuteAt = date('Y-m-d H:i:s', time() + $delay);
            
            $sql = "UPDATE cis_queue_jobs 
                    SET status = ?, attempts = ?, last_error = ?, execute_at = ?
                    WHERE id = ?";
                    
            $params = [
                self::STATUS_PENDING,
                $attempts,
                $error,
                $nextExecuteAt,
                $jobId
            ];
            
            $this->logger->warning('Job failed, retrying', [
                'job_id' => $jobId,
                'attempt' => $attempts,
                'max_attempts' => $maxAttempts,
                'retry_at' => $nextExecuteAt,
                'error' => $error
            ]);
        } else {
            // Mark as permanently failed
            $sql = "UPDATE cis_queue_jobs 
                    SET status = ?, attempts = ?, last_error = ?, failed_at = NOW() 
                    WHERE id = ?";
                    
            $params = [
                self::STATUS_FAILED,
                $attempts,
                $error,
                $jobId
            ];
            
            $this->logger->error('Job permanently failed', [
                'job_id' => $jobId,
                'attempts' => $attempts,
                'error' => $error
            ]);
        }

        $stmt = $this->database->executeQuery($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get job by ID
     */
    public function getJob(int $jobId): ?array
    {
        $sql = "SELECT * FROM cis_queue_jobs WHERE id = ?";
        $stmt = $this->database->executeQuery($sql, [$jobId]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($job && $job['payload']) {
            $job['payload'] = json_decode($job['payload'], true);
        }
        
        if ($job && $job['result']) {
            $job['result'] = json_decode($job['result'], true);
        }
        
        return $job ?: null;
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(?string $queue = null): array
    {
        $conditions = [];
        $params = [];

        if ($queue) {
            $conditions[] = "queue_name = ?";
            $params[] = $queue;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM cis_queue_jobs 
                {$whereClause}
                GROUP BY status";

        $stmt = $this->database->executeQuery($sql, $params);
        $statusCounts = [];
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $statusCounts[$row['status']] = (int) $row['count'];
        }

        // Get failed jobs in last 24 hours
        $failedSql = "SELECT COUNT(*) as count 
                      FROM cis_queue_jobs 
                      WHERE status = 'failed' 
                      AND failed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        if ($queue) {
            $failedSql .= " AND queue_name = ?";
            $params = [$queue];
        } else {
            $params = [];
        }

        $stmt = $this->database->executeQuery($failedSql, $params);
        $failed24h = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

        return [
            'status_counts' => $statusCounts,
            'failed_last_24h' => (int) $failed24h,
            'total_jobs' => array_sum($statusCounts)
        ];
    }

    /**
     * Clean up old completed and failed jobs
     */
    public function cleanup(int $daysToKeep = 7): int
    {
        $sql = "DELETE FROM cis_queue_jobs 
                WHERE status IN ('completed', 'failed') 
                AND (completed_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                     OR failed_at < DATE_SUB(NOW(), INTERVAL ? DAY))";

        $stmt = $this->database->executeQuery($sql, [$daysToKeep, $daysToKeep]);
        $deletedCount = $stmt->rowCount();

        $this->logger->info('Queue cleanup completed', [
            'days_kept' => $daysToKeep,
            'jobs_deleted' => $deletedCount
        ]);

        return $deletedCount;
    }

    /**
     * Cancel pending job
     */
    public function cancelJob(int $jobId): bool
    {
        $sql = "UPDATE cis_queue_jobs 
                SET status = ?, cancelled_at = NOW() 
                WHERE id = ? AND status = ?";

        $stmt = $this->database->executeQuery($sql, [
            self::STATUS_CANCELLED,
            $jobId,
            self::STATUS_PENDING
        ]);

        $success = $stmt->rowCount() > 0;

        if ($success) {
            $this->logger->info('Job cancelled', ['job_id' => $jobId]);
        }

        return $success;
    }

    /**
     * Update job status
     */
    private function updateJobStatus(int $jobId, string $status): bool
    {
        $sql = "UPDATE cis_queue_jobs SET status = ? WHERE id = ?";
        $stmt = $this->database->executeQuery($sql, [$status, $jobId]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Process jobs (worker method)
     */
    public function processJobs(
        ?string $queue = null, 
        array $jobTypes = [], 
        int $maxJobs = 100
    ): int {
        $processed = 0;
        
        $this->logger->info('Queue worker started', [
            'queue' => $queue,
            'job_types' => $jobTypes,
            'max_jobs' => $maxJobs
        ]);

        while ($processed < $maxJobs) {
            $job = $this->dequeue($queue, $jobTypes);
            
            if (!$job) {
                // No more jobs available
                break;
            }

            try {
                // Process the job based on type
                $result = $this->executeJob($job);
                $this->markCompleted((int) $job['id'], $result);
                
            } catch (\Throwable $e) {
                $this->markFailed((int) $job['id'], $e->getMessage());
            }

            $processed++;
        }

        $this->logger->info('Queue worker finished', [
            'jobs_processed' => $processed
        ]);

        return $processed;
    }

    /**
     * Execute a job based on its type
     */
    private function executeJob(array $job): array
    {
        $jobType = $job['job_type'];
        $payload = $job['payload'];

        // Job type handlers
        switch ($jobType) {
            case 'send_email':
                return $this->handleEmailJob($payload);
                
            case 'generate_report':
                return $this->handleReportJob($payload);
                
            case 'cleanup_files':
                return $this->handleCleanupJob($payload);
                
            default:
                throw new \InvalidArgumentException("Unknown job type: {$jobType}");
        }
    }

    /**
     * Handle email sending job
     */
    private function handleEmailJob(array $payload): array
    {
        // Mock email sending
        $this->logger->info('Processing email job', $payload);
        
        // Simulate processing time
        usleep(100000); // 100ms
        
        return [
            'email_sent' => true,
            'recipient' => $payload['to'] ?? 'unknown',
            'processed_at' => date('c')
        ];
    }

    /**
     * Handle report generation job
     */
    private function handleReportJob(array $payload): array
    {
        $this->logger->info('Processing report job', $payload);
        
        // Simulate processing time
        usleep(500000); // 500ms
        
        return [
            'report_generated' => true,
            'report_type' => $payload['type'] ?? 'unknown',
            'file_size' => rand(1024, 10240),
            'processed_at' => date('c')
        ];
    }

    /**
     * Handle cleanup job
     */
    private function handleCleanupJob(array $payload): array
    {
        $this->logger->info('Processing cleanup job', $payload);
        
        // Simulate processing time
        usleep(200000); // 200ms
        
        return [
            'files_cleaned' => rand(5, 50),
            'space_freed_mb' => rand(10, 500),
            'processed_at' => date('c')
        ];
    }
}
