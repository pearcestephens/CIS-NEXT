<?php
declare(strict_types=1);

namespace App\Shared\Backup;

use App\Shared\Logging\Logger;
use App\Infra\Persistence\MariaDB\Database;
use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ZipArchive;

/**
 * Production-Ready Backup Manager
 * File: app/Shared/Backup/BackupManager.php
 * Author: CIS Developer Bot
 * Created: 2025-09-13
 * Purpose: Complete backup system for CIS with database, file, and incremental backups
 */
class BackupManager
{
    private string $backup_base_dir;
    private array $config;
    private Logger $logger;
    private Database $database;
    
    // Backup types
    const TYPE_FULL = 'full';
    const TYPE_DATABASE = 'database';
    const TYPE_FILES = 'files';
    const TYPE_INCREMENTAL = 'incremental';
    const TYPE_MIGRATION = 'migration';
    
    // Backup statuses
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    
    public function __construct()
    {
        $this->backup_base_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/backups';
        $this->logger = Logger::getInstance();
        $this->database = Database::getInstance();
        
        $this->config = [
            'max_backups' => 50,
            'max_age_days' => 90,
            'compression' => true,
            'compression_level' => 6,
            'chunk_size' => 1024 * 1024, // 1MB chunks
            'exclude_patterns' => [
                '*.tmp', '*.log', '*.cache', 
                'cache/*', 'var/cache/*', 'var/logs/*',
                'backups/*', 'node_modules/*', '.git/*'
            ],
            'database_batch_size' => 1000,
            'max_backup_size_mb' => 2048 // 2GB limit
        ];
        
        $this->initializeBackupDirectory();
    }
    
    /**
     * Initialize backup directory and database table
     */
    private function initializeBackupDirectory(): void
    {
        if (!is_dir($this->backup_base_dir)) {
            mkdir($this->backup_base_dir, 0755, true);
            $this->logger->info('Created backup directory', ['path' => $this->backup_base_dir]);
        }
        
        // Ensure backup tracking table exists
        $this->createBackupTrackingTable();
    }
    
    /**
     * Create backup tracking table if it doesn't exist
     */
    private function createBackupTrackingTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS cis_backup_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_id VARCHAR(64) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            type ENUM('full', 'database', 'files', 'incremental', 'migration') NOT NULL,
            status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            file_path VARCHAR(500),
            file_size BIGINT DEFAULT 0,
            compressed_size BIGINT DEFAULT 0,
            items_count INT DEFAULT 0,
            compression_ratio DECIMAL(5,2) DEFAULT 0.00,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            created_by INT NULL,
            error_message TEXT NULL,
            metadata JSON NULL,
            retention_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_backup_id (backup_id),
            INDEX idx_type (type),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_retention_date (retention_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->database->execute($sql);
    }
    
