<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Shared\Logging\Telemetry;

/**
 * Telemetry API Controller
 * 
 * Performance metrics and system monitoring API
 */
class TelemetryController extends BaseController
{
    private Telemetry $telemetry;

    public function __construct()
    {
        parent::__construct();
        $this->telemetry = Telemetry::getInstance();
    }

    /**
     * GET /api/telemetry/metrics
     */
    public function getMetrics(): void
    {
        $this->requireAuth();
        
        $eventType = $_GET['event_type'] ?? null;
        $timeFrame = $_GET['time_frame'] ?? '24h';
        
        $metrics = $this->telemetry->getMetrics($eventType, $timeFrame);
        
        $this->jsonResponse([
            'success' => true,
            'data' => $metrics,
            'meta' => [
                'event_type_filter' => $eventType,
                'time_frame' => $timeFrame,
                'generated_at' => date('c')
            ]
        ]);
    }

    /**
     * GET /api/telemetry/slow-requests
     */
    public function getSlowRequests(): void
    {
        $this->requireAuth();
        
        $limit = min((int) ($_GET['limit'] ?? 10), 100);
        
        $slowRequests = $this->telemetry->getSlowRequests($limit);
        
        $this->jsonResponse([
            'success' => true,
            'data' => $slowRequests,
            'meta' => [
                'limit' => $limit,
                'generated_at' => date('c')
            ]
        ]);
    }

    /**
     * POST /api/telemetry/event
     */
    public function recordEvent(): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        
        $input = $this->getJsonInput();
        
        if (!isset($input['event_type']) || !isset($input['event_name'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'MISSING_REQUIRED_FIELDS',
                    'message' => 'event_type and event_name are required'
                ]
            ], 400);
            return;
        }

        $recorded = $this->telemetry->recordEvent(
            $input['event_type'],
            $input['event_name'],
            $input['data'] ?? [],
            $input['request_id'] ?? null
        );

        if (!$recorded) {
            $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'RECORDING_FAILED',
                    'message' => 'Failed to record telemetry event'
                ]
            ], 500);
            return;
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Event recorded successfully',
            'meta' => [
                'recorded_at' => date('c')
            ]
        ], 201);
    }

    /**
     * DELETE /api/telemetry/cleanup
     */
    public function cleanup(): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        
        $input = $this->getJsonInput();
        $daysToKeep = min($input['days_to_keep'] ?? 30, 365);
        
        $deletedCount = $this->telemetry->cleanup($daysToKeep);
        
        $this->jsonResponse([
            'success' => true,
            'data' => [
                'deleted_events' => $deletedCount,
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
