<?php
declare(strict_types=1);

/**
 * Visual Capture Fallback Tool
 * 
 * Standalone screenshot capture tool for use when admin interface
 * is not available or accessible.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Generate CSP nonce for html2canvas
$nonce = base64_encode(random_bytes(16));

// Add CSP header with nonce for this page only
header("Content-Security-Policy: script-src 'self' https://html2canvas.hertzen.com 'nonce-{$nonce}'; img-src 'self' data: blob:; connect-src 'self';");

$targetRoutes = [
    ['stage' => 'pre_login', 'path' => '/login', 'description' => 'Login page (pre-login)'],
    ['stage' => 'invalid_login', 'path' => '/login', 'description' => 'Login with invalid credentials'],
    ['stage' => 'valid_login', 'path' => '/dashboard', 'description' => 'Dashboard after valid login'],
    ['stage' => 'admin_deny', 'path' => '/admin', 'description' => 'Admin area as non-admin user'],
    ['stage' => 'admin_allow', 'path' => '/admin', 'description' => 'Admin area as admin user']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Capture Tool - CIS</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken()) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-camera mr-2"></i>Visual Capture Tool</h4>
                        <p class="mb-0 text-muted">Capture screenshots of critical application pages for automated testing</p>
                    </div>
                    <div class="card-body">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>
                            This tool captures visual screenshots using html2canvas. Screenshots are stored in 
                            <code>var/screenshots/<?= date('Ymd') ?>/</code> and can be used for visual regression testing.
                        </div>

                        <div class="row">
                            <?php foreach ($targetRoutes as $route): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><?= htmlspecialchars($route['description']) ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-2">Route: <code><?= htmlspecialchars($route['path']) ?></code></p>
                                        <p class="text-muted mb-3">Stage: <code><?= htmlspecialchars($route['stage']) ?></code></p>
                                        
                                        <button class="btn btn-primary btn-sm capture-btn" 
                                                data-stage="<?= htmlspecialchars($route['stage']) ?>" 
                                                data-path="<?= htmlspecialchars($route['path']) ?>">
                                            <i class="fas fa-camera mr-1"></i>Capture Screenshot
                                        </button>
                                        
                                        <div class="capture-status mt-2" id="status-<?= htmlspecialchars($route['stage']) ?>"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>Capture Results</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="capture-results">
                                            <p class="text-muted">No captures yet. Click "Capture Screenshot" buttons above to start.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden iframe for page loading -->
    <iframe id="capture-frame" style="position: absolute; left: -9999px; width: 1200px; height: 800px;"></iframe>

    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js" nonce="<?= $nonce ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script nonce="<?= $nonce ?>">
    $(document).ready(function() {
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        $('.capture-btn').click(function() {
            const stage = $(this).data('stage');
            const path = $(this).data('path');
            const button = $(this);
            const status = $('#status-' + stage);
            
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Capturing...');
            status.html('<div class="alert alert-info alert-sm">Loading page...</div>');
            
            captureScreenshot(stage, path, button, status);
        });
        
        function captureScreenshot(stage, path, button, status) {
            const iframe = document.getElementById('capture-frame');
            const fullUrl = window.location.protocol + '//' + window.location.host + path;
            
            iframe.onload = function() {
                // Wait for page to fully render
                setTimeout(function() {
                    status.html('<div class="alert alert-info alert-sm">Generating screenshot...</div>');
                    
                    try {
                        const iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
                        
                        html2canvas(iframeDocument.body, {
                            useCORS: true,
                            scale: 0.8,
                            width: 1200,
                            height: 800,
                            scrollX: 0,
                            scrollY: 0
                        }).then(function(canvas) {
                            const imageData = canvas.toDataURL('image/png');
                            
                            // Upload screenshot
                            $.ajax({
                                url: 'capture_upload.php',
                                method: 'POST',
                                data: {
                                    stage: stage,
                                    route: path,
                                    png_base64: imageData,
                                    html: iframeDocument.documentElement.outerHTML,
                                    csrf_token: csrfToken
                                },
                                success: function(response) {
                                    if (response.ok) {
                                        status.html('<div class="alert alert-success alert-sm">Captured: ' + response.filename + '</div>');
                                        addCaptureResult(stage, path, response);
                                    } else {
                                        status.html('<div class="alert alert-danger alert-sm">Error: ' + response.error + '</div>');
                                    }
                                },
                                error: function() {
                                    status.html('<div class="alert alert-danger alert-sm">Upload failed</div>');
                                },
                                complete: function() {
                                    button.prop('disabled', false).html('<i class="fas fa-camera mr-1"></i>Capture Screenshot');
                                }
                            });
                        }).catch(function(error) {
                            status.html('<div class="alert alert-danger alert-sm">Canvas error: ' + error.message + '</div>');
                            button.prop('disabled', false).html('<i class="fas fa-camera mr-1"></i>Capture Screenshot');
                        });
                    } catch (error) {
                        status.html('<div class="alert alert-danger alert-sm">Access error: ' + error.message + '</div>');
                        button.prop('disabled', false).html('<i class="fas fa-camera mr-1"></i>Capture Screenshot');
                    }
                }, 1500);
            };
            
            iframe.onerror = function() {
                status.html('<div class="alert alert-danger alert-sm">Failed to load page</div>');
                button.prop('disabled', false).html('<i class="fas fa-camera mr-1"></i>Capture Screenshot');
            };
            
            iframe.src = fullUrl;
        }
        
        function addCaptureResult(stage, path, response) {
            const results = $('#capture-results');
            if (results.find('p.text-muted').length) {
                results.empty();
            }
            
            const resultHtml = `
                <div class="capture-result mb-2 p-2 border rounded">
                    <strong>${stage}</strong> - ${path}
                    <br>
                    <small class="text-muted">Saved: ${response.filename}</small>
                    <small class="text-muted float-right">${new Date().toLocaleTimeString()}</small>
                </div>
            `;
            
            results.prepend(resultHtml);
        }
    });
    </script>
</body>
</html>
