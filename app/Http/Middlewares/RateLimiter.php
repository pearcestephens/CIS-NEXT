<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Shared\Config\Config;
use App\Shared\Logging\Logger;

/**
 * Rate Limiter Middleware
 * Protects against excessive requests
 */
class RateLimiter
{
    private int $maxRequests;
    private int $windowSeconds;
    
    public function __construct()
    {
        $this->maxRequests = Config::isDevelopment() ? 1000 : 100; // Liberal for dev
        $this->windowSeconds = 60; // 1 minute window
    }
    
    public function handle(array $request, callable $next): mixed
    {
        $clientIp = $this->getClientIp($request);
        $key = "rate_limit:{$clientIp}";
        
        // Simple file-based rate limiting for now
        $rateLimitFile = sys_get_temp_dir() . '/' . md5($key) . '.rate';
        $now = time();
        
        $attempts = [];
        if (file_exists($rateLimitFile)) {
            $attempts = json_decode(file_get_contents($rateLimitFile), true) ?? [];
        }
        
        // Clean old attempts
        $attempts = array_filter($attempts, function ($timestamp) use ($now) {
            return ($now - $timestamp) < $this->windowSeconds;
        });
        
        // Check if limit exceeded
        if (count($attempts) >= $this->maxRequests) {
            Logger::getInstance()->warning('Rate limit exceeded', [
                'client_ip' => $clientIp,
                'attempts' => count($attempts),
                'window_seconds' => $this->windowSeconds,
            ]);
            
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . $this->windowSeconds);
            header('X-RateLimit-Limit: ' . $this->maxRequests);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . ($now + $this->windowSeconds));
            
            if (str_starts_with($request['REQUEST_URI'] ?? '', '/api/')) {
                return [
                    'json' => [
                        'success' => false,
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many requests. Please try again later.',
                        ],
                        'meta' => [
                            'request_id' => $request['HTTP_X_REQUEST_ID'] ?? 'unknown',
                            'retry_after' => $this->windowSeconds,
                        ],
                    ]
                ];
            }
            
            return ['view' => $this->render429Page()];
        }
        
        // Add current attempt
        $attempts[] = $now;
        file_put_contents($rateLimitFile, json_encode($attempts));
        
        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . max(0, $this->maxRequests - count($attempts)));
        header('X-RateLimit-Reset: ' . ($now + $this->windowSeconds));
        
        return $next($request);
    }
    
    private function getClientIp(array $request): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($request[$header])) {
                $ip = trim(explode(',', $request[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $request['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function render429Page(): string
    {
        ob_start();
        include __DIR__ . '/../Views/errors/429.php';
        return ob_get_clean();
    }
}
