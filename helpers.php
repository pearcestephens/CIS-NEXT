<?php
declare(strict_types=1);

/**
 * CIS 2.0 Helper Functions
 * 
 * Global utility functions for the application
 * Author: GitHub Copilot
 * Created: 2025-09-13
 */

if (!function_exists('dd')) {
    /**
     * Dump and die (for debugging)
     */
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die();
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable with optional default
     */
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config(string $key, mixed $default = null): mixed
    {
        // Simple config implementation
        static $config = null;
        
        if ($config === null) {
            $config = [
                'app.name' => 'CIS 2.0',
                'app.env' => env('APP_ENV', 'production'),
                'app.debug' => env('APP_DEBUG', false),
                'database.host' => env('DB_HOST', 'localhost'),
                'database.name' => env('DB_NAME', 'cis'),
                'database.user' => env('DB_USER', 'root'),
                'database.password' => env('DB_PASSWORD', ''),
                'cache.driver' => env('CACHE_DRIVER', 'file'),
                'session.lifetime' => env('SESSION_LIFETIME', 120),
            ];
        }
        
        return $config[$key] ?? $default;
    }
}

if (!function_exists('route')) {
    /**
     * Generate URL for named route
     */
    function route(string $name, array $params = []): string
    {
        // Simple route generation - can be enhanced
        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        
        // Basic route mapping
        $routes = [
            'home' => '/',
            'login' => '/login',
            'dashboard' => '/dashboard',
            'admin.dashboard' => '/admin/dashboard',
            'admin.users' => '/admin/users',
            'admin.settings' => '/admin/settings',
        ];
        
        $path = $routes[$name] ?? "/{$name}";
        
        // Replace parameters in path
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", $value, $path);
        }
        
        return $baseUrl . $path;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to URL
     */
    function redirect(string $url, int $code = 302): void
    {
        header("Location: {$url}", true, $code);
        exit;
    }
}

if (!function_exists('back')) {
    /**
     * Redirect back to previous page
     */
    function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value (for forms)
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate CSRF token
     */
    function csrf_token(): string
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF hidden input field
     */
    function csrf_field(): string
    {
        $token = csrf_token();
        return "<input type=\"hidden\" name=\"_token\" value=\"{$token}\">";
    }
}

if (!function_exists('method_field')) {
    /**
     * Generate method override field for forms
     */
    function method_field(string $method): string
    {
        return "<input type=\"hidden\" name=\"_method\" value=\"{$method}\">";
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset(string $path): string
    {
        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $baseUrl . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate full URL
     */
    function url(string $path = ''): string
    {
        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('secure_path')) {
    /**
     * Secure file path to prevent directory traversal
     */
    function secure_path(string $path, string $basePath = null): string
    {
        $basePath = $basePath ?? __DIR__;
        $realBase = realpath($basePath);
        $realPath = realpath($basePath . '/' . $path);
        
        if ($realPath === false || strpos($realPath, $realBase) !== 0) {
            throw new InvalidArgumentException('Invalid file path');
        }
        
        return $realPath;
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Format bytes to human readable format
     */
    function format_bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('sanitize_html')) {
    /**
     * Sanitize HTML to prevent XSS
     */
    function sanitize_html(string $html): string
    {
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if current user is admin
     */
    function is_admin(): bool
    {
        return ($_SESSION['user']['role'] ?? '') === 'admin';
    }
}

if (!function_exists('current_user')) {
    /**
     * Get current authenticated user
     */
    function current_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('auth_check')) {
    /**
     * Check if user is authenticated
     */
    function auth_check(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('cache_key')) {
    /**
     * Generate standardized cache key
     */
    function cache_key(string $prefix, ...$parts): string
    {
        $key = $prefix;
        foreach ($parts as $part) {
            $key .= ':' . (is_scalar($part) ? $part : md5(serialize($part)));
        }
        return $key;
    }
}

if (!function_exists('request_id')) {
    /**
     * Get or generate request ID for tracing
     */
    function request_id(): string
    {
        static $requestId = null;
        
        if ($requestId === null) {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_', true);
        }
        
        return $requestId;
    }
}

if (!function_exists('log_activity')) {
    /**
     * Log user activity
     */
    function log_activity(string $activity, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => request_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'activity' => $activity,
            'context' => $context
        ];
        
        error_log('ACTIVITY: ' . json_encode($logEntry));
    }
}
