<?php
/**
 * Live Route Validation with HTML Output Capture
 * Shows actual page content and validation results
 */

declare(strict_types=1);

// Routes to validate with expected content types
$routes = [
    'GET /' => ['path' => '/', 'type' => 'html', 'expect' => 'CIS System'],
    'GET /_health' => ['path' => '/_health', 'type' => 'json', 'expect' => 'ok'],
    'GET /health' => ['path' => '/health', 'type' => 'json', 'expect' => 'healthy'],
    'GET /ready' => ['path' => '/ready', 'type' => 'json', 'expect' => 'ready'],
    'GET /dashboard' => ['path' => '/dashboard', 'type' => 'html', 'expect' => 'Dashboard'],
    'GET /admin/' => ['path' => '/admin/', 'type' => 'html', 'expect' => 'Admin'],
    'GET /admin/dashboard' => ['path' => '/admin/dashboard', 'type' => 'html', 'expect' => 'Dashboard'],
    'GET /admin/tools' => ['path' => '/admin/tools', 'type' => 'html', 'expect' => 'Tools'],
    'GET /admin/settings' => ['path' => '/admin/settings', 'type' => 'html', 'expect' => 'Settings'],
    'GET /admin/users' => ['path' => '/admin/users', 'type' => 'html', 'expect' => 'Users'],
    'GET /admin/integrations' => ['path' => '/admin/integrations', 'type' => 'html', 'expect' => 'Integration'],
    'GET /admin/analytics' => ['path' => '/admin/analytics', 'type' => 'html', 'expect' => 'Analytics'],
    'GET /admin/database/prefix-manager' => ['path' => '/admin/database/prefix-manager', 'type' => 'html', 'expect' => 'Database']
];

echo "================================================================\n";
echo "CIS PHASE 0 - LIVE ROUTE VALIDATION WITH HTML OUTPUT\n";
echo "================================================================\n";
echo "Date: " . date('Y-m-d H:i:s T') . "\n\n";

$total_routes = count($routes);
$passing = 0;
$failing = 0;
$results = [];

