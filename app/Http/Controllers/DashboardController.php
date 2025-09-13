<?php
/**
 * CIS - Central Information System
 * app/Http/Controllers/DashboardController.php
 * 
 * Main dashboard controller for CIS admin interface.
 * Displays system overview, statistics, and navigation.
 *
 * @package CIS
 * @version 1.0.0
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Permission;
use App\Models\Feed;
use App\Http\Controllers\BaseController;
use App\Infra\Persistence\MariaDB\Database;
use App\Shared\Logging\Logger;
use App\Shared\Config\Config;

class DashboardController extends BaseController
{
    private User $userModel;
    private Permission $permissionModel;
    private Feed $feedModel;
    private Database $database;
    private Logger $logger;

    public function __construct()
    {
        $this->database = Database::getInstance();
        $this->logger = Logger::getInstance();
        
        $this->userModel = new User();
        $this->permissionModel = new Permission();
        $this->feedModel = new Feed();
    }

    /**
     * Display main dashboard
     */
    public function index(): void
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        try {
            // Get current user data
            $sessionValidation = $this->userModel->validateSession();
            if (!$sessionValidation['success']) {
                $this->redirect('/login');
                return;
            }

            $currentUser = $sessionValidation['data']['user'];

            // Get dashboard statistics
            $stats = $this->getDashboardStats();
            $feedData = $this->feedModel->getTimelineFeed([], 1, 10);
            $recentActivity = $this->getRecentActivity();
            $systemHealth = $this->getSystemHealth();

            // Check permissions for various sections
            $permissions = [
                'view_users' => $this->userModel->hasPermission('users.view'),
                'view_system' => $this->userModel->hasPermission('system.view'),
                'view_reports' => $this->userModel->hasPermission('reports.view'),
                'view_feed' => $this->userModel->hasPermission('feed.view')
            ];

            $this->render('dashboard/index', [
                'title' => 'Dashboard - CIS',
                'current_user' => $currentUser,
                'stats' => $stats,
                'feed_data' => $feedData,
                'recent_activity' => $recentActivity,
                'system_health' => $systemHealth,
                'permissions' => $permissions,
                'request_id' => $requestId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Dashboard index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId
            ]);

            $this->render('errors/500', [
                'title' => 'Dashboard Error - CIS',
                'message' => 'Unable to load dashboard',
                'request_id' => $requestId
            ]);
        }
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats(): array
    {
        try {
            // User statistics
            $stmt = $this->database->execute("
                SELECT 
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                    COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d
                FROM users
            ");
            $userStats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            // Session statistics
            $stmt = $this->database->execute("
                SELECT 
                    COUNT(*) as active_sessions,
                    COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as sessions_24h
                FROM user_sessions
                WHERE expires_at > NOW()
            ");
            $sessionStats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            // Feed statistics
            $stmt = $this->database->execute("
                SELECT 
                    COUNT(*) as total_events,
                    COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as events_24h,
                    COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as events_1h
                FROM feed_events
                WHERE status = 'active'
            ");
            $feedStats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            // System performance
            $stmt = $this->database->execute("
                SELECT 
                    COUNT(*) as total_requests,
                    AVG(duration_ms) as avg_response_time,
                    COUNT(CASE WHEN duration_ms > 1000 THEN 1 END) as slow_requests
                FROM request_profiling
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $performanceStats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            return [
                'users' => [
                    'total' => (int) ($userStats['total_users'] ?? 0),
                    'active' => (int) ($userStats['active_users'] ?? 0),
                    'new_30d' => (int) ($userStats['new_users_30d'] ?? 0)
                ],
                'sessions' => [
                    'active' => (int) ($sessionStats['active_sessions'] ?? 0),
                    'today' => (int) ($sessionStats['sessions_24h'] ?? 0)
                ],
                'feed' => [
                    'total_events' => (int) ($feedStats['total_events'] ?? 0),
                    'events_24h' => (int) ($feedStats['events_24h'] ?? 0),
                    'events_1h' => (int) ($feedStats['events_1h'] ?? 0)
                ],
                'performance' => [
                    'total_requests' => (int) ($performanceStats['total_requests'] ?? 0),
                    'avg_response_time' => round((float) ($performanceStats['avg_response_time'] ?? 0), 2),
                    'slow_requests' => (int) ($performanceStats['slow_requests'] ?? 0)
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Get dashboard stats error', [
                'error' => $e->getMessage()
            ]);

            return [
                'users' => ['total' => 0, 'active' => 0, 'new_30d' => 0],
                'sessions' => ['active' => 0, 'today' => 0],
                'feed' => ['total_events' => 0, 'events_24h' => 0, 'events_1h' => 0],
                'performance' => ['total_requests' => 0, 'avg_response_time' => 0, 'slow_requests' => 0]
            ];
        }
    }

    /**
     * Get recent system activity
     */
    private function getRecentActivity(): array
    {
        try {
            $stmt = $this->database->execute("
                SELECT 
                    'user_login' as activity_type,
                    u.email as actor,
                    'User logged in' as description,
                    us.created_at as activity_time,
                    us.ip_address as metadata
                FROM user_sessions us
                JOIN users u ON us.user_id = u.id
                WHERE us.created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                
                UNION ALL
                
                SELECT 
                    'feed_event' as activity_type,
                    COALESCE(u.email, 'System') as actor,
                    CONCAT(fe.event_type, ': ', fe.title) as description,
                    fe.created_at as activity_time,
                    fe.source_module as metadata
                FROM feed_events fe
                LEFT JOIN users u ON fe.user_id = u.id
                WHERE fe.created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                AND fe.status = 'active'
                
                ORDER BY activity_time DESC
                LIMIT 15
            ");

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $this->logger->error('Get recent activity error', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get system health indicators
     */
    private function getSystemHealth(): array
    {
        try {
            // Database health check
            $dbHealth = $this->database->healthCheck();

            // Check for recent errors
            $stmt = $this->database->execute("
                SELECT COUNT(*) as error_count
                FROM request_profiling
                WHERE status_code >= 500
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $recentErrors = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            // Check slow queries
            $stmt = $this->database->execute("
                SELECT COUNT(*) as slow_count
                FROM request_profiling
                WHERE duration_ms > 2000
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $slowQueries = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            // Storage check (simplified)
            $diskUsage = $this->getApproximateDiskUsage();

            $healthScore = 100;
            $issues = [];

            // Database connectivity
            if (!$dbHealth['connected']) {
                $healthScore -= 50;
                $issues[] = 'Database connection issues';
            }

            // Recent errors
            $errorCount = (int) ($recentErrors['error_count'] ?? 0);
            if ($errorCount > 10) {
                $healthScore -= 20;
                $issues[] = "High error rate: {$errorCount} errors in last hour";
            }

            // Slow queries
            $slowCount = (int) ($slowQueries['slow_count'] ?? 0);
            if ($slowCount > 5) {
                $healthScore -= 15;
                $issues[] = "Performance issues: {$slowCount} slow queries in last hour";
            }

            // Disk usage
            if ($diskUsage > 85) {
                $healthScore -= 10;
                $issues[] = "High disk usage: {$diskUsage}%";
            }

            return [
                'score' => max(0, $healthScore),
                'status' => $healthScore > 80 ? 'healthy' : ($healthScore > 50 ? 'warning' : 'critical'),
                'database' => $dbHealth,
                'performance' => [
                    'recent_errors' => $errorCount,
                    'slow_queries' => $slowCount
                ],
                'storage' => [
                    'disk_usage_percent' => $diskUsage
                ],
                'issues' => $issues
            ];

        } catch (\Exception $e) {
            $this->logger->error('Get system health error', [
                'error' => $e->getMessage()
            ]);

            return [
                'score' => 0,
                'status' => 'critical',
                'issues' => ['Unable to check system health']
            ];
        }
    }

    /**
     * Get approximate disk usage (simplified implementation)
     */
    private function getApproximateDiskUsage(): float
    {
        try {
            $totalSpace = disk_total_space($_SERVER['DOCUMENT_ROOT']);
            $freeSpace = disk_free_space($_SERVER['DOCUMENT_ROOT']);
            
            if ($totalSpace && $freeSpace) {
                return round((($totalSpace - $freeSpace) / $totalSpace) * 100, 1);
            }

            return 0.0;

        } catch (\Exception $e) {
            return 0.0;
        }
    }
}
