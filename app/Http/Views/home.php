<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'CIS'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome - Latest Version with Fallback -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        :root {
            --vs-primary: #007bff;
            --vs-secondary: #6c757d;
            --vs-success: #28a745;
            --vs-warning: #ffc107;
            --vs-danger: #dc3545;
            --vs-info: #17a2b8;
            --vs-light: #f8f9fa;
            --vs-dark: #343a40;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--vs-primary) 0%, var(--vs-info) 100%);
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        
        .feature-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .btn-cis {
            background: linear-gradient(45deg, var(--vs-primary), var(--vs-info));
            border: none;
            color: white;
        }
        
        .btn-cis:hover {
            background: linear-gradient(45deg, #0056b3, #138496);
            color: white;
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <h1 class="display-4 mb-4"><?php echo $title; ?></h1>
            <p class="lead mb-4"><?php echo $subtitle ?? 'Modern ERP and Analytics Platform'; ?></p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card bg-white text-dark">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-sign-in-alt"></i> Staff Login</h5>
                            <p class="card-text">Access the Central Information System</p>
                            <a href="/login" class="btn btn-cis btn-lg btn-block">
                                <i class="fas fa-arrow-right"></i> Login to CIS
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Real-time Analytics</h5>
                        <p class="card-text">Monitor sales, inventory, and KPIs across all 17 locations with live dashboards and AI-powered insights.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Staff Portal</h5>
                        <p class="card-text">Unified workspace for team collaboration, training modules, and personalized dashboards for every role.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="card feature-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-robot fa-3x text-info mb-3"></i>
                        <h5 class="card-title">AI Intelligence</h5>
                        <p class="card-text">Smart clustering, anomaly detection, and automated insights to help you make better business decisions.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-12 text-center">
                <h3 class="mb-4">Integrated Systems</h3>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="bg-light p-3 rounded">
                            <i class="fas fa-cash-register fa-2x text-muted mb-2"></i>
                            <div><strong>Vend POS</strong></div>
                            <small class="text-muted">Live inventory & sales</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="bg-light p-3 rounded">
                            <i class="fas fa-calculator fa-2x text-muted mb-2"></i>
                            <div><strong>Xero</strong></div>
                            <small class="text-muted">Accounting integration</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="bg-light p-3 rounded">
                            <i class="fas fa-calendar fa-2x text-muted mb-2"></i>
                            <div><strong>Deputy</strong></div>
                            <small class="text-muted">Staff scheduling</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="bg-light p-3 rounded">
                            <i class="fas fa-eye fa-2x text-muted mb-2"></i>
                            <div><strong>CISWatch</strong></div>
                            <small class="text-muted">AI security monitoring</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6>Ecigdis Limited</h6>
                    <p class="mb-0">Trading as The Vape Shed</p>
                    <small class="text-muted">Est. 2015 • 17 Locations • New Zealand</small>
                </div>
                <div class="col-md-6 text-md-right">
                    <p class="mb-0">CIS v1.0.0</p>
                    <small class="text-muted">Central Information System</small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
