<?php
/**
 * Security Class
 * 
 * Security utilities for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

namespace App\Core;

use Exception;

class Security
{
    private static ?string $csrfSecret = null;
    private static array $rateLimits = [];

    /**
     * Initialize security
     */
    public function __construct()
    {
        if (!isset($_SESSION)) {
            $this->startSecureSession();
        }
        
        self::$csrfSecret = config('security.csrf.secret') ?: $this->generateSecret();
    }

    /**
     * Start secure session
     */
    private function startSecureSession(): void
    {
        $config = config('security.session');
        
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string)$config['lifetime']);
        
        session_name($config['name']);
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken(): string
    {
        $token = hash_hmac('sha256', session_id() . time(), self::$csrfSecret);
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Check token expiry
        $expiry = config('security.csrf.token_expiry', 3600);
        if (time() - $_SESSION['csrf_token_time'] > $expiry) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        // Constant-time comparison
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize input
     */
    public function sanitizeInput(string $input, bool $allowHtml = false): string
    {
        if ($allowHtml) {
            return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return filter_var(trim($input), FILTER_SANITIZE_STRING);
    }

    /**
     * Validate email
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Hash password
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64MB
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate secure random string
     */
    public function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate secret key
     */
    private function generateSecret(): string
    {
        return $this->generateRandomString(64);
    }

    /**
     * Rate limiting
     */
    public function checkRateLimit(string $key, int $maxRequests = null, int $windowSeconds = null): bool
    {
        if (!config('rate_limiting.enabled', true)) {
            return true;
        }
        
        $maxRequests = $maxRequests ?? config('rate_limiting.requests', 60);
        $windowSeconds = $windowSeconds ?? config('rate_limiting.window', 60);
        
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Clean old entries
        if (isset(self::$rateLimits[$key])) {
            self::$rateLimits[$key] = array_filter(
                self::$rateLimits[$key],
                fn($timestamp) => $timestamp > $windowStart
            );
        } else {
            self::$rateLimits[$key] = [];
        }
        
        // Check if limit exceeded
        if (count(self::$rateLimits[$key]) >= $maxRequests) {
            return false;
        }
        
        // Add current request
        self::$rateLimits[$key][] = $now;
        
        return true;
    }

    /**
     * Get client IP address
     */
    public function getClientIp(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
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
     * Set security headers
     */
    public function setSecurityHeaders(): void
    {
        $headers = config('security.headers', []);
        
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Additional security headers
        header('X-Request-ID: ' . $this->generateRequestId());
        header('X-Powered-By: CIS MVC Platform');
    }

    /**
     * Generate request ID
     */
    public function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $data = []): void
    {
        $logData = [
            'timestamp' => date('c'),
            'event' => $event,
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'data' => $data,
        ];
        
        $logFile = config('logging.path') . '/security.log';
        $logEntry = json_encode($logData) . "\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Validate input against XSS
     */
    public function validateXss(string $input): bool
    {
        $dangerous = [
            '<script',
            'javascript:',
            'onload=',
            'onerror=',
            'onclick=',
            'onmouseover=',
            'vbscript:',
            'data:text/html',
        ];
        
        $lowercaseInput = strtolower($input);
        
        foreach ($dangerous as $pattern) {
            if (strpos($lowercaseInput, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate SQL injection patterns
     */
    public function validateSqlInjection(string $input): bool
    {
        $dangerous = [
            'union select',
            'drop table', 
            'delete from',
            'insert into',
            'update set',
            'exec(',
            'execute(',
            '--',
            '/*',
            '*/',
            'xp_',
            'sp_',
        ];
        
        $lowercaseInput = strtolower($input);
        
        foreach ($dangerous as $pattern) {
            if (strpos($lowercaseInput, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Secure file upload validation
     */
    public function validateFileUpload(array $file): array
    {
        $errors = [];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return $errors;
        }
        
        // Check file size (max 10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = 'File too large (max 10MB)';
        }
        
        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'File type not allowed';
        }
        
        // Check filename
        $filename = basename($file['name']);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            $errors[] = 'Invalid filename';
        }
        
        return $errors;
    }
}
