<?php
declare(strict_types=1);

/**
 * RateLimiterMiddleware.php - Rate limiting middleware
 * 
 * Implements sliding window rate limiting with feature flag support.
 * Protects against abuse and DDoS attacks.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T14:45:00Z
 */

namespace App\Http\Middlewares;

use App\Http\MiddlewareInterface;
use App\Shared\Logging\Logger;

class RateLimiterMiddleware implements MiddlewareInterface
{
    private Logger $logger;
    private int $maxRequests;
    private int $windowSeconds;
    private bool $enabled;
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        
        // Load configuration from environment
        $this->enabled = (bool) ($_ENV['RATE_LIMIT_ENABLED'] ?? true);
        $this->maxRequests = (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 60);
        $this->windowSeconds = (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
    }
    
    /**
     * Check rate limits before request processing
     */
    public function before($request): void
    {
        // Skip if rate limiting is disabled
        if (!$this->enabled) {
            $this->logger->debug('Rate limiting disabled via feature flag', [
                'component' => 'rate_limiter',
                'action' => 'bypass_disabled',
                'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
            ]);
            return;
        }
        
        $clientId = $this->getClientId($request);
        $currentCount = $this->getCurrentCount($clientId);
        
        // Check if limit exceeded
        if ($currentCount >= $this->maxRequests) {
            $this->logger->warning('Rate limit exceeded', [
                'component' => 'rate_limiter',
                'action' => 'limit_exceeded',
                'client_id' => $clientId,
                'current_count' => $currentCount,
                'max_requests' => $this->maxRequests,
                'window_seconds' => $this->windowSeconds,
                'uri' => $request->uri,
                'method' => $request->method,
                'user_agent' => $request->headers['User-Agent'] ?? 'unknown',
                'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
            ]);
            
            $this->sendRateLimitError($request, $currentCount);
        }
        
        // Increment counter
        $this->incrementCounter($clientId);
        
        $this->logger->debug('Rate limit check passed', [
            'component' => 'rate_limiter',
            'action' => 'limit_ok',
            'client_id' => $clientId,
            'current_count' => $currentCount + 1,
            'max_requests' => $this->maxRequests,
            'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
        ]);
    }
    
    /**
     * Add rate limit headers after response
     */
    public function after($request, $response): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $clientId = $this->getClientId($request);
        $currentCount = $this->getCurrentCount($clientId);
        $remaining = max(0, $this->maxRequests - $currentCount);
        $resetTime = $this->getResetTime();
        
        // Add rate limit headers
        if (!headers_sent()) {
            header("X-RateLimit-Limit: {$this->maxRequests}");
            header("X-RateLimit-Remaining: {$remaining}");
            header("X-RateLimit-Reset: {$resetTime}");
            header("X-RateLimit-Window: {$this->windowSeconds}");
        }
        
