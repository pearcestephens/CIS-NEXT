<?php
/**
 * Admin Database Prefix Management Controller
 * File: app/Http/Controllers/Admin/PrefixManagementController.php
 */

declare(strict_types=1);

namespace App\H    /**
     * API: Get operation history
     */
    public function apiHistory(): void
    {
        header('Content-Type: application/json');
        
        try {
            $limit = (int)($_GET['limit'] ?? 50);
            $history = $this->prefixManager->getOperationHistory($limit);
            $this->jsonResponse(['success' => true, 'data' => $history]);
            
        } catch (\Exception $e) {
            error_log("API History Error: " . $e->getMessage());
            http_response_code(500);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Preview rename operation
     */
    public function apiPreviewRename(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        try {
            // Verify CSRF
            if (!$this->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $this->jsonResponse(['success' => false, 'error' => 'CSRF token validation failed']);
                return;
            }
            
            $current_table = trim($_POST['current_table'] ?? '');
            $new_prefix = trim($_POST['new_prefix'] ?? '');
            
            if (empty($current_table)) {
                $this->jsonResponse(['success' => false, 'error' => 'Current table name is required']);
                return;
            }
            
            // Get preview data
            $preview_data = $this->prefixManager->previewTableRename($current_table, $new_prefix);
            
            // Generate preview HTML
            $preview_html = $this->generatePreviewHTML($preview_data);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'preview_html' => $preview_html,
                    'preview_data' => $preview_data
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("API Preview Rename Error: " . $e->getMessage());
            http_response_code(500);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Bulk rename operations
     */
    public function apiBulkRename(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        try {
            // Verify CSRF
            if (!$this->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $this->jsonResponse(['success' => false, 'error' => 'CSRF token validation failed']);
                return;
            }
            
            $target_prefix = trim($_POST['target_prefix'] ?? '');
            $dry_run = !empty($_POST['dry_run']);
            $skip_system = !empty($_POST['skip_system'] ?? true);
            
            // Get all tables for bulk operation
            $audit_data = $this->prefixManager->auditSchema();
            $tables_to_process = [];
            
            foreach ($audit_data['table_analysis'] as $table) {
                if ($skip_system && $table['type'] === 'system') {
                    continue;
                }
                $tables_to_process[] = $table['name'];
            }
            
            if (empty($tables_to_process)) {
                $this->jsonResponse(['success' => false, 'error' => 'No tables selected for processing']);
                return;
            }
            
            // Execute bulk operation
            $results = $this->prefixManager->bulkRenameWithPrefix($tables_to_process, $target_prefix, $dry_run);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'dry_run' => $dry_run,
                    'processed_count' => count($tables_to_process),
                    'results' => $results
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("API Bulk Rename Error: " . $e->getMessage());
            http_response_code(500);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Generate preview HTML for rename operation
     */
    private function generatePreviewHTML(array $preview_data): string
    {
        $html = '<div class="rename-preview">';
        
        $html .= '<div class="alert alert-info">';
        $html .= '<h6><i class="fas fa-info-circle me-2"></i>Rename Preview</h6>';
        $html .= '<p class="mb-0">The following changes will be made:</p>';
        $html .= '</div>';
        
        $html .= '<table class="table table-sm table-bordered">';
        $html .= '<thead class="table-light">';
        $html .= '<tr><th>Current Name</th><th>New Name</th><th>Operation</th></tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $html .= '<tr>';
        $html .= '<td><code>' . htmlspecialchars($preview_data['current_name']) . '</code></td>';
        $html .= '<td><code>' . htmlspecialchars($preview_data['new_name']) . '</code></td>';
        $html .= '<td><span class="badge bg-primary">RENAME TABLE</span></td>';
        $html .= '</tr>';
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        if (!empty($preview_data['warnings'])) {
            $html .= '<div class="alert alert-warning mt-3">';
            $html .= '<h6><i class="fas fa-exclamation-triangle me-2"></i>Warnings</h6>';
            $html .= '<ul class="mb-0">';
            foreach ($preview_data['warnings'] as $warning) {
                $html .= '<li>' . htmlspecialchars($warning) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        if (!empty($preview_data['dependencies'])) {
            $html .= '<div class="alert alert-info mt-3">';
            $html .= '<h6><i class="fas fa-link me-2"></i>Dependencies Found</h6>';
            $html .= '<ul class="mb-0">';
            foreach ($preview_data['dependencies'] as $dep) {
                $html .= '<li>' . htmlspecialchars($dep) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }in;

require_once __DIR__ . '/../../../Shared/Database/PrefixManager.php';
require_once __DIR__ . '/../../../../functions/config.php';

use App\Shared\Database\PrefixManager;

class PrefixManagementController
{
    private \mysqli $db;
    private PrefixManager $prefixManager;
    
    public function __construct()
    {
        $this->db = get_db_connection();
        $this->prefixManager = new PrefixManager($this->db);
    }
    
    /**
     * Show prefix management dashboard
     */
    public function dashboard(): void
    {
        try {
            // Get current schema audit
            $audit_data = $this->prefixManager->auditSchema();
            
            // Get recent operations
            $recent_operations = $this->prefixManager->getOperationHistory(20);
            
            // Prepare data for view
            $data = [
                'page_title' => 'Database Prefix Management',
                'audit_data' => $audit_data,
                'recent_operations' => $recent_operations,
                'csrf_token' => $this->generateCSRFToken()
            ];
            
            $this->render('admin/prefix_management', $data);
            
        } catch (\Exception $e) {
            error_log("Prefix Management Dashboard Error: " . $e->getMessage());
            http_response_code(500);
            $this->render('error', ['message' => 'Database audit failed: ' . $e->getMessage()]);
        }
    }
    
    /**
     * API: Get schema audit data
     */
    public function apiAudit(): void
    {
        header('Content-Type: application/json');
        
        try {
            $audit_data = $this->prefixManager->auditSchema();
            $this->jsonResponse(['success' => true, 'data' => $audit_data]);
            
        } catch (\Exception $e) {
            error_log("API Audit Error: " . $e->getMessage());
            http_response_code(500);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Rename table
     */
    public function apiRename(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        try {
            // Verify CSRF
            if (!$this->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $this->jsonResponse(['success' => false, 'error' => 'CSRF token validation failed']);
                return;
            }
            
            $current_table = trim($_POST['current_table'] ?? '');
            $new_table = trim($_POST['new_table'] ?? '');
            $dry_run = !empty($_POST['dry_run']);
            
            if (empty($current_table) || empty($new_table)) {
                $this->jsonResponse(['success' => false, 'error' => 'Current table and new table names are required']);
                return;
            }
            
            // Validate table names (basic security)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $current_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $new_table)) {
                $this->jsonResponse(['success' => false, 'error' => 'Invalid table names (alphanumeric and underscore only)']);
                return;
            }
            
            $result = $this->prefixManager->renameTable($current_table, $new_table, $dry_run);
            $this->jsonResponse(['success' => true, 'data' => $result]);
            
        } catch (\Exception $e) {
            error_log("API Rename Error: " . $e->getMessage());
            http_response_code(500);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Drop table
     */
    public function apiDrop(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        try {
            // Verify CSRF
            if (!$this->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $this->jsonResponse(['success' => false, 'error' => 'CSRF token validation failed']);
                return;
            }
            
            $table_name = trim($_POST['table_name'] ?? '');
            $dry_run = !empty($_POST['dry_run']);
            $confirm = !empty($_POST['confirm_drop']);
            
            if (empty($table_name)) {
                $this->jsonResponse(['success' => false, 'error' => 'Table name is required']);
                return;
            }
            
            if (!$dry_run && !$confirm) {
                $this->jsonResponse(['success' => false, 'error' => 'Drop confirmation required for non-dry-run operations']);
                return;
            }
            
            // Validate table name
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
                $this->jsonResponse(['success' => false, 'error' => 'Invalid table name (alphanumeric and underscore only)']);
                return;
            }
            
            $result = $this->prefixManager->dropTable($table_name, $dry_run);
            $this->jsonResponse(['success' => true, 'data' => $result]);
            
        } catch (\Exception $e) {
            error_log("API Drop Error: " . $e->getMessage());
            http_response_code(500);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Get operation history
     */
    public function apiHistory(): void
    {
        header('Content-Type: application/json');
        
        try {
            $limit = max(1, min(500, intval($_GET['limit'] ?? 50)));
            $history = $this->prefixManager->getOperationHistory($limit);
            
            $this->jsonResponse(['success' => true, 'data' => $history]);
            
        } catch (\Exception $e) {
            error_log("API History Error: " . $e->getMessage());
            http_response_code(500);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Utility Methods
     */
    
    private function render(string $view, array $data = []): void
    {
        extract($data);
        
        switch ($view) {
            case 'admin/prefix_management':
                include __DIR__ . '/../../Views/admin/prefix_management.php';
                break;
            case 'error':
                include __DIR__ . '/../../Views/error.php';
                break;
            default:
                http_response_code(404);
                echo "View not found: $view";
        }
    }
    
    private function jsonResponse(array $data): void
    {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    private function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    private function verifyCSRFToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Handle direct access
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $controller = new PrefixManagementController();
    
    $action = $_GET['action'] ?? 'dashboard';
    
    switch ($action) {
        case 'dashboard':
            $controller->dashboard();
            break;
        case 'apiAudit':
            $controller->apiAudit();
            break;
        case 'apiRename':
            $controller->apiRename();
            break;
        case 'previewRename':
            $controller->apiPreviewRename();
            break;
        case 'bulkRename':
            $controller->apiBulkRename();
            break;
        case 'apiDrop':
            $controller->apiDrop();
            break;
        case 'apiHistory':
            $controller->apiHistory();
            break;
        default:
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
}

?>
