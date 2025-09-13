<?php
/**
 * Prefix Manager - Core Database Prefix Management System
 * File: app/Shared/Database/PrefixManager.php
 */

declare(strict_types=1);

namespace App\Shared\Database;

class PrefixManager
{
    private \mysqli $db;
    private string $audit_table = 'cis_prefix_migrations';
    
    public function __construct(\mysqli $database)
    {
        $this->db = $database;
        $this->ensureAuditTable();
    }
    
    /**
     * Create the prefix migrations audit table
     */
    private function ensureAuditTable(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS {$this->audit_table} (
            id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            operation_id VARCHAR(64) NOT NULL UNIQUE,
            operation_type ENUM('rename', 'drop', 'create', 'audit') NOT NULL,
            source_table VARCHAR(255) NULL,
            target_table VARCHAR(255) NULL,
            source_prefix VARCHAR(50) NULL,
            target_prefix VARCHAR(50) NULL,
            dry_run BOOLEAN DEFAULT FALSE,
            status ENUM('pending', 'success', 'failed', 'rolled_back') DEFAULT 'pending',
            rows_affected INT DEFAULT 0,
            execution_time_ms INT DEFAULT 0,
            error_message TEXT NULL,
            rollback_sql TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            created_by VARCHAR(255) DEFAULT 'system',
            
            INDEX idx_operation_id (operation_id),
            INDEX idx_operation_type (operation_type),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_source_table (source_table),
            INDEX idx_target_table (target_table)
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$this->db->query($sql)) {
            throw new \Exception("Failed to create prefix migrations table: " . $this->db->error);
        }
    }
    
