<?php
declare(strict_types=1);

/**
 * Dashboard Model
 * File: app/Models/DashboardModel.php
 * Purpose: System monitoring data access using AdminDAL
 */

namespace App\Models;

use RuntimeException;

class DashboardModel
{
    private AdminDAL $dal;

    public function __construct()
    {
        $this->dal = new AdminDAL();
    }

    /**
     * Get system overview metrics
     */
    public function getSystemMetrics(): array
    {
        try {
            // Get user counts
            $user_stats = $this->dal->query("
                SELECT 
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN {$this->dal->col('active')} = 1 THEN 1 END) as active_users
                FROM {$this->dal->table('users')} 
                WHERE {$this->dal->col('deleted_at')} IS NULL
            ");

            // Get recent audit activity
            $recent_activity = $this->dal->query("
                SELECT COUNT(*) as activity_count 
                FROM {$this->dal->table('audit_logs')} 
                WHERE {$this->dal->col('created_at')} >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");

            // Get system health indicators
            $error_count = $this->dal->query("
                SELECT COUNT(*) as error_count 
                FROM {$this->dal->table('error_logs')} 
                WHERE {$this->dal->col('created_at')} >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");

            // Get job queue status
            $job_stats = $this->dal->query("
                SELECT 
                    COUNT(*) as total_jobs,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_jobs,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_jobs
                FROM {$this->dal->table('job_queue')} 
                WHERE {$this->dal->col('created_at')} >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");

            return [
                'users' => $user_stats[0] ?? ['total_users' => 0, 'active_users' => 0],
                'activity' => $recent_activity[0] ?? ['activity_count' => 0],
                'errors' => $error_count[0] ?? ['error_count' => 0],
                'jobs' => $job_stats[0] ?? ['total_jobs' => 0, 'pending_jobs' => 0, 'failed_jobs' => 0]
            ];

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to fetch system metrics: " . $e->getMessage());
        }
    }

    /**
     * Get performance metrics for charts
     */
    public function getPerformanceMetrics(int $hours = 24): array
    {
        try {
            $metrics = $this->dal->query("
                SELECT 
                    DATE_FORMAT({$this->dal->col('created_at')}, '%H:00') as hour_label,
                    AVG(response_time) as avg_response_time,
                    AVG(cpu_usage) as avg_cpu_usage,
                    AVG(memory_usage) as avg_memory_usage
                FROM {$this->dal->table('performance_logs')} 
                WHERE {$this->dal->col('created_at')} >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY DATE_FORMAT({$this->dal->col('created_at')}, '%Y-%m-%d %H')
                ORDER BY hour_label
            ", [$hours], 'i');

            return $metrics;

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to fetch performance metrics: " . $e->getMessage());
        }
    }

    /**
     * Get recent system alerts
     */
    public function getSystemAlerts(int $limit = 10): array
    {
        try {
            return $this->dal->query("
                SELECT 
                    id,
                    level,
                    message,
                    source,
                    {$this->dal->col('created_at')} as timestamp
                FROM {$this->dal->table('error_logs')} 
                WHERE level IN ('warning', 'error', 'critical')
                ORDER BY {$this->dal->col('created_at')} DESC 
                LIMIT ?
            ", [$limit], 'i');

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to fetch system alerts: " . $e->getMessage());
        }
    }

    /**
     * Log system activity for audit trail
     */
    public function logActivity(string $action, array $details = []): void
    {
        try {
            $this->dal->exec("
                INSERT INTO {$this->dal->table('audit_logs')} 
                (user_id, action, details, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [
                $_SESSION['user_id'] ?? null,
                $action,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ], 'issss');

        } catch (RuntimeException $e) {
            // Don't throw on audit logging failures, just error_log
            error_log("Failed to log audit activity: " . $e->getMessage());
        }
    }
}
?>
