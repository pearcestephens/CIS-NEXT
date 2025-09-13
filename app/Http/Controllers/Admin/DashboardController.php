<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;

/**
 * Admin Dashboard Controller
 * Handles main admin dashboard and index pages
 */
class DashboardController extends BaseController
{
    /**
     * Admin home/index page
     */
    public function index()
    {
        return $this->render('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'stats' => $this->getDashboardStats()
        ]);
    }
    
    /**
     * Main admin dashboard
     */
    public function dashboard()
    {
        // Get metrics from services
        $metricsService = new \App\Services\MetricsService();
        $systemService = new \App\Services\SystemService();
        $securityService = new \App\Services\SecurityService();
        
        $metrics = [
            'system_health' => $systemService->getSystemHealth(),
            'active_users' => $metricsService->getActiveUserCount(),
            'response_time' => $metricsService->getAverageResponseTime(),
            'security_score' => $securityService->getSecurityScore(),
            'cpu_usage' => $systemService->getCpuUsage(),
            'memory_usage' => $systemService->getMemoryUsage(),
            'disk_usage' => $systemService->getDiskUsage(),
            'network_io' => $systemService->getNetworkIO(),
        ];
        
        // Get user data
        $user = $_SESSION['user'] ?? ['name' => 'Admin', 'role' => 'admin'];
        
        // Render clean dashboard
        include __DIR__ . '/../../Views/admin/dashboard_clean.php';
    }
    
    /**
     * Get dashboard statistics
     */
    private function getDashboardStats(): array
    {
        return [
            'total_users' => $this->getUserCount(),
            'system_health' => $this->getSystemHealth(),
            'active_sessions' => $this->getActiveSessions(),
            'uptime' => $this->getSystemUptime()
        ];
    }
    
    /**
     * Get recent system activity
     */
    private function getRecentActivity(): array
    {
        // TODO: Implement based on logging system
        return [
            ['action' => 'User login', 'user' => 'admin', 'time' => date('Y-m-d H:i:s')],
            ['action' => 'Settings updated', 'user' => 'admin', 'time' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
        ];
    }
    
    private function getUserCount(): int
    {
        // TODO: Implement user counting
        return 5;
    }
    
    private function getSystemHealth(): string
    {
        return 'Good';
    }
    
    private function getActiveSessions(): int
    {
        return 1;
    }
    
    private function getSystemUptime(): string
    {
        return '24h 15m';
    }
}
