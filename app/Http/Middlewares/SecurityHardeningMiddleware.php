<?php
/**
 * Security Hardening Middleware
 * File: app/Http/Middlewares/SecurityHardeningMiddleware.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Comprehensive security hardening with rate limiting, CSP, and security headers
 */

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Infra\Cache\RedisCache;
use App\Shared\Logging\Logger;

class SecurityHardeningMiddleware
{
    private RedisCache $cache;
    private Logger $logger;
    private array $config;
    
    public function __construct()
    {
        $this->cache = RedisCache::getInstance();
        $this->logger = Logger::getInstance();
        
        $this->config = [
            'rate_limit' => [
                'requests_per_minute' => 60,
                'burst_requests' => 100,
                'window_seconds' => 60,
                'block_duration' => 300 // 5 minutes
            ],
            'security_headers' => [
                'csp_enabled' => true,
                'hsts_max_age' => 31536000, // 1 year
                'frame_options' => 'DENY',
                'content_type_options' => 'nosniff',
                'referrer_policy' => 'strict-origin-when-cross-origin'
            ],
            'session_security' => [
                'secure_cookies' => true,
                'httponly_cookies' => true,
                'samesite_strict' => true,
                'session_timeout' => 3600 // 1 hour
            ]
        ];
    }
    
    /**
     * Execute security hardening middleware
     */
    public function handle(string $method, string $uri, array $headers = []): array
    {
        $startTime = microtime(true);
        $clientIp = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $this->logger->info('Security middleware start', [
            'component' => 'security_middleware',
            'client_ip' => $clientIp,
            'method' => $method,
            'uri' => $uri,
            'user_agent' => $userAgent
        ]);
        
        try {
            // Rate limiting check
            $rateLimitResult = $this->checkRateLimit($clientIp, $method, $uri);
            if (!$rateLimitResult['allowed']) {
                return $this->handleRateLimitExceeded($rateLimitResult);
            }
            
            // Security headers
            $securityHeaders = $this->generateSecurityHeaders($uri);
            
            // Session security
            $this->enforceSessionSecurity();
            
            // Content Security Policy
            $cspHeader = $this->generateCSPHeader($uri);
            if ($cspHeader) {
                $securityHeaders['Content-Security-Policy'] = $cspHeader;
            }
            
            // CSRF protection for state-changing operations
            if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
                $csrfResult = $this->validateCSRF();
                if (!$csrfResult['valid']) {
                    return $this->handleCSRFFailure($csrfResult);
                }
            }
            
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('Security middleware complete', [
                'component' => 'security_middleware',
                'client_ip' => $clientIp,
                'rate_limit_remaining' => $rateLimitResult['remaining'],
                'duration_ms' => round($duration * 1000, 2),
                'headers_count' => count($securityHeaders)
            ]);
            
            return [
                'success' => true,
                'headers' => $securityHeaders,
                'rate_limit' => $rateLimitResult,
                'duration_ms' => round($duration * 1000, 2)
            ];
            
        } catch (\Throwable $e) {
            $this->logger->error('Security middleware error', [
                'component' => 'security_middleware',
                'client_ip' => $clientIp,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Return minimal security headers on error
            return [
                'success' => false,
                'error' => 'Security middleware error',
                'headers' => $this->getMinimalSecurityHeaders()
            ];
        }
    }
    
    /**
     * Rate limiting implementation
     */
    private function checkRateLimit(string $clientIp, string $method, string $uri): array
    {
        $cacheKey = "rate_limit:{$clientIp}";
        $burstKey = "rate_limit_burst:{$clientIp}";
        
        try {
            // Get current request count
            $requests = $this->cache->get($cacheKey);
            $burstRequests = $this->cache->get($burstKey);
            
            if ($requests === null) {
                $requests = 0;
                $burstRequests = 0;
            } else {
                $requests = (int)$requests;
                $burstRequests = (int)$burstRequests;
            }
            
            // Check burst limit (immediate block for rapid requests)
            if ($burstRequests >= $this->config['rate_limit']['burst_requests']) {
                $this->cache->set($burstKey, $burstRequests + 1, 60);
                
                $this->logger->warning('Rate limit burst exceeded', [
                    'component' => 'rate_limiter',
                    'client_ip' => $clientIp,
                    'burst_requests' => $burstRequests,
                    'limit' => $this->config['rate_limit']['burst_requests']
                ]);
                
                return [
                    'allowed' => false,
                    'reason' => 'burst_limit_exceeded',
                    'requests' => $requests,
                    'remaining' => 0,
                    'reset_time' => time() + 60
                ];
            }
            
            // Check normal rate limit
            if ($requests >= $this->config['rate_limit']['requests_per_minute']) {
                $this->cache->set($cacheKey, $requests + 1, $this->config['rate_limit']['window_seconds']);
                $this->cache->set($burstKey, $burstRequests + 1, 60);
                
                $this->logger->warning('Rate limit exceeded', [
                    'component' => 'rate_limiter',
                    'client_ip' => $clientIp,
                    'requests' => $requests,
                    'limit' => $this->config['rate_limit']['requests_per_minute']
                ]);
                
                return [
                    'allowed' => false,
                    'reason' => 'rate_limit_exceeded',
                    'requests' => $requests,
                    'remaining' => 0,
                    'reset_time' => time() + $this->config['rate_limit']['window_seconds']
                ];
            }
            
            // Increment counters
            $newRequests = $requests + 1;
            $newBurstRequests = $burstRequests + 1;
            
            $this->cache->set($cacheKey, $newRequests, $this->config['rate_limit']['window_seconds']);
            $this->cache->set($burstKey, $newBurstRequests, 60);
            
            return [
                'allowed' => true,
                'requests' => $newRequests,
                'remaining' => $this->config['rate_limit']['requests_per_minute'] - $newRequests,
                'reset_time' => time() + $this->config['rate_limit']['window_seconds']
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Rate limit check failed', [
                'component' => 'rate_limiter',
                'client_ip' => $clientIp,
                'error' => $e->getMessage()
            ]);
            
            // Allow request if rate limiting fails
            return [
                'allowed' => true,
                'requests' => 0,
                'remaining' => $this->config['rate_limit']['requests_per_minute'],
                'reset_time' => time() + $this->config['rate_limit']['window_seconds'],
                'error' => 'rate_limit_check_failed'
            ];
        }
    }
    
