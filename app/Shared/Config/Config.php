<?php
declare(strict_types=1);

namespace App\Shared\Config;

/**
 * Configuration Manager
 * Loads and manages application configuration from environment files
 */
class Config
{
    private static array $config = [];
    
    public static function load(string $envFile): void
    {
        if (!file_exists($envFile)) {
            throw new \RuntimeException("Environment file not found: {$envFile}");
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }
            
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                self::$config[$key] = $value;
                
                // Also set as environment variable
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
    
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
    
    public static function set(string $key, mixed $value): void
    {
        self::$config[$key] = $value;
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
    
    public static function has(string $key): bool
    {
        return isset(self::$config[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }
    
    public static function all(): array
    {
        return self::$config;
    }
    
    public static function getDatabase(): array
    {
        return [
            'host' => self::get('DB_HOST'),
            'port' => (int) self::get('DB_PORT', 3306),
            'database' => self::get('DB_NAME'),
            'username' => self::get('DB_USER'),
            'password' => self::get('DB_PASS'),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
            'collation' => self::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'table_prefix' => self::get('DB_TABLE_PREFIX', 'cis_'),
        ];
    }
    
    public static function isDebug(): bool
    {
        return self::get('APP_DEBUG') === 'true';
    }
    
    public static function isProduction(): bool
    {
        return self::get('APP_ENV') === 'production';
    }
    
    public static function isDevelopment(): bool
    {
        return self::get('APP_ENV') === 'development';
    }
}
