<?php
/**
 * Redis Cache Admin Controller - Management Interface
 * File: app/Http/Controllers/Admin/CacheController.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Admin interface for Redis cache management and monitoring
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Infra\Cache\RedisCache;
use App\Infra\Cache\CacheManager;
use App\Shared\Logging\Logger;

class CacheController extends BaseController
{
    private RedisCache $cache;
    private CacheManager $cacheManager;
    private Logger $logger;
    
    public function __construct()
    {
        parent::__construct();
        $this->cache = RedisCache::getInstance();
        $this->cacheManager = new CacheManager();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Cache dashboard
     */
    public function index(): void
    {
        if (!$this->hasPermission('admin')) {
            $this->redirect('/dashboard');
            return;
        }
        
        $data = [
            'cache_stats' => $this->cache->getStats(),
            'health_status' => $this->cacheManager->getHealthStatus(),
            'analytics' => $this->cacheManager->getAnalytics()
        ];
        
        $this->render('admin/cache/dashboard', $data);
    }
    
    /**
     * Get cache statistics (API)
     */
    public function stats(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        try {
            $stats = [
                'cache' => $this->cache->getStats(),
                'health' => $this->cacheManager->getHealthStatus(),
                'analytics' => $this->cacheManager->getAnalytics(),
                'monitor' => $this->cacheManager->monitor()
            ];
            
            echo json_encode($stats, JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Warm up cache
     */
    public function warmUp(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        try {
            $results = $this->cacheManager->warmUp();
            
            $this->logger->info('Cache warm-up initiated via admin panel', [
                'user_id' => $_SESSION['user']['id'] ?? null,
                'results' => $results
            ]);
            
            echo json_encode([
                'success' => true,
                'results' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            $this->logger->error('Cache warm-up failed', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user']['id'] ?? null
            ]);
            
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Clear cache by region
     */
    public function clearRegion(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $region = $_POST['region'] ?? $_GET['region'] ?? '';
        
        if (empty($region)) {
            http_response_code(400);
            echo json_encode(['error' => 'Region parameter required']);
            return;
        }
        
        try {
            $cleared = $this->cacheManager->clearRegion($region);
            
            $this->logger->info('Cache region cleared via admin panel', [
                'region' => $region,
                'keys_cleared' => $cleared,
                'user_id' => $_SESSION['user']['id'] ?? null
            ]);
            
            echo json_encode([
                'success' => true,
                'region' => $region,
                'keys_cleared' => $cleared,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            $this->logger->error('Cache region clear failed', [
                'region' => $region,
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user']['id'] ?? null
            ]);
            
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Clear all cache
     */
    public function clearAll(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // Require confirmation for clear all
        $confirm = $_POST['confirm'] ?? $_GET['confirm'] ?? '';
        
        if ($confirm !== 'yes') {
            http_response_code(400);
            echo json_encode(['error' => 'Confirmation required. Pass confirm=yes']);
            return;
        }
        
        try {
            $success = $this->cacheManager->clearAll();
            
            $this->logger->warning('All cache cleared via admin panel', [
                'success' => $success,
                'user_id' => $_SESSION['user']['id'] ?? null
            ]);
            
            echo json_encode([
                'success' => $success,
                'message' => 'All cache cleared',
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            $this->logger->error('Clear all cache failed', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user']['id'] ?? null
            ]);
            
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Optimize cache
     */
    public function optimize(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        try {
            $results = $this->cacheManager->optimize();
            
            $this->logger->info('Cache optimization performed', [
                'results' => $results,
                'user_id' => $_SESSION['user']['id'] ?? null
            ]);
            
            echo json_encode([
                'success' => true,
                'optimization' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Test cache operation
     */
    public function test(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        try {
            $testKey = 'admin:test:' . time();
            $testData = [
                'message' => 'Cache test successful',
                'timestamp' => time(),
                'random' => bin2hex(random_bytes(8))
            ];
            
            $startTime = microtime(true);
            
            // Test write
            $writeSuccess = $this->cache->set($testKey, $testData, 30);
            $writeTime = microtime(true);
            
            // Test read
            $readData = $this->cache->get($testKey);
            $readTime = microtime(true);
            
            // Test delete
            $deleteSuccess = $this->cache->delete($testKey);
            $deleteTime = microtime(true);
            
            $results = [
                'write' => [
                    'success' => $writeSuccess,
                    'duration_ms' => round(($writeTime - $startTime) * 1000, 2)
                ],
                'read' => [
                    'success' => $readData === $testData,
                    'duration_ms' => round(($readTime - $writeTime) * 1000, 2),
                    'data_matches' => $readData === $testData
                ],
                'delete' => [
                    'success' => $deleteSuccess,
                    'duration_ms' => round(($deleteTime - $readTime) * 1000, 2)
                ],
                'total_duration_ms' => round(($deleteTime - $startTime) * 1000, 2)
            ];
            
            $overallSuccess = $writeSuccess && ($readData === $testData) && $deleteSuccess;
            
            echo json_encode([
                'success' => $overallSuccess,
                'test_results' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Get cache key information
     */
    public function keyInfo(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $key = $_GET['key'] ?? '';
        
        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Key parameter required']);
            return;
        }
        
        try {
            $exists = $this->cache->exists($key);
            $value = null;
            $ttl = null;
            
            if ($exists) {
                $value = $this->cache->get($key);
                
                // Get TTL if Redis connection available
                if ($this->cache->isConnected()) {
                    // This would require accessing Redis directly for TTL
                    // For now, we'll indicate that the key exists
                    $ttl = 'unknown';
                }
            }
            
            echo json_encode([
                'key' => $key,
                'exists' => $exists,
                'value' => $value,
                'ttl' => $ttl,
                'value_type' => gettype($value),
                'serialized_size' => $value ? strlen(serialize($value)) : 0
            ], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Export cache analytics
     */
    public function export(): void
    {
        if (!$this->hasPermission('admin')) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Access denied';
            return;
        }
        
        try {
            $analytics = $this->cacheManager->getAnalytics();
            $stats = $this->cache->getStats();
            $health = $this->cacheManager->getHealthStatus();
            
            $export = [
                'export_timestamp' => date('Y-m-d H:i:s'),
                'analytics' => $analytics,
                'statistics' => $stats,
                'health_status' => $health
            ];
            
            $filename = 'cache_export_' . date('Y-m-d_H-i-s') . '.json';
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            echo json_encode($export, JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            header('Content-Type: text/plain');
            echo 'Export failed: ' . $e->getMessage();
        }
    }
}
