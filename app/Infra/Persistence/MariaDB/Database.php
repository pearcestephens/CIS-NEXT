<?php
declare(strict_types=1);

namespace App\Infra\Persistence\MariaDB;

use App\Shared\Config\Config;
use PDO;
use PDOException;

/**
 * Database Connection Manager
 * Handles MariaDB connections with query builder and profiling
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    private array $config;
    private array $queryLog = [];
    private string $tablePrefix;
    
    private function __construct(array $config)
    {
        $this->config = $config;
        $this->tablePrefix = $config['table_prefix'] ?? 'cis_';
        $this->connect();
    }
    
    public static function initialize(array $config): void
    {
        self::$instance = new self($config);
    }
    
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Database not initialized');
        }
        
        return self::$instance;
    }
    
    private function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config['host'],
            $this->config['port'],
            $this->config['database']
        );
        
        try {
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function prepare(string $sql): \PDOStatement
    {
        return $this->connection->prepare($sql);
    }
    
    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            $this->logQuery($sql, $params, $stmt->rowCount(), $executionTime);
            
            return $stmt;
        } catch (PDOException $e) {
            $this->logQuery($sql, $params, 0, 0, $e->getMessage());
            throw $e;
        }
    }
    
    public function query(string $sql): \PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->query($sql);
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            $this->logQuery($sql, [], $stmt->rowCount(), $executionTime);
            
            return $stmt;
        } catch (PDOException $e) {
            $this->logQuery($sql, [], 0, 0, $e->getMessage());
            throw $e;
        }
    }
    
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }
    
    public function commit(): bool
    {
        return $this->connection->commit();
    }
    
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }
    
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }
    
    private function logQuery(string $sql, array $params, int $rowCount, float $executionTime, ?string $error = null): void
    {
        if (!Config::get('PROFILER_ENABLED')) {
            return;
        }
        
        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'rows' => $rowCount,
            'time_ms' => round($executionTime, 2),
            'error' => $error,
            'timestamp' => microtime(true),
        ];
        
        // Log slow queries
        $slowQueryThreshold = (float) Config::get('SLOW_QUERY_THRESHOLD', 500);
        if ($executionTime > $slowQueryThreshold) {
            \App\Shared\Logging\Logger::getInstance()->warning('Slow query detected', [
                'sql' => $sql,
                'params' => $params,
                'execution_time_ms' => $executionTime,
                'rows_affected' => $rowCount,
            ]);
        }
    }
    
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }
    
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }
    
    /**
     * Simple query builder for common operations
     */
    public function select(string $table): QueryBuilder
    {
        return (new QueryBuilder($this, 'SELECT', $table))->select(['*']);
    }
    
    public function insert(string $table): QueryBuilder
    {
        return new QueryBuilder($this, 'INSERT', $table);
    }
    
    public function update(string $table): QueryBuilder
    {
        return new QueryBuilder($this, 'UPDATE', $table);
    }
    
    public function delete(string $table): QueryBuilder
    {
        return new QueryBuilder($this, 'DELETE', $table);
    }
    
    /**
     * Health check for monitoring
     */
    /**
     * Test database health/connectivity
     */
    public static function health(): bool
    {
        try {
            $instance = self::getInstance();
            $stmt = $instance->connection->query('SELECT 1');
            return $stmt !== false;
        } catch (PDOException $e) {
            error_log("Database health check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if database connection is active
     */
    public function isConnected(): bool
    {
        try {
            if ($this->connection === null) {
                return false;
            }
            
            // Perform a simple query to test connection
            $this->connection->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get table name with prefix
     */
    public function table(string $tableName): string
    {
        return $this->tablePrefix . $tableName;
    }
    
    /**
     * Get the table prefix
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }
    
    /**
     * Replace table placeholders with prefixed table names in SQL
     */
    public function prefixSql(string $sql): string
    {
        // Replace {table_name} with prefixed table names
        return preg_replace_callback('/\{([a-z_]+)\}/', function($matches) {
            return $this->table($matches[1]);
        }, $sql);
    }
    
    /**
     * Execute SQL with automatic table prefix replacement
     */
    public function executeWithPrefix(string $sql, array $params = []): \PDOStatement
    {
        $prefixedSql = $this->prefixSql($sql);
        return $this->execute($prefixedSql, $params);
    }
    
    /**
     * Temporary raw query method for legacy compatibility
     * @deprecated Use execute() or prepared statements instead
     */
    public function raw(string $sql, array $params = []): array
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
