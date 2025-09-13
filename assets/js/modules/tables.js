/**
 * Tables Helper Module
 * File: assets/js/modules/tables.js
 * Purpose: Enhanced table functionality - sorting, filtering, pagination, bulk operations
 */

class TablesHelper {
    constructor() {
        this.tables = new Map();
    }

    /**
     * Initialize enhanced table functionality
     */
    initialize(table, options = {}) {
        const tableId = table.id || `table-${Date.now()}`;
        table.id = tableId;

        const config = {
            sortable: true,
            filterable: true,
            stickyHeader: true,
            bulkSelect: false,
            pagination: false,
            emptyMessage: 'No data available',
            ...options
        };

        this.tables.set(tableId, { element: table, config });

        if (config.sortable) {
            this.setupSorting(table);
        }

        if (config.filterable) {
            this.setupFiltering(table);
        }

        if (config.stickyHeader) {
            this.setupStickyHeader(table);
        }

        if (config.bulkSelect) {
            this.setupBulkSelection(table);
        }

        if (config.pagination) {
            this.setupPagination(table, config.pagination);
        }

        this.setupEmptyState(table, config.emptyMessage);
        this.setupResponsiveTable(table);
    }

    /**
     * Setup column sorting
     */
    setupSorting(table) {
        const headers = table.querySelectorAll('th[data-sortable]');

        for (const header of headers) {
            header.classList.add('sortable');
            header.style.cursor = 'pointer';
            header.setAttribute('tabindex', '0');
            header.setAttribute('role', 'columnheader');
            
            // Add sort indicator
            const indicator = document.createElement('i');
            indicator.className = 'fas fa-sort ms-1 sort-indicator';
            header.appendChild(indicator);

            const handleSort = () => {
                const column = header.dataset.sortable;
                const currentSort = header.dataset.sort || 'none';
                
                // Reset other headers
                table.querySelectorAll('th[data-sortable]').forEach(h => {
                    if (h !== header) {
                        h.dataset.sort = 'none';
                        h.querySelector('.sort-indicator').className = 'fas fa-sort ms-1 sort-indicator';
                    }
                });

                // Toggle current header
                let newSort;
                if (currentSort === 'none' || currentSort === 'desc') {
                    newSort = 'asc';
                    indicator.className = 'fas fa-sort-up ms-1 sort-indicator';
                } else {
                    newSort = 'desc';
                    indicator.className = 'fas fa-sort-down ms-1 sort-indicator';
                }

                header.dataset.sort = newSort;
                this.sortTable(table, column, newSort);
            };

            header.addEventListener('click', handleSort);
            header.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleSort();
                }
            });
        }
    }

    /**
     * Sort table by column
     */
    sortTable(table, column, direction) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        const sortedRows = rows.sort((a, b) => {
            const aValue = this.getCellValue(a, column);
            const bValue = this.getCellValue(b, column);
            
            // Try numeric comparison first
            const aNum = parseFloat(aValue);
            const bNum = parseFloat(bValue);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            }

            // Date comparison
            const aDate = Date.parse(aValue);
            const bDate = Date.parse(bValue);
            
            if (!isNaN(aDate) && !isNaN(bDate)) {
                return direction === 'asc' ? aDate - bDate : bDate - aDate;
            }

            // String comparison
            const comparison = aValue.localeCompare(bValue, undefined, { numeric: true });
            return direction === 'asc' ? comparison : -comparison;
        });

        // Re-append sorted rows
        for (const row of sortedRows) {
            tbody.appendChild(row);
        }

        // Announce sort to screen readers
        window.AdminPanel.ui.announce(`Table sorted by ${column} in ${direction}ending order`);
    }

    /**
     * Get cell value for sorting
     */
    getCellValue(row, column) {
        const cell = row.querySelector(`[data-column="${column}"]`) || 
                    row.cells[parseInt(column)] ||
                    row.querySelector(`td:nth-child(${parseInt(column) + 1})`);
        
        if (!cell) return '';
        
        const sortValue = cell.dataset.sortValue;
        if (sortValue !== undefined) return sortValue;
        
        return cell.textContent.trim();
    }

    /**
     * Setup table filtering
     */
    setupFiltering(table) {
        const filterId = `${table.id}-filter`;
        let filterInput = document.getElementById(filterId);
        
        if (!filterInput) {
            const filterContainer = document.createElement('div');
            filterContainer.className = 'table-filter mb-3';
            filterContainer.innerHTML = `
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control" id="${filterId}" 
                           placeholder="Filter table..." aria-label="Filter table">
                </div>
            `;
            
            table.parentElement.insertBefore(filterContainer, table);
            filterInput = document.getElementById(filterId);
        }

        const debounceFilter = window.AdminPanel.ui.debounce((query) => {
            this.filterTable(table, query);
        }, 300);

        filterInput.addEventListener('input', (e) => {
            debounceFilter(e.target.value);
        });
    }

    /**
     * Filter table rows
     */
    filterTable(table, query) {
        const tbody = table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        const searchTerm = query.toLowerCase();
        let visibleCount = 0;

        for (const row of rows) {
            const text = row.textContent.toLowerCase();
            const isVisible = searchTerm === '' || text.includes(searchTerm);
            
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        }

        this.updateEmptyState(table, visibleCount === 0 && query !== '');
        
        // Announce filter result
        if (query) {
            window.AdminPanel.ui.announce(`${visibleCount} rows match your filter`);
        }
    }

    /**
     * Setup sticky header
     */
    setupStickyHeader(table) {
        const thead = table.querySelector('thead');
        if (!thead) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                thead.classList.toggle('sticky-header', !entry.isIntersecting);
            },
            { threshold: [0] }
        );

        observer.observe(table);
    }

    /**
     * Setup bulk selection
     */
    setupBulkSelection(table) {
        const thead = table.querySelector('thead tr');
        const tbody = table.querySelector('tbody');

        // Add select all checkbox to header
        const selectAllTh = document.createElement('th');
        selectAllTh.className = 'select-column';
        selectAllTh.innerHTML = `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="${table.id}-select-all">
                <label class="form-check-label sr-only" for="${table.id}-select-all">Select all</label>
            </div>
        `;
        thead.insertBefore(selectAllTh, thead.firstChild);

        // Add checkboxes to each row
        const rows = tbody.querySelectorAll('tr');
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const selectTd = document.createElement('td');
            selectTd.className = 'select-column';
            selectTd.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input row-select" type="checkbox" 
                           id="${table.id}-row-${i}" data-row-id="${i}">
                    <label class="form-check-label sr-only" for="${table.id}-row-${i}">Select row</label>
                </div>
            `;
            row.insertBefore(selectTd, row.firstChild);
        }

        // Handle select all
        const selectAllCheckbox = document.getElementById(`${table.id}-select-all`);
        const rowCheckboxes = tbody.querySelectorAll('.row-select');

        selectAllCheckbox.addEventListener('change', () => {
            for (const checkbox of rowCheckboxes) {
                checkbox.checked = selectAllCheckbox.checked;
            }
            this.updateBulkActionBar(table);
        });

        // Handle individual row selection
        for (const checkbox of rowCheckboxes) {
            checkbox.addEventListener('change', () => {
                const checkedCount = tbody.querySelectorAll('.row-select:checked').length;
                selectAllCheckbox.checked = checkedCount === rowCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
                this.updateBulkActionBar(table);
            });
        }

        // Create bulk action bar
        this.createBulkActionBar(table);
    }

    /**
     * Create bulk action bar
     */
    createBulkActionBar(table) {
        const actionBar = document.createElement('div');
        actionBar.className = 'bulk-action-bar d-none alert alert-info d-flex justify-content-between align-items-center';
        actionBar.id = `${table.id}-bulk-actions`;
        actionBar.innerHTML = `
            <div>
                <span class="selected-count">0</span> items selected
            </div>
            <div class="bulk-actions">
                <button type="button" class="btn btn-sm btn-outline-danger bulk-delete">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary bulk-clear">
                    Clear Selection
                </button>
            </div>
        `;

        table.parentElement.insertBefore(actionBar, table);

        // Handle bulk actions
        const deleteBtn = actionBar.querySelector('.bulk-delete');
        const clearBtn = actionBar.querySelector('.bulk-clear');

        deleteBtn.addEventListener('click', () => {
            this.handleBulkDelete(table);
        });

        clearBtn.addEventListener('click', () => {
            this.clearSelection(table);
        });
    }

    /**
     * Update bulk action bar
     */
    updateBulkActionBar(table) {
        const actionBar = document.getElementById(`${table.id}-bulk-actions`);
        const selectedCheckboxes = table.querySelectorAll('.row-select:checked');
        const count = selectedCheckboxes.length;

        if (count > 0) {
            actionBar.classList.remove('d-none');
            actionBar.querySelector('.selected-count').textContent = count;
        } else {
            actionBar.classList.add('d-none');
        }
    }

    /**
     * Handle bulk delete
     */
    async handleBulkDelete(table) {
        const selectedCheckboxes = table.querySelectorAll('.row-select:checked');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.dataset.rowId);

        const confirmed = await window.AdminPanel.ui.showConfirm(
            'Confirm Delete',
            `Are you sure you want to delete ${selectedIds.length} selected items?`,
            {
                confirmText: 'Delete',
                confirmClass: 'btn-danger'
            }
        );

        if (confirmed) {
            // Emit custom event for deletion
            const deleteEvent = new CustomEvent('bulkDelete', {
                detail: { table, selectedIds }
            });
            table.dispatchEvent(deleteEvent);
        }
    }

    /**
     * Clear selection
     */
    clearSelection(table) {
        const checkboxes = table.querySelectorAll('input[type="checkbox"]');
        for (const checkbox of checkboxes) {
            checkbox.checked = false;
            checkbox.indeterminate = false;
        }
        this.updateBulkActionBar(table);
    }

    /**
     * Setup empty state
     */
    setupEmptyState(table, message) {
        const tbody = table.querySelector('tbody');
        const colCount = table.querySelectorAll('thead th').length;
        
        const emptyRow = document.createElement('tr');
        emptyRow.className = 'empty-state-row d-none';
        emptyRow.innerHTML = `
            <td colspan="${colCount}" class="text-center py-4 text-muted">
                <div class="empty-state">
                    <i class="fas fa-inbox empty-state-icon"></i>
                    <div>${message}</div>
                </div>
            </td>
        `;
        
        tbody.appendChild(emptyRow);
    }

    /**
     * Update empty state visibility
     */
    updateEmptyState(table, isEmpty) {
        const emptyRow = table.querySelector('.empty-state-row');
        if (emptyRow) {
            emptyRow.classList.toggle('d-none', !isEmpty);
        }
    }

    /**
     * Setup responsive table wrapper
     */
    setupResponsiveTable(table) {
        if (!table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentElement.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    }

    /**
     * Add row to table
     */
    addRow(tableId, rowData) {
        const tableInfo = this.tables.get(tableId);
        if (!tableInfo) return;

        const table = tableInfo.element;
        const tbody = table.querySelector('tbody');
        const row = document.createElement('tr');
        
        // If bulk selection is enabled, add checkbox
        if (tableInfo.config.bulkSelect) {
            const selectCell = document.createElement('td');
            selectCell.className = 'select-column';
            const rowIndex = tbody.children.length;
            selectCell.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input row-select" type="checkbox" 
                           id="${tableId}-row-${rowIndex}" data-row-id="${rowIndex}">
                    <label class="form-check-label sr-only" for="${tableId}-row-${rowIndex}">Select row</label>
                </div>
            `;
            row.appendChild(selectCell);
        }

        // Add data cells
        for (const cellData of rowData) {
            const cell = document.createElement('td');
            cell.textContent = cellData;
            row.appendChild(cell);
        }

        tbody.appendChild(row);
        this.updateEmptyState(table, false);
    }

    /**
     * Remove row from table
     */
    removeRow(tableId, rowIndex) {
        const tableInfo = this.tables.get(tableId);
        if (!tableInfo) return;

        const table = tableInfo.element;
        const tbody = table.querySelector('tbody');
        const row = tbody.children[rowIndex];
        
        if (row && !row.classList.contains('empty-state-row')) {
            row.remove();
            
            // Check if table is now empty
            const visibleRows = Array.from(tbody.children).filter(r => 
                !r.classList.contains('empty-state-row') && 
                r.style.display !== 'none'
            );
            
            this.updateEmptyState(table, visibleRows.length === 0);
        }
    }

    /**
     * Get selected rows
     */
    getSelectedRows(tableId) {
        const tableInfo = this.tables.get(tableId);
        if (!tableInfo) return [];

        const table = tableInfo.element;
        const selectedCheckboxes = table.querySelectorAll('.row-select:checked');
        return Array.from(selectedCheckboxes).map(cb => cb.dataset.rowId);
    }

    /**
     * Refresh table (for AJAX updates)
     */
    async refreshTable(tableId, endpoint) {
        const tableInfo = this.tables.get(tableId);
        if (!tableInfo) return;

        const table = tableInfo.element;
        const tbody = table.querySelector('tbody');
        
        try {
            const response = await window.AdminPanel.net.get(endpoint);
            
            // Clear existing rows (except empty state)
            const rows = tbody.querySelectorAll('tr:not(.empty-state-row)');
            for (const row of rows) {
                row.remove();
            }

            // Add new rows
            if (response.data && response.data.length > 0) {
                for (const rowData of response.data) {
                    this.addRow(tableId, rowData);
                }
            }

            this.updateEmptyState(table, response.data?.length === 0);

        } catch (error) {
            window.AdminPanel.ui.showError(`Failed to refresh table: ${error.message}`);
        }
    }
}

export default new TablesHelper();
