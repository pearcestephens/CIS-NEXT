<?php
declare(strict_types=1);

/**
 * Admin Data Access Layer (DAL)
 * File: app/Models/AdminDAL.php
 * Purpose: Schema-aware database access with prepared statements, transactions, and RBAC
 * Author: CIS Development Bot - Stage 2
 * Created: 2025-01-11
 */

namespace App\Models;

use mysqli;
use RuntimeException;
use InvalidArgumentException;

class AdminDAL
{
    private mysqli $connection;
    private array $schema_map;
    private bool $in_transaction = false;
    private array $error_log = [];
    
    /**
     * Vend integration table mapping
     */
    const VEND_TABLES = [
        'outlets' => 'vend_outlets',
        'products' => 'vend_products', 
        'sales' => 'vend_sales',
        'customers' => 'vend_customers',
        'inventory' => 'vend_inventory',
    ];

    /**
     * CIS core table mapping  
     */
    const CIS_TABLES = [
        'users' => 'users',
        'user_roles' => 'user_roles',
        'configuration' => 'configuration',
        'audit_logs' => 'audit_logs',
        'migrations' => 'migrations',
        'jobs' => 'jobs'
    ];

    /**
     * Schema table mapping for vend integration and CIS tables
     */
    private const SCHEMA_MAP = [
        // Vend Integration Tables
        'vend_outlets' => 'vend_outlets',
        'vend_products' => 'vend_products', 
        'vend_sales' => 'vend_sales',
        'vend_customers' => 'vend_customers',
        'vend_inventory' => 'vend_inventory',
        
        // CIS Core Tables
        'users' => 'users',
        'roles' => 'roles',
        'user_roles' => 'user_roles',
        'permissions' => 'permissions',
        'role_permissions' => 'role_permissions',
        
        // Configuration & Settings
        'configuration' => 'configuration',
        'configuration_history' => 'configuration_history',
        
        // Audit & Logging
        'audit_logs' => 'audit_logs',
        'job_queue' => 'job_queue',
        'telemetry_events' => 'telemetry_events',
        
        // Integration Secrets
        'integration_secrets' => 'integration_secrets',
        
        // Migration System
        'migrations' => 'migrations',
        'migration_batches' => 'migration_batches',
        
        // Automation & Testing
        'automation_runs' => 'automation_runs',
        'test_data_seeds' => 'test_data_seeds',
        
        // CISWatch (if applicable)
        'ciswatch_events' => 'ciswatch_events',
        'ciswatch_cameras' => 'ciswatch_cameras',
        
        // System Monitoring
        'system_metrics' => 'system_metrics',
        'error_logs' => 'error_logs',
        'performance_logs' => 'performance_logs'
    ];
    
    /**
     * Column name mapping for consistent access
     */
    private const COLUMN_MAP = [
        // User Management
        'user_id' => 'id',
        'username' => 'username',
        'email' => 'email',
        'role_name' => 'name',
        
        // Vend Integration
        'outlet_id' => 'outlet_id',
        'product_id' => 'product_id',
        'customer_id' => 'customer_id',
        
        // Timestamps
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'deleted_at' => 'deleted_at',
        
        // Common Status Fields
        'status' => 'status',
        'active' => 'active',
        'enabled' => 'enabled'
    ];

    public function __construct()
    {
        $this->initializeConnection();
        $this->schema_map = self::SCHEMA_MAP;
    }

