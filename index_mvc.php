<?php
declare(strict_types=1);

/**
 * CIS 2.0 Enterprise MVC Front Controller
 * 
 * Rock-solid entry point for all web requests
 * Author: GitHub Copilot
 * Created: 2025-09-13
 */

use App\Http\Router;

// Start session with security hardening
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Generate CSP nonce for inline scripts
$csp_nonce = base64_encode(random_bytes(16));
$GLOBALS['csp_nonce'] = $csp_nonce;

// Set content security policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: https:;");

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

ini_set('error_log', $logDir . '/php_errors.log');

// Custom error handler for graceful degradation
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $logMessage = sprintf(
        "[%s] %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $message,
        $file,
        $line
    );
    
    error_log($logMessage);
    
    // Don't display errors to users
    return true;
});

// Exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    http_response_code(500);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'FATAL_ERROR',
            'message' => 'A fatal error occurred while processing your request'
        ],
        'timestamp' => date('c')
    ]);
    
    exit(1);
});

try {
    // Autoloader for classes
    spl_autoload_register(function ($class) {
        // Convert namespace to file path
        $file = __DIR__ . '/' . str_replace(['\\', 'App/'], ['/', 'app/'], $class) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        
        return false;
    });

    // Load helper functions
    if (file_exists(__DIR__ . '/helpers.php')) {
        require_once __DIR__ . '/helpers.php';
    }

    // Initialize router
    $router = new Router();

    // Load routes
    if (file_exists(__DIR__ . '/routes/web.php')) {
        require_once __DIR__ . '/routes/web.php';
    } else {
        throw new Exception('Routes file not found');
    }

    // Create request object for router
    $request = (object) [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'uri' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
        'query' => $_GET,
        'body' => $_POST,
        'headers' => getallheaders() ?: [],
        'server' => $_SERVER
    ];

    // Route the request
    $router->dispatch($request);

} catch (Exception $e) {
    error_log("MVC Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SYSTEM_ERROR',
            'message' => 'System temporarily unavailable'
        ],
        'timestamp' => date('c')
    ]);
}
