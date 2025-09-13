<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Shared\Queue\QueueManager;

/**
 * Queue Management API
 * 
 * RESTful API for queue operations
 */
class QueueController extends BaseController
{
    private QueueManager $queue;

    public function __construct()
    {
        parent::__construct();
        $this->queue = QueueManager::getInstance();
    }

    /**
     * GET /api/queue/stats
     */
    public function getStats(): void
    {
        $this->requireAuth();
        
        $queue = $_GET['queue'] ?? null;
        $stats = $this->queue->getQueueStats($queue);
        
        $this->jsonResponse([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'queue_filter' => $queue,
                'timestamp' => date('c')
            ]
        ]);
    }

    /**
     * POST /api/queue/jobs
     */
    public function createJob(): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        
        $input = $this->getJsonInput();
        
        if (!isset($input['job_type']) || !isset($input['payload'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'MISSING_REQUIRED_FIELDS',
                    'message' => 'job_type and payload are required'
                ]
            ], 400);
            return;
        }

        $jobId = $this->queue->enqueue(
            $input['job_type'],
            $input['payload'],
            $input['priority'] ?? 5,
            $input['queue'] ?? 'default',
            $input['delay_seconds'] ?? null
        );

        $this->jsonResponse([
            'success' => true,
            'data' => [
                'job_id' => $jobId,
                'status' => 'queued'
            ],
            'meta' => [
                'created_at' => date('c')
            ]
        ], 201);
    }

    /**
     * GET /api/queue/jobs/{id}
     */
    public function getJob(): void
    {
        $this->requireAuth();
        
        $jobId = (int) ($_GET['id'] ?? 0);
        
        if (!$jobId) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_JOB_ID',
                    'message' => 'Valid job ID is required'
                ]
            ], 400);
            return;
        }

        $job = $this->queue->getJob($jobId);
        
        if (!$job) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'JOB_NOT_FOUND',
                    'message' => 'Job not found'
                ]
            ], 404);
            return;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $job
        ]);
    }

    /**
     * DELETE /api/queue/jobs/{id}
     */
    public function cancelJob(): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        
        $jobId = (int) ($_GET['id'] ?? 0);
        
        if (!$jobId) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_JOB_ID',
                    'message' => 'Valid job ID is required'
                ]
            ], 400);
            return;
        }

        $cancelled = $this->queue->cancelJob($jobId);
        
        if (!$cancelled) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'CANCEL_FAILED',
                    'message' => 'Job could not be cancelled (may already be processing or completed)'
                ]
            ], 400);
            return;
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Job cancelled successfully'
        ]);
    }

    /**
     * POST /api/queue/process
     */
    public function processJobs(): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        
        $input = $this->getJsonInput();
        
        $queue = $input['queue'] ?? null;
        $jobTypes = $input['job_types'] ?? [];
        $maxJobs = min($input['max_jobs'] ?? 10, 100); // Cap at 100
        
        $processed = $this->queue->processJobs($queue, $jobTypes, $maxJobs);
        
        $this->jsonResponse([
            'success' => true,
            'data' => [
                'jobs_processed' => $processed,
                'queue' => $queue,
                'job_types' => $jobTypes
            ],
            'meta' => [
                'processed_at' => date('c')
            ]
        ]);
    }

    /**
     * DELETE /api/queue/cleanup
     */
    public function cleanup(): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        
        $input = $this->getJsonInput();
        $daysToKeep = min($input['days_to_keep'] ?? 7, 365); // Cap at 365 days
        
        $deletedCount = $this->queue->cleanup($daysToKeep);
        
        $this->jsonResponse([
            'success' => true,
            'data' => [
                'deleted_jobs' => $deletedCount,
                'days_kept' => $daysToKeep
            ],
            'meta' => [
                'cleaned_at' => date('c')
            ]
        ]);
    }

    /**
     * Get JSON input from request body
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_JSON',
                    'message' => 'Invalid JSON in request body'
                ]
            ], 400);
            exit;
        }
        
        return $decoded ?? [];
    }
}
