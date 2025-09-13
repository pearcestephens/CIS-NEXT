<?php include_once __DIR__ . '/../../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../partials/admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><?= htmlspecialchars($title) ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/admin/backup/dashboard">Backup System</a></li>
                            <li class="breadcrumb-item"><a href="/admin/backup/list">Backups</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($backup['name']) ?></li>
                        </ol>
                    </nav>
                </div>
                <div class="btn-group">
                    <a href="/admin/backup/list" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <?php if ($backup['status'] === 'completed'): ?>
                        <a href="/admin/backup/download?id=<?= urlencode($backup['backup_id']) ?>" 
                           class="btn btn-success">
                            <i class="fas fa-download"></i> Download
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <!-- Main Details -->
                <div class="col-md-8">
                    <!-- Backup Overview -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?= getBackupIcon($backup['type']) ?>"></i>
                                <?= htmlspecialchars($backup['name']) ?>
                            </h5>
                            <span class="badge bg-<?= getStatusColor($backup['status']) ?> fs-6">
                                <?= ucfirst($backup['status']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($backup['description'])): ?>
                                <p class="text-muted mb-3"><?= htmlspecialchars($backup['description']) ?></p>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Type:</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge bg-<?= getBackupTypeColor($backup['type']) ?>">
                                                <?= ucfirst($backup['type']) ?>
                                            </span>
                                        </dd>

                                        <dt class="col-sm-4">Created:</dt>
                                        <dd class="col-sm-8"><?= date('M j, Y g:i A', strtotime($backup['created_at'])) ?></dd>

                                        <dt class="col-sm-4">Created By:</dt>
                                        <dd class="col-sm-8"><?= htmlspecialchars($backup['created_by_name'] ?? 'System') ?></dd>

                                        <?php if ($backup['started_at']): ?>
                                            <dt class="col-sm-4">Started:</dt>
                                            <dd class="col-sm-8"><?= date('M j, Y g:i A', strtotime($backup['started_at'])) ?></dd>
                                        <?php endif; ?>

                                        <?php if ($backup['completed_at']): ?>
                                            <dt class="col-sm-4">Completed:</dt>
                                            <dd class="col-sm-8"><?= date('M j, Y g:i A', strtotime($backup['completed_at'])) ?></dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>

                                <div class="col-md-6">
                                    <dl class="row">
                                        <?php if ($backup['original_size'] > 0): ?>
                                            <dt class="col-sm-4">Original Size:</dt>
                                            <dd class="col-sm-8"><?= formatBytes($backup['original_size']) ?></dd>
                                        <?php endif; ?>

                                        <?php if ($backup['compressed_size'] > 0): ?>
                                            <dt class="col-sm-4">Compressed:</dt>
                                            <dd class="col-sm-8">
                                                <?= formatBytes($backup['compressed_size']) ?>
                                                <?php if ($backup['original_size'] > 0): ?>
                                                    <span class="text-muted">
                                                        (<?= number_format((1 - $backup['compressed_size'] / $backup['original_size']) * 100, 1) ?>% reduction)
                                                    </span>
                                                <?php endif; ?>
                                            </dd>
                                        <?php endif; ?>

                                        <?php if ($backup['compression_method']): ?>
                                            <dt class="col-sm-4">Compression:</dt>
                                            <dd class="col-sm-8"><?= strtoupper($backup['compression_method']) ?></dd>
                                        <?php endif; ?>

                                        <?php if ($backup['password_protected']): ?>
                                            <dt class="col-sm-4">Protection:</dt>
                                            <dd class="col-sm-8">
                                                <i class="fas fa-lock text-warning"></i> Password Protected
                                            </dd>
                                        <?php endif; ?>

                                        <?php if ($backup['started_at'] && $backup['completed_at']): ?>
                                            <dt class="col-sm-4">Duration:</dt>
                                            <dd class="col-sm-8">
                                                <?= formatDuration(strtotime($backup['completed_at']) - strtotime($backup['started_at'])) ?>
                                            </dd>
                                        <?php endif; ?>

                                        <dt class="col-sm-4">Priority:</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge bg-<?= getPriorityColor($backup['priority']) ?>">
                                                <?= ucfirst($backup['priority']) ?>
                                            </span>
                                        </dd>
                                    </dl>
                                </div>
                            </div>

                            <!-- Progress Bar for Running Backups -->
                            <?php if ($backup['status'] === 'running'): ?>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Progress</span>
                                        <span id="progressPercent"><?= $backup['progress_percent'] ?? 0 ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                                             role="progressbar" style="width: <?= $backup['progress_percent'] ?? 0 ?>%"
                                             id="progressBar"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Error Messages -->
                            <?php if ($backup['status'] === 'failed' && !empty($backup['error_message'])): ?>
                                <div class="alert alert-danger mt-3">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Error Details</h6>
                                    <pre class="mb-0"><?= htmlspecialchars($backup['error_message']) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Backup Contents (if available) -->
                    <?php if (!empty($backup['contents']) && $backup['status'] === 'completed'): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list"></i> Backup Contents
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="contentsAccordion">
                                    <?php foreach ($backup['contents'] as $section => $items): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?= ucfirst($section) ?>">
                                                <button class="accordion-button collapsed" type="button" 
                                                        data-bs-toggle="collapse" data-bs-target="#collapse<?= ucfirst($section) ?>">
                                                    <?= ucfirst($section) ?> 
                                                    <span class="badge bg-secondary ms-2"><?= count($items) ?></span>
                                                </button>
                                            </h2>
                                            <div id="collapse<?= ucfirst($section) ?>" class="accordion-collapse collapse"
                                                 data-bs-parent="#contentsAccordion">
                                                <div class="accordion-body">
                                                    <ul class="list-unstyled mb-0" style="max-height: 200px; overflow-y: auto;">
                                                        <?php foreach ($items as $item): ?>
                                                            <li class="py-1">
                                                                <i class="fas fa-<?= getItemIcon($section, $item) ?> me-2"></i>
                                                                <?= htmlspecialchars($item) ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Activity Log -->
                    <?php if (!empty($activity_log)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history"></i> Activity Log
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="180">Timestamp</th>
                                                <th width="100">Level</th>
                                                <th>Message</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activity_log as $entry): ?>
                                                <tr>
                                                    <td>
                                                        <small><?= date('M j, Y g:i:s A', strtotime($entry['created_at'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= getLogLevelColor($entry['level']) ?>">
                                                            <?= strtoupper($entry['level']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($entry['message']) ?>
                                                        <?php if (!empty($entry['context'])): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars(json_encode($entry['context'])) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Actions -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-cogs"></i> Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($backup['status'] === 'completed'): ?>
                                <div class="d-grid gap-2">
                                    <a href="/admin/backup/download?id=<?= urlencode($backup['backup_id']) ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-download"></i> Download Backup
                                    </a>
                                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#restoreModal">
                                        <i class="fas fa-undo"></i> Restore from Backup
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" onclick="duplicateBackup()">
                                        <i class="fas fa-copy"></i> Duplicate Backup
                                    </button>
                                </div>
                                <hr>
                            <?php endif; ?>

                            <?php if ($backup['status'] === 'running'): ?>
                                <div class="d-grid gap-2 mb-3">
                                    <button type="button" class="btn btn-warning" onclick="cancelBackup()">
                                        <i class="fas fa-stop"></i> Cancel Backup
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="refreshStatus()">
                                        <i class="fas fa-sync-alt"></i> Refresh Status
                                    </button>
                                </div>
                                <hr>
                            <?php endif; ?>

                            <?php if (in_array($backup['status'], ['completed', 'failed', 'cancelled'])): ?>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="fas fa-trash"></i> Delete Backup
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Backup Statistics -->
                    <?php if ($backup['status'] === 'completed'): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-chart-bar"></i> Statistics
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 border-end">
                                        <div class="h5 mb-0"><?= $backup['file_count'] ?? 0 ?></div>
                                        <small class="text-muted">Files</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h5 mb-0"><?= $backup['table_count'] ?? 0 ?></div>
                                        <small class="text-muted">DB Tables</small>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Compression Ratio:</span>
                                    <strong>
                                        <?php if ($backup['original_size'] > 0): ?>
                                            <?= number_format((1 - $backup['compressed_size'] / $backup['original_size']) * 100, 1) ?>%
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <span>Processing Speed:</span>
                                    <strong>
                                        <?php if ($backup['started_at'] && $backup['completed_at'] && $backup['original_size'] > 0): ?>
                                            <?= formatBytes($backup['original_size'] / (strtotime($backup['completed_at']) - strtotime($backup['started_at']))) ?>/s
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Related Backups -->
                    <?php if (!empty($related_backups)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-link"></i> Related Backups
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($related_backups as $related): ?>
                                        <a href="/admin/backup/view?id=<?= urlencode($related['backup_id']) ?>" 
                                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars($related['name']) ?></div>
                                                <small class="text-muted">
                                                    <?= ucfirst($related['type']) ?> • <?= timeAgo($related['created_at']) ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?= getStatusColor($related['status']) ?>">
                                                <?= ucfirst($related['status']) ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restoreModalLabel">Restore from Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> Restoring from backup will overwrite current data. This action cannot be undone.
                </div>
                
                <form id="restoreForm">
                    <div class="mb-3">
                        <label class="form-label">Restore Options:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="restore_database" checked>
                            <label class="form-check-label" for="restore_database">
                                Restore Database
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="restore_files" checked>
                            <label class="form-check-label" for="restore_files">
                                Restore Files
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="create_backup_before_restore" checked>
                            <label class="form-check-label" for="create_backup_before_restore">
                                Create backup of current state before restore
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmRestore()">Start Restore</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the backup "<strong><?= htmlspecialchars($backup['name']) ?></strong>"?</p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete Backup</button>
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

function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }
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

function getPriorityColor($priority) {
    $colors = [
        'high' => 'danger',
        'normal' => 'secondary',
        'low' => 'light'
    ];
    return $colors[$priority] ?? 'secondary';
}

function getLogLevelColor($level) {
    $colors = [
        'error' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
        'debug' => 'secondary'
    ];
    return $colors[strtolower($level)] ?? 'secondary';
}

function getItemIcon($section, $item) {
    if ($section === 'database') return 'table';
    if ($section === 'files') {
        if (strpos($item, '.php') !== false) return 'code';
        if (strpos($item, '.js') !== false) return 'js-square';
        if (strpos($item, '.css') !== false) return 'css3-alt';
        if (strpos($item, '.jpg') !== false || strpos($item, '.png') !== false) return 'image';
        return 'file';
    }
    return 'folder';
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
