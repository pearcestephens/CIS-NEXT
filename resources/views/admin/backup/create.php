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
                            <li class="breadcrumb-item active" aria-current="page">Create Backup</li>
                        </ol>
                    </nav>
                </div>
                <a href="/admin/backup/list" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Main Form -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-plus-circle"></i> Create New Backup
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/admin/backup/create" id="backupForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                
                                <!-- Backup Type Selection -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Backup Type <span class="text-danger">*</span></label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="card backup-type-card" data-type="full">
                                                <div class="card-body text-center">
                                                    <input type="radio" name="type" value="full" id="type_full" 
                                                           class="form-check-input d-none" 
                                                           <?= ($form_data['type'] ?? 'full') === 'full' ? 'checked' : '' ?>>
                                                    <label for="type_full" class="w-100 cursor-pointer">
                                                        <i class="fas fa-archive fa-2x text-primary mb-2"></i>
                                                        <h5 class="card-title">Full System Backup</h5>
                                                        <p class="card-text text-muted">
                                                            Complete backup including database, files, and configurations
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="card backup-type-card" data-type="database">
                                                <div class="card-body text-center">
                                                    <input type="radio" name="type" value="database" id="type_database" 
                                                           class="form-check-input d-none"
                                                           <?= ($form_data['type'] ?? '') === 'database' ? 'checked' : '' ?>>
                                                    <label for="type_database" class="w-100 cursor-pointer">
                                                        <i class="fas fa-database fa-2x text-info mb-2"></i>
                                                        <h5 class="card-title">Database Only</h5>
                                                        <p class="card-text text-muted">
                                                            Backup only the database structure and data
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="card backup-type-card" data-type="files">
                                                <div class="card-body text-center">
                                                    <input type="radio" name="type" value="files" id="type_files" 
                                                           class="form-check-input d-none"
                                                           <?= ($form_data['type'] ?? '') === 'files' ? 'checked' : '' ?>>
                                                    <label for="type_files" class="w-100 cursor-pointer">
                                                        <i class="fas fa-folder fa-2x text-secondary mb-2"></i>
                                                        <h5 class="card-title">Files Only</h5>
                                                        <p class="card-text text-muted">
                                                            Backup only application files and uploads
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="card backup-type-card" data-type="incremental">
                                                <div class="card-body text-center">
                                                    <input type="radio" name="type" value="incremental" id="type_incremental" 
                                                           class="form-check-input d-none"
                                                           <?= ($form_data['type'] ?? '') === 'incremental' ? 'checked' : '' ?>>
                                                    <label for="type_incremental" class="w-100 cursor-pointer">
                                                        <i class="fas fa-layer-group fa-2x text-warning mb-2"></i>
                                                        <h5 class="card-title">Incremental</h5>
                                                        <p class="card-text text-muted">
                                                            Backup only changes since last backup
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Basic Information -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Backup Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" 
                                               placeholder="Enter backup name" required>
                                        <div class="form-text">A descriptive name for this backup</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="description" class="form-label">Description</label>
                                        <input type="text" class="form-control" id="description" name="description" 
                                               value="<?= htmlspecialchars($form_data['description'] ?? '') ?>" 
                                               placeholder="Optional description">
                                    </div>
                                </div>

                                <!-- Advanced Options -->
                                <div class="card bg-light mb-4">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">
                                            <button class="btn btn-link p-0 text-decoration-none" type="button" 
                                                    data-bs-toggle="collapse" data-bs-target="#advancedOptions">
                                                <i class="fas fa-chevron-down"></i> Advanced Options
                                            </button>
                                        </h6>
                                    </div>
                                    <div class="collapse" id="advancedOptions">
                                        <div class="card-body">
                                            <div class="row">
                                                <!-- Compression -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="compression" class="form-label">Compression Method</label>
                                                    <select class="form-control" id="compression" name="compression">
                                                        <option value="zip" <?= ($form_data['compression'] ?? 'zip') === 'zip' ? 'selected' : '' ?>>
                                                            ZIP (Default)
                                                        </option>
                                                        <option value="gzip" <?= ($form_data['compression'] ?? '') === 'gzip' ? 'selected' : '' ?>>
                                                            GZIP (Better compression)
                                                        </option>
                                                        <option value="none" <?= ($form_data['compression'] ?? '') === 'none' ? 'selected' : '' ?>>
                                                            No compression
                                                        </option>
                                                    </select>
                                                </div>

                                                <!-- Password Protection -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="password" class="form-label">Password Protection</label>
                                                    <input type="password" class="form-control" id="password" name="password" 
                                                           placeholder="Optional password">
                                                    <div class="form-text">Leave empty for no password protection</div>
                                                </div>

                                                <!-- File Exclusions -->
                                                <div class="col-md-12 mb-3" id="fileExclusionsSection" style="display: none;">
                                                    <label class="form-label">File Exclusions</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="exclude_logs" 
                                                               name="exclusions[]" value="logs" 
                                                               <?= in_array('logs', $form_data['exclusions'] ?? []) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="exclude_logs">
                                                            Exclude log files (*.log)
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="exclude_cache" 
                                                               name="exclusions[]" value="cache"
                                                               <?= in_array('cache', $form_data['exclusions'] ?? []) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="exclude_cache">
                                                            Exclude cache files
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="exclude_temp" 
                                                               name="exclusions[]" value="temp"
                                                               <?= in_array('temp', $form_data['exclusions'] ?? []) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="exclude_temp">
                                                            Exclude temporary files
                                                        </label>
                                                    </div>
                                                </div>

                                                <!-- Custom Exclusion Patterns -->
                                                <div class="col-md-12 mb-3" id="customExclusionsSection" style="display: none;">
                                                    <label for="custom_exclusions" class="form-label">Custom Exclusion Patterns</label>
                                                    <textarea class="form-control" id="custom_exclusions" name="custom_exclusions" 
                                                              rows="3" placeholder="One pattern per line (e.g., *.tmp, /var/temp/*)">{{ $form_data['custom_exclusions'] ?? '' }}</textarea>
                                                    <div class="form-text">Use glob patterns to exclude files/directories</div>
                                                </div>

                                                <!-- Priority -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="priority" class="form-label">Priority</label>
                                                    <select class="form-control" id="priority" name="priority">
                                                        <option value="normal" <?= ($form_data['priority'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>
                                                            Normal
                                                        </option>
                                                        <option value="high" <?= ($form_data['priority'] ?? '') === 'high' ? 'selected' : '' ?>>
                                                            High
                                                        </option>
                                                        <option value="low" <?= ($form_data['priority'] ?? '') === 'low' ? 'selected' : '' ?>>
                                                            Low
                                                        </option>
                                                    </select>
                                                </div>

                                                <!-- Notifications -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Notifications</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="notify_completion" 
                                                               name="notify_completion" value="1"
                                                               <?= !empty($form_data['notify_completion']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="notify_completion">
                                                            Notify on completion
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="notify_failure" 
                                                               name="notify_failure" value="1"
                                                               <?= !empty($form_data['notify_failure']) ? 'checked' : 'checked' ?>>
                                                        <label class="form-check-label" for="notify_failure">
                                                            Notify on failure
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Buttons -->
                                <div class="d-flex justify-content-between">
                                    <a href="/admin/backup/list" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                    <div>
                                        <button type="submit" name="action" value="create" class="btn btn-primary me-2" id="createBtn">
                                            <i class="fas fa-play"></i> Create Backup
                                        </button>
                                        <button type="submit" name="action" value="schedule" class="btn btn-outline-primary" id="scheduleBtn">
                                            <i class="fas fa-clock"></i> Schedule for Later
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Info -->
                <div class="col-md-4">
                    <!-- Backup Type Info -->
                    <div class="card mb-3" id="backupTypeInfo">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i> Backup Information
                            </h6>
                        </div>
                        <div class="card-body" id="backupTypeDetails">
                            <div data-type="full">
                                <h6>Full System Backup</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Database structure and data</li>
                                    <li><i class="fas fa-check text-success"></i> Application files</li>
                                    <li><i class="fas fa-check text-success"></i> Configuration files</li>
                                    <li><i class="fas fa-check text-success"></i> User uploads</li>
                                    <li><i class="fas fa-check text-success"></i> System logs</li>
                                </ul>
                                <div class="alert alert-info">
                                    <small>Most comprehensive backup option. Recommended for disaster recovery.</small>
                                </div>
                            </div>
                            
                            <div data-type="database" style="display: none;">
                                <h6>Database Only Backup</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Database structure</li>
                                    <li><i class="fas fa-check text-success"></i> All table data</li>
                                    <li><i class="fas fa-check text-success"></i> Stored procedures</li>
                                    <li><i class="fas fa-check text-success"></i> Indexes and constraints</li>
                                </ul>
                                <div class="alert alert-warning">
                                    <small>Does not include files or configurations.</small>
                                </div>
                            </div>
                            
                            <div data-type="files" style="display: none;">
                                <h6>Files Only Backup</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Application source code</li>
                                    <li><i class="fas fa-check text-success"></i> User uploads</li>
                                    <li><i class="fas fa-check text-success"></i> Configuration files</li>
                                    <li><i class="fas fa-check text-success"></i> Static assets</li>
                                </ul>
                                <div class="alert alert-warning">
                                    <small>Does not include database content.</small>
                                </div>
                            </div>
                            
                            <div data-type="incremental" style="display: none;">
                                <h6>Incremental Backup</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Changed files only</li>
                                    <li><i class="fas fa-check text-success"></i> Database changes</li>
                                    <li><i class="fas fa-check text-success"></i> Faster creation</li>
                                    <li><i class="fas fa-check text-success"></i> Smaller file size</li>
                                </ul>
                                <div class="alert alert-info">
                                    <small>Requires a previous full backup as baseline.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Status -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-server"></i> System Status
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="mb-2">
                                        <i class="fas fa-hdd fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <strong><?= $system_info['free_space'] ?? 'Unknown' ?></strong>
                                        <br><small class="text-muted">Free Space</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-2">
                                        <i class="fas fa-memory fa-2x text-success"></i>
                                    </div>
                                    <div>
                                        <strong><?= $system_info['memory_usage'] ?? 'Unknown' ?></strong>
                                        <br><small class="text-muted">Memory</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Active Backups:</span>
                                <span class="badge bg-warning"><?= $system_info['active_backups'] ?? 0 ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Queue Size:</span>
                                <span class="badge bg-info"><?= $system_info['queue_size'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Backups -->
                    <?php if (!empty($recent_backups)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history"></i> Recent Backups
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($recent_backups, 0, 5) as $backup): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars($backup['name']) ?></div>
                                                <small class="text-muted"><?= timeAgo($backup['created_at']) ?></small>
                                            </div>
                                            <span class="badge bg-<?= getStatusColor($backup['status']) ?>">
                                                <?= ucfirst($backup['status']) ?>
                                            </span>
                                        </div>
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

<?php
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
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
?>

<?php include_once __DIR__ . '/../../partials/footer.php'; ?>
