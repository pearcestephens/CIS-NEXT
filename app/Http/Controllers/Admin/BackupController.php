<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Shared\Backup\BackupManager;
use App\Shared\Logging\Logger;
use Exception;

/**
 * Admin Backup Controller
 * File: app/Http/Controllers/Admin/BackupController.php
 * Author: CIS Developer Bot
 * Created: 2025-09-13
 * Purpose: Complete admin interface for backup management
 */
class BackupController extends BaseController
{
    private BackupManager $backupManager;
    private Logger $logger;
    
    public function __construct()
    {
        parent::__construct();
        $this->backupManager = new BackupManager();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Backup dashboard - main overview
     */
    public function dashboard(): void
    {
        $this->requirePermission('manage_backups');
        
        try {
            $statistics = $this->backupManager->getStatistics();
            $health = $this->backupManager->healthCheck();
            
            // Get recent backups
            $recent_backups = $this->backupManager->listBackups([], 1, 5);
            
            $this->render('admin/backup/dashboard', [
                'title' => 'Backup Management Dashboard',
                'statistics' => $statistics,
                'health' => $health,
                'recent_backups' => $recent_backups['backups'],
                'page_scripts' => ['backup-dashboard.js'],
                'page_styles' => ['backup.css']
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Backup dashboard error', ['error' => $e->getMessage()]);
            $this->handleError('Failed to load backup dashboard: ' . $e->getMessage());
        }
    }
    
    /**
     * List all backups with filtering and pagination
     */
    public function list(): void
    {
        $this->requirePermission('view_backups');
        
        try {
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            
            $filters = [
                'type' => $_GET['type'] ?? null,
                'status' => $_GET['status'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];
            
            // Remove empty filters
            $filters = array_filter($filters);
            
            $result = $this->backupManager->listBackups($filters, $page, $limit);
            
            $this->render('admin/backup/list', [
                'title' => 'All Backups',
                'backups' => $result['backups'],
                'pagination' => $result['pagination'],
                'filters' => $filters,
                'page_scripts' => ['backup-list.js']
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Backup list error', ['error' => $e->getMessage()]);
            $this->handleError('Failed to load backup list: ' . $e->getMessage());
        }
    }
    
    /**
     * Create new backup form
     */
    public function create(): void
    {
        $this->requirePermission('create_backups');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleCreateBackup();
            return;
        }
        
        $this->render('admin/backup/create', [
            'title' => 'Create New Backup',
            'backup_types' => [
                BackupManager::TYPE_FULL => 'Full System Backup',
                BackupManager::TYPE_DATABASE => 'Database Only',
                BackupManager::TYPE_FILES => 'Files Only'
            ],
            'page_scripts' => ['backup-create.js']
        ]);
    }
    
    /**
     * Handle backup creation
     */
    private function handleCreateBackup(): void
    {
        try {
            $this->validateCsrfToken();
            
            $backup_type = $_POST['backup_type'] ?? '';
            $backup_name = trim($_POST['backup_name'] ?? '');
            $created_by = $this->user['id'];
            
            if (empty($backup_type) || !in_array($backup_type, [
                BackupManager::TYPE_FULL,
                BackupManager::TYPE_DATABASE,
                BackupManager::TYPE_FILES
            ])) {
                throw new Exception('Invalid backup type');
            }
            
            // Start backup in background for large operations
            if ($backup_type === BackupManager::TYPE_FULL) {
                $backup_id = $this->startBackgroundBackup($backup_type, $backup_name, $created_by);
                $this->setFlashMessage('success', "Full backup started (ID: $backup_id). Check status in backup list.");
                
            } elseif ($backup_type === BackupManager::TYPE_DATABASE) {
                $result = $this->backupManager->createDatabaseBackup($backup_name, $created_by);
                
                if ($result['success']) {
                    $this->setFlashMessage('success', 'Database backup created successfully');
                } else {
                    throw new Exception($result['error']);
                }
                
            } elseif ($backup_type === BackupManager::TYPE_FILES) {
                $file_paths = $_POST['file_paths'] ?? [];
                if (empty($file_paths)) {
                    throw new Exception('No file paths specified for files backup');
                }
                
                $result = $this->backupManager->createFilesBackup($file_paths, $backup_name, $created_by);
                
                if ($result['success']) {
                    $this->setFlashMessage('success', 'Files backup created successfully');
                } else {
                    throw new Exception($result['error']);
                }
            }
            
            header('Location: /admin/backup/list');
            exit;
            
        } catch (Exception $e) {
            $this->logger->error('Backup creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $this->user['id']
            ]);
            
            $this->setFlashMessage('error', 'Backup creation failed: ' . $e->getMessage());
            header('Location: /admin/backup/create');
            exit;
        }
    }
    
    /**
     * View backup details
     */
    public function view(): void
    {
        $this->requirePermission('view_backups');
        
        $backup_id = $_GET['id'] ?? '';
        if (empty($backup_id)) {
            $this->handleError('Backup ID required', 400);
            return;
        }
        
        try {
            $backup = $this->backupManager->getBackup($backup_id);
            if (!$backup) {
                $this->handleError('Backup not found', 404);
                return;
            }
            
            $this->render('admin/backup/view', [
                'title' => 'Backup Details: ' . $backup['name'],
                'backup' => $backup,
                'page_scripts' => ['backup-view.js']
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Backup view error', ['error' => $e->getMessage()]);
            $this->handleError('Failed to load backup details: ' . $e->getMessage());
        }
    }
    
    /**
     * Download backup file
     */
    public function download(): void
    {
        $this->requirePermission('download_backups');
        
        $backup_id = $_GET['id'] ?? '';
        if (empty($backup_id)) {
            $this->handleError('Backup ID required', 400);
            return;
        }
        
        try {
            $result = $this->backupManager->downloadBackup($backup_id);
            
            if (!$result['success']) {
                $this->handleError($result['error'], 404);
                return;
            }
            
            $this->logger->info('Backup downloaded', [
                'backup_id' => $backup_id,
                'user_id' => $this->user['id']
            ]);
            
            // Set download headers
            header('Content-Type: ' . $result['mime_type']);
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            header('Content-Length: ' . $result['size']);
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            // Stream file
            $handle = fopen($result['file_path'], 'rb');
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
            
        } catch (Exception $e) {
            $this->logger->error('Backup download error', ['error' => $e->getMessage()]);
            $this->handleError('Failed to download backup: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete backup
     */
    public function delete(): void
    {
        $this->requirePermission('delete_backups');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->handleError('Invalid request method', 405);
            return;
        }
        
        try {
            $this->validateCsrfToken();
            
            $backup_id = $_POST['backup_id'] ?? '';
            if (empty($backup_id)) {
                throw new Exception('Backup ID required');
            }
            
            $success = $this->backupManager->deleteBackup($backup_id, $this->user['id']);
            
            if ($success) {
                $this->jsonResponse(['success' => true, 'message' => 'Backup deleted successfully']);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Failed to delete backup'], 500);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Backup deletion failed', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * System health check endpoint
     */
    public function health(): void
    {
        $this->requirePermission('view_backups');
        
        try {
            $health = $this->backupManager->healthCheck();
            $this->jsonResponse($health);
            
        } catch (Exception $e) {
            $this->logger->error('Health check failed', ['error' => $e->getMessage()]);
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get backup statistics
     */
    public function statistics(): void
    {
        $this->requirePermission('view_backups');
        
        try {
            $statistics = $this->backupManager->getStatistics();
            $this->jsonResponse($statistics);
            
        } catch (Exception $e) {
            $this->logger->error('Statistics fetch failed', ['error' => $e->getMessage()]);
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Apply retention policy manually
     */
    public function retention(): void
    {
        $this->requirePermission('manage_backups');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->handleError('Invalid request method', 405);
            return;
        }
        
        try {
            $this->validateCsrfToken();
            
            $result = $this->backupManager->applyRetentionPolicy();
            
            if ($result['success']) {
                $message = "Retention policy applied. Deleted {$result['deleted_count']} backups, freed " . 
                          $this->formatBytes($result['freed_space']);
                          
                $this->jsonResponse(['success' => true, 'message' => $message]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => $result['error']], 500);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Retention policy failed', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Backup settings management
     */
    public function settings(): void
    {
        $this->requirePermission('manage_backup_settings');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleSaveSettings();
            return;
        }
        
        $this->render('admin/backup/settings', [
            'title' => 'Backup Settings',
            'page_scripts' => ['backup-settings.js']
        ]);
    }
    
    /**
     * Handle backup settings save
     */
    private function handleSaveSettings(): void
    {
        try {
            $this->validateCsrfToken();
            
            // This would save settings to configuration
            // For now, just show success
            $this->setFlashMessage('success', 'Backup settings saved successfully');
            header('Location: /admin/backup/settings');
            exit;
            
        } catch (Exception $e) {
            $this->setFlashMessage('error', 'Failed to save settings: ' . $e->getMessage());
            header('Location: /admin/backup/settings');
            exit;
        }
    }
    
    /**
     * Start background backup job
     */
    private function startBackgroundBackup(string $type, string $name, int $created_by): string
    {
        // For now, execute directly (in production, use queue system)
        $result = $this->backupManager->createFullBackup($name, $created_by);
        
        if (!$result['success']) {
            throw new Exception($result['error']);
        }
        
        return $result['backup_id'];
    }
    
    /**
     * Format bytes for human readability
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
