<?php
/**
 * Admin Layout Template with CSP Nonce Support
 * File: app/Http/Views/admin/layout.php
 * Purpose: Main admin dashboard layout with header, sidebar, and footer partials
 * Updated: 2025-09-13 - Phase 0 CSP Implementation
 */

// Security check
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Get CSP nonce from middleware
$csp_nonce = $csp_nonce ?? ($GLOBALS['csp_nonce'] ?? null);

$user = $_SESSION['user'] ?? [];
$user_role = $user['role'] ?? 'general';
$is_super_admin = $user_role === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'CIS Admin Panel') ?> - Ecigdis CIS</title>
    
    <!-- CSRF Token for AJAX requests -->
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    
    <!-- CSP with strict same-origin policy and nonce -->
    <?php if ($csp_nonce): ?>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'nonce-<?= htmlspecialchars($csp_nonce) ?>'; style-src 'self'; img-src 'self' data:; connect-src 'self'; font-src 'self'; base-uri 'none'; frame-ancestors 'none';">
    <?php endif; ?>
    
    <!-- Local Bootstrap 5.3.2 (same-origin) -->
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Local Font Awesome (same-origin) -->
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Custom Admin CSS -->
    <link href="/assets/css/admin.css" rel="stylesheet">
    
    <!-- Preload critical modules -->
    <link rel="modulepreload" href="/assets/js/modules/ui.js">
    <link rel="modulepreload" href="/assets/js/modules/net.js">
    
    <!-- Additional head content -->
    <?php if (isset($additional_head)): ?>
    <?= $additional_head ?>
    <?php endif; ?>
</head>
<body class="admin-body" <?= isset($page_module) ? "data-page=\"{$page_module}\"" : '' ?>>
    
    <?php include __DIR__ . '/partials/header.php'; ?>
    
    <div class="admin-container">
        
        <?php include __DIR__ . '/partials/sidebar.php'; ?>
        
        <main class="admin-main" role="main">
            
            <!-- Page Header -->
            <?php if (isset($page_header) && $page_header): ?>
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="page-title">
                        <?php if (isset($page_icon)): ?>
                        <i class="<?= htmlspecialchars($page_icon) ?>"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($page_title ?? $title ?? 'Admin Panel') ?>
                    </h1>
                    
                    <?php if (isset($page_actions)): ?>
                    <div class="page-actions">
                        <?= $page_actions ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($page_description)): ?>
                <p class="page-description text-muted"><?= htmlspecialchars($page_description) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Alert Messages -->
            <?php if (isset($alert_message)): ?>
            <div class="alert alert-<?= htmlspecialchars($alert_type ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($alert_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Toast Container -->
            <div class="toast-container position-fixed top-0 end-0 p-3" id="adminToastContainer" style="z-index: 1055;">
                <!-- Toasts will be dynamically inserted here -->
            </div>
            
            <!-- Main Content Area -->
            <div class="admin-content">
                <?php
                // Include the main content
                if (isset($content)) {
                    echo $content;
                } elseif (isset($content_file)) {
                    include $content_file;
                } else {
                    echo "<p class='text-muted'>No content specified</p>";
                }
                ?>
            </div>
            
        </main>
        
    </div>
    
    <?php include __DIR__ . '/partials/footer.php'; ?>
    
    <!-- Local Bootstrap 5.3.2 JS -->
    <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js" <?= $csp_nonce ? "nonce=\"{$csp_nonce}\"" : '' ?>></script>
    
    <!-- Admin Panel Core Module -->
    <script type="module" src="/assets/js/admin.js" <?= $csp_nonce ? "nonce=\"{$csp_nonce}\"" : '' ?>></script>
    
    <!-- Additional scripts -->
    <?php if (isset($additional_scripts)): ?>
    <?= $additional_scripts ?>
    <?php endif; ?>
    
    <!-- Page-specific initialization -->
    <?php if (isset($page_scripts)): ?>
    <script type="module" <?= $csp_nonce ? "nonce=\"{$csp_nonce}\"" : '' ?>>
    <?= $page_scripts ?>
    </script>
    <?php endif; ?>
    
</body>
</html>
