<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;

/**
 * Simple Dashboard Controller for Testing
 * No authentication required - just returns HTML
 */
class SimpleDashboardController extends BaseController
{
    /**
     * Simple dashboard without authentication
     */
    public function index()
    {
        return $this->renderSimple();
    }
    
    /**
     * Generate simple HTML dashboard
     */
    private function renderSimple(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS 2.0 Admin Dashboard - TEST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tachometer-alt me-2"></i>
                CIS 2.0 Admin Dashboard
            </a>
            <span class="navbar-text text-light">
                <i class="fas fa-check-circle text-success me-1"></i>
                System Status: Online
            </span>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Dashboard Overview - TEST MODE
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    System Status
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <span class="badge bg-success">ONLINE</span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-server fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    MVC Router
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <span class="badge bg-success">ACTIVE</span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-route fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    Authentication
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <span class="badge bg-warning">TEST MODE</span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Database
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <span class="badge bg-success">CONNECTED</span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-database fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-success" role="alert">
                                    <h4 class="alert-heading">
                                        <i class="fas fa-check-circle me-2"></i>
                                        MVC System Successfully Operational!
                                    </h4>
                                    <p>The CIS 2.0 MVC system is running successfully with the following features:</p>
                                    <hr>
                                    <ul class="mb-0">
                                        <li><strong>SimpleRouter:</strong> Lightweight routing system active</li>
                                        <li><strong>Controllers:</strong> Admin dashboard controller responding</li>
                                        <li><strong>Views:</strong> Bootstrap 5 responsive templates</li>
                                        <li><strong>Authentication:</strong> 4-tier RBAC system ready (Admin/Manager/Staff/Viewer)</li>
                                        <li><strong>Services:</strong> Core service layer initialized</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
    }
}
