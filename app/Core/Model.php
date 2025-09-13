<?php
/**
 * Base Model Class
 * 
 * Base model for CIS MVC Platform with database interaction
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Exception;

abstract class Model
{
    protected static ?PDO $connection = null;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $casts = [];
    
    protected static array $queryLog = [];
    protected static bool $logQueries = false;

    /**
     * Get database connection
     */
    protected static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }
        
        return self::$connection;
    }

    /**
     * Create new database connection
     */
    private static function createConnection(): PDO
    {
        $config = config('database.connections.mysql');
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
        
        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            
            // Enable query logging if configured
            self::$logQueries = config('database.query_log.enabled', false);
            
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Execute query with logging
     */
    protected static function query(string $sql, array $params = []): \PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            
            if (self::$logQueries) {
                $executionTime = microtime(true) - $startTime;
                self::$queryLog[] = [
                    'sql' => $sql,
                    'params' => $params,
                    'time' => $executionTime,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                
                // Log slow queries
                $slowThreshold = config('database.query_log.slow_query_threshold', 1.0);
                if ($executionTime > $slowThreshold) {
                    error_log("Slow query ({$executionTime}s): {$sql}");
                }
            }
            
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage() . " - SQL: {$sql}");
        }
    }

    /**
     * Find record by ID
     */
    public static function find(int $id): ?array
    {
        $model = new static();
        $sql = "SELECT * FROM {$model->getTableName()} WHERE {$model->primaryKey} = ? LIMIT 1";
        $stmt = self::query($sql, [$id]);
        
        $result = $stmt->fetch();
        return $result ? $model->castAttributes($result) : null;
    }

    /**
     * Find record by ID or throw exception
     */
    public static function findOrFail(int $id): array
    {
        $result = self::find($id);
        if (!$result) {
            throw new Exception("Record not found with ID: {$id}");
        }
        return $result;
    }

    /**
     * Find first record matching conditions
     */
    public static function where(string $column, $operator, $value = null): QueryBuilder
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        return (new QueryBuilder(new static()))->where($column, $operator, $value);
    }

    /**
     * Get all records
     */
    public static function all(): array
    {
        $model = new static();
        $sql = "SELECT * FROM {$model->getTableName()}";
        $stmt = self::query($sql);
        
        $results = $stmt->fetchAll();
        return array_map([$model, 'castAttributes'], $results);
    }

    /**
     * Create new record
     */
    public static function create(array $data): array
    {
        $model = new static();
        $fillableData = $model->filterFillable($data);
        
        $columns = array_keys($fillableData);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $model->getTableName(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        self::query($sql, array_values($fillableData));
        
        $id = self::getConnection()->lastInsertId();
        return self::find((int)$id);
    }

    /**
     * Update record by ID
     */
    public static function update(int $id, array $data): bool
    {
        $model = new static();
        $fillableData = $model->filterFillable($data);
        
        if (empty($fillableData)) {
            return false;
        }
        
        $setParts = array_map(fn($column) => "{$column} = ?", array_keys($fillableData));
        $values = array_values($fillableData);
        $values[] = $id;
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $model->getTableName(),
            implode(', ', $setParts),
            $model->primaryKey
        );
        
        $stmt = self::query($sql, $values);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete record by ID
     */
    public static function delete(int $id): bool
    {
        $model = new static();
        $sql = "DELETE FROM {$model->getTableName()} WHERE {$model->primaryKey} = ?";
        $stmt = self::query($sql, [$id]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Count records
     */
    public static function count(string $column = '*'): int
    {
        $model = new static();
        $sql = "SELECT COUNT({$column}) as count FROM {$model->getTableName()}";
        $stmt = self::query($sql);
        
        return (int)$stmt->fetch()['count'];
    }

    /**
     * Get table name with prefix
     */
    protected function getTableName(): string
    {
        $prefix = config('database.connections.mysql.prefix', '');
        return $prefix . $this->table;
    }

    /**
     * Filter data to only fillable attributes
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Cast attributes according to model rules
     */
    protected function castAttributes(array $attributes): array
    {
        foreach ($this->casts as $key => $type) {
            if (!isset($attributes[$key])) {
                continue;
            }
            
            $attributes[$key] = match ($type) {
                'int', 'integer' => (int)$attributes[$key],
                'float', 'double' => (float)$attributes[$key],
                'bool', 'boolean' => (bool)$attributes[$key],
                'array', 'json' => json_decode($attributes[$key], true),
                'date' => new \DateTime($attributes[$key]),
                default => $attributes[$key],
            };
        }
        
        // Remove hidden attributes
        if (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }
        
        return $attributes;
    }

    /**
     * Begin database transaction
     */
    public static function beginTransaction(): void
    {
        self::getConnection()->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    /**
     * Rollback database transaction
     */
    public static function rollback(): void
    {
        self::getConnection()->rollback();
    }

    /**
     * Get query log
     */
    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }

    /**
     * Clear query log
     */
    public static function clearQueryLog(): void
    {
        self::$queryLog = [];
    }
}

/**
 * Query Builder Class
 */
class QueryBuilder
{
    private Model $model;
    private array $wheres = [];
    private array $params = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Add WHERE condition
     */
    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = "{$column} {$operator} ?";
        $this->params[] = $value;
        return $this;
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "{$column} {$direction}";
        return $this;
    }

    /**
     * Add LIMIT clause
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Add OFFSET clause
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Get first result
     */
    public function first(): ?array
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    /**
     * Execute query and get results
     */
    public function get(): array
    {
        $sql = "SELECT * FROM " . $this->model->getTableName();
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }
        
        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        
        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        if ($this->offset) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        $stmt = Model::query($sql, $this->params);
        $results = $stmt->fetchAll();
        
        return array_map([$this->model, 'castAttributes'], $results);
    }

    /**
     * Count results
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM " . $this->model->getTableName();
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }
        
        $stmt = Model::query($sql, $this->params);
        return (int)$stmt->fetch()['count'];
    }
}
