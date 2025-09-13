<?php

declare(strict_types=1);

namespace App\Shared;

use App\Shared\Config\Config;
use App\Shared\Logging\Logger;
use App\Infra\Persistence\MariaDB\Database;
use App\Http\Router; // <-- adjust if your Router lives elsewhere

/**
 * Application Bootstrap
 * Initializes core services and dependencies
 */
class Bootstrap
{
    private static bool $initialized = false;
    private static string $rootPath;

    private Logger $logger;
    private Router $router;

    public function __construct(Router $router)
    {
        $this->logger = Logger::getInstance();
        $this->router = $router;
    }

    public static function init(string $rootPath): void
    {
        if (self::$initialized) {
            return;
        }

        self::$rootPath = $rootPath;

        // Autoloader
        self::setupAutoloader();

        // Load environment
        Config::load($rootPath . '/.env');

        // Logging
        Logger::initialize([
            'path'  => $rootPath . '/var/logs',
            'level' => Config::get('LOG_LEVEL', 'info'),
        ]);

        // Database - with connection error handling
        try {
            Database::initialize([
                'host'         => Config::get('DB_HOST', '127.0.0.1'),
                'port'         => (int) Config::get('DB_PORT', '3306'),
                'database'     => Config::get('DB_NAME'),
                'username'     => Config::get('DB_USER'),
                'password'     => Config::get('DB_PASS'),
                'table_prefix' => Config::get('DB_TABLE_PREFIX', 'cis_'),
                'charset'      => Config::get('DB_CHARSET', 'utf8mb4'),
            ]);
        } catch (\Throwable $e) {
            // Log database connection failure but continue bootstrap
            error_log("Database connection failed: " . $e->getMessage());
            // Set a flag that database is unavailable
            define('DB_UNAVAILABLE', true);
        }

        self::setupBasicErrorHandling();
        self::setupSessionSecurity();

        self::$initialized = true;

        Logger::getInstance()->info('Application bootstrapped', [
            'environment' => Config::get('APP_ENV'),
            'debug'       => Config::get('APP_DEBUG'),
        ]);
    }

    private static function setupAutoloader(): void
    {
        spl_autoload_register(function (string $className): void {
            if (!str_starts_with($className, 'App\\')) {
                return;
            }
            $relative = str_replace('App\\', '', $className);
            $file = self::$rootPath . '/app/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    private static function setupBasicErrorHandling(): void
    {
        // Phase 0: Basic error handling without complex dependencies
        set_error_handler(function($severity, $message, $file, $line) {
            if (class_exists('\App\Shared\Logging\Logger')) {
                try {
                    Logger::getInstance()->error('PHP Error', [
                        'severity' => $severity,
                        'message' => $message,
                        'file' => $file,
                        'line' => $line
                    ]);
                } catch (\Throwable $e) {
                    error_log("Logger error: {$e->getMessage()}");
                }
            }
            
            // Don't suppress errors in development
            if (Config::get('APP_DEBUG') === 'true') {
                return false; // Let PHP handle it
            }
            
            return true; // Suppress in production
        });
        
        register_shutdown_function(function(): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                if (class_exists('\App\Shared\Logging\Logger')) {
                    try {
                        Logger::getInstance()->critical('Fatal Bootstrap Error', [
                            'type' => $error['type'],
                            'message' => $error['message'],
                            'file' => $error['file'],
                            'line' => $error['line']
                        ]);
                    } catch (\Throwable $e) {
                        error_log("Fatal error logging failed: {$e->getMessage()}");
                    }
                }

                if (Config::get('APP_ENV') === 'production') {
                    http_response_code(500);
                    echo 'Internal Server Error';
                    return;
                }
            }
        });
    }

    private static function setupSessionSecurity(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            // Enhanced session security
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', '3600'); // 1 hour
            ini_set('session.cookie_lifetime', '0'); // Session cookies
            // Only secure cookies over HTTPS
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - (int)$_SESSION['created'] > 3600) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }

    public static function getRootPath(): string
    {
        return self::$rootPath;
    }

