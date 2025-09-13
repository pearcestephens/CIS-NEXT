<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Shared\Logging\Audit;
use App\Shared\Logging\Logger;

/**
 * Live Logs Controller
 * 
 * Real-time log viewing and management
 */
class LogsController extends BaseController
{
    private Audit $audit;
    private Logger $logger;

    public function __construct()
    {
        parent::__construct();
        $this->audit = Audit::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * Display logs dashboard
     */
    public function index(): void
    {
        $this->requireAuth();
        
        // Get recent logs
        $recentLogs = $this->getRecentLogs(50);
        $auditCount = $this->audit->getAuditCount();
        
        // Get log file sizes
        $logStats = $this->getLogStatistics();
        
        $this->render('admin/logs/index', [
            'recent_logs' => $recentLogs,
            'audit_count' => $auditCount,
            'log_stats' => $logStats,
            'page_title' => 'Live Logs Dashboard'
        ]);
    }

    /**
     * Get audit logs via AJAX
     */
    public function getAuditLogs(): void
    {
        $this->requireAuth();
        
        $table = $_GET['table'] ?? null;
        $user = $_GET['user_id'] ?? null;
        $action = $_GET['action'] ?? null;
        $page = (int) ($_GET['page'] ?? 1);
        $limit = min((int) ($_GET['limit'] ?? 25), 100);
        
        $offset = ($page - 1) * $limit;
        
        $records = $this->audit->getAuditRecords(
            $table, 
            $user ? (int) $user : null, 
            $action, 
            $limit, 
            $offset
        );
        
        $total = $this->audit->getAuditCount($table, $user ? (int) $user : null);
        
        $this->jsonResponse([
            'records' => $records,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Stream live logs via Server-Sent Events
     */
    public function streamLogs(): void
    {
        $this->requireAuth();
        
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        // Get last log ID from client
        $lastId = (int) ($_GET['last_id'] ?? 0);
        
        // Continuously send new log entries
        while (true) {
            $newLogs = $this->getNewLogs($lastId);
            
            if (!empty($newLogs)) {
                foreach ($newLogs as $log) {
                    $data = json_encode($log);
                    echo "id: {$log['id']}\n";
                    echo "data: {$data}\n\n";
                    $lastId = max($lastId, $log['id']);
                }
                
                ob_flush();
                flush();
            }
            
            // Check every 2 seconds
            sleep(2);
            
            // Stop if client disconnected
            if (connection_aborted()) {
                break;
            }
        }
    }

    /**
     * Download logs as file
     */
    public function downloadLogs(): void
    {
        $this->requireAuth();
        
        $type = $_GET['type'] ?? 'audit';
        $format = $_GET['format'] ?? 'json';
        
        if ($type === 'audit') {
            $this->downloadAuditLogs($format);
        } else {
            $this->downloadSystemLogs($format);
        }
    }

    /**
     * Clear old logs
     */
    public function clearLogs(): void
    {
        $this->requireAuth();
        $this->requireCsrf();
        
        $type = $_POST['type'] ?? 'audit';
        $days = (int) ($_POST['days'] ?? 30);
        
        if ($type === 'audit') {
            $deleted = $this->audit->cleanupOldRecords($days);
            $message = "Deleted {$deleted} audit records older than {$days} days";
        } else {
            $deleted = $this->clearSystemLogs($days);
            $message = "Cleared system logs older than {$days} days";
        }
        
        $this->logger->info('Logs cleared by admin', [
            'type' => $type,
            'days' => $days,
            'deleted_count' => $deleted,
            'admin_id' => $_SESSION['user_id'] ?? null
        ]);
        
        $this->jsonResponse([
            'success' => true,
            'message' => $message,
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Get recent log entries
     */
    private function getRecentLogs(int $limit = 50): array
    {
        return $this->audit->getAuditRecords(null, null, null, $limit, 0);
    }

    /**
     * Get new logs since last ID
     */
    private function getNewLogs(int $lastId): array
    {
        // Get new audit logs
        $sql = "SELECT id, user_id, action, table_name, record_id, created_at, ip_address
                FROM cis_audit_log 
                WHERE id > ? 
                ORDER BY id ASC 
                LIMIT 50";
        
        $stmt = $this->database->executeQuery($sql, [$lastId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get log file statistics
     */
    private function getLogStatistics(): array
    {
        $logDir = dirname(__DIR__, 3) . '/var/logs';
        $stats = [];
        
        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            
            foreach ($files as $file) {
                $stats[basename($file)] = [
                    'size' => filesize($file),
                    'size_human' => $this->formatBytes(filesize($file)),
                    'modified' => filemtime($file),
                    'lines' => $this->countFileLines($file)
                ];
            }
        }
        
        return $stats;
    }

    /**
     * Download audit logs
     */
    private function downloadAuditLogs(string $format): void
    {
        $logs = $this->audit->getAuditRecords(null, null, null, 10000, 0);
        $filename = 'audit_logs_' . date('Y-m-d_H-i-s');
        
        if ($format === 'csv') {
            $this->downloadAsCsv($logs, $filename);
        } else {
            $this->downloadAsJson($logs, $filename);
        }
    }

    /**
     * Download system logs
     */
    private function downloadSystemLogs(string $format): void
    {
        $logDir = dirname(__DIR__, 3) . '/var/logs';
        $logFile = $logDir . '/app.log';
        
        if (!file_exists($logFile)) {
            $this->jsonResponse(['error' => 'Log file not found'], 404);
            return;
        }
        
        $filename = 'system_logs_' . date('Y-m-d_H-i-s') . '.log';
        
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Content-Length: ' . filesize($logFile));
        
        readfile($logFile);
        exit;
    }

    /**
     * Clear system logs
     */
    private function clearSystemLogs(int $days): int
    {
        $logDir = dirname(__DIR__, 3) . '/var/logs';
        $cutoff = time() - ($days * 24 * 3600);
        $cleared = 0;
        
        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    if (unlink($file)) {
                        $cleared++;
                    }
                }
            }
        }
        
        return $cleared;
    }

    /**
     * Download data as CSV
     */
    private function downloadAsCsv(array $data, string $filename): void
    {
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
        
        $output = fopen('php://output', 'w');
        
        // Write header
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Download data as JSON
     */
    private function downloadAsJson(array $data, string $filename): void
    {
        header('Content-Type: application/json');
        header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Count lines in file
     */
    private function countFileLines(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $count++;
            }
            fclose($handle);
        }
        
        return $count;
    }

    /**
     * Format bytes to human readable
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
