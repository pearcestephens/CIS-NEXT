<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

/**
 * Authentication Middleware
 * Redirects unauthenticated users for protected routes
 */
class Auth
{
    public function handle(array $request, callable $next): mixed
    {
        $path = $request['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        // Check if route requires authentication
        if (!$this->requiresAuth($path)) {
            return $next($request);
        }
        
        // Check for testing bypass mode
        if ($this->checkTestingBypass()) {
            return $next($request);
        }
        
        // Check if user is authenticated
        $isAuthenticated = isset($_REQUEST['_user']);
        
        if (!$isAuthenticated) {
            if (str_starts_with($path, '/api/')) {
                header('HTTP/1.1 401 Unauthorized');
                return [
                    'json' => [
                        'success' => false,
                        'error' => [
                            'code' => 'AUTHENTICATION_REQUIRED',
                            'message' => 'Authentication required to access this resource',
                        ],
                        'meta' => [
                            'request_id' => $request['HTTP_X_REQUEST_ID'] ?? 'unknown',
                        ],
                    ]
                ];
            }
            
            // Store intended URL for redirect after login
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['intended_url'] = $path;
            session_write_close();
            
            return ['redirect' => '/login'];
        }
        
        return $next($request);
    }
    
    private function requiresAuth(string $path): bool
    {
        // Public routes that don't require authentication
        $publicRoutes = [
            '/',
            '/login',
            '/health',
            '/ready',
            '/favicon.ico',
        ];
        
        // Public route patterns
        $publicPatterns = [
            '#^/assets/#',
            '#^/css/#',
            '#^/js/#',
            '#^/images/#',
        ];
        
        // Check exact matches
        if (in_array($path, $publicRoutes)) {
            return false;
        }
        
        // Check patterns
        foreach ($publicPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return false;
            }
        }
        
        // Everything else requires authentication
        return true;
    }
    
    /**
     * Check if testing bypass mode is enabled
     */
    private function checkTestingBypass(): bool
    {
        // Include bypass configuration if it exists
        $bypassFile = __DIR__ . '/../../../functions/config.php';
        if (file_exists($bypassFile)) {
            require_once $bypassFile;
            
            // Check if TestingBypass class exists and is enabled
            if (class_exists('TestingBypass')) {
                if (\TestingBypass::bypassAuthentication()) {
                    // Set user in request for downstream middleware
                    $user = \TestingBypass::getCurrentUser();
                    if ($user) {
                        // Add permissions to user array for RBAC middleware
                        $user['permissions'] = $_SESSION['permissions'] ?? [];
                        
                        // Also add admin.access permission for dashboard access
                        if (!in_array('admin.access', $user['permissions'])) {
                            $user['permissions'][] = 'admin.access';
                        }
                        
                        $_REQUEST['_user'] = $user;
                        error_log("Auth Middleware: Using testing bypass for user " . $user['email'] . " with " . count($user['permissions']) . " permissions");
                    }
                    return true;
                }
            }
        }
        
        return false;
    }
}
