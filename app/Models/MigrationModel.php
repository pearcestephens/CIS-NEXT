<?php
declare(strict_types=1);

/**
 * Migration Model
 * File: app/Models/MigrationModel.php
 * Purpose: Database migration management using AdminDAL
 */

namespace App\Models;

use RuntimeException;
use DirectoryIterator;

class MigrationModel
{
    private AdminDAL $dal;
    private string $migrations_path;

    public function __construct()
    {
        $this->dal = new AdminDAL();
        $this->migrations_path = __DIR__ . '/../../migrations/';
    }

    /**
     * Get migration status overview
     */
    public function getMigrationStatus(): array
    {
        try {
            // Get applied migrations
            $applied = $this->dal->query("
                SELECT migration, batch, executed_at 
                FROM {$this->dal->table('migrations')} 
                ORDER BY executed_at DESC
            ");

            // Get available migration files
            $available = $this->getAvailableMigrations();
            $applied_names = array_column($applied, 'migration');
            
            // Calculate pending migrations
            $pending = [];
            foreach ($available as $migration) {
                if (!in_array($migration['name'], $applied_names)) {
                    $pending[] = $migration;
                }
            }

            return [
                'applied_migrations' => $applied,
                'pending_migrations' => $pending,
                'migration_status' => [
                    'applied_count' => count($applied),
                    'pending_count' => count($pending),
                    'total_migrations' => count($available),
                    'up_to_date' => count($pending) === 0
                ]
            ];

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to get migration status: " . $e->getMessage());
        }
    }

    /**
     * Run pending migrations
     */
    public function runMigrations(): array
    {
        try {
            $this->dal->begin();
            
            $status = $this->getMigrationStatus();
            $pending = $status['pending_migrations'];
            
            if (empty($pending)) {
                $this->dal->commit();
                return [
                    'success' => true,
                    'message' => 'No pending migrations',
                    'applied_count' => 0
                ];
            }

            // Get next batch number
            $batch_result = $this->dal->query("
                SELECT COALESCE(MAX(batch), 0) + 1 as next_batch 
                FROM {$this->dal->table('migrations')}
            ");
            $batch = $batch_result[0]['next_batch'] ?? 1;

            $applied_count = 0;
            $errors = [];

            foreach ($pending as $migration) {
                try {
                    // Execute migration file
                    $migration_path = $this->migrations_path . $migration['filename'];
                    
                    if (!file_exists($migration_path)) {
                        throw new RuntimeException("Migration file not found: {$migration['filename']}");
                    }

                    // Include and execute migration
                    $result = $this->executeMigrationFile($migration_path);
                    
                    if ($result['success']) {
                        // Record successful migration
                        $this->dal->exec("
                            INSERT INTO {$this->dal->table('migrations')} 
                            (migration, batch, executed_at) 
                            VALUES (?, ?, NOW())
                        ", [$migration['name'], $batch], 'si');
                        
                        $applied_count++;
                    } else {
                        $errors[] = "Failed to execute {$migration['name']}: " . $result['error'];
                        break; // Stop on first error
                    }

                } catch (RuntimeException $e) {
                    $errors[] = "Error in {$migration['name']}: " . $e->getMessage();
                    break;
                }
            }

            if (!empty($errors)) {
                $this->dal->rollback();
                return [
                    'success' => false,
                    'error' => 'Migration failed: ' . implode('; ', $errors),
                    'applied_count' => 0
                ];
            }

            $this->dal->commit();
            
            return [
                'success' => true,
                'message' => "Successfully applied {$applied_count} migrations",
                'applied_count' => $applied_count,
                'batch' => $batch
            ];

        } catch (RuntimeException $e) {
            $this->dal->rollback();
            throw new RuntimeException("Migration execution failed: " . $e->getMessage());
        }
    }

    /**
     * Validate migration files
     */
    public function validateMigrations(): array
    {
        try {
            $migrations = $this->getAvailableMigrations();
            $validation_results = [];
            $errors = [];

            foreach ($migrations as $migration) {
                $file_path = $this->migrations_path . $migration['filename'];
                
                $validation = [
                    'name' => $migration['name'],
                    'filename' => $migration['filename'],
                    'exists' => file_exists($file_path),
                    'readable' => is_readable($file_path),
                    'syntax_valid' => false,
                    'has_up_method' => false,
                    'has_down_method' => false
                ];

                if ($validation['exists'] && $validation['readable']) {
                    // Basic syntax check
                    $content = file_get_contents($file_path);
                    $validation['syntax_valid'] = $this->validatePHPSyntax($content);
                    $validation['has_up_method'] = strpos($content, 'function up(') !== false;
                    $validation['has_down_method'] = strpos($content, 'function down(') !== false;
                }

                if (!$validation['exists']) {
                    $errors[] = "Missing file: {$migration['filename']}";
                }

                if (!$validation['syntax_valid']) {
                    $errors[] = "Syntax error in: {$migration['filename']}";
                }

                $validation_results[] = $validation;
            }

            return [
                'success' => empty($errors),
                'validations' => $validation_results,
                'errors' => $errors,
                'total_checked' => count($migrations)
            ];

        } catch (RuntimeException $e) {
            throw new RuntimeException("Migration validation failed: " . $e->getMessage());
        }
    }

    /**
     * Get available migration files from filesystem
     */
    private function getAvailableMigrations(): array
    {
        $migrations = [];

        if (!is_dir($this->migrations_path)) {
            return $migrations;
        }

        $iterator = new DirectoryIterator($this->migrations_path);
        
        foreach ($iterator as $file) {
            if ($file->isDot() || $file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getFilename();
            
            // Extract timestamp and name from filename (e.g., "001_create_users_table.php")
            if (preg_match('/^(\d+)_(.+)\.php$/', $filename, $matches)) {
                $migrations[] = [
                    'timestamp' => $matches[1],
                    'name' => $matches[1] . '_' . $matches[2],
                    'filename' => $filename,
                    'description' => str_replace('_', ' ', $matches[2])
                ];
            }
        }

        // Sort by timestamp
        usort($migrations, function($a, $b) {
            return strcmp($a['timestamp'], $b['timestamp']);
        });

        return $migrations;
    }

    /**
     * Execute a migration file safely
     */
    private function executeMigrationFile(string $file_path): array
    {
        try {
            // Create a safe execution environment
            ob_start();
            
            // Include migration file
            $migration_result = include $file_path;
            
            $output = ob_get_clean();
            
            return [
                'success' => true,
                'output' => $output,
                'result' => $migration_result
            ];

        } catch (\Throwable $e) {
            ob_end_clean();
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $file_path
            ];
        }
    }

    /**
     * Validate PHP syntax without executing
     */
    private function validatePHPSyntax(string $code): bool
    {
        // Create temporary file for syntax checking
        $temp_file = tempnam(sys_get_temp_dir(), 'migration_syntax_check_');
        
        if ($temp_file === false) {
            return false;
        }

        try {
            file_put_contents($temp_file, $code);
            
            // Use php -l to check syntax
            $output = [];
            $return_code = 0;
            exec("php -l {$temp_file} 2>&1", $output, $return_code);
            
            return $return_code === 0;

        } finally {
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        }
    }

    /**
     * Get migration history with pagination
     */
    public function getMigrationHistory(int $page = 1, int $per_page = 20): array
    {
        try {
            return $this->dal->paginate("
                SELECT 
                    migration,
                    batch,
                    executed_at,
                    CASE 
                        WHEN executed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'recent'
                        WHEN executed_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK) THEN 'week'
                        ELSE 'older'
                    END as age_category
                FROM {$this->dal->table('migrations')} 
                ORDER BY executed_at DESC
            ", $page, $per_page);

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to get migration history: " . $e->getMessage());
        }
    }
}
?>
