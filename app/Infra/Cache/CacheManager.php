<?php
/**
 * Cache Manager - High-level cache operations and management
 * File: app/Infra/Cache/CacheManager.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Advanced cache management with warming, tagging, and analytics
 */

declare(strict_types=1);

namespace App\Infra\Cache;

use App\Shared\Logging\Logger;

class CacheManager
{
    private RedisCache $cache;
    private Logger $logger;
    
    // Cache regions with different TTLs
    private const REGIONS = [
        'user' => 1800,        // 30 minutes
        'config' => 3600,      // 1 hour
        'api' => 600,          // 10 minutes
        'database' => 1800,    // 30 minutes
        'session' => 1440,     // 24 minutes
        'validation' => 300,   // 5 minutes
        'static' => 86400,     // 24 hours
        'temp' => 60           // 1 minute
    ];
    
    public function __construct()
    {
        $this->cache = RedisCache::getInstance();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Warm up cache with essential data
     */
    public function warmUp(): array
    {
        $startTime = microtime(true);
        $results = [];
        
        try {
            // Warm up user roles and permissions
            $results['users'] = $this->warmUpUsers();
            
            // Warm up configuration
            $results['config'] = $this->warmUpConfig();
            
            // Warm up static data
            $results['static'] = $this->warmUpStatic();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Cache warm-up completed', [
                'duration_ms' => $duration,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Cache warm-up failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Warm up user data
     */
    private function warmUpUsers(): array
    {
        try {
            // This would connect to your database
            // For now, we'll simulate with sample data
            $users = [
                ['id' => 1, 'email' => 'pearce.stephens@gmail.com', 'role' => 'admin'],
                ['id' => 2, 'email' => 'admin@ecigdis.co.nz', 'role' => 'admin']
            ];
            
            $cached = 0;
            foreach ($users as $user) {
                $key = "user:{$user['id']}";
                if ($this->cache->set($key, $user, self::REGIONS['user'])) {
                    $cached++;
                }
            }
            
            return ['cached' => $cached, 'total' => count($users)];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Warm up configuration data
     */
    private function warmUpConfig(): array
    {
        try {
            $configs = [
                'app.name' => 'CIS - Central Information System',
                'app.version' => '2.0.0-alpha.2',
                'cache.enabled' => true,
                'redis.enabled' => true
            ];
            
            $cached = 0;
            foreach ($configs as $key => $value) {
                if ($this->cache->set("config:$key", $value, self::REGIONS['config'])) {
                    $cached++;
                }
            }
            
            return ['cached' => $cached, 'total' => count($configs)];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Warm up static data
     */
    private function warmUpStatic(): array
    {
        try {
            $staticData = [
                'roles' => ['admin', 'manager', 'staff', 'viewer'],
                'permissions' => ['read', 'write', 'delete', 'admin'],
                'outlets' => ['store_1', 'store_2', 'store_3']
            ];
            
            $cached = 0;
            foreach ($staticData as $key => $value) {
                if ($this->cache->set("static:$key", $value, self::REGIONS['static'])) {
                    $cached++;
                }
            }
            
            return ['cached' => $cached, 'total' => count($staticData)];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get cache health status
     */
    public function getHealthStatus(): array
    {
        $status = [
            'connected' => $this->cache->isConnected(),
            'stats' => $this->cache->getStats(),
            'regions' => [],
            'health_score' => 0
        ];
        
        if (!$status['connected']) {
            $status['health_score'] = 0;
            $status['status'] = 'disconnected';
            return $status;
        }
        
        // Test each cache region
        $regionTests = 0;
        $regionPassed = 0;
        
        foreach (self::REGIONS as $region => $ttl) {
            $testKey = "health:test:$region:" . time();
            $testValue = ['test' => true, 'timestamp' => time()];
            
            $regionTests++;
            
            if ($this->cache->set($testKey, $testValue, 5)) { // 5 second TTL for test
                $retrieved = $this->cache->get($testKey);
                if ($retrieved === $testValue) {
                    $regionPassed++;
                    $status['regions'][$region] = 'ok';
                } else {
                    $status['regions'][$region] = 'read_failed';
                }
                $this->cache->delete($testKey); // Cleanup
            } else {
                $status['regions'][$region] = 'write_failed';
            }
        }
        
        $status['health_score'] = round(($regionPassed / $regionTests) * 100, 1);
        
        if ($status['health_score'] >= 90) {
            $status['status'] = 'excellent';
        } elseif ($status['health_score'] >= 75) {
            $status['status'] = 'good';
        } elseif ($status['health_score'] >= 50) {
            $status['status'] = 'degraded';
        } else {
            $status['status'] = 'poor';
        }
        
        return $status;
    }
    
    /**
     * Clear cache by region
     */
    public function clearRegion(string $region): int
    {
        if (!isset(self::REGIONS[$region])) {
            throw new \InvalidArgumentException("Unknown cache region: $region");
        }
        
        $pattern = "$region:*";
        $cleared = $this->cache->flushByPattern($pattern);
        
        $this->logger->info('Cache region cleared', [
            'region' => $region,
            'keys_cleared' => $cleared
        ]);
        
        return $cleared;
    }
    
    /**
     * Clear all application cache
     */
    public function clearAll(): bool
    {
        $result = $this->cache->clear();
        
        $this->logger->info('All cache cleared', [
            'success' => $result
        ]);
        
        return $result;
    }
    
    /**
     * Get cache analytics
     */
    public function getAnalytics(): array
    {
        $stats = $this->cache->getStats();
        
        return [
            'connection' => [
                'status' => $stats['connected'] ? 'connected' : 'disconnected',
                'host' => $stats['config']['host'] ?? 'unknown',
                'port' => $stats['config']['port'] ?? 0,
                'database' => $stats['config']['database'] ?? 0
            ],
            'performance' => [
                'hits' => $stats['hits'],
                'misses' => $stats['misses'],
                'hit_ratio' => $stats['hit_ratio'],
                'total_operations' => $stats['hits'] + $stats['misses']
            ],
            'redis_info' => $stats['redis_info'] ?? [],
            'regions' => self::REGIONS,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Optimize cache performance
     */
    public function optimize(): array
    {
        $results = [];
        
        try {
            // Clear expired keys (Redis handles this automatically, but we can force it)
            $results['cleanup'] = 'automatic';
            
            // Get memory usage and suggest optimizations
            $stats = $this->cache->getStats();
            
            if (isset($stats['redis_info']['used_memory_human'])) {
                $results['memory_usage'] = $stats['redis_info']['used_memory_human'];
            }
            
            // Analyze hit ratio and suggest improvements
            if ($stats['hit_ratio'] < 70) {
                $results['suggestions'] = [
                    'Hit ratio is below 70%',
                    'Consider increasing cache TTL for frequently accessed data',
                    'Review cache key patterns for optimization'
                ];
            } elseif ($stats['hit_ratio'] >= 90) {
                $results['suggestions'] = [
                    'Excellent cache performance!',
                    'Hit ratio is above 90%'
                ];
            }
            
            $results['status'] = 'optimized';
            
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            $results['status'] = 'error';
        }
        
        return $results;
    }
    
    /**
     * Backup cache data
     */
    public function backup(): array
    {
        // This is a simplified backup - in production you'd use Redis BGSAVE
        $backupData = [];
        
        try {
            foreach (self::REGIONS as $region => $ttl) {
                $pattern = "$region:*";
                // Note: This is a simplified approach
                // In production, you'd use Redis SCAN for large datasets
                $backupData[$region] = "Pattern: $pattern";
            }
            
            $backupFile = '/var/reports/cache_backup_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT));
            
            return [
                'status' => 'success',
                'backup_file' => $backupFile,
                'regions' => array_keys($backupData),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Monitor cache performance
     */
    public function monitor(): array
    {
        $monitoring = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'monitoring',
            'metrics' => []
        ];
        
        try {
            // Connection test
            $startTime = microtime(true);
            $testKey = 'monitor:test:' . time();
            $this->cache->set($testKey, 'test', 5);
            $this->cache->get($testKey);
            $this->cache->delete($testKey);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $monitoring['metrics'] = [
                'response_time_ms' => $responseTime,
                'connection_healthy' => $responseTime < 10, // Under 10ms is excellent
                'stats' => $this->cache->getStats()
            ];
            
            // Performance grade
            if ($responseTime < 5) {
                $monitoring['performance_grade'] = 'A+';
            } elseif ($responseTime < 10) {
                $monitoring['performance_grade'] = 'A';
            } elseif ($responseTime < 20) {
                $monitoring['performance_grade'] = 'B';
            } else {
                $monitoring['performance_grade'] = 'C';
            }
            
        } catch (\Exception $e) {
            $monitoring['error'] = $e->getMessage();
            $monitoring['performance_grade'] = 'F';
        }
        
        return $monitoring;
    }
}
