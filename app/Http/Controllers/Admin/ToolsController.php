<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;

/**
 * Admin Tools Controller
 * Handles admin tools and utilities
 */
class ToolsController extends BaseController
{
    public function index()
    {
        return $this->render('admin/tools', [
            'title' => 'Admin Tools',
            'tools' => $this->getAvailableTools()
        ]);
    }
    
    private function getAvailableTools(): array
    {
        return [
            ['name' => 'Database Prefix Manager', 'url' => '/admin/database/prefix-manager', 'icon' => 'database'],
            ['name' => 'System Health Check', 'url' => '/admin/tools/health', 'icon' => 'heartbeat'],
            ['name' => 'Cache Management', 'url' => '/admin/tools/cache', 'icon' => 'memory'],
            ['name' => 'Migration Runner', 'url' => '/admin/tools/migrations', 'icon' => 'cogs']
        ];
    }
}
