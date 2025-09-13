<?php
declare(strict_types=1);

namespace App\Shared\Config;

/**
 * Environment Configuration Loader
 * 
 * Handles .env file parsing with quote handling and variable expansion.
 * Single source of truth for environment configuration.
 * 
 * @version 2.0.0-alpha.2
 */
class Env
{
    private static array $env = [];
    private static bool $loaded = false;
    
    /**
     * Get environment value with default fallback
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadIfNeeded();
        return self::$env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
    
    /**
     * Get all environment variables
     */
    public static function all(): array
    {
        self::loadIfNeeded();
        return array_merge($_ENV, getenv(), self::$env);
    }
    
    /**
     * Set environment value (for testing)
     */
    public static function set(string $key, mixed $value): void
    {
        self::$env[$key] = $value;
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
    
    /**
     * Load .env file if not already loaded
     */
    private static function loadIfNeeded(): void
    {
        if (self::$loaded) {
            return;
        }
        
        $envFiles = [
            __DIR__ . '/../../../.env',
            __DIR__ . '/../../../../.env',
            getcwd() . '/.env'
        ];
        
        foreach ($envFiles as $envFile) {
            if (file_exists($envFile)) {
                self::loadEnvFile($envFile);
                break;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Parse and load .env file
     */
    private static function loadEnvFile(string $filePath): void
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return;
        }
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Handle quoted values
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                // Handle variable expansion ${VAR} or $VAR
                $value = preg_replace_callback('/\$\{([^}]+)\}|\$([A-Za-z_][A-Za-z0-9_]*)/', function($matches) {
                    $varName = $matches[1] ?? $matches[2];
                    return self::$env[$varName] ?? $_ENV[$varName] ?? getenv($varName) ?: '';
                }, $value);
                
                self::$env[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    
    /**
     * Check if environment is production
     */
    public static function isProduction(): bool
    {
        return strtolower(self::get('APP_ENV', 'development')) === 'production';
    }
    
    /**
     * Check if environment is development
     */
    public static function isDevelopment(): bool
    {
        return strtolower(self::get('APP_ENV', 'development')) === 'development';
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebug(): bool
    {
        return filter_var(self::get('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN);
    }
}
