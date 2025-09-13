<?php
/**
 * Global Helper Functions
 * 
 * Global utility functions for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

if (!function_exists('config')) {
    /**
     * Get configuration value using dot notation
     * 
     * @param string $key Configuration key (e.g., 'app.name', 'database.connections.mysql.host')
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    function config(string $key, $default = null)
    {
        static $config = [];
        
        // Load configuration files if not already loaded
        if (empty($config)) {
            $configPath = __DIR__ . '/../config';
            
            // Load main config files
            $configFiles = ['app', 'database', 'security', 'logging'];
            
            foreach ($configFiles as $file) {
                $filePath = $configPath . '/' . $file . '.php';
                if (file_exists($filePath)) {
                    $config[$file] = require $filePath;
                }
            }
        }
        
        // Parse dot notation key
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if variable not found
     * @return mixed Environment variable value
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        
        // Convert boolean strings
        if (is_string($value)) {
            switch (strtolower($value)) {
                case 'true':
                case '(true)':
                    return true;
                case 'false':
                case '(false)':
                    return false;
                case 'null':
                case '(null)':
                    return null;
                case 'empty':
                case '(empty)':
                    return '';
            }
        }
        
        return $value;
    }
}

if (!function_exists('app_path')) {
    /**
     * Get application path
     * 
     * @param string $path Additional path to append
     * @return string Full application path
     */
    function app_path(string $path = ''): string
    {
        $basePath = realpath(__DIR__ . '/..');
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get storage path
     * 
     * @param string $path Additional path to append
     * @return string Full storage path
     */
    function storage_path(string $path = ''): string
    {
        $storagePath = app_path('storage');
        return $path ? $storagePath . '/' . ltrim($path, '/') : $storagePath;
    }
}

if (!function_exists('view_path')) {
    /**
     * Get view path
     * 
     * @param string $path Additional path to append
     * @return string Full view path
     */
    function view_path(string $path = ''): string
    {
        $viewPath = app_path('app/Views');
        return $path ? $viewPath . '/' . ltrim($path, '/') : $viewPath;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die for debugging
     * 
     * @param mixed ...$vars Variables to dump
     */
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        exit;
    }
}

if (!function_exists('logger')) {
    /**
     * Get logger instance
     * 
     * @return object Logger instance
     */
    function logger()
    {
        static $logger;
        
        if (!$logger) {
            // Simple file logger for now
            $logger = new class {
                public function info(string $message, array $context = []): void
                {
                    $this->log('info', $message, $context);
                }
                
                public function error(string $message, array $context = []): void
                {
                    $this->log('error', $message, $context);
                }
                
                public function warning(string $message, array $context = []): void
                {
                    $this->log('warning', $message, $context);
                }
                
                public function debug(string $message, array $context = []): void
                {
                    $this->log('debug', $message, $context);
                }
                
                private function log(string $level, string $message, array $context = []): void
                {
                    $logPath = storage_path('logs/app.log');
                    $logDir = dirname($logPath);
                    
                    if (!is_dir($logDir)) {
                        mkdir($logDir, 0755, true);
                    }
                    
                    $entry = [
                        'timestamp' => date('c'),
                        'level' => strtoupper($level),
                        'message' => $message,
                        'context' => $context,
                    ];
                    
                    file_put_contents($logPath, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
                }
            };
        }
        
        return $logger;
    }
}

if (!function_exists('abort')) {
    /**
     * Abort request with HTTP status code
     * 
     * @param int $code HTTP status code
     * @param string $message Error message
     */
    function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
        ];
        
        $defaultMessage = $messages[$code] ?? 'Error';
        $message = $message ?: $defaultMessage;
        
        // Check if this is an API request
        $isApi = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
        
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
                'request_id' => uniqid('req_', true),
            ]);
        } else {
            echo "<h1>{$code} - {$message}</h1>";
        }
        
        exit;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to URL
     * 
     * @param string $url URL to redirect to
     * @param int $status HTTP status code
     */
    function redirect(string $url, int $status = 302): void
    {
        header("Location: {$url}", true, $status);
        exit;
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value from session
     * 
     * @param string $key Input key
     * @param mixed $default Default value
     * @return mixed Old input value
     */
    function old(string $key, $default = null)
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    function csrf_token(): string
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['_token'];
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     * 
     * @param string $path Asset path
     * @return string Asset URL
     */
    function asset(string $path): string
    {
        $baseUrl = rtrim(config('app.url', ''), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL for route
     * 
     * @param string $path Path
     * @return string Full URL
     */
    function url(string $path = ''): string
    {
        $baseUrl = rtrim(config('app.url', ''), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}
