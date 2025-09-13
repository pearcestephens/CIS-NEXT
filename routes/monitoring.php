<?php
/**
 * Monitoring & Analytics API Routes
 * Privacy-compliant user monitoring and analytics endpoints
 * 
 * @package CIS
 * @subpackage Routes
 * @author CIS Development Team
 * @version 1.0.0
 */

// Load required controllers
require_once __DIR__ . '/../app/Http/Controllers/Admin/UserAnalyticsController.php';
require_once __DIR__ . '/../app/Monitoring/SessionRecorder.php';

use App\Http\Controllers\Admin\UserAnalyticsController;
use App\Monitoring\SessionRecorder;

/**
 * ===============================================
 * CONSENT MANAGEMENT API ENDPOINTS
 * ===============================================
 */

// Check existing consent status
$router->get('/api/monitoring/check-consent', function() {
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        return;
    }
    
    $recorder = new SessionRecorder();
    $consent = $recorder->checkUserConsent($user_id);
    
    echo json_encode([
        'success' => true,
        'has_consent' => $consent !== null && $consent['status'] === 'granted',
        'consent_data' => $consent
    ]);
});

// Grant monitoring consent
$router->post('/api/monitoring/grant-consent', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        return;
    }
    
    $recorder = new SessionRecorder();
    $result = $recorder->recordConsent(
        $input['user_id'],
        'granted',
        $input['consent_type'] ?? 'session_recording',
        $input['duration_days'] ?? 30
    );
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Consent recorded']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record consent']);
    }
});

// Deny monitoring consent
$router->post('/api/monitoring/deny-consent', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        return;
    }
    
    $recorder = new SessionRecorder();
    $result = $recorder->recordConsent(
        $input['user_id'],
        'denied',
        'session_recording',
        0,
        $input['reason'] ?? 'user_declined'
    );
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Consent denial recorded']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record consent denial']);
    }
});

// Revoke existing consent
$router->post('/api/monitoring/revoke-consent', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        return;
    }
    
    $recorder = new SessionRecorder();
    $result = $recorder->revokeConsent($input['user_id']);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Consent revoked']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to revoke consent']);
    }
});

/**
 * ===============================================
 * SESSION RECORDING API ENDPOINTS
 * ===============================================
 */

// Get monitoring script for user
$router->get('/api/monitoring/get-script', function() {
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(400);
        echo 'console.error("User ID required for monitoring");';
        return;
    }
    
    $recorder = new SessionRecorder();
    
    // Check consent before providing script
    $consent = $recorder->checkUserConsent($user_id);
    if (!$consent || $consent['status'] !== 'granted') {
        echo 'console.log("User has not granted monitoring consent");';
        return;
    }
    
    // Generate monitoring script
    $script = $recorder->generateMonitoringScript($user_id);
    
    header('Content-Type: application/javascript');
    echo $script;
});

// Record session event
$router->post('/api/monitoring/record-event', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id']) || !isset($input['event_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID and event type required']);
        return;
    }
    
    $recorder = new SessionRecorder();
    
    // Verify consent before recording
    $consent = $recorder->checkUserConsent($input['user_id']);
    if (!$consent || $consent['status'] !== 'granted') {
        http_response_code(403);
        echo json_encode(['error' => 'No monitoring consent']);
        return;
    }
    
    // Record the event with privacy filtering
    $event_data = $recorder->filterSensitiveData($input['event_data'] ?? []);
    
    $result = $recorder->recordEvent([
        'user_id' => $input['user_id'],
        'session_id' => $input['session_id'] ?? session_id(),
        'event_type' => $input['event_type'],
        'event_data' => $event_data,
        'url' => $input['url'] ?? $_SERVER['HTTP_REFERER'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    
    echo json_encode(['success' => $result]);
});

// Get session recording data
$router->get('/api/monitoring/session/{session_id}', function($session_id) {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $recorder = new SessionRecorder();
    $session_data = $recorder->getSessionRecording($session_id);
    
    if ($session_data) {
        echo json_encode([
            'success' => true,
            'session' => $session_data
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
    }
});

/**
 * ===============================================
 * ANALYTICS DASHBOARD API ENDPOINTS
 * ===============================================
 */

// Get dashboard statistics
$router->get('/api/analytics/statistics', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $controller = new UserAnalyticsController();
    $stats = $controller->getDashboardStatistics();
    
    echo json_encode($stats);
});

// Get live sessions
$router->get('/api/analytics/live-sessions', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $controller = new UserAnalyticsController();
    $sessions = $controller->getLiveSessions();
    
    echo json_encode(['sessions' => $sessions]);
});

// Get heatmap data
$router->get('/api/analytics/heatmap-data', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $time_range = $_GET['time_range'] ?? '24h';
    $page = $_GET['page'] ?? null;
    
    $controller = new UserAnalyticsController();
    $heatmap = $controller->getClickHeatmapData($time_range, $page);
    
    echo json_encode(['clicks' => $heatmap]);
});

// Get activity timeline
$router->get('/api/analytics/activity-timeline', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $time_range = $_GET['time_range'] ?? '24h';
    
    $controller = new UserAnalyticsController();
    $timeline = $controller->getActivityTimeline($time_range);
    
    echo json_encode($timeline);
});

// Get page popularity data
$router->get('/api/analytics/page-popularity', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $time_range = $_GET['time_range'] ?? '24h';
    
    $controller = new UserAnalyticsController();
    $popularity = $controller->getPagePopularity($time_range);
    
    echo json_encode($popularity);
});

