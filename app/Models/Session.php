<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Session Model
 * 
 * Handles user sessions and CSRF tokens
 */
class Session extends BaseModel
{
    protected string $table = 'cis_user_sessions';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'csrf_token',
        'is_active',
        'expires_at'
    ];

    /**
     * Create new session
     */
    public function createSession(int $userId, string $ipAddress, string $userAgent, int $expiresInMinutes = 120): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $csrfToken = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInMinutes * 60));

        $this->create([
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'csrf_token' => $csrfToken,
            'is_active' => 1,
            'expires_at' => $expiresAt
        ]);

        return $sessionId;
    }

    /**
     * Get active session
     */
    public function getActiveSession(string $sessionId): ?array
    {
        $sql = "SELECT s.*, u.email, u.first_name, u.last_name, u.role 
                FROM {$this->table} s
                JOIN cis_users u ON s.user_id = u.id
                WHERE s.id = ? AND s.is_active = 1 AND s.expires_at > NOW() AND u.is_active = 1
                LIMIT 1";

        $stmt = $this->database->executeQuery($sql, [$sessionId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            // Update last activity
            $this->updateActivity($sessionId);
        }

        return $result ?: null;
    }

    /**
     * Update session activity
     */
    public function updateActivity(string $sessionId): void
    {
        $sql = "UPDATE {$this->table} SET last_activity = NOW() WHERE id = ?";
        $this->database->executeQuery($sql, [$sessionId]);
    }

    /**
     * Invalidate session
     */
    public function invalidateSession(string $sessionId): bool
    {
        $sql = "UPDATE {$this->table} SET is_active = 0 WHERE id = ?";
        $stmt = $this->database->executeQuery($sql, [$sessionId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Invalidate all user sessions
     */
    public function invalidateUserSessions(int $userId): int
    {
        $sql = "UPDATE {$this->table} SET is_active = 0 WHERE user_id = ?";
        $stmt = $this->database->executeQuery($sql, [$userId]);

        return $stmt->rowCount();
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions(): int
    {
        $sql = "DELETE FROM {$this->table} WHERE expires_at < NOW() OR is_active = 0";
        $stmt = $this->database->executeQuery($sql);

        return $stmt->rowCount();
    }

    /**
     * Get user's active sessions
     */
    public function getUserSessions(int $userId): array
    {
        $sql = "SELECT id, ip_address, user_agent, created_at, last_activity, expires_at
                FROM {$this->table} 
                WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
                ORDER BY last_activity DESC";

        $stmt = $this->database->executeQuery($sql, [$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken(string $sessionId, string $csrfToken): bool
    {
        $sql = "SELECT 1 FROM {$this->table} 
                WHERE id = ? AND csrf_token = ? AND is_active = 1 AND expires_at > NOW()
                LIMIT 1";

        $stmt = $this->database->executeQuery($sql, [$sessionId, $csrfToken]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Regenerate CSRF token
     */
    public function regenerateCsrfToken(string $sessionId): string
    {
        $newCsrfToken = bin2hex(random_bytes(16));
        
        $sql = "UPDATE {$this->table} SET csrf_token = ? WHERE id = ?";
        $this->database->executeQuery($sql, [$newCsrfToken, $sessionId]);

        return $newCsrfToken;
    }

    /**
     * Get session statistics
     */
    public function getSessionStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN is_active = 1 AND expires_at > NOW() THEN 1 END) as active_sessions,
                    COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_sessions,
                    COUNT(DISTINCT user_id) as unique_users
                FROM {$this->table}";

        $stmt = $this->database->executeQuery($sql);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
