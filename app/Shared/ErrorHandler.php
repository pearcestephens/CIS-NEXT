<?php
/**
 * Advanced Error Handler Service
 * File: app/Shared/ErrorHandler.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Comprehensive error handling with logging, notifications, and recovery
 */

declare(strict_types=1);

namespace App\Shared;

use App\Shared\Logging\Logger;
use App\Infra\Cache\RedisCache;
use App\Models\SystemEvent;

class ErrorHandler
{
    private Logger $logger;
    private RedisCache $cache;
    private array $config;
    private static ?ErrorHandler $instance = null;
    
    private function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->cache = RedisCache::getInstance();
        
        $this->config = [
            'error_levels' => [
                E_ERROR => 'FATAL',
                E_WARNING => 'WARNING', 
                E_PARSE => 'PARSE_ERROR',
                E_NOTICE => 'NOTICE',
                E_CORE_ERROR => 'CORE_ERROR',
                E_CORE_WARNING => 'CORE_WARNING',
                E_COMPILE_ERROR => 'COMPILE_ERROR',
                E_COMPILE_WARNING => 'COMPILE_WARNING',
                E_USER_ERROR => 'USER_ERROR',
                E_USER_WARNING => 'USER_WARNING',
                E_USER_NOTICE => 'USER_NOTICE',
                E_STRICT => 'STRICT',
                E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
                E_DEPRECATED => 'DEPRECATED',
                E_USER_DEPRECATED => 'USER_DEPRECATED'
            ],
            'notification_thresholds' => [
                'error_rate_per_minute' => 10,
                'critical_error_immediate' => true,
                'memory_usage_threshold' => 128 * 1024 * 1024, // 128MB
                'execution_time_threshold' => 30 // 30 seconds
            ],
            'recovery_strategies' => [
                'auto_restart_services' => true,
                'fallback_to_cache' => true,
                'graceful_degradation' => true
            ]
        ];
        
