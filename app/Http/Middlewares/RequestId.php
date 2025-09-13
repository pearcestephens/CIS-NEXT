<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Shared\Logging\Logger;

/**
 * Request ID Middleware
 * Generates unique request correlation IDs for tracing
 * 
 * @package CIS\Http\Middlewares  
 * @version 2.0.0
 */
class RequestId implements MiddlewareInterface
{
    private Logger $logger;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }
    
    public function handle(array $request, callable $next): mixed
    {
        // Generate or use existing request ID
        $requestId = $this->getOrGenerateRequestId($request);
        
        // Inject into request for downstream use
        $request['HTTP_X_REQUEST_ID'] = $requestId;
        
        // Set in superglobal for access throughout request
        $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;
        
        // Add to response headers
        if (!headers_sent()) {
            header("X-Request-ID: {$requestId}");
        }
        
        // Update logger context for this request
        $this->logger->withContext(['request_id' => $requestId]);
        
        $this->logger->info('Request started', [
            'request_id' => $requestId,
            'method' => $request['REQUEST_METHOD'] ?? 'GET',
            'path' => $request['REQUEST_URI'] ?? '/',
            'ip' => $this->getClientIp($request),
            'user_agent' => $request['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return $next($request);
    }
    
    private function getOrGenerateRequestId(array $request): string
    {
        // Check if request already has an ID (from load balancer, etc.)
        if (!empty($request['HTTP_X_REQUEST_ID'])) {
            return $request['HTTP_X_REQUEST_ID'];
        }
        
        // Check alternative headers
        $alternativeHeaders = [
            'HTTP_X_CORRELATION_ID',
            'HTTP_X_TRACE_ID', 
            'HTTP_X_AMZN_TRACE_ID',
            'HTTP_CF_RAY'
        ];
        
        foreach ($alternativeHeaders as $header) {
            if (!empty($request[$header])) {
                return $request[$header];
            }
        }
        
        // Generate new UUID v4
        return $this->generateUuid4();
    }
    
    private function getClientIp(array $request): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($request[$header])) {
                $ip = trim(explode(',', $request[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $request['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function generateRequestId(): string
    {
        return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 12);
    }
}
