<?php 
/**
 * Admin Dashboard - Phase 0 CSP Compliant
 */
$title='CIS Admin Dashboard'; 
$page_title='Dashboard'; 
$page_icon='fa-solid fa-gauge-high'; 
$page_header=true; 
$csp_nonce = $csp_nonce ?? ($GLOBALS['csp_nonce'] ?? null);
ob_start(); 
?>
<main id="admin-dashboard" data-page="dashboard">
  <div class="container-fluid py-3">
    <div class="row">
      <div class="col-lg-3 mb-3">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="fa-solid fa-gauge-high me-2"></i>System Stats</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <strong>Health Status:</strong> <span class="badge bg-success">Operational</span>
            </div>
            <div class="mb-3">
              <strong>Active Users:</strong> <span id="active-users">5</span>
            </div>
            <div class="mb-3">
              <strong>System Load:</strong> <span id="system-load">Normal</span>
            </div>
            <div class="mb-3">
              <strong>Uptime:</strong> <span id="uptime">24h 15m</span>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-9 mb-3">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Admin Quick Actions</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4 mb-3">
                <a href="/admin/users" class="btn btn-outline-primary w-100">
                  <i class="fa-solid fa-users me-1"></i>Manage Users
                </a>
              </div>
              <div class="col-md-4 mb-3">
                <a href="/admin/settings" class="btn btn-outline-secondary w-100">
                  <i class="fa-solid fa-cog me-1"></i>System Settings
                </a>
              </div>
              <div class="col-md-4 mb-3">
                <a href="/admin/analytics" class="btn btn-outline-info w-100">
                  <i class="fa-solid fa-chart-bar me-1"></i>Analytics
                </a>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <a href="/admin/integrations" class="btn btn-outline-success w-100">
                  <i class="fa-solid fa-plug me-1"></i>Integrations
                </a>
              </div>
              <div class="col-md-4 mb-3">
                <a href="/admin/database/prefix-manager" class="btn btn-outline-warning w-100">
                  <i class="fa-solid fa-database me-1"></i>Database Tools
                </a>
              </div>
              <div class="col-md-4 mb-3">
                <a href="/admin/tools" class="btn btn-outline-dark w-100">
                  <i class="fa-solid fa-tools me-1"></i>System Tools
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php 
$content=ob_get_clean(); 
$page_scripts = "
// Dashboard initialization with CSP nonce
console.log('Admin dashboard initialized');
// Auto-refresh stats every 30 seconds
setInterval(() => {
    document.getElementById('uptime').textContent = new Date().toLocaleTimeString();
}, 30000);
"; 
include __DIR__.'/layout.php'; 
?>
