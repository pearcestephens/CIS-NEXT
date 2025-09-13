<?php
declare(strict_types=1);

/**
 * CIS 2.0 MVC Front Controller (Simplified Router)
 * 
 * Main entry point for all HTTP requests in the MVC system
 * Uses SimpleRouter for reliable routing without complex dependencies
 * Author: GitHub Copilot  
 * Created: 2025-09-13
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CSP nonce for inline scripts
$nonce = bin2hex(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

try {
    // Autoloader for composer packages if available
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    
    // Global helpers
    if (file_exists(__DIR__ . '/helpers.php')) {
        require_once __DIR__ . '/helpers.php';
    }
    
    // Use SimpleRouter instead of complex Router
    require_once __DIR__ . '/app/Http/SimpleRouter.php';
    
    // Load required files manually for now
    require_once __DIR__ . '/app/Http/Controllers/BaseController.php';
    require_once __DIR__ . '/app/Http/Controllers/Admin/DashboardController.php';
    require_once __DIR__ . '/app/Http/Controllers/Admin/SimpleDashboardController.php';
    
    // Get request info
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    
    // Check for route in query parameter first
    if (isset($_GET['route'])) {
        $uri = $_GET['route'];
    } else {
        // Handle PATH_INFO for routing (e.g., index_simple.php/admin/dashboard)
        if (isset($_SERVER['PATH_INFO'])) {
            $uri = $_SERVER['PATH_INFO'];
        } else {
            // Parse URI to remove script name if present
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            if ($scriptName && strpos($uri, $scriptName) === 0) {
                $uri = substr($uri, strlen($scriptName));
            }
        }
    }
    
    // Clean up URI
    $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
    $uri = rtrim($uri, '/') ?: '/';
    
    // Initialize SimpleRouter
    $router = new App\Http\SimpleRouter();
    
    // Define routes directly here for now
    $router->get('/', function() {
        return json_encode([
            'success' => true,
            'message' => 'CIS 2.0 MVC System Active',
            'timestamp' => date('c')
        ]);
    });
    
    $router->get('/admin/dashboard', 'Admin\\SimpleDashboardController@index');
    $router->get('/test-admin-dashboard', 'Admin\\SimpleDashboardController@index');
    
    // Health check
    $router->get('/health', function() {
        return json_encode([
            'success' => true,
            'status' => 'healthy',
            'system' => 'CIS 2.0 MVC',
            'timestamp' => date('c')
        ]);
    });
    
    // Dispatch request using simple method signature
    try {
        $router->dispatch($method, $uri);
    } catch (Throwable $routeError) {
        error_log("Route dispatch error: " . $routeError->getMessage());
        
        header('Content-Type: application/json');
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'ROUTE_ERROR',
                'message' => 'Route handling failed',
                'details' => $routeError->getMessage()
            ],
            'timestamp' => date('c')
        ]);
    }
    
} catch (Exception $e) {
    error_log("MVC Front Controller Error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SYSTEM_ERROR',
            'message' => 'Application error occurred'
        ],
        'timestamp' => date('c')
    ]);
}
