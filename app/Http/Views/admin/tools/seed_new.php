DELETE_FILE

$page_actions = '
    <div class="d-flex align-items-center">
        <button id="refreshDataBtn" class="btn btn-outline-info mr-2">
            <i class="fas fa-sync-alt"></i> Refresh Data
        </button>
        <button id="clearAllDataBtn" class="btn btn-outline-danger mr-2">
            <i class="fas fa-trash-alt"></i> Clear All
        </button>
        <button id="seedAllBtn" class="btn btn-success">
            <i class="fas fa-play"></i> Seed All
        </button>
    </div>
';

// Start content capture
ob_start();
?>

<!-- Environment Info -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-info h-100">
            <div class="card-header bg-info text-white">
                <i class="fas fa-info-circle mr-2"></i>Environment Info
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>Environment:</strong> 
                    <span class="badge badge-<?= $data['environment'] === 'production' ? 'danger' : 'primary' ?>">
                        <?= ucfirst($data['environment']) ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Seeding:</strong>
                    <span class="badge <?= $data['seeding_enabled'] ? 'badge-success' : 'badge-warning' ?>">
                        <?= $data['seeding_enabled'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Database:</strong> 
                    <span class="text-muted"><?= htmlspecialchars($data['database_name']) ?></span>
                </div>
                <div>
                    <strong>Last Seed:</strong> 
                    <span class="text-muted"><?= $data['last_seed_time'] ?? 'Never' ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-success h-100">
            <div class="card-header bg-success text-white">
                <i class="fas fa-chart-bar mr-2"></i>Current Data Count
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 border-right">
                        <h4 class="text-success"><?= $data['counts']['users'] ?></h4>
                        <small class="text-muted">Users</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?= $data['counts']['roles'] ?></h4>
                        <small class="text-muted">Roles</small>
                    </div>
                </div>
                <hr class="my-2">
                <div class="row text-center">
                    <div class="col-6 border-right">
                        <h6 class="text-info"><?= $data['counts']['configs'] ?></h6>
                        <small class="text-muted">Configs</small>
                    </div>
                    <div class="col-6">
                        <h6 class="text-info"><?= $data['counts']['audit_logs'] ?></h6>
                        <small class="text-muted">Audit Logs</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-warning h-100">
            <div class="card-header bg-warning text-white">
                <i class="fas fa-exclamation-triangle mr-2"></i>Safety Checks
            </div>
            <div class="card-body">
                <?php if ($data['environment'] === 'production'): ?>
                    <div class="alert alert-danger mb-2 p-2">
                        <i class="fas fa-ban mr-1"></i>
                        <small><strong>Production Mode:</strong> Seeding disabled for safety</small>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($data['warnings'])): ?>
                    <?php foreach ($data['warnings'] as $warning): ?>
                    <div class="alert alert-warning mb-2 p-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <small><?= htmlspecialchars($warning) ?></small>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        <small>All safety checks passed</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Seeding Options -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-seedling mr-2"></i>Available Seed Options
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($data['seed_options'] as $option): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-left-primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0">
                                        <i class="<?= $option['icon'] ?> text-primary mr-2"></i>
                                        <?= htmlspecialchars($option['name']) ?>
                                    </h6>
                                    <span class="badge badge-<?= $option['status'] === 'available' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($option['status']) ?>
                                    </span>
                                </div>
                                <p class="card-text text-muted small mb-3">
                                    <?= htmlspecialchars($option['description']) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Creates ~<?= $option['count'] ?> records
                                    </small>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary mr-1" 
                                                onclick="previewSeed('<?= $option['id'] ?>')"
                                                <?= !$data['seeding_enabled'] ? 'disabled' : '' ?>>
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="runSeed('<?= $option['id'] ?>')"
                                                id="seed-<?= $option['id'] ?>-btn"
                                                <?= !$data['seeding_enabled'] ? 'disabled' : '' ?>>
                                            <i class="fas fa-play"></i> Seed
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Options -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-trash-alt mr-2"></i>Cleanup Operations
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            onclick="cleanupData('test_users')"
                            <?= !$data['seeding_enabled'] ? 'disabled' : '' ?>>
                        <div>
                            <i class="fas fa-user-minus text-warning mr-2"></i>
                            Remove Test Users
                        </div>
                        <span class="badge badge-warning"><?= $data['counts']['test_users'] ?></span>
                    </button>
                    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            onclick="cleanupData('audit_logs')"
                            <?= !$data['seeding_enabled'] ? 'disabled' : '' ?>>
                        <div>
                            <i class="fas fa-history text-info mr-2"></i>
                            Clear Audit Logs
                        </div>
                        <span class="badge badge-info"><?= $data['counts']['audit_logs'] ?></span>
                    </button>
                    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            onclick="cleanupData('all_test_data')"
                            <?= !$data['seeding_enabled'] ? 'disabled' : '' ?>>
                        <div>
                            <i class="fas fa-bomb text-danger mr-2"></i>
                            Remove All Test Data
                        </div>
                        <span class="badge badge-danger">All</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="fas fa-history mr-2"></i>Recent Seeding Activity
            </div>
            <div class="card-body">
                <?php if (!empty($data['recent_activity'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Operation</th>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['recent_activity'] as $activity): ?>
                                <tr>
                                    <td class="font-weight-bold"><?= htmlspecialchars($activity['operation']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $activity['status'] === 'success' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($activity['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= $activity['count'] ?></td>
                                    <td class="text-muted"><?= $activity['time'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">No recent seeding activity</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Output Console -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-terminal mr-2"></i>Seeding Output
                </div>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearSeedOutput()">
                    <i class="fas fa-trash"></i> Clear
                </button>
            </div>
            <div class="card-body">
                <pre id="seedOutput" class="bg-dark text-light p-3" style="height: 300px; overflow-y: auto; font-family: 'Courier New', monospace;">
Seeding system ready. Select options to preview or execute seeding operations.
</pre>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye mr-2"></i>Seeding Preview
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="previewContent">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                        <p class="text-muted">Loading preview...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="executeFromPreview()" id="executeFromPreviewBtn">
                    <i class="fas fa-play mr-1"></i>Execute This Seed
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Capture content and create JavaScript
$content = ob_get_clean();

// Page-specific JavaScript
$page_scripts = '
let isSeeding = false;
let currentPreviewSeed = null;

// Run seed operation
function runSeed(seedId) {
    if (isSeeding) {
        AdminPanel.showAlert("Another seeding operation is running", "warning");
        return;
    }
    
    if (!confirm("Execute seeding operation: " + seedId + "?\\nThis will create test data in the database.")) {
        return;
    }
    
    const btn = document.getElementById("seed-" + seedId + "-btn");
    const originalContent = btn.innerHTML;
    
    isSeeding = true;
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Seeding...";
    
    appendSeedOutput("Starting seed operation: " + seedId);
    
    fetch("/admin/seed/run", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": document.querySelector("meta[name=csrf-token]")?.content || ""
        },
        body: JSON.stringify({ seed_id: seedId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendSeedOutput("✓ Seeding completed successfully");
            appendSeedOutput("Created " + data.created_count + " records");
            if (data.details) {
                appendSeedOutput("Details: " + JSON.stringify(data.details, null, 2));
            }
            AdminPanel.showAlert("Seeding completed: " + data.created_count + " records created", "success");
            setTimeout(() => location.reload(), 2000);
        } else {
            appendSeedOutput("✗ Seeding failed: " + data.error);
            if (data.details) {
                appendSeedOutput("Error details: " + JSON.stringify(data.details, null, 2));
            }
            AdminPanel.showAlert("Seeding failed: " + data.error, "danger");
        }
    })
    .catch(error => {
        console.error("Seeding error:", error);
        appendSeedOutput("✗ Seeding request failed: " + error.message);
        AdminPanel.showAlert("Seeding request failed", "danger");
    })
    .finally(() => {
        isSeeding = false;
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

// Preview seed data
function previewSeed(seedId) {
    currentPreviewSeed = seedId;
    $("#previewModal").modal("show");
    
    fetch("/admin/seed/preview", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": document.querySelector("meta[name=csrf-token]")?.content || ""
        },
        body: JSON.stringify({ seed_id: seedId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = "<h6>Preview for: " + seedId + "</h6>";
            html += "<p class=\"text-muted\">Records to be created: <strong>" + data.preview_count + "</strong></p>";
            
            if (data.preview_data && data.preview_data.length > 0) {
                html += "<div class=\"table-responsive\">";
                html += "<table class=\"table table-sm table-bordered\">";
                html += "<thead class=\"thead-light\"><tr>";
                
                // Headers
                Object.keys(data.preview_data[0]).forEach(key => {
                    html += "<th>" + key + "</th>";
                });
                html += "</tr></thead><tbody>";
                
                // Data rows (limit to 5 for preview)
                data.preview_data.slice(0, 5).forEach(row => {
                    html += "<tr>";
                    Object.values(row).forEach(value => {
                        html += "<td>" + (value || "<em>null</em>") + "</td>";
                    });
                    html += "</tr>";
                });
                
                if (data.preview_data.length > 5) {
                    html += "<tr><td colspan=\"" + Object.keys(data.preview_data[0]).length + "\" class=\"text-center text-muted\">... and " + (data.preview_data.length - 5) + " more records</td></tr>";
                }
                
                html += "</tbody></table></div>";
            }
            
            document.getElementById("previewContent").innerHTML = html;
        } else {
            document.getElementById("previewContent").innerHTML = "<div class=\"alert alert-danger\">Failed to load preview: " + data.error + "</div>";
            document.getElementById("executeFromPreviewBtn").style.display = "none";
        }
    })
    .catch(error => {
        console.error("Preview error:", error);
        document.getElementById("previewContent").innerHTML = "<div class=\"alert alert-danger\">Preview request failed: " + error.message + "</div>";
        document.getElementById("executeFromPreviewBtn").style.display = "none";
    });
}

// Execute from preview modal
function executeFromPreview() {
    $("#previewModal").modal("hide");
    if (currentPreviewSeed) {
        runSeed(currentPreviewSeed);
    }
}

// Cleanup data
function cleanupData(type) {
    let confirmText = "Remove " + type.replace("_", " ") + "?";
    if (type === "all_test_data") {
        confirmText = "⚠️ DANGER: This will remove ALL test data including users, logs, and configurations.\\n\\nType \"DELETE ALL\" to confirm:";
        let userConfirm = prompt(confirmText);
        if (userConfirm !== "DELETE ALL") {
            AdminPanel.showAlert("Cleanup cancelled - confirmation text did not match", "info");
            return;
        }
    } else if (!confirm(confirmText + "\\n\\nThis action cannot be undone.")) {
        return;
    }
    
    appendSeedOutput("Starting cleanup: " + type);
    
    fetch("/admin/seed/cleanup", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": document.querySelector("meta[name=csrf-token]")?.content || ""
        },
        body: JSON.stringify({ cleanup_type: type })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendSeedOutput("✓ Cleanup completed: " + data.removed_count + " records removed");
            AdminPanel.showAlert("Cleanup completed: " + data.removed_count + " records removed", "success");
            setTimeout(() => location.reload(), 2000);
        } else {
            appendSeedOutput("✗ Cleanup failed: " + data.error);
            AdminPanel.showAlert("Cleanup failed: " + data.error, "danger");
        }
    })
    .catch(error => {
        console.error("Cleanup error:", error);
        appendSeedOutput("✗ Cleanup request failed: " + error.message);
        AdminPanel.showAlert("Cleanup request failed", "danger");
    });
}

// Seed all data
document.getElementById("seedAllBtn").addEventListener("click", function() {
    if (isSeeding) {
        AdminPanel.showAlert("Another seeding operation is running", "warning");
        return;
    }
    
    if (!confirm("Execute ALL seeding operations?\\nThis will create comprehensive test data.")) return;
    
    const btn = this;
    const originalContent = btn.innerHTML;
    
    isSeeding = true;
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin mr-2\"></i>Seeding All...";
    
    appendSeedOutput("Starting comprehensive seeding...");
    
    fetch("/admin/seed/run-all", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": document.querySelector("meta[name=csrf-token]")?.content || ""
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendSeedOutput("✓ All seeding operations completed");
            appendSeedOutput("Total records created: " + data.total_created);
            if (data.summary) {
                appendSeedOutput("Summary: " + JSON.stringify(data.summary, null, 2));
            }
            AdminPanel.showAlert("All seeding completed: " + data.total_created + " records", "success");
            setTimeout(() => location.reload(), 3000);
        } else {
            appendSeedOutput("✗ Some seeding operations failed: " + data.error);
            AdminPanel.showAlert("Some seeding operations failed", "warning");
        }
    })
    .catch(error => {
        console.error("Seed all error:", error);
        appendSeedOutput("✗ Failed to run all seeding: " + error.message);
        AdminPanel.showAlert("Failed to run all seeding", "danger");
    })
    .finally(() => {
        isSeeding = false;
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
});

// Refresh data
document.getElementById("refreshDataBtn").addEventListener("click", function() {
    const btn = this;
    const icon = btn.querySelector("i");
    
    icon.classList.add("fa-spin");
    btn.disabled = true;
    
    appendSeedOutput("Refreshing data counts...");
    
    setTimeout(() => {
        location.reload();
    }, 1000);
});

// Clear all data
document.getElementById("clearAllDataBtn").addEventListener("click", function() {
    cleanupData("all_test_data");
});

// Clear output
function clearSeedOutput() {
    document.getElementById("seedOutput").textContent = "Output cleared.\\n";
}

// Append output to console
function appendSeedOutput(text) {
    const output = document.getElementById("seedOutput");
    const timestamp = new Date().toLocaleTimeString();
    output.textContent += "[" + timestamp + "] " + text + "\\n";
    output.scrollTop = output.scrollHeight;
}
';

// Include the layout
include __DIR__ . '/../layout.php';
?>
