<?php
declare(strict_types=1);

/**
 * RBACMiddleware.php - Role-Based Access Control middleware
 * 
 * Enforces role-based permissions with caching for performance.
 * Supports role hierarchy and permission checking.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T14:45:00Z
 */

namespace App\Http\Middlewares;

use App\Http\MiddlewareInterface;
use App\Shared\Logging\Logger;

class RBACMiddleware implements MiddlewareInterface
{
    private Logger $logger;
    private string $requiredRole;
    private array $roleHierarchy = [
        'admin' => ['admin', 'manager', 'staff', 'viewer'],
        'manager' => ['manager', 'staff', 'viewer'],
        'staff' => ['staff', 'viewer'],
        'viewer' => ['viewer']
    ];
    private int $cacheTtl = 300; // 5 minutes
    
    public function __construct(Logger $logger, string $requiredRole = 'viewer')
    {
        $this->logger = $logger;
        $this->requiredRole = $requiredRole;
    }
    
    /**
     * Check role permissions before request processing
     */
    public function before($request): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $userId = $_SESSION['user_id'] ?? null;
        $userRole = $_SESSION['user_role'] ?? null;
        
        if (!$userId || !$userRole) {
            $this->logger->warning('RBAC check failed - no user session', [
                'component' => 'rbac_middleware',
                'action' => 'access_denied_no_session',
                'required_role' => $this->requiredRole,
                'uri' => $request->uri,
                'method' => $request->method,
                'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
            ]);
            
            $this->sendAccessDenied($request);
        }
        
        if (!$this->hasRequiredRole($userRole, $this->requiredRole)) {
            $this->logger->warning('RBAC check failed - insufficient permissions', [
                'component' => 'rbac_middleware',
                'action' => 'access_denied_insufficient_role',
                'user_id' => $userId,
                'user_role' => $userRole,
                'required_role' => $this->requiredRole,
                'uri' => $request->uri,
                'method' => $request->method,
                'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
            ]);
            
            $this->sendAccessDenied($request);
        }
        
        $this->logger->debug('RBAC check passed', [
            'component' => 'rbac_middleware',
            'action' => 'access_granted',
            'user_id' => $userId,
            'user_role' => $userRole,
            'required_role' => $this->requiredRole,
            'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
        ]);
    }
    
    /**
     * No action needed after request
     */
    public function after($request, $response): void
    {
        // RBAC happens before request, no cleanup needed
    }
    
    /**
     * Check if user role satisfies required role using hierarchy
     */
    private function hasRequiredRole(string $userRole, string $requiredRole): bool
    {
        // Check cache first
        $cacheKey = "rbac:{$userRole}:{$requiredRole}";
        $cached = $this->getCachedPermission($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Calculate permission
        $allowed = isset($this->roleHierarchy[$userRole]) && 
                   in_array($requiredRole, $this->roleHierarchy[$userRole]);
        
        // Cache the result
        $this->cachePermission($cacheKey, $allowed);
        
        return $allowed;
    }
    
    /**
     * Get cached permission result
     */
    private function getCachedPermission(string $key): ?bool
    {
        // Use APCu if available
        if (extension_loaded('apcu') && apcu_enabled()) {
            $result = apcu_fetch($key, $success);
            return $success ? (bool) $result : null;
        }
        
        // Use file cache
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = file_get_contents($filename);
        $cache = json_decode($data, true);
        
        if (!$cache || $cache['expires'] < time()) {
            return null;
        }
        
        return (bool) $cache['value'];
    }
    
    /**
     * Cache permission result
     */
    private function cachePermission(string $key, bool $allowed): void
    {
        // Use APCu if available
        if (extension_loaded('apcu') && apcu_enabled()) {
            apcu_store($key, $allowed, $this->cacheTtl);
            return;
        }
        
        // Use file cache
        $filename = $this->getCacheFilename($key);
        $dir = dirname($filename);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $cache = [
            'value' => $allowed,
            'expires' => time() + $this->cacheTtl
        ];
        
        file_put_contents($filename, json_encode($cache));
    }
    
    /**
     * Get cache filename for permission
     */
    private function getCacheFilename(string $key): string
    {
        $hash = md5($key);
        return __DIR__ . "/../../../var/cache/rbac/{$hash}.json";
    }
    
    /**
     * Send access denied response
     */
    private function sendAccessDenied($request): void
    {
        $isApiRequest = str_starts_with($request->uri, '/api/') || 
                       isset($request->headers['Accept']) && 
                       str_contains($request->headers['Accept'], 'application/json');
        
        if ($isApiRequest) {
            // API response
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json');
            }
            
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => 'You do not have permission to access this resource.',
                    'type' => 'authorization_error'
                ],
                'meta' => [
                    'required_role' => $this->requiredRole,
                    'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
                ]
            ], JSON_PRETTY_PRINT);
        } else {
            // Web request - show access denied page
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: text/html');
            }
            
            echo $this->renderAccessDeniedPage($request);
        }
        
        exit;
    }
    
    /**
     * Render access denied page for web requests
     */
    private function renderAccessDeniedPage($request): string
    {
        $requestId = $request->headers['X-Request-ID'] ?? 'unknown';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - CIS V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%); }
        .error-container { background: rgba(255,255,255,0.95); border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
    </style>
</head>
<body class="min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="error-container p-5 text-center">
                    <i class="fas fa-lock fa-4x text-danger mb-4"></i>
                    <h1 class="h2 mb-3">Access Denied</h1>
                    <p class="lead mb-4">You don't have permission to access this resource.</p>
                    <p class="text-muted mb-4">Required role: <strong>{$this->requiredRole}</strong></p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="/dashboard" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                        </a>
                        <a href="/" class="btn btn-primary">
                            <i class="fas fa-home mr-2"></i>Home
                        </a>
                    </div>
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted">Request ID: {$requestId}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Parse role from middleware parameters (for Router usage)
     */
    public static function parseRole(string $params): string
    {
        // Extract role from parameter like "RBACMiddleware:admin"
        if (str_contains($params, ':')) {
            return explode(':', $params, 2)[1];
        }
        
        return 'viewer';
    }
}
