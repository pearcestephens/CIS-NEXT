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
                    <button type="button" class="btn btn-outline-secondary" id="refreshList">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="/admin/backup/list" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>" 
                                       placeholder="Search by name...">
                            </div>
                            <div class="col-md-2">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-control" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="full" <?= ($filters['type'] ?? '') === 'full' ? 'selected' : '' ?>>Full</option>
                                    <option value="database" <?= ($filters['type'] ?? '') === 'database' ? 'selected' : '' ?>>Database</option>
                                    <option value="files" <?= ($filters['type'] ?? '') === 'files' ? 'selected' : '' ?>>Files</option>
                                    <option value="incremental" <?= ($filters['type'] ?? '') === 'incremental' ? 'selected' : '' ?>>Incremental</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="running" <?= ($filters['status'] ?? '') === 'running' ? 'selected' : '' ?>>Running</option>
                                    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
                                    <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Backup List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        Backups 
                        <span class="badge bg-secondary"><?= number_format($total_count) ?></span>
                    </h5>
                    
                    <!-- Bulk Actions -->
                    <div class="d-flex align-items-center">
                        <div class="me-3" id="bulkActions" style="display: none;">
                            <select class="form-select form-select-sm me-2" id="bulkActionSelect">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete Selected</option>
                                <option value="download">Download Selected</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-warning" id="executeBulkAction">Execute</button>
                        </div>
                        
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="selectAll">
                                <i class="fas fa-check-square"></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="clearSelection">
                                <i class="fas fa-square"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($backups)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-archive fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No backups found</h5>
                            <?php if (!empty($filters) && array_filter($filters)): ?>
                                <p class="text-muted">Try adjusting your filters or <a href="/admin/backup/list">clear all filters</a>.</p>
                            <?php else: ?>
                                <p class="text-muted">Create your first backup to get started.</p>
                                <a href="/admin/backup/create" class="btn btn-primary">Create Backup</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                        </th>
                                        <th>
                                            <a href="<?= buildSortUrl('name') ?>" class="text-decoration-none text-dark">
                                                Name <?= getSortIcon('name') ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="<?= buildSortUrl('type') ?>" class="text-decoration-none text-dark">
                                                Type <?= getSortIcon('type') ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="<?= buildSortUrl('status') ?>" class="text-decoration-none text-dark">
                                                Status <?= getSortIcon('status') ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="<?= buildSortUrl('compressed_size') ?>" class="text-decoration-none text-dark">
                                                Size <?= getSortIcon('compressed_size') ?>
                                            </a>
                                        </th>
                                        <th>Duration</th>
                                        <th>
                                            <a href="<?= buildSortUrl('created_at') ?>" class="text-decoration-none text-dark">
                                                Created <?= getSortIcon('created_at') ?>
                                            </a>
                                        </th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr class="backup-row" data-backup-id="<?= htmlspecialchars($backup['backup_id']) ?>">
                                            <td>
                                                <input type="checkbox" class="form-check-input backup-checkbox" 
                                                       value="<?= htmlspecialchars($backup['backup_id']) ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-<?= getBackupIcon($backup['type']) ?> me-2 text-<?= getBackupTypeColor($backup['type']) ?>"></i>
                                                    <div>
                                                        <div class="fw-medium"><?= htmlspecialchars($backup['name']) ?></div>
                                                        <?php if (!empty($backup['description'])): ?>
                                                            <small class="text-muted"><?= htmlspecialchars($backup['description']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getBackupTypeColor($backup['type']) ?>">
                                                    <?= ucfirst($backup['type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-<?= getStatusColor($backup['status']) ?> me-2">
                                                        <?= ucfirst($backup['status']) ?>
                                                    </span>
                                                    <?php if ($backup['status'] === 'running'): ?>
                                                        <div class="spinner-border spinner-border-sm text-warning" role="status">
                                                            <span class="visually-hidden">Loading...</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($backup['compressed_size'] > 0): ?>
                                                    <div><?= formatBytes($backup['compressed_size']) ?></div>
                                                    <?php if ($backup['original_size'] > 0): ?>
                                                        <small class="text-muted">
                                                            <?= number_format((1 - $backup['compressed_size'] / $backup['original_size']) * 100, 1) ?>% compressed
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($backup['completed_at'] && $backup['started_at']): ?>
                                                    <?= formatDuration(strtotime($backup['completed_at']) - strtotime($backup['started_at'])) ?>
                                                <?php elseif ($backup['started_at']): ?>
                                                    <?= formatDuration(time() - strtotime($backup['started_at'])) ?> <span class="text-muted">(running)</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div title="<?= $backup['created_at'] ?>">
                                                    <?= timeAgo($backup['created_at']) ?>
                                                </div>
                                                <small class="text-muted">by <?= htmlspecialchars($backup['created_by_name'] ?? 'System') ?></small>
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
                                                    
                                                    <?php if (in_array($backup['status'], ['completed', 'failed', 'cancelled'])): ?>
                                                        <button type="button" class="btn btn-outline-danger delete-backup" 
                                                                data-backup-id="<?= htmlspecialchars($backup['backup_id']) ?>"
                                                                data-backup-name="<?= htmlspecialchars($backup['name']) ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        Showing <?= number_format(($current_page - 1) * $per_page + 1) ?> to 
                                        <?= number_format(min($current_page * $per_page, $total_count)) ?> of 
                                        <?= number_format($total_count) ?> entries
                                    </div>
                                    
                                    <nav aria-label="Backup pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            <?php if ($current_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= buildPageUrl(1) ?>">First</a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= buildPageUrl($current_page - 1) ?>">Previous</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            $start = max(1, $current_page - 2);
                                            $end = min($total_pages, $current_page + 2);
                                            ?>
                                            
                                            <?php if ($start > 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                                <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= buildPageUrl($i) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($end < $total_pages): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            
                                            <?php if ($current_page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= buildPageUrl($current_page + 1) ?>">Next</a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= buildPageUrl($total_pages) ?>">Last</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the backup "<strong id="deleteBackupName"></strong>"?</p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete Backup</button>
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

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}

function buildSortUrl($column) {
    global $sort_column, $sort_direction, $filters;
    
    $params = array_merge($filters, [
        'sort' => $column,
        'direction' => ($sort_column === $column && $sort_direction === 'asc') ? 'desc' : 'asc'
    ]);
    
    return '/admin/backup/list?' . http_build_query(array_filter($params));
}

function getSortIcon($column) {
    global $sort_column, $sort_direction;
    
    if ($sort_column !== $column) {
        return '<i class="fas fa-sort text-muted"></i>';
    }
    
    return $sort_direction === 'asc' 
        ? '<i class="fas fa-sort-up text-primary"></i>'
        : '<i class="fas fa-sort-down text-primary"></i>';
}

function buildPageUrl($page) {
    global $filters, $sort_column, $sort_direction;
    
    $params = array_merge($filters, [
        'page' => $page,
        'sort' => $sort_column,
        'direction' => $sort_direction
    ]);
    
    return '/admin/backup/list?' . http_build_query(array_filter($params));
}
?>

<?php include_once __DIR__ . '/../../partials/footer.php'; ?>
