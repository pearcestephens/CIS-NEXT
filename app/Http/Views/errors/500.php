<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?= $error_code ?> - CIS System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .error-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            width: 90%;
        }
        
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
            color: #e74c3c;
        }
        
        .error-code {
            font-size: 64px;
            font-weight: 700;
            color: #e74c3c;
            margin-bottom: 10px;
        }
        
        .error-title {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .error-message {
            font-size: 16px;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .error-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .error-id {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
            color: #6c757d;
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
            word-break: break-all;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .debug-section {
            margin-bottom: 15px;
        }
        
        .debug-label {
            font-weight: bold;
            color: #495057;
            display: block;
            margin-bottom: 5px;
        }
        
        .debug-value {
            font-family: monospace;
            background: white;
            padding: 5px 8px;
            border-radius: 3px;
            border: 1px solid #ced4da;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .action-buttons {
            margin-top: 40px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .system-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .error-container {
                padding: 40px 20px;
            }
            
            .error-code {
                font-size: 48px;
            }
            
            .error-title {
                font-size: 24px;
            }
            
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <div class="error-code"><?= $error_code ?></div>
        <div class="error-title"><?= $error_title ?></div>
        
        <div class="error-message">
            We apologize for the inconvenience. Our system has encountered an unexpected error 
            while processing your request. This incident has been automatically logged and our 
            technical team has been notified.
        </div>
        
        <div class="error-id">
            <strong>Error Reference:</strong> <?= $error_id ?>
        </div>
        
        <?php if ($show_debug && !empty($debug_data)): ?>
            <div class="error-details">
                <h4 style="margin-bottom: 15px; color: #e74c3c;">Debug Information</h4>
                
                <?php if (isset($debug_data['message'])): ?>
                    <div class="debug-section">
                        <span class="debug-label">Error Message:</span>
                        <div class="debug-value"><?= htmlspecialchars($debug_data['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($debug_data['file']) && isset($debug_data['line'])): ?>
                    <div class="debug-section">
                        <span class="debug-label">Location:</span>
                        <div class="debug-value"><?= htmlspecialchars($debug_data['file'], ENT_QUOTES, 'UTF-8') ?> : <?= $debug_data['line'] ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($debug_data['url'])): ?>
                    <div class="debug-section">
                        <span class="debug-label">Request URL:</span>
                        <div class="debug-value"><?= htmlspecialchars($debug_data['url'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($debug_data['timestamp'])): ?>
                    <div class="debug-section">
                        <span class="debug-label">Timestamp:</span>
                        <div class="debug-value"><?= htmlspecialchars($debug_data['timestamp'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($debug_data['memory_usage'])): ?>
                    <div class="debug-section">
                        <span class="debug-label">Memory Usage:</span>
                        <div class="debug-value">
                            Current: <?= number_format($debug_data['memory_usage']) ?> bytes
                            <?php if (isset($debug_data['peak_memory'])): ?>
                                | Peak: <?= number_format($debug_data['peak_memory']) ?> bytes
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($debug_data['trace'])): ?>
                    <div class="debug-section">
                        <span class="debug-label">Stack Trace:</span>
                        <div class="debug-value" style="max-height: 200px; overflow-y: auto;">
                            <?= htmlspecialchars($debug_data['trace'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="/" class="btn btn-primary">Return Home</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
        
        <div class="system-info">
            <strong>Need Help?</strong> Please contact our support team with the error reference above.<br>
            CIS System &copy; <?= date('Y') ?> | Error Page Generated: <?= date('Y-m-d H:i:s T') ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh after 30 seconds if this is a 500 error (optional)
        <?php if ($error_code >= 500): ?>
        setTimeout(function() {
            if (confirm('Would you like to try reloading the page? The issue might be resolved.')) {
                window.location.reload();
            }
        }, 30000);
        <?php endif; ?>
        
        // Log client-side error info
        if (typeof console !== 'undefined') {
            console.group('CIS Error Details');
            console.error('Error Code:', <?= $error_code ?>);
            console.error('Error ID:', '<?= $error_id ?>');
            console.error('Timestamp:', '<?= date('c') ?>');
            console.error('User Agent:', navigator.userAgent);
            console.error('URL:', window.location.href);
            console.groupEnd();
        }
    </script>
</body>
</html>
