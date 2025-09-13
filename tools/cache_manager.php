<?php
/**
 * Cache CLI Management Tool
 * File: tools/cache_manager.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Command-line interface for Redis cache management
 */

require_once __DIR__ . '/../functions/config.php';
require_once __DIR__ . '/../app/Infra/Cache/RedisCache.php';
require_once __DIR__ . '/../app/Infra/Cache/CacheManager.php';

use App\Infra\Cache\RedisCache;
use App\Infra\Cache\CacheManager;

class CacheCLI {
    
    private RedisCache $cache;
    private CacheManager $manager;
    
    public function __construct() {
        $this->cache = RedisCache::getInstance();
        $this->manager = new CacheManager();
    }
    
    /**
     * Show cache status
     */
    public function status(): void {
        echo "=== Redis Cache Status ===" . PHP_EOL;
        
        $stats = $this->cache->getStats();
        $health = $this->manager->getHealthStatus();
        
        echo "Connection: " . ($stats['connected'] ? '✅ Connected' : '❌ Disconnected') . PHP_EOL;
        echo "Hit Ratio: {$stats['hit_ratio']}%" . PHP_EOL;
        echo "Hits: {$stats['hits']}" . PHP_EOL;
        echo "Misses: {$stats['misses']}" . PHP_EOL;
        echo "Health Score: {$health['health_score']}%" . PHP_EOL;
        
        if (isset($stats['redis_info'])) {
            echo "Redis Version: {$stats['redis_info']['version']}" . PHP_EOL;
            echo "Memory Usage: {$stats['redis_info']['used_memory_human']}" . PHP_EOL;
            echo "Connected Clients: {$stats['redis_info']['connected_clients']}" . PHP_EOL;
        }
        
        echo PHP_EOL . "Cache Regions:" . PHP_EOL;
        foreach ($health['regions'] ?? [] as $region => $status) {
            $icon = $status === 'ok' ? '✅' : '❌';
            echo "  $icon $region: $status" . PHP_EOL;
        }
    }
    
    /**
     * Warm up cache
     */
    public function warmup(): void {
        echo "🔥 Warming up cache..." . PHP_EOL;
        
        $results = $this->manager->warmUp();
        
        foreach ($results as $category => $result) {
            if (is_array($result) && isset($result['cached'])) {
                echo "  ✅ $category: {$result['cached']} items cached" . PHP_EOL;
            } elseif (is_array($result) && isset($result['error'])) {
                echo "  ❌ $category: {$result['error']}" . PHP_EOL;
            }
        }
        
        echo "✅ Cache warm-up completed!" . PHP_EOL;
    }
    
    /**
     * Test cache operations
     */
    public function test(): void {
        echo "🧪 Testing cache operations..." . PHP_EOL;
        
        $testKey = 'cli:test:' . time();
        $testData = ['message' => 'CLI test', 'timestamp' => time()];
        
        $startTime = microtime(true);
        
        // Test write
        echo "  Testing write... ";
        if ($this->cache->set($testKey, $testData, 30)) {
            echo "✅ OK" . PHP_EOL;
        } else {
            echo "❌ FAILED" . PHP_EOL;
            return;
        }
        
        // Test read
        echo "  Testing read... ";
        $retrieved = $this->cache->get($testKey);
        if ($retrieved === $testData) {
            echo "✅ OK" . PHP_EOL;
        } else {
            echo "❌ FAILED" . PHP_EOL;
        }
        
        // Test delete
        echo "  Testing delete... ";
        if ($this->cache->delete($testKey)) {
            echo "✅ OK" . PHP_EOL;
        } else {
            echo "❌ FAILED" . PHP_EOL;
        }
        
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        echo "✅ All tests completed in {$totalTime}ms" . PHP_EOL;
    }
    
