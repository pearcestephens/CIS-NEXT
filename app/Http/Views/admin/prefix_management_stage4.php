<?php
declare(strict_types=1);

/**
 * Database Prefix Management Page - Stage 4 Refactored
 * File: app/Http/Views/admin/prefix_management_stage4.php
 * Purpose: Standardized UI using card.php, table.php, alert.php, modal.php components
 * Uses: PrefixModel via AdminDAL for all data operations
 */

// Include required components
require_once __DIR__ . '/components/card.php';
require_once __DIR__ . '/components/table.php';
require_once __DIR__ . '/components/alert.php';
require_once __DIR__ . '/components/modal.php';

// Include models and DAL
require_once __DIR__ . '/../../../Models/PrefixModel.php';
require_once __DIR__ . '/../../../Models/AdminDAL.php';

use App\Models\PrefixModel;
use App\Models\AdminDAL;

// Initialize DAL and models
$dal = new AdminDAL();
$prefixModel = new PrefixModel();

// Page configuration
$title = 'Database Prefix Management';
$page_title = 'Database Prefix Management';
$page_description = 'Audit and manage database table prefixes across the CIS system';
$page_icon = 'fas fa-tags';
$page_header = true;

// RBAC check for page access
if (!$dal->checkPermission('admin.prefix_management')) {
    http_response_code(403);
    die('Access denied. Insufficient permissions.');
}

// Get prefix analysis data
try {
    $prefix_data = $prefixModel->getPrefixAnalysis();
} catch (Exception $e) {
    error_log("Prefix Management Error: " . $e->getMessage());
    $prefix_data = [
        'database_name' => 'unknown',
        'current_prefix' => '',
        'table_count' => 0,
        'tables' => [],
        'stats' => [],
        'recommendations' => []
    ];
}

// Page actions with RBAC
$page_actions = '';
if ($dal->checkPermission('prefix.refresh')) {
    $page_actions .= '<button type="button" class="btn btn-outline-primary" onclick="refreshAudit()">
        <i class="fas fa-sync-alt"></i> Refresh Audit
    </button>';
}

