<?php
/**
 * Global Error Handler Middleware
 * File: app/Http/Middlewares/ErrorPage.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Hardened error handling with recovery pages and security logging
 */

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Shared\Logging\Logger;
use Exception;
use Throwable;

class ErrorPage {
    
    private Logger $logger;
    private array $config;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->config = [
            'show_debug' => $_ENV['APP_DEBUG'] ?? false,
            'log_all_errors' => true,
            'sanitize_output' => true,
            'error_reporting_level' => E_ALL & ~E_DEPRECATED
        ];
        
        $this->setupGlobalHandlers();
    }
    
    /**
     * Setup global error and exception handlers
     */
    private function setupGlobalHandlers(): void {
        // Error handler
        set_error_handler([$this, 'handleError']);
        
        // Exception handler
        set_exception_handler([$this, 'handleException']);
        
        // Fatal error handler
        register_shutdown_function([$this, 'handleFatalError']);
        
        // Set error reporting level
        error_reporting($this->config['error_reporting_level']);
        ini_set('display_errors', $this->config['show_debug'] ? '1' : '0');
        ini_set('log_errors', '1');
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorData = [
            'type' => 'PHP_ERROR',
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => session_id() ?: 'no-session',
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        // Log error with context
        $this->logger->error("PHP Error: $message", $errorData);
        
        // For fatal errors, show error page
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $this->showErrorPage(500, 'Internal Server Error', $errorData);
            return true;
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException(Throwable $exception): void {
        $errorData = [
            'type' => 'UNCAUGHT_EXCEPTION',
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => session_id() ?: 'no-session',
            'user_id' => $_SESSION['user_id'] ?? null,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        // Log exception with full context
        $this->logger->critical("Uncaught Exception: " . $exception->getMessage(), $errorData);
        
        // Show sanitized error page
        $this->showErrorPage(500, 'Internal Server Error', $errorData);
    }
    
    /**
     * Handle fatal errors during shutdown
     */
    public function handleFatalError(): void {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $errorData = [
                'type' => 'FATAL_ERROR',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'timestamp' => date('Y-m-d H:i:s'),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];
            
            $this->logger->critical("Fatal Error: " . $error['message'], $errorData);
            
            // Only show error page if not already sent
            if (!headers_sent()) {
                $this->showErrorPage(500, 'Fatal Error Occurred', $errorData);
            }
        }
    }
    
    /**
     * Show sanitized error page
     */
    public function showErrorPage(int $statusCode, string $title, array $errorData = []): void {
        if (headers_sent()) {
            return;
        }
        
        http_response_code($statusCode);
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: no-referrer');
        
        // Generate error ID for reference
        $errorId = 'ERR_' . date('Ymd_His') . '_' . substr(md5(json_encode($errorData)), 0, 8);
        
        // Check if this is an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'status' => $statusCode,
                'message' => $this->config['show_debug'] ? $title : 'An error occurred',
                'error_id' => $errorId,
                'timestamp' => date('c'),
                'debug_info' => $this->config['show_debug'] ? $errorData : null
            ]);
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            $this->renderErrorTemplate($statusCode, $title, $errorId, $errorData);
        }
        
        exit;
    }
    
    /**
     * Render error page template
     */
    private function renderErrorTemplate(int $statusCode, string $title, string $errorId, array $errorData): void {
        $templatePath = __DIR__ . '/../Views/errors/500.php';
        
        if (file_exists($templatePath)) {
            // Pass variables to template
            $error_code = $statusCode;
            $error_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $error_id = htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8');
            $show_debug = $this->config['show_debug'];
            $debug_data = $this->config['show_debug'] ? $errorData : [];
            
            include $templatePath;
        } else {
            // Fallback minimal error page
            echo $this->getMinimalErrorPage($statusCode, $title, $errorId);
        }
    }
    
    /**
     * Get minimal error page HTML
     */
    private function getMinimalErrorPage(int $statusCode, string $title, string $errorId): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeErrorId = htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8');
        
        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Error $statusCode - CIS System</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
               background: #f8f9fa; margin: 0; padding: 40px 20px; }
        .error-container { max-width: 600px; margin: 0 auto; background: white; 
                          padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-code { color: #dc3545; font-size: 48px; font-weight: bold; margin: 0; }
        .error-title { color: #343a40; font-size: 24px; margin: 10px 0 20px; }
        .error-id { color: #6c757d; font-size: 14px; font-family: monospace; }
        .error-message { color: #6c757d; margin: 20px 0; line-height: 1.5; }
        .back-link { color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class=\"error-container\">
        <div class=\"error-code\">$statusCode</div>
        <div class=\"error-title\">$safeTitle</div>
        <div class=\"error-message\">
            We apologize for the inconvenience. An error has occurred while processing your request.
        </div>
        <div class=\"error-id\">Error ID: $safeErrorId</div>
        <div style=\"margin-top: 30px;\">
            <a href=\"/\" class=\"back-link\">← Return to Home</a>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Middleware handler for request processing
     */
    public function handle($request, $next) {
        try {
            return $next($request);
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
