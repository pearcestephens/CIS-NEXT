<?php
/**
 * Admin Header Partial
 * File: app/Http/Views/admin/partials/header.php
 * Purpose: Top navigation bar for admin panel
 */
?>
<header class="admin-header navbar navbar-expand-lg navbar-dark shadow-sm">
    <div class="container-fluid">
        
        <!-- Sidebar Toggle (Mobile/Desktop) -->
        <button class="btn btn-link text-light p-0 me-3 sidebar-toggle" type="button" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand / Logo -->
        <a class="navbar-brand d-flex align-items-center" href="/admin/dashboard">
            <i class="fas fa-cogs me-2"></i>
            <span class="brand-text">CIS Admin</span>
            
            <!-- Environment Badge -->
            <?php 
            $env = $_ENV['APP_ENV'] ?? 'production';
            if ($env !== 'production'): 
            ?>
            <span class="badge bg-warning text-dark ms-2 env-badge"><?= strtoupper($env) ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Mobile Menu Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" 
                aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation Links -->
        <div class="collapse navbar-collapse" id="adminNavbar">
            
            <!-- Left Navigation -->
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/admin/dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/analytics">
                        <i class="fas fa-chart-line"></i> Analytics
                    </a>
                </li>
                <?php if ($is_super_admin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-database"></i> Database
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/admin/database/prefix-manager">
                            <i class="fas fa-table"></i> Prefix Manager
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/migrations">
                            <i class="fas fa-code-branch"></i> Migrations
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/backups">
                            <i class="fas fa-database"></i> Backups
                        </a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-tools"></i> Tools
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/admin/tools/automation">
                            <i class="fas fa-robot"></i> Automation
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/seeds">
                            <i class="fas fa-seedling"></i> Seeds
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/cache">
                            <i class="fas fa-memory"></i> Cache
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/queue">
                            <i class="fas fa-tasks"></i> Queue
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/monitor">
                        <i class="fas fa-heartbeat"></i> Monitor
                    </a>
                </li>
            </ul>
            
            <!-- Right Navigation -->
            <ul class="navbar-nav ms-auto">
                
                <!-- Theme Toggle -->
                <li class="nav-item">
                    <button class="btn btn-link nav-link theme-toggle" type="button" title="Toggle theme" aria-label="Toggle theme">
                        <i class="fas fa-sun" data-theme="light"></i>
                        <i class="fas fa-moon" data-theme="dark" style="display: none;"></i>
                    </button>
                </li>
                
                <!-- System Health Indicator -->
                <li class="nav-item">
                    <span class="navbar-text">
                        <span class="health-indicator" title="System Status">
                            <i class="fas fa-circle text-success"></i>
                        </span>
                    </span>
                </li>
                
                <!-- User Info Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i>
                        <span class="user-name"><?= htmlspecialchars($user['name'] ?? $user['username'] ?? 'Admin') ?></span>
                        <?php if ($is_super_admin): ?>
                        <span class="badge bg-danger ms-2">SUPER</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <strong><?= htmlspecialchars($user['name'] ?? $user['username'] ?? 'Admin') ?></strong>
                            <br>
                            <small class="text-muted"><?= htmlspecialchars(ucfirst($user_role)) ?> Access</small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/admin/profile">
                            <i class="fas fa-user"></i> Profile
                        </a></li>
                        <li><a class="dropdown-item" href="/admin/settings">
                            <i class="fas fa-cog"></i> Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="/logout" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
                
            </ul>
            
        </div>
        
    </div>
</header>