    /**
     * Run complete application lifecycle with middleware pipeline
     */
    public function run(): void
    {
        try {
            $request = $this->buildRequest();

            // Phase 0: Use minimal middleware for initial routing validation
            // TODO: Fix middleware interface mismatch in next phase
            $globalMiddleware = [];

            $this->logger->info('Request started', [
                'component'  => 'bootstrap',
                'action'     => 'request_start',
                'method'     => $request->method,
                'uri'        => $request->uri,
                'user_agent' => $request->headers['User-Agent'] ?? 'unknown',
                'ip'         => $request->ip,
                'request_id' => $request->headers['X-Request-ID'] ?? 'phase0-' . uniqid(),
            ]);

            $response = $this->router->dispatch($request, $globalMiddleware);

            $this->sendResponse($response);

            $this->logger->info('Request completed', [
                'component'  => 'bootstrap',
                'action'     => 'request_complete',
                'status_code' => $response->statusCode ?? 200,
                'request_id' => $request->headers['X-Request-ID'] ?? 'phase0-' . uniqid(),
            ]);
        } catch (\Throwable $e) {
            $this->handleFatalError($e);
        }
    }



    /**
     * Build standardized request object from PHP globals
     */
    private function buildRequest(): object
    {
        $request = new \stdClass();
        $request->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $request->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $request->host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $request->protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        $request->queryString = $_SERVER['QUERY_STRING'] ?? '';

        // Build headers array from $_SERVER
        $request->headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $request->headers[$headerName] = $value;
            }
        }

        // Add standard headers that might not be prefixed with HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $request->headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $request->headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }

        // Request body/input data
        $request->post = $_POST;
        $request->get = $_GET;
        $request->files = $_FILES ?? [];
        $request->cookies = $_COOKIE ?? [];

        // Client IP (consider proxies)
        $request->ip = $this->getClientIp();

        // Session data (if available)
        if (session_status() === PHP_SESSION_ACTIVE) {
            $request->session = $_SESSION ?? [];
        } else {
            $request->session = [];
        }

        // Request body for non-form data
        $request->rawBody = file_get_contents('php://input');
        if (!empty($request->rawBody) && isset($request->headers['Content-Type'])) {
            if (str_contains($request->headers['Content-Type'], 'application/json')) {
                $request->json = json_decode($request->rawBody, true);
            }
        }

        return $request;
    }

    /**
     * Get client IP with proxy support
     */
    private function getClientIp(): string
    {
        // Check for forwarded IP (reverse proxy/load balancer)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        // Check for real IP header
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        // Fallback to remote address
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Send HTTP response to client
     */
    private function sendResponse($response): void
    {
        // Handle object response format (new middleware pipeline)
        if (is_object($response)) {
            // Set status code
            if (isset($response->statusCode)) {
                http_response_code($response->statusCode);
            }

            // Set headers
            if (isset($response->headers)) {
                foreach ($response->headers as $name => $value) {
                    if (!headers_sent()) {
                        header("{$name}: {$value}");
                    }
                }
            }

            // Output body
            if (isset($response->body)) {
                if (is_array($response->body)) {
                    if (!headers_sent()) {
                        header('Content-Type: application/json');
                    }
                    echo json_encode($response->body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                } else {
                    echo $response->body;
                }
            }
            return;
        }

        // Handle legacy array format
        if (is_array($response)) {
            if (isset($response['json'])) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode($response['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } elseif (isset($response['view'])) {
                echo $response['view'];
            } elseif (isset($response['redirect'])) {
                if (!headers_sent()) {
                    header("Location: {$response['redirect']}", true, 302);
                }
            } else {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        } else {
            // Plain string response
            echo $response;
        }
    }

    /**
     * Handle fatal application errors
     */
    private function handleFatalError(\Throwable $e): void
    {
        $this->logger->critical('Fatal application error', [
            'component' => 'bootstrap',
            'action' => 'fatal_error',
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Ensure we can send headers
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }

        // Send minimal error response
        $errorResponse = [
            'success' => false,
            'error' => [
                'code' => 'FATAL_ERROR',
                'message' => 'A fatal error occurred while processing your request'
            ],
            'timestamp' => date('c')
        ];

        // Add debug info in development
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $errorResponse['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }

        echo json_encode($errorResponse, JSON_PRETTY_PRINT);
        exit(1);
    }
}