        // Store in response object for API responses
        if (isset($response->headers)) {
            $response->headers['X-RateLimit-Limit'] = (string) $this->maxRequests;
            $response->headers['X-RateLimit-Remaining'] = (string) $remaining;
            $response->headers['X-RateLimit-Reset'] = (string) $resetTime;
            $response->headers['X-RateLimit-Window'] = (string) $this->windowSeconds;
        }
    }
    
    /**
     * Generate client identifier for rate limiting
     */
    private function getClientId($request): string
    {
        // Use IP address as primary identifier
        $ip = $request->ip;
        
        // Add user ID if authenticated for per-user limits
        $userId = $request->session['user_id'] ?? null;
        
        if ($userId) {
            return "user:{$userId}";
        }
        
        return "ip:{$ip}";
    }
    
    /**
     * Get current request count for client
     */
    private function getCurrentCount(string $clientId): int
    {
        $key = "rate_limit:{$clientId}";
        $currentTime = time();
        
        // Use APCu if available, fallback to file-based storage
        if (extension_loaded('apcu') && apcu_enabled()) {
            return $this->getCurrentCountAPCu($key, $currentTime);
        }
        
        return $this->getCurrentCountFile($key, $currentTime);
    }
    
    /**
     * Get count using APCu cache
     */
    private function getCurrentCountAPCu(string $key, int $currentTime): int
    {
        $data = apcu_fetch($key);
        
        if ($data === false) {
            return 0;
        }
        
        // Clean old entries (sliding window)
        $cutoff = $currentTime - $this->windowSeconds;
        $requests = array_filter($data, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        return count($requests);
    }
    
    /**
     * Get count using file storage
     */
    private function getCurrentCountFile(string $key, int $currentTime): int
    {
        $filename = $this->getStorageFilename($key);
        
        if (!file_exists($filename)) {
            return 0;
        }
        
        $data = file_get_contents($filename);
        if ($data === false) {
            return 0;
        }
        
        $requests = json_decode($data, true) ?? [];
        
        // Clean old entries (sliding window)
        $cutoff = $currentTime - $this->windowSeconds;
        $requests = array_filter($requests, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        return count($requests);
    }
    
    /**
     * Increment request counter
     */
    private function incrementCounter(string $clientId): void
    {
        $key = "rate_limit:{$clientId}";
        $currentTime = time();
        
        if (extension_loaded('apcu') && apcu_enabled()) {
            $this->incrementCounterAPCu($key, $currentTime);
        } else {
            $this->incrementCounterFile($key, $currentTime);
        }
    }
    
    /**
     * Increment counter using APCu
     */
    private function incrementCounterAPCu(string $key, int $currentTime): void
    {
        $data = apcu_fetch($key, $success);
        $requests = $success ? $data : [];
        
        // Add current timestamp
        $requests[] = $currentTime;
        
        // Clean old entries
        $cutoff = $currentTime - $this->windowSeconds;
        $requests = array_filter($requests, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        // Store for window duration + 60 seconds buffer
        apcu_store($key, array_values($requests), $this->windowSeconds + 60);
    }
    
    /**
     * Increment counter using file storage
     */
    private function incrementCounterFile(string $key, int $currentTime): void
    {
        $filename = $this->getStorageFilename($key);
        
        // Ensure directory exists
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Lock file for atomic operation
        $lockFile = $filename . '.lock';
        $lock = fopen($lockFile, 'c');
        
        if (flock($lock, LOCK_EX)) {
            $data = file_exists($filename) ? file_get_contents($filename) : '[]';
            $requests = json_decode($data, true) ?? [];
            
            // Add current timestamp
            $requests[] = $currentTime;
            
            // Clean old entries
            $cutoff = $currentTime - $this->windowSeconds;
            $requests = array_filter($requests, function($timestamp) use ($cutoff) {
                return $timestamp > $cutoff;
            });
            
            // Write back
            file_put_contents($filename, json_encode(array_values($requests)));
            
            flock($lock, LOCK_UN);
        }
        
        fclose($lock);
    }
    
    /**
     * Get storage filename for client
     */
    private function getStorageFilename(string $key): string
    {
        $hash = md5($key);
        return __DIR__ . "/../../../var/cache/rate_limit/{$hash}.json";
    }
    
    /**
     * Get reset timestamp
     */
    private function getResetTime(): int
    {
        return time() + $this->windowSeconds;
    }
    
    /**
     * Send rate limit exceeded error
     */
    private function sendRateLimitError($request, int $currentCount): void
    {
        $retryAfter = $this->windowSeconds;
        
        if (!headers_sent()) {
            http_response_code(429); // Too Many Requests
            header("Retry-After: {$retryAfter}");
            header("X-RateLimit-Limit: {$this->maxRequests}");
            header("X-RateLimit-Remaining: 0");
            header("X-RateLimit-Reset: " . $this->getResetTime());
        }
        
        // Determine response format
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
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                    'type' => 'rate_limit_error'
                ],
                'meta' => [
                    'rate_limit' => [
                        'limit' => $this->maxRequests,
                        'remaining' => 0,
                        'reset' => $this->getResetTime(),
                        'retry_after' => $retryAfter
                    ],
                    'request_id' => $request->headers['X-Request-ID'] ?? 'unknown'
                ]
            ], JSON_PRETTY_PRINT);
        } else {
            echo $this->renderRateLimitErrorPage($request, $retryAfter);
        }
        
        exit;
    }
    
    /**
     * Render rate limit error page
     */
    private function renderRateLimitErrorPage($request, int $retryAfter): string
    {
        $requestId = $request->headers['X-Request-ID'] ?? 'unknown';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Limit Exceeded - CIS V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .error-container { background: rgba(255,255,255,0.95); border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .countdown { font-size: 2rem; font-weight: bold; }
    </style>
</head>
<body class="min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="error-container p-5 text-center">
                    <i class="fas fa-clock fa-4x text-warning mb-4"></i>
                    <h1 class="h2 mb-3">Rate Limit Exceeded</h1>
                    <p class="lead mb-4">You've made too many requests. Please wait before trying again.</p>
                    <div class="countdown text-primary mb-4" id="countdown">{$retryAfter}</div>
                    <p class="text-muted">seconds remaining</p>
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted">Request ID: {$requestId}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        let seconds = {$retryAfter};
        const countdown = document.getElementById('countdown');
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                location.reload();
            }
        }, 1000);
    </script>
</body>
</html>
HTML;
    }
}
