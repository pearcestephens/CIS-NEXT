<?php
/**
 * Automated Backup System
 * File: tools/backup_system.php
 * Author: CIS Developer Bot
 * Created: 2025-01-11
 * Purpose: Comprehensive backup and restoration system with retention management
 */

require_once __DIR__ . '/../functions/config.php';

class BackupSystem {
    
    private string $backupDir;
    private int $retentionDays;
    private array $config;
    
    public function __construct(array $config = []) {
        $this->backupDir = $config['backup_dir'] ?? __DIR__ . '/../backups';
        $this->retentionDays = $config['retention_days'] ?? 7;
        $this->config = array_merge([
            'compress' => true,
            'include_files' => true,
            'exclude_patterns' => [
                'var/logs/*',
                'var/cache/*', 
                'var/sessions/*',
                'backups/*',
                '.git/*',
                'node_modules/*',
                '*.tmp'
            ]
        ], $config);
        
        // Ensure backup directory exists
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Create complete system backup
     */
    public function createBackup(): array {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "cis_backup_{$timestamp}";
        
        $results = [
            'success' => false,
            'backup_name' => $backupName,
            'timestamp' => $timestamp,
            'components' => []
        ];
        
        try {
            // Create backup directory
            $backupPath = $this->backupDir . '/' . $backupName;
            mkdir($backupPath, 0755, true);
            
            // Backup database
            $dbResult = $this->backupDatabase($backupPath);
            $results['components']['database'] = $dbResult;
            
            // Backup files
            if ($this->config['include_files']) {
                $fileResult = $this->backupFiles($backupPath);
                $results['components']['files'] = $fileResult;
            }
            
            // Create backup manifest
            $manifest = $this->createManifest($results);
            file_put_contents($backupPath . '/backup_manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            
            // Compress backup if configured
            if ($this->config['compress']) {
                $compressResult = $this->compressBackup($backupPath, $backupName);
                $results['components']['compression'] = $compressResult;
                
                if ($compressResult['success']) {
                    // Remove uncompressed directory
                    $this->removeDirectory($backupPath);
                    $results['backup_file'] = $backupName . '.tar.gz';
                }
            }
            
            // Clean old backups
            $cleanupResult = $this->cleanupOldBackups();
            $results['components']['cleanup'] = $cleanupResult;
            
            $results['success'] = true;
            $results['message'] = 'Backup created successfully';
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Backup database
     */
    private function backupDatabase(string $backupPath): array {
        global $mysqli;
        
        $result = [
            'success' => false,
            'file' => '',
            'size' => 0
        ];
        
        try {
            if (!$mysqli) {
                throw new Exception('Database connection not available');
            }
            
            $sqlFile = $backupPath . '/database_backup.sql';
            $fp = fopen($sqlFile, 'w');
            
            if (!$fp) {
                throw new Exception('Cannot create database backup file');
            }
            
            // Write header
            fwrite($fp, "-- CIS Database Backup\n");
            fwrite($fp, "-- Created: " . date('Y-m-d H:i:s') . "\n");
            fwrite($fp, "-- Database: " . DB_NAME . "\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");
            
            // Get all tables
            $tables = [];
            $tablesResult = $mysqli->query("SHOW TABLES");
            while ($row = $tablesResult->fetch_array()) {
                $tables[] = $row[0];
            }
            
            // Backup each table
            foreach ($tables as $table) {
                // Table structure
                fwrite($fp, "-- Table: $table\n");
                fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
                
                $createResult = $mysqli->query("SHOW CREATE TABLE `$table`");
                $createRow = $createResult->fetch_array();
                fwrite($fp, $createRow[1] . ";\n\n");
                
                // Table data
                $dataResult = $mysqli->query("SELECT * FROM `$table`");
                if ($dataResult && $dataResult->num_rows > 0) {
                    fwrite($fp, "-- Data for table: $table\n");
                    
                    while ($row = $dataResult->fetch_assoc()) {
                        $values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . $mysqli->real_escape_string($value) . "'";
                            }
                        }
                        
                        $insert = "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                        fwrite($fp, $insert);
                    }
                    fwrite($fp, "\n");
                }
            }
            
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);
            
            $result['success'] = true;
            $result['file'] = 'database_backup.sql';
            $result['size'] = filesize($sqlFile);
            $result['tables_count'] = count($tables);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Backup files
     */
    private function backupFiles(string $backupPath): array {
        $result = [
            'success' => false,
            'files_count' => 0,
            'total_size' => 0
        ];
        
        try {
            $sourceDir = dirname(__DIR__);
            $filesDir = $backupPath . '/files';
            mkdir($filesDir, 0755, true);
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                $relativePath = substr($file->getPathname(), strlen($sourceDir) + 1);
                
                // Skip excluded patterns
                if ($this->shouldExcludeFile($relativePath)) {
                    continue;
                }
                
                $targetPath = $filesDir . '/' . $relativePath;
                $targetDir = dirname($targetPath);
                
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                if (copy($file->getPathname(), $targetPath)) {
                    $result['files_count']++;
                    $result['total_size'] += $file->getSize();
                }
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check if file should be excluded
     */
    private function shouldExcludeFile(string $relativePath): bool {
        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Compress backup directory
     */
    private function compressBackup(string $backupPath, string $backupName): array {
        $result = [
            'success' => false,
            'compressed_size' => 0
        ];
        
        try {
            $tarFile = $this->backupDir . '/' . $backupName . '.tar.gz';
            
            // Use tar command for compression
            $command = sprintf(
                'cd %s && tar -czf %s %s',
                escapeshellarg(dirname($backupPath)),
                escapeshellarg($tarFile),
                escapeshellarg(basename($backupPath))
            );
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($tarFile)) {
                $result['success'] = true;
                $result['compressed_size'] = filesize($tarFile);
                $result['compression_ratio'] = $this->calculateDirectorySize($backupPath) / $result['compressed_size'];
            } else {
                throw new Exception('Compression failed: ' . implode("\n", $output));
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Calculate directory size
     */
    private function calculateDirectorySize(string $directory): int {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $directory): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        
        rmdir($directory);
    }
    
    /**
     * Create backup manifest
     */
    private function createManifest(array $backupInfo): array {
        return [
            'backup_name' => $backupInfo['backup_name'],
            'timestamp' => $backupInfo['timestamp'],
            'created_at' => date('c'),
            'php_version' => PHP_VERSION,
            'database' => DB_NAME,
            'components' => $backupInfo['components'],
            'system_info' => [
                'hostname' => gethostname(),
                'php_version' => PHP_VERSION,
                'extensions' => get_loaded_extensions()
            ]
        ];
    }
    
    /**
     * Clean up old backups
     */
    private function cleanupOldBackups(): array {
        $result = [
            'success' => false,
            'cleaned_count' => 0,
            'freed_space' => 0
        ];
        
        try {
            $cutoffDate = time() - ($this->retentionDays * 24 * 60 * 60);
            $cleaned = 0;
            $freedSpace = 0;
            
            $files = glob($this->backupDir . '/cis_backup_*');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffDate) {
                    $size = filesize($file);
                    if (unlink($file)) {
                        $cleaned++;
                        $freedSpace += $size;
                    }
                }
            }
            
            $result['success'] = true;
            $result['cleaned_count'] = $cleaned;
            $result['freed_space'] = $freedSpace;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * List available backups
     */
    public function listBackups(): array {
        $backups = [];
        
        $files = glob($this->backupDir . '/cis_backup_*.tar.gz');
        
        foreach ($files as $file) {
            $basename = basename($file);
            $backups[] = [
                'name' => $basename,
                'path' => $file,
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'age_days' => (time() - filemtime($file)) / (24 * 60 * 60)
            ];
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return filemtime($b['path']) - filemtime($a['path']);
        });
        
        return $backups;
    }
    
    /**
     * Restore from backup
     */
    public function restoreBackup(string $backupName): array {
        $result = [
            'success' => false,
            'restored_components' => []
        ];
        
        try {
            $backupFile = $this->backupDir . '/' . $backupName;
            
            if (!file_exists($backupFile)) {
                throw new Exception("Backup file not found: $backupName");
            }
            
            // Extract backup
            $extractDir = $this->backupDir . '/restore_' . time();
            mkdir($extractDir, 0755, true);
            
            $command = sprintf(
                'cd %s && tar -xzf %s',
                escapeshellarg($extractDir),
                escapeshellarg($backupFile)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('Failed to extract backup');
            }
            
            // Find extracted directory
            $extractedDirs = glob($extractDir . '/cis_backup_*');
            if (empty($extractedDirs)) {
                throw new Exception('No backup directory found in archive');
            }
            
            $backupDir = $extractedDirs[0];
            
            // Restore database
            $sqlFile = $backupDir . '/database_backup.sql';
            if (file_exists($sqlFile)) {
                $dbResult = $this->restoreDatabase($sqlFile);
                $result['restored_components']['database'] = $dbResult;
            }
            
            // Restore files (optional - requires confirmation)
            // This would overwrite existing files
            
            // Clean up extraction directory
            $this->removeDirectory($extractDir);
            
            $result['success'] = true;
            $result['message'] = 'Backup restored successfully';
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Restore database from SQL file
     */
    private function restoreDatabase(string $sqlFile): array {
        global $mysqli;
        
        $result = [
            'success' => false,
            'queries_executed' => 0
        ];
        
        try {
            if (!$mysqli) {
                throw new Exception('Database connection not available');
            }
            
            $sql = file_get_contents($sqlFile);
            $queries = explode(";\n", $sql);
            $executed = 0;
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (empty($query) || substr($query, 0, 2) === '--') {
                    continue;
                }
                
                if ($mysqli->query($query)) {
                    $executed++;
                } else {
                    // Log error but continue
                    error_log("Restore query failed: " . $mysqli->error);
                }
            }
            
            $result['success'] = true;
            $result['queries_executed'] = $executed;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
}

// CLI interface
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'backup';
    
    switch ($action) {
        case 'backup':
            echo "Creating backup...\n";
            $backup = new BackupSystem();
            $result = $backup->createBackup();
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'list':
            echo "Available backups:\n";
            $backup = new BackupSystem();
            $backups = $backup->listBackups();
            foreach ($backups as $b) {
                printf("%-30s %10s %s\n", $b['name'], 
                       number_format($b['size'] / 1024 / 1024, 2) . 'MB', 
                       $b['created']);
            }
            break;
            
        case 'restore':
            $backupName = $argv[2] ?? null;
            if (!$backupName) {
                echo "Usage: php backup_system.php restore <backup_name>\n";
                exit(1);
            }
            echo "Restoring backup: $backupName\n";
            $backup = new BackupSystem();
            $result = $backup->restoreBackup($backupName);
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        default:
            echo "Usage: php backup_system.php [backup|list|restore]\n";
            exit(1);
    }
}
