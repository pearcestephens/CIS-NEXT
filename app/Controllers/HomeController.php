<?php
/**
 * Home Controller
 * 
 * Main home controller for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    /**
     * Show home page
     */
    public function index(): void
    {
        $data = [
            'title' => 'Welcome to CIS MVC Platform',
            'message' => 'Your MVC platform is running successfully!',
            'version' => config('app.version', '2.0.0'),
            'environment' => config('app.env', 'production'),
            'features' => [
                'MVC Architecture',
                'Security Layer with CSRF Protection',
                'Database ORM',
                'Routing System',
                'View Templates',
                'Session Management',
                'Rate Limiting',
                'Security Headers',
                'Query Logging',
                'Error Handling',
            ],
            'stats' => $this->getSystemStats(),
        ];
        
        $this->view('home.index', $data);
    }

    /**
     * Show dashboard (authenticated users)
     */
    public function dashboard(): void
    {
        // Check if user is authenticated
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
            return;
        }
        
        $data = [
            'title' => 'Dashboard',
            'user' => $_SESSION['user_name'] ?? 'User',
            'metrics' => $this->getDashboardMetrics(),
            'recent_activity' => $this->getRecentActivity(),
        ];
        
        $this->view('home.dashboard', $data);
    }

    /**
     * Get system statistics
     */
    private function getSystemStats(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s T'),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'uptime' => $this->getServerUptime(),
        ];
    }

    /**
     * Get dashboard metrics (for authenticated users)
     */
    private function getDashboardMetrics(): array
    {
        return [
            'total_users' => 0, // Would query from database
            'active_sessions' => 1,
            'system_health' => 'Good',
            'last_backup' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get recent activity (for authenticated users)
     */
    private function getRecentActivity(): array
    {
        return [
            [
                'action' => 'User login',
                'user' => $_SESSION['user_name'] ?? 'User',
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $this->security->getClientIp(),
            ],
            [
                'action' => 'Dashboard access',
                'user' => $_SESSION['user_name'] ?? 'User', 
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $this->security->getClientIp(),
            ],
        ];
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get server uptime (approximation)
     */
    private function getServerUptime(): string
    {
        if (function_exists('sys_getloadavg')) {
            $uptime = shell_exec('uptime');
            if ($uptime) {
                return trim($uptime);
            }
        }
        
        return 'N/A';
    }
}
