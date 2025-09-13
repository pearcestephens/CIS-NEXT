<?php
/**
 * Hardened Rate Limiting Middleware
 * File: app/Http/Middlewares/RateLimit.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Advanced rate limiting with exponential backoff and per-user/IP tracking
 */

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Shared\Logging\Logger;

class RateLimit {
    
    private Logger $logger;
    private array $config;
    private string $storageFile;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->storageFile = __DIR__ . '/../../../var/cache/rate_limits.json';
        $this->config = [
            'global_rpm' => 1000,           // Global requests per minute
            'ip_rpm' => 60,                 // Per-IP requests per minute
            'user_rpm' => 120,              // Per-user requests per minute
            'burst_allowance' => 10,        // Burst requests allowed
            'exponential_base' => 2,        // Exponential backoff base
            'max_penalty_minutes' => 60,    // Maximum penalty duration
            'cleanup_interval' => 300,      // Cleanup old entries every 5 minutes
            'whitelist_ips' => [
                '127.0.0.1',
                '::1',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16'
            ],
            'high_risk_paths' => [
                '/login',
                '/admin',
                '/api',
                '/reset-password'
            ]
        ];
        
        $this->ensureStorageDirectory();
    }
    
    /**
     * Rate limit middleware handler
     */
    public function handle($request, $next) {
        $clientIp = $this->getClientIp();
        $userId = $_SESSION['user_id'] ?? null;
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Skip rate limiting for whitelisted IPs
        if ($this->isWhitelistedIp($clientIp)) {
            return $next($request);
        }
        
        // Load current rate limit data
        $rateLimits = $this->loadRateLimits();
        
        // Clean up old entries
        $this->cleanupOldEntries($rateLimits);
        
        // Check rate limits
        $limitResult = $this->checkRateLimits($clientIp, $userId, $path, $rateLimits);
        
        if (!$limitResult['allowed']) {
            $this->logRateLimitViolation($clientIp, $userId, $path, $limitResult);
            $this->respondWithRateLimit($limitResult);
            return;
        }
        
        // Record successful request
        $this->recordRequest($clientIp, $userId, $path, $rateLimits);
        
        // Save updated rate limits
        $this->saveRateLimits($rateLimits);
        
        return $next($request);
    }
    
    /**
     * Check rate limits for IP and user
     */
    private function checkRateLimits(string $clientIp, ?int $userId, string $path, array &$rateLimits): array {
        $currentTime = time();
        $currentMinute = floor($currentTime / 60);
        $isHighRisk = $this->isHighRiskPath($path);
        
        // Adjust limits for high-risk paths
        $ipLimit = $isHighRisk ? floor($this->config['ip_rpm'] * 0.3) : $this->config['ip_rpm'];
        $userLimit = $isHighRisk ? floor($this->config['user_rpm'] * 0.5) : $this->config['user_rpm'];
        
        $result = [
            'allowed' => true,
            'reason' => '',
            'retry_after' => 0,
            'current_requests' => 0,
            'limit' => 0,
            'reset_time' => ($currentMinute + 1) * 60
        ];
        
        // Check IP-based limits
        $ipKey = "ip:{$clientIp}";
        if (!isset($rateLimits[$ipKey])) {
            $rateLimits[$ipKey] = [
                'requests' => [],
                'violations' => 0,
                'penalty_until' => 0,
                'last_violation' => 0
            ];
        }
        
        // Check if IP is under penalty
        if ($rateLimits[$ipKey]['penalty_until'] > $currentTime) {
            $result['allowed'] = false;
            $result['reason'] = 'IP penalty active';
            $result['retry_after'] = $rateLimits[$ipKey]['penalty_until'] - $currentTime;
            return $result;
        }
        
        // Count current minute requests for IP
        $ipRequests = $this->countRequestsInMinute($rateLimits[$ipKey]['requests'], $currentMinute);
        
        if ($ipRequests >= $ipLimit) {
            // Apply exponential backoff penalty
            $penalty = $this->calculatePenalty($rateLimits[$ipKey]['violations']);
            $rateLimits[$ipKey]['violations']++;
            $rateLimits[$ipKey]['penalty_until'] = $currentTime + $penalty;
            $rateLimits[$ipKey]['last_violation'] = $currentTime;
            
            $result['allowed'] = false;
            $result['reason'] = 'IP rate limit exceeded';
            $result['retry_after'] = $penalty;
            $result['current_requests'] = $ipRequests;
            $result['limit'] = $ipLimit;
            return $result;
        }
        
        // Check user-based limits if user is authenticated
        if ($userId) {
            $userKey = "user:{$userId}";
            if (!isset($rateLimits[$userKey])) {
                $rateLimits[$userKey] = [
                    'requests' => [],
                    'violations' => 0,
                    'penalty_until' => 0,
                    'last_violation' => 0
                ];
            }
            
            // Check if user is under penalty
            if ($rateLimits[$userKey]['penalty_until'] > $currentTime) {
                $result['allowed'] = false;
                $result['reason'] = 'User penalty active';
                $result['retry_after'] = $rateLimits[$userKey]['penalty_until'] - $currentTime;
                return $result;
            }
            
            $userRequests = $this->countRequestsInMinute($rateLimits[$userKey]['requests'], $currentMinute);
            
            if ($userRequests >= $userLimit) {
                $penalty = $this->calculatePenalty($rateLimits[$userKey]['violations']);
                $rateLimits[$userKey]['violations']++;
                $rateLimits[$userKey]['penalty_until'] = $currentTime + $penalty;
                $rateLimits[$userKey]['last_violation'] = $currentTime;
                
                $result['allowed'] = false;
                $result['reason'] = 'User rate limit exceeded';
                $result['retry_after'] = $penalty;
                $result['current_requests'] = $userRequests;
                $result['limit'] = $userLimit;
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Record successful request
     */
    private function recordRequest(string $clientIp, ?int $userId, string $path, array &$rateLimits): void {
        $currentTime = time();
        $currentMinute = floor($currentTime / 60);
        
        // Record IP request
        $ipKey = "ip:{$clientIp}";
        $rateLimits[$ipKey]['requests'][] = $currentMinute;
        
        // Record user request if authenticated
        if ($userId) {
            $userKey = "user:{$userId}";
            $rateLimits[$userKey]['requests'][] = $currentMinute;
        }
    }
    
    /**
     * Calculate exponential backoff penalty
     */
    private function calculatePenalty(int $violations): int {
        $penalty = min(
            pow($this->config['exponential_base'], $violations) * 60,
            $this->config['max_penalty_minutes'] * 60
        );
        return (int)$penalty;
    }
    
    /**
     * Count requests in current minute
     */
    private function countRequestsInMinute(array $requests, int $currentMinute): int {
        return count(array_filter($requests, function($minute) use ($currentMinute) {
            return $minute >= $currentMinute;
        }));
    }
    
    /**
     * Clean up old entries
     */
    private function cleanupOldEntries(array &$rateLimits): void {
        $currentTime = time();
        $currentMinute = floor($currentTime / 60);
        $cutoffMinute = $currentMinute - 5; // Keep last 5 minutes
        
        foreach ($rateLimits as $key => &$data) {
            // Remove old requests
            $data['requests'] = array_filter($data['requests'], function($minute) use ($cutoffMinute) {
                return $minute > $cutoffMinute;
            });
            
            // Reset violations if last violation was more than 1 hour ago
            if (isset($data['last_violation']) && ($currentTime - $data['last_violation']) > 3600) {
                $data['violations'] = 0;
            }
            
            // Remove penalty if expired
            if (isset($data['penalty_until']) && $data['penalty_until'] < $currentTime) {
                $data['penalty_until'] = 0;
            }
        }
        
        // Remove empty entries
        $rateLimits = array_filter($rateLimits, function($data) {
            return !empty($data['requests']) || $data['penalty_until'] > time() || $data['violations'] > 0;
        });
    }
    
    /**
     * Check if IP is whitelisted
     */
    private function isWhitelistedIp(string $ip): bool {
        foreach ($this->config['whitelist_ips'] as $whitelisted) {
            if ($this->ipInRange($ip, $whitelisted)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange(string $ip, string $range): bool {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        [$subnet, $mask] = explode('/', $range);
        return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
    }
    
    /**
     * Check if path is high-risk
     */
    private function isHighRiskPath(string $path): bool {
        foreach ($this->config['high_risk_paths'] as $pattern) {
            if (strpos($path, $pattern) === 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
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
     * Log rate limit violation
     */
    private function logRateLimitViolation(string $clientIp, ?int $userId, string $path, array $limitResult): void {
        $this->logger->warning('Rate limit violation', [
            'client_ip' => $clientIp,
            'user_id' => $userId,
            'path' => $path,
            'reason' => $limitResult['reason'],
            'retry_after' => $limitResult['retry_after'],
            'current_requests' => $limitResult['current_requests'] ?? 0,
            'limit' => $limitResult['limit'] ?? 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    }
    
    /**
     * Respond with rate limit headers and error
     */
    private function respondWithRateLimit(array $limitResult): void {
        http_response_code(429);
        
        header('X-RateLimit-Limit: ' . ($limitResult['limit'] ?? 'N/A'));
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . ($limitResult['reset_time'] ?? time() + 60));
        header('Retry-After: ' . $limitResult['retry_after']);
        header('Content-Type: application/json');
        
        $response = [
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $limitResult['retry_after'],
            'limit' => $limitResult['limit'] ?? null,
            'remaining' => 0,
            'reset' => $limitResult['reset_time'] ?? time() + 60
        ];
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Load rate limits from storage
     */
    private function loadRateLimits(): array {
        if (!file_exists($this->storageFile)) {
            return [];
        }
        
        $data = file_get_contents($this->storageFile);
        return $data ? json_decode($data, true) : [];
    }
    
    /**
     * Save rate limits to storage
     */
    private function saveRateLimits(array $rateLimits): void {
        file_put_contents($this->storageFile, json_encode($rateLimits, JSON_PRETTY_PRINT));
    }
    
    /**
     * Ensure storage directory exists
     */
    private function ensureStorageDirectory(): void {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
