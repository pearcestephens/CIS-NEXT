<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Feed Model
 * 
 * Handles activity feed system for user actions and system events
 */
class Feed extends BaseModel
{
    protected string $table = 'cis_feed_events';
    protected array $fillable = [
        'event_type',
        'actor_type',
        'actor_id',
        'target_type',
        'target_id',
        'action',
        'data',
        'visibility'
    ];

    // Event types
    const TYPE_USER_ACTION = 'user_action';
    const TYPE_SYSTEM_EVENT = 'system_event';
    const TYPE_AUDIT_EVENT = 'audit_event';
    const TYPE_INTEGRATION_EVENT = 'integration_event';

    // Visibility levels
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_INTERNAL = 'internal';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_SYSTEM = 'system';

    /**
     * Create a user action feed event
     */
    public function createUserAction(
        int $userId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $data = null,
        string $visibility = self::VISIBILITY_INTERNAL
    ): int {
        return $this->create([
            'event_type' => self::TYPE_USER_ACTION,
            'actor_type' => 'User',
            'actor_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'data' => $data ? json_encode($data) : null,
            'visibility' => $visibility
        ]);
    }

    /**
     * Create a system event
     */
    public function createSystemEvent(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $data = null,
        string $visibility = self::VISIBILITY_SYSTEM
    ): int {
        return $this->create([
            'event_type' => self::TYPE_SYSTEM_EVENT,
            'actor_type' => 'System',
            'actor_id' => null,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'data' => $data ? json_encode($data) : null,
            'visibility' => $visibility
        ]);
    }

    /**
     * Create an audit trail feed event
     */
    public function createAuditEvent(
        int $userId,
        string $action,
        string $targetType,
        int $targetId,
        ?array $data = null
    ): int {
        return $this->create([
            'event_type' => self::TYPE_AUDIT_EVENT,
            'actor_type' => 'User',
            'actor_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'data' => $data ? json_encode($data) : null,
            'visibility' => self::VISIBILITY_INTERNAL
        ]);
    }

    /**
     * Create an integration event
     */
    public function createIntegrationEvent(
        string $integration,
        string $action,
        ?array $data = null,
        string $visibility = self::VISIBILITY_SYSTEM
    ): int {
        return $this->create([
            'event_type' => self::TYPE_INTEGRATION_EVENT,
            'actor_type' => 'Integration',
            'actor_id' => null,
            'target_type' => $integration,
            'target_id' => null,
            'action' => $action,
            'data' => $data ? json_encode($data) : null,
            'visibility' => $visibility
        ]);
    }

    /**
     * Get recent feed events
     */
    public function getRecentEvents(
        int $limit = 50,
        ?string $visibility = null,
        ?string $eventType = null,
        ?int $actorId = null
    ): array {
        $conditions = [];
        $params = [];

        if ($visibility) {
            $conditions[] = "visibility = ?";
            $params[] = $visibility;
        }

        if ($eventType) {
            $conditions[] = "event_type = ?";
            $params[] = $eventType;
        }

        if ($actorId) {
            $conditions[] = "actor_id = ?";
            $params[] = $actorId;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT 
                    fe.*,
                    u.username as actor_username,
                    u.email as actor_email
                FROM {$this->table} fe
                LEFT JOIN cis_users u ON fe.actor_type = 'User' AND fe.actor_id = u.id
                {$whereClause}
                ORDER BY fe.created_at DESC
                LIMIT ?";

        $params[] = $limit;
        $stmt = $this->database->executeQuery($sql, $params);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode data field
        foreach ($events as &$event) {
            $event['data'] = $event['data'] ? json_decode($event['data'], true) : null;
        }

        return $events;
    }

    /**
     * Get user activity feed
     */
    public function getUserActivity(int $userId, int $limit = 50): array
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE actor_type = 'User' AND actor_id = ?
                ORDER BY created_at DESC
                LIMIT ?";

        $stmt = $this->database->executeQuery($sql, [$userId, $limit]);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode data field
        foreach ($events as &$event) {
            $event['data'] = $event['data'] ? json_decode($event['data'], true) : null;
        }

        return $events;
    }

