<?php
/**
 * Home Index View
 * 
 * Main home page template for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */
?>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="jumbotron bg-primary text-white text-center py-5 mb-4">
        <div class="container">
            <h1 class="display-4 font-weight-bold"><?= htmlspecialchars($title ?? 'CIS MVC Platform') ?></h1>
            <p class="lead"><?= htmlspecialchars($message ?? 'Welcome to the enterprise MVC platform') ?></p>
            <hr class="my-4 bg-white">
            <p class="mb-4">Version <?= htmlspecialchars($version ?? '2.0.0') ?> | Environment: <?= htmlspecialchars($environment ?? 'production') ?></p>
        </div>
    </div>

    <!-- System Status Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5 class="card-title">System Status</h5>
                    <p class="card-text">Online & Healthy</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-server fa-3x mb-3"></i>
                    <h5 class="card-title">PHP Version</h5>
                    <p class="card-text"><?= htmlspecialchars($stats['php_version'] ?? PHP_VERSION) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <i class="fas fa-memory fa-3x mb-3"></i>
                    <h5 class="card-title">Memory Usage</h5>
                    <p class="card-text"><?= htmlspecialchars($stats['memory_usage'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-3x mb-3"></i>
                    <h5 class="card-title">Server Time</h5>
                    <p class="card-text"><?= htmlspecialchars($stats['server_time'] ?? date('H:i:s')) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-cogs mr-2"></i>Platform Features
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (isset($features) && is_array($features)): ?>
                            <?php foreach (array_chunk($features, ceil(count($features) / 2)) as $chunk): ?>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <?php foreach ($chunk as $feature): ?>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success mr-2"></i>
                                                <?= htmlspecialchars($feature) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-muted">No features configured</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-chart-line mr-2"></i>System Stats
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (isset($stats) && is_array($stats)): ?>
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($stats as $key => $value): ?>
                                    <tr>
                                        <td class="font-weight-bold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></td>
                                        <td><?= htmlspecialchars($value) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No statistics available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-rocket mr-2"></i>Quick Actions
                    </h3>
                </div>
                <div class="card-body text-center">
                    <a href="/dashboard" class="btn btn-primary btn-lg mx-2">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="/health" class="btn btn-success btn-lg mx-2">
                        <i class="fas fa-heartbeat mr-2"></i>Health Check
                    </a>
                    <a href="/metrics" class="btn btn-info btn-lg mx-2">
                        <i class="fas fa-chart-bar mr-2"></i>Metrics
                    </a>
                    <a href="/api/v1/system/status" class="btn btn-warning btn-lg mx-2">
                        <i class="fas fa-code mr-2"></i>API Status
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Notice -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info" role="alert">
                <i class="fas fa-shield-alt mr-2"></i>
                <strong>Security Notice:</strong> This application includes enterprise-grade security features including CSRF protection, 
                rate limiting, secure sessions, and comprehensive input validation.
            </div>
        </div>
    </div>
</div>

<!-- CSRF Token for forms -->
<script>
window.csrfToken = '<?= htmlspecialchars($csrf_token ?? '') ?>';
</script>