    /**
     * Audit current database schema and prefix usage
     */
    public function auditSchema(): array
    {
        $operation_id = $this->generateOperationId('audit');
        $start_time = microtime(true);
        
        try {
            // Get all tables
            $result = $this->db->query("SHOW TABLES");
            if (!$result) {
                throw new \Exception("Failed to get tables: " . $this->db->error);
            }
            
            $tables = [];
            while ($row = $result->fetch_array()) {
                $tables[] = $row[0];
            }
            
            // Analyze prefixes
            $prefix_analysis = $this->analyzePrefixes($tables);
            
            // Log audit operation
            $execution_time = (int)round((microtime(true) - $start_time) * 1000);
            $this->logOperation($operation_id, 'audit', null, null, null, null, false, 'success', count($tables), $execution_time);
            
            return [
                'operation_id' => $operation_id,
                'total_tables' => count($tables),
                'tables' => $tables,
                'prefix_analysis' => $prefix_analysis,
                'execution_time_ms' => $execution_time
            ];
            
        } catch (\Exception $e) {
            $execution_time = (int)round((microtime(true) - $start_time) * 1000);
            $this->logOperation($operation_id, 'audit', null, null, null, null, false, 'failed', 0, $execution_time, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Analyze prefix patterns in table names
     */
    private function analyzePrefixes(array $tables): array
    {
        $prefix_groups = [];
        $no_prefix = [];
        
        foreach ($tables as $table) {
            if (preg_match('/^([a-zA-Z]+)_/', $table, $matches)) {
                $prefix = $matches[1];
                if (!isset($prefix_groups[$prefix])) {
                    $prefix_groups[$prefix] = [];
                }
                $prefix_groups[$prefix][] = $table;
            } else {
                $no_prefix[] = $table;
            }
        }
        
        return [
            'prefixed_tables' => $prefix_groups,
            'unprefixed_tables' => $no_prefix,
            'prefix_count' => count($prefix_groups),
            'recommendations' => $this->generateRecommendations($prefix_groups, $no_prefix)
        ];
    }
    
    /**
     * Generate renaming recommendations
     */
    private function generateRecommendations(array $prefix_groups, array $no_prefix): array
    {
        $recommendations = [
            'keep' => [],
            'rename_to_cis' => [],
            'drop_framework' => [],
            'needs_review' => []
        ];
        
        // Framework tables to drop
        $framework_patterns = ['laravel_', 'sessions', 'password_resets', 'personal_access_tokens', 'telescope_'];
        
        // Specialized prefixes to keep
        $keep_prefixes = ['cis', 'cam'];
        
        foreach ($prefix_groups as $prefix => $tables) {
            if (in_array($prefix, $keep_prefixes)) {
                $recommendations['keep'] = array_merge($recommendations['keep'], $tables);
            } else {
                foreach ($tables as $table) {
                    $is_framework = false;
                    foreach ($framework_patterns as $pattern) {
                        if (strpos($table, $pattern) !== false) {
                            $recommendations['drop_framework'][] = $table;
                            $is_framework = true;
                            break;
                        }
                    }
                    
                    if (!$is_framework) {
                        $base_name = preg_replace('/^[a-zA-Z]+_/', '', $table);
                        $recommendations['rename_to_cis'][] = [
                            'current' => $table,
                            'target' => 'cis_' . $base_name
                        ];
                    }
                }
            }
        }
        
        // Handle unprefixed tables
        foreach ($no_prefix as $table) {
            $is_framework = false;
            foreach ($framework_patterns as $pattern) {
                if (strpos($table, $pattern) !== false) {
                    $recommendations['drop_framework'][] = $table;
                    $is_framework = true;
                    break;
                }
            }
            
            if (!$is_framework) {
                // Core tables without prefix should get cis_ prefix
                $core_tables = ['users', 'roles', 'permissions', 'configuration', 'audit_log'];
                if (in_array($table, $core_tables)) {
                    $recommendations['rename_to_cis'][] = [
                        'current' => $table,
                        'target' => 'cis_' . $table
                    ];
                } else {
                    $recommendations['needs_review'][] = $table;
                }
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Rename table with dry-run support
     */
    public function renameTable(string $current_table, string $new_table, bool $dry_run = true): array
    {
        $operation_id = $this->generateOperationId('rename');
        $start_time = microtime(true);
        
        try {
            // Validate inputs
            if (!$this->tableExists($current_table)) {
                throw new \Exception("Source table '$current_table' does not exist");
            }
            
            if ($this->tableExists($new_table)) {
                throw new \Exception("Target table '$new_table' already exists");
            }
            
            // Extract prefixes
            $source_prefix = $this->extractPrefix($current_table);
            $target_prefix = $this->extractPrefix($new_table);
            
            // Generate SQL
            $rename_sql = "RENAME TABLE `$current_table` TO `$new_table`";
            $rollback_sql = "RENAME TABLE `$new_table` TO `$current_table`";
            
            $result = [
                'operation_id' => $operation_id,
                'current_table' => $current_table,
                'new_table' => $new_table,
                'source_prefix' => $source_prefix,
                'target_prefix' => $target_prefix,
                'dry_run' => $dry_run,
                'sql' => $rename_sql,
                'rollback_sql' => $rollback_sql,
                'status' => 'pending'
            ];
            
            if (!$dry_run) {
                // Begin transaction
                $this->db->autocommit(false);
                
                // Execute rename
                if (!$this->db->query($rename_sql)) {
                    throw new \Exception("Rename failed: " . $this->db->error);
                }
                
                // Commit
                $this->db->commit();
                $this->db->autocommit(true);
                
                $result['status'] = 'success';
            }
            
            // Log operation
            $execution_time = (int)round((microtime(true) - $start_time) * 1000);
            $this->logOperation(
                $operation_id, 'rename', $current_table, $new_table, 
                $source_prefix, $target_prefix, $dry_run, 
                $dry_run ? 'success' : $result['status'], 1, $execution_time, null, $rollback_sql
            );
            
            $result['execution_time_ms'] = $execution_time;
            
            return $result;
            
        } catch (\Exception $e) {
            if (!$dry_run && isset($this->db)) {
                $this->db->rollback();
                $this->db->autocommit(true);
            }
            
            $execution_time = (int)round((microtime(true) - $start_time) * 1000);
            $this->logOperation(
                $operation_id, 'rename', $current_table ?? null, $new_table ?? null,
                $source_prefix ?? null, $target_prefix ?? null, $dry_run, 'failed', 0, $execution_time, $e->getMessage()
            );
            
            throw $e;
        }
    }
    
    /**
     * Drop table with dry-run support
     */
    public function dropTable(string $table_name, bool $dry_run = true): array
    {
        $operation_id = $this->generateOperationId('drop');
        $start_time = microtime(true);
        
        try {
            if (!$this->tableExists($table_name)) {
                throw new \Exception("Table '$table_name' does not exist");
            }
            
            $prefix = $this->extractPrefix($table_name);
            $drop_sql = "DROP TABLE `$table_name`";
            
            $result = [
                'operation_id' => $operation_id,
                'table_name' => $table_name,
                'prefix' => $prefix,
                'dry_run' => $dry_run,
                'sql' => $drop_sql,
                'status' => 'pending'
            ];
            
            if (!$dry_run) {
                if (!$this->db->query($drop_sql)) {
                    throw new \Exception("Drop failed: " . $this->db->error);
                }
                $result['status'] = 'success';
            }
            
            $execution_time = (int)round((microtime(true) - $start_time) * 1000);
            $this->logOperation(
                $operation_id, 'drop', $table_name, null, $prefix, null, $dry_run,
                $dry_run ? 'success' : $result['status'], 1, $execution_time
            );
            
            $result['execution_time_ms'] = $execution_time;
            
            return $result;
            
        } catch (\Exception $e) {
            $execution_time = (int)round((microtime(true) - $start_time) * 1000);
            $this->logOperation(
                $operation_id, 'drop', $table_name ?? null, null, 
                $prefix ?? null, null, $dry_run, 'failed', 0, $execution_time, $e->getMessage()
            );
            
            throw $e;
        }
    }
    
    /**
     * Get operation history
     */
    public function getOperationHistory(int $limit = 100): array
    {
        $sql = "
            SELECT * FROM {$this->audit_table} 
            ORDER BY created_at DESC 
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $operations = [];
        
        while ($row = $result->fetch_assoc()) {
            $operations[] = $row;
        }
        
        return $operations;
    }
    
    /**
     * Audit table prefixes for the rename tools (legacy compatibility)
     */
    public function auditTablePrefixes(): array
    {
        $result = $this->db->query("SHOW TABLES");
        if (!$result) {
            throw new \Exception("Failed to get tables: " . $this->db->error);
        }
        
        $all_tables = [];
        $prefixed = [];
        $unprefixed = [];
        
        while ($row = $result->fetch_array()) {
            $table = $row[0];
            $all_tables[] = $table;
            
            if (strpos($table, 'cis_') === 0) {
                $prefixed[] = $table;
            } else {
                $unprefixed[] = $table;
            }
        }
        
        return [
            'total_tables' => count($all_tables),
            'all_tables' => $all_tables,
            'prefixed' => $prefixed,
            'unprefixed' => $unprefixed
        ];
    }
    
    /**
     * Generate rename operations for unprefixed tables
     */
    public function generateRenameOperations(array $unprefixed_tables): array
    {
        $operations = [];
        $operation_id = $this->generateOperationId('rename_batch');
        
        foreach ($unprefixed_tables as $table) {
            $new_table = 'cis_' . $table;
            
            // Check if target table already exists
            if ($this->tableExists($new_table)) {
                // Skip this table as target already exists
                continue;
            }
            
            $operations[] = [
                'operation_id' => $operation_id . '_' . $table,
                'source_table' => $table,
                'target_table' => $new_table,
                'sql' => "RENAME TABLE `$table` TO `$new_table`",
                'rollback_sql' => "RENAME TABLE `$new_table` TO `$table`",
                'dry_run' => true
            ];
        }
        
        return $operations;
    }
    
    /**
     * Execute rename operations with transaction safety
     */
    public function executeRenames(array $operations, bool $dry_run = true): array
    {
        if ($dry_run) {
            return ['status' => 'dry_run', 'operations' => $operations];
        }
        
        $results = [];
        $this->db->begin_transaction();
        
        try {
            foreach ($operations as $operation) {
                $start_time = microtime(true);
                
                // Execute the rename
                if (!$this->db->query($operation['sql'])) {
                    throw new \Exception("Failed to rename {$operation['source_table']}: " . $this->db->error);
                }
                
                $execution_time = (int)round((microtime(true) - $start_time) * 1000);
                
                // Log the operation
                $this->logOperation(
                    $operation['operation_id'],
                    'rename',
                    $operation['source_table'],
                    $operation['target_table'],
                    null,
                    'cis',
                    false,
                    'success',
                    1,
                    $execution_time,
                    null,
                    $operation['rollback_sql']
                );
                
                $results[] = [
                    'operation_id' => $operation['operation_id'],
                    'source_table' => $operation['source_table'],
                    'target_table' => $operation['target_table'],
                    'status' => 'success',
                    'execution_time_ms' => $execution_time
                ];
            }
            
            $this->db->commit();
            return ['status' => 'success', 'results' => $results];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Utility Methods
     */
    
    private function generateOperationId(string $type): string
    {
        return strtoupper($type) . '_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
    }
    
    private function tableExists(string $table_name): bool
    {
        $result = $this->db->query("SHOW TABLES LIKE '$table_name'");
        return $result && $result->num_rows > 0;
    }
    
    private function extractPrefix(string $table_name): ?string
    {
        if (preg_match('/^([a-zA-Z]+)_/', $table_name, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function logOperation(
        string $operation_id, string $operation_type, ?string $source_table, ?string $target_table,
        ?string $source_prefix, ?string $target_prefix, bool $dry_run, string $status,
        int $rows_affected, int $execution_time, ?string $error_message = null, ?string $rollback_sql = null
    ): void {
        $sql = "
            INSERT INTO {$this->audit_table} (
                operation_id, operation_type, source_table, target_table, 
                source_prefix, target_prefix, dry_run, status, rows_affected, 
                execution_time_ms, error_message, rollback_sql, completed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "sssssssiisss",
            $operation_id, $operation_type, $source_table, $target_table,
            $source_prefix, $target_prefix, $dry_run, $status, $rows_affected,
            $execution_time, $error_message, $rollback_sql
        );
        
        $stmt->execute();
    }
}

?>
