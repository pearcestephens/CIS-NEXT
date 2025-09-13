<?php
/**
 * Admin Table Component
 * File: app/Http/Views/admin/components/table.php
 * Purpose: Standardized sortable/paginated table for admin interface
 */

// Default values
$table_id = $table_id ?? uniqid('table_');
$table_class = $table_class ?? 'table-striped table-hover';
$table_data = $table_data ?? [];
$table_columns = $table_columns ?? [];
$table_actions = $table_actions ?? [];
$table_sortable = $table_sortable ?? true;
$table_paginated = $table_paginated ?? true;
$table_search = $table_search ?? true;
$table_per_page = $table_per_page ?? 25;
$table_empty_message = $table_empty_message ?? 'No data available';
$table_loading = $table_loading ?? false;
$table_responsive = $table_responsive ?? true;
$table_striped = $table_striped ?? true;
?>

<div class="admin-table-wrapper" id="<?= htmlspecialchars($table_id) ?>_wrapper">
    
    <?php if ($table_search || !empty($table_actions)): ?>
    <div class="table-controls mb-3">
        <div class="row align-items-center">
            <?php if ($table_search): ?>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           id="<?= htmlspecialchars($table_id) ?>_search"
                           placeholder="Search table..." 
                           autocomplete="off">
                    <button class="btn btn-outline-secondary" 
                            type="button" 
                            id="<?= htmlspecialchars($table_id) ?>_clear">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($table_actions)): ?>
            <div class="col-md-6 text-end">
                <div class="btn-group" role="group">
                    <?php foreach ($table_actions as $action): ?>
                    <button type="button" 
                            class="btn <?= $action['class'] ?? 'btn-primary' ?>"
                            <?= isset($action['onclick']) ? 'onclick="' . htmlspecialchars($action['onclick']) . '"' : '' ?>
                            <?= isset($action['data']) ? 'data-action="' . htmlspecialchars($action['data']) . '"' : '' ?>
                            <?= isset($action['disabled']) && $action['disabled'] ? 'disabled' : '' ?>>
                        <?php if (isset($action['icon'])): ?>
                        <i class="<?= htmlspecialchars($action['icon']) ?>"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($action['text']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($table_responsive): ?>
    <div class="table-responsive">
    <?php endif; ?>
    
    <table class="table <?= $table_class ?>" id="<?= htmlspecialchars($table_id) ?>">
        <thead class="table-dark">
            <tr>
                <?php foreach ($table_columns as $column): ?>
                <th scope="col" 
                    <?= ($table_sortable && ($column['sortable'] ?? true)) ? 'class="sortable" data-sort="' . htmlspecialchars($column['key']) . '"' : '' ?>
                    <?= isset($column['width']) ? 'style="width: ' . htmlspecialchars($column['width']) . ';"' : '' ?>>
                    <?= htmlspecialchars($column['label']) ?>
                    <?php if ($table_sortable && ($column['sortable'] ?? true)): ?>
                    <i class="fas fa-sort ms-1"></i>
                    <?php endif; ?>
                </th>
                <?php endforeach; ?>
                
                <?php if (!empty($table_actions) && isset($table_actions[0]['row_actions'])): ?>
                <th scope="col" class="text-center" style="width: 120px;">Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($table_loading): ?>
            <tr>
                <td colspan="<?= count($table_columns) + (isset($table_actions[0]['row_actions']) ? 1 : 0) ?>" class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2">Loading data...</div>
                </td>
            </tr>
            <?php elseif (empty($table_data)): ?>
            <tr>
                <td colspan="<?= count($table_columns) + (isset($table_actions[0]['row_actions']) ? 1 : 0) ?>" class="text-center p-4 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <div><?= htmlspecialchars($table_empty_message) ?></div>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($table_data as $row_index => $row): ?>
                <tr data-row-index="<?= $row_index ?>">
                    <?php foreach ($table_columns as $column): ?>
                    <td <?= isset($column['class']) ? 'class="' . htmlspecialchars($column['class']) . '"' : '' ?>>
                        <?php
                        $value = $row[$column['key']] ?? '';
                        
                        // Handle different column types
                        if (isset($column['type'])) {
                            switch ($column['type']) {
                                case 'badge':
                                    $badge_class = $column['badge_class'] ?? 'bg-secondary';
                                    echo '<span class="badge ' . htmlspecialchars($badge_class) . '">' . htmlspecialchars($value) . '</span>';
                                    break;
                                case 'date':
                                    echo $value ? date('Y-m-d H:i', strtotime($value)) : '-';
                                    break;
                                case 'currency':
                                    echo '$' . number_format((float)$value, 2);
                                    break;
                                case 'boolean':
                                    echo $value ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>';
                                    break;
                                case 'html':
                                    echo $value; // Raw HTML - be careful!
                                    break;
                                default:
                                    echo htmlspecialchars($value);
                            }
                        } else {
                            echo htmlspecialchars($value);
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($table_actions) && isset($table_actions[0]['row_actions'])): ?>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                            <?php foreach ($table_actions[0]['row_actions'] as $action): ?>
                            <button type="button" 
                                    class="btn <?= $action['class'] ?? 'btn-outline-primary' ?>"
                                    data-row-action="<?= htmlspecialchars($action['action']) ?>"
                                    data-row-id="<?= htmlspecialchars($row['id'] ?? $row_index) ?>"
                                    title="<?= htmlspecialchars($action['title'] ?? $action['action']) ?>">
                                <?php if (isset($action['icon'])): ?>
                                <i class="<?= htmlspecialchars($action['icon']) ?>"></i>
                                <?php else: ?>
                                <?= htmlspecialchars($action['text'] ?? ucfirst($action['action'])) ?>
                                <?php endif; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($table_responsive): ?>
    </div>
    <?php endif; ?>
    
    <?php if ($table_paginated && !empty($table_data)): ?>
    <div class="table-pagination">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="table-info">
                    Showing <span id="<?= htmlspecialchars($table_id) ?>_start">1</span> 
                    to <span id="<?= htmlspecialchars($table_id) ?>_end"><?= min($table_per_page, count($table_data)) ?></span> 
                    of <span id="<?= htmlspecialchars($table_id) ?>_total"><?= count($table_data) ?></span> entries
                </div>
            </div>
            <div class="col-md-6">
                <nav>
                    <ul class="pagination justify-content-end mb-0" id="<?= htmlspecialchars($table_id) ?>_pagination">
                        <!-- Pagination will be generated by JavaScript -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.admin-table-wrapper .sortable {
    cursor: pointer;
    user-select: none;
}

.admin-table-wrapper .sortable:hover {
    background-color: rgba(0,0,0,0.05);
}

.admin-table-wrapper .sortable.asc i {
    transform: rotate(180deg);
}

.admin-table-wrapper .sortable.desc i {
    transform: rotate(0deg);
}

.admin-table-wrapper .table-controls {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
}

.admin-table-wrapper .table-info {
    color: #6c757d;
    font-size: 0.875rem;
}

.admin-table-wrapper .btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableWrapper = document.getElementById('<?= htmlspecialchars($table_id) ?>_wrapper');
    const table = document.getElementById('<?= htmlspecialchars($table_id) ?>');
    const searchInput = document.getElementById('<?= htmlspecialchars($table_id) ?>_search');
    const clearButton = document.getElementById('<?= htmlspecialchars($table_id) ?>_clear');
    
    let currentPage = 1;
    let itemsPerPage = <?= $table_per_page ?>;
    let filteredData = [...table.querySelectorAll('tbody tr:not([data-empty])')];
    let sortColumn = null;
    let sortDirection = 'asc';
    
    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterTable(this.value);
        });
    }
    
    if (clearButton) {
        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            filterTable('');
        });
    }
    
    // Sort functionality
    table.querySelectorAll('.sortable').forEach(header => {
        header.addEventListener('click', function() {
            const sortKey = this.dataset.sort;
            
            if (sortColumn === sortKey) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumn = sortKey;
                sortDirection = 'asc';
            }
            
            // Update header classes
            table.querySelectorAll('.sortable').forEach(h => {
                h.classList.remove('asc', 'desc');
            });
            this.classList.add(sortDirection);
            
            sortTable(sortKey, sortDirection);
        });
    });
    
    // Row actions
    table.addEventListener('click', function(e) {
        if (e.target.closest('[data-row-action]')) {
            const button = e.target.closest('[data-row-action]');
            const action = button.dataset.rowAction;
            const rowId = button.dataset.rowId;
            
            // Trigger custom event for row actions
            document.dispatchEvent(new CustomEvent('tableRowAction', {
                detail: { action, rowId, button, table: table }
            }));
        }
    });
    
    function filterTable(searchTerm) {
        const rows = table.querySelectorAll('tbody tr:not([data-empty])');
        const term = searchTerm.toLowerCase();
        
        filteredData = [];
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(term)) {
                row.style.display = '';
                filteredData.push(row);
            } else {
                row.style.display = 'none';
            }
        });
        
        updatePagination();
    }
    
    function sortTable(column, direction) {
        const columnIndex = [...table.querySelectorAll('thead th')].findIndex(th => th.dataset.sort === column);
        if (columnIndex === -1) return;
        
        const rows = [...filteredData];
        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();
            
            const result = aVal.localeCompare(bVal, undefined, { numeric: true });
            return direction === 'asc' ? result : -result;
        });
        
        const tbody = table.querySelector('tbody');
        rows.forEach(row => tbody.appendChild(row));
        
        updatePagination();
    }
    
    function updatePagination() {
        <?php if ($table_paginated): ?>
        const totalItems = filteredData.length;
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        
        // Update info
        const startItem = Math.min((currentPage - 1) * itemsPerPage + 1, totalItems);
        const endItem = Math.min(currentPage * itemsPerPage, totalItems);
        
        document.getElementById('<?= htmlspecialchars($table_id) ?>_start').textContent = totalItems > 0 ? startItem : 0;
        document.getElementById('<?= htmlspecialchars($table_id) ?>_end').textContent = endItem;
        document.getElementById('<?= htmlspecialchars($table_id) ?>_total').textContent = totalItems;
        
        // Generate pagination
        const pagination = document.getElementById('<?= htmlspecialchars($table_id) ?>_pagination');
        pagination.innerHTML = '';
        
        if (totalPages > 1) {
            // Previous button
            const prevItem = document.createElement('li');
            prevItem.className = 'page-item' + (currentPage === 1 ? ' disabled' : '');
            prevItem.innerHTML = '<a class="page-link" href="#" data-page="' + (currentPage - 1) + '">Previous</a>';
            pagination.appendChild(prevItem);
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
                    const pageItem = document.createElement('li');
                    pageItem.className = 'page-item' + (i === currentPage ? ' active' : '');
                    pageItem.innerHTML = '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
                    pagination.appendChild(pageItem);
                } else if (Math.abs(i - currentPage) === 3) {
                    const ellipsis = document.createElement('li');
                    ellipsis.className = 'page-item disabled';
                    ellipsis.innerHTML = '<span class="page-link">...</span>';
                    pagination.appendChild(ellipsis);
                }
            }
            
            // Next button
            const nextItem = document.createElement('li');
            nextItem.className = 'page-item' + (currentPage === totalPages ? ' disabled' : '');
            nextItem.innerHTML = '<a class="page-link" href="#" data-page="' + (currentPage + 1) + '">Next</a>';
            pagination.appendChild(nextItem);
        }
        
        // Show/hide rows based on current page
        filteredData.forEach((row, index) => {
            const shouldShow = index >= (currentPage - 1) * itemsPerPage && index < currentPage * itemsPerPage;
            row.style.display = shouldShow ? '' : 'none';
        });
        <?php endif; ?>
    }
    
    // Pagination click handler
    <?php if ($table_paginated): ?>
    document.addEventListener('click', function(e) {
        if (e.target.matches('#<?= htmlspecialchars($table_id) ?>_pagination .page-link')) {
            e.preventDefault();
            const page = parseInt(e.target.dataset.page);
            if (page && page !== currentPage) {
                currentPage = page;
                updatePagination();
            }
        }
    });
    <?php endif; ?>
    
    // Initial pagination setup
    updatePagination();
});
</script>
