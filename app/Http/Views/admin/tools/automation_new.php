DELETE_FILE

<!-- System Status -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-info h-100">
            <div class="card-body text-center">
                <i class="fas fa-server fa-2x text-info mb-2"></i>
                <h6>System Status</h6>
                <small class="text-muted">
                    PHP <?= $data['system_status']['php_version'] ?><br>
                    Memory: <?= round($data['system_status']['memory_usage'] / 1024 / 1024, 1) ?>MB
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success h-100">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x text-success mb-2"></i>
                <h6>Server Time</h6>
                <small class="text-muted">
                    <?= date('M j, Y') ?><br>
                    <?= date('H:i:s T') ?>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning h-100">
            <div class="card-body text-center">
                <i class="fas fa-cogs fa-2x text-warning mb-2"></i>
                <h6>Available Suites</h6>
                <h4 class="text-warning"><?= count($data['automation_suites']) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-secondary h-100">
            <div class="card-body text-center">
                <i class="fas fa-history fa-2x text-secondary mb-2"></i>
                <h6>Last Run</h6>
                <small class="text-muted">
                    <?= $data['last_run'] ?? 'Never' ?>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Automation Suites -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-list mr-2"></i>Available Automation Suites
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($data['automation_suites'] as $suite): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-left-primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0">
                                        <i class="<?= $suite['icon'] ?> text-primary mr-2"></i>
                                        <?= htmlspecialchars($suite['name']) ?>
                                    </h6>
                                    <span class="badge badge-<?= $suite['status'] === 'ready' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($suite['status']) ?>
                                    </span>
                                </div>
                                <p class="card-text text-muted small mb-3">
                                    <?= htmlspecialchars($suite['description']) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        ~<?= $suite['estimated_time'] ?>s runtime
                                    </small>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="runSuite('<?= $suite['id'] ?>')"
                                            id="suite-<?= $suite['id'] ?>-btn">
                                        <i class="fas fa-play"></i> Run
                                    </button>
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

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="fas fa-bolt mr-2"></i>Quick Actions
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            onclick="runQuickAction('database_health')">
                        <div>
                            <i class="fas fa-database text-primary mr-2"></i>
                            Database Health Check
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            onclick="runQuickAction('cache_clear')">
                        <div>
                            <i class="fas fa-broom text-warning mr-2"></i>
                            Clear System Cache
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            onclick="runQuickAction('log_rotation')">
                        <div>
                            <i class="fas fa-file-archive text-info mr-2"></i>
                            Rotate Log Files
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            onclick="runQuickAction('security_scan')">
                        <div>
                            <i class="fas fa-shield-alt text-danger mr-2"></i>
                            Security Scan
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="fas fa-chart-bar mr-2"></i>Recent Results
            </div>
            <div class="card-body">
                <?php if (!empty($data['recent_results'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Suite</th>
                                    <th>Status</th>
                                    <th>Runtime</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['recent_results'] as $result): ?>
                                <tr>
                                    <td class="font-weight-bold"><?= htmlspecialchars($result['suite_name']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $result['status'] === 'success' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($result['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= $result['runtime'] ?>s</td>
                                    <td class="text-muted"><?= $result['date'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">No recent automation runs</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Execution Output -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-terminal mr-2"></i>Execution Output
                </div>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearOutput()">
                    <i class="fas fa-trash"></i> Clear
                </button>
            </div>
            <div class="card-body">
                <pre id="executionOutput" class="bg-dark text-light p-3" style="height: 400px; overflow-y: auto; font-family: 'Courier New', monospace;">
Automation suite ready. Select a suite to run or execute quick actions.
</pre>
            </div>
        </div>
    </div>
</div>

<?php
// Capture content and create JavaScript
$content = ob_get_clean();

// Page-specific JavaScript
$page_scripts = '
let isRunning = false;

// Run automation suite
function runSuite(suiteId) {
    if (isRunning) {
        AdminPanel.showAlert("Another suite is currently running", "warning");
        return;
    }
    
    const btn = document.getElementById("suite-" + suiteId + "-btn");
    const originalContent = btn.innerHTML;
    
    isRunning = true;
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Running...";
    
    appendOutput("Starting automation suite: " + suiteId);
    
    fetch("/admin/automation/run", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": document.querySelector("meta[name=csrf-token]")?.content || ""
        },
        body: JSON.stringify({ suite_id: suiteId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendOutput("✓ Suite completed successfully");
            if (data.output) {
                appendOutput(data.output);
            }
            if (data.results) {
                appendOutput("Results: " + JSON.stringify(data.results, null, 2));
            }
            AdminPanel.showAlert("Automation suite completed", "success");
        } else {
            appendOutput("✗ Suite failed: " + data.error);
            if (data.output) {
                appendOutput(data.output);
            }
            AdminPanel.showAlert("Suite failed: " + data.error, "danger");
        }
    })
    .catch(error => {
        console.error("Automation error:", error);
        appendOutput("✗ Suite execution failed: " + error.message);
        AdminPanel.showAlert("Suite execution failed", "danger");
    })
    .finally(() => {
        isRunning = false;
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

// Run all suites
document.getElementById("runAllSuitesBtn").addEventListener("click", function() {
    if (isRunning) {
        AdminPanel.showAlert("Another suite is currently running", "warning");
        return;
    }
    
    if (!confirm("Run all automation suites? This may take several minutes.")) return;
    
    const btn = this;
    const originalContent = btn.innerHTML;
    
    isRunning = true;
    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin mr-2\"></i>Running All...";
    
    appendOutput("Starting all automation suites...");
    
    fetch("/admin/automation/run-all", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": document.querySelector("meta[name=csrf-token]")?.content || ""
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendOutput("✓ All suites completed successfully");
            if (data.summary) {
                appendOutput("Summary: " + JSON.stringify(data.summary, null, 2));
            }
            AdminPanel.showAlert("All automation suites completed", "success");
        } else {
            appendOutput("✗ Some suites failed: " + data.error);
            AdminPanel.showAlert("Some suites failed", "warning");
        }
    })
    .catch(error => {
        console.error("Automation error:", error);
        appendOutput("✗ Failed to run all suites: " + error.message);
        AdminPanel.showAlert("Failed to run all suites", "danger");
    })
    .finally(() => {
        isRunning = false;
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
});

// Run quick action
function runQuickAction(actionId) {
    if (isRunning) {
        AdminPanel.showAlert("Another action is currently running", "warning");
        return;
    }
    
    isRunning = true;
    appendOutput("Running quick action: " + actionId);
    
    fetch("/admin/automation/quick-action", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": document.querySelector("meta[name=csrf-token]")?.content || ""
        },
        body: JSON.stringify({ action: actionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendOutput("✓ Quick action completed: " + actionId);
            if (data.output) {
                appendOutput(data.output);
            }
            AdminPanel.showAlert("Quick action completed", "success", 2000);
        } else {
            appendOutput("✗ Quick action failed: " + data.error);
            AdminPanel.showAlert("Quick action failed", "danger");
        }
    })
    .catch(error => {
        console.error("Quick action error:", error);
        appendOutput("✗ Quick action request failed: " + error.message);
        AdminPanel.showAlert("Quick action failed", "danger");
    })
    .finally(() => {
        isRunning = false;
    });
}

// Refresh status
document.getElementById("refreshStatusBtn").addEventListener("click", function() {
    const btn = this;
    const icon = btn.querySelector("i");
    
    icon.classList.add("fa-spin");
    btn.disabled = true;
    
    fetch("/admin/automation/status")
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appendOutput("Status refreshed successfully");
                AdminPanel.showAlert("Status refreshed", "success", 2000);
                setTimeout(() => location.reload(), 1000);
            } else {
                AdminPanel.showAlert("Failed to refresh status", "danger");
            }
        })
        .catch(error => {
            console.error("Refresh error:", error);
            AdminPanel.showAlert("Refresh failed", "danger");
        })
        .finally(() => {
            icon.classList.remove("fa-spin");
            btn.disabled = false;
        });
});

// Clear output
function clearOutput() {
    document.getElementById("executionOutput").textContent = "Output cleared.\\n";
}

// Append output to terminal
function appendOutput(text) {
    const output = document.getElementById("executionOutput");
    const timestamp = new Date().toLocaleTimeString();
    output.textContent += "[" + timestamp + "] " + text + "\\n";
    output.scrollTop = output.scrollHeight;
}
';

// Include the layout
include __DIR__ . '/../layout.php';
?>
