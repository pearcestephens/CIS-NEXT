<?php
/**
 * Cache Helper Trait - Easy Integration for Models and Controllers
 * File: app/Infra/Cache/CacheTrait.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Convenient caching methods for easy integration
 */

declare(strict_types=1);

namespace App\Infra\Cache;

trait CacheTrait
{
    private ?RedisCache $cache = null;
    
    /**
     * Get cache instance
     */
    protected function cache(): RedisCache
    {
        if ($this->cache === null) {
            $this->cache = RedisCache::getInstance();
        }
        return $this->cache;
    }
    
    /**
     * Cache a query result
     */
    protected function cacheQuery(string $cacheKey, callable $query, int $ttl = 3600)
    {
        return $this->cache()->remember($cacheKey, $query, $ttl);
    }
    
    /**
     * Cache user data
     */
    protected function cacheUser(int $userId, array $userData, int $ttl = 1800): bool
    {
        $key = "user:$userId";
        return $this->cache()->set($key, $userData, $ttl);
    }
    
    /**
     * Get cached user data
     */
    protected function getCachedUser(int $userId): ?array
    {
        $key = "user:$userId";
        return $this->cache()->get($key);
    }
    
    /**
     * Clear user cache
     */
    protected function clearUserCache(int $userId): bool
    {
        $key = "user:$userId";
        return $this->cache()->delete($key);
    }
    
    /**
     * Cache configuration data
     */
    protected function cacheConfig(string $configKey, $configValue, int $ttl = 3600): bool
    {
        $key = "config:$configKey";
        return $this->cache()->set($key, $configValue, $ttl);
    }
    
    /**
     * Get cached configuration
     */
    protected function getCachedConfig(string $configKey, $default = null)
    {
        $key = "config:$configKey";
        return $this->cache()->get($key, $default);
    }
    
    /**
     * Cache API response
     */
    protected function cacheApiResponse(string $endpoint, array $response, int $ttl = 600): bool
    {
        $key = "api:" . md5($endpoint);
        return $this->cache()->set($key, $response, $ttl);
    }
    
    /**
     * Get cached API response
     */
    protected function getCachedApiResponse(string $endpoint): ?array
    {
        $key = "api:" . md5($endpoint);
        return $this->cache()->get($key);
    }
    
    /**
     * Cache database results with automatic key generation
     */
    protected function cacheDbResult(string $table, array $conditions, array $result, int $ttl = 1800): bool
    {
        $key = "db:$table:" . md5(serialize($conditions));
        return $this->cache()->set($key, $result, $ttl);
    }
    
    /**
     * Get cached database result
     */
    protected function getCachedDbResult(string $table, array $conditions): ?array
    {
        $key = "db:$table:" . md5(serialize($conditions));
        return $this->cache()->get($key);
    }
    
    /**
     * Invalidate table cache
     */
    protected function invalidateTableCache(string $table): int
    {
        $pattern = "db:$table:*";
        return $this->cache()->flushByPattern($pattern);
    }
    
    /**
     * Cache count queries
     */
    protected function cacheCount(string $countKey, int $count, int $ttl = 900): bool
    {
        $key = "count:$countKey";
        return $this->cache()->set($key, $count, $ttl);
    }
    
    /**
     * Get cached count
     */
    protected function getCachedCount(string $countKey): ?int
    {
        $key = "count:$countKey";
        return $this->cache()->get($key);
    }
    
    /**
     * Cache session data
     */
    protected function cacheSession(string $sessionId, array $sessionData, int $ttl = 1440): bool
    {
        $key = "session:$sessionId";
        return $this->cache()->set($key, $sessionData, $ttl);
    }
    
    /**
     * Get cached session data
     */
    protected function getCachedSession(string $sessionId): ?array
    {
        $key = "session:$sessionId";
        return $this->cache()->get($key);
    }
    
    /**
     * Rate limiting helper
     */
    protected function checkRateLimit(string $identifier, int $maxRequests, int $windowSeconds): bool
    {
        $key = "rate_limit:$identifier";
        $current = $this->cache()->get($key, 0);
        
        if ($current >= $maxRequests) {
            return false; // Rate limit exceeded
        }
        
        $this->cache()->increment($key);
        
        // Set TTL on first request
        if ($current === 0) {
            $this->cache()->set($key, 1, $windowSeconds);
        }
        
        return true; // Request allowed
    }
    
    /**
     * Cache validation results
     */
    protected function cacheValidation(string $validationKey, bool $isValid, int $ttl = 300): bool
    {
        $key = "validation:$validationKey";
        return $this->cache()->set($key, $isValid, $ttl);
    }
    
    /**
     * Get cached validation result
     */
    protected function getCachedValidation(string $validationKey): ?bool
    {
        $key = "validation:$validationKey";
        return $this->cache()->get($key);
    }
}