    /**
     * Initialize database connection with error handling
     */
    private function initializeConnection(): void
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $database = $_ENV['DB_NAME'] ?? 'cis_dev';
        $port = (int)($_ENV['DB_PORT'] ?? 3306);

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $this->connection = new mysqli($host, $username, $password, $database, $port);
            $this->connection->set_charset('utf8mb4');
        } catch (\Exception $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Public method to check permissions
     */
    public function checkPermission(string $permission): bool
    {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }

        $user_role = $_SESSION['user_role'];
        
        // Super admin has all permissions
        if ($user_role === 'super_admin') {
            return true;
        }

        // Check specific permissions based on role
        $role_permissions = [
            'admin' => ['admin.*', 'user.read', 'config.read', 'migration.*', 'automation.*'],
            'user' => ['user.read', 'dashboard.read']
        ];

        $permissions = $role_permissions[$user_role] ?? [];
        
        // Check exact match or wildcard
        foreach ($permissions as $allowed) {
            if ($permission === $allowed || str_ends_with($allowed, '.*') && str_starts_with($permission, str_replace('.*', '', $allowed))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check RBAC permissions for current user
     */
    private function checkPermissions(string $operation, string $table): void
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            throw new RuntimeException("Authentication required for database operation");
        }

        $role = $_SESSION['role'];
        
        // Super admin has all permissions
        if ($role === 'super_admin') {
            return;
        }

        // Admin users have read access and limited write access
        if ($role === 'admin') {
            $restricted_operations = ['DROP', 'ALTER', 'TRUNCATE', 'DELETE'];
            $restricted_tables = ['users', 'roles', 'permissions', 'integration_secrets'];
            
            if (in_array(strtoupper($operation), $restricted_operations) && in_array($table, $restricted_tables)) {
                throw new RuntimeException("Insufficient permissions for {$operation} on {$table}");
            }
            return;
        }

        // Regular users have very limited access
        throw new RuntimeException("Insufficient permissions for database operations");
    }

    /**
     * Resolve table name using schema mapping
     */
    public function table(string $logical_name): string
    {
        if (!isset($this->schema_map[$logical_name])) {
            throw new InvalidArgumentException("Unknown table: {$logical_name}");
        }
        
        return $this->schema_map[$logical_name];
    }

    /**
     * Resolve column name using column mapping
     */
    public function col(string $logical_name): string
    {
        return self::COLUMN_MAP[$logical_name] ?? $logical_name;
    }

    /**
     * Execute SELECT query with prepared statements
     */
    public function query(string $sql, array $params = [], string $types = ''): array
    {
        // Extract operation and table for RBAC check
        preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|ALTER|DROP|TRUNCATE)\s+.*?FROM\s+(\w+)/i', $sql, $matches);
        $operation = $matches[1] ?? 'SELECT';
        $table = $matches[2] ?? 'unknown';
        
        $this->checkPermissions($operation, $table);
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            if ($stmt === false) {
                throw new RuntimeException("Prepare failed: " . $this->connection->error . " | SQL: " . $sql);
            }

            if (!empty($params)) {
                if (empty($types)) {
                    // Auto-detect parameter types
                    $types = $this->detectParameterTypes($params);
                }
                
                if (!$stmt->bind_param($types, ...$params)) {
                    throw new RuntimeException("Parameter binding failed: " . $stmt->error . " | SQL: " . $sql);
                }
            }

            if (!$stmt->execute()) {
                throw new RuntimeException("Query execution failed: " . $stmt->error . " | SQL: " . $sql);
            }

            $result = $stmt->get_result();
            $data = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }

            $stmt->close();
            return $data;

        } catch (\Exception $e) {
            $this->logError($sql, $params, $e->getMessage());
            throw new RuntimeException($e->getMessage() . " | SQL: " . $sql);
        }
    }

    /**
     * Execute INSERT/UPDATE/DELETE with prepared statements
     */
    public function exec(string $sql, array $params = [], string $types = ''): array
    {
        // Extract operation and table for RBAC check
        preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|DROP|TRUNCATE)\s+.*?(?:INTO|FROM)?\s*(\w+)/i', $sql, $matches);
        $operation = $matches[1] ?? 'UPDATE';
        $table = $matches[2] ?? 'unknown';
        
        $this->checkPermissions($operation, $table);
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            if ($stmt === false) {
                throw new RuntimeException("Prepare failed: " . $this->connection->error . " | SQL: " . $sql);
            }

            if (!empty($params)) {
                if (empty($types)) {
                    $types = $this->detectParameterTypes($params);
                }
                
                if (!$stmt->bind_param($types, ...$params)) {
                    throw new RuntimeException("Parameter binding failed: " . $stmt->error . " | SQL: " . $sql);
                }
            }

            if (!$stmt->execute()) {
                throw new RuntimeException("Execution failed: " . $stmt->error . " | SQL: " . $sql);
            }

            $result = [
                'success' => true,
                'affected_rows' => $stmt->affected_rows,
                'insert_id' => $this->connection->insert_id,
                'info' => $this->connection->info
            ];

            $stmt->close();
            return $result;

        } catch (\Exception $e) {
            $this->logError($sql, $params, $e->getMessage());
            throw new RuntimeException($e->getMessage() . " | SQL: " . $sql);
        }
    }

    /**
     * Begin database transaction
     */
    public function begin(): void
    {
        if ($this->in_transaction) {
            throw new RuntimeException("Transaction already active");
        }
        
        if (!$this->connection->begin_transaction()) {
            throw new RuntimeException("Failed to begin transaction");
        }
        
        $this->in_transaction = true;
    }

    /**
     * Commit database transaction
     */
    public function commit(): void
    {
        if (!$this->in_transaction) {
            throw new RuntimeException("No active transaction to commit");
        }
        
        if (!$this->connection->commit()) {
            throw new RuntimeException("Failed to commit transaction");
        }
        
        $this->in_transaction = false;
    }

    /**
     * Rollback database transaction
     */
    public function rollback(): void
    {
        if (!$this->in_transaction) {
            throw new RuntimeException("No active transaction to rollback");
        }
        
        if (!$this->connection->rollback()) {
            throw new RuntimeException("Failed to rollback transaction");
        }
        
        $this->in_transaction = false;
    }

    /**
     * Auto-detect parameter types for prepared statements
     */
    private function detectParameterTypes(array $params): string
    {
        $types = '';
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 's'; // Default to string for other types
            }
        }
        
        return $types;
    }

    /**
     * Log database errors for debugging
     */
    private function logError(string $sql, array $params, string $error): void
    {
        $log_entry = [
            'timestamp' => date('c'),
            'user_id' => $_SESSION['user_id'] ?? null,
            'sql' => $sql,
            'params' => $params,
            'error' => $error,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];
        
        $this->error_log[] = $log_entry;
        
        // Write to error log file if available
        if (defined('ERROR_LOG_PATH')) {
            error_log(json_encode($log_entry) . "\n", 3, ERROR_LOG_PATH);
        }
    }

    /**
     * Get database connection health status
     */
    public function health(): array
    {
        try {
            $result = $this->connection->query("SELECT 1 as status");
            $row = $result->fetch_assoc();
            
            return [
                'connected' => true,
                'server_info' => $this->connection->server_info,
                'host_info' => $this->connection->host_info,
                'protocol_version' => $this->connection->protocol_version,
                'status' => $row['status'] === 1 ? 'healthy' : 'degraded'
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'status' => 'failed'
            ];
        }
    }

    /**
     * Automatic parameter type detection
     */
    private function detectBindTypes(array $params): string
    {
        $types = '';
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        
        return $types;
    }

    /**
     * Get recent error log entries
     */
    public function getErrorLog(): array
    {
        return $this->error_log;
    }

    /**
     * Close database connection
     */
    public function close(): void
    {
        if ($this->in_transaction) {
            $this->rollback();
        }
        
        $this->connection->close();
    }

    /**
     * Destructor ensures clean connection closure
     */
    public function __destruct()
    {
        if (isset($this->connection)) {
            $this->close();
        }
    }

    /**
     * Utility method to safely quote identifiers (table/column names)
     */
    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Utility method for pagination queries
     */
    public function paginate(string $sql, int $page = 1, int $per_page = 20): array
    {
        $offset = ($page - 1) * $per_page;
        $paginated_sql = $sql . " LIMIT ? OFFSET ?";
        
        // Count total records
        $count_sql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_query";
        $count_result = $this->query($count_sql);
        $total = $count_result[0]['total'] ?? 0;
        
        // Get paginated data
        $data = $this->query($paginated_sql, [$per_page, $offset], 'ii');
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => ceil($total / $per_page),
                'has_next' => $page < ceil($total / $per_page),
                'has_prev' => $page > 1
            ]
        ];
    }
}
?>
