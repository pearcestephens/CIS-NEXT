<?php
/**
 * Enhanced BaseModel with Redis Cache Integration
 * File: app/Models/BaseModel.php
 * Author: CIS Developer Bot  
 * Created: 2025-09-11
 * Purpose: Update BaseModel to include caching capabilities
 */

declare(strict_types=1);

namespace App\Models;

use App\Infra\Persistence\MariaDB\Database;
use App\Infra\Cache\CacheTrait;
use App\Shared\Logging\Logger;

abstract class BaseModel
{
    use CacheTrait;
    
    protected Database $db;
    protected Logger $logger;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected bool $useCache = true;
    protected int $cacheTtl = 1800; // 30 minutes default
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Find record by ID with caching
     */
    public function find(int $id): ?array
    {
        if ($this->useCache) {
            return $this->cacheQuery(
                "model:{$this->table}:find:$id",
                fn() => $this->findFromDatabase($id),
                $this->cacheTtl
            );
        }
        
        return $this->findFromDatabase($id);
    }
    
    /**
     * Find record from database
     */
    protected function findFromDatabase(int $id): ?array
    {
        try {
            $stmt = $this->db->execute(
                "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?",
                [$id]
            );
            
            $record = $stmt->fetch();
            return $record ?: null;
            
        } catch (\Exception $e) {
            $this->logger->error('Database find failed', [
                'model' => static::class,
                'table' => $this->table,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Find multiple records with caching
     */
    public function findWhere(array $conditions, int $limit = 100, int $offset = 0): array
    {
        if ($this->useCache) {
            $cacheKey = "model:{$this->table}:where:" . md5(serialize($conditions) . ":$limit:$offset");
            return $this->cacheQuery(
                $cacheKey,
                fn() => $this->findWhereFromDatabase($conditions, $limit, $offset),
                $this->cacheTtl
            );
        }
        
        return $this->findWhereFromDatabase($conditions, $limit, $offset);
    }
    
    /**
     * Find records from database
     */
    protected function findWhereFromDatabase(array $conditions, int $limit, int $offset): array
    {
        try {
            $whereClauses = [];
            $params = [];
            
            foreach ($conditions as $column => $value) {
                $whereClauses[] = "$column = ?";
                $params[] = $value;
            }
            
            $whereClause = implode(' AND ', $whereClauses);
            $sql = "SELECT * FROM {$this->table} WHERE $whereClause LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->execute($sql, $params);
            return $stmt->fetchAll();
            
        } catch (\Exception $e) {
            $this->logger->error('Database findWhere failed', [
                'model' => static::class,
                'table' => $this->table,
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Count records with caching
     */
    public function count(array $conditions = []): int
    {
        if ($this->useCache) {
            $cacheKey = "model:{$this->table}:count:" . md5(serialize($conditions));
            return $this->cacheQuery(
                $cacheKey,
                fn() => $this->countFromDatabase($conditions),
                $this->cacheTtl
            );
        }
        
        return $this->countFromDatabase($conditions);
    }
    
    /**
     * Count records from database
     */
    protected function countFromDatabase(array $conditions = []): int
    {
        try {
            if (empty($conditions)) {
                $stmt = $this->db->execute("SELECT COUNT(*) FROM {$this->table}");
                return (int)$stmt->fetchColumn();
            }
            
            $whereClauses = [];
            $params = [];
            
            foreach ($conditions as $column => $value) {
                $whereClauses[] = "$column = ?";
                $params[] = $value;
            }
            
            $whereClause = implode(' AND ', $whereClauses);
            $stmt = $this->db->execute("SELECT COUNT(*) FROM {$this->table} WHERE $whereClause", $params);
            
            return (int)$stmt->fetchColumn();
            
        } catch (\Exception $e) {
            $this->logger->error('Database count failed', [
                'model' => static::class,
                'table' => $this->table,
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Create new record and invalidate cache
     */
    public function create(array $data): ?int
    {
        try {
            $filteredData = $this->filterFillable($data);
            
            $columns = array_keys($filteredData);
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            
            $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES ($placeholders)";
            
            $this->db->execute($sql, array_values($filteredData));
            $id = (int)$this->db->lastInsertId();
            
            // Invalidate relevant cache
            $this->invalidateCache();
            
            $this->logger->info('Record created', [
                'model' => static::class,
                'table' => $this->table,
                'id' => $id
            ]);
            
            return $id;
            
        } catch (\Exception $e) {
            $this->logger->error('Database create failed', [
                'model' => static::class,
                'table' => $this->table,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Update record and invalidate cache
     */
    public function update(int $id, array $data): bool
    {
        try {
            $filteredData = $this->filterFillable($data);
            
            $setClauses = [];
            $params = [];
            
            foreach ($filteredData as $column => $value) {
                $setClauses[] = "$column = ?";
                $params[] = $value;
            }
            
            $params[] = $id;
            
            $setClause = implode(', ', $setClauses);
            $sql = "UPDATE {$this->table} SET $setClause WHERE {$this->primaryKey} = ?";
            
            $stmt = $this->db->execute($sql, $params);
            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                // Invalidate relevant cache
                $this->invalidateCache();
                $this->cache()->delete("model:{$this->table}:find:$id");
            }
            
            $this->logger->info('Record updated', [
                'model' => static::class,
                'table' => $this->table,
                'id' => $id,
                'success' => $success
            ]);
            
            return $success;
            
        } catch (\Exception $e) {
            $this->logger->error('Database update failed', [
                'model' => static::class,
                'table' => $this->table,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete record and invalidate cache
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->execute(
                "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?",
                [$id]
            );
            
            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                // Invalidate relevant cache
                $this->invalidateCache();
                $this->cache()->delete("model:{$this->table}:find:$id");
            }
            
            $this->logger->info('Record deleted', [
                'model' => static::class,
                'table' => $this->table,
                'id' => $id,
                'success' => $success
            ]);
            
            return $success;
            
        } catch (\Exception $e) {
            $this->logger->error('Database delete failed', [
                'model' => static::class,
                'table' => $this->table,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Remove hidden fields from data
     */
    protected function filterHidden(array $data): array
    {
        if (empty($this->hidden)) {
            return $data;
        }
        
        return array_diff_key($data, array_flip($this->hidden));
    }
    
    /**
     * Invalidate model cache
     */
    protected function invalidateCache(): void
    {
        if ($this->useCache) {
            $this->invalidateTableCache($this->table);
        }
    }
    
    /**
     * Get fresh data bypassing cache
     */
    public function fresh(int $id): ?array
    {
        $useCache = $this->useCache;
        $this->useCache = false;
        
        $result = $this->find($id);
        
        $this->useCache = $useCache;
        return $result;
    }
    
    /**
     * Enable/disable caching for this model
     */
    public function setCaching(bool $enabled): self
    {
        $this->useCache = $enabled;
        return $this;
    }
    
    /**
     * Set cache TTL for this model
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }
    
    /**
     * Get table name
     */
    public function getTable(): string
    {
        return $this->table;
    }
    
    /**
     * Get cache statistics for this model
     */
    public function getCacheStats(): array
    {
        return [
            'table' => $this->table,
            'cache_enabled' => $this->useCache,
            'cache_ttl' => $this->cacheTtl,
            'cache_stats' => $this->cache()->getStats()
        ];
    }
}
