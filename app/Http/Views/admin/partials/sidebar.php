<?php
/**
 * Admin Sidebar Partial
 * File: app/Http/Views/admin/partials/sidebar.php
 * Purpose: Left sidebar navigation for admin panel - Modernized Bootstrap 5
 */

// Helper function to check active nav
function isActive($path, $exact = false) {
    $current = $_SERVER['REQUEST_URI'] ?? '';
    return $exact ? $current === $path : strpos($current, $path) === 0;
}
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-content">
        
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <button class="btn btn-sm btn-outline-light sidebar-collapse-toggle" type="button" aria-label="Collapse sidebar">
                <i class="fas fa-angle-left"></i>
            </button>
        </div>
        
        <!-- Main Navigation -->
        <nav class="sidebar-nav" role="navigation">
            <ul class="nav flex-column">
                
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/dashboard', true) ? 'active' : '' ?>" 
                       href="/admin/dashboard">
                        <i class="fas fa-tachometer-alt nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <!-- System Operations -->
                <li class="nav-separator">
                    <span class="separator-text">System Operations</span>
                </li>
                
                <!-- Backups -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/backups') ? 'active' : '' ?>" 
                       href="/admin/backups">
                        <i class="fas fa-database nav-icon"></i>
                        <span class="nav-text">Backups</span>
                    </a>
                </li>
                
                <!-- Migrations & Seeds -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/migrations') ? 'active' : '' ?>" 
                       href="/admin/migrations">
                        <i class="fas fa-code-branch nav-icon"></i>
                        <span class="nav-text">Migrations</span>
                    </a>
                </li>
                
                <!-- Cache & Redis -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/cache') ? 'active' : '' ?>" 
                       href="/admin/cache">
                        <i class="fas fa-memory nav-icon"></i>
                        <span class="nav-text">Cache</span>
                    </a>
                </li>
                
                <!-- Queue & Jobs -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/queue') ? 'active' : '' ?>" 
                       href="/admin/queue">
                        <i class="fas fa-tasks nav-icon"></i>
                        <span class="nav-text">Queue</span>
                    </a>
                </li>
                
                <!-- Cron & Schedules -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/cron') ? 'active' : '' ?>" 
                       href="/admin/cron">
                        <i class="fas fa-clock nav-icon"></i>
                        <span class="nav-text">Cron</span>
                    </a>
                </li>
            
                
                <!-- Security & Monitoring -->
                <li class="nav-separator">
                    <span class="separator-text">Security & Monitoring</span>
                </li>
                
                <!-- Security -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/security') ? 'active' : '' ?>" 
                       href="/admin/security">
                        <i class="fas fa-shield-alt nav-icon"></i>
                        <span class="nav-text">Security</span>
                    </a>
                </li>
                
                <!-- Logs -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/logs') ? 'active' : '' ?>" 
                       href="/admin/logs">
                        <i class="fas fa-file-alt nav-icon"></i>
                        <span class="nav-text">Logs</span>
                    </a>
                </li>
                
                <!-- Analytics -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/analytics') ? 'active' : '' ?>" 
                       href="/admin/analytics">
                        <i class="fas fa-chart-line nav-icon"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </li>
                
                <!-- Configuration -->
                <li class="nav-separator">
                    <span class="separator-text">Configuration</span>
                </li>
                
                <!-- Settings -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/settings') ? 'active' : '' ?>" 
                       href="/admin/settings">
                        <i class="fas fa-cog nav-icon"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>
                
                <!-- Integrations -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/integrations') ? 'active' : '' ?>" 
                       href="/admin/integrations">
                        <i class="fas fa-plug nav-icon"></i>
                        <span class="nav-text">Integrations</span>
                    </a>
                </li>
                
                <!-- Users -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/users') ? 'active' : '' ?>" 
                       href="/admin/users">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                
                <?php if ($is_super_admin): ?>
                <!-- Super Admin -->
                <li class="nav-separator">
                    <span class="separator-text">System Admin</span>
                </li>
                
                <!-- System Modules -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/system/modules') ? 'active' : '' ?>" 
                       href="/admin/system/modules">
                        <i class="fas fa-cubes nav-icon"></i>
                        <span class="nav-text">Modules</span>
                    </a>
                </li>
                
                <!-- Database -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin/database') ? 'active' : '' ?>" 
                       href="/admin/database">
                        <i class="fas fa-table nav-icon"></i>
                        <span class="nav-text">Database</span>
                    </a>
                </li>
                <?php endif; ?>
                
            </ul>
        </nav>
        
    </div>
</aside>