        // Register error handlers
        $this->registerHandlers();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register PHP error handlers
     */
    private function registerHandlers(): void
    {
        // Set error handler
        set_error_handler([$this, 'handleError']);
        
        // Set exception handler
        set_exception_handler([$this, 'handleException']);
        
        // Set fatal error handler
        register_shutdown_function([$this, 'handleFatalError']);
        
        // Set memory limit monitoring
        if (function_exists('memory_get_usage')) {
            $this->startMemoryMonitoring();
        }
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        $errorId = uniqid('err_', true);
        $timestamp = microtime(true);
        
        // Don't handle suppressed errors (@-operator)
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorLevel = $this->config['error_levels'][$severity] ?? 'UNKNOWN';
        
        $errorData = [
            'error_id' => $errorId,
            'timestamp' => $timestamp,
            'level' => $errorLevel,
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'backtrace' => $this->getFilteredBacktrace(),
            'context' => $this->getErrorContext(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        // Log the error
        $this->logError($errorData);
        
        // Check if we need to send notifications
        if ($this->shouldNotify($errorData)) {
            $this->sendErrorNotification($errorData);
        }
        
        // Apply recovery strategies if needed
        if ($this->isCriticalError($errorData)) {
            $this->applyRecoveryStrategy($errorData);
        }
        
        // Track error rate
        $this->trackErrorRate();
        
        // Don't execute PHP's internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException(\Throwable $exception): void
    {
        $errorId = uniqid('exc_', true);
        $timestamp = microtime(true);
        
        $errorData = [
            'error_id' => $errorId,
            'timestamp' => $timestamp,
            'level' => 'EXCEPTION',
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'backtrace' => $this->formatExceptionTrace($exception),
            'context' => $this->getErrorContext(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        // Log the exception
        $this->logError($errorData);
        
        // Always notify for uncaught exceptions
        $this->sendErrorNotification($errorData);
        
        // Apply recovery strategies
        $this->applyRecoveryStrategy($errorData);
        
        // Output user-friendly error page
        $this->displayErrorPage($errorData);
    }
    
    /**
     * Handle fatal errors
     */
    public function handleFatalError(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorId = uniqid('fatal_', true);
            
            $errorData = [
                'error_id' => $errorId,
                'timestamp' => microtime(true),
                'level' => 'FATAL',
                'type' => $this->config['error_levels'][$error['type']] ?? 'FATAL_ERROR',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];
            
            // Log fatal error
            $this->logError($errorData);
            
            // Send immediate notification
            $this->sendErrorNotification($errorData);
            
            // Try recovery
            $this->applyEmergencyRecovery($errorData);
        }
    }
    
    /**
     * Log error with structured data
     */
    private function logError(array $errorData): void
    {
        $logLevel = match ($errorData['level']) {
            'FATAL', 'EXCEPTION', 'CORE_ERROR', 'COMPILE_ERROR' => 'critical',
            'WARNING', 'CORE_WARNING', 'COMPILE_WARNING' => 'warning',
            'NOTICE', 'USER_NOTICE', 'STRICT', 'DEPRECATED' => 'notice',
            default => 'error'
        };
        
        $this->logger->log($logLevel, 'Error occurred', [
            'component' => 'error_handler',
            'error_id' => $errorData['error_id'],
            'error_level' => $errorData['level'],
            'message' => $errorData['message'],
            'file' => $errorData['file'],
            'line' => $errorData['line'],
            'memory_usage_mb' => round($errorData['memory_usage'] / 1024 / 1024, 2),
            'peak_memory_mb' => round($errorData['peak_memory'] / 1024 / 1024, 2),
            'context' => $errorData['context']
        ]);
        
        // Store in system events for tracking
        try {
            $this->storeSystemEvent($errorData);
        } catch (\Throwable $e) {
            // Fallback logging if database is unavailable
            error_log("Failed to store system event: " . $e->getMessage());
        }
    }
    
    /**
     * Get filtered backtrace
     */
    private function getFilteredBacktrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        
        // Remove error handler calls from trace
        $filtered = [];
        foreach ($trace as $frame) {
            if (isset($frame['class']) && $frame['class'] === self::class) {
                continue;
            }
            
            // Remove sensitive information
            unset($frame['args']);
            
            $filtered[] = $frame;
        }
        
        return array_slice($filtered, 0, 10); // Limit to 10 frames
    }
    
    /**
     * Format exception trace
     */
    private function formatExceptionTrace(\Throwable $exception): array
    {
        $trace = $exception->getTrace();
        
        // Remove sensitive arguments
        foreach ($trace as &$frame) {
            unset($frame['args']);
        }
        
        return array_slice($trace, 0, 10);
    }
    
    /**
     * Get error context information
     */
    private function getErrorContext(): array
    {
        return [
            'timestamp' => date('c'),
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_'),
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'session_id' => session_id() ?: null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'php_version' => PHP_VERSION,
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown'
        ];
    }
    
    /**
     * Check if error should trigger notification
     */
    private function shouldNotify(array $errorData): bool
    {
        // Always notify for critical errors
        if ($this->isCriticalError($errorData)) {
            return true;
        }
        
        // Check error rate threshold
        $errorRate = $this->getErrorRate();
        if ($errorRate > $this->config['notification_thresholds']['error_rate_per_minute']) {
            return true;
        }
        
        // Check memory usage
        if ($errorData['memory_usage'] > $this->config['notification_thresholds']['memory_usage_threshold']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if error is critical
     */
    private function isCriticalError(array $errorData): bool
    {
        $criticalLevels = ['FATAL', 'EXCEPTION', 'CORE_ERROR', 'COMPILE_ERROR', 'USER_ERROR'];
        return in_array($errorData['level'], $criticalLevels);
    }
    
    /**
     * Send error notification
     */
    private function sendErrorNotification(array $errorData): void
    {
        try {
            // Store notification in cache for admin dashboard
            $notificationKey = "error_notification:" . $errorData['error_id'];
            $notificationData = [
                'error_id' => $errorData['error_id'],
                'level' => $errorData['level'],
                'message' => $errorData['message'],
                'file' => basename($errorData['file']),
                'line' => $errorData['line'],
                'timestamp' => date('c', (int)$errorData['timestamp']),
                'context' => $errorData['context']
            ];
            
            $this->cache->set($notificationKey, $notificationData, 3600); // 1 hour
            
            // Add to notifications list
            $this->cache->lpush('error_notifications', json_encode($notificationData));
            $this->cache->ltrim('error_notifications', 0, 99); // Keep last 100
            
        } catch (\Throwable $e) {
            error_log("Failed to send error notification: " . $e->getMessage());
        }
    }
    
    /**
     * Apply recovery strategy
     */
    private function applyRecoveryStrategy(array $errorData): void
    {
        if (!$this->config['recovery_strategies']['graceful_degradation']) {
            return;
        }
        
        try {
            // Memory cleanup
            if ($errorData['memory_usage'] > $this->config['notification_thresholds']['memory_usage_threshold']) {
                $this->performMemoryCleanup();
            }
            
            // Cache fallback for database errors
            if (str_contains($errorData['message'], 'database') || str_contains($errorData['message'], 'MySQL')) {
                if ($this->config['recovery_strategies']['fallback_to_cache']) {
                    $this->enableCacheFallback();
                }
            }
            
        } catch (\Throwable $e) {
            error_log("Recovery strategy failed: " . $e->getMessage());
        }
    }
    
    /**
     * Apply emergency recovery for fatal errors
     */
    private function applyEmergencyRecovery(array $errorData): void
    {
        try {
            // Clear problematic cache entries
            $this->cache->flushPattern('temp:*');
            
            // Reset session if corrupted
            if (str_contains($errorData['message'], 'session')) {
                session_destroy();
            }
            
        } catch (\Throwable $e) {
            error_log("Emergency recovery failed: " . $e->getMessage());
        }
    }
    
    /**
     * Track error rate
     */
    private function trackErrorRate(): void
    {
        $key = 'error_rate:' . date('Y-m-d H:i');
        $this->cache->incr($key);
        $this->cache->expire($key, 300); // 5 minutes
    }
    
    /**
     * Get current error rate
     */
    private function getErrorRate(): int
    {
        $key = 'error_rate:' . date('Y-m-d H:i');
        return (int)$this->cache->get($key);
    }
    
    /**
     * Start memory monitoring
     */
    private function startMemoryMonitoring(): void
    {
        register_tick_function(function() {
            static $lastCheck = 0;
            
            if (time() - $lastCheck > 30) { // Check every 30 seconds
                $usage = memory_get_usage(true);
                
                if ($usage > $this->config['notification_thresholds']['memory_usage_threshold']) {
                    $this->logger->warning('High memory usage detected', [
                        'component' => 'memory_monitor',
                        'memory_usage_mb' => round($usage / 1024 / 1024, 2),
                        'threshold_mb' => round($this->config['notification_thresholds']['memory_usage_threshold'] / 1024 / 1024, 2)
                    ]);
                }
                
                $lastCheck = time();
            }
        });
    }
    
    /**
     * Perform memory cleanup
     */
    private function performMemoryCleanup(): void
    {
        // Clear realpath cache
        clearstatcache();
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        $this->logger->info('Memory cleanup performed', [
            'component' => 'memory_cleanup',
            'memory_after_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);
    }
    
    /**
     * Enable cache fallback mode
     */
    private function enableCacheFallback(): void
    {
        $this->cache->set('system:cache_fallback_mode', true, 300); // 5 minutes
        
        $this->logger->info('Cache fallback mode enabled', [
            'component' => 'cache_fallback'
        ]);
    }
    
    /**
     * Store system event
     */
    private function storeSystemEvent(array $errorData): void
    {
        $systemEvent = new SystemEvent();
        
        $eventData = [
            'event_type' => 'error',
            'severity' => $this->mapSeverityLevel($errorData['level']),
            'title' => 'Error: ' . $errorData['level'],
            'description' => $errorData['message'],
            'metadata' => json_encode([
                'error_id' => $errorData['error_id'],
                'file' => $errorData['file'],
                'line' => $errorData['line'],
                'memory_usage' => $errorData['memory_usage'],
                'context' => $errorData['context']
            ]),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $systemEvent->create($eventData);
    }
    
    /**
     * Map error level to severity
     */
    private function mapSeverityLevel(string $level): string
    {
        return match ($level) {
            'FATAL', 'EXCEPTION', 'CORE_ERROR' => 'critical',
            'WARNING', 'CORE_WARNING' => 'high',
            'NOTICE', 'USER_NOTICE' => 'medium',
            'DEPRECATED', 'STRICT' => 'low',
            default => 'medium'
        };
    }
    
    /**
     * Display error page
     */
    private function displayErrorPage(array $errorData): void
    {
        if (php_sapi_name() === 'cli') {
            echo "Fatal Error: " . $errorData['message'] . "\n";
            echo "File: " . $errorData['file'] . ":" . $errorData['line'] . "\n";
            return;
        }
        
        http_response_code(500);
        
        if (headers_sent()) {
            return;
        }
        
        // Check if this is an API request
        $isApiRequest = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') || 
                       str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        
        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'internal_server_error',
                'message' => 'An internal error occurred. Please try again later.',
                'error_id' => $errorData['error_id'],
                'timestamp' => date('c')
            ]);
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            echo $this->generateErrorHtml($errorData);
        }
    }
    
    /**
     * Generate error HTML page
     */
    private function generateErrorHtml(array $errorData): string
    {
        $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';
        
        $errorDetails = '';
        if ($isDev) {
            $errorDetails = "
                <div class='mt-4'>
                    <h6>Error Details (Development Mode)</h6>
                    <p><strong>File:</strong> {$errorData['file']}:{$errorData['line']}</p>
                    <p><strong>Message:</strong> " . htmlspecialchars($errorData['message']) . "</p>
                    <p><strong>Error ID:</strong> {$errorData['error_id']}</p>
                </div>";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Internal Server Error</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body class='bg-light'>
            <div class='container py-5'>
                <div class='row justify-content-center'>
                    <div class='col-md-6'>
                        <div class='card'>
                            <div class='card-body text-center'>
                                <h1 class='text-danger'>500</h1>
                                <h4>Internal Server Error</h4>
                                <p class='text-muted'>Something went wrong on our end. We've been notified and are working to fix it.</p>
                                <p><small class='text-muted'>Error ID: {$errorData['error_id']}</small></p>
                                {$errorDetails}
                                <a href='/' class='btn btn-primary mt-3'>Return Home</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get error statistics
     */
    public function getErrorStats(): array
    {
        try {
            $currentRate = $this->getErrorRate();
            $notifications = $this->cache->lrange('error_notifications', 0, 9);
            
            return [
                'current_error_rate' => $currentRate,
                'rate_threshold' => $this->config['notification_thresholds']['error_rate_per_minute'],
                'recent_notifications' => array_map('json_decode', $notifications),
                'memory_threshold_mb' => round($this->config['notification_thresholds']['memory_usage_threshold'] / 1024 / 1024, 2),
                'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ];
            
        } catch (\Throwable $e) {
            return [
                'error' => 'Failed to get error stats',
                'message' => $e->getMessage()
            ];
        }
    }
}
