<?php
declare(strict_types=1);

namespace App\Models;

use App\Infra\Persistence\MariaDB\Database;

/**
 * Base Model
 * Handles common database operations with table prefix support
 */
abstract class BaseModel
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get the full table name with prefix
     */
    protected function getTable(): string
    {
        return $this->db->table($this->table);
    }
    
    /**
     * Execute SQL with table prefix replacement
     */
    protected function executeWithPrefix(string $sql, array $params = []): \PDOStatement
    {
        return $this->db->executeWithPrefix($sql, $params);
    }
    
    /**
     * Alias for executeWithPrefix for convenience
     */
    protected function query(string $sql, array $params = []): \PDOStatement
    {
        return $this->executeWithPrefix($sql, $params);
    }
    
    /**
     * Find a record by ID
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {" . $this->table . "} WHERE {$this->primaryKey} = ?";
        $stmt = $this->executeWithPrefix($sql, [$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Get all records
     */
    public function all(): array
    {
        $sql = "SELECT * FROM {" . $this->table . "}";
        $stmt = $this->executeWithPrefix($sql);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create a new record
     */
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {" . $this->table . "} ({$columns}) VALUES ({$placeholders})";
        $this->executeWithPrefix($sql, $data);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Update a record
     */
    public function update(int $id, array $data): bool
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {" . $this->table . "} SET {$setClause} WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;
        
        $stmt = $this->executeWithPrefix($sql, $data);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Delete a record
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {" . $this->table . "} WHERE {$this->primaryKey} = ?";
        $stmt = $this->executeWithPrefix($sql, [$id]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Count records
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {" . $this->table . "}";
        $stmt = $this->executeWithPrefix($sql);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
}
