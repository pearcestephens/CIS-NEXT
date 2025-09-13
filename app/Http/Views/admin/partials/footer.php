<?php
/**
 * Admin Footer Partial
 * File: app/Http/Views/admin/partials/footer.php
 * Purpose: Footer for admin panel with system info and links
 */
?>
<footer class="admin-footer">
    <div class="container-fluid">
        <div class="row">
            
            <!-- Left Side: Company Info -->
            <div class="col-md-6">
                <p class="footer-text mb-0">
                    &copy; <?= date('Y') ?> <strong>Ecigdis Limited</strong> (The Vape Shed)
                    <span class="text-muted">| CIS Admin Panel v2.0</span>
                </p>
            </div>
            
            <!-- Right Side: System Info & Links -->
            <div class="col-md-6 text-end">
                <div class="footer-info d-flex justify-content-end align-items-center">
                    
                    <!-- Performance Indicator -->
                    <div class="performance-info me-3">
                        <small class="text-muted">
                            <i class="fas fa-tachometer-alt"></i>
                            <span id="pageLoadTime">--</span>ms
                        </small>
                    </div>
                    
                    <!-- User Session Info -->
                    <div class="session-info me-3">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i>
                            <span id="sessionTime"><?= isset($_SESSION['login_time']) ? date('H:i', $_SESSION['login_time']) : '--:--' ?></span>
                        </small>
                    </div>
                    
                    <!-- System Status -->
                    <div class="system-status">
                        <small class="status-indicator">
                            <i class="fas fa-circle text-success" title="System Online"></i>
                        </small>
                    </div>
                    
                </div>
            </div>
            
        </div>
    </div>
</footer>

<!-- Performance tracking -->
<script type="module">
// Track page load time and session duration (moved to admin.js modules)
if (window.performance && window.performance.timing) {
    const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
    const loadTimeEl = document.getElementById('pageLoadTime');
    if (loadTimeEl) {
        loadTimeEl.textContent = loadTime;
    }
}
</script>
