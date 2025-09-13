<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Audit Model
 * 
 * Handles audit trail operations with PII redaction
 */
class Audit extends BaseModel
{
    protected string $table = 'cis_audit_log';
    protected array $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'session_id'
    ];

    // Sensitive fields to redact
    const REDACTED_FIELDS = [
        'password',
        'password_hash',
        'api_key',
        'secret',
        'token',
        'ssn',
        'credit_card',
        'bank_account'
    ];

    /**
     * Log an action with automatic PII redaction
     */
    public function logAction(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null
    ): int {
        // Redact sensitive information
        $oldValues = $this->redactSensitiveData($oldValues);
        $newValues = $this->redactSensitiveData($newValues);

        return $this->create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? null),
            'user_agent' => $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? null),
            'session_id' => $sessionId ?: (session_id() ?: null)
        ]);
    }

    /**
     * Redact sensitive data from arrays
     */
    protected function redactSensitiveData(?array $data): ?array
    {
        if (!$data) {
            return $data;
        }

        $redacted = $data;

        foreach (self::REDACTED_FIELDS as $field) {
            if (isset($redacted[$field])) {
                $redacted[$field] = '[REDACTED]';
            }
        }

        return $redacted;
    }

    /**
     * Log user authentication events
     */
    public function logAuth(int $userId, string $action, bool $successful = true, ?string $reason = null): int
    {
        $details = ['successful' => $successful];
        if ($reason) {
            $details['reason'] = $reason;
        }

        return $this->logAction(
            $userId,
            "auth.{$action}",
            'User',
            $userId,
            null,
            $details
        );
    }

    /**
     * Log permission changes
     */
    public function logPermissionChange(int $userId, int $targetUserId, array $oldPermissions, array $newPermissions): int
    {
        return $this->logAction(
            $userId,
            'permissions.update',
            'User',
            $targetUserId,
            ['permissions' => $oldPermissions],
            ['permissions' => $newPermissions]
        );
    }

    /**
     * Log configuration changes
     */
    public function logConfigChange(int $userId, string $configKey, $oldValue, $newValue): int
    {
        return $this->logAction(
            $userId,
            'config.update',
            'Configuration',
            null,
            ['key' => $configKey, 'value' => $oldValue],
            ['key' => $configKey, 'value' => $newValue]
        );
    }

    /**
     * Log data access
     */
    public function logDataAccess(int $userId, string $entityType, int $entityId, string $operation = 'view'): int
    {
        return $this->logAction(
            $userId,
            "data.{$operation}",
            $entityType,
            $entityId
        );
    }

    /**
     * Get audit trail for specific entity
     */
    public function getEntityAudit(string $entityType, int $entityId, int $limit = 50): array
    {
        $sql = "SELECT 
                    al.*,
                    u.username,
                    u.email
                FROM {$this->table} al
                LEFT JOIN cis_users u ON al.user_id = u.id
                WHERE al.entity_type = ? AND al.entity_id = ?
                ORDER BY al.created_at DESC
                LIMIT ?";

        $stmt = $this->database->executeQuery($sql, [$entityType, $entityId, $limit]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
        }

        return $logs;
    }

    /**
     * Get audit trail for user actions
     */
    public function getUserAudit(int $userId, int $limit = 50): array
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?";

        $stmt = $this->database->executeQuery($sql, [$userId, $limit]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
        }

        return $logs;
    }

    /**
     * Get recent audit activity
     */
    public function getRecentActivity(int $limit = 100, ?string $action = null): array
    {
        $conditions = [];
        $params = [];

        if ($action) {
            $conditions[] = "action = ?";
            $params[] = $action;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT 
                    al.*,
                    u.username,
                    u.email
                FROM {$this->table} al
                LEFT JOIN cis_users u ON al.user_id = u.id
                {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT ?";

        $params[] = $limit;
        $stmt = $this->database->executeQuery($sql, $params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
        }

        return $logs;
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats(int $daysBack = 30): array
    {
        $sql = "SELECT 
                    action,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM {$this->table}
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY action, DATE(created_at)
                ORDER BY date DESC, count DESC";

        $stmt = $this->database->executeQuery($sql, [$daysBack]);
        $stats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get top actions
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

        // Get unique users count
        $userSql = "SELECT COUNT(DISTINCT user_id) as unique_users
                    FROM {$this->table}
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND user_id IS NOT NULL";

        $stmt = $this->database->executeQuery($userSql, [$daysBack]);
        $uniqueUsers = $stmt->fetch(\PDO::FETCH_ASSOC)['unique_users'];

        return [
            'daily_stats' => $stats,
            'top_actions' => $topActions,
            'unique_users' => (int) $uniqueUsers,
            'total_events' => array_sum(array_column($topActions, 'count'))
        ];
    }

    /**
     * Search audit logs
     */
    public function searchAudit(array $criteria, int $limit = 50): array
    {
        $conditions = [];
        $params = [];

        if (!empty($criteria['user_id'])) {
            $conditions[] = "al.user_id = ?";
            $params[] = $criteria['user_id'];
        }

        if (!empty($criteria['action'])) {
            $conditions[] = "al.action LIKE ?";
            $params[] = '%' . $criteria['action'] . '%';
        }

        if (!empty($criteria['entity_type'])) {
            $conditions[] = "al.entity_type = ?";
            $params[] = $criteria['entity_type'];
        }

        if (!empty($criteria['ip_address'])) {
            $conditions[] = "al.ip_address = ?";
            $params[] = $criteria['ip_address'];
        }

        if (!empty($criteria['date_from'])) {
            $conditions[] = "al.created_at >= ?";
            $params[] = $criteria['date_from'];
        }

        if (!empty($criteria['date_to'])) {
            $conditions[] = "al.created_at <= ?";
            $params[] = $criteria['date_to'];
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT 
                    al.*,
                    u.username,
                    u.email
                FROM {$this->table} al
                LEFT JOIN cis_users u ON al.user_id = u.id
                {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT ?";

        $params[] = $limit;
        $stmt = $this->database->executeQuery($sql, $params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
        }

        return $logs;
    }

    /**
     * Clean up old audit logs
     */
    public function cleanup(int $daysToKeep = 90): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $stmt = $this->database->executeQuery($sql, [$daysToKeep]);
        return $stmt->rowCount();
    }
}