    /**
     * Create a full system backup
     */
    public function createFullBackup(string $name = null, int $created_by = null): array
    {
        $backup_id = $this->generateBackupId();
        $backup_name = $name ?? ('system_full_' . date('Ymd_His'));
        
        $this->logger->info('Starting full system backup', [
            'backup_id' => $backup_id,
            'name' => $backup_name,
            'created_by' => $created_by
        ]);
        
        // Create backup job record
        $job_id = $this->createBackupJob($backup_id, $backup_name, self::TYPE_FULL, $created_by);
        
        try {
            $this->updateBackupStatus($backup_id, self::STATUS_RUNNING);
            
            $backup_path = $this->backup_base_dir . '/' . $backup_name . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($backup_path, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Cannot create backup archive: $backup_path");
            }
            
            // Set compression level
            $zip->setCompressionName('*', ZipArchive::CM_DEFLATE, $this->config['compression_level']);
            
            $items_count = 0;
            $total_size = 0;
            
            // Backup application files
            $source_paths = $this->getBackupSourcePaths();
            
            foreach ($source_paths as $source => $description) {
                $full_source = dirname(dirname(dirname(dirname(__FILE__)))) . '/' . $source;
                
                if (is_dir($full_source)) {
                    $items_added = $this->addDirectoryToZip($zip, $full_source, $source);
                    $items_count += $items_added;
                    $this->logger->debug("Added directory to backup", [
                        'source' => $source,
                        'items' => $items_added
                    ]);
                } elseif (is_file($full_source)) {
                    $zip->addFile($full_source, $source);
                    $items_count++;
                    $total_size += filesize($full_source);
                }
            }
            
            // Add database dump to backup
            $db_dump_path = $this->createDatabaseDump($backup_id);
            if ($db_dump_path) {
                $zip->addFile($db_dump_path, 'database_dump.sql');
                $items_count++;
                $total_size += filesize($db_dump_path);
            }
            
            // Add backup manifest
            $manifest = $this->createBackupManifest($backup_id, $backup_name, self::TYPE_FULL, $source_paths);
            $zip->addFromString('BACKUP_MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT));
            $items_count++;
            
            $zip->close();
            
            // Calculate sizes and compression
            $compressed_size = filesize($backup_path);
            $compression_ratio = $total_size > 0 ? round((1 - ($compressed_size / $total_size)) * 100, 2) : 0;
            
            // Update backup job
            $this->updateBackupJob($backup_id, [
                'status' => self::STATUS_COMPLETED,
                'file_path' => $backup_path,
                'file_size' => $total_size,
                'compressed_size' => $compressed_size,
                'items_count' => $items_count,
                'compression_ratio' => $compression_ratio,
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Cleanup temporary files
            if ($db_dump_path && file_exists($db_dump_path)) {
                unlink($db_dump_path);
            }
            
            // Run retention policy
            $this->applyRetentionPolicy();
            
            $this->logger->info('Full backup completed successfully', [
                'backup_id' => $backup_id,
                'file_size' => $compressed_size,
                'compression_ratio' => $compression_ratio
            ]);
            
            return [
                'success' => true,
                'backup_id' => $backup_id,
                'backup_name' => $backup_name,
                'file_path' => $backup_path,
                'file_size' => $compressed_size,
                'items_count' => $items_count,
                'compression_ratio' => $compression_ratio
            ];
            
        } catch (Exception $e) {
            $this->updateBackupJob($backup_id, [
                'status' => self::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->logger->error('Full backup failed', [
                'backup_id' => $backup_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'backup_id' => $backup_id
            ];
        }
    }
    
    /**
     * Create database-only backup
     */
    public function createDatabaseBackup(string $name = null, int $created_by = null): array
    {
        $backup_id = $this->generateBackupId();
        $backup_name = $name ?? ('database_' . date('Ymd_His'));
        
        $this->logger->info('Starting database backup', ['backup_id' => $backup_id]);
        
        $job_id = $this->createBackupJob($backup_id, $backup_name, self::TYPE_DATABASE, $created_by);
        
        try {
            $this->updateBackupStatus($backup_id, self::STATUS_RUNNING);
            
            $backup_file = $this->backup_base_dir . '/' . $backup_name . '.sql';
            
            $this->createDatabaseDumpToFile($backup_file);
            
            $file_size = filesize($backup_file);
            
            // Compress if enabled
            if ($this->config['compression']) {
                $compressed_file = $backup_file . '.gz';
                $this->compressFile($backup_file, $compressed_file);
                unlink($backup_file);
                $backup_file = $compressed_file;
                $compressed_size = filesize($compressed_file);
                $compression_ratio = round((1 - ($compressed_size / $file_size)) * 100, 2);
            } else {
                $compressed_size = $file_size;
                $compression_ratio = 0;
            }
            
            $this->updateBackupJob($backup_id, [
                'status' => self::STATUS_COMPLETED,
                'file_path' => $backup_file,
                'file_size' => $file_size,
                'compressed_size' => $compressed_size,
                'compression_ratio' => $compression_ratio,
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->logger->info('Database backup completed', ['backup_id' => $backup_id]);
            
            return [
                'success' => true,
                'backup_id' => $backup_id,
                'backup_name' => $backup_name,
                'file_path' => $backup_file,
                'file_size' => $compressed_size
            ];
            
        } catch (Exception $e) {
            $this->updateBackupJob($backup_id, [
                'status' => self::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'backup_id' => $backup_id
            ];
        }
    }
    
    /**
     * Create files-only backup
     */
    public function createFilesBackup(array $paths, string $name = null, int $created_by = null): array
    {
        $backup_id = $this->generateBackupId();
        $backup_name = $name ?? ('files_' . date('Ymd_His'));
        
        $job_id = $this->createBackupJob($backup_id, $backup_name, self::TYPE_FILES, $created_by);
        
        try {
            $this->updateBackupStatus($backup_id, self::STATUS_RUNNING);
            
            $backup_path = $this->backup_base_dir . '/' . $backup_name . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($backup_path, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Cannot create backup archive: $backup_path");
            }
            
            $items_count = 0;
            $total_size = 0;
            
            foreach ($paths as $path) {
                $full_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/' . $path;
                
                if (is_file($full_path)) {
                    $zip->addFile($full_path, $path);
                    $items_count++;
                    $total_size += filesize($full_path);
                } elseif (is_dir($full_path)) {
                    $items_added = $this->addDirectoryToZip($zip, $full_path, $path);
                    $items_count += $items_added;
                }
            }
            
            $zip->close();
            
            $compressed_size = filesize($backup_path);
            $compression_ratio = $total_size > 0 ? round((1 - ($compressed_size / $total_size)) * 100, 2) : 0;
            
            $this->updateBackupJob($backup_id, [
                'status' => self::STATUS_COMPLETED,
                'file_path' => $backup_path,
                'file_size' => $total_size,
                'compressed_size' => $compressed_size,
                'items_count' => $items_count,
                'compression_ratio' => $compression_ratio,
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'backup_id' => $backup_id,
                'backup_name' => $backup_name,
                'file_path' => $backup_path,
                'items_count' => $items_count
            ];
            
        } catch (Exception $e) {
            $this->updateBackupJob($backup_id, [
                'status' => self::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * List all backups with filtering and pagination
     */
    public function listBackups(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM cis_backup_jobs WHERE 1=1";
        $params = [];
        
        if (!empty($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $backups = $this->database->query($sql, $params);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM cis_backup_jobs WHERE 1=1";
        $count_params = array_slice($params, 0, -2); // Remove limit and offset
        
        if (!empty($filters['type'])) {
            $count_sql .= " AND type = ?";
        }
        if (!empty($filters['status'])) {
            $count_sql .= " AND status = ?";
        }
        if (!empty($filters['date_from'])) {
            $count_sql .= " AND created_at >= ?";
        }
        if (!empty($filters['date_to'])) {
            $count_sql .= " AND created_at <= ?";
        }
        
        $total_result = $this->database->query($count_sql, $count_params);
        $total = $total_result[0]['total'] ?? 0;
        
        return [
            'backups' => $backups,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get backup by ID
     */
    public function getBackup(string $backup_id): ?array
    {
        $sql = "SELECT * FROM cis_backup_jobs WHERE backup_id = ?";
        $result = $this->database->query($sql, [$backup_id]);
        
        return $result[0] ?? null;
    }
    
    /**
     * Delete backup
     */
    public function deleteBackup(string $backup_id, int $deleted_by = null): bool
    {
        try {
            $backup = $this->getBackup($backup_id);
            if (!$backup) {
                return false;
            }
            
            // Delete physical file
            if ($backup['file_path'] && file_exists($backup['file_path'])) {
                unlink($backup['file_path']);
            }
            
            // Delete database record
            $sql = "DELETE FROM cis_backup_jobs WHERE backup_id = ?";
            $this->database->execute($sql, [$backup_id]);
            
            $this->logger->info('Backup deleted', [
                'backup_id' => $backup_id,
                'deleted_by' => $deleted_by
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete backup', [
                'backup_id' => $backup_id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Download backup file
     */
    public function downloadBackup(string $backup_id): array
    {
        $backup = $this->getBackup($backup_id);
        
        if (!$backup || !file_exists($backup['file_path'])) {
            return [
                'success' => false,
                'error' => 'Backup file not found'
            ];
        }
        
        return [
            'success' => true,
            'file_path' => $backup['file_path'],
            'filename' => basename($backup['file_path']),
            'size' => $backup['compressed_size'],
            'mime_type' => $this->getMimeType($backup['file_path'])
        ];
    }
    
    /**
     * Get backup statistics
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
            COUNT(*) as total_backups,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_backups,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_backups,
            SUM(compressed_size) as total_size,
            AVG(compression_ratio) as avg_compression,
            MAX(created_at) as last_backup
        FROM cis_backup_jobs";
        
        $result = $this->database->query($sql);
        $stats = $result[0] ?? [];
        
        // Get backup types breakdown
        $type_sql = "SELECT type, COUNT(*) as count, SUM(compressed_size) as size 
                    FROM cis_backup_jobs 
                    WHERE status = 'completed' 
                    GROUP BY type";
        
        $type_stats = $this->database->query($type_sql);
        
        return [
            'overview' => $stats,
            'by_type' => $type_stats,
            'storage_usage' => $this->getStorageUsage()
        ];
    }
    
    /**
     * Run system health check
     */
    public function healthCheck(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'checks' => [],
            'warnings' => [],
            'errors' => []
        ];
        
        try {
            // Check backup directory
            $health['checks']['backup_directory'] = [
                'status' => is_dir($this->backup_base_dir) && is_writable($this->backup_base_dir),
                'message' => 'Backup directory accessible and writable'
            ];
            
            // Check database connectivity
            $health['checks']['database'] = [
                'status' => $this->database->isConnected(),
                'message' => 'Database connection healthy'
            ];
            
            // Check disk space
            $free_space = disk_free_space($this->backup_base_dir);
            $total_space = disk_total_space($this->backup_base_dir);
            $usage_percent = round(((($total_space - $free_space) / $total_space) * 100), 2);
            
            $health['checks']['disk_space'] = [
                'status' => $usage_percent < 90,
                'message' => "Disk usage: {$usage_percent}%",
                'free_space' => $free_space,
                'usage_percent' => $usage_percent
            ];
            
            if ($usage_percent > 85) {
                $health['warnings'][] = 'High disk usage detected';
            }
            
            // Check recent backup success rate
            $recent_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful
            FROM cis_backup_jobs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            
            $recent_result = $this->database->query($recent_sql);
            $recent_stats = $recent_result[0] ?? ['total' => 0, 'successful' => 0];
            
            $success_rate = $recent_stats['total'] > 0 
                ? round(($recent_stats['successful'] / $recent_stats['total']) * 100, 2)
                : 100;
            
            $health['checks']['success_rate'] = [
                'status' => $success_rate >= 80,
                'message' => "7-day success rate: {$success_rate}%",
                'success_rate' => $success_rate
            ];
            
            if ($success_rate < 80) {
                $health['warnings'][] = 'Low backup success rate';
            }
            
            // Overall status
            $failed_checks = array_filter($health['checks'], fn($check) => !$check['status']);
            
            if (!empty($failed_checks)) {
                $health['overall_status'] = 'unhealthy';
                $health['errors'] = array_map(fn($check) => $check['message'], $failed_checks);
            } elseif (!empty($health['warnings'])) {
                $health['overall_status'] = 'warning';
            }
            
        } catch (Exception $e) {
            $health['overall_status'] = 'error';
            $health['errors'][] = $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * Apply retention policy to clean up old backups
     */
    public function applyRetentionPolicy(): array
    {
        $deleted_count = 0;
        $freed_space = 0;
        
        try {
            // Delete by age
            $age_sql = "SELECT backup_id, file_path, compressed_size 
                       FROM cis_backup_jobs 
                       WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                       AND status = 'completed'";
            
            $old_backups = $this->database->query($age_sql, [$this->config['max_age_days']]);
            
            foreach ($old_backups as $backup) {
                if ($this->deleteBackup($backup['backup_id'])) {
                    $deleted_count++;
                    $freed_space += $backup['compressed_size'];
                }
            }
            
            // Delete by count (keep only max_backups)
            $count_sql = "SELECT backup_id, file_path, compressed_size 
                         FROM cis_backup_jobs 
                         WHERE status = 'completed'
                         ORDER BY created_at DESC 
                         LIMIT 999999 OFFSET ?";
            
            $excess_backups = $this->database->query($count_sql, [$this->config['max_backups']]);
            
            foreach ($excess_backups as $backup) {
                if ($this->deleteBackup($backup['backup_id'])) {
                    $deleted_count++;
                    $freed_space += $backup['compressed_size'];
                }
            }
            
            $this->logger->info('Retention policy applied', [
                'deleted_count' => $deleted_count,
                'freed_space' => $freed_space
            ]);
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'freed_space' => $freed_space
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Retention policy failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Private helper methods
    
    private function generateBackupId(): string
    {
        return 'backup_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }
    
    private function createBackupJob(string $backup_id, string $name, string $type, ?int $created_by): int
    {
        $sql = "INSERT INTO cis_backup_jobs (backup_id, name, type, created_by, started_at, retention_date) 
                VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))";
        
        return $this->database->insert($sql, [
            $backup_id, 
            $name, 
            $type, 
            $created_by, 
            $this->config['max_age_days']
        ]);
    }
    
    private function updateBackupStatus(string $backup_id, string $status): void
    {
        $sql = "UPDATE cis_backup_jobs SET status = ?, updated_at = NOW() WHERE backup_id = ?";
        $this->database->execute($sql, [$status, $backup_id]);
    }
    
    private function updateBackupJob(string $backup_id, array $data): void
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        
        $values[] = $backup_id;
        
        $sql = "UPDATE cis_backup_jobs SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE backup_id = ?";
        $this->database->execute($sql, $values);
    }
    
    private function getBackupSourcePaths(): array
    {
        return [
            'app/' => 'Application code',
            'config/' => 'Configuration files', 
            'functions/' => 'Legacy functions',
            'migrations/' => 'Database migrations',
            'routes/' => 'Route definitions',
            'resources/' => 'Resources and views',
            'seeds/' => 'Database seeders',
            '.env' => 'Environment configuration'
        ];
    }
    
    private function addDirectoryToZip(ZipArchive $zip, string $source_dir, string $zip_path): int
    {
        $items_added = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $file_path = $file->getRealPath();
            $relative_path = $zip_path . '/' . substr($file_path, strlen($source_dir) + 1);
            
            if ($this->shouldExcludeFile($file_path)) {
                continue;
            }
            
            if ($file->isFile()) {
                $zip->addFile($file_path, $relative_path);
                $items_added++;
            } elseif ($file->isDir()) {
                $zip->addEmptyDir($relative_path);
                $items_added++;
            }
        }
        
        return $items_added;
    }
    
    private function shouldExcludeFile(string $file_path): bool
    {
        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (fnmatch($pattern, $file_path) || fnmatch($pattern, basename($file_path))) {
                return true;
            }
        }
        
        return false;
    }
    
    private function createDatabaseDump(string $backup_id): string
    {
        $dump_file = sys_get_temp_dir() . '/db_dump_' . $backup_id . '.sql';
        $this->createDatabaseDumpToFile($dump_file);
        return $dump_file;
    }
    
    private function createDatabaseDumpToFile(string $file_path): void
    {
        $tables = $this->database->query("SHOW TABLES");
        
        $dump_content = "-- Database Backup\n";
        $dump_content .= "-- Generated: " . date('c') . "\n";
        $dump_content .= "-- Database: CIS\n\n";
        $dump_content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        foreach ($tables as $table_row) {
            $table_name = array_values($table_row)[0];
            
            // Get table structure
            $create_table = $this->database->query("SHOW CREATE TABLE `$table_name`");
            $dump_content .= "-- Table: $table_name\n";
            $dump_content .= "DROP TABLE IF EXISTS `$table_name`;\n";
            $dump_content .= $create_table[0]['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $this->database->query("SELECT * FROM `$table_name`");
            
            if (!empty($rows)) {
                $dump_content .= "-- Data for table: $table_name\n";
                $dump_content .= "INSERT INTO `$table_name` VALUES\n";
                
                $value_strings = [];
                foreach ($rows as $row) {
                    $values = array_map(function($value) {
                        return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                    }, array_values($row));
                    
                    $value_strings[] = '(' . implode(', ', $values) . ')';
                }
                
                $dump_content .= implode(",\n", $value_strings) . ";\n\n";
            }
        }
        
        $dump_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        file_put_contents($file_path, $dump_content);
    }
    
    private function compressFile(string $source, string $target): void
    {
        $source_handle = fopen($source, 'rb');
        $target_handle = gzopen($target, 'wb' . $this->config['compression_level']);
        
        while (!feof($source_handle)) {
            gzwrite($target_handle, fread($source_handle, $this->config['chunk_size']));
        }
        
        fclose($source_handle);
        gzclose($target_handle);
    }
    
    private function createBackupManifest(string $backup_id, string $name, string $type, array $source_paths): array
    {
        return [
            'backup_id' => $backup_id,
            'name' => $name,
            'type' => $type,
            'created_at' => date('c'),
            'php_version' => PHP_VERSION,
            'source_paths' => array_keys($source_paths),
            'config' => [
                'compression' => $this->config['compression'],
                'compression_level' => $this->config['compression_level'],
                'exclude_patterns' => $this->config['exclude_patterns']
            ],
            'system_info' => [
                'hostname' => gethostname(),
                'os' => php_uname(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ]
        ];
    }
    
    private function getMimeType(string $file_path): string
    {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        $mime_types = [
            'zip' => 'application/zip',
            'sql' => 'application/sql',
            'gz' => 'application/gzip',
            'tar' => 'application/x-tar'
        ];
        
        return $mime_types[$extension] ?? 'application/octet-stream';
    }
    
    private function getStorageUsage(): array
    {
        $backup_size = 0;
        
        if (is_dir($this->backup_base_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->backup_base_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $backup_size += $file->getSize();
                }
            }
        }
        
        $free_space = disk_free_space($this->backup_base_dir);
        $total_space = disk_total_space($this->backup_base_dir);
        
        return [
            'backup_size' => $backup_size,
            'free_space' => $free_space,
            'total_space' => $total_space,
            'usage_percent' => round((($total_space - $free_space) / $total_space) * 100, 2)
        ];
    }
}
