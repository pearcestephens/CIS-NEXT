<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <title><?= htmlspecialchars($title ?? $app_name ?? 'CIS MVC Platform') ?></title>
    
    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --dark-color: #34495e;
            --light-color: #ecf0f1;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: 600;
            color: var(--primary-color) !important;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 80px 0;
            margin-bottom: 40px;
        }
        
        .feature-card {
            transition: transform 0.2s ease-in-out;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .version-badge {
            background: var(--success-color);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .footer {
            background-color: var(--dark-color);
            color: white;
            padding: 40px 0;
            margin-top: 60px;
        }
        
        .system-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .status-healthy {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-error {
            background-color: var(--danger-color);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="/">
                <strong><?= htmlspecialchars($app_name ?? 'CIS MVC Platform') ?></strong>
                <span class="version-badge ms-2"><?= htmlspecialchars($app_version ?? '2.0.0') ?></span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/health">Health</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/profile">Profile</a></li>
                                <li><a class="dropdown-item" href="/settings">Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="/logout" style="display: inline;">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                        <button type="submit" class="dropdown-item">Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php if (!empty($flash = $_SESSION['flash'] ?? [])): ?>
        <div class="container mt-3">
            <?php foreach ($flash as $type => $message): ?>
                <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Main Content -->
    <main>
        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?= htmlspecialchars($app_name ?? 'CIS MVC Platform') ?></h5>
                    <p class="mb-0">Enterprise-grade MVC platform with comprehensive security, performance monitoring, and scalability features.</p>
                </div>
                <div class="col-md-3">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="/health" class="text-light">System Health</a></li>
                        <li><a href="/metrics" class="text-light">Metrics</a></li>
                        <li><a href="/docs" class="text-light">Documentation</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>System Info</h6>
                    <ul class="list-unstyled">
                        <li>Version: <?= htmlspecialchars($app_version ?? '2.0.0') ?></li>
                        <li>Environment: <?= htmlspecialchars($environment ?? 'production') ?></li>
                        <li>PHP: <?= PHP_VERSION ?></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?= date('Y') ?> Ecigdis Limited. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <small>Request ID: <?= uniqid('req_') ?></small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // CSRF token for AJAX requests
        window.csrfToken = '<?= htmlspecialchars($csrf_token ?? '') ?>';
        
        // Configure AJAX defaults
        if (typeof XMLHttpRequest !== 'undefined') {
            const originalSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(body) {
                if (this.method !== 'GET' && window.csrfToken) {
                    if (body instanceof FormData) {
                        body.append('_token', window.csrfToken);
                    } else if (typeof body === 'string' && body.includes('=')) {
                        body += (body ? '&' : '') + '_token=' + encodeURIComponent(window.csrfToken);
                    }
                }
                return originalSend.call(this, body);
            };
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log('Page load time:', Math.round(loadTime), 'ms');
            
            // Send performance metrics (if analytics enabled)
            if (window.navigator.sendBeacon && loadTime > 1000) {
                const data = JSON.stringify({
                    type: 'performance',
                    load_time: Math.round(loadTime),
                    url: window.location.pathname,
                    timestamp: new Date().toISOString()
                });
                
                navigator.sendBeacon('/api/v1/metrics/performance', data);
            }
        });
    </script>
</body>
</html>
