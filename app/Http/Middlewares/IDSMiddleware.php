<?php
/**
 * IDS/IPS Middleware Integration - Stage 12 Second Hardening Pass
 * File: app/Http/Middlewares/IDSMiddleware.php
 * Purpose: Integrate IDS engine into request pipeline
 */

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Security\IDSEngine;

class IDSMiddleware
{
    private IDSEngine $idsEngine;
    private bool $enabled;
    private array $whitelist;
    
    public function __construct()
    {
        $this->idsEngine = new IDSEngine();
        $this->enabled = true; // Can be configured via environment
        $this->whitelist = [
            '127.0.0.1',
            '::1',
            // Add trusted IPs here
        ];
    }
    
    /**
     * Process request through IDS/IPS engine
     */
    public function handle($request, $next)
    {
        // Skip if IDS is disabled
        if (!$this->enabled) {
            return $next($request);
        }
        
        $clientIP = $this->getClientIP();
        
        // Skip whitelist IPs
        if (in_array($clientIP, $this->whitelist)) {
            return $next($request);
        }
        
        // Prepare request data for scanning
        $requestData = [
            'get' => $_GET ?? [],
            'post' => $_POST ?? [],
            'headers' => $this->getHeaders(),
            'cookies' => $_COOKIE ?? [],
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ];
        
        // Scan request through IDS
        $scanResult = $this->idsEngine->scanRequest($requestData);
        
        // Handle scan results
        switch ($scanResult['action']) {
            case 'BLOCK':
            case 'DENY_REQUEST':
                return $this->handleBlockedRequest($scanResult);
                
            case 'SANITIZE':
                $this->sanitizeRequest($scanResult);
                break;
                
            case 'RATE_LIMIT':
                return $this->handleRateLimit($scanResult);
                
            case 'LOG':
            case 'ALLOW':
            default:
                // Continue with request
                break;
        }
        
        // Add IDS scan result to request for downstream processing
        $request->ids_scan_result = $scanResult;
        
        return $next($request);
    }
    
    /**
     * Handle blocked requests
     */
    private function handleBlockedRequest(array $scanResult): void
    {
        $this->logSecurityEvent('REQUEST_BLOCKED', $scanResult);
        
        http_response_code(403);
        header('Content-Type: application/json');
        
        $response = [
            'error' => 'Access Denied',
            'message' => 'Your request has been blocked by security systems',
            'code' => 'SECURITY_VIOLATION',
            'timestamp' => time(),
            'request_id' => $this->generateRequestId()
        ];
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Handle rate limiting
     */
    private function handleRateLimit(array $scanResult)
    {
        $this->logSecurityEvent('RATE_LIMITED', $scanResult);
        
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: 300'); // 5 minutes
        
        $response = [
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => 300,
            'timestamp' => time()
        ];
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Sanitize request data
     */
    private function sanitizeRequest(array $scanResult): void
    {
        foreach ($scanResult['details'] as $violation) {
            if ($violation['action'] === 'SANITIZE') {
                $field = $violation['field'];
                
                // Sanitize GET parameters
                if (strpos($field, 'get.') === 0) {
                    $key = substr($field, 4);
                    if (isset($_GET[$key])) {
                        $_GET[$key] = $this->sanitizeValue($_GET[$key]);
                    }
                }
                
                // Sanitize POST parameters
                if (strpos($field, 'post.') === 0) {
                    $key = substr($field, 5);
                    if (isset($_POST[$key])) {
                        $_POST[$key] = $this->sanitizeValue($_POST[$key]);
                    }
                }
            }
        }
        
        $this->logSecurityEvent('REQUEST_SANITIZED', $scanResult);
    }
    
    /**
     * Sanitize individual values
     */
    private function sanitizeValue(string $value): string
    {
        // Remove script tags
        $value = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $value);
        
        // Remove event handlers
        $value = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $value);
        
        // Remove javascript: URLs
        $value = preg_replace('/javascript\s*:/i', '', $value);
        
        // HTML encode dangerous characters
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $value;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get request headers
     */
    private function getHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('HTTP_', '', $key);
                $header = str_replace('_', '-', $header);
                $headers[strtolower($header)] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent(string $event, array $data): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'event' => $event,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'data' => $data,
            'request_id' => $this->generateRequestId()
        ];
        
        $logFile = __DIR__ . '/../../var/logs/ids_middleware.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }
    
    /**
     * Get IDS engine instance (for admin purposes)
     */
    public function getIDSEngine(): IDSEngine
    {
        return $this->idsEngine;
    }
    
    /**
     * Enable/disable IDS
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
    
    /**
     * Add IP to whitelist
     */
    public function addToWhitelist(string $ip): void
    {
        if (!in_array($ip, $this->whitelist)) {
            $this->whitelist[] = $ip;
        }
    }
    
    /**
     * Remove IP from whitelist
     */
    public function removeFromWhitelist(string $ip): void
    {
        $this->whitelist = array_filter($this->whitelist, function($whitelistedIP) use ($ip) {
            return $whitelistedIP !== $ip;
        });
        
        $this->whitelist = array_values($this->whitelist);
    }
    
    /**
     * Get current statistics
     */
    public function getStatistics(): array
    {
        return [
            'ids_enabled' => $this->enabled,
            'whitelist_count' => count($this->whitelist),
            'whitelist_ips' => $this->whitelist,
            'ids_stats' => $this->idsEngine->getStatistics()
        ];
    }
}
