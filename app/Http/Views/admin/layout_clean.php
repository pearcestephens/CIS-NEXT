<?php
/**
 * Clean Admin Layout - Compact Top + Minimal Sidebar
 * 
 * Modern, lean design with all features accessible but not overwhelming
 * Author: GitHub Copilot  
 * Created: 2025-09-13
 */

// Security check
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'] ?? [];
$user_role = $user['role'] ?? 'general';
$is_super_admin = $user_role === 'super_admin';
$csp_nonce = $csp_nonce ?? ($GLOBALS['csp_nonce'] ?? uniqid('nonce_'));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'CIS Admin') ?> - Ecigdis CIS</title>
    
    <!-- Security Headers -->
    <meta name="csrf-token" content="<?= $csp_nonce ?>">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Admin Styles -->
    <style nonce="<?= $csp_nonce ?>">
        :root {
            --sidebar-width: 60px;
            --sidebar-width-expanded: 240px;
            --topbar-height: 60px;
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --border-color: #dee2e6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f6fa;
            margin: 0;
            padding: 0;
        }

        /* Top Bar */
        .top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--topbar-height);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            padding: 0 20px;
        }

        .top-bar .brand {
            font-size: 1.2rem;
            font-weight: 600;
            margin-right: 30px;
        }

        .top-bar .search-box {
            flex: 1;
            max-width: 400px;
            margin-right: 20px;
        }

        .top-bar .search-box input {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            border-radius: 25px;
            padding: 8px 20px;
            width: 100%;
        }

        .top-bar .search-box input::placeholder {
            color: rgba(255,255,255,0.7);
        }

        .top-bar .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-bar .notification-badge {
            position: relative;
            cursor: pointer;
        }

        .top-bar .notification-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--topbar-height));
            background: white;
            border-right: 1px solid var(--border-color);
            z-index: 999;
            transition: width 0.3s ease;
            overflow: hidden;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar:hover {
            width: var(--sidebar-width-expanded);
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .sidebar-menu a:hover {
            background: #f8f9fa;
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .sidebar-menu a.active {
            background: var(--primary-color);
            color: white;
        }

        .sidebar-menu i {
            width: 20px;
            margin-right: 15px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sidebar-menu span {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar:hover .sidebar-menu span {
            opacity: 1;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            padding: 30px;
            min-height: calc(100vh - var(--topbar-height));
            transition: margin-left 0.3s ease;
        }

        /* Cards */
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .metric-card .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
        }

        .metric-card .card-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .metric-card .card-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 10px;
        }

        .metric-card .card-change {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .metric-card .card-change.positive {
            color: var(--success-color);
        }

        .metric-card .card-change.negative {
            color: var(--danger-color);
        }

        /* Status Indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-healthy {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success-color);
        }

        .status-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-critical {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        /* Progress Bars */
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }

        .progress-bar-custom .progress {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Quick Actions */
        .quick-action {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            display: block;
        }

        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            color: var(--primary-color);
            text-decoration: none;
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
            
            .top-bar .search-box {
                display: none;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <nav class="top-bar">
        <div class="brand">
            <i class="bi bi-shield-check"></i>
            CIS Admin
        </div>
        
        <div class="search-box">
            <input type="text" placeholder="Search admin tools..." id="admin-search">
        </div>
        
        <div class="user-menu">
            <div class="notification-badge">
                <i class="bi bi-bell" style="font-size: 1.2rem;"></i>
                <span class="badge">3</span>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-link text-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle" style="font-size: 1.5rem;"></i>
                    <?= htmlspecialchars($user['name'] ?? 'Admin') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="/settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="/admin/dashboard" class="active">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a></li>
            
            <li><a href="/admin/users">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a></li>
            
            <li><a href="/admin/security">
                <i class="bi bi-shield-lock"></i>
                <span>Security</span>
            </a></li>
            
            <li><a href="/admin/performance">
                <i class="bi bi-graph-up"></i>
                <span>Performance</span>
            </a></li>
            
            <li><a href="/admin/database">
                <i class="bi bi-database"></i>
                <span>Database</span>
            </a></li>
            
            <li><a href="/admin/backups">
                <i class="bi bi-archive"></i>
                <span>Backups</span>
            </a></li>
            
            <li><a href="/admin/logs">
                <i class="bi bi-journal-text"></i>
                <span>Logs</span>
            </a></li>
            
            <li><a href="/admin/integrations">
                <i class="bi bi-plug"></i>
                <span>Integrations</span>
            </a></li>
            
            <li><a href="/admin/settings">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a></li>
            
            <li><a href="/admin/tools">
                <i class="bi bi-tools"></i>
                <span>Tools</span>
            </a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <?= $content ?? '' ?>
    </main>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous" nonce="<?= $csp_nonce ?>"></script>
    
    <!-- Admin JavaScript -->
    <script nonce="<?= $csp_nonce ?>">
        // Admin search functionality
        document.getElementById('admin-search').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            const menuItems = document.querySelectorAll('.sidebar-menu a');
            
            menuItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                const li = item.parentElement;
                
                if (text.includes(query) || query === '') {
                    li.style.display = 'block';
                } else {
                    li.style.display = 'none';
                }
            });
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Auto-refresh dashboard data every 30 seconds
        setInterval(function() {
            if (window.location.pathname.includes('dashboard')) {
                // Refresh metric cards without full page reload
                refreshMetrics();
            }
        }, 30000);

        function refreshMetrics() {
            fetch('/api/admin/metrics', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateMetricCards(data.data);
                }
            })
            .catch(error => console.error('Error refreshing metrics:', error));
        }

        function updateMetricCards(metrics) {
            // Update metric values without disrupting the UI
            for (const [key, value] of Object.entries(metrics)) {
                const element = document.querySelector(`[data-metric="${key}"]`);
                if (element) {
                    element.textContent = value;
                }
            }
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
