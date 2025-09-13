<?php
/**
 * Backup System Routes
 * File: routes/backup.php
 * Author: CIS Developer Bot
 * Created: 2025-09-13
 * Purpose: Complete routing for backup management system
 */

use App\Http\Controllers\Admin\BackupController;

// Admin backup routes (protected)
$router->group(['prefix' => 'admin/backup', 'middleware' => ['auth', 'admin']], function($router) {
    
    // Dashboard and overview
    $router->get('/dashboard', [BackupController::class, 'dashboard']);
    $router->get('/', [BackupController::class, 'dashboard']); // Default route
    
    // Backup management
    $router->get('/list', [BackupController::class, 'list']);
    $router->get('/view', [BackupController::class, 'view']);
    $router->get('/create', [BackupController::class, 'create']);
    $router->post('/create', [BackupController::class, 'create']);
    
    // Backup operations
    $router->get('/download', [BackupController::class, 'download']);
    $router->post('/delete', [BackupController::class, 'delete']);
    
    // System management
    $router->post('/retention', [BackupController::class, 'retention']);
    $router->get('/settings', [BackupController::class, 'settings']);
    $router->post('/settings', [BackupController::class, 'settings']);
});

// API routes for backup system
$router->group(['prefix' => 'api/backup', 'middleware' => ['auth', 'admin']], function($router) {
    
    // System status and statistics
    $router->get('/health', [BackupController::class, 'health']);
    $router->get('/statistics', [BackupController::class, 'statistics']);
    
    // Backup operations (for AJAX)
    $router->post('/create', [BackupController::class, 'create']);
    $router->post('/delete', [BackupController::class, 'delete']);
    $router->post('/retention', [BackupController::class, 'retention']);
    
    // Real-time status updates
    $router->get('/status/{backup_id}', function($backup_id) {
        $controller = new BackupController();
        $controller->getBackupStatus($backup_id);
    });
});

// Scheduled backup routes (for cron jobs)
$router->group(['prefix' => 'cron/backup'], function($router) {
    
    // Automated backup execution
    $router->get('/run', function() {
        // This would be called by cron to run scheduled backups
        $controller = new BackupController();
        $controller->runScheduledBackups();
    });
    
    // Retention policy execution
    $router->get('/retention', function() {
        $controller = new BackupController();
        $controller->runRetentionPolicy();
    });
});
