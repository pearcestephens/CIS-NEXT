<?php
/**
 * Complete Route Validation - Phase 0
 * Purpose: Test ALL routes and provide detailed failure analysis
 * Quality: Production-ready testing with comprehensive reporting
 * Justification: Required to validate Phase 0 completion before proceeding
 */

declare(strict_types=1);

// Route definitions to test
$routes = [
    'GET /' => '/',
    'GET /_health' => '/_health', 
    'GET /health' => '/health',
    'GET /ready' => '/ready',
    'GET /dashboard' => '/dashboard',
    'GET /admin/' => '/admin/',
    'GET /admin/dashboard' => '/admin/dashboard',
    'GET /admin/tools' => '/admin/tools',
    'GET /admin/settings' => '/admin/settings',
    'GET /admin/users' => '/admin/users',
    'GET /admin/integrations' => '/admin/integrations',
    'GET /admin/analytics' => '/admin/analytics',
    'GET /admin/database/prefix-manager' => '/admin/database/prefix-manager'
];

$results = [];
$passing = 0;
$failing = 0;

echo "=== CIS PHASE 0 ROUTE VALIDATION ===\n";
echo "Testing " . count($routes) . " routes with minimal bootstrap...\n\n";

foreach ($routes as $name => $path) {
    echo sprintf("%-40s", "Testing {$name}:");
    
    // Set up clean environment
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => $path,
        'HTTP_HOST' => 'cis.dev.ecigdis.co.nz',
        'REMOTE_ADDR' => '127.0.0.1',
        'SERVER_NAME' => 'cis.dev.ecigdis.co.nz',
        'HTTPS' => 'on',
        'SERVER_PORT' => '443',
        'HTTP_USER_AGENT' => 'CIS-Phase0-Tester/1.0'
    ];
    
    // Clear session state
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    ob_start();
    $error = null;
    $status = 'UNKNOWN';
    
    try {
        // Test the route by including index.php
        include '/var/www/cis.dev.ecigdis.co.nz/public_html/index.php';
        $output = ob_get_contents();
        
        // Analyze response
        if (empty($output)) {
            $status = 'EMPTY_RESPONSE';
        } elseif (strpos($output, 'Bootstrap Error') !== false) {
            $status = 'BOOTSTRAP_ERROR';
        } elseif (strpos($output, 'Route not found') !== false) {
            $status = 'ROUTE_NOT_FOUND';
        } elseif (strpos($output, 'Fatal error') !== false) {
            $status = 'FATAL_ERROR';
        } elseif (strpos($output, 'Exception') !== false) {
            $status = 'EXCEPTION';
        } elseif (strpos($output, 'Error:') !== false) {
            $status = 'PHP_ERROR';
        } elseif (json_decode($output) !== null && json_last_error() === JSON_ERROR_NONE) {
            $status = 'JSON_SUCCESS';
        } elseif (strpos($output, '<h1>') !== false || strpos($output, 'CIS') !== false) {
            $status = 'HTML_SUCCESS';
        } else {
            $status = 'UNKNOWN_RESPONSE';
        }
        
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        $status = 'EXCEPTION_CAUGHT';
    }
    
    ob_end_clean();
    
    $isSuccess = in_array($status, ['JSON_SUCCESS', 'HTML_SUCCESS']);
    
    if ($isSuccess) {
        echo "✅ PASS\n";
        $passing++;
    } else {
        echo "❌ FAIL ({$status})\n";
        $failing++;
        if ($error) {
            echo "    Error: " . substr($error, 0, 80) . "...\n";
        }
    }
    
    $results[] = [
        'route' => $name,
        'path' => $path,
        'status' => $status,
        'success' => $isSuccess,
        'error' => $error,
        'output_length' => isset($output) ? strlen($output) : 0
    ];
}

echo "\n=== DETAILED RESULTS ===\n";
echo sprintf("PASSING: %d\n", $passing);
echo sprintf("FAILING: %d\n", $failing);
echo sprintf("SUCCESS RATE: %.1f%%\n", ($passing / count($routes)) * 100);

if ($failing > 0) {
    echo "\n=== FAILURE ANALYSIS ===\n";
    foreach ($results as $result) {
        if (!$result['success']) {
            echo "❌ {$result['route']}: {$result['status']}\n";
            if ($result['error']) {
                echo "   {$result['error']}\n";
            }
        }
    }
}

echo "\n=== SUCCESS LIST ===\n";
foreach ($results as $result) {
    if ($result['success']) {
        echo "✅ {$result['route']}\n";
    }
}

echo "\n=== PHASE 0 STATUS ===\n";
if ($failing === 0) {
    echo "🎉 ALL ROUTES WORKING! Phase 0 route validation COMPLETE.\n";
    echo "Ready to proceed to Phase 1 middleware implementation.\n";
} else {
    echo "⚠️  {$failing} routes still failing.\n";
    echo "Must fix all routes before Phase 0 completion.\n";
}

echo "\nTest completed: " . date('Y-m-d H:i:s T') . "\n";
