<?php
/**
 * Database Configuration
 * 
 * Database configuration for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int)($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_NAME'] ?? 'cis_development',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
            'prefix' => $_ENV['DB_TABLE_PREFIX'] ?? 'cis_',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . ($_ENV['DB_CHARSET'] ?? 'utf8mb4'),
            ],
        ],

        'testing' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_TEST_HOST'] ?? 'localhost',
            'port' => (int)($_ENV['DB_TEST_PORT'] ?? 3306),
            'database' => $_ENV['DB_TEST_NAME'] ?? 'cis_testing',
            'username' => $_ENV['DB_TEST_USER'] ?? 'root',
            'password' => $_ENV['DB_TEST_PASS'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => 'test_',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],

    'migration' => [
        'table' => 'migrations',
        'path' => realpath(__DIR__ . '/../database/migrations'),
    ],

    'seeding' => [
        'path' => realpath(__DIR__ . '/../database/seeds'),
    ],

    'query_log' => [
        'enabled' => filter_var($_ENV['DB_QUERY_LOG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'slow_query_threshold' => (float)($_ENV['DB_SLOW_QUERY_THRESHOLD'] ?? 1.0),
        'log_file' => 'database.log',
    ],

    'pool' => [
        'max_connections' => (int)($_ENV['DB_MAX_CONNECTIONS'] ?? 10),
        'idle_timeout' => (int)($_ENV['DB_IDLE_TIMEOUT'] ?? 300),
        'retry_attempts' => (int)($_ENV['DB_RETRY_ATTEMPTS'] ?? 3),
        'retry_delay' => (int)($_ENV['DB_RETRY_DELAY'] ?? 100),
    ],

    'backup' => [
        'enabled' => filter_var($_ENV['DB_BACKUP_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'path' => realpath(__DIR__ . '/../storage/backups'),
        'retention_days' => (int)($_ENV['DB_BACKUP_RETENTION'] ?? 30),
        'compression' => true,
    ],
];
