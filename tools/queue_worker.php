<?php
/**
 * Worker Script for Queue Processing
 * 
 * Long-running worker that processes background jobs
 */

require_once dirname(__DIR__) . '/app/Shared/Bootstrap.php';

use App\Shared\Queue\QueueManager;
use App\Shared\Logging\Logger;

class QueueWorker
{
    private QueueManager $queue;
    private Logger $logger;
    private bool $running = true;
    private string $workerId;

    public function __construct()
    {
        $this->queue = QueueManager::getInstance();
        $this->logger = Logger::getInstance();
        $this->workerId = gethostname() . '_' . getmypid();
        
        // Handle shutdown signals
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }

    /**
     * Start processing jobs
     */
    public function run(array $options = []): void
    {
        $queue = $options['queue'] ?? null;
        $jobTypes = $options['job_types'] ?? [];
        $maxJobs = $options['max_jobs'] ?? 1000;
        $sleepTime = $options['sleep_time'] ?? 5;
        
        $this->logger->info('Queue worker started', [
            'worker_id' => $this->workerId,
            'queue' => $queue,
            'job_types' => $jobTypes,
            'max_jobs' => $maxJobs
        ]);

        $processed = 0;

        while ($this->running && $processed < $maxJobs) {
            try {
                // Process signal handlers
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                $job = $this->queue->dequeue($queue, $jobTypes);
                
                if ($job) {
                    $this->processJob($job);
                    $processed++;
                } else {
                    // No jobs available, sleep
                    sleep($sleepTime);
                }
                
            } catch (\Throwable $e) {
                $this->logger->error('Worker error', [
                    'worker_id' => $this->workerId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                sleep($sleepTime);
            }
        }

        $this->logger->info('Queue worker finished', [
            'worker_id' => $this->workerId,
            'jobs_processed' => $processed
        ]);
    }

    /**
     * Process individual job
     */
    private function processJob(array $job): void
    {
        $startTime = microtime(true);
        $jobId = (int) $job['id'];
        
        $this->logger->info('Processing job', [
            'job_id' => $jobId,
            'job_type' => $job['job_type'],
            'worker_id' => $this->workerId
        ]);

        try {
            // Execute job based on type
            $result = $this->executeJob($job);
            
            // Mark as completed
            $this->queue->markCompleted($jobId, $result);
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->info('Job completed', [
                'job_id' => $jobId,
                'duration_ms' => round($duration, 2),
                'worker_id' => $this->workerId
            ]);
            
        } catch (\Throwable $e) {
            $this->queue->markFailed($jobId, $e->getMessage());
            
            $this->logger->error('Job failed', [
                'job_id' => $jobId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'worker_id' => $this->workerId
            ]);
        }
    }

    /**
     * Execute job based on type
     */
    private function executeJob(array $job): array
    {
        $jobType = $job['job_type'];
        $payload = $job['payload'];

        switch ($jobType) {
            case 'send_email':
                return $this->handleEmailJob($payload);
                
            case 'generate_report':
                return $this->handleReportJob($payload);
                
            case 'cleanup_files':
                return $this->handleCleanupJob($payload);
                
            case 'process_upload':
                return $this->handleUploadJob($payload);
                
            case 'sync_data':
                return $this->handleSyncJob($payload);
                
            default:
                throw new \InvalidArgumentException("Unknown job type: {$jobType}");
        }
    }

    /**
     * Handle email job
     */
    private function handleEmailJob(array $payload): array
    {
        // Validate required fields
        if (!isset($payload['to']) || !isset($payload['subject'])) {
            throw new \InvalidArgumentException('Email job requires "to" and "subject"');
        }

        // Simulate email sending
        $this->logger->info('Sending email', [
            'to' => $payload['to'],
            'subject' => $payload['subject']
        ]);
        
        // Add actual email sending logic here
        usleep(100000); // Simulate processing time
        
        return [
            'email_sent' => true,
            'recipient' => $payload['to'],
            'message_id' => uniqid('msg_'),
            'sent_at' => date('c')
        ];
    }

    /**
     * Handle report generation job
     */
    private function handleReportJob(array $payload): array
    {
        $reportType = $payload['type'] ?? 'unknown';
        
        $this->logger->info('Generating report', [
            'type' => $reportType,
            'format' => $payload['format'] ?? 'pdf'
        ]);
        
        // Add actual report generation logic here
        usleep(500000); // Simulate processing time
        
        return [
            'report_generated' => true,
            'report_type' => $reportType,
            'file_path' => '/var/reports/' . uniqid('report_') . '.pdf',
            'file_size_kb' => rand(100, 5000),
            'generated_at' => date('c')
        ];
    }

    /**
     * Handle file cleanup job
     */
    private function handleCleanupJob(array $payload): array
    {
        $directory = $payload['directory'] ?? '/tmp';
        $daysOld = $payload['days_old'] ?? 7;
        
        $this->logger->info('Cleaning up files', [
            'directory' => $directory,
            'days_old' => $daysOld
        ]);
        
        // Add actual cleanup logic here
        $filesDeleted = rand(5, 50);
        $spaceFreed = rand(10, 500);
        
        return [
            'files_deleted' => $filesDeleted,
            'space_freed_mb' => $spaceFreed,
            'directory' => $directory,
            'cleaned_at' => date('c')
        ];
    }

    /**
     * Handle upload processing job
     */
    private function handleUploadJob(array $payload): array
    {
        $filePath = $payload['file_path'] ?? '';
        
        $this->logger->info('Processing upload', [
            'file_path' => $filePath
        ]);
        
        // Add actual upload processing logic here
        usleep(200000); // Simulate processing time
        
        return [
            'upload_processed' => true,
            'file_path' => $filePath,
            'processed_at' => date('c')
        ];
    }

    /**
     * Handle data sync job
     */
    private function handleSyncJob(array $payload): array
    {
        $source = $payload['source'] ?? 'unknown';
        $destination = $payload['destination'] ?? 'unknown';
        
        $this->logger->info('Syncing data', [
            'source' => $source,
            'destination' => $destination
        ]);
        
        // Add actual sync logic here
        usleep(300000); // Simulate processing time
        
        return [
            'data_synced' => true,
            'source' => $source,
            'destination' => $destination,
            'records_synced' => rand(100, 10000),
            'synced_at' => date('c')
        ];
    }

    /**
     * Graceful shutdown handler
     */
    public function shutdown(): void
    {
        $this->running = false;
        $this->logger->info('Worker shutdown requested', [
            'worker_id' => $this->workerId
        ]);
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $options = [];
    
    // Parse command line arguments
    $shortopts = "q:t:m:s:h";
    $longopts = [
        "queue:",
        "job-types:",
        "max-jobs:",
        "sleep:",
        "help"
    ];
    
    $opts = getopt($shortopts, $longopts);
    
    if (isset($opts['h']) || isset($opts['help'])) {
        echo "Queue Worker Usage:\n";
        echo "  php queue_worker.php [options]\n\n";
        echo "Options:\n";
        echo "  -q, --queue=NAME       Process jobs from specific queue\n";
        echo "  -t, --job-types=TYPES  Comma-separated list of job types to process\n";
        echo "  -m, --max-jobs=NUM     Maximum number of jobs to process (default: 1000)\n";
        echo "  -s, --sleep=SECONDS    Sleep time when no jobs available (default: 5)\n";
        echo "  -h, --help             Show this help message\n";
        exit(0);
    }
    
    if (isset($opts['q']) || isset($opts['queue'])) {
        $options['queue'] = $opts['q'] ?? $opts['queue'];
    }
    
    if (isset($opts['t']) || isset($opts['job-types'])) {
        $jobTypes = $opts['t'] ?? $opts['job-types'];
        $options['job_types'] = explode(',', $jobTypes);
    }
    
    if (isset($opts['m']) || isset($opts['max-jobs'])) {
        $options['max_jobs'] = (int) ($opts['m'] ?? $opts['max-jobs']);
    }
    
    if (isset($opts['s']) || isset($opts['sleep'])) {
        $options['sleep_time'] = (int) ($opts['s'] ?? $opts['sleep']);
    }
    
    $worker = new QueueWorker();
    $worker->run($options);
}
