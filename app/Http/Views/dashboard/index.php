<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Dashboard - CIS'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome - Latest Version with Fallback -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .sidebar {
            background: linear-gradient(180deg, #343a40 0%, #495057 100%);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin: 0.25rem 0.5rem;
            transition: all 0.2s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .stat-card .stat-icon {
            font-size: 2.5rem;
            opacity: 0.3;
        }

        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }
        .stat-card.info { border-left-color: var(--info-color); }

        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid #dee2e6;
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 0 4px 4px 0;
        }

        .activity-item.user_login { border-left-color: var(--success-color); }
        .activity-item.feed_event { border-left-color: var(--info-color); }
        .activity-item.error { border-left-color: var(--danger-color); }

        .health-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .health-indicator.healthy { background-color: var(--success-color); }
        .health-indicator.warning { background-color: var(--warning-color); }
        .health-indicator.critical { background-color: var(--danger-color); }

        .feed-event {
            background: white;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 3px solid #dee2e6;
            transition: all 0.2s ease;
        }

        .feed-event:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .feed-event.error { border-left-color: var(--danger-color); }
        .feed-event.warning { border-left-color: var(--warning-color); }
        .feed-event.alert { border-left-color: var(--info-color); }
        .feed-event.security { border-left-color: var(--danger-color); }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="p-3">
            <h5 class="text-white mb-0">
                <i class="fas fa-shield-alt"></i>
                CIS Dashboard
            </h5>
            <small class="text-muted">Central Information System</small>
        </div>
        
        <hr class="bg-secondary mx-3">
        
        <nav class="nav flex-column">
            <a class="nav-link active" href="/dashboard">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
            
            <?php if ($permissions['view_feed']): ?>
            <a class="nav-link" href="/feed">
                <i class="fas fa-stream"></i>
                Activity Feed
            </a>
            <?php endif; ?>
            
            <?php if ($permissions['view_users']): ?>
            <a class="nav-link" href="/users">
                <i class="fas fa-users"></i>
                User Management
            </a>
            <?php endif; ?>
            
            <?php if ($permissions['view_system']): ?>
            <a class="nav-link" href="/system">
                <i class="fas fa-cog"></i>
                System Settings
            </a>
            <?php endif; ?>
            
            <?php if ($permissions['view_reports']): ?>
            <a class="nav-link" href="/reports">
                <i class="fas fa-chart-bar"></i>
                Reports
            </a>
            <?php endif; ?>
            
            <div class="dropdown-divider mx-3 my-3"></div>
            
            <a class="nav-link" href="/profile">
                <i class="fas fa-user"></i>
                Profile
            </a>
            
            <a class="nav-link" href="/logout">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </nav>
        
        <div class="mt-auto p-3">
            <small class="text-muted d-block">
                Logged in as:<br>
                <strong><?php echo htmlspecialchars(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')); ?></strong><br>
                <em><?php echo htmlspecialchars($current_user['role'] ?? 'Role ID: ' . ($current_user['role_id'] ?? 'Unknown')); ?></em>
            </small>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Dashboard Overview</h2>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($current_user['first_name']); ?>!</p>
            </div>
            
            <div class="d-flex align-items-center">
                <span class="health-indicator <?php echo $system_health['status']; ?>"></span>
                <span class="mr-3">System Health: <?php echo ucfirst($system_health['status']); ?></span>
                <small class="text-muted"><?php echo date('M j, Y g:i A'); ?></small>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Active Users</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['users']['active']); ?></h3>
                            <small class="text-success">
                                <i class="fas fa-arrow-up"></i>
                                +<?php echo $stats['users']['new_30d']; ?> this month
                            </small>
                        </div>
                        <i class="fas fa-users stat-icon text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Active Sessions</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['sessions']['active']); ?></h3>
                            <small class="text-info">
                                <?php echo $stats['sessions']['today']; ?> today
                            </small>
                        </div>
                        <i class="fas fa-user-clock stat-icon text-info"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Feed Events</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['feed']['events_24h']); ?></h3>
                            <small class="text-warning">
                                <?php echo $stats['feed']['events_1h']; ?> in last hour
                            </small>
                        </div>
                        <i class="fas fa-stream stat-icon text-warning"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card <?php echo $stats['performance']['slow_requests'] > 10 ? 'danger' : 'success'; ?>">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Avg Response Time</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['performance']['avg_response_time']); ?>ms</h3>
                            <small class="<?php echo $stats['performance']['slow_requests'] > 10 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $stats['performance']['slow_requests']; ?> slow queries
                            </small>
                        </div>
                        <i class="fas fa-tachometer-alt stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="row">
            <!-- Activity Feed -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-stream"></i>
                            Recent Activity Feed
                        </h5>
                        <?php if ($permissions['view_feed']): ?>
                        <a href="/feed" class="btn btn-sm btn-outline-primary">View All</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($feed_data['success'] && !empty($feed_data['data']['events'])): ?>
                            <?php foreach (array_slice($feed_data['data']['events'], 0, 5) as $event): ?>
                            <div class="feed-event <?php echo htmlspecialchars($event['event_type']); ?>">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($event['title']); ?></h6>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($event['created_at'])); ?></small>
                                </div>
                                <p class="mb-1 text-muted small"><?php echo htmlspecialchars($event['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-cube"></i>
                                        <?php echo htmlspecialchars($event['source_module']); ?>
                                    </small>
                                    <span class="badge badge-<?php echo $event['event_type'] === 'error' ? 'danger' : ($event['event_type'] === 'warning' ? 'warning' : 'info'); ?> badge-sm">
                                        <?php echo ucfirst($event['event_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-3"></i>
                                <p>No recent feed events</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- System Activity -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i>
                            Recent System Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activity)): ?>
                            <?php foreach (array_slice($recent_activity, 0, 8) as $activity): ?>
                            <div class="activity-item <?php echo htmlspecialchars($activity['activity_type']); ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['actor']); ?></strong>
                                        <p class="mb-0 text-muted small"><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <?php if (!empty($activity['metadata'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($activity['metadata']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('g:i A', strtotime($activity['activity_time'])); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-clock fa-2x mb-3"></i>
                                <p>No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health Details -->
        <?php if (!empty($system_health['issues'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?php echo $system_health['status'] === 'critical' ? 'danger' : 'warning'; ?>" role="alert">
                    <h6 class="alert-heading">
                        <i class="fas fa-exclamation-triangle"></i>
                        System Health Issues Detected
                    </h6>
                    <ul class="mb-0">
                        <?php foreach ($system_health['issues'] as $issue): ?>
                        <li><?php echo htmlspecialchars($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($permissions['view_users']): ?>
                            <div class="col-md-3 mb-2">
                                <a href="/users/create" class="btn btn-outline-primary btn-block">
                                    <i class="fas fa-user-plus"></i>
                                    Add User
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($permissions['view_reports']): ?>
                            <div class="col-md-3 mb-2">
                                <a href="/reports" class="btn btn-outline-info btn-block">
                                    <i class="fas fa-chart-line"></i>
                                    Generate Report
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($permissions['view_system']): ?>
                            <div class="col-md-3 mb-2">
                                <a href="/system/logs" class="btn btn-outline-warning btn-block">
                                    <i class="fas fa-file-alt"></i>
                                    View Logs
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3 mb-2">
                                <a href="/health" class="btn btn-outline-success btn-block">
                                    <i class="fas fa-heartbeat"></i>
                                    System Health
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh dashboard every 30 seconds
        let refreshInterval;
        
        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                // Only refresh if the page is visible and user hasn't interacted recently
                if (!document.hidden) {
                    window.location.reload();
                }
            }, 30000);
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }
        
        // Start auto-refresh on load
        $(document).ready(() => {
            startAutoRefresh();
            
            // Stop auto-refresh when user interacts with page
            $(document).on('click keypress', () => {
                stopAutoRefresh();
                // Restart after 2 minutes of inactivity
                setTimeout(startAutoRefresh, 120000);
            });
        });
        
        // Mobile sidebar toggle
        function toggleSidebar() {
            $('.sidebar').toggleClass('show');
        }
        
        // Add mobile toggle button for small screens
        if (window.innerWidth <= 768) {
            $('.main-content').prepend(`
                <button class="btn btn-outline-secondary mb-3 d-md-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                    Menu
                </button>
            `);
        }
    </script>
</body>
</html>
