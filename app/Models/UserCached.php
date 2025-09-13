<?php
/**
 * Enhanced User Model with Redis Cache Integration  
 * File: app/Models/User.php
 * Author: CIS Developer Bot
 * Updated: 2025-09-11
 * Purpose: User model with advanced caching for authentication and user data
 */

declare(strict_types=1);

namespace App\Models;

use App\Infra\Cache\CacheTrait;

class User extends BaseModel
{
    use CacheTrait;
    
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'username', 'email', 'password', 'first_name', 'last_name', 
        'phone', 'role_id', 'status', 'last_login', 'preferences'
    ];
    protected array $hidden = ['password'];
    protected bool $useCache = true;
    protected int $cacheTtl = 3600; // 1 hour for user data
    
    /**
     * Find user by username with caching
     */
    public function findByUsername(string $username): ?array
    {
        return $this->cacheQuery(
            "user:username:$username",
            fn() => $this->findByUsernameFromDb($username),
            $this->cacheTtl
        );
    }
    
    /**
     * Find user by username from database
     */
    private function findByUsernameFromDb(string $username): ?array
    {
        try {
            $stmt = $this->db->execute(
                "SELECT u.*, r.name as role_name, r.permissions 
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 WHERE u.username = ? AND u.status = 'active'",
                [$username]
            );
            
            $user = $stmt->fetch();
            return $user ? $this->filterHidden($user) : null;
            
        } catch (\Exception $e) {
            $this->logger->error('Find user by username failed', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Find user by email with caching
     */
    public function findByEmail(string $email): ?array
    {
        return $this->cacheQuery(
            "user:email:" . md5($email),
            fn() => $this->findByEmailFromDb($email),
            $this->cacheTtl
        );
    }
    
    /**
     * Find user by email from database
     */
    private function findByEmailFromDb(string $email): ?array
    {
        try {
            $stmt = $this->db->execute(
                "SELECT u.*, r.name as role_name, r.permissions 
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 WHERE u.email = ? AND u.status = 'active'",
                [$email]
            );
            
            $user = $stmt->fetch();
            return $user ? $this->filterHidden($user) : null;
            
        } catch (\Exception $e) {
            $this->logger->error('Find user by email failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Verify user password and cache authentication
     */
    public function verifyPassword(string $username, string $password): ?array
    {
        // First check if we have a recent auth cache
        $authCacheKey = "auth:verify:" . md5($username . ':' . $password);
        
        $cached = $this->cache()->get($authCacheKey);
        if ($cached !== null) {
            $this->logger->info('Password verification from cache', ['username' => $username]);
            return $cached;
        }
        
        try {
            // Get user with password for verification
            $stmt = $this->db->execute(
                "SELECT u.*, r.name as role_name, r.permissions 
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 WHERE u.username = ? AND u.status = 'active'",
                [$username]
            );
            
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                $this->logger->warning('Password verification failed', ['username' => $username]);
                return null;
            }
            
            // Remove password from result
            unset($user['password']);
            
            // Cache successful authentication for 5 minutes
            $this->cache()->set($authCacheKey, $user, 300);
            
            $this->logger->info('Password verification successful', ['username' => $username]);
            return $user;
            
        } catch (\Exception $e) {
            $this->logger->error('Password verification error', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Update last login time and invalidate cache
     */
    public function updateLastLogin(int $userId): bool
    {
        try {
            $stmt = $this->db->execute(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$userId]
            );
            
            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                // Invalidate user caches
                $this->cache()->deletePattern("user:*:$userId");
                $this->cache()->deletePattern("auth:*");
            }
            
            return $success;
            
        } catch (\Exception $e) {
            $this->logger->error('Update last login failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get user permissions with caching
     */
    public function getPermissions(int $userId): array
    {
        return $this->cacheQuery(
            "user:permissions:$userId",
            fn() => $this->getPermissionsFromDb($userId),
            1800 // 30 minutes
        );
    }
    
    /**
     * Get user permissions from database
     */
    private function getPermissionsFromDb(int $userId): array
    {
        try {
            $stmt = $this->db->execute(
                "SELECT r.permissions 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.id = ? AND u.status = 'active'",
                [$userId]
            );
            
            $result = $stmt->fetch();
            
            if (!$result || !$result['permissions']) {
                return [];
            }
            
            return json_decode($result['permissions'], true) ?: [];
            
        } catch (\Exception $e) {
            $this->logger->error('Get user permissions failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get active users with caching
     */
    public function getActiveUsers(int $limit = 100, int $offset = 0): array
    {
        return $this->cacheQuery(
            "users:active:$limit:$offset",
            fn() => $this->getActiveUsersFromDb($limit, $offset),
            600 // 10 minutes
        );
    }
    
    /**
     * Get active users from database
     */
    private function getActiveUsersFromDb(int $limit, int $offset): array
    {
        try {
            $stmt = $this->db->execute(
                "SELECT u.id, u.username, u.email, u.first_name, u.last_name, 
                        u.last_login, u.created_at, r.name as role_name
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 WHERE u.status = 'active' 
                 ORDER BY u.last_login DESC, u.username ASC 
                 LIMIT ? OFFSET ?",
                [$limit, $offset]
            );
            
            return $stmt->fetchAll();
            
        } catch (\Exception $e) {
            $this->logger->error('Get active users failed', [
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Search users with caching
     */
    public function searchUsers(string $query, int $limit = 50): array
    {
        $cacheKey = "users:search:" . md5($query) . ":$limit";
        
        return $this->cacheQuery(
            $cacheKey,
            fn() => $this->searchUsersFromDb($query, $limit),
            300 // 5 minutes
        );
    }
    
    /**
     * Search users from database
     */
    private function searchUsersFromDb(string $query, int $limit): array
    {
        try {
            $searchTerm = "%$query%";
            
            $stmt = $this->db->execute(
                "SELECT u.id, u.username, u.email, u.first_name, u.last_name, 
                        r.name as role_name
                 FROM users u 
                 LEFT JOIN roles r ON u.role_id = r.id 
                 WHERE u.status = 'active' 
                 AND (u.username LIKE ? OR u.email LIKE ? OR 
                      u.first_name LIKE ? OR u.last_name LIKE ?)
                 ORDER BY u.username ASC 
                 LIMIT ?",
                [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]
            );
            
            return $stmt->fetchAll();
            
        } catch (\Exception $e) {
            $this->logger->error('Search users failed', [
                'query' => $query,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get user statistics with caching
     */
    public function getUserStats(): array
    {
        return $this->cacheQuery(
            'users:stats',
            fn() => $this->getUserStatsFromDb(),
            1800 // 30 minutes
        );
    }
    
    /**
     * Get user statistics from database
     */
    private function getUserStatsFromDb(): array
    {
        try {
            $stmt = $this->db->execute(
                "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
                    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as active_24h,
                    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_7d,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_30d
                 FROM users"
            );
            
            return $stmt->fetch() ?: [];
            
        } catch (\Exception $e) {
            $this->logger->error('Get user stats failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Create user with cache invalidation
     */
    public function create(array $data): ?int
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        }
        
        $userId = parent::create($data);
        
        if ($userId) {
            // Invalidate user-related caches
            $this->cache()->deletePattern('users:*');
            $this->cache()->deletePattern('auth:*');
        }
        
        return $userId;
    }
    
    /**
     * Update user with cache invalidation
     */
    public function update(int $id, array $data): bool
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        }
        
        $success = parent::update($id, $data);
        
        if ($success) {
            // Invalidate specific user caches
            $this->cache()->deletePattern("user:*:$id");
            $this->cache()->deletePattern('users:*');
            $this->cache()->deletePattern('auth:*');
        }
        
        return $success;
    }
    
    /**
     * Delete user with cache invalidation
     */
    public function delete(int $id): bool
    {
        $success = parent::delete($id);
        
        if ($success) {
            // Invalidate user-related caches
            $this->cache()->deletePattern("user:*:$id");
            $this->cache()->deletePattern('users:*');
            $this->cache()->deletePattern('auth:*');
        }
        
        return $success;
    }
    
    /**
     * Clear authentication cache for user
     */
    public function clearAuthCache(int $userId): void
    {
        $this->cache()->deletePattern("user:*:$userId");
        $this->cache()->deletePattern("auth:*");
        
        $this->logger->info('User auth cache cleared', ['user_id' => $userId]);
    }
    
    /**
     * Warm up user cache
     */
    public function warmCache(): array
    {
        $results = [];
        
        try {
            // Warm up user stats
            $results['stats'] = $this->getUserStats();
            
            // Warm up recent active users
            $results['active_users'] = $this->getActiveUsers(50);
            
            $this->logger->info('User cache warmed', $results);
            
        } catch (\Exception $e) {
            $this->logger->error('User cache warm failed', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $results;
    }
}