    /**
     * Get system activity summary
     */
    public function getSystemSummary(int $hoursBack = 24): array
    {
        $sql = "SELECT 
                    event_type,
                    action,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY event_type, action
                ORDER BY count DESC";

        $stmt = $this->database->executeQuery($sql, [$hoursBack]);
        $summary = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get unique users active in period
        $userSql = "SELECT COUNT(DISTINCT actor_id) as active_users
                    FROM {$this->table}
                    WHERE actor_type = 'User'
                    AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)";

        $stmt = $this->database->executeQuery($userSql, [$hoursBack]);
        $activeUsers = $stmt->fetch(\PDO::FETCH_ASSOC)['active_users'];

        return [
            'activity_summary' => $summary,
            'active_users' => (int) $activeUsers,
            'period_hours' => $hoursBack
        ];
    }

    /**
     * Get feed events for dashboard
     */
    public function getDashboardFeed(int $limit = 20): array
    {
        $sql = "SELECT 
                    fe.*,
                    u.username as actor_username,
                    u.email as actor_email
                FROM {$this->table} fe
                LEFT JOIN cis_users u ON fe.actor_type = 'User' AND fe.actor_id = u.id
                WHERE fe.visibility IN ('public', 'internal')
                ORDER BY fe.created_at DESC
                LIMIT ?";

        $stmt = $this->database->executeQuery($sql, [$limit]);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode data field and format for display
        foreach ($events as &$event) {
            $event['data'] = $event['data'] ? json_decode($event['data'], true) : null;
            $event['formatted_message'] = $this->formatEventMessage($event);
            $event['icon'] = $this->getEventIcon($event);
            $event['color'] = $this->getEventColor($event);
        }

        return $events;
    }

    /**
     * Format event message for display
     */
    protected function formatEventMessage(array $event): string
    {
        $actor = $event['actor_username'] ?: 'System';
        $action = $event['action'];
        $target = $event['target_type'];

        switch ($event['event_type']) {
            case self::TYPE_USER_ACTION:
                return "{$actor} {$action}" . ($target ? " {$target}" : "");
            
            case self::TYPE_SYSTEM_EVENT:
                return "System {$action}" . ($target ? " {$target}" : "");
            
            case self::TYPE_AUDIT_EVENT:
                return "{$actor} performed audit action: {$action}";
            
            case self::TYPE_INTEGRATION_EVENT:
                return "Integration {$event['target_type']}: {$action}";
            
            default:
                return "{$actor}: {$action}";
        }
    }

    /**
     * Get icon for event type
     */
    protected function getEventIcon(array $event): string
    {
        switch ($event['event_type']) {
            case self::TYPE_USER_ACTION:
                return 'fas fa-user';
            case self::TYPE_SYSTEM_EVENT:
                return 'fas fa-cog';
            case self::TYPE_AUDIT_EVENT:
                return 'fas fa-shield-alt';
            case self::TYPE_INTEGRATION_EVENT:
                return 'fas fa-plug';
            default:
                return 'fas fa-circle';
        }
    }

    /**
     * Get color for event type
     */
    protected function getEventColor(array $event): string
    {
        switch ($event['event_type']) {
            case self::TYPE_USER_ACTION:
                return 'primary';
            case self::TYPE_SYSTEM_EVENT:
                return 'info';
            case self::TYPE_AUDIT_EVENT:
                return 'warning';
            case self::TYPE_INTEGRATION_EVENT:
                return 'success';
            default:
                return 'secondary';
        }
    }

    /**
     * Search feed events
     */
    public function searchEvents(array $criteria, int $limit = 50): array
    {
        $conditions = [];
        $params = [];

        if (!empty($criteria['action'])) {
            $conditions[] = "action LIKE ?";
            $params[] = '%' . $criteria['action'] . '%';
        }

        if (!empty($criteria['actor_id'])) {
            $conditions[] = "actor_id = ?";
            $params[] = $criteria['actor_id'];
        }

        if (!empty($criteria['event_type'])) {
            $conditions[] = "event_type = ?";
            $params[] = $criteria['event_type'];
        }

        if (!empty($criteria['target_type'])) {
            $conditions[] = "target_type = ?";
            $params[] = $criteria['target_type'];
        }

        if (!empty($criteria['visibility'])) {
            $conditions[] = "visibility = ?";
            $params[] = $criteria['visibility'];
        }

        if (!empty($criteria['date_from'])) {
            $conditions[] = "created_at >= ?";
            $params[] = $criteria['date_from'];
        }

        if (!empty($criteria['date_to'])) {
            $conditions[] = "created_at <= ?";
            $params[] = $criteria['date_to'];
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT 
                    fe.*,
                    u.username as actor_username,
                    u.email as actor_email
                FROM {$this->table} fe
                LEFT JOIN cis_users u ON fe.actor_type = 'User' AND fe.actor_id = u.id
                {$whereClause}
                ORDER BY fe.created_at DESC
                LIMIT ?";

        $params[] = $limit;
        $stmt = $this->database->executeQuery($sql, $params);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode data field
        foreach ($events as &$event) {
            $event['data'] = $event['data'] ? json_decode($event['data'], true) : null;
        }

        return $events;
    }

    /**
     * Get feed analytics
     */
    public function getAnalytics(int $daysBack = 30): array
    {
        // Daily activity counts
        $dailySql = "SELECT 
                        DATE(created_at) as date,
                        event_type,
                        COUNT(*) as count
                     FROM {$this->table}
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                     GROUP BY DATE(created_at), event_type
                     ORDER BY date DESC";

        $stmt = $this->database->executeQuery($dailySql, [$daysBack]);
        $dailyActivity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Top actions
        $actionSql = "SELECT 
                         action,
                         COUNT(*) as count
                      FROM {$this->table}
                      WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                      GROUP BY action
                      ORDER BY count DESC
                      LIMIT 10";

        $stmt = $this->database->executeQuery($actionSql, [$daysBack]);
        $topActions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Most active users
        $userSql = "SELECT 
                       fe.actor_id,
                       u.username,
                       u.email,
                       COUNT(*) as action_count
                    FROM {$this->table} fe
                    LEFT JOIN cis_users u ON fe.actor_id = u.id
                    WHERE fe.actor_type = 'User'
                    AND fe.created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY fe.actor_id
                    ORDER BY action_count DESC
                    LIMIT 10";

        $stmt = $this->database->executeQuery($userSql, [$daysBack]);
        $activeUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'daily_activity' => $dailyActivity,
            'top_actions' => $topActions,
            'most_active_users' => $activeUsers,
            'period_days' => $daysBack
        ];
    }

    /**
     * Clean up old feed events
     */
    public function cleanup(int $daysToKeep = 90): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $stmt = $this->database->executeQuery($sql, [$daysToKeep]);
        return $stmt->rowCount();
    }

    /**
     * Get events related to specific target
     */
    public function getTargetEvents(string $targetType, int $targetId, int $limit = 50): array
    {
        $sql = "SELECT 
                    fe.*,
                    u.username as actor_username,
                    u.email as actor_email
                FROM {$this->table} fe
                LEFT JOIN cis_users u ON fe.actor_type = 'User' AND fe.actor_id = u.id
                WHERE fe.target_type = ? AND fe.target_id = ?
                ORDER BY fe.created_at DESC
                LIMIT ?";

        $stmt = $this->database->executeQuery($sql, [$targetType, $targetId, $limit]);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode data field
        foreach ($events as &$event) {
            $event['data'] = $event['data'] ? json_decode($event['data'], true) : null;
        }

        return $events;
    }
}
