<?php
declare(strict_types=1);

/**
 * CSRFMiddleware.php - CSRF Protection Middleware
 * 
 * Validates CSRF tokens for state-changing requests while allowing
 * health endpoints and read-only operations to bypass validation.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T14:45:00Z
 */

namespace App\Http\Middlewares;

use App\Http\MiddlewareInterface;
use App\Shared\Logging\Logger;

class CSRFMiddleware implements MiddlewareInterface
{
    private Logger $logger;
    private array $excludedPaths = [
        '/_health',
        '/_selftest', 
        '/_ready',
        '/api/health',
        '/api/selftest',
        '/api/ready'
    ];
    
    private array $excludedMethods = ['GET', 'HEAD', 'OPTIONS'];
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Validate CSRF token for state-changing requests
     */
    public function before($request): void
    {
        // Skip CSRF for excluded paths
        if ($this->shouldSkipCSRF($request)) {
            return;
        }
        
        // Skip CSRF for safe HTTP methods
        if (in_array($request->method, $this->excludedMethods)) {
            return;
        }
        
        $token = $this->extractToken($request);
        
        if (!$this->isValidToken($token, $request)) {
            $this->logger->warning('CSRF token validation failed', [
                'component' => 'csrf_middleware',
                'action' => 'token_invalid',
                'method' => $request->method,
                'uri' => $request->uri,
                'token_provided' => !empty($token),
                'ip' => $request->ip,
                'user_agent' => $request->headers['User-Agent'] ?? 'unknown',
                'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
            ]);
            
            $this->sendCSRFError($request);
        }
        
        $this->logger->debug('CSRF token validated', [
            'component' => 'csrf_middleware',
            'action' => 'token_valid',
            'method' => $request->method,
            'uri' => $request->uri,
            'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
        ]);
    }
    
    /**
     * No action needed after request processing
     */
    public function after($request, $response): void
    {
        // CSRF validation happens before request, no cleanup needed
    }
    
    /**
     * Check if CSRF validation should be skipped
     */
    private function shouldSkipCSRF($request): bool
    {
        $path = parse_url($request->uri, PHP_URL_PATH);
        
        foreach ($this->excludedPaths as $excludedPath) {
            if ($path === $excludedPath || str_starts_with($path, $excludedPath)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract CSRF token from request
     */
    private function extractToken($request): ?string
    {
        // Check POST data first
        if (isset($request->post['csrf_token'])) {
            return $request->post['csrf_token'];
        }
        
        // Check JSON body
        if (isset($request->json['csrf_token'])) {
            return $request->json['csrf_token'];
        }
        
        // Check headers (for AJAX requests)
        if (isset($request->headers['X-CSRF-Token'])) {
            return $request->headers['X-CSRF-Token'];
        }
        
        if (isset($request->headers['X-CSRF-TOKEN'])) {
            return $request->headers['X-CSRF-TOKEN'];
        }
        
        return null;
    }
    
    /**
     * Validate CSRF token against session
     */
    private function isValidToken(?string $token, $request): bool
    {
        if (empty($token)) {
            return false;
        }
        
        // Start session if not already started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        
        if (empty($sessionToken)) {
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Generate new CSRF token for session
     */
    public static function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }
    
    /**
     * Get current CSRF token from session
     */
    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            return self::generateToken();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Send CSRF error response and terminate
     */
    private function sendCSRFError($request): void
    {
        if (!headers_sent()) {
            http_response_code(419); // Page Expired (Laravel convention for CSRF)
        }
        
        // Determine response format based on request
        $isApiRequest = str_starts_with($request->uri, '/api/') || 
                       isset($request->headers['Accept']) && 
                       str_contains($request->headers['Accept'], 'application/json');
        
        if ($isApiRequest) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'CSRF_TOKEN_MISMATCH',
                    'message' => 'CSRF token validation failed. Please refresh and try again.',
                    'type' => 'security_error'
                ],
                'meta' => [
                    'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
                ]
            ], JSON_PRETTY_PRINT);
        } else {
            // HTML response
            if (!headers_sent()) {
                header('Content-Type: text/html');
            }
            
            echo $this->renderCSRFErrorPage($request);
        }
        
        exit;
    }
    
    /**
     * Render CSRF error page for web requests
     */
    private function renderCSRFErrorPage($request): string
    {
        $requestId = $request->headers['X-Request-ID'] ?? 'unknown';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Token Expired - CIS V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); }
        .error-container { background: rgba(255,255,255,0.95); border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
    </style>
</head>
<body class="min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="error-container p-5 text-center">
                    <i class="fas fa-shield-alt fa-4x text-danger mb-4"></i>
                    <h1 class="h2 mb-3">Security Token Expired</h1>
                    <p class="lead mb-4">Your security token has expired for protection against cross-site request forgery.</p>
                    <p class="text-muted mb-4">Please refresh the page and try your request again.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button onclick="history.back()" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left mr-2"></i>Go Back
                        </button>
                        <button onclick="location.reload()" class="btn btn-primary">
                            <i class="fas fa-redo mr-2"></i>Refresh Page
                        </button>
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
}
