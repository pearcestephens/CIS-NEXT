<?php
/**
 * Application Configuration
 * 
 * Main application configuration file for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

return [
    'app' => [
        'name' => 'CIS MVC Platform',
        'version' => '2.0.0-alpha.1',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'] ?? 'https://staff.vapeshed.co.nz',
        'key' => $_ENV['APP_KEY'] ?? '',
        'timezone' => 'Pacific/Auckland',
        'locale' => 'en_NZ',
        'charset' => 'UTF-8',
    ],

    'security' => [
        'csrf' => [
            'secret' => $_ENV['CSRF_SECRET'] ?? '',
            'token_expiry' => (int)($_ENV['CSRF_TOKEN_EXPIRY'] ?? 3600),
            'header_name' => 'X-CSRF-Token',
            'field_name' => '_token',
        ],
        'session' => [
            'name' => $_ENV['SESSION_NAME'] ?? 'CIS_SESSION',
            'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 1440),
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ],
        'headers' => [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;",
        ],
    ],

    'performance' => [
        'profiler_enabled' => filter_var($_ENV['PROFILER_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'cache_driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
        'cache_ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600),
        'compression' => [
            'enabled' => true,
            'level' => 6,
            'types' => ['text/html', 'text/css', 'application/javascript', 'application/json'],
        ],
    ],

    'quality' => [
        'gates_enabled' => filter_var($_ENV['QUALITY_GATES_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'psr12_target' => (int)($_ENV['PSR12_COMPLIANCE_TARGET'] ?? 95),
        'coverage_target' => (int)($_ENV['TEST_COVERAGE_TARGET'] ?? 70),
    ],

    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'path' => $_ENV['LOG_PATH'] ?? './var/logs',
        'max_files' => 30,
        'format' => 'json',
        'channels' => [
            'app' => 'app.log',
            'security' => 'security.log',
            'performance' => 'performance.log',
            'database' => 'database.log',
        ],
    ],

    'rate_limiting' => [
        'enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'requests' => (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 60),
        'window' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
        'storage' => 'file',
    ],

    'paths' => [
        'base' => realpath(__DIR__ . '/..'),
        'app' => realpath(__DIR__ . '/../app'),
        'config' => __DIR__,
        'views' => realpath(__DIR__ . '/../app/Views'),
        'storage' => realpath(__DIR__ . '/../storage'),
        'cache' => realpath(__DIR__ . '/../storage/cache'),
        'logs' => realpath(__DIR__ . '/../storage/logs'),
    ],

    'view' => [
        'cache' => true,
        'cache_path' => realpath(__DIR__ . '/../storage/cache/views'),
        'extensions' => ['.php', '.html'],
        'globals' => [
            'app_name' => 'CIS MVC Platform',
            'app_version' => '2.0.0-alpha.1',
        ],
    ],
];
