<?php
declare(strict_types=1);

/**
 * Prefix Management Model
 * File: app/Models/PrefixModel.php
 * Purpose: Database prefix management using AdminDAL
 */

namespace App\Models;

use RuntimeException;
use InvalidArgumentException;

class PrefixModel
{
    private AdminDAL $dal;

    public function __construct()
    {
        $this->dal = new AdminDAL();
    }

    /**
     * Get current database prefix configuration and table analysis
     */
    public function getPrefixAnalysis(): array
    {
        try {
            // Get current database name
            $db_result = $this->dal->query("SELECT DATABASE() as db_name");
            $database_name = $db_result[0]['db_name'] ?? 'unknown';

            // Get all tables with their properties
            $tables = $this->dal->query("
                SELECT 
                    t.TABLE_NAME as table_name,
                    t.TABLE_ROWS as row_count,
                    ROUND(((t.DATA_LENGTH + t.INDEX_LENGTH) / 1024 / 1024), 2) as size_mb,
                    t.ENGINE,
                    CASE 
                        WHEN t.TABLE_NAME LIKE 'information_schema%' 
                          OR t.TABLE_NAME LIKE 'mysql%' 
                          OR t.TABLE_NAME LIKE 'performance_schema%' 
                          OR t.TABLE_NAME LIKE 'sys%' THEN 'system'
                        ELSE 'user'
                    END as table_type
                FROM information_schema.TABLES t
                WHERE t.TABLE_SCHEMA = ?
                ORDER BY t.TABLE_NAME
            ", [$database_name], 's');

            // Analyze current prefix patterns
            $prefix_analysis = $this->analyzePrefixPatterns($tables);
            
            // Generate statistics
            $stats = $this->generateTableStats($tables, $prefix_analysis['current_prefix']);
            
            // Generate recommendations
            $recommendations = $this->generateRecommendations($tables, $prefix_analysis, $stats);

            return [
                'database_name' => $database_name,
                'current_prefix' => $prefix_analysis['current_prefix'],
                'suggested_prefix' => $prefix_analysis['suggested_prefix'],
                'connection_status' => 'active',
                'table_count' => count($tables),
                'tables' => $this->enrichTableData($tables, $prefix_analysis['current_prefix']),
                'stats' => $stats,
                'recommendations' => $recommendations
            ];

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to analyze database prefixes: " . $e->getMessage());
        }
    }

    /**
     * Preview prefix changes without executing
     */
    public function previewPrefixChanges(string $new_prefix, array $selected_tables, array $options = []): array
    {
        try {
            $preview = [];
            $current_analysis = $this->getPrefixAnalysis();
            $current_prefix = $current_analysis['current_prefix'];

            foreach ($selected_tables as $table_name) {
                $table_info = $this->findTableInfo($current_analysis['tables'], $table_name);
                
                if (!$table_info) {
                    throw new InvalidArgumentException("Table not found: {$table_name}");
                }

                if ($table_info['type'] === 'system' && ($options['skip_system'] ?? true)) {
                    continue;
                }

                // Calculate new name
                $new_name = $this->calculateNewTableName($table_name, $current_prefix, $new_prefix);
                
                $preview[] = [
                    'current_name' => $table_name,
                    'new_name' => $new_name,
                    'operation' => $new_name === $table_name ? 'no_change' : 'rename',
                    'size_mb' => $table_info['size_mb'],
                    'row_count' => $table_info['row_count'],
                    'sql' => $new_name !== $table_name ? "RENAME TABLE `{$table_name}` TO `{$new_name}`" : null
                ];
            }

            return [
                'success' => true,
                'preview' => $preview,
                'summary' => [
                    'total_operations' => count(array_filter($preview, fn($p) => $p['operation'] !== 'no_change')),
                    'tables_affected' => count($preview),
                    'estimated_time_seconds' => $this->estimateOperationTime($preview)
                ]
            ];

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to preview prefix changes: " . $e->getMessage());
        }
    }

    /**
     * Execute prefix changes with transaction safety
     */
    public function executePrefixChanges(string $new_prefix, array $selected_tables, array $options = []): array
    {
        try {
            // Create backup if requested
            $backup_file = null;
            if ($options['backup'] ?? true) {
                $backup_file = $this->createBackup($selected_tables);
            }

            // Get preview to validate operations
            $preview_result = $this->previewPrefixChanges($new_prefix, $selected_tables, $options);
            $operations = array_filter($preview_result['preview'], fn($p) => $p['operation'] !== 'no_change');

            if (empty($operations)) {
                return [
                    'success' => true,
                    'message' => 'No changes needed',
                    'processed_count' => 0,
                    'backup_file' => $backup_file
                ];
            }

            // Dry run check
            if ($options['dry_run'] ?? false) {
                return [
                    'success' => true,
                    'message' => 'Dry run completed - no changes made',
                    'preview' => $operations,
                    'processed_count' => count($operations)
                ];
            }

            // Execute operations in transaction
            $this->dal->begin();
            
            $processed_count = 0;
            $execution_log = [];

            foreach ($operations as $operation) {
                try {
                    if ($operation['sql']) {
                        $result = $this->dal->exec($operation['sql']);
                        
                        $execution_log[] = [
                            'operation' => $operation['sql'],
                            'success' => true,
                            'affected_rows' => $result['affected_rows'] ?? 0
                        ];
                        
                        $processed_count++;
                    }
                } catch (RuntimeException $e) {
                    $execution_log[] = [
                        'operation' => $operation['sql'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    
                    // Rollback on any failure
                    $this->dal->rollback();
                    
                    return [
                        'success' => false,
                        'error' => "Operation failed: {$e->getMessage()}",
                        'processed_count' => $processed_count,
                        'execution_log' => $execution_log,
                        'rollback_info' => 'All changes have been rolled back'
                    ];
                }
            }

            $this->dal->commit();

            // Log successful prefix change
            $this->logPrefixOperation($new_prefix, $processed_count, $execution_log);

            return [
                'success' => true,
                'message' => "Successfully processed {$processed_count} tables",
                'processed_count' => $processed_count,
                'backup_file' => $backup_file,
                'execution_log' => $execution_log
            ];

        } catch (RuntimeException $e) {
            if ($this->dal->in_transaction ?? false) {
                $this->dal->rollback();
            }
            throw new RuntimeException("Prefix operation failed: " . $e->getMessage());
        }
    }

    /**
     * Analyze prefix patterns in current tables
     */
    private function analyzePrefixPatterns(array $tables): array
    {
        $user_tables = array_filter($tables, fn($t) => $t['table_type'] === 'user');
        
        if (empty($user_tables)) {
            return [
                'current_prefix' => '',
                'suggested_prefix' => 'cis_',
                'pattern_confidence' => 0
            ];
        }

        // Find common prefixes
        $prefixes = [];
        foreach ($user_tables as $table) {
            if (preg_match('/^([a-z]+_)/', $table['table_name'], $matches)) {
                $prefix = $matches[1];
                $prefixes[$prefix] = ($prefixes[$prefix] ?? 0) + 1;
            }
        }

        if (empty($prefixes)) {
            return [
                'current_prefix' => '',
                'suggested_prefix' => 'cis_',
                'pattern_confidence' => 0
            ];
        }

        // Find most common prefix
        arsort($prefixes);
        $most_common = array_key_first($prefixes);
        $confidence = $prefixes[$most_common] / count($user_tables);

        return [
            'current_prefix' => $confidence > 0.5 ? $most_common : '',
            'suggested_prefix' => $most_common !== 'cis_' ? 'cis_' : 'app_',
            'pattern_confidence' => $confidence
        ];
    }

    /**
     * Generate table statistics
     */
    private function generateTableStats(array $tables, string $current_prefix): array
    {
        $stats = [
            'prefixed_tables' => 0,
            'unprefixed_tables' => 0,
            'mixed_prefix' => 0,
            'system_tables' => 0
        ];

        foreach ($tables as $table) {
            if ($table['table_type'] === 'system') {
                $stats['system_tables']++;
            } elseif (empty($current_prefix)) {
                $stats['unprefixed_tables']++;
            } elseif (str_starts_with($table['table_name'], $current_prefix)) {
                $stats['prefixed_tables']++;
            } else {
                $stats['mixed_prefix']++;
            }
        }

        return $stats;
    }

    /**
     * Generate recommendations based on analysis
     */
    private function generateRecommendations(array $tables, array $prefix_analysis, array $stats): array
    {
        $recommendations = [];

        if ($stats['mixed_prefix'] > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Mixed prefix patterns detected. Consider standardizing.'
            ];
        }

        if ($stats['unprefixed_tables'] > $stats['prefixed_tables']) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Most tables are unprefixed. Consider adding a consistent prefix.'
            ];
        }

        if ($prefix_analysis['pattern_confidence'] < 0.7 && $stats['prefixed_tables'] > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Inconsistent prefix pattern. Review table naming convention.'
            ];
        }

