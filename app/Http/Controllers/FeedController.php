<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Http\Controllers\BaseController;
use App\Infra\Persistence\MariaDB\Database;
use App\Shared\Logging\Logger;

/**
 * Feed Controller
 * Handles AI Timeline & Newsfeed interface
 */
class FeedController extends BaseController
{
    private Feed $feedModel;
    
    public function __construct()
    {
        $this->feedModel = new Feed();
    }
    
    public function timeline(): string
    {
        try {
            // Get filter parameters
            $limit = (int) ($_GET['limit'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);
            $outlet = $_GET['outlet'] ?? 'all';
            $severity = $_GET['severity'] ?? 'all';
            $source = $_GET['source'] ?? 'all';
            
            // Get user preferences
            $userId = $_SESSION['user_id'] ?? null;
            $preferences = $this->getUserPreferences($userId);
            
            // Fetch events with ranking
            $events = $this->feedModel->getTimelineEvents([
                'limit' => $limit,
                'offset' => $offset,
                'outlet' => $outlet,
                'severity' => $severity,
                'source' => $source,
                'user_id' => $userId,
                'preferences' => $preferences
            ]);
            
            // Get outlet list for filter dropdown
            $outlets = $this->getOutlets();
            
            return $this->render('feed/timeline', [
                'page_title' => 'AI Timeline - CIS',
                'events' => $events,
                'outlets' => $outlets,
                'controller' => $this, // Pass controller for getSeverityClass method
                'filters' => [
                    'outlet' => $outlet,
                    'severity' => $severity,
                    'source' => $source,
                    'limit' => $limit,
                    'offset' => $offset
                ],
                'total_count' => $this->feedModel->getTotalEventCount([
                    'outlet' => $outlet,
                    'severity' => $severity,
                    'source' => $source
                ]),
                'user_preferences' => $preferences
            ]);
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('Feed timeline error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->render('errors/500', [
                'page_title' => 'Error - CIS',
                'error_message' => 'Unable to load timeline feed'
            ]);
        }
    }
    
    public function newsfeed(): string
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $outlet = $_GET['outlet'] ?? 'all';
            
            // Get personalized digest
            $digest = $this->feedModel->getPersonalizedDigest($userId, $outlet);
            
            // Get top events for newsfeed
            $topEvents = $this->feedModel->getTopEvents($userId, 20, $outlet);
            
            // Get store grid overview
            $storeOverview = $this->feedModel->getStoreOverview($userId);
            
