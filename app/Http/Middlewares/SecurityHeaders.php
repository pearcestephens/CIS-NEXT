<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Shared\Logging\Logger;
use App\Shared\Config\Config;

/**
 * Security Headers Middleware
 * Adds essential security headers to all responses with enhanced configuration
 * 
 * @package CIS\Http\Middlewares
 * @version 2.0.0
 */
class SecurityHeaders implements MiddlewareInterface
{
    private Logger $logger;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }
    
    public function handle(array $request, callable $next): mixed
    {
        // Set security headers before processing request
        $this->setSecurityHeaders($request);
        
        $this->logger->debug('Security headers middleware executed', [
            'request_id' => $request['HTTP_X_REQUEST_ID'] ?? 'unknown',
            'path' => $request['REQUEST_URI'] ?? '/',
            'method' => $request['REQUEST_METHOD'] ?? 'GET'
        ]);
        
        // Continue to next middleware
        return $next($request);
    }
    
    private function setSecurityHeaders(array $request): void
    {
        // Prevent headers being sent twice
        if (headers_sent()) {
            return;
        }
        
        $isProduction = Config::get('APP_ENV') === 'production';
        $isAdmin = str_starts_with($request['REQUEST_URI'] ?? '', '/admin');
        
        // HTTPS enforcement in production
        if ($isProduction) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Frame protection
        header('X-Frame-Options: DENY');
        
        // Content type sniffing protection
        header('X-Content-Type-Options: nosniff');
        
        // XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = $this->buildCSP($request, $isProduction, $isAdmin);
        header("Content-Security-Policy: {$csp}");
        
        // Feature/Permissions policy
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        
        // Remove server fingerprinting
        header_remove('X-Powered-By');
        header_remove('Server');
        
        // Cache control for sensitive pages
        if ($isAdmin) {
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
            header('Pragma: no-cache');
        }
    }
    
    private function buildCSP(array $request, bool $isProduction, bool $isAdmin): string
    {
        $csp = [
            "default-src 'self'",
            "script-src 'self'" . ($isProduction ? "" : " 'unsafe-inline' 'unsafe-eval'") . " https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'"
        ];
        
        // More restrictive policy for admin areas
        if ($isAdmin) {
            $csp[] = "form-action 'self'";
            $csp[] = "frame-ancestors 'none'";
        }
        
        return implode('; ', $csp);
    }
        
        header("Content-Security-Policy: {$csp}");
        
        return $response;
    }
}
