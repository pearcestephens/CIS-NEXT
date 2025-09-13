/**
 * CIS Backup System - JavaScript Module
 * 
 * @description Interactive backup management functionality
 * @version 1.0.0
 * @author CIS Development Team
 */

class BackupDashboard {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initCharts();
        this.startStatusUpdates();
    }

    bindEvents() {
        // Refresh stats button
        document.getElementById('refreshStats')?.addEventListener('click', () => {
            this.refreshStatistics();
        });

        // Auto-refresh toggle
        const autoRefreshToggle = document.getElementById('autoRefresh');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.startStatusUpdates();
                } else {
                    this.stopStatusUpdates();
                }
            });
        }
    }

    async refreshStatistics() {
        const button = document.getElementById('refreshStats');
        const originalText = button.innerHTML;
        
        try {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            button.disabled = true;

            const response = await fetch('/admin/backup/api/statistics', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch statistics');
            }

            const data = await response.json();
            
            if (data.success) {
                this.updateStatisticsDisplay(data.data);
                this.showNotification('Statistics refreshed successfully', 'success');
            } else {
                throw new Error(data.error?.message || 'Unknown error');
            }

        } catch (error) {
            console.error('Error refreshing statistics:', error);
            this.showNotification('Failed to refresh statistics', 'error');
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }

    updateStatisticsDisplay(statistics) {
        // Update overview cards
        const overview = statistics.overview || {};
        
        this.updateElement('.total-backups', overview.total_backups || 0);
        this.updateElement('.completed-backups', overview.completed_backups || 0);
        this.updateElement('.failed-backups', overview.failed_backups || 0);
        this.updateElement('.total-size', this.formatBytes(overview.total_size || 0));

        // Update storage usage
        const storage = statistics.storage_usage || {};
        const usagePercent = storage.usage_percent || 0;
        
        const progressBar = document.querySelector('.storage-progress .progress-bar');
        if (progressBar) {
            progressBar.style.width = usagePercent + '%';
            progressBar.className = `progress-bar ${this.getStorageProgressColor(usagePercent)}`;
        }

        this.updateElement('.backup-size', this.formatBytes(storage.backup_size || 0));
        this.updateElement('.free-space', this.formatBytes(storage.free_space || 0));
        this.updateElement('.usage-percent', usagePercent.toFixed(1) + '%');

        // Update charts
        this.updateCharts(statistics);
    }

    initCharts() {
        this.initBackupTypesChart();
    }

    initBackupTypesChart() {
        const ctx = document.getElementById('backupTypesChart');
        if (!ctx) return;

        this.backupTypesChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Full', 'Database', 'Files', 'Incremental'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: [
                        '#007bff', // Primary
                        '#17a2b8', // Info
                        '#6c757d', // Secondary
                        '#ffc107'  // Warning
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    updateCharts(statistics) {
        // Update backup types chart
        if (this.backupTypesChart && statistics.backup_types) {
            const types = statistics.backup_types;
            this.backupTypesChart.data.datasets[0].data = [
                types.full || 0,
                types.database || 0,
                types.files || 0,
                types.incremental || 0
            ];
            this.backupTypesChart.update();
        }
    }

    startStatusUpdates() {
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
        }

        this.statusInterval = setInterval(() => {
            this.checkRunningBackups();
        }, 5000); // Check every 5 seconds
    }

    stopStatusUpdates() {
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
            this.statusInterval = null;
        }
    }

    async checkRunningBackups() {
        try {
            const response = await fetch('/admin/backup/api/status', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) return;

            const data = await response.json();
            
            if (data.success && data.data.running_backups) {
                this.updateRunningBackups(data.data.running_backups);
            }

        } catch (error) {
            console.error('Error checking backup status:', error);
        }
    }

    updateRunningBackups(runningBackups) {
        runningBackups.forEach(backup => {
            const row = document.querySelector(`[data-backup-id="${backup.backup_id}"]`);
            if (row) {
                // Update progress if available
                const progressCell = row.querySelector('.progress-percent');
                if (progressCell && backup.progress_percent !== undefined) {
                    progressCell.textContent = backup.progress_percent + '%';
                }

                // Update status
                const statusBadge = row.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.textContent = backup.status;
                    statusBadge.className = `badge bg-${this.getStatusColor(backup.status)}`;
                }
            }
        });
    }

    formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let size = bytes;
        let unitIndex = 0;
        
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        
        return size.toFixed(2) + ' ' + units[unitIndex];
    }

    getStorageProgressColor(percent) {
        if (percent > 85) return 'bg-danger';
        if (percent > 70) return 'bg-warning';
        return 'bg-success';
    }

    getStatusColor(status) {
        const colors = {
            'completed': 'success',
            'running': 'warning',
            'pending': 'info',
            'failed': 'danger',
            'cancelled': 'secondary'
        };
        return colors[status] || 'light';
    }

    updateElement(selector, value) {
        const element = document.querySelector(selector);
        if (element) {
            if (typeof value === 'number') {
                element.textContent = value.toLocaleString();
            } else {
                element.textContent = value;
            }
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    destroy() {
        this.stopStatusUpdates();
        
        if (this.backupTypesChart) {
            this.backupTypesChart.destroy();
        }
    }
}

class BackupList {
    constructor() {
        this.selectedBackups = new Set();
        this.init();
    }

    init() {
        this.bindEvents();
        this.initBulkActions();
    }

    bindEvents() {
        // Refresh list button
        document.getElementById('refreshList')?.addEventListener('click', () => {
            this.refreshList();
        });

        // Select all checkbox
        document.getElementById('selectAllCheckbox')?.addEventListener('change', (e) => {
            this.toggleSelectAll(e.target.checked);
        });

        // Individual backup checkboxes
        document.querySelectorAll('.backup-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.toggleBackupSelection(e.target.value, e.target.checked);
            });
        });

        // Delete buttons
        document.querySelectorAll('.delete-backup').forEach(button => {
            button.addEventListener('click', (e) => {
                const backupId = e.target.closest('[data-backup-id]').dataset.backupId;
                const backupName = e.target.closest('[data-backup-name]').dataset.backupName;
                this.showDeleteModal(backupId, backupName);
            });
        });

        // Filter form auto-submit
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            const inputs = filterForm.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    if (input.type !== 'text') {
                        filterForm.submit();
                    }
                });
            });

            // Search input with debounce
            const searchInput = document.getElementById('search');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filterForm.submit();
                    }, 500);
                });
            }
        }
    }

    initBulkActions() {
        const selectAllBtn = document.getElementById('selectAll');
        const clearSelectionBtn = document.getElementById('clearSelection');
        const bulkActionSelect = document.getElementById('bulkActionSelect');
        const executeBulkBtn = document.getElementById('executeBulkAction');

        selectAllBtn?.addEventListener('click', () => {
            this.toggleSelectAll(true);
        });

        clearSelectionBtn?.addEventListener('click', () => {
            this.toggleSelectAll(false);
        });

        executeBulkBtn?.addEventListener('click', () => {
            const action = bulkActionSelect.value;
            if (action && this.selectedBackups.size > 0) {
                this.executeBulkAction(action);
            }
        });
    }

    toggleSelectAll(select) {
        const checkboxes = document.querySelectorAll('.backup-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = select;
            this.toggleBackupSelection(checkbox.value, select);
        });

        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = select;
        }
    }

    toggleBackupSelection(backupId, selected) {
        if (selected) {
            this.selectedBackups.add(backupId);
        } else {
            this.selectedBackups.delete(backupId);
        }

        this.updateBulkActionVisibility();
        this.updateSelectAllCheckbox();
    }

    updateBulkActionVisibility() {
        const bulkActions = document.getElementById('bulkActions');
        if (bulkActions) {
            bulkActions.style.display = this.selectedBackups.size > 0 ? 'block' : 'none';
        }
    }

    updateSelectAllCheckbox() {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const allCheckboxes = document.querySelectorAll('.backup-checkbox');
        
        if (selectAllCheckbox && allCheckboxes.length > 0) {
            const checkedCount = document.querySelectorAll('.backup-checkbox:checked').length;
            selectAllCheckbox.checked = checkedCount === allCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
        }
    }

    async refreshList() {
        location.reload();
    }

    showDeleteModal(backupId, backupName) {
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        document.getElementById('deleteBackupName').textContent = backupName;
        
        const confirmBtn = document.getElementById('confirmDelete');
        confirmBtn.onclick = () => this.deleteBackup(backupId);
        
        modal.show();
    }

    async deleteBackup(backupId) {
        try {
            const response = await fetch(`/admin/backup/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    backup_id: backupId,
                    csrf_token: this.getCsrfToken()
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Backup deleted successfully', 'success');
                
                // Remove row from table
                const row = document.querySelector(`[data-backup-id="${backupId}"]`);
                if (row) {
                    row.remove();
                }
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                modal.hide();
            } else {
                throw new Error(data.error?.message || 'Delete failed');
            }

        } catch (error) {
            console.error('Error deleting backup:', error);
            this.showNotification('Failed to delete backup: ' + error.message, 'error');
        }
    }

    async executeBulkAction(action) {
        const backupIds = Array.from(this.selectedBackups);
        
        if (!confirm(`Are you sure you want to ${action} ${backupIds.length} selected backups?`)) {
            return;
        }

        try {
            const response = await fetch(`/admin/backup/bulk-action`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: action,
                    backup_ids: backupIds,
                    csrf_token: this.getCsrfToken()
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(`Bulk ${action} completed successfully`, 'success');
                
                if (action === 'delete') {
                    // Remove rows from table
                    backupIds.forEach(id => {
                        const row = document.querySelector(`[data-backup-id="${id}"]`);
                        if (row) row.remove();
                    });
                }
                
                // Clear selection
                this.toggleSelectAll(false);
            } else {
                throw new Error(data.error?.message || 'Bulk action failed');
            }

        } catch (error) {
            console.error('Error executing bulk action:', error);
            this.showNotification(`Failed to execute bulk ${action}: ` + error.message, 'error');
        }
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
}

class BackupCreate {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initTypeSelection();
        this.generateDefaultName();
    }

    bindEvents() {
        // Backup type selection
        document.querySelectorAll('.backup-type-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.type !== 'radio') {
                    const radio = card.querySelector('input[type="radio"]');
                    radio.checked = true;
                    this.updateTypeSelection();
                    this.updateAdvancedOptions();
                }
            });
        });

        document.querySelectorAll('input[name="type"]').forEach(radio => {
            radio.addEventListener('change', () => {
                this.updateTypeSelection();
                this.updateAdvancedOptions();
                this.updateTypeInfo();
            });
        });

        // Form submission
        document.getElementById('backupForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit(e);
        });

        // Advanced options toggle
        const advancedToggle = document.querySelector('[data-bs-target="#advancedOptions"]');
        if (advancedToggle) {
            advancedToggle.addEventListener('click', () => {
                const icon = advancedToggle.querySelector('i');
                setTimeout(() => {
                    if (document.getElementById('advancedOptions').classList.contains('show')) {
                        icon.className = 'fas fa-chevron-up';
                    } else {
                        icon.className = 'fas fa-chevron-down';
                    }
                }, 350);
            });
        }
    }

    initTypeSelection() {
        this.updateTypeSelection();
        this.updateTypeInfo();
        this.updateAdvancedOptions();
    }

    updateTypeSelection() {
        document.querySelectorAll('.backup-type-card').forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            if (radio.checked) {
                card.classList.add('border-primary', 'bg-light');
            } else {
                card.classList.remove('border-primary', 'bg-light');
            }
        });
    }

    updateTypeInfo() {
        const selectedType = document.querySelector('input[name="type"]:checked')?.value;
        
        document.querySelectorAll('#backupTypeDetails [data-type]').forEach(info => {
            info.style.display = info.dataset.type === selectedType ? 'block' : 'none';
        });
    }

    updateAdvancedOptions() {
        const selectedType = document.querySelector('input[name="type"]:checked')?.value;
        
        // Show/hide file exclusion options
        const fileExclusionsSection = document.getElementById('fileExclusionsSection');
        const customExclusionsSection = document.getElementById('customExclusionsSection');
        
        if (selectedType === 'files' || selectedType === 'full') {
            fileExclusionsSection.style.display = 'block';
            customExclusionsSection.style.display = 'block';
        } else {
            fileExclusionsSection.style.display = 'none';
            customExclusionsSection.style.display = 'none';
        }
    }

    generateDefaultName() {
        const nameInput = document.getElementById('name');
        if (nameInput && !nameInput.value) {
            const now = new Date();
            const timestamp = now.getFullYear() + 
                String(now.getMonth() + 1).padStart(2, '0') + 
                String(now.getDate()).padStart(2, '0') + '_' +
                String(now.getHours()).padStart(2, '0') + 
                String(now.getMinutes()).padStart(2, '0');
            
            nameInput.value = `CIS_Backup_${timestamp}`;
        }
    }

    async handleFormSubmit(event) {
        const form = event.target;
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]:focus') || 
                           form.querySelector('button[type="submit"]');
        
        const action = submitButton.value || 'create';
        const originalText = submitButton.innerHTML;
        
        try {
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitButton.disabled = true;

            // Add action to form data
            formData.set('action', action);

            const response = await fetch('/admin/backup/create', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                if (action === 'create') {
                    this.showNotification('Backup started successfully', 'success');
                    // Redirect to backup view page
                    window.location.href = `/admin/backup/view?id=${data.data.backup_id}`;
                } else if (action === 'schedule') {
                    this.showNotification('Backup scheduled successfully', 'success');
                    window.location.href = '/admin/backup/list';
                }
            } else {
                throw new Error(data.error?.message || 'Unknown error');
            }

        } catch (error) {
            console.error('Error creating backup:', error);
            this.showNotification('Failed to create backup: ' + error.message, 'error');
        } finally {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
}

// Initialize appropriate class based on current page
document.addEventListener('DOMContentLoaded', () => {
    const currentPath = window.location.pathname;
    
    if (currentPath.includes('/backup/dashboard')) {
        window.backupDashboard = new BackupDashboard();
    } else if (currentPath.includes('/backup/list')) {
        window.backupList = new BackupList();
    } else if (currentPath.includes('/backup/create')) {
        window.backupCreate = new BackupCreate();
    }
});

// Export classes for use in other modules
export { BackupDashboard, BackupList, BackupCreate };