            return $this->render('feed/newsfeed', [
                'page_title' => 'Newsfeed - CIS',
                'digest' => $digest,
                'top_events' => $topEvents,
                'store_overview' => $storeOverview,
                'outlets' => $this->getOutlets(),
                'selected_outlet' => $outlet
            ]);
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('Feed newsfeed error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->render('errors/500', [
                'page_title' => 'Error - CIS',
                'error_message' => 'Unable to load newsfeed'
            ]);
        }
    }
    
    public function storeGrid(): string
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            // Get overview for all 17 stores
            $storeOverview = $this->feedModel->getStoreOverview($userId);
            
            // Get health scores and recent activity
            $healthScores = $this->feedModel->getStoreHealthScores();
            $recentActivity = $this->feedModel->getRecentActivityByStore();
            
            return $this->render('feed/store-grid', [
                'page_title' => 'Store Overview - CIS',
                'stores' => $storeOverview,
                'health_scores' => $healthScores,
                'recent_activity' => $recentActivity,
                'total_stores' => count($storeOverview)
            ]);
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('Feed store grid error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->render('errors/500', [
                'page_title' => 'Error - CIS',
                'error_message' => 'Unable to load store overview'
            ]);
        }
    }
    
    public function markAsRead(): array
    {
        try {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$eventId || !$userId) {
                return [
                    'success' => false,
                    'error' => ['code' => 'INVALID_REQUEST', 'message' => 'Event ID and user required']
                ];
            }
            
            $result = $this->feedModel->markEventAsRead($eventId, $userId);
            
            return [
                'success' => true,
                'data' => $result,
                'meta' => ['timestamp' => date('c')]
            ];
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('Mark as read error', [
                'event_id' => $eventId ?? null,
                'user_id' => $userId ?? null,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => ['code' => 'SERVER_ERROR', 'message' => 'Unable to mark event as read']
            ];
        }
    }
    
    public function getEvents(): array
    {
        try {
            $limit = min((int) ($_GET['limit'] ?? 50), 100); // Max 100 events
            $offset = (int) ($_GET['offset'] ?? 0);
            $outlet = $_GET['outlet'] ?? 'all';
            $severity = $_GET['severity'] ?? 'all';
            $source = $_GET['source'] ?? 'all';
            $userId = $_SESSION['user_id'] ?? null;
            
            $events = $this->feedModel->getTimelineEvents([
                'limit' => $limit,
                'offset' => $offset,
                'outlet' => $outlet,
                'severity' => $severity,
                'source' => $source,
                'user_id' => $userId
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'events' => $events,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => count($events) === $limit
                    ]
                ],
                'meta' => [
                    'timestamp' => date('c'),
                    'total_count' => $this->feedModel->getTotalEventCount([
                        'outlet' => $outlet,
                        'severity' => $severity,
                        'source' => $source
                    ])
                ]
            ];
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('Get events API error', [
                'error' => $e->getMessage(),
                'params' => $_GET
            ]);
            
            return [
                'success' => false,
                'error' => ['code' => 'SERVER_ERROR', 'message' => 'Unable to fetch events']
            ];
        }
    }
    
    private function getUserPreferences(?int $userId): array
    {
        if (!$userId) {
            return $this->getDefaultPreferences();
        }
        
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT outlet_filter, source_filter, severity_threshold, 
                       ai_suggestions_enabled, digest_frequency
                FROM feed_user_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $prefs = $stmt->fetch();
            
            if (!$prefs) {
                return $this->getDefaultPreferences();
            }
            
            return [
                'outlet_filter' => json_decode($prefs['outlet_filter'] ?? '[]', true),
                'source_filter' => json_decode($prefs['source_filter'] ?? '[]', true),
                'severity_threshold' => $prefs['severity_threshold'] ?? 'info',
                'ai_suggestions_enabled' => (bool) $prefs['ai_suggestions_enabled'],
                'digest_frequency' => $prefs['digest_frequency'] ?? 'daily'
            ];
            
        } catch (\Exception $e) {
            Logger::getInstance()->warning('Unable to load user preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->getDefaultPreferences();
        }
    }
    
    private function getDefaultPreferences(): array
    {
        return [
            'outlet_filter' => [],
            'source_filter' => [],
            'severity_threshold' => 'info',
            'ai_suggestions_enabled' => true,
            'digest_frequency' => 'daily'
        ];
    }
    
    private function getOutlets(): array
    {
        try {
            // Mock outlets for now - in real implementation this would come from vend_outlets
            return [
                ['id' => 1, 'name' => 'Auckland CBD', 'code' => 'AKL-CBD'],
                ['id' => 2, 'name' => 'Wellington Central', 'code' => 'WLG-CTR'],
                ['id' => 3, 'name' => 'Christchurch Mall', 'code' => 'CHC-MALL'],
                ['id' => 4, 'name' => 'Hamilton East', 'code' => 'HAM-EAST'],
                ['id' => 5, 'name' => 'Tauranga Bay', 'code' => 'TGA-BAY'],
                // ... add remaining 12 outlets
            ];
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('Unable to load outlets', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get CSS class for severity level
     */
    public function getSeverityClass(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical' => 'danger',
            'high' => 'warning', 
            'medium' => 'info',
            'low' => 'secondary',
            default => 'light'
        };
    }
}