    /**
     * Clear cache region
     */
    public function clearRegion(string $region): void {
        echo "🗑️  Clearing $region region..." . PHP_EOL;
        
        try {
            $cleared = $this->manager->clearRegion($region);
            echo "✅ Cleared $cleared keys from $region region" . PHP_EOL;
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    /**
     * Clear all cache
     */
    public function clearAll(): void {
        echo "💥 Clearing ALL cache..." . PHP_EOL;
        echo "⚠️  This will clear ALL cached data!" . PHP_EOL;
        echo "Are you sure? Type 'yes' to confirm: ";
        
        $handle = fopen("php://stdin", "r");
        $confirmation = trim(fgets($handle));
        fclose($handle);
        
        if ($confirmation !== 'yes') {
            echo "❌ Operation cancelled" . PHP_EOL;
            return;
        }
        
        if ($this->manager->clearAll()) {
            echo "✅ All cache cleared successfully" . PHP_EOL;
        } else {
            echo "❌ Failed to clear cache" . PHP_EOL;
        }
    }
    
    /**
     * Optimize cache
     */
    public function optimize(): void {
        echo "🚀 Optimizing cache..." . PHP_EOL;
        
        $results = $this->manager->optimize();
        
        if (isset($results['error'])) {
            echo "❌ Optimization failed: {$results['error']}" . PHP_EOL;
            return;
        }
        
        echo "✅ Cache optimization completed" . PHP_EOL;
        
        if (isset($results['suggestions'])) {
            echo "💡 Suggestions:" . PHP_EOL;
            foreach ($results['suggestions'] as $suggestion) {
                echo "  - $suggestion" . PHP_EOL;
            }
        }
    }
    
    /**
     * Monitor cache performance
     */
    public function monitor(int $duration = 60): void {
        echo "📊 Monitoring cache for {$duration} seconds..." . PHP_EOL;
        echo "Press Ctrl+C to stop" . PHP_EOL . PHP_EOL;
        
        $startTime = time();
        $lastStats = null;
        
        while (time() - $startTime < $duration) {
            $monitoring = $this->manager->monitor();
            $currentTime = date('H:i:s');
            
            echo "[$currentTime] ";
            echo "Response: {$monitoring['metrics']['response_time_ms']}ms | ";
            
            $stats = $monitoring['metrics']['stats'];
            echo "Hit Ratio: {$stats['hit_ratio']}% | ";
            
            if ($lastStats) {
                $hitDelta = $stats['hits'] - $lastStats['hits'];
                $missDelta = $stats['misses'] - $lastStats['misses'];
                echo "Hits: +$hitDelta | Misses: +$missDelta";
            }
            
            echo PHP_EOL;
            
            $lastStats = $stats;
            sleep(5);
        }
        
        echo "✅ Monitoring completed" . PHP_EOL;
    }
    
    /**
     * Get cache analytics
     */
    public function analytics(): void {
        echo "📈 Cache Analytics" . PHP_EOL;
        echo "=================" . PHP_EOL;
        
        $analytics = $this->manager->getAnalytics();
        
        echo "Connection Status: {$analytics['connection']['status']}" . PHP_EOL;
        echo "Host: {$analytics['connection']['host']}:{$analytics['connection']['port']}" . PHP_EOL;
        echo "Database: {$analytics['connection']['database']}" . PHP_EOL . PHP_EOL;
        
        echo "Performance:" . PHP_EOL;
        echo "  Hits: {$analytics['performance']['hits']}" . PHP_EOL;
        echo "  Misses: {$analytics['performance']['misses']}" . PHP_EOL;
        echo "  Hit Ratio: {$analytics['performance']['hit_ratio']}%" . PHP_EOL;
        echo "  Total Operations: {$analytics['performance']['total_operations']}" . PHP_EOL . PHP_EOL;
        
        if (isset($analytics['redis_info']['version'])) {
            echo "Redis Info:" . PHP_EOL;
            echo "  Version: {$analytics['redis_info']['version']}" . PHP_EOL;
            echo "  Memory: {$analytics['redis_info']['used_memory_human']}" . PHP_EOL;
            echo "  Clients: {$analytics['redis_info']['connected_clients']}" . PHP_EOL;
        }
    }
    
    /**
     * Show help
     */
    public function help(): void {
        echo "Redis Cache Manager - CLI Tool" . PHP_EOL;
        echo "=============================" . PHP_EOL . PHP_EOL;
        
        echo "Usage: php cache_manager.php <command> [options]" . PHP_EOL . PHP_EOL;
        
        echo "Commands:" . PHP_EOL;
        echo "  status              Show cache connection and performance status" . PHP_EOL;
        echo "  warmup              Warm up cache with essential data" . PHP_EOL;
        echo "  test                Test cache read/write/delete operations" . PHP_EOL;
        echo "  clear <region>      Clear specific cache region" . PHP_EOL;
        echo "  clear-all           Clear all cache (requires confirmation)" . PHP_EOL;
        echo "  optimize            Optimize cache performance" . PHP_EOL;
        echo "  monitor [duration]  Monitor cache performance (default 60s)" . PHP_EOL;
        echo "  analytics           Show detailed cache analytics" . PHP_EOL;
        echo "  help                Show this help message" . PHP_EOL . PHP_EOL;
        
        echo "Cache Regions:" . PHP_EOL;
        echo "  user                User data cache" . PHP_EOL;
        echo "  config              Configuration cache" . PHP_EOL;
        echo "  api                 API response cache" . PHP_EOL;
        echo "  database            Database query cache" . PHP_EOL;
        echo "  session             Session data cache" . PHP_EOL;
        echo "  static              Static data cache" . PHP_EOL . PHP_EOL;
        
        echo "Examples:" . PHP_EOL;
        echo "  php cache_manager.php status" . PHP_EOL;
        echo "  php cache_manager.php warmup" . PHP_EOL;
        echo "  php cache_manager.php clear user" . PHP_EOL;
        echo "  php cache_manager.php monitor 120" . PHP_EOL;
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $cli = new CacheCLI();
    
    $command = $argv[1] ?? 'help';
    $arg = $argv[2] ?? null;
    
    switch ($command) {
        case 'status':
            $cli->status();
            break;
            
        case 'warmup':
            $cli->warmup();
            break;
            
        case 'test':
            $cli->test();
            break;
            
        case 'clear':
            if (!$arg) {
                echo "Error: Region name required" . PHP_EOL;
                echo "Usage: php cache_manager.php clear <region>" . PHP_EOL;
                exit(1);
            }
            $cli->clearRegion($arg);
            break;
            
        case 'clear-all':
            $cli->clearAll();
            break;
            
        case 'optimize':
            $cli->optimize();
            break;
            
        case 'monitor':
            $duration = $arg ? (int)$arg : 60;
            $cli->monitor($duration);
            break;
            
        case 'analytics':
            $cli->analytics();
            break;
            
        case 'help':
        default:
            $cli->help();
            break;
    }
} else {
    // Web interface
    header('Content-Type: application/json');
    echo json_encode(['error' => 'This tool is designed for CLI use']);
}