// Get user journey data
$router->get('/api/analytics/user-journeys', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $time_range = $_GET['time_range'] ?? '24h';
    $user_id = $_GET['user_id'] ?? null;
    
    $controller = new UserAnalyticsController();
    $journeys = $controller->getUserJourneys($time_range, $user_id);
    
    echo json_encode(['journeys' => $journeys]);
});

// Get privacy statistics
$router->get('/api/analytics/privacy-stats', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $controller = new UserAnalyticsController();
    $stats = $controller->getPrivacyStatistics();
    
    echo json_encode($stats);
});

// Get session data for viewer
$router->get('/api/analytics/session-data/{session_id}', function($session_id) {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $controller = new UserAnalyticsController();
    $session_data = $controller->getSessionData($session_id);
    
    if ($session_data) {
        echo json_encode($session_data);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
    }
});

/**
 * ===============================================
 * PRIVACY COMPLIANCE API ENDPOINTS
 * ===============================================
 */

// Export user data (GDPR compliance)
$router->post('/api/analytics/export-user-data', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        return;
    }
    
    // Verify user is requesting their own data or admin
    $requesting_user_id = $_SESSION['user']['id'] ?? null;
    $is_admin = ($_SESSION['user']['role'] ?? '') === 'admin';
    
    if ($input['user_id'] != $requesting_user_id && !$is_admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Can only export your own data']);
        return;
    }
    
    $controller = new UserAnalyticsController();
    $export_data = $controller->exportUserData($input['user_id']);
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="user_data_export_' . $input['user_id'] . '.json"');
    echo json_encode($export_data, JSON_PRETTY_PRINT);
});

// Delete user data (Right to be forgotten)
$router->delete('/api/analytics/delete-user-data/{user_id}', function($user_id) {
    // Verify user is deleting their own data or admin
    $requesting_user_id = $_SESSION['user']['id'] ?? null;
    $is_admin = ($_SESSION['user']['role'] ?? '') === 'admin';
    
    if ($user_id != $requesting_user_id && !$is_admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Can only delete your own data']);
        return;
    }
    
    $controller = new UserAnalyticsController();
    $result = $controller->deleteUserData($user_id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'User data deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete user data']);
    }
});

// Generate privacy compliance report
$router->post('/api/analytics/privacy-report', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    $controller = new UserAnalyticsController();
    $report = $controller->generatePrivacyComplianceReport();
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="privacy_compliance_report_' . date('Y-m-d') . '.pdf"');
    echo $report;
});

/**
 * ===============================================
 * REAL-TIME MONITORING ENDPOINTS
 * ===============================================
 */

// WebSocket-style real-time updates (using long polling)
$router->get('/api/analytics/real-time-updates', function() {
    // Check admin permissions
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return;
    }
    
    // Set headers for long polling
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    
    $controller = new UserAnalyticsController();
    $last_update = $_GET['last_update'] ?? 0;
    
    // Poll for updates for up to 30 seconds
    $timeout = time() + 30;
    while (time() < $timeout) {
        $updates = $controller->getRealTimeUpdates($last_update);
        
        if (!empty($updates)) {
            echo json_encode([
                'success' => true,
                'updates' => $updates,
                'timestamp' => time()
            ]);
            return;
        }
        
        sleep(1); // Wait 1 second before checking again
    }
    
    // No updates within timeout
    echo json_encode([
        'success' => true,
        'updates' => [],
        'timestamp' => time()
    ]);
});

// Health check for monitoring system
$router->get('/api/monitoring/health', function() {
    $recorder = new SessionRecorder();
    $health = $recorder->getSystemHealth();
    
    if ($health['status'] === 'healthy') {
        echo json_encode($health);
    } else {
        http_response_code(503);
        echo json_encode($health);
    }
});

/**
 * ===============================================
 * MIDDLEWARE REGISTRATION
 * ===============================================
 */

// Add CSRF protection to all POST/PUT/DELETE routes
$router->addMiddleware('csrf_protection', [
    '/api/monitoring/grant-consent',
    '/api/monitoring/deny-consent',
    '/api/monitoring/revoke-consent',
    '/api/monitoring/record-event',
    '/api/analytics/export-user-data',
    '/api/analytics/privacy-report'
]);

// Add rate limiting to prevent abuse
$router->addMiddleware('rate_limit', [
    '/api/monitoring/record-event' => ['limit' => 100, 'window' => 60], // 100 events per minute
    '/api/monitoring/grant-consent' => ['limit' => 5, 'window' => 300],  // 5 consent changes per 5 minutes
    '/api/analytics/real-time-updates' => ['limit' => 60, 'window' => 60] // 1 request per second
]);

// Add admin authorization to analytics routes
$router->addMiddleware('admin_required', [
    '/api/analytics/statistics',
    '/api/analytics/live-sessions',
    '/api/analytics/heatmap-data',
    '/api/analytics/activity-timeline',
    '/api/analytics/page-popularity',
    '/api/analytics/user-journeys',
    '/api/analytics/privacy-stats',
    '/api/analytics/session-data/*',
    '/api/analytics/privacy-report',
    '/api/analytics/real-time-updates'
]);

/**
 * ===============================================
 * ERROR HANDLING
 * ===============================================
 */

// Global error handler for monitoring APIs
$router->setErrorHandler(function($error, $request) {
    error_log("Monitoring API Error: " . $error . " for request: " . $request);
    
    // Don't expose sensitive error details to clients
    $safe_error = "An error occurred while processing your request";
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $safe_error,
        'request_id' => uniqid(),
        'timestamp' => date('c')
    ]);
});

?>