        return $recommendations;
    }

    /**
     * Enrich table data with prefix information
     */
    private function enrichTableData(array $tables, string $current_prefix): array
    {
        return array_map(function($table) use ($current_prefix) {
            $table['has_prefix'] = !empty($current_prefix) && str_starts_with($table['table_name'], $current_prefix);
            $table['name'] = $table['table_name'];
            $table['type'] = $table['table_type'];
            return $table;
        }, $tables);
    }

    /**
     * Find table info by name
     */
    private function findTableInfo(array $tables, string $table_name): ?array
    {
        foreach ($tables as $table) {
            if ($table['name'] === $table_name) {
                return $table;
            }
        }
        return null;
    }

    /**
     * Calculate new table name with prefix
     */
    private function calculateNewTableName(string $current_name, string $current_prefix, string $new_prefix): string
    {
        // Remove current prefix if it exists
        if (!empty($current_prefix) && str_starts_with($current_name, $current_prefix)) {
            $base_name = substr($current_name, strlen($current_prefix));
        } else {
            $base_name = $current_name;
        }

        // Add new prefix
        return $new_prefix . $base_name;
    }

    /**
     * Estimate operation time based on table sizes
     */
    private function estimateOperationTime(array $operations): int
    {
        $total_mb = array_sum(array_column($operations, 'size_mb'));
        $total_rows = array_sum(array_column($operations, 'row_count'));
        
        // Rough estimate: 1 second per MB + 1 second per 100k rows + base time
        return (int)(ceil($total_mb) + ceil($total_rows / 100000) + count($operations) * 2);
    }

    /**
     * Create backup of selected tables
     */
    private function createBackup(array $table_names): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = "backups/prefix_backup_{$timestamp}.sql";
        
        try {
            // Create backup directory if it doesn't exist
            $backup_dir = dirname($backup_file);
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }

            // Generate mysqldump command (simplified - in production use proper mysqldump)
            $backup_sql = "-- Prefix Management Backup - {$timestamp}\n";
            $backup_sql .= "-- Tables: " . implode(', ', $table_names) . "\n\n";
            
            foreach ($table_names as $table_name) {
                $backup_sql .= "-- Backup for table: {$table_name}\n";
                $backup_sql .= "CREATE TABLE IF NOT EXISTS `{$table_name}_backup_{$timestamp}` LIKE `{$table_name}`;\n";
                $backup_sql .= "INSERT INTO `{$table_name}_backup_{$timestamp}` SELECT * FROM `{$table_name}`;\n\n";
            }

            file_put_contents($backup_file, $backup_sql);
            
            return $backup_file;

        } catch (\Exception $e) {
            throw new RuntimeException("Failed to create backup: " . $e->getMessage());
        }
    }

    /**
     * Log prefix operation for audit trail
     */
    private function logPrefixOperation(string $new_prefix, int $processed_count, array $execution_log): void
    {
        try {
            $this->dal->exec("
                INSERT INTO {$this->dal->table('audit_logs')} 
                (user_id, action, details, ip_address, created_at)
                VALUES (?, 'prefix_management', ?, ?, NOW())
            ", [
                $_SESSION['user_id'] ?? null,
                json_encode([
                    'new_prefix' => $new_prefix,
                    'tables_processed' => $processed_count,
                    'execution_log' => $execution_log
                ]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ], 'iss');

        } catch (RuntimeException $e) {
            // Don't fail the operation if audit logging fails
            error_log("Failed to log prefix operation: " . $e->getMessage());
        }
    }

    /**
     * Get table information for detailed view
     */
    public function getTableInfo(string $table_name): array
    {
        try {
            // Get table structure
            $columns = $this->dal->query("DESCRIBE `{$table_name}`");
            
            // Get table status
            $status = $this->dal->query("SHOW TABLE STATUS LIKE ?", [$table_name], 's');
            
            // Get indexes
            $indexes = $this->dal->query("SHOW INDEX FROM `{$table_name}`");

            return [
                'success' => true,
                'info' => [
                    'name' => $table_name,
                    'columns' => $columns,
                    'status' => $status[0] ?? [],
                    'indexes' => $indexes
                ]
            ];

        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
