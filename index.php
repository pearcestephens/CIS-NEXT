<?php
declare(strict_types=1);

/**
 * CIS Application Entry Point - FIXED
 * Bootstrap and routing problems resolved
 */

// Start session early
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = __DIR__;

// FIXED: Manual class loading before autoloader is available
require_once $rootPath . '/app/Shared/Config/Config.php';
require_once $rootPath . '/app/Shared/Logging/Logger.php';
require_once $rootPath . '/app/Infra/Persistence/MariaDB/Database.php';
require_once $rootPath . '/app/Http/Router.php';
require_once $rootPath . '/app/Shared/Bootstrap.php';

// FIXED: Load controllers and models for routes  
require_once $rootPath . '/app/Http/Controllers/BaseController.php';
require_once $rootPath . '/app/Models/BaseModel.php';
require_once $rootPath . '/app/Models/User.php';  
require_once $rootPath . '/app/Http/Controllers/AuthController.php';

use App\Shared\Bootstrap;
use App\Http\Router;

try {
    // FIXED: Initialize with proper error handling  
    Bootstrap::init($rootPath);
    
    // Create router and load routes
    $router = new Router();
    require_once $rootPath . '/routes/web.php';
    
    // Run application
    $bootstrap = new Bootstrap($router);
    $bootstrap->run();
    
} catch (\Throwable $e) {
    // Emergency fallback - handle bootstrap failures gracefully
    http_response_code(500);
    error_log("CIS Bootstrap Error: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
    
    $path = $_SERVER['REQUEST_URI'] ?? '/';
    if (($pos = strpos($path, '?')) !== false) {
        $path = substr($path, 0, $pos);
    }
    
    // For health checks and APIs, return JSON
    if (in_array($path, ['/', '/_health', '/health', '/ready'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'timestamp' => date('c'),
            'message' => 'Bootstrap error - database may be unavailable',
            'error' => $e->getMessage()
        ]);
        exit;
    }
    
    // For other requests, show error page
    echo "CIS Bootstrap Error: " . htmlspecialchars($e->getMessage());
    exit;
}
