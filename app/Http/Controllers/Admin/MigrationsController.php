<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Infra\Persistence\MariaDB\Database;
use App\Shared\Logging\Logger;

/**
 * Migration Controller - Database Migration Management
 * 
 * Admin-only interface for running and managing database migrations
 * with comprehensive reporting and rollback capabilities.
 * 
 * @version 2.0.0-alpha.2
 */
class MigrationsController extends BaseController
{
    private Logger $logger;
    private string $migrationsPath;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = Logger::getInstance();
        $this->migrationsPath = __DIR__ . '/../../../migrations';
    }
    
    /**
     * Show migrations dashboard
     */
    public function index(): void
    {
        if (!$this->hasPermission('admin')) {
            $this->redirect('/dashboard');
            return;
        }
        
        $data = [
            'pending_migrations' => $this->getPendingMigrations(),
            'applied_migrations' => $this->getAppliedMigrations(),
            'migration_status' => $this->getMigrationStatus()
        ];
        
        $this->render('admin/tools/migrations', $data);
    }
    
    /**
     * Run pending migrations
     */
    public function run(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        try {
            // Ensure migrations table exists
            $this->ensureMigrationsTable();
            
            $pendingMigrations = $this->getPendingMigrations();
            $results = [];
            $appliedCount = 0;
            
            foreach ($pendingMigrations as $migration) {
                try {
                    $result = $this->runMigration($migration);
                    $results[] = $result;
                    
                    if ($result['success']) {
                        $appliedCount++;
                    }
                    
                } catch (Exception $e) {
                    $results[] = [
                        'migration' => $migration['filename'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $response = [
                'success' => true,
                'applied_count' => $appliedCount,
                'total_pending' => count($pendingMigrations),
                'results' => $results,
                'timestamp' => date('Y-m-d H:i:s T')
            ];
            
            $this->logger->info('Migrations executed', [
                'component' => 'migrations_controller',
                'action' => 'run',
                'applied_count' => $appliedCount,
                'total_pending' => count($pendingMigrations)
            ]);
            
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            $this->logger->error('Migration execution failed', [
                'component' => 'migrations_controller',
                'action' => 'run',
                'error' => $e->getMessage()
            ]);
            
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s T')
            ]);
        }
    }
    
    /**
     * Get migration status
     */
    public function status(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        try {
            echo json_encode([
                'success' => true,
                'status' => $this->getMigrationStatus(),
                'timestamp' => date('Y-m-d H:i:s T')
            ], JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s T')
            ]);
        }
    }
    
    /**
     * Ensure migrations tracking table exists
     */
    private function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS cis_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_batch (batch),
            INDEX idx_executed (executed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        Database::execute($sql);
    }
    
    /**
     * Get pending migrations
     */
    private function getPendingMigrations(): array
    {
        $this->ensureMigrationsTable();
        
        // Get all migration files
        $migrationFiles = [];
        if (is_dir($this->migrationsPath)) {
            $files = scandir($this->migrationsPath);
            foreach ($files as $file) {
                if (preg_match('/^\d{14}_.*\.php$/', $file)) {
                    $migrationFiles[] = $file;
                }
            }
        }
        
        sort($migrationFiles);
        
        // Get applied migrations
        $appliedMigrations = Database::query("SELECT migration FROM cis_migrations ORDER BY migration");
        $appliedList = array_column($appliedMigrations, 'migration');
        
        // Find pending
        $pending = [];
        foreach ($migrationFiles as $file) {
            if (!in_array($file, $appliedList)) {
                $pending[] = [
                    'filename' => $file,
                    'filepath' => $this->migrationsPath . '/' . $file,
                    'timestamp' => substr($file, 0, 14),
                    'name' => substr($file, 15, -4)
                ];
            }
        }
        
        return $pending;
    }
    
    /**
     * Get applied migrations
     */
    private function getAppliedMigrations(): array
    {
        $this->ensureMigrationsTable();
        
        return Database::query("
            SELECT migration, batch, executed_at 
            FROM cis_migrations 
            ORDER BY executed_at DESC
        ");
    }
    
    /**
     * Get migration status summary
     */
    private function getMigrationStatus(): array
    {
        $pending = count($this->getPendingMigrations());
        $applied = count($this->getAppliedMigrations());
        
        return [
            'pending_count' => $pending,
            'applied_count' => $applied,
            'total_migrations' => $pending + $applied,
            'up_to_date' => $pending === 0
        ];
    }
    
    /**
     * Run a single migration
     */
    private function runMigration(array $migration): array
    {
        $startTime = microtime(true);
        
        try {
            // Include migration file
            require_once $migration['filepath'];
            
            // Determine class name from filename
            $className = $this->getClassNameFromFile($migration['filename']);
            
            if (!class_exists($className)) {
                throw new Exception("Migration class {$className} not found");
            }
            
            // Create instance and run up() method
            $instance = new $className();
            
            if (!method_exists($instance, 'up')) {
                throw new Exception("Migration {$className} does not have up() method");
            }
            
            Database::beginTransaction();
            
            $result = $instance->up();
            
            // Record migration as applied
            $nextBatch = $this->getNextBatch();
            Database::execute(
                "INSERT INTO cis_migrations (migration, batch) VALUES (?, ?)",
                [$migration['filename'], $nextBatch]
            );
            
            Database::commit();
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'migration' => $migration['filename'],
                'success' => true,
                'execution_time_ms' => $executionTime,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }
            
            throw $e;
        }
    }
    
    /**
     * Get next batch number
     */
    private function getNextBatch(): int
    {
        $result = Database::query("SELECT MAX(batch) as max_batch FROM cis_migrations");
        return (int) ($result[0]['max_batch'] ?? 0) + 1;
    }
    
    /**
     * Get class name from migration filename
     */
    private function getClassNameFromFile(string $filename): string
    {
        // Remove timestamp and extension
        $name = substr($filename, 15, -4); // Remove YYYYMMDDHHMMSS_ and .php
        
        // Convert snake_case to PascalCase
        return str_replace('_', '', ucwords($name, '_'));
    }
    
    /**
     * Check if current user has permission
     */
    private function hasPermission(string $permission): bool
    {
        $user = $_SESSION['user'] ?? null;
        
        if (!$user) {
            return false;
        }
        
        // Admin has all permissions
        if ($user['role'] === 'admin') {
            return true;
        }
        
        return false;
    }
}
