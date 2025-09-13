<?php
/**
 * User Activity Analytics Dashboard - Privacy Compliant
 * File: app/Http/Controllers/Admin/UserAnalyticsController.php
 * Purpose: Comprehensive user behavior analytics with privacy controls
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Monitoring\SessionRecorder;

class UserAnalyticsController extends BaseController
{
    private SessionRecorder $recorder;
    private string $analyticsPath;
    
    public function __construct()
    {
        parent::__construct();
        $this->recorder = new SessionRecorder();
        $this->analyticsPath = __DIR__ . '/../../../var/analytics';
    }
    
    /**
     * Main analytics dashboard
     */
    public function dashboard()
    {
        $this->requirePermission('analytics.view');
        
        $data = [
            'page_title' => 'User Analytics Dashboard',
            'user_stats' => $this->getUserStatistics(),
            'session_stats' => $this->getSessionStatistics(),
            'activity_heatmap' => $this->getActivityHeatmap(),
            'consent_status' => $this->getConsentStatus(),
            'top_pages' => $this->getTopPages(),
            'user_journeys' => $this->getUserJourneys()
        ];
        
        return $this->render('admin/analytics_dashboard', $data);
    }
    
    /**
     * Real-time session viewer (for consented users only)
     */
    public function liveSessionViewer()
    {
        $this->requirePermission('analytics.live_view');
        
        $userId = intval($_GET['user_id'] ?? 0);
        
        // Verify user has consented to monitoring
        if (!$this->recorder->hasUserConsented($userId)) {
            return $this->render('admin/no_consent_error', [
                'user_id' => $userId
            ]);
        }
        
        $data = [
            'page_title' => 'Live Session Viewer',
            'user_id' => $userId,
            'user_info' => $this->getUserInfo($userId),
            'active_sessions' => $this->getActiveSessions($userId),
            'session_timeline' => $this->getSessionTimeline($userId)
        ];
        
        return $this->render('admin/live_session_viewer', $data);
    }
    
    /**
     * Session replay viewer
     */
    public function sessionReplay()
    {
        $this->requirePermission('analytics.replay');
        
        $sessionId = $_GET['session_id'] ?? '';
        $userId = intval($_GET['user_id'] ?? 0);
        
        // Verify consent before showing replay
        if (!$this->recorder->hasUserConsented($userId)) {
            $this->setFlash('error', 'User has not consented to session recording');
            return $this->redirect('/admin/analytics');
        }
        
        $sessionData = $this->getSessionReplayData($sessionId);
        
        if (!$sessionData) {
            $this->setFlash('error', 'Session data not found');
            return $this->redirect('/admin/analytics');
        }
        
        $data = [
            'page_title' => 'Session Replay',
            'session_data' => $sessionData,
            'user_info' => $this->getUserInfo($userId),
            'privacy_controls' => $this->getPrivacyControls()
        ];
        
        return $this->render('admin/session_replay', $data);
    }
    
    /**
     * User behavior analytics
     */
    public function userBehavior()
    {
        $this->requirePermission('analytics.behavior');
        
        $userId = intval($_GET['user_id'] ?? 0);
        
        $data = [
            'page_title' => 'User Behavior Analysis',
            'user_id' => $userId,
            'behavior_patterns' => $this->analyzeBehaviorPatterns($userId),
            'click_heatmap' => $this->generateClickHeatmap($userId),
            'navigation_flow' => $this->analyzeNavigationFlow($userId),
            'time_analytics' => $this->getTimeAnalytics($userId),
            'anomaly_detection' => $this->detectAnomalies($userId)
        ];
        
        return $this->render('admin/user_behavior', $data);
    }
    
    /**
     * Consent management interface
     */
    public function consentManagement()
    {
        $this->requirePermission('analytics.manage_consent');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $userId = intval($_POST['user_id'] ?? 0);
            
            switch ($action) {
                case 'revoke_consent':
                    $this->recorder->revokeConsent($userId);
                    $this->setFlash('success', "Consent revoked for user {$userId}");
                    break;
                    
                case 'export_data':
                    return $this->exportUserData($userId);
                    
                case 'delete_data':
                    $this->deleteUserData($userId);
                    $this->setFlash('success', "User data deleted for user {$userId}");
                    break;
            }
            
            return $this->redirect('/admin/analytics/consent');
        }
        
        $data = [
            'page_title' => 'Consent Management',
            'consented_users' => $this->getConsentedUsers(),
            'consent_requests' => $this->getPendingConsentRequests(),
            'data_retention_policy' => $this->getDataRetentionPolicy()
        ];
        
        return $this->render('admin/consent_management', $data);
    }
    
    /**
     * API endpoint for real-time data
     */
    public function apiRealTimeData()
    {
        header('Content-Type: application/json');
        
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'live_events':
                $userId = intval($_GET['user_id'] ?? 0);
                echo json_encode($this->getLiveEvents($userId));
                break;
                
            case 'session_stats':
                echo json_encode($this->getRealtimeSessionStats());
                break;
                
            case 'activity_feed':
                echo json_encode($this->getActivityFeed());
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    }
    
    /**
     * Get comprehensive user statistics
     */
    private function getUserStatistics(): array
    {
        return [
            'total_users' => $this->getTotalUsers(),
            'active_sessions' => $this->getActiveSessionCount(),
            'consented_users' => $this->getConsentedUserCount(),
            'average_session_duration' => $this->getAverageSessionDuration(),
            'page_views_today' => $this->getPageViewsToday(),
            'unique_visitors_today' => $this->getUniqueVisitorsToday()
        ];
    }
    
    /**
     * Get session statistics
     */
    private function getSessionStatistics(): array
    {
        return [
            'sessions_today' => $this->getSessionsToday(),
            'bounce_rate' => $this->getBounceRate(),
            'avg_pages_per_session' => $this->getAvgPagesPerSession(),
            'peak_concurrent_users' => $this->getPeakConcurrentUsers(),
            'session_duration_distribution' => $this->getSessionDurationDistribution()
        ];
    }
    
    /**
     * Generate activity heatmap data
     */
    private function getActivityHeatmap(): array
    {
        // Returns click coordinates and frequency for heatmap visualization
        $heatmapData = [];
        
        // Read recent session files
        $recentFiles = $this->getRecentSessionFiles(7); // Last 7 days
        
        foreach ($recentFiles as $file) {
            $sessionData = json_decode(file_get_contents($file), true);
            
            if (!$sessionData || !isset($sessionData['events'])) continue;
            
            foreach ($sessionData['events'] as $event) {
                if ($event['type'] === 'click' && isset($event['data']['x'], $event['data']['y'])) {
                    $key = $event['data']['x'] . ',' . $event['data']['y'];
                    $heatmapData[$key] = ($heatmapData[$key] ?? 0) + 1;
                }
            }
        }
        
        // Convert to format expected by heatmap library
        $heatmapPoints = [];
        foreach ($heatmapData as $coordinates => $count) {
            [$x, $y] = explode(',', $coordinates);
            $heatmapPoints[] = [
                'x' => intval($x),
                'y' => intval($y),
                'value' => $count
            ];
        }
        
        return $heatmapPoints;
    }
    
    /**
     * Analyze user behavior patterns
     */
    private function analyzeBehaviorPatterns(int $userId): array
    {
        $patterns = [
            'most_active_hours' => [],
            'preferred_pages' => [],
            'click_patterns' => [],
            'navigation_speed' => 0,
            'scroll_behavior' => [],
            'form_completion_rate' => 0
        ];
        
        $userSessions = $this->getUserSessions($userId, 30); // Last 30 days
        
        foreach ($userSessions as $session) {
            $events = $session['events'] ?? [];
            
            // Analyze time patterns
            foreach ($events as $event) {
                $hour = date('H', $event['timestamp'] / 1000);
                $patterns['most_active_hours'][$hour] = ($patterns['most_active_hours'][$hour] ?? 0) + 1;
            }
            
            // Analyze page preferences
            $pages = array_unique(array_column($events, 'url'));
            foreach ($pages as $page) {
                $patterns['preferred_pages'][$page] = ($patterns['preferred_pages'][$page] ?? 0) + 1;
            }
            
            // Additional pattern analysis...
        }
        
        return $patterns;
    }
    
    /**
     * Generate click heatmap for specific user
     */
    private function generateClickHeatmap(int $userId): array
    {
        $userSessions = $this->getUserSessions($userId, 30);
        $clickData = [];
        
        foreach ($userSessions as $session) {
            foreach ($session['events'] ?? [] as $event) {
                if ($event['type'] === 'click') {
                    $page = $event['url'] ?? 'unknown';
                    if (!isset($clickData[$page])) {
                        $clickData[$page] = [];
                    }
                    
                    $clickData[$page][] = [
                        'x' => $event['data']['x'] ?? 0,
                        'y' => $event['data']['y'] ?? 0,
                        'timestamp' => $event['timestamp'] ?? 0
                    ];
                }
            }
        }
        
        return $clickData;
    }
    
    /**
     * Analyze user navigation flow
     */
    private function analyzeNavigationFlow(int $userId): array
    {
        $userSessions = $this->getUserSessions($userId, 30);
        $flows = [];
        
        foreach ($userSessions as $session) {
            $pages = [];
            foreach ($session['events'] ?? [] as $event) {
                if (isset($event['url']) && $event['url'] !== end($pages)) {
                    $pages[] = $event['url'];
                }
            }
            
            // Create flow pairs
            for ($i = 0; $i < count($pages) - 1; $i++) {
                $from = $pages[$i];
                $to = $pages[$i + 1];
                $flowKey = "{$from} -> {$to}";
                $flows[$flowKey] = ($flows[$flowKey] ?? 0) + 1;
            }
        }
        
        // Sort by frequency
        arsort($flows);
        
        return array_slice($flows, 0, 20); // Top 20 flows
    }
    
    // Additional helper methods...
    
    private function getConsentStatus(): array
    {
        // Implementation for consent status overview
        return [
            'total_requests' => 0,
            'granted' => 0,
            'revoked' => 0,
            'pending' => 0
        ];
    }
    
    private function getTopPages(): array
    {
        // Implementation for most visited pages
        return [];
    }
    
    private function getUserJourneys(): array
    {
        // Implementation for common user journey analysis
        return [];
    }
    
    private function detectAnomalies(int $userId): array
    {
        // Implementation for behavioral anomaly detection
        return [
            'unusual_hours' => false,
            'suspicious_clicks' => false,
            'abnormal_navigation' => false,
            'risk_score' => 0
        ];
    }
    
    // More helper methods would be implemented here...
    private function getTotalUsers(): int { return 0; }
    private function getActiveSessionCount(): int { return 0; }
    private function getConsentedUserCount(): int { return 0; }
    private function getAverageSessionDuration(): float { return 0.0; }
    private function getPageViewsToday(): int { return 0; }
    private function getUniqueVisitorsToday(): int { return 0; }
    // ... etc
}