foreach ($routes as $name => $config) {
    echo str_repeat("=", 80) . "\n";
    echo "TESTING: {$name}\n";
    echo "Path: {$config['path']}\n";
    echo "Expected Type: {$config['type']}\n";
    echo str_repeat("-", 80) . "\n";
    
    // Set up clean environment
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => $config['path'],
        'HTTP_HOST' => 'cis.dev.ecigdis.co.nz',
        'REMOTE_ADDR' => '127.0.0.1',
        'SERVER_NAME' => 'cis.dev.ecigdis.co.nz',
        'HTTPS' => 'on',
        'SERVER_PORT' => '443',
        'HTTP_USER_AGENT' => 'CIS-Phase0-Validator/1.0'
    ];
    
    // Clear any existing session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // Capture output
    ob_start();
    $error = null;
    $exception = null;
    
    try {
        // Include the main index.php to test the route
        include '/var/www/cis.dev.ecigdis.co.nz/public_html/index.php';
        $output = ob_get_contents();
    } catch (\Throwable $e) {
        $exception = $e;
        $error = $e->getMessage();
        $output = '';
    }
    
    ob_end_clean();
    
    // Analyze the response
    $success = false;
    $status = 'UNKNOWN';
    $content_analysis = '';
    
    if ($exception) {
        $status = 'EXCEPTION';
        $content_analysis = "Exception: " . get_class($exception) . " - " . $error;
    } elseif (empty($output)) {
        $status = 'EMPTY_RESPONSE';
        $content_analysis = "No output generated";
    } else {
        // Check for errors in output
        if (strpos($output, 'Fatal error') !== false) {
            $status = 'FATAL_ERROR';
        } elseif (strpos($output, 'Route not found') !== false) {
            $status = 'ROUTE_NOT_FOUND';
        } elseif (strpos($output, 'Controller') !== false && strpos($output, 'not found') !== false) {
            $status = 'CONTROLLER_ERROR';
        } else {
            // Analyze content type
            if ($config['type'] === 'json') {
                $decoded = json_decode($output);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $status = 'SUCCESS_JSON';
                    $success = true;
                    $content_analysis = "Valid JSON response";
                } else {
                    $status = 'INVALID_JSON';
                    $content_analysis = "Invalid JSON: " . json_last_error_msg();
                }
            } else {
                // HTML response
                if (strpos($output, $config['expect']) !== false) {
                    $status = 'SUCCESS_HTML';
                    $success = true;
                    $content_analysis = "HTML contains expected content: '{$config['expect']}'";
                } else {
                    $status = 'UNEXPECTED_CONTENT';
                    $content_analysis = "HTML does not contain expected text: '{$config['expect']}'";
                }
            }
        }
    }
    
    // Update counters
    if ($success) {
        $passing++;
        echo "STATUS: ✅ PASS ({$status})\n";
    } else {
        $failing++;
        echo "STATUS: ❌ FAIL ({$status})\n";
    }
    
    echo "ANALYSIS: {$content_analysis}\n";
    echo "OUTPUT LENGTH: " . strlen($output) . " bytes\n";
    
    // Show actual output
    if (!empty($output)) {
        echo "\nACTUAL OUTPUT:\n";
        echo str_repeat("-", 40) . "\n";
        
        if ($config['type'] === 'json') {
            // Pretty print JSON
            $decoded = json_decode($output);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                echo $output;
            }
        } else {
            // Show HTML (truncate if very long)
            if (strlen($output) > 1000) {
                echo substr($output, 0, 1000) . "\n[... truncated, total " . strlen($output) . " bytes]\n";
            } else {
                echo $output;
            }
        }
        echo "\n" . str_repeat("-", 40) . "\n";
    }
    
    // Store result
    $results[] = [
        'route' => $name,
        'path' => $config['path'],
        'status' => $status,
        'success' => $success,
        'output_length' => strlen($output),
        'content_type' => $config['type'],
        'output_preview' => substr($output, 0, 200),
        'error' => $error
    ];
    
    echo "\n";
}

// Final summary
echo str_repeat("=", 80) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "Total Routes: {$total_routes}\n";
echo "Passing: {$passing}\n";
echo "Failing: {$failing}\n";
echo "Success Rate: " . round(($passing / $total_routes) * 100, 1) . "%\n\n";

// Success breakdown
echo "SUCCESSFUL ROUTES:\n";
foreach ($results as $result) {
    if ($result['success']) {
        echo "✅ {$result['route']} - {$result['status']}\n";
    }
}

if ($failing > 0) {
    echo "\nFAILED ROUTES:\n";
    foreach ($results as $result) {
        if (!$result['success']) {
            echo "❌ {$result['route']} - {$result['status']}\n";
            if ($result['error']) {
                echo "   Error: {$result['error']}\n";
            }
        }
    }
}

// Generate detailed report
$report = [
    'validation_date' => date('c'),
    'phase' => 'Phase 0 - Routes & CSP Foundation',
    'total_routes' => $total_routes,
    'passing_routes' => $passing,
    'failing_routes' => $failing,
    'success_rate_percent' => round(($passing / $total_routes) * 100, 1),
    'routes' => $results
];

file_put_contents('/var/www/cis.dev.ecigdis.co.nz/public_html/var/reports/route_validation_detailed.json', 
                 json_encode($report, JSON_PRETTY_PRINT));

echo "\n" . str_repeat("=", 80) . "\n";
if ($failing === 0) {
    echo "🎉 ALL ROUTES VALIDATED SUCCESSFULLY!\n";
    echo "Phase 0 route validation complete.\n";
} else {
    echo "⚠️ {$failing} routes need attention.\n";
}
echo "Detailed report saved to: var/reports/route_validation_detailed.json\n";
echo str_repeat("=", 80) . "\n";
