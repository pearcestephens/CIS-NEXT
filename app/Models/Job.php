<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Job Model
 * 
 * Handles background job queue operations
 */
class Job extends BaseModel
{
    protected string $table = 'cis_jobs';
    protected array $fillable = [
        'job_type',
        'queue_name',
        'priority',
        'payload',
        'status',
        'execute_at',
        'max_attempts'
    ];

    // Job statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Enqueue a new job
     */
    public function enqueue(string $jobType, array $payload, int $priority = 5, string $queue = 'default', ?int $delaySeconds = null): int
    {
        $executeAt = $delaySeconds ? 
            date('Y-m-d H:i:s', time() + $delaySeconds) : 
            date('Y-m-d H:i:s');

        return $this->create([
            'job_type' => $jobType,
            'queue_name' => $queue,
            'priority' => $priority,
            'payload' => json_encode($payload),
            'status' => self::STATUS_PENDING,
            'execute_at' => $executeAt
        ]);
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
        $sql = "SELECT * FROM {$this->table} 
                WHERE {$whereClause}
                ORDER BY priority ASC, created_at ASC 
                LIMIT 1 
                FOR UPDATE";

        $stmt = $this->database->executeQuery($sql, $params);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($job) {
            // Mark as processing
            $this->updateStatus((int) $job['id'], self::STATUS_PROCESSING);
            
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
        $data = [
            'status' => self::STATUS_COMPLETED,
            'completed_at' => date('Y-m-d H:i:s')
        ];

        if ($result) {
            $data['result'] = json_encode($result);
        }

        return $this->update($jobId, $data);
    }

    /**
     * Mark job as failed with retry logic
     */
    public function markFailed(int $jobId, string $error, bool $retry = true): bool
    {
        $job = $this->find($jobId);
        if (!$job) {
            return false;
        }

        $attempts = (int) $job['attempts'] + 1;
        $maxAttempts = (int) $job['max_attempts'];
        
        $shouldRetry = $retry && $attempts < $maxAttempts;
        
        if ($shouldRetry) {
            // Calculate exponential backoff delay
            $delay = min(pow(2, $attempts) * 60, 3600); // Max 1 hour
            $nextExecuteAt = date('Y-m-d H:i:s', time() + $delay);
            
            return $this->update($jobId, [
                'status' => self::STATUS_PENDING,
                'attempts' => $attempts,
                'last_error' => $error,
                'execute_at' => $nextExecuteAt
            ]);
        } else {
            // Mark as permanently failed
            return $this->update($jobId, [
                'status' => self::STATUS_FAILED,
                'attempts' => $attempts,
                'last_error' => $error,
                'failed_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Cancel pending job
     */
    public function cancelJob(int $jobId): bool
    {
        return $this->update($jobId, [
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update job status
     */
    public function updateStatus(int $jobId, string $status): bool
    {
        $data = ['status' => $status];

        if ($status === self::STATUS_PROCESSING) {
            $data['started_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($jobId, $data);
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
                FROM {$this->table} 
                {$whereClause}
                GROUP BY status";

        $stmt = $this->database->executeQuery($sql, $params);
        $statusCounts = [];
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $statusCounts[$row['status']] = (int) $row['count'];
        }

        // Get failed jobs in last 24 hours
        $failedSql = "SELECT COUNT(*) as count 
                      FROM {$this->table} 
                      WHERE status = 'failed' 
                      AND failed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        if ($queue) {
            $failedSql .= " AND queue_name = ?";
            $failedParams = [$queue];
        } else {
            $failedParams = [];
        }

        $stmt = $this->database->executeQuery($failedSql, $failedParams);
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
        $sql = "DELETE FROM {$this->table} 
                WHERE status IN ('completed', 'failed') 
                AND (completed_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                     OR failed_at < DATE_SUB(NOW(), INTERVAL ? DAY))";

        $stmt = $this->database->executeQuery($sql, [$daysToKeep, $daysToKeep]);
        return $stmt->rowCount();
    }

    /**
     * Get jobs by status
     */
    public function getByStatus(string $status, int $limit = 100): array
    {
        return $this->where(['status' => $status], $limit);
    }

    /**
     * Get failed jobs for analysis
     */
    public function getFailedJobs(int $limit = 50): array
    {
        $sql = "SELECT id, job_type, queue_name, attempts, last_error, failed_at, payload
                FROM {$this->table} 
                WHERE status = 'failed'
                ORDER BY failed_at DESC 
                LIMIT ?";

        $stmt = $this->database->executeQuery($sql, [$limit]);
        $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode payload for each job
        foreach ($jobs as &$job) {
            $job['payload'] = json_decode($job['payload'], true);
        }

        return $jobs;
    }
}
