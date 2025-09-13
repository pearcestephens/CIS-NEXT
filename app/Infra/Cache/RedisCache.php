<?php
/**
 * Redis Cache Service - Production-Ready Cache Integration
 * File: app/Infra/Cache/RedisCache.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: High-performance Redis caching with comprehensive features
 */

declare(strict_types=1);

namespace App\Infra\Cache;

use Redis;
use RedisException;
use App\Shared\Logging\Logger;
use App\Shared\Config\ConfigService;

class RedisCache
{
    private static ?RedisCache $instance = null;
    private ?Redis $redis = null;
    private Logger $logger;
    private array $config;
    private bool $connected = false;
    private int $hitCount = 0;
    private int $missCount = 0;
    private array $stats = [];
    
    // Default cache TTLs (seconds)
    private const DEFAULT_TTL = 3600; // 1 hour
    private const SHORT_TTL = 300;    // 5 minutes
    private const LONG_TTL = 86400;   // 24 hours
    private const WEEK_TTL = 604800;  // 7 days
    
    private function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->config = $this->loadConfig();
        $this->connect();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load Redis configuration
     */
    private function loadConfig(): array
    {
        return [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => (int)($_ENV['REDIS_DATABASE'] ?? 0),
            'timeout' => (float)($_ENV['REDIS_TIMEOUT'] ?? 2.0),
            'prefix' => $_ENV['REDIS_PREFIX'] ?? 'cis:',
            'enabled' => filter_var($_ENV['REDIS_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'compression' => filter_var($_ENV['REDIS_COMPRESSION'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'serialization' => $_ENV['REDIS_SERIALIZATION'] ?? 'php'
        ];
    }
    
    /**
     * Connect to Redis server
     */
    private function connect(): void
    {
        if (!$this->config['enabled']) {
            $this->logger->info('Redis cache disabled via configuration');
            return;
        }
        
        if (!extension_loaded('redis')) {
            $this->logger->error('Redis PHP extension not loaded');
            return;
        }
        
        try {
            $this->redis = new Redis();
            
            $connected = $this->redis->connect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );
            
            if (!$connected) {
                throw new RedisException('Failed to connect to Redis server');
            }
            
            // Authenticate if password provided
            if ($this->config['password']) {
                $this->redis->auth($this->config['password']);
            }
            
            // Select database
            $this->redis->select($this->config['database']);
            
            // Configure serialization
            switch ($this->config['serialization']) {
                case 'igbinary':
                    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
                    break;
                case 'msgpack':
                    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_MSGPACK);
                    break;
                default:
                    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            }
            
            // Configure compression
            if ($this->config['compression']) {
                $this->redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZ4);
            }
            
            // Set key prefix
            $this->redis->setOption(Redis::OPT_PREFIX, $this->config['prefix']);
            
            $this->connected = true;
            
            $this->logger->info('Redis cache connected successfully', [
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'database' => $this->config['database']
            ]);
            
        } catch (RedisException $e) {
            $this->connected = false;
            $this->logger->error('Redis connection failed', [
                'error' => $e->getMessage(),
                'host' => $this->config['host'],
                'port' => $this->config['port']
            ]);
        }
    }
    
    /**
     * Check if Redis is available
     */
    public function isConnected(): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }
        
        try {
            $this->redis->ping();
            return true;
        } catch (RedisException $e) {
            $this->connected = false;
            return false;
        }
    }
    
    /**
     * Get value from cache
     */
    public function get(string $key, $default = null)
    {
        if (!$this->isConnected()) {
            $this->missCount++;
            return $default;
        }
        
        try {
            $value = $this->redis->get($key);
            
            if ($value === false) {
                $this->missCount++;
                return $default;
            }
            
            $this->hitCount++;
            return $value;
            
        } catch (RedisException $e) {
            $this->logger->error('Redis get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            $this->missCount++;
            return $default;
        }
    }
    
    /**
     * Set value in cache
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        $ttl = $ttl ?? self::DEFAULT_TTL;
        
        try {
            return $this->redis->setex($key, $ttl, $value);
            
        } catch (RedisException $e) {
            $this->logger->error('Redis set failed', [
                'key' => $key,
                'ttl' => $ttl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete key from cache
     */
    public function delete(string $key): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            return $this->redis->del($key) > 0;
            
        } catch (RedisException $e) {
            $this->logger->error('Redis delete failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Check if key exists
     */
    public function exists(string $key): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            return $this->redis->exists($key) > 0;
            
        } catch (RedisException $e) {
            $this->logger->error('Redis exists check failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get multiple values
     */
    public function getMultiple(array $keys): array
    {
        if (!$this->isConnected() || empty($keys)) {
            return [];
        }
        
        try {
            $values = $this->redis->mget($keys);
            $result = [];
            
            foreach ($keys as $index => $key) {
                $value = $values[$index] ?? false;
                if ($value !== false) {
                    $result[$key] = $value;
                    $this->hitCount++;
                } else {
                    $this->missCount++;
                }
            }
            
            return $result;
            
        } catch (RedisException $e) {
            $this->logger->error('Redis mget failed', [
                'keys' => $keys,
                'error' => $e->getMessage()
            ]);
            $this->missCount += count($keys);
            return [];
        }
    }
    
    /**
     * Set multiple values
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        if (!$this->isConnected() || empty($values)) {
            return false;
        }
        
        $ttl = $ttl ?? self::DEFAULT_TTL;
        
        try {
            $pipeline = $this->redis->multi();
            
            foreach ($values as $key => $value) {
                $pipeline->setex($key, $ttl, $value);
            }
            
            $results = $pipeline->exec();
            
            return !in_array(false, $results, true);
            
        } catch (RedisException $e) {
            $this->logger->error('Redis mset failed', [
                'count' => count($values),
                'ttl' => $ttl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clear all cache (use with caution)
     */
    public function clear(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            return $this->redis->flushDB();
            
        } catch (RedisException $e) {
            $this->logger->error('Redis flush failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $stats = [
            'connected' => $this->isConnected(),
            'hits' => $this->hitCount,
            'misses' => $this->missCount,
            'hit_ratio' => 0,
            'config' => $this->config
        ];
        
        if ($this->hitCount + $this->missCount > 0) {
            $stats['hit_ratio'] = round(
                ($this->hitCount / ($this->hitCount + $this->missCount)) * 100, 
                2
            );
        }
        
        if ($this->isConnected()) {
            try {
                $info = $this->redis->info();
                $stats['redis_info'] = [
                    'version' => $info['redis_version'] ?? 'unknown',
                    'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0
                ];
            } catch (RedisException $e) {
                $stats['redis_info_error'] = $e->getMessage();
            }
        }
        
        return $stats;
    }
    
    /**
     * Remember pattern - get from cache or execute callback
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Forever cache (very long TTL)
     */
    public function forever(string $key, $value): bool
    {
        return $this->set($key, $value, self::WEEK_TTL);
    }
    
    /**
     * Short-term cache
     */
    public function short(string $key, $value): bool
    {
        return $this->set($key, $value, self::SHORT_TTL);
    }
    
    /**
     * Long-term cache
     */
    public function long(string $key, $value): bool
    {
        return $this->set($key, $value, self::LONG_TTL);
    }
    
    /**
     * Increment counter
     */
    public function increment(string $key, int $value = 1): int
    {
        if (!$this->isConnected()) {
            return 0;
        }
        
        try {
            return $this->redis->incrBy($key, $value);
        } catch (RedisException $e) {
            $this->logger->error('Redis increment failed', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Decrement counter
     */
    public function decrement(string $key, int $value = 1): int
    {
        if (!$this->isConnected()) {
            return 0;
        }
        
        try {
            return $this->redis->decrBy($key, $value);
        } catch (RedisException $e) {
            $this->logger->error('Redis decrement failed', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Add to set
     */
    public function setAdd(string $key, string $member): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            return $this->redis->sAdd($key, $member) > 0;
        } catch (RedisException $e) {
            $this->logger->error('Redis set add failed', [
                'key' => $key,
                'member' => $member,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get all set members
     */
    public function setMembers(string $key): array
    {
        if (!$this->isConnected()) {
            return [];
        }
        
        try {
            return $this->redis->sMembers($key);
        } catch (RedisException $e) {
            $this->logger->error('Redis set members failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Push to list
     */
    public function listPush(string $key, string $value): int
    {
        if (!$this->isConnected()) {
            return 0;
        }
        
        try {
            return $this->redis->lPush($key, $value);
        } catch (RedisException $e) {
            $this->logger->error('Redis list push failed', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Pop from list
     */
    public function listPop(string $key): ?string
    {
        if (!$this->isConnected()) {
            return null;
        }
        
        try {
            $value = $this->redis->lPop($key);
            return $value === false ? null : $value;
        } catch (RedisException $e) {
            $this->logger->error('Redis list pop failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get cache key with namespace
     */
    public function key(string $key): string
    {
        return $this->config['prefix'] . $key;
    }
    
    /**
     * Flush cache by pattern
     */
    public function flushByPattern(string $pattern): int
    {
        if (!$this->isConnected()) {
            return 0;
        }
        
        try {
            $keys = $this->redis->keys($pattern);
            if (empty($keys)) {
                return 0;
            }
            
            return $this->redis->del($keys);
            
        } catch (RedisException $e) {
            $this->logger->error('Redis flush by pattern failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Close Redis connection
     */
    public function close(): void
    {
        if ($this->redis) {
            try {
                $this->redis->close();
            } catch (RedisException $e) {
                // Ignore close errors
            }
            $this->redis = null;
            $this->connected = false;
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close();
    }
}