    /**
     * Generate comprehensive security headers
     */
    private function generateSecurityHeaders(string $uri): array
    {
        $headers = [];
        
        // HSTS (HTTP Strict Transport Security)
        $headers['Strict-Transport-Security'] = 'max-age=' . $this->config['security_headers']['hsts_max_age'] . '; includeSubDomains; preload';
        
        // X-Frame-Options
        $headers['X-Frame-Options'] = $this->config['security_headers']['frame_options'];
        
        // X-Content-Type-Options
        $headers['X-Content-Type-Options'] = $this->config['security_headers']['content_type_options'];
        
        // Referrer Policy
        $headers['Referrer-Policy'] = $this->config['security_headers']['referrer_policy'];
        
        // X-XSS-Protection
        $headers['X-XSS-Protection'] = '1; mode=block';
        
        // Permissions Policy (formerly Feature Policy)
        $headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=(), payment=()';
        
        // Security-related cache headers
        if (str_contains($uri, '/admin') || str_contains($uri, '/api')) {
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, private';
            $headers['Pragma'] = 'no-cache';
            $headers['Expires'] = '0';
        }
        
        return $headers;
    }
    
    /**
     * Generate Content Security Policy header
     */
    private function generateCSPHeader(string $uri): ?string
    {
        if (!$this->config['security_headers']['csp_enabled']) {
            return null;
        }
        
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests"
        ];
        
        // Admin areas get stricter CSP
        if (str_contains($uri, '/admin')) {
            $cspDirectives[] = "report-uri /api/csp-report";
        }
        
        return implode('; ', $cspDirectives);
    }
    
    /**
     * Enforce session security settings
     */
    private function enforceSessionSecurity(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure session security before starting
            ini_set('session.cookie_secure', $this->config['session_security']['secure_cookies'] ? '1' : '0');
            ini_set('session.cookie_httponly', $this->config['session_security']['httponly_cookies'] ? '1' : '0');
            ini_set('session.cookie_samesite', $this->config['session_security']['samesite_strict'] ? 'Strict' : 'Lax');
            ini_set('session.gc_maxlifetime', (string)$this->config['session_security']['session_timeout']);
            ini_set('session.use_strict_mode', '1');
            
            session_start();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $this->config['session_security']['session_timeout']) {
                session_destroy();
                session_start();
                
                $this->logger->info('Session expired', [
                    'component' => 'session_security',
                    'client_ip' => $this->getClientIp()
                ]);
            }
        }
        
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCSRF(): array
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$submittedToken) {
            return [
                'valid' => false,
                'reason' => 'missing_csrf_token'
            ];
        }
        
        if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
            return [
                'valid' => false,
                'reason' => 'invalid_csrf_token'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Handle rate limit exceeded
     */
    private function handleRateLimitExceeded(array $rateLimitResult): array
    {
        http_response_code(429);
        
        $retryAfter = $rateLimitResult['reset_time'] - time();
        header("Retry-After: {$retryAfter}");
        
        return [
            'success' => false,
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
            'headers' => array_merge(
                $this->getMinimalSecurityHeaders(),
                ["Retry-After" => (string)$retryAfter]
            )
        ];
    }
    
    /**
     * Handle CSRF failure
     */
    private function handleCSRFFailure(array $csrfResult): array
    {
        http_response_code(403);
        
        $this->logger->warning('CSRF validation failed', [
            'component' => 'csrf_protection',
            'client_ip' => $this->getClientIp(),
            'reason' => $csrfResult['reason']
        ]);
        
        return [
            'success' => false,
            'error' => 'csrf_token_invalid',
            'message' => 'Invalid or missing CSRF token',
            'headers' => $this->getMinimalSecurityHeaders()
        ];
    }
    
    /**
     * Get minimal security headers for error responses
     */
    private function getMinimalSecurityHeaders(): array
    {
        return [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block'
        ];
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Get current rate limit status for IP
     */
    public function getRateLimitStatus(string $clientIp): array
    {
        $cacheKey = "rate_limit:{$clientIp}";
        $requests = $this->cache->get($cacheKey);
        
        if ($requests === null) {
            $requests = 0;
        } else {
            $requests = (int)$requests;
        }
        
        return [
            'requests' => $requests,
            'remaining' => max(0, $this->config['rate_limit']['requests_per_minute'] - $requests),
            'limit' => $this->config['rate_limit']['requests_per_minute'],
            'window_seconds' => $this->config['rate_limit']['window_seconds']
        ];
    }
    
    /**
     * Generate CSRF token for forms
     */
    public function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}
