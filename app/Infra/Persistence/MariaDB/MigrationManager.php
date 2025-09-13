<?php
declare(strict_types=1);

namespace App\Infra\Persistence\MariaDB;

use App\Shared\Logging\Logger;

/**
 * Migration Manager
 * Handles database schema migrations
 */
class MigrationManager
{
    private Database $db;
    private Logger $logger;
    private string $migrationsPath;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
        $this->migrationsPath = __DIR__ . '/Migrations';
        
        $this->ensureMigrationsTable();
    }
    
    public function migrate(): void
    {
        $this->logger->info('Starting database migrations');
        
        $migrations = $this->getPendingMigrations();
        
        if (empty($migrations)) {
            $this->logger->info('No pending migrations');
            return;
        }
        
        foreach ($migrations as $migration) {
            $this->runMigration($migration);
        }
        
        $this->logger->info('Database migrations completed', [
            'count' => count($migrations),
        ]);
    }
    
    public function rollback(?string $migration = null): void
    {
        if ($migration) {
            $this->rollbackMigration($migration);
        } else {
            $this->rollbackLastBatch();
        }
    }
    
    private function ensureMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_batch (batch)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->db->execute($sql);
    }
    
    private function getPendingMigrations(): array
    {
        // Get executed migrations
        $stmt = $this->db->execute("SELECT migration FROM migrations ORDER BY id");
        $executed = array_column($stmt->fetchAll(), 'migration');
        
        // Get all migration files
        $files = glob($this->migrationsPath . '/*.php');
        $allMigrations = [];
        
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            if (!in_array($filename, $executed)) {
                $allMigrations[] = $filename;
            }
        }
        
        sort($allMigrations);
        return $allMigrations;
    }
    
    private function runMigration(string $migration): void
    {
        $this->logger->info('Running migration', ['migration' => $migration]);
        
        $file = $this->migrationsPath . '/' . $migration . '.php';
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$file}");
        }
        
        try {
            $this->db->beginTransaction();
            
            // Include and execute migration
            $migrationInstance = include $file;
            
            if ($migrationInstance instanceof Migration) {
                $migrationInstance->up($this->db);
            } else {
                throw new \RuntimeException("Invalid migration format: {$migration}");
            }
            
            // Record migration
            $batch = $this->getNextBatch();
            $stmt = $this->db->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$migration, $batch]);
            
            $this->db->commit();
            
            $this->logger->info('Migration completed', ['migration' => $migration]);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            
            $this->logger->error('Migration failed', [
                'migration' => $migration,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    private function rollbackMigration(string $migration): void
    {
        $this->logger->info('Rolling back migration', ['migration' => $migration]);
        
        $file = $this->migrationsPath . '/' . $migration . '.php';
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$file}");
        }
        
        try {
            $this->db->beginTransaction();
            
            // Include and execute rollback
            $migrationInstance = include $file;
            
            if ($migrationInstance instanceof Migration) {
                $migrationInstance->down($this->db);
            }
            
            // Remove migration record
            $stmt = $this->db->prepare("DELETE FROM migrations WHERE migration = ?");
            $stmt->execute([$migration]);
            
            $this->db->commit();
            
            $this->logger->info('Migration rolled back', ['migration' => $migration]);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function rollbackLastBatch(): void
    {
        $stmt = $this->db->execute("SELECT MAX(batch) as last_batch FROM migrations");
        $result = $stmt->fetch();
        $lastBatch = $result['last_batch'] ?? 0;
        
        if ($lastBatch == 0) {
            $this->logger->info('No migrations to rollback');
            return;
        }
        
        $stmt = $this->db->execute("SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC", [$lastBatch]);
        $migrations = array_column($stmt->fetchAll(), 'migration');
        
        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration);
        }
    }
    
    private function getNextBatch(): int
    {
        $stmt = $this->db->execute("SELECT COALESCE(MAX(batch), 0) + 1 as next_batch FROM migrations");
        $result = $stmt->fetch();
        return (int) $result['next_batch'];
    }
    
    public function getStatus(): array
    {
        $stmt = $this->db->execute("
            SELECT migration, batch, executed_at 
            FROM migrations 
            ORDER BY id DESC
        ");
        
        return [
            'executed' => $stmt->fetchAll(),
            'pending' => $this->getPendingMigrations(),
        ];
    }
}
