<?php
/**
 * CIS MVC Platform - Front Controller
 * 
 * Enterprise-grade MVC front controller with comprehensive error handling,
 * security, performance monitoring, and graceful degradation.
 * 
 * Author: GitHub Copilot (System Design Architect)
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 * Version: 2.0.0-alpha.1
 */

declare(strict_types=1);

// Performance monitoring start
$_SERVER['REQUEST_START'] = microtime(true);

// Environment setup
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Root path constant
define('ROOT_PATH', __DIR__);

try {
    // Load environment variables
    if (file_exists(ROOT_PATH . '/.env')) {
        $env = parse_ini_file(ROOT_PATH . '/.env');
        if ($env) {
            foreach ($env as $key => $value) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    // Load global helpers first
    require_once ROOT_PATH . '/app/Core/helpers.php';

    // Start secure session
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        
        session_name(config('security.session.name', 'CIS_SESSION'));
        session_start();
    }

    // Load core MVC classes
    require_once ROOT_PATH . '/app/Core/Security.php';
    require_once ROOT_PATH . '/app/Core/Model.php';
    require_once ROOT_PATH . '/app/Core/Controller.php';
    require_once ROOT_PATH . '/app/Core/Router.php';

    // Initialize security
    $security = new App\Core\Security();
    $security->setSecurityHeaders();

    // Rate limiting check
    $clientIp = $security->getClientIp();
    if (!$security->checkRateLimit("ip:{$clientIp}")) {
        http_response_code(429);
        header('Retry-After: 60');
        
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Rate limit exceeded. Please try again later.',
                ],
                'request_id' => $security->generateRequestId(),
            ]);
        } else {
            echo '<h1>429 - Rate Limit Exceeded</h1><p>Too many requests. Please try again later.</p>';
        }
        exit;
    }

    // Load and configure router
    $router = require ROOT_PATH . '/routes/mvc.php';
    
    if (!$router instanceof App\Core\Router) {
        throw new Exception('Router configuration must return Router instance');
    }

    // Load controllers
    require_once ROOT_PATH . '/app/Controllers/HomeController.php';

    // Dispatch request
    $response = $router->resolve();

    // Log performance metrics
    $requestTime = microtime(true) - $_SERVER['REQUEST_START'];
    $memoryUsage = memory_get_peak_usage(true);
    
    logger()->info('Request completed', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'uri' => $_SERVER['REQUEST_URI'] ?? '/',
        'response_time' => round($requestTime * 1000, 2), // ms
        'memory_usage' => $memoryUsage,
        'status_code' => http_response_code(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $clientIp,
    ]);

} catch (Throwable $e) {
    // Emergency error handling
    $errorId = uniqid('err_', true);
    $timestamp = date('c');
    
    // Log error with full context
    $errorContext = [
        'id' => $errorId,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '/',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => $timestamp,
    ];
    
    error_log('CIS MVC Error [' . $errorId . ']: ' . json_encode($errorContext));
    
    // Security event logging for potential attacks
    if (isset($security)) {
        $security->logSecurityEvent('application_error', [
            'error_id' => $errorId,
            'message' => $e->getMessage(),
            'severity' => 'high',
        ]);
    }
    
    // Determine response format
    $isApi = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || 
             str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    
    $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true' && 
               ($_ENV['APP_ENV'] ?? 'production') !== 'production';
    
    http_response_code(500);
    
    if ($isApi) {
        header('Content-Type: application/json');
        $response = [
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_SERVER_ERROR',
                'message' => $isDebug ? $e->getMessage() : 'An internal server error occurred',
                'error_id' => $errorId,
            ],
            'request_id' => $errorId,
            'timestamp' => $timestamp,
        ];
        
        if ($isDebug) {
            $response['debug'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    } else { 
        // HTML error page
        $title = $isDebug ? 'Application Error' : '500 - Internal Server Error';
        $message = $isDebug ? $e->getMessage() : 'An unexpected error occurred. Please try again later.';
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; margin-bottom: 20px; }
        .error-id { background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; margin: 20px 0; }
        .debug { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin-top: 20px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>' . htmlspecialchars($title) . '</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <div class="error-id">Error ID: ' . htmlspecialchars($errorId) . '</div>';
        
        if ($isDebug) {
            echo '<div class="debug">
                <h3>Debug Information</h3>
                <p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>
                <p><strong>Line:</strong> ' . $e->getLine() . '</p>
                <h4>Stack Trace:</h4>
                <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
            </div>';
        }
        
        echo '<p><small>Timestamp: ' . htmlspecialchars($timestamp) . '</small></p>
    </div>
</body>
</html>';
    }
    
    exit;
}
