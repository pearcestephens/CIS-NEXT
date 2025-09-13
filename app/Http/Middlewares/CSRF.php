<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Shared\Config\Config;

/**
 * CSRF Protection Middleware
 * Validates CSRF tokens for state-changing requests
 */
class CSRF
{
    public function handle(array $request, callable $next): mixed
    {
        $method = $request['REQUEST_METHOD'] ?? 'GET';
        
        // Only check CSRF for state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }
        
        // Skip CSRF for API endpoints using API tokens
        if (str_starts_with($request['REQUEST_URI'] ?? '', '/api/') && 
            isset($request['HTTP_AUTHORIZATION'])) {
            return $next($request);
        }
        
        $token = $this->getTokenFromRequest($request);
        
        if (!$this->isValidToken($token)) {
            if (str_starts_with($request['REQUEST_URI'] ?? '', '/api/')) {
                header('HTTP/1.1 403 Forbidden');
                return [
                    'json' => [
                        'success' => false,
                        'error' => [
                            'code' => 'CSRF_TOKEN_INVALID',
                            'message' => 'CSRF token validation failed',
                        ],
                        'meta' => [
                            'request_id' => $request['HTTP_X_REQUEST_ID'] ?? 'unknown',
                        ],
                    ]
                ];
            }
            
            header('HTTP/1.1 403 Forbidden');
            return ['view' => $this->render403Page()];
        }
        
        return $next($request);
    }
    
    private function getTokenFromRequest(array $request): ?string
    {
        // Check POST data first
        if (isset($_POST['_token'])) {
            return $_POST['_token'];
        }
        
        // Check headers
        if (isset($request['HTTP_X_CSRF_TOKEN'])) {
            return $request['HTTP_X_CSRF_TOKEN'];
        }
        
        return null;
    }
    
    private function isValidToken(?string $token): bool
    {
        if (!$token) {
            return false;
        }
        
        // Session should already be active from index.php
        $sessionToken = $_SESSION['_csrf_token'] ?? null;
        
        return $sessionToken && hash_equals($sessionToken, $token);
    }
    
    private function render403Page(): string
    {
        ob_start();
        include __DIR__ . '/../Views/errors/403.php';
        return ob_get_clean();
    }
    
    public static function generateToken(): string
    {
        // Session should already be active from index.php
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['_csrf_token'];
    }
}
