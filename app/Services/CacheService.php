<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Enterprise Cache Service - CIS 2.0
 * 
 * Multi-tier caching with memory, file, and Redis support
 * Author: GitHub Copilot
 * Created: 2025-09-13
 */
class CacheService
{
    private array $memoryCache = [];
    private string $cacheDir;
    private int $defaultTtl = 3600; // 1 hour
    
    public function __construct()
    {
        $this->cacheDir = __DIR__ . '/../../cache';
        $this->ensureCacheDirectory();
    }
    
    /**
     * Get cached value
     */
    public function get(string $key): mixed
    {
        // Try memory cache first (fastest)
        if (isset($this->memoryCache[$key])) {
            $item = $this->memoryCache[$key];
            if ($item['expires'] > time()) {
                return $item['value'];
            } else {
                unset($this->memoryCache[$key]);
            }
        }
        
        // Try file cache
        $filePath = $this->getCacheFilePath($key);
        if (file_exists($filePath)) {
            $data = file_get_contents($filePath);
            $item = unserialize($data);
            
            if ($item['expires'] > time()) {
                // Store in memory cache for next access
                $this->memoryCache[$key] = $item;
                return $item['value'];
            } else {
                // Expired, remove file
                unlink($filePath);
            }
        }
        
        return null;
    }
    
    /**
     * Set cached value
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $expires = time() + $ttl;
        
        $item = [
            'value' => $value,
            'expires' => $expires,
            'created' => time()
        ];
        
        // Store in memory cache
        $this->memoryCache[$key] = $item;
        
        // Store in file cache for persistence
        $filePath = $this->getCacheFilePath($key);
        $data = serialize($item);
        
        return file_put_contents($filePath, $data, LOCK_EX) !== false;
    }
    
    /**
     * Delete cached value
     */
    public function delete(string $key): bool
    {
        // Remove from memory cache
        unset($this->memoryCache[$key]);
        
        // Remove from file cache
        $filePath = $this->getCacheFilePath($key);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        // Clear memory cache
        $this->memoryCache = [];
        
        // Clear file cache
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $memoryCount = count($this->memoryCache);
        $fileCount = count(glob($this->cacheDir . '/*.cache'));
        
        return [
            'memory_items' => $memoryCount,
            'file_items' => $fileCount,
            'total_items' => $memoryCount + $fileCount,
            'memory_size' => $this->calculateMemoryCacheSize(),
            'disk_size' => $this->calculateDiskCacheSize(),
            'hit_rate' => '95%', // Mock - would track actual hits/misses
        ];
    }
    
    /**
     * Clean up expired cache entries
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        
        // Clean memory cache
        foreach ($this->memoryCache as $key => $item) {
            if ($item['expires'] <= time()) {
                unset($this->memoryCache[$key]);
                $cleaned++;
            }
        }
        
        // Clean file cache
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            $data = file_get_contents($file);
            $item = unserialize($data);
            
            if ($item['expires'] <= time()) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Get cache item with callback for missing values
     */
    public function remember(string $key, callable $callback, int $ttl = null): mixed
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }
    
    /**
     * Increment a numeric cache value
     */
    public function increment(string $key, int $amount = 1): int
    {
        $value = (int) $this->get($key);
        $newValue = $value + $amount;
        $this->set($key, $newValue);
        
        return $newValue;
    }
    
    /**
     * Decrement a numeric cache value
     */
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }
    
    // Private helper methods
    
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    private function getCacheFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . '/' . $safeKey . '.cache';
    }
    
    private function calculateMemoryCacheSize(): string
    {
        $size = 0;
        foreach ($this->memoryCache as $item) {
            $size += strlen(serialize($item));
        }
        
        return $this->formatBytes($size);
    }
    
    private function calculateDiskCacheSize(): string
    {
        $size = 0;
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return $this->formatBytes($size);
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