// Additional head content for custom styles
$additional_head = '
<style>
    .prefix-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 0.25rem;
        margin-right: 0.5rem;
    }
    .prefix-cis { background-color: #28a745; color: white; }
    .prefix-vend { background-color: #007bff; color: white; }
    .prefix-cam { background-color: #6f42c1; color: white; }
    .prefix-xero { background-color: #fd7e14; color: white; }
    .prefix-other { background-color: #6c757d; color: white; }
    .prefix-none { background-color: #dc3545; color: white; }
    
    .audit-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
</style>
<script>
// Global variables for AJAX operations
let csrfToken = "' . ($_SESSION['csrf_token'] ?? '') . '";
let currentAuditData = ' . json_encode($prefix_data) . ';

// Initialize page
document.addEventListener("DOMContentLoaded", function() {
    initializePrefixManagement();
});

function initializePrefixManagement() {
    // Set up event listeners
    setupEventListeners();
    
    // Load initial data display
    updateAuditDisplay();
}

function setupEventListeners() {
    // Listen for component events
    document.addEventListener("admin-table-action", handleTableAction);
    document.addEventListener("admin-modal-submit", handleModalSubmit);
    
    // Custom action handlers
    window.refreshAudit = refreshAudit;
    window.previewRename = previewRename;
    window.executeRename = executeRename;
}

async function refreshAudit() {
    AdminAlert.show("Refreshing audit data...", "info", 0);
    
    try {
        const response = await fetch("/app/Http/Controllers/Admin/PrefixManagementController.php?action=apiAudit");
        const result = await response.json();
        
        if (result.success) {
            currentAuditData = result.data;
            updateAuditDisplay();
            AdminAlert.show("Audit data refreshed successfully", "success");
        } else {
            AdminAlert.show("Failed to refresh audit: " + (result.error || "Unknown error"), "danger");
        }
    } catch (error) {
        AdminAlert.show("Network error during refresh: " + error.message, "danger");
    }
}

function updateAuditDisplay() {
    // Update summary cards
    updateSummaryCards();
    
    // Reload table data
    const tableElement = document.getElementById("prefixTable");
    if (tableElement) {
        // Trigger table refresh with new data
        tableElement.dispatchEvent(new CustomEvent("admin-table-refresh", {
            detail: { data: currentAuditData.tables }
        }));
    }
}

function updateSummaryCards() {
    const stats = currentAuditData.stats || {};
    
    document.getElementById("totalTables").textContent = currentAuditData.table_count || 0;
    document.getElementById("currentPrefix").textContent = currentAuditData.current_prefix || "None";
    document.getElementById("prefixedTables").textContent = stats.prefixed_count || 0;
    document.getElementById("unprefixedTables").textContent = stats.unprefixed_count || 0;
}

async function previewRename(tableName) {
    const newPrefix = prompt("Enter new prefix (leave empty to remove prefix):", currentAuditData.current_prefix || "");
    
    if (newPrefix === null) return; // User cancelled
    
    try {
        const response = await fetch("/app/Http/Controllers/Admin/PrefixManagementController.php?action=previewRename", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
                current_table: tableName,
                new_prefix: newPrefix,
                csrf_token: csrfToken
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show preview in modal
            AdminModal.show("Rename Preview", result.data.preview_html, {
                size: "large",
                actions: [{
                    text: "Execute Rename",
                    class: "btn-primary",
                    onclick: () => executeRename(tableName, newPrefix)
                }]
            });
        } else {
            AdminAlert.show("Preview failed: " + (result.error || "Unknown error"), "danger");
        }
    } catch (error) {
        AdminAlert.show("Network error during preview: " + error.message, "danger");
    }
}

async function executeRename(tableName, newPrefix) {
    if (!confirm("Are you sure you want to execute this rename operation?")) {
        return;
    }
    
    AdminAlert.show("Executing rename operation...", "info", 0);
    
    try {
        const response = await fetch("/app/Http/Controllers/Admin/PrefixManagementController.php?action=apiRename", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
                current_table: tableName,
                new_prefix: newPrefix,
                dry_run: false,
                csrf_token: csrfToken
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            AdminAlert.show("Rename operation completed successfully", "success");
            AdminModal.hide();
            refreshAudit(); // Refresh data
        } else {
            AdminAlert.show("Rename failed: " + (result.error || "Unknown error"), "danger");
        }
    } catch (error) {
        AdminAlert.show("Network error during rename: " + error.message, "danger");
    }
}

function handleTableAction(event) {
    const { action, rowData } = event.detail;
    
    switch (action) {
        case "rename":
            previewRename(rowData.table_name);
            break;
        case "view_stats":
            showTableStats(rowData);
            break;
        default:
            console.log("Unknown table action:", action);
    }
}

function handleModalSubmit(event) {
    const { modalId, formData } = event.detail;
    
    if (modalId === "bulkRenameModal") {
        executeBulkRename(formData);
    }
}

function showTableStats(tableData) {
    const statsHtml = `
        <div class="table-responsive">
            <table class="table table-sm">
                <tr><th>Table Name</th><td>${tableData.table_name}</td></tr>
                <tr><th>Current Prefix</th><td>${tableData.prefix || "None"}</td></tr>
                <tr><th>Row Count</th><td>${tableData.row_count || 0}</td></tr>
                <tr><th>Size (MB)</th><td>${tableData.size_mb || 0}</td></tr>
                <tr><th>Engine</th><td>${tableData.engine || "Unknown"}</td></tr>
                <tr><th>Type</th><td>${tableData.type || "Unknown"}</td></tr>
            </table>
        </div>
    `;
    
    AdminModal.show(`Table Statistics: ${tableData.table_name}`, statsHtml, { size: "medium" });
}

async function executeBulkRename(formData) {
    AdminAlert.show("Processing bulk rename operation...", "info", 0);
    
    try {
        const response = await fetch("/app/Http/Controllers/Admin/PrefixManagementController.php?action=bulkRename", {
            method: "POST",
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            AdminAlert.show("Bulk rename completed successfully", "success");
            AdminModal.hide();
            refreshAudit();
        } else {
            AdminAlert.show("Bulk rename failed: " + (result.error || "Unknown error"), "danger");
        }
    } catch (error) {
        AdminAlert.show("Network error during bulk rename: " + error.message, "danger");
    }
}
</script>
';

// Start content capture
ob_start();
?>

<!-- Audit Summary Cards -->
<div class="audit-summary">
    <?php
    echo AdminCard::create('Total Tables', [
        'icon' => 'fas fa-table',
        'content' => '<div class="display-4 mb-0" id="totalTables">' . ($prefix_data['table_count'] ?? 0) . '</div>',
        'variant' => 'primary',
        'size' => 'sm'
    ]);
    
    echo AdminCard::create('Current Prefix', [
        'icon' => 'fas fa-tag',
        'content' => '<div class="display-6 mb-0" id="currentPrefix">' . ($prefix_data['current_prefix'] ?: 'None') . '</div>',
        'variant' => 'info',
        'size' => 'sm'
    ]);
    
    $stats = $prefix_data['stats'] ?? [];
    echo AdminCard::create('Prefixed Tables', [
        'icon' => 'fas fa-check-circle',
        'content' => '<div class="display-4 mb-0" id="prefixedTables">' . ($stats['prefixed_count'] ?? 0) . '</div>',
        'variant' => 'success',
        'size' => 'sm'
    ]);
    
    echo AdminCard::create('Unprefixed Tables', [
        'icon' => 'fas fa-exclamation-triangle',
        'content' => '<div class="display-4 mb-0" id="unprefixedTables">' . ($stats['unprefixed_count'] ?? 0) . '</div>',
        'variant' => 'warning',
        'size' => 'sm'
    ]);
    ?>
</div>

<!-- Alert Container -->
<div id="alertContainer"></div>

<!-- Main Content Grid -->
<div class="stats-grid">
    <!-- Database Tables Card -->
    <?php
    // Prepare table data
    $table_data = [];
    foreach ($prefix_data['tables'] ?? [] as $table) {
        $prefix_class = '';
        $prefix_text = $table['prefix'] ?? 'none';
        
        switch ($prefix_text) {
            case (str_starts_with($prefix_text, 'cis')):
                $prefix_class = 'prefix-cis';
                break;
            case (str_starts_with($prefix_text, 'vend')):
                $prefix_class = 'prefix-vend';
                break;
            case (str_starts_with($prefix_text, 'cam')):
                $prefix_class = 'prefix-cam';
                break;
            case (str_starts_with($prefix_text, 'xero')):
                $prefix_class = 'prefix-xero';
                break;
            case 'none':
                $prefix_class = 'prefix-none';
                break;
            default:
                $prefix_class = 'prefix-other';
        }
        
        $table_data[] = [
            'table_name' => $table['table_name'],
            'prefix' => $prefix_text !== 'none' 
                ? '<span class="prefix-badge ' . $prefix_class . '">' . htmlspecialchars($prefix_text) . '</span>'
                : '<span class="prefix-badge prefix-none">No Prefix</span>',
            'row_count' => number_format((int)($table['row_count'] ?? 0)),
            'size_mb' => number_format((float)($table['size_mb'] ?? 0), 2),
            'engine' => $table['engine'] ?? 'Unknown',
            'type' => ucfirst($table['type'] ?? 'unknown'),
            'actions' => ''
        ];
    }
    
    // Define table columns
    $table_columns = [
        ['key' => 'table_name', 'label' => 'Table Name', 'sortable' => true],
        ['key' => 'prefix', 'label' => 'Prefix', 'sortable' => true, 'type' => 'html'],
        ['key' => 'row_count', 'label' => 'Rows', 'sortable' => true, 'align' => 'right'],
        ['key' => 'size_mb', 'label' => 'Size (MB)', 'sortable' => true, 'align' => 'right'],
        ['key' => 'engine', 'label' => 'Engine', 'sortable' => true],
        ['key' => 'type', 'label' => 'Type', 'sortable' => true],
        ['key' => 'actions', 'label' => 'Actions', 'type' => 'actions']
    ];
    
    // Define table actions based on permissions
    $table_actions = [];
    if ($dal->checkPermission('prefix.rename')) {
        $table_actions[] = [
            'label' => 'Rename',
            'action' => 'rename',
            'icon' => 'fas fa-edit',
            'class' => 'btn-outline-primary btn-sm'
        ];
    }
    
    $table_actions[] = [
        'label' => 'Stats',
        'action' => 'view_stats', 
        'icon' => 'fas fa-chart-bar',
        'class' => 'btn-outline-info btn-sm'
    ];
    
    echo AdminCard::create('Database Tables', [
        'content' => AdminTable::create($table_data, [
            'columns' => $table_columns,
            'actions' => $table_actions,
            'searchable' => true,
            'paginated' => true,
            'per_page' => 25,
            'sortable' => true,
            'id' => 'prefixTable',
            'responsive' => true,
            'striped' => true,
            'hover' => true
        ]),
        'collapsible' => true,
        'collapsed' => false
    ]);
    ?>

    <!-- Recommendations Card -->
    <?php
    $recommendations_html = '';
    if (!empty($prefix_data['recommendations'])) {
        $recommendations_html .= '<div class="list-group list-group-flush">';
        foreach ($prefix_data['recommendations'] as $recommendation) {
            $icon_class = '';
            $badge_class = '';
            
            switch ($recommendation['priority'] ?? 'medium') {
                case 'high':
                    $icon_class = 'text-danger';
                    $badge_class = 'badge-danger';
                    break;
                case 'medium':
                    $icon_class = 'text-warning';
                    $badge_class = 'badge-warning';
                    break;
                case 'low':
                    $icon_class = 'text-info';
                    $badge_class = 'badge-info';
                    break;
            }
            
            $recommendations_html .= '
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <i class="fas fa-lightbulb ' . $icon_class . ' me-2"></i>
                            <strong>' . htmlspecialchars($recommendation['title'] ?? '') . '</strong>
                            <p class="mb-1 mt-2">' . htmlspecialchars($recommendation['description'] ?? '') . '</p>
                        </div>
                        <span class="badge ' . $badge_class . '">' . ucfirst($recommendation['priority'] ?? 'medium') . '</span>
                    </div>
                </div>';
        }
        $recommendations_html .= '</div>';
    } else {
        $recommendations_html = '<div class="text-center text-muted py-4">
            <i class="fas fa-check-circle fa-3x mb-3"></i>
            <p>No recommendations at this time. Your prefix configuration looks good!</p>
        </div>';
    }
    
    echo AdminCard::create('System Recommendations', [
        'content' => $recommendations_html,
        'icon' => 'fas fa-lightbulb',
        'variant' => 'light'
    ]);
    ?>
</div>

<!-- Bulk Operations Card -->
<?php if ($dal->checkPermission('prefix.bulk_manage')): ?>
<div class="mb-4">
    <?php
    $bulk_operations_html = '
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-cogs me-2"></i>Bulk Operations</h6>
                <p class="text-muted mb-3">Apply prefix changes to multiple tables at once.</p>
                
                <div class="mb-3">
                    <label class="form-label">Target Prefix</label>
                    <input type="text" class="form-control" id="bulkTargetPrefix" 
                           placeholder="Enter new prefix (e.g., cis_)" 
                           value="' . htmlspecialchars($prefix_data['current_prefix'] ?? '') . '">
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="bulkDryRun" checked>
                        <label class="form-check-label" for="bulkDryRun">
                            Dry Run (Preview Changes Only)
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <h6><i class="fas fa-filter me-2"></i>Table Selection</h6>
                <p class="text-muted mb-3">Choose which tables to include in the operation.</p>
                
                <div class="mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllTables">
                        <label class="form-check-label" for="selectAllTables">
                            Select All Tables
                        </label>
                    </div>
                </div>
                
                <div class="mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="skipSystemTables" checked>
                        <label class="form-check-label" for="skipSystemTables">
                            Skip System Tables
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <button type="button" class="btn btn-primary" onclick="showBulkRenameModal()">
                <i class="fas fa-play me-2"></i>Preview Bulk Changes
            </button>
            
            <button type="button" class="btn btn-outline-secondary ms-2" onclick="exportPrefixReport()">
                <i class="fas fa-download me-2"></i>Export Report
            </button>
        </div>
    ';
    
    echo AdminCard::create('Bulk Operations', [
        'content' => $bulk_operations_html,
        'icon' => 'fas fa-cogs',
        'collapsible' => true,
        'collapsed' => true
    ]);
    ?>
</div>

<script>
function showBulkRenameModal() {
    const targetPrefix = document.getElementById('bulkTargetPrefix').value;
    const dryRun = document.getElementById('bulkDryRun').checked;
    
    if (!targetPrefix.trim()) {
        AdminAlert.show('Please enter a target prefix', 'warning');
        return;
    }
    
    const modalContent = `
        <form id="bulkRenameForm">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <input type="hidden" name="target_prefix" value="${targetPrefix}">
            <input type="hidden" name="dry_run" value="${dryRun ? '1' : '0'}">
            
            <div class="mb-3">
                <h6>Bulk Rename Configuration</h6>
                <table class="table table-sm">
                    <tr><th>Target Prefix:</th><td>${targetPrefix}</td></tr>
                    <tr><th>Mode:</th><td>${dryRun ? 'Preview Only (Dry Run)' : 'Execute Changes'}</td></tr>
                    <tr><th>Estimated Tables:</th><td>${currentAuditData.table_count || 0}</td></tr>
                </table>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                This operation will ${dryRun ? 'preview' : 'execute'} prefix changes for selected tables.
            </div>
        </form>
    `;
    
    AdminModal.show('Bulk Prefix Rename', modalContent, {
        size: 'medium',
        form: 'bulkRenameForm',
        actions: [{
            text: dryRun ? 'Generate Preview' : 'Execute Changes',
            class: dryRun ? 'btn-outline-primary' : 'btn-danger',
            type: 'submit'
        }]
    });
}

function exportPrefixReport() {
    AdminAlert.show('Generating prefix report...', 'info', 3000);
    
    // Create downloadable report
    const reportData = {
        timestamp: new Date().toISOString(),
        database: currentAuditData.database_name,
        current_prefix: currentAuditData.current_prefix,
        table_count: currentAuditData.table_count,
        tables: currentAuditData.tables,
        recommendations: currentAuditData.recommendations
    };
    
    const blob = new Blob([JSON.stringify(reportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = `prefix_audit_${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    AdminAlert.show('Report exported successfully', 'success');
}
</script>
<?php endif; ?>

<?php
// Get the content and clean buffer
$content = ob_get_clean();

// Include the admin layout
require_once __DIR__ . '/layout.php';
?>
