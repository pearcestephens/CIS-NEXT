<?php
/**
 * Test Data Seeding Management Page
 * File: app/Http/Views/admin/tools/seed.php
 * Purpose: Manage test users, roles, and sample data for development and testing
 * Extends: admin/layout.php
 */

// Page configuration
$title = 'Test Data Seeding';
$page_title = 'Test Data Seeding';
$page_description = 'Manage test users, roles, and sample data for development and testing';
$page_icon = 'fas fa-seedling';
$page_header = true;

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

    <?php if (!$data['seeding_enabled']): ?>
    <!-- Warning Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-3 fa-lg"></i>
                <div>
                    <strong>Seeding Disabled:</strong> Test data seeding is currently disabled. 
                    Enable in configuration to use these tools.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Environment Info -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-info h-100">
                <div class="card-header bg-info text-white">
                    <i class="fa-solid fa-info-circle me-2"></i>Environment Info
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Environment:</strong> 
                        <span class="badge badge-<?= $data['environment'] === 'production' ? 'danger' : 'primary' ?>">
                            <?= strtoupper($data['environment']) ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <strong>Test User Email:</strong><br>
                        <code class="small"><?= htmlspecialchars($data['test_user_email']) ?></code>
                    </div>
                    <div class="mb-0">
                        <strong>Current Users:</strong> 
                        <span class="badge badge-secondary"><?= count($data['current_users']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Users Actions -->
        <div class="col-md-8">
            <div class="card border-primary h-100">
                <div class="card-header bg-primary text-white">
                    <i class="fa-solid fa-users me-2"></i>Test Users Management
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <button id="seedUsersBtn" class="btn btn-success btn-block" 
                                    <?= !$data['seeding_enabled'] ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-user-plus me-2"></i>Seed Test Users
                            </button>
                            <small class="text-muted d-block mt-1">
                                Create/update admin, manager, staff, and viewer accounts
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <button id="resetPasswordsBtn" class="btn btn-warning btn-block"
                                    <?= !$data['seeding_enabled'] ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-key me-2"></i>Reset Passwords
                            </button>
                            <small class="text-muted d-block mt-1">
                                Reset all test user passwords to default
                            </small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <button id="showUsersBtn" class="btn btn-info btn-block">
                                <i class="fa-solid fa-eye me-2"></i>Show Current Users
                            </button>
                            <small class="text-muted d-block mt-1">
                                View existing test users and their status
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <button class="btn btn-secondary btn-block" 
                                    onclick="$('#hashModal').modal('show')">
                                <i class="fa-solid fa-hashtag me-2"></i>Hash Password
                            </button>
                            <small class="text-muted d-block mt-1">
                                Generate bcrypt hash for any password
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Test Users Display -->
    <?php if (!empty($data['current_users'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <i class="fa-solid fa-users me-2"></i>Current Test Users
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Login Issues</th>
                                    <th>Last Login</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['current_users'] as $user): ?>
                                <tr>
                                    <td><code><?= $user['id'] ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                                        <?php if ($user['must_change_password']): ?>
                                            <i class="fa-solid fa-exclamation-triangle text-warning ms-1" 
                                               title="Must change password"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="small"><?= htmlspecialchars($user['email_masked']) ?></code>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $user['role'] === 'admin' ? 'danger' : 
                                            ($user['role'] === 'manager' ? 'warning' : 
                                            ($user['role'] === 'staff' ? 'info' : 'secondary')) ?>">
                                            <?= strtoupper($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= strtoupper($user['status']) ?>
                                        </span>
                                        <?php if ($user['is_locked']): ?>
                                            <i class="fa-solid fa-lock text-danger ms-1" title="Account locked"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($user['login_attempts'] > 0): ?>
                                            <span class="badge badge-warning"><?= $user['login_attempts'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($user['last_login']): ?>
                                            <?= date('M j, Y H:i', strtotime($user['last_login'])) ?>
                                            <?php if ($user['days_since_login'] !== null): ?>
                                                <br><span class="text-muted"><?= $user['days_since_login'] ?> days ago</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Results Area -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card" id="resultsCard" style="display: none;">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fa-solid fa-terminal me-2"></i>Operation Results
                    </span>
                    <button class="btn btn-sm btn-outline-light" onclick="$('#resultsCard').hide()">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <div class="card-body">
                    <pre id="resultsOutput" class="mb-0 bg-dark text-light p-3 rounded small"></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Hash Modal -->
<div class="modal fade" id="hashModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">
                    <i class="fa-solid fa-hashtag me-2"></i>Password Hash Generator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="hashForm">
                    <div class="mb-3">
                        <label for="plainPassword" class="form-label">Plain Text Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="plainPassword" 
                                   placeholder="Enter password to hash" required>
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="togglePasswordVisibility('plainPassword')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">
                            Password will be hashed using PHP's default algorithm (bcrypt)
                        </small>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-hashtag me-2"></i>Generate Hash
                        </button>
                    </div>
                </form>
                <div id="hashResults" class="mt-3" style="display: none;">
                    <hr>
                    <h6>Hash Results:</h6>
                    <div class="bg-light p-3 rounded">
                        <pre id="hashOutput" class="mb-0 small"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Seed Users Button
    document.getElementById('seedUsersBtn').addEventListener('click', function() {
        executeAction('/admin/seeds/seed-users', 'Seeding test users...');
    });
    
    // Reset Passwords Button
    document.getElementById('resetPasswordsBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to reset all test user passwords?')) {
            executeAction('/admin/seeds/reset-passwords', 'Resetting test passwords...');
        }
    });
    
    // Show Users Button
    document.getElementById('showUsersBtn').addEventListener('click', function() {
        executeAction('/admin/seeds/current-users', 'Fetching current users...');
    });
    
    // Password Hash Form
    document.getElementById('hashForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = document.getElementById('plainPassword').value;
        if (!password) return;
        
        const url = `/admin/seeds/hash-password?plain=${encodeURIComponent(password)}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                document.getElementById('hashOutput').textContent = JSON.stringify(data, null, 2);
                document.getElementById('hashResults').style.display = 'block';
                
                // Clear password field for security
                document.getElementById('plainPassword').value = '';
            })
            .catch(error => {
                document.getElementById('hashOutput').textContent = 'Error: ' + error.message;
                document.getElementById('hashResults').style.display = 'block';
            });
    });
    
    // Generic action executor
    function executeAction(url, loadingText) {
        const resultsCard = document.getElementById('resultsCard');
        const resultsOutput = document.getElementById('resultsOutput');
        
        // Show loading
        resultsOutput.textContent = loadingText;
        resultsCard.style.display = 'block';
        
        // Scroll to results
        resultsCard.scrollIntoView({ behavior: 'smooth' });
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                resultsOutput.textContent = JSON.stringify(data, null, 2);
                
                // Refresh page if successful seeding operation
                if (data.success && (url.includes('seed-users') || url.includes('reset-passwords'))) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                resultsOutput.textContent = 'Network Error: ' + error.message;
            });
    }
    
    // Password visibility toggle
    window.togglePasswordVisibility = function(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = field.nextElementSibling.querySelector('i');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            field.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    };
});
</script>

<style>
.btn-block {
    width: 100%;
}

.card-header {
    border-bottom: 2px solid rgba(0,0,0,0.125);
}

pre {
    max-height: 400px;
    overflow-y: auto;
}

.table th {
    border-top: none;
    font-weight: 600;
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .btn-block {
        margin-bottom: 0.5rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>

<?php
// End content capture
$content = ob_get_clean();
$title = "Seed Data Management";

// Include layout
include __DIR__ . '/../layout.php';
?>
