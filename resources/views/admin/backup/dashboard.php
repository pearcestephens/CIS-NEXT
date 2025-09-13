<?php include_once __DIR__ . '/../../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../partials/admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><?= htmlspecialchars($title) ?></h1>
                <div class="btn-group">
                    <a href="/admin/backup/create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Backup
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="refreshStats">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- System Health Alert -->
            <?php if ($health['overall_status'] !== 'healthy'): ?>
            <div class="alert alert-<?= $health['overall_status'] === 'error' ? 'danger' : 'warning' ?> mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div>
                        <strong>System Health: <?= ucfirst($health['overall_status']) ?></strong>
                        <?php if (!empty($health['errors'])): ?>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($health['errors'] as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($health['warnings'])): ?>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($health['warnings'] as $warning): ?>
                                    <li><?= htmlspecialchars($warning) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-0">Total Backups</h5>
                                    <h2 class="mb-0"><?= number_format($statistics['overview']['total_backups'] ?? 0) ?></h2>
                                </div>
                                <div class="fs-2">
                                    <i class="fas fa-archive"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-0">Successful</h5>
                                    <h2 class="mb-0"><?= number_format($statistics['overview']['completed_backups'] ?? 0) ?></h2>
                                </div>
                                <div class="fs-2">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-0">Failed</h5>
                                    <h2 class="mb-0"><?= number_format($statistics['overview']['failed_backups'] ?? 0) ?></h2>
                                </div>
                                <div class="fs-2">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-0">Total Size</h5>
                                    <h2 class="mb-0"><?= formatBytes($statistics['overview']['total_size'] ?? 0) ?></h2>
                                </div>
                                <div class="fs-2">
                                    <i class="fas fa-hdd"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics Row -->
            <div class="row mb-4">
                <!-- Backup Types Chart -->
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Backup Types Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="backupTypesChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Storage Usage -->
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Storage Usage</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $storage = $statistics['storage_usage'] ?? [];
                            $usage_percent = $storage['usage_percent'] ?? 0;
                            $progress_class = $usage_percent > 85 ? 'bg-danger' : ($usage_percent > 70 ? 'bg-warning' : 'bg-success');
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Disk Usage</span>
                                    <span><?= number_format($usage_percent, 1) ?>%</span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?= $progress_class ?>" role="progressbar" 
                                         style="width: <?= $usage_percent ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong><?= formatBytes($storage['backup_size'] ?? 0) ?></strong>
                                    <br><small class="text-muted">Backup Size</small>
                                </div>
                                <div class="col-6">
                                    <strong><?= formatBytes($storage['free_space'] ?? 0) ?></strong>
                                    <br><small class="text-muted">Free Space</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Backups Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Backups</h5>
                    <a href="/admin/backup/list" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_backups)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No backups found</h5>
                            <p class="text-muted">Create your first backup to get started.</p>
                            <a href="/admin/backup/create" class="btn btn-primary">Create Backup</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Size</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_backups as $backup): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-<?= getBackupIcon($backup['type']) ?> me-2"></i>
                                                    <?= htmlspecialchars($backup['name']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getBackupTypeColor($backup['type']) ?>">
                                                    <?= ucfirst($backup['type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getStatusColor($backup['status']) ?>">
                                                    <?= ucfirst($backup['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatBytes($backup['compressed_size']) ?></td>
                                            <td>
                                                <span title="<?= $backup['created_at'] ?>">
                                                    <?= timeAgo($backup['created_at']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="/admin/backup/view?id=<?= urlencode($backup['backup_id']) ?>" 
                                                       class="btn btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($backup['status'] === 'completed'): ?>
                                                        <a href="/admin/backup/download?id=<?= urlencode($backup['backup_id']) ?>" 
                                                           class="btn btn-outline-success" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function getBackupIcon($type) {
    $icons = [
        'full' => 'archive',
        'database' => 'database',
        'files' => 'folder',
        'incremental' => 'layer-group'
    ];
    return $icons[$type] ?? 'file';
}

function getBackupTypeColor($type) {
    $colors = [
        'full' => 'primary',
        'database' => 'info', 
        'files' => 'secondary',
        'incremental' => 'warning'
    ];
    return $colors[$type] ?? 'light';
}

function getStatusColor($status) {
    $colors = [
        'completed' => 'success',
        'running' => 'warning',
        'pending' => 'info',
        'failed' => 'danger',
        'cancelled' => 'secondary'
    ];
    return $colors[$status] ?? 'light';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>

<?php include_once __DIR__ . '/../../partials/footer.php'; ?>
