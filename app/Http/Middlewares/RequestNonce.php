<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

/**
 * RequestNonce Middleware
 * Generates CSP nonce for secure inline script execution
 */
class RequestNonce implements MiddlewareInterface
{
    public function handle(array $request, callable $next): mixed
    {
        // Generate cryptographically secure nonce
        $nonce = base64_encode(random_bytes(16));
        
        // Store in GLOBALS for access in views
        $GLOBALS['csp_nonce'] = $nonce;
        
        // Also store in request for middleware chain access
        $_REQUEST['_csp_nonce'] = $nonce;
        
        return $next($request);
    }
}
