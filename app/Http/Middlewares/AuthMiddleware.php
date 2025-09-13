<?php
declare(strict_types=1);

/**
 * AuthMiddleware.php - Authentication middleware
 * 
 * Ensures users are authenticated before accessing protected routes.
 * Redirects unauthenticated users to login page.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T14:45:00Z
 */

namespace App\Http\Middlewares;

use App\Http\MiddlewareInterface;
use App\Shared\Logging\Logger;

class AuthMiddleware implements MiddlewareInterface
{
    private Logger $logger;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Handle the request through middleware (required by MiddlewareInterface)
     */
    public function handle(array $request, callable $next): mixed
    {
        // Check if route requires authentication
        $path = $request['REQUEST_URI'] ?? '/';
        if (!$this->requiresAuth($path)) {
            return $next($request);
        }
        
        // Check if user is authenticated via session
        if (!$this->isAuthenticated()) {
            // Not authenticated - redirect to login
            if (str_starts_with($path, '/api/')) {
                header('HTTP/1.1 401 Unauthorized');
                return [
                    'json' => [
                        'success' => false,
                        'error' => [
                            'code' => 'AUTHENTICATION_REQUIRED',
                            'message' => 'Authentication required to access this resource',
                        ]
                    ]
                ];
            } else {
                return ['redirect' => '/login'];
            }
        }
        
        return $next($request);
    }
    
    /**
     * Check if user is authenticated
     */
    private function isAuthenticated(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Check if path requires authentication
     */
    private function requiresAuth(string $path): bool
    {
        $publicRoutes = [
            '/',
            '/login',
            '/health',
            '/ready',
            '/favicon.ico',
        ];
        
        return !in_array($path, $publicRoutes);
    }
    
    /**
     * Check authentication before request processing (legacy method)
     */
    public function before($request): void
    {
        if (!$this->isAuthenticated()) {
            $this->logger->info('Unauthenticated access attempt', [
                'component' => 'auth_middleware',
                'action' => 'access_denied',
                'uri' => $request->uri ?? 'unknown',
                'method' => $request->method ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? 'unknown'
            ]);
            
            $this->sendAuthRequired($request);
        }
        
        // Update last activity
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['last_activity'] = time();
        }
    }
    
    /**
     * No action needed after request
     */
    public function after($request, $response): void
    {
        // Authentication happens before request, no cleanup needed
    }
    
    /**
     * Send authentication required response
     */
    private function sendAuthRequired($request): void
    {
        $isApiRequest = str_starts_with($request->uri, '/api/') || 
                       isset($request->headers['Accept']) && 
                       str_contains($request->headers['Accept'], 'application/json');
        
        if ($isApiRequest) {
            // API response
            if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json');
            }
            
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_REQUIRED',
                    'message' => 'Authentication is required to access this resource.',
                    'type' => 'auth_error'
                ],
                'meta' => [
                    'login_url' => '/login',
                    'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
                ]
            ], JSON_PRETTY_PRINT);
        } else {
            // Web request - redirect to login
            $loginUrl = '/login';
            
            // Add return URL parameter
            if ($request->uri !== '/') {
                $loginUrl .= '?return=' . urlencode($request->uri);
            }
            
            if (!headers_sent()) {
                http_response_code(302);
                header("Location: {$loginUrl}");
            }
        }
        
        exit;
    }
}
