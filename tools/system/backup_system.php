<?php
/**
 * CIS Backup System
 * File: tools/system/backup_system.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Comprehensive backup and restore system for CIS
 */

declare(strict_types=1);

require_once __DIR__ . '/../../functions/config.php';
require_once __DIR__ . '/../../app/Shared/Bootstrap.php';

use App\Shared\Bootstrap;
use App\Shared\Logging\Logger;

class CISBackupSystem
{
    private Logger $logger;
    private array $config;
    private string $backupPath;
    private string $hostname;
    
    public function __construct()
    {
        Bootstrap::init(__DIR__ . '/../..');
        
        $this->logger = Logger::getInstance();
        $this->hostname = gethostname() ?: 'unknown';
        
        $this->config = [
            'backup_path' => __DIR__ . '/../../backups',
            'retention_days' => 30,
            'compression' => true,
            'encryption' => false, // Set to true in production
            'max_backup_size' => 1024 * 1024 * 1024, // 1GB
            'database_backup' => true,
            'file_backup' => true,
            'config_backup' => true,
            'exclude_patterns' => [
                'var/cache/*',
                'var/logs/*',
                'var/screenshots/*',
                'node_modules',
                '.git',
                '*.tmp',
                '*.lock'
            ]
        ];
        
        $this->backupPath = $this->config['backup_path'];
        
        // Ensure backup directory exists
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    /**
     * Create complete system backup
     */
    public function createBackup(array $options = []): array
    {
        $backupId = 'backup_' . date('Y-m-d_H-i-s') . '_' . uniqid();
        $backupStart = microtime(true);
        
        $this->log("Starting backup: {$backupId}", 'info');
        
        $result = [
            'backup_id' => $backupId,
            'timestamp' => date('c'),
            'hostname' => $this->hostname,
            'components' => [],
            'files' => [],
            'total_size' => 0,
            'success' => false,
            'errors' => [],
            'duration' => 0
        ];
        
        try {
            // Create backup directory
            $backupDir = $this->backupPath . '/' . $backupId;
            mkdir($backupDir, 0755, true);
            
            // Database backup
            if ($this->config['database_backup'] && !isset($options['skip_database'])) {
                $dbResult = $this->backupDatabase($backupDir);
                $result['components']['database'] = $dbResult;
                
                if ($dbResult['success']) {
                    $result['files'] = array_merge($result['files'], $dbResult['files']);
                    $result['total_size'] += $dbResult['size'];
                } else {
                    $result['errors'] = array_merge($result['errors'], $dbResult['errors']);
                }
            }
            
            // Configuration backup
            if ($this->config['config_backup'] && !isset($options['skip_config'])) {
                $configResult = $this->backupConfiguration($backupDir);
                $result['components']['configuration'] = $configResult;
                
                if ($configResult['success']) {
                    $result['files'] = array_merge($result['files'], $configResult['files']);
                    $result['total_size'] += $configResult['size'];
                } else {
                    $result['errors'] = array_merge($result['errors'], $configResult['errors']);
                }
            }
            
            // Application files backup
            if ($this->config['file_backup'] && !isset($options['skip_files'])) {
                $filesResult = $this->backupFiles($backupDir);
                $result['components']['files'] = $filesResult;
                
                if ($filesResult['success']) {
                    $result['files'] = array_merge($result['files'], $filesResult['files']);
                    $result['total_size'] += $filesResult['size'];
                } else {
                    $result['errors'] = array_merge($result['errors'], $filesResult['errors']);
                }
            }
            
            // Create backup manifest
            $manifest = $this->createManifest($result);
            file_put_contents($backupDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            
            // Compress backup if enabled
            if ($this->config['compression']) {
                $compressResult = $this->compressBackup($backupDir);
                if ($compressResult['success']) {
                    $result['compressed_file'] = $compressResult['file'];
                    $result['compressed_size'] = $compressResult['size'];
                    
                    // Remove uncompressed directory
                    $this->removeDirectory($backupDir);
                }
            }
            
            $result['duration'] = round(microtime(true) - $backupStart, 2);
            $result['success'] = empty($result['errors']);
            
            $this->log("Backup completed: {$backupId} (" . 
                      round($result['total_size'] / 1024 / 1024, 2) . " MB)", 'info');
            
            // Clean old backups
            $this->cleanOldBackups();
            
        } catch (\Throwable $e) {
            $result['errors'][] = "Backup failed: " . $e->getMessage();
            $result['duration'] = round(microtime(true) - $backupStart, 2);
            
            $this->log("Backup failed: {$backupId} - " . $e->getMessage(), 'error');
        }
        
        return $result;
    }
    
    /**
     * Backup database
     */
    private function backupDatabase(string $backupDir): array
    {
        $result = [
            'success' => false,
            'files' => [],
            'size' => 0,
            'errors' => []
        ];
        
        try {
            $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
            $dbName = $_ENV['DB_NAME'] ?? '';
            $dbUser = $_ENV['DB_USER'] ?? '';
            $dbPass = $_ENV['DB_PASS'] ?? '';
            
            if (empty($dbName) || empty($dbUser)) {
                $result['errors'][] = 'Database credentials not configured';
                return $result;
            }
            
            $dumpFile = $backupDir . '/database.sql';
            
            // Use mysqldump command
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($dumpFile)
            );
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($dumpFile)) {
                $size = filesize($dumpFile);
                
                $result['success'] = true;
                $result['files'][] = $dumpFile;
                $result['size'] = $size;
                
                $this->log("Database backup created: " . round($size / 1024 / 1024, 2) . " MB", 'info');
                
            } else {
                // Fallback to PHP-based backup
                $result = $this->backupDatabasePHP($backupDir);
            }
            
        } catch (\Exception $e) {
            $result['errors'][] = "Database backup failed: " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * PHP-based database backup (fallback)
     */
    private function backupDatabasePHP(string $backupDir): array
    {
        $result = [
            'success' => false,
            'files' => [],
            'size' => 0,
            'errors' => []
        ];
        
        try {
            $db = \App\Infra\Persistence\MariaDB\Database::getInstance();
            $dumpFile = $backupDir . '/database.sql';
            
            $fp = fopen($dumpFile, 'w');
            if (!$fp) {
                $result['errors'][] = 'Cannot create database dump file';
                return $result;
            }
            
            // Write header
            fwrite($fp, "-- CIS Database Backup\n");
            fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($fp, "-- Host: " . ($_ENV['DB_HOST'] ?? 'localhost') . "\n");
            fwrite($fp, "-- Database: " . ($_ENV['DB_NAME'] ?? 'unknown') . "\n\n");
            
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($fp, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
            
            // Get all tables
            $stmt = $db->execute("SHOW TABLES");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                $this->dumpTable($db, $fp, $table);
            }
            
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);
            
            $size = filesize($dumpFile);
            
            $result['success'] = true;
            $result['files'][] = $dumpFile;
            $result['size'] = $size;
            
            $this->log("PHP database backup created: " . round($size / 1024 / 1024, 2) . " MB", 'info');
            
        } catch (\Exception $e) {
            $result['errors'][] = "PHP database backup failed: " . $e->getMessage();
            if (isset($fp)) {
                fclose($fp);
            }
        }
        
        return $result;
    }
    
    /**
     * Dump single table
     */
    private function dumpTable($db, $fp, string $table): void
    {
        // Table structure
        $stmt = $db->execute("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($fp, $row['Create Table'] . ";\n\n");
        
        // Table data
        $stmt = $db->execute("SELECT * FROM `{$table}`");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            
            fwrite($fp, "INSERT INTO `{$table}` ({$columnList}) VALUES\n");
            
            $rowCount = count($rows);
            foreach ($rows as $index => $row) {
                $values = array_map(function($value) use ($db) {
                    return $value === null ? 'NULL' : $db->quote($value);
                }, array_values($row));
                
                $valueList = implode(', ', $values);
                $comma = ($index < $rowCount - 1) ? ',' : ';';
                
                fwrite($fp, "({$valueList}){$comma}\n");
            }
            
            fwrite($fp, "\n");
        }
    }
    
    /**
     * Backup configuration files
     */
    private function backupConfiguration(string $backupDir): array
    {
        $result = [
            'success' => false,
            'files' => [],
            'size' => 0,
            'errors' => []
        ];
        
        try {
            $configDir = $backupDir . '/config';
            mkdir($configDir, 0755, true);
            
            $configFiles = [
                'functions/config.php' => 'Main configuration',
                '.env' => 'Environment variables',
                '.htaccess' => 'Web server config',
                'composer.json' => 'PHP dependencies',
                'package.json' => 'Node.js dependencies'
            ];
            
            $totalSize = 0;
            $copiedFiles = [];
            
            foreach ($configFiles as $file => $description) {
                $sourcePath = __DIR__ . '/../../' . $file;
                $destPath = $configDir . '/' . basename($file);
                
                if (file_exists($sourcePath)) {
                    if (copy($sourcePath, $destPath)) {
                        $size = filesize($destPath);
                        $totalSize += $size;
                        $copiedFiles[] = $destPath;
                        
                        $this->log("Backed up config: {$file} ({$size} bytes)", 'debug');
                    } else {
                        $result['errors'][] = "Failed to backup config file: {$file}";
                    }
                }
            }
            
            // Backup database configuration from configurations table
            try {
                $db = \App\Infra\Persistence\MariaDB\Database::getInstance();
                $stmt = $db->execute("SELECT * FROM configurations ORDER BY config_key");
                $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $configData = [
                    'timestamp' => date('c'),
                    'hostname' => $this->hostname,
                    'configurations' => $configs
                ];
                
                $configJsonFile = $configDir . '/database_configurations.json';
                file_put_contents($configJsonFile, json_encode($configData, JSON_PRETTY_PRINT));
                
                $size = filesize($configJsonFile);
                $totalSize += $size;
                $copiedFiles[] = $configJsonFile;
                
            } catch (\Exception $e) {
                $result['errors'][] = "Failed to backup database configurations: " . $e->getMessage();
            }
            
            $result['success'] = !empty($copiedFiles);
            $result['files'] = $copiedFiles;
            $result['size'] = $totalSize;
            
            $this->log("Configuration backup completed: " . count($copiedFiles) . " files", 'info');
            
        } catch (\Exception $e) {
            $result['errors'][] = "Configuration backup failed: " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Backup application files
     */
    private function backupFiles(string $backupDir): array
    {
        $result = [
            'success' => false,
            'files' => [],
            'size' => 0,
            'errors' => []
        ];
        
        try {
            $filesDir = $backupDir . '/files';
            mkdir($filesDir, 0755, true);
            
            $sourceDir = __DIR__ . '/../..';
            $totalSize = 0;
            $fileCount = 0;
            
            // Use rsync if available, otherwise use recursive copy
            if ($this->commandExists('rsync')) {
                $result = $this->backupFilesRsync($sourceDir, $filesDir);
            } else {
                $result = $this->backupFilesRecursive($sourceDir, $filesDir);
            }
            
        } catch (\Exception $e) {
            $result['errors'][] = "File backup failed: " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Backup files using rsync
     */
    private function backupFilesRsync(string $sourceDir, string $destDir): array
    {
        $excludeOptions = '';
        foreach ($this->config['exclude_patterns'] as $pattern) {
            $excludeOptions .= " --exclude='" . escapeshellarg($pattern) . "'";
        }
        
        $command = sprintf(
            'rsync -av --relative %s %s/ %s/ 2>&1',
            $excludeOptions,
            escapeshellarg($sourceDir),
            escapeshellarg($destDir)
        );
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        $result = [
            'success' => $returnCode === 0,
            'files' => [],
            'size' => 0,
            'errors' => []
        ];
        
        if ($returnCode === 0) {
            $result['size'] = $this->getDirectorySize($destDir);
            $result['files'] = [$destDir];
            
            $this->log("Rsync file backup completed: " . 
                      round($result['size'] / 1024 / 1024, 2) . " MB", 'info');
        } else {
            $result['errors'][] = "Rsync failed: " . implode("\n", $output);
        }
        
        return $result;
    }
    
    /**
     * Backup files using recursive copy
     */
    private function backupFilesRecursive(string $sourceDir, string $destDir): array
    {
        $result = [
            'success' => false,
            'files' => [],
            'size' => 0,
            'errors' => []
        ];
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            $totalSize = 0;
            $fileCount = 0;
            
            foreach ($iterator as $path) {
                $relativePath = substr($path->getPathname(), strlen($sourceDir) + 1);
                
                // Check exclude patterns
                if ($this->isExcluded($relativePath)) {
                    continue;
                }
                
                $destPath = $destDir . '/' . $relativePath;
                
                if ($path->isDir()) {
                    if (!is_dir($destPath)) {
                        mkdir($destPath, 0755, true);
                    }
                } else {
                    $destDirPath = dirname($destPath);
                    if (!is_dir($destDirPath)) {
                        mkdir($destDirPath, 0755, true);
                    }
                    
                    if (copy($path->getPathname(), $destPath)) {
                        $size = $path->getSize();
                        $totalSize += $size;
                        $fileCount++;
                        
                        $result['files'][] = $destPath;
                    } else {
                        $result['errors'][] = "Failed to copy: {$relativePath}";
                    }
                }
            }
            
            $result['success'] = $fileCount > 0;
            $result['size'] = $totalSize;
            
            $this->log("Recursive file backup completed: {$fileCount} files, " . 
                      round($totalSize / 1024 / 1024, 2) . " MB", 'info');
            
        } catch (\Exception $e) {
            $result['errors'][] = "Recursive backup failed: " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check if file/directory should be excluded
     */
    private function isExcluded(string $path): bool
    {
        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (fnmatch($pattern, $path) || strpos($path, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Compress backup directory
     */
    private function compressBackup(string $backupDir): array
    {
        $result = [
            'success' => false,
            'file' => '',
            'size' => 0,
            'errors' => []
        ];
        
        try {
            $archiveFile = $backupDir . '.tar.gz';
            
            if ($this->commandExists('tar')) {
                // Use tar command
                $command = sprintf(
                    'cd %s && tar -czf %s %s 2>&1',
                    escapeshellarg(dirname($backupDir)),
                    escapeshellarg($archiveFile),
                    escapeshellarg(basename($backupDir))
                );
                
                $output = [];
                $returnCode = 0;
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0 && file_exists($archiveFile)) {
                    $result['success'] = true;
                    $result['file'] = $archiveFile;
                    $result['size'] = filesize($archiveFile);
                    
                    $this->log("Backup compressed: " . 
                              round($result['size'] / 1024 / 1024, 2) . " MB", 'info');
                } else {
                    $result['errors'][] = "Tar compression failed: " . implode("\n", $output);
                }
                
            } else {
                // Use PHP ZipArchive as fallback
                $result = $this->compressBackupZip($backupDir);
            }
            
        } catch (\Exception $e) {
            $result['errors'][] = "Compression failed: " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Compress using PHP ZipArchive
     */
    private function compressBackupZip(string $backupDir): array
    {
        $result = [
            'success' => false,
            'file' => '',
            'size' => 0,
            'errors' => []
        ];
        
        if (!class_exists('ZipArchive')) {
            $result['errors'][] = 'ZipArchive class not available';
            return $result;
        }
        
        try {
            $zipFile = $backupDir . '.zip';
            $zip = new \ZipArchive();
            
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                $result['errors'][] = 'Cannot create zip file';
                return $result;
            }
            
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($backupDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $path) {
                if ($path->isFile()) {
                    $relativePath = substr($path->getPathname(), strlen($backupDir) + 1);
                    $zip->addFile($path->getPathname(), $relativePath);
                }
            }
            
            $zip->close();
            
            if (file_exists($zipFile)) {
                $result['success'] = true;
                $result['file'] = $zipFile;
                $result['size'] = filesize($zipFile);
                
                $this->log("Backup compressed (ZIP): " . 
                          round($result['size'] / 1024 / 1024, 2) . " MB", 'info');
            } else {
                $result['errors'][] = 'Zip file was not created';
            }
            
        } catch (\Exception $e) {
            $result['errors'][] = "ZIP compression failed: " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Create backup manifest
     */
    private function createManifest(array $backupResult): array
    {
        return [
            'backup_id' => $backupResult['backup_id'],
            'timestamp' => $backupResult['timestamp'],
            'hostname' => $backupResult['hostname'],
            'version' => '1.0',
            'components' => array_keys($backupResult['components']),
            'total_size' => $backupResult['total_size'],
            'success' => $backupResult['success'],
            'php_version' => PHP_VERSION,
            'system_info' => [
                'os' => PHP_OS,
                'architecture' => php_uname('m'),
                'kernel' => php_uname('r')
            ],
            'database_info' => [
                'host' => $_ENV['DB_HOST'] ?? 'unknown',
                'name' => $_ENV['DB_NAME'] ?? 'unknown'
            ],
            'config' => $this->config
        ];
    }
    
    /**
     * List available backups
     */
    public function listBackups(): array
    {
        $backups = [];
        
        if (!is_dir($this->backupPath)) {
            return $backups;
        }
        
        $files = scandir($this->backupPath);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $fullPath = $this->backupPath . '/' . $file;
            
            if (is_dir($fullPath)) {
                // Uncompressed backup directory
                $manifestFile = $fullPath . '/manifest.json';
                if (file_exists($manifestFile)) {
                    $manifest = json_decode(file_get_contents($manifestFile), true);
                    $backups[] = array_merge($manifest, [
                        'type' => 'directory',
                        'path' => $fullPath,
                        'size' => $this->getDirectorySize($fullPath)
                    ]);
                }
            } elseif (preg_match('/^backup_.*\.(tar\.gz|zip)$/', $file)) {
                // Compressed backup file
                $backups[] = [
                    'backup_id' => pathinfo($file, PATHINFO_FILENAME),
                    'type' => pathinfo($file, PATHINFO_EXTENSION),
                    'path' => $fullPath,
                    'size' => filesize($fullPath),
                    'timestamp' => date('c', filemtime($fullPath))
                ];
            }
        }
        
        // Sort by timestamp (newest first)
        usort($backups, function($a, $b) {
            return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
        });
        
        return $backups;
    }
    
    /**
     * Delete old backups based on retention policy
     */
    private function cleanOldBackups(): void
    {
        $backups = $this->listBackups();
        $cutoffDate = time() - ($this->config['retention_days'] * 24 * 3600);
        
        foreach ($backups as $backup) {
            $backupTime = strtotime($backup['timestamp'] ?? '');
            
            if ($backupTime && $backupTime < $cutoffDate) {
                if ($backup['type'] === 'directory') {
                    $this->removeDirectory($backup['path']);
                } else {
                    unlink($backup['path']);
                }
                
                $this->log("Deleted old backup: " . ($backup['backup_id'] ?? 'unknown'), 'info');
            }
        }
    }
    
    /**
     * Utility methods
     */
    private function commandExists(string $command): bool
    {
        $which = shell_exec("which {$command} 2>/dev/null");
        return !empty($which);
    }
    
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            ) as $file) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($directory);
    }
    
    private function log(string $message, string $level = 'info'): void
    {
        $this->logger->log($level, $message, ['component' => 'backup_system']);
        
        if (php_sapi_name() === 'cli') {
            echo "[" . strtoupper($level) . "] " . date('Y-m-d H:i:s') . " - {$message}\n";
        }
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $args = $argv ?? [];
    
    $backupSystem = new CISBackupSystem();
    
    if (in_array('--create', $args) || in_array('create', $args)) {
        $options = [];
        
        if (in_array('--skip-database', $args)) {
            $options['skip_database'] = true;
        }
        
        if (in_array('--skip-files', $args)) {
            $options['skip_files'] = true;
        }
        
        if (in_array('--skip-config', $args)) {
            $options['skip_config'] = true;
        }
        
        $result = $backupSystem->createBackup($options);
        
        if ($result['success']) {
            echo "Backup completed successfully: {$result['backup_id']}\n";
            echo "Total size: " . round($result['total_size'] / 1024 / 1024, 2) . " MB\n";
            echo "Duration: {$result['duration']} seconds\n";
            exit(0);
        } else {
            echo "Backup failed:\n";
            foreach ($result['errors'] as $error) {
                echo "  - {$error}\n";
            }
            exit(1);
        }
        
    } elseif (in_array('--list', $args) || in_array('list', $args)) {
        $backups = $backupSystem->listBackups();
        
        if (empty($backups)) {
            echo "No backups found.\n";
            exit(0);
        }
        
        echo "Available backups:\n";
        echo str_repeat('-', 80) . "\n";
        
        foreach ($backups as $backup) {
            $size = round(($backup['size'] ?? 0) / 1024 / 1024, 2);
            echo sprintf("%-40s %-20s %8s MB\n",
                $backup['backup_id'] ?? 'Unknown',
                $backup['timestamp'] ?? 'Unknown',
                $size
            );
        }
        
        exit(0);
        
    } else {
        echo "CIS Backup System\n";
        echo "Usage:\n";
        echo "  php backup_system.php --create [options]   Create new backup\n";
        echo "  php backup_system.php --list               List available backups\n";
        echo "\nOptions for --create:\n";
        echo "  --skip-database    Skip database backup\n";
        echo "  --skip-files       Skip file backup\n";  
        echo "  --skip-config      Skip configuration backup\n";
        exit(1);
    }
}

// Web interface
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $backupSystem = new CISBackupSystem();
    
    switch ($_GET['action']) {
        case 'create':
            $options = [];
            if (isset($_GET['skip_database'])) $options['skip_database'] = true;
            if (isset($_GET['skip_files'])) $options['skip_files'] = true;
            if (isset($_GET['skip_config'])) $options['skip_config'] = true;
            
            $result = $backupSystem->createBackup($options);
            echo json_encode($result);
            break;
            
        case 'list':
            $backups = $backupSystem->listBackups();
            echo json_encode(['backups' => $backups]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Backup System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-save mr-2"></i>CIS Backup System</h4>
                        <p class="mb-0">Comprehensive backup and restore system for database, files, and configuration</p>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <button id="createBackup" class="btn btn-primary btn-block">
                                    <i class="fas fa-plus mr-2"></i>Create New Backup
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button id="listBackups" class="btn btn-info btn-block">
                                    <i class="fas fa-list mr-2"></i>List Backups
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="skipDatabase">
                                    <label class="form-check-label" for="skipDatabase">Skip Database</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="skipFiles">
                                    <label class="form-check-label" for="skipFiles">Skip Files</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="skipConfig">
                                    <label class="form-check-label" for="skipConfig">Skip Configuration</label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="results" class="mt-4"></div>
                        
                        <div class="mt-4">
                            <h6>CLI Usage:</h6>
                            <pre class="bg-dark text-light p-3">
# Create full backup
php backup_system.php --create

# Create backup with options
php backup_system.php --create --skip-files --skip-config

# List available backups
php backup_system.php --list
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
    $('#createBackup').click(function() {
        const button = $(this);
        const originalText = button.html();
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Creating Backup...');
        
        let url = '?action=create';
        if ($('#skipDatabase').is(':checked')) url += '&skip_database=1';
        if ($('#skipFiles').is(':checked')) url += '&skip_files=1';
        if ($('#skipConfig').is(':checked')) url += '&skip_config=1';
        
        $.ajax({
            url: url,
            method: 'GET',
            success: function(data) {
                displayBackupResult(data);
            },
            error: function() {
                $('#results').html('<div class="alert alert-danger">Backup creation failed</div>');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    $('#listBackups').click(function() {
        const button = $(this);
        const originalText = button.html();
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading...');
        
        $.ajax({
            url: '?action=list',
            method: 'GET',
            success: function(data) {
                displayBackupList(data.backups);
            },
            error: function() {
                $('#results').html('<div class="alert alert-danger">Failed to load backup list</div>');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    function displayBackupResult(data) {
        let html = '<div class="card">';
        html += '<div class="card-header">';
        html += '<h6>Backup Result: ' + data.backup_id + '</h6>';
        html += '</div>';
        html += '<div class="card-body">';
        
        if (data.success) {
            html += '<div class="alert alert-success">';
            html += '<i class="fas fa-check mr-2"></i>Backup completed successfully';
            html += '</div>';
        } else {
            html += '<div class="alert alert-danger">';
            html += '<i class="fas fa-exclamation-triangle mr-2"></i>Backup failed';
            html += '</div>';
        }
        
        html += '<div class="row">';
        html += '<div class="col-md-6">';
        html += '<p><strong>Size:</strong> ' + Math.round(data.total_size / 1024 / 1024 * 100) / 100 + ' MB</p>';
        html += '<p><strong>Duration:</strong> ' + data.duration + ' seconds</p>';
        html += '<p><strong>Timestamp:</strong> ' + data.timestamp + '</p>';
        html += '</div>';
        html += '<div class="col-md-6">';
        html += '<p><strong>Hostname:</strong> ' + data.hostname + '</p>';
        html += '<p><strong>Components:</strong> ' + Object.keys(data.components).join(', ') + '</p>';
        html += '</div>';
        html += '</div>';
        
        if (data.errors && data.errors.length > 0) {
            html += '<h6 class="mt-3">Errors:</h6>';
            html += '<ul class="list-group">';
            data.errors.forEach(error => {
                html += '<li class="list-group-item list-group-item-danger">' + error + '</li>';
            });
            html += '</ul>';
        }
        
        html += '</div></div>';
        
        $('#results').html(html);
    }
    
    function displayBackupList(backups) {
        let html = '<div class="card">';
        html += '<div class="card-header">';
        html += '<h6>Available Backups (' + backups.length + ')</h6>';
        html += '</div>';
        html += '<div class="card-body">';
        
        if (backups.length === 0) {
            html += '<p class="text-muted">No backups found.</p>';
        } else {
            html += '<div class="table-responsive">';
            html += '<table class="table table-striped">';
            html += '<thead>';
            html += '<tr>';
            html += '<th>Backup ID</th>';
            html += '<th>Timestamp</th>';
            html += '<th>Size</th>';
            html += '<th>Type</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            backups.forEach(backup => {
                html += '<tr>';
                html += '<td><code>' + (backup.backup_id || 'Unknown') + '</code></td>';
                html += '<td>' + (backup.timestamp || 'Unknown') + '</td>';
                html += '<td>' + Math.round((backup.size || 0) / 1024 / 1024 * 100) / 100 + ' MB</td>';
                html += '<td><span class="badge badge-secondary">' + (backup.type || 'unknown') + '</span></td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
        }
        
        html += '</div></div>';
        
        $('#results').html(html);
    }
    </script>
</body>
</html>
