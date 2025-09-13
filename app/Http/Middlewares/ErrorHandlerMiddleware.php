<?php
declare(strict_types=1);

/**
 * ErrorHandlerMiddleware.php - Centralized error handling and logging
 * 
 * Captures exceptions, formats error responses, and provides structured
 * error logging for debugging and monitoring.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.1
 * @last_modified 2024-12-30T08:30:00Z
 */

namespace App\Http\Middlewares;

use App\Http\MiddlewareInterface;
use App\Shared\Logging\Logger;
use Throwable;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private Logger $logger;
    private bool $debugMode;
    private array $errorContext = [];
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->debugMode = defined('APP_DEBUG') && APP_DEBUG;
        
        // Set up error and exception handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    /**
     * Initialize error handling context
     */
    public function before($request): void
    {
        $this->errorContext = [
            'request_id' => $request->headers['X-Request-ID'] ?? 'unknown',
            'method' => $request->method ?? 'GET',
            'uri' => $request->uri ?? '/',
            'user_agent' => $request->headers['User-Agent'] ?? 'unknown',
            'ip_address' => $this->getClientIp($request),
            'timestamp' => date('c')
        ];
        
        $this->logger->debug('Error handler initialized', [
            'component' => 'error_handler',
            'action' => 'initialize',
            'context' => $this->errorContext
        ]);
    }
    
    /**
     * Clean up error handling (optional)
     */
    public function after($request, $response): void
    {
        // Nothing needed here for normal flow
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $level)) {
            return false;
        }
        
        $errorType = $this->getErrorType($level);
        $severity = $this->getErrorSeverity($level);
        
        $errorData = array_merge($this->errorContext, [
            'component' => 'error_handler',
            'action' => 'php_error',
            'error_type' => $errorType,
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
        ]);
        
        // Log based on severity
        if (in_array($level, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $this->logger->error('PHP Error', $errorData);
        } elseif (in_array($level, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING])) {
            $this->logger->warning('PHP Warning', $errorData);
        } else {
            $this->logger->notice('PHP Notice', $errorData);
        }
        
        // Return false to allow PHP's default error handler to run as well
        return false;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException(Throwable $exception): void
    {
        $exceptionData = array_merge($this->errorContext, [
            'component' => 'error_handler',
            'action' => 'uncaught_exception',
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'stack_trace' => $exception->getTrace()
        ]);
        
        $this->logger->critical('Uncaught Exception', $exceptionData);
        
        // Send error response
        $this->sendErrorResponse(500, 'Internal Server Error', $exception);
    }
    
    /**
     * Handle fatal errors during shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $errorData = array_merge($this->errorContext, [
                'component' => 'error_handler',
                'action' => 'fatal_error',
                'error_type' => $this->getErrorType($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
            
            $this->logger->emergency('Fatal Error', $errorData);
            
            // If headers haven't been sent, send error response
            if (!headers_sent()) {
                $this->sendErrorResponse(500, 'Fatal Error Occurred');
            }
        }
    }
    
    /**
     * Send structured error response
     */
    private function sendErrorResponse(int $statusCode, string $message, Throwable $exception = null): void
    {
        if (headers_sent()) {
            return; // Can't send headers
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => [
                'code' => $statusCode,
                'message' => $message,
                'request_id' => $this->errorContext['request_id'] ?? 'unknown'
            ],
            'timestamp' => date('c')
        ];
        
        // Add debug info if in debug mode
        if ($this->debugMode && $exception) {
            $response['debug'] = [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Get human-readable error type
     */
    private function getErrorType(int $level): string
    {
        $errorTypes = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING', 
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $errorTypes[$level] ?? 'UNKNOWN_ERROR';
    }
    
    /**
     * Get error severity level
     */
    private function getErrorSeverity(int $level): string
    {
        if (in_array($level, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            return 'critical';
        } elseif (in_array($level, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING])) {
            return 'warning';
        } else {
            return 'notice';
        }
    }
    
    /**
     * Get client IP address with proxy support
     */
    private function getClientIp($request): string
    {
        // Check for forwarded IP (reverse proxy/load balancer)
        $forwardedFor = $request->headers['X-Forwarded-For'] ?? '';
        if (!empty($forwardedFor)) {
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }
        
        // Check for real IP header  
        $realIp = $request->headers['X-Real-IP'] ?? '';
        if (!empty($realIp)) {
            return $realIp;
        }
        
        // Fallback to remote address
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Create exception from error context for testing
     */
    public function createExceptionFromError(array $error): \Exception
    {
        return new \Exception(
            $error['message'] ?? 'Unknown error',
            $error['code'] ?? 0
        );
    }
}
