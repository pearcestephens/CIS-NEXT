<?php
declare(strict_types=1);

/**
 * Database Prefix Management Page - Stage 4 Refactored
 * File: app/Http/Views/admin/prefix_management.php
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

<!-- Audit Summary -->
<div class="audit-summary">
    <div class="audit-card">
        <div class="audit-number"><?= $audit_data['total_tables'] ?></div>
        <div class="audit-label">Total Tables</div>
    </div>
    <div class="audit-card">
        <div class="audit-number"><?= $audit_data['prefix_analysis']['prefix_count'] ?></div>
        <div class="audit-label">Prefixes Found</div>
    </div>
    <div class="audit-card">
        <div class="audit-number"><?= count($audit_data['prefix_analysis']['unprefixed_tables']) ?></div>
        <div class="audit-label">Unprefixed Tables</div>
    </div>
    <div class="audit-card">
        <div class="audit-number"><?= $audit_data['execution_time_ms'] ?>ms</div>
        <div class="audit-label">Audit Time</div>
    </div>
</div>

<!-- Dry Run Notice -->
<div class="dry-run-notice">
    <i class="fas fa-info-circle"></i>
    <strong>Dry Run Mode:</strong> All operations are performed in dry-run mode by default. 
    This allows you to preview changes before executing them. Uncheck "Dry Run" to execute actual changes.
</div>

<div class="row">
                
                <!-- Audit Summary -->
                <div class="audit-summary">
                    <div class="audit-card">
                        <div class="audit-number"><?= $audit_data['total_tables'] ?></div>
                        <div class="audit-label">Total Tables</div>
                    </div>
                    <div class="audit-card">
                        <div class="audit-number"><?= $audit_data['prefix_analysis']['prefix_count'] ?></div>
                        <div class="audit-label">Prefixes Found</div>
                    </div>
                    <div class="audit-card">
                        <div class="audit-number"><?= count($audit_data['prefix_analysis']['unprefixed_tables']) ?></div>
                        <div class="audit-label">Unprefixed Tables</div>
                    </div>
                    <div class="audit-card">
                        <div class="audit-number"><?= $audit_data['execution_time_ms'] ?>ms</div>
                        <div class="audit-label">Audit Time</div>
                    </div>
                </div>
                
                <!-- Dry Run Notice -->
                <div class="dry-run-notice">
                    <i class="fas fa-info-circle"></i>
                    <strong>Dry Run Mode:</strong> All operations are performed in dry-run mode by default. 
                    This allows you to preview changes before executing them. Uncheck "Dry Run" to execute actual changes.
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Current Prefix Analysis -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> Current Prefix Analysis</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($audit_data['prefix_analysis']['prefixed_tables'] as $prefix => $tables): ?>
                            <div class="mb-3">
                                <h6>
                                    <span class="prefix-badge prefix-<?= in_array($prefix, ['cis', 'vend', 'cam', 'xero']) ? $prefix : 'other' ?>">
                                        <?= htmlspecialchars($prefix) ?>_
                                    </span>
                                    (<?= count($tables) ?> tables)
                                </h6>
                                <div class="table-list">
                                    <?php foreach ($tables as $table): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-monospace"><?= htmlspecialchars($table) ?></span>
                                            <div class="table-actions">
                                                <button class="btn btn-sm btn-outline-warning" onclick="renameTable('<?= htmlspecialchars($table) ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="dropTable('<?= htmlspecialchars($table) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($audit_data['prefix_analysis']['unprefixed_tables'])): ?>
                            <div class="mb-3">
                                <h6>
                                    <span class="prefix-badge prefix-none">NO PREFIX</span>
                                    (<?= count($audit_data['prefix_analysis']['unprefixed_tables']) ?> tables)
                                </h6>
                                <div class="table-list">
                                    <?php foreach ($audit_data['prefix_analysis']['unprefixed_tables'] as $table): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-monospace"><?= htmlspecialchars($table) ?></span>
                                            <div class="table-actions">
                                                <button class="btn btn-sm btn-outline-warning" onclick="renameTable('<?= htmlspecialchars($table) ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="dropTable('<?= htmlspecialchars($table) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recommendations -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb"></i> Recommendations</h5>
                    </div>
                    <div class="card-body">
                        <?php $recommendations = $audit_data['prefix_analysis']['recommendations']; ?>
                        
                        <!-- Keep Tables -->
                        <?php if (!empty($recommendations['keep'])): ?>
                            <div class="recommendation-section">
                                <h6 class="text-success">
                                    <i class="fas fa-check-circle"></i> Keep As-Is (<?= count($recommendations['keep']) ?> tables)
                                </h6>
                                <small class="text-muted">These tables have correct prefixes and should remain unchanged.</small>
                                <div class="table-list">
                                    <?php foreach ($recommendations['keep'] as $table): ?>
                                        <div class="font-monospace text-success"><?= htmlspecialchars($table) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Rename to CIS -->
                        <?php if (!empty($recommendations['rename_to_cis'])): ?>
                            <div class="recommendation-section">
                                <h6 class="text-warning">
                                    <i class="fas fa-arrow-right"></i> Rename to CIS Prefix (<?= count($recommendations['rename_to_cis']) ?> tables)
                                    <button class="btn btn-sm btn-outline-warning ml-2" onclick="executeRecommendedRenames()">
                                        Execute All Renames
                                    </button>
                                </h6>
                                <small class="text-muted">These tables should be renamed with the 'cis_' prefix.</small>
                                <div class="table-list">
                                    <?php foreach ($recommendations['rename_to_cis'] as $rename): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <span class="font-monospace"><?= htmlspecialchars($rename['current']) ?></span>
                                                <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                <span class="font-monospace text-success"><?= htmlspecialchars($rename['target']) ?></span>
                                            </div>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="renameTable('<?= htmlspecialchars($rename['current']) ?>', '<?= htmlspecialchars($rename['target']) ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Drop Framework Tables -->
                        <?php if (!empty($recommendations['drop_framework'])): ?>
                            <div class="recommendation-section">
                                <h6 class="text-danger">
                                    <i class="fas fa-trash"></i> Drop Framework Tables (<?= count($recommendations['drop_framework']) ?> tables)
                                    <button class="btn btn-sm btn-outline-danger ml-2" onclick="executeRecommendedDrops()">
                                        Drop All Framework
                                    </button>
                                </h6>
                                <small class="text-muted">These are framework tables that can be safely removed.</small>
                                <div class="table-list">
                                    <?php foreach ($recommendations['drop_framework'] as $table): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-monospace text-danger"><?= htmlspecialchars($table) ?></span>
                                            <button class="btn btn-sm btn-outline-danger" onclick="dropTable('<?= htmlspecialchars($table) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Needs Review -->
                        <?php if (!empty($recommendations['needs_review'])): ?>
                            <div class="recommendation-section">
                                <h6 class="text-info">
                                    <i class="fas fa-question-circle"></i> Needs Manual Review (<?= count($recommendations['needs_review']) ?> tables)
                                </h6>
                                <small class="text-muted">These tables require manual review to determine the best action.</small>
                                <div class="table-list">
                                    <?php foreach ($recommendations['needs_review'] as $table): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-monospace"><?= htmlspecialchars($table) ?></span>
                                            <div class="table-actions">
                                                <button class="btn btn-sm btn-outline-warning" onclick="renameTable('<?= htmlspecialchars($table) ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="dropTable('<?= htmlspecialchars($table) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Operation History -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Operations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Operation</th>
                                        <th>Type</th>
                                        <th>Source</th>
                                        <th>Target</th>
                                        <th>Status</th>
                                        <th>Execution Time</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_operations as $op): ?>
                                        <tr>
                                            <td class="font-monospace"><?= htmlspecialchars($op['operation_id']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $op['operation_type'] === 'audit' ? 'info' : ($op['operation_type'] === 'drop' ? 'danger' : 'warning') ?>">
                                                    <?= htmlspecialchars($op['operation_type']) ?>
                                                </span>
                                            </td>
                                            <td class="font-monospace"><?= htmlspecialchars($op['source_table'] ?? '-') ?></td>
                                            <td class="font-monospace"><?= htmlspecialchars($op['target_table'] ?? '-') ?></td>
                                            <td>
                                                <span class="operation-status status-<?= $op['status'] ?>">
                                                    <?= htmlspecialchars($op['status']) ?>
                                                </span>
                                                <?php if ($op['dry_run']): ?>
                                                    <span class="badge badge-secondary ml-1">DRY RUN</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $op['execution_time_ms'] ?>ms</td>
                                            <td><?= date('M j, H:i', strtotime($op['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
        <div class="mt-2">Processing operation...</div>
    </div>
    
    <!-- Rename Modal -->
    <div class="modal fade" id="renameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rename Table</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="renameForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="form-group">
                            <label for="currentTable">Current Table Name</label>
                            <input type="text" class="form-control" id="currentTable" name="current_table" readonly>
                        </div>
                        <div class="form-group">
                            <label for="newTable">New Table Name</label>
                            <input type="text" class="form-control" id="newTable" name="new_table" required>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="dryRun" name="dry_run" checked>
                            <label class="form-check-label" for="dryRun">Dry Run (Preview Only)</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="executeRename()">Rename Table</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Drop Modal -->
    <div class="modal fade" id="dropModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Drop Table</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="dropForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action will permanently delete the table and all its data.
                        </div>
                        <div class="form-group">
                            <label for="dropTableName">Table Name</label>
                            <input type="text" class="form-control" id="dropTableName" name="table_name" readonly>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="dropDryRun" name="dry_run" checked>
                            <label class="form-check-label" for="dropDryRun">Dry Run (Preview Only)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="confirmDrop" name="confirm_drop">
                            <label class="form-check-label" for="confirmDrop">I confirm this table can be deleted</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="executeDrop()">Drop Table</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentAuditData = <?= json_encode($audit_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        
        // Refresh audit data
        function refreshAudit() {
            showLoading(true);
            
            fetch('?action=audit')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentAuditData = data.data;
                        location.reload(); // Simple refresh for now
                    } else {
                        alert('Audit failed: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Audit error:', error);
                    alert('Audit request failed');
                })
                .finally(() => {
                    showLoading(false);
                });
        }
        
        // Show rename modal
        function renameTable(currentTable, suggestedNew = '') {
            document.getElementById('currentTable').value = currentTable;
            document.getElementById('newTable').value = suggestedNew || (currentTable.includes('_') ? currentTable : 'cis_' + currentTable);
            $('#renameModal').modal('show');
        }
        
        // Execute rename
        function executeRename() {
            const form = document.getElementById('renameForm');
            const formData = new FormData(form);
            
            showLoading(true);
            $('#renameModal').modal('hide');
            
            fetch('?action=rename', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Rename operation completed: ' + data.data.status);
                        if (!data.data.dry_run) {
                            location.reload();
                        }
                    } else {
                        alert('Rename failed: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Rename error:', error);
                    alert('Rename request failed');
                })
                .finally(() => {
                    showLoading(false);
                });
        }
        
        // Show drop modal
        function dropTable(tableName) {
            document.getElementById('dropTableName').value = tableName;
            $('#dropModal').modal('show');
        }
        
        // Execute drop
        function executeDrop() {
            const form = document.getElementById('dropForm');
            const formData = new FormData(form);
            
            showLoading(true);
            $('#dropModal').modal('hide');
            
            fetch('?action=drop', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Drop operation completed: ' + data.data.status);
                        if (!data.data.dry_run) {
                            location.reload();
                        }
                    } else {
                        alert('Drop failed: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Drop error:', error);
                    alert('Drop request failed');
                })
                .finally(() => {
                    showLoading(false);
                });
        }
        
        // Execute all recommended renames
        function executeRecommendedRenames() {
            if (!confirm('Execute all recommended renames in dry-run mode?')) return;
            
            const renames = currentAuditData.prefix_analysis.recommendations.rename_to_cis;
            let completed = 0;
            
            showLoading(true);
            
            function processNext() {
                if (completed >= renames.length) {
                    showLoading(false);
                    alert('All rename operations completed. Refresh the page to see changes.');
                    return;
                }
                
                const rename = renames[completed];
                const formData = new FormData();
                formData.append('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');
                formData.append('current_table', rename.current);
                formData.append('new_table', rename.target);
                formData.append('dry_run', '1');
                
                fetch('?action=rename', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Rename result:', data);
                        completed++;
                        setTimeout(processNext, 500); // Small delay between operations
                    })
                    .catch(error => {
                        console.error('Rename error:', error);
                        completed++;
                        setTimeout(processNext, 500);
                    });
            }
            
            processNext();
        }
        
        // Execute all recommended drops
        function executeRecommendedDrops() {
            if (!confirm('Execute all recommended framework table drops in dry-run mode?')) return;
            
            const drops = currentAuditData.prefix_analysis.recommendations.drop_framework;
            let completed = 0;
            
            showLoading(true);
            
            function processNext() {
                if (completed >= drops.length) {
                    showLoading(false);
                    alert('All drop operations completed. Refresh the page to see changes.');
                    return;
                }
                
                const tableName = drops[completed];
                const formData = new FormData();
                formData.append('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');
                formData.append('table_name', tableName);
                formData.append('dry_run', '1');
                
                fetch('?action=drop', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Drop result:', data);
                        completed++;
                        setTimeout(processNext, 500);
                    })
                    .catch(error => {
                        console.error('Drop error:', error);
                        completed++;
                        setTimeout(processNext, 500);
                    });
            }
            
            processNext();
        }
        
        // Show/hide loading spinner
        function showLoading(show) {
            document.getElementById('loadingSpinner').style.display = show ? 'block' : 'none';
        }
    </script>
</body>
</html>
