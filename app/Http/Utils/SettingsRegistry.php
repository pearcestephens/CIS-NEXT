<?php
declare(strict_types=1);

namespace App\Http\Utils;

/**
 * Settings Registry System
 * "Everything Is a Setting" - Centralized configuration management
 * 
 * Supports ENV > DB > Default precedence with audit history
 * 
 * @author CIS Developer Bot
 * @created 2025-09-13
 */
class SettingsRegistry
{
    private static array $schema = [];
    private static array $cache = [];
    private static bool $initialized = false;
    
    /**
     * Initialize the settings schema
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        
        self::$schema = [
            // System Configuration
            'system.name' => [
                'group' => 'system',
                'label' => 'Application Name',
                'type' => 'text',
                'default' => 'CIS Admin',
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'string|max:100',
                'description' => 'Display name for the application'
            ],
            
            'system.base_url' => [
                'group' => 'system',
                'label' => 'Base URL',
                'type' => 'url',
                'default' => 'https://staff.vapeshed.co.nz',
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'url',
                'description' => 'Base URL for the application'
            ],
            
            'system.timezone' => [
                'group' => 'system',
                'label' => 'Timezone',
                'type' => 'select',
                'default' => 'Pacific/Auckland',
                'options' => ['Pacific/Auckland', 'UTC', 'America/New_York', 'Europe/London'],
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'timezone',
                'description' => 'Default timezone for the application'
            ],
            
            'system.debug' => [
                'group' => 'system',
                'label' => 'Debug Mode',
                'type' => 'boolean',
                'default' => false,
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'boolean',
                'description' => 'Enable debug mode (use with caution in production)'
            ],
            
            // Mail Configuration
            'mail.driver' => [
                'group' => 'mail',
                'label' => 'Mail Driver',
                'type' => 'select',
                'default' => 'smtp',
                'options' => ['smtp', 'sendmail', 'log'],
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'in:smtp,sendmail,log',
                'description' => 'Email delivery method'
            ],
            
            'mail.host' => [
                'group' => 'mail',
                'label' => 'SMTP Host',
                'type' => 'text',
                'default' => 'localhost',
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'string',
                'description' => 'SMTP server hostname'
            ],
            
            'mail.port' => [
                'group' => 'mail',
                'label' => 'SMTP Port',
                'type' => 'number',
                'default' => 587,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'integer|min:1|max:65535',
                'description' => 'SMTP server port'
            ],
            
            'mail.username' => [
                'group' => 'mail',
                'label' => 'SMTP Username',
                'type' => 'text',
                'default' => '',
                'is_secret' => true,
                'is_advanced' => false,
                'validator' => 'string|nullable',
                'description' => 'SMTP authentication username'
            ],
            
            'mail.password' => [
                'group' => 'mail',
                'label' => 'SMTP Password',
                'type' => 'password',
                'default' => '',
                'is_secret' => true,
                'is_advanced' => false,
                'validator' => 'string|nullable',
                'description' => 'SMTP authentication password'
            ],
            
            // Cache Configuration
            'cache.driver' => [
                'group' => 'cache',
                'label' => 'Cache Driver',
                'type' => 'select',
                'default' => 'redis',
                'options' => ['redis', 'file', 'memory'],
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'in:redis,file,memory',
                'description' => 'Cache storage backend'
            ],
            
            'cache.redis.host' => [
                'group' => 'cache',
                'label' => 'Redis Host',
                'type' => 'text',
                'default' => '127.0.0.1',
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'string',
                'description' => 'Redis server hostname'
            ],
            
            'cache.redis.port' => [
                'group' => 'cache',
                'label' => 'Redis Port',
                'type' => 'number',
                'default' => 6379,
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'integer|min:1|max:65535',
                'description' => 'Redis server port'
            ],
            
            'cache.redis.db' => [
                'group' => 'cache',
                'label' => 'Redis Database',
                'type' => 'number',
                'default' => 0,
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'integer|min:0|max:15',
                'description' => 'Redis database index'
            ],
            
            'cache.tags_enabled' => [
                'group' => 'cache',
                'label' => 'Cache Tags',
                'type' => 'boolean',
                'default' => true,
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'boolean',
                'description' => 'Enable cache tagging for selective invalidation'
            ],
            
            // Queue Configuration
            'queue.driver' => [
                'group' => 'queue',
                'label' => 'Queue Driver',
                'type' => 'select',
                'default' => 'redis',
                'options' => ['redis', 'database', 'sync'],
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'in:redis,database,sync',
                'description' => 'Job queue backend'
            ],
            
            'queue.concurrency' => [
                'group' => 'queue',
                'label' => 'Worker Concurrency',
                'type' => 'number',
                'default' => 4,
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'integer|min:1|max:20',
                'description' => 'Number of concurrent queue workers'
            ],
            
            'queue.retry_limit' => [
                'group' => 'queue',
                'label' => 'Retry Limit',
                'type' => 'number',
                'default' => 3,
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'integer|min:0|max:10',
                'description' => 'Maximum job retry attempts'
            ],
            
            // Backup Configuration
            'backups.retention_days' => [
                'group' => 'backups',
                'label' => 'Retention Period (Days)',
                'type' => 'number',
                'default' => 30,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'integer|min:1|max:365',
                'description' => 'How long to keep backup files'
            ],
            
            'backups.storage_path' => [
                'group' => 'backups',
                'label' => 'Storage Path',
                'type' => 'text',
                'default' => 'var/backups',
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'string',
                'description' => 'Directory path for backup storage'
            ],
            
            'backups.encrypt' => [
                'group' => 'backups',
                'label' => 'Encrypt Backups',
                'type' => 'boolean',
                'default' => true,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'boolean',
                'description' => 'Encrypt backup files with AES-256'
            ],
            
            // Security Configuration
            'security.csp.level' => [
                'group' => 'security',
                'label' => 'CSP Level',
                'type' => 'select',
                'default' => 'strict',
                'options' => ['disabled', 'report', 'strict'],
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'in:disabled,report,strict',
                'description' => 'Content Security Policy enforcement level'
            ],
            
            'security.rate_limit.window' => [
                'group' => 'security',
                'label' => 'Rate Limit Window (Minutes)',
                'type' => 'number',
                'default' => 15,
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'integer|min:1|max:60',
                'description' => 'Time window for rate limiting'
            ],
            
            'security.rate_limit.max' => [
                'group' => 'security',
                'label' => 'Rate Limit Maximum',
                'type' => 'number',
                'default' => 100,
                'is_secret' => false,
                'is_advanced' => true,
                'validator' => 'integer|min:10|max:1000',
                'description' => 'Maximum requests per window'
            ],
            
            'security.session.lifetime' => [
                'group' => 'security',
                'label' => 'Session Lifetime (Hours)',
                'type' => 'number',
                'default' => 8,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'integer|min:1|max:168',
                'description' => 'Session expiry time in hours'
            ],
            
            // Logging Configuration
            'logs.level' => [
                'group' => 'logs',
                'label' => 'Log Level',
                'type' => 'select',
                'default' => 'info',
                'options' => ['debug', 'info', 'warning', 'error'],
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'in:debug,info,warning,error',
                'description' => 'Minimum log level to record'
            ],
            
            'logs.retention_days' => [
                'group' => 'logs',
                'label' => 'Log Retention (Days)',
                'type' => 'number',
                'default' => 90,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'integer|min:7|max:365',
                'description' => 'How long to keep log files'
            ],
            
            // Integration Toggles
            'integrations.xero.enabled' => [
                'group' => 'integrations',
                'label' => 'Xero Integration',
                'type' => 'boolean',
                'default' => false,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'boolean',
                'description' => 'Enable Xero accounting integration'
            ],
            
            'integrations.vend.enabled' => [
                'group' => 'integrations',
                'label' => 'Vend Integration',
                'type' => 'boolean',
                'default' => false,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'boolean',
                'description' => 'Enable Vend POS integration'
            ],
            
            'integrations.deputy.enabled' => [
                'group' => 'integrations',
                'label' => 'Deputy Integration',
                'type' => 'boolean',
                'default' => false,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'boolean',
                'description' => 'Enable Deputy workforce management'
            ],
            
            'integrations.openai.enabled' => [
                'group' => 'integrations',
                'label' => 'OpenAI Integration',
                'type' => 'boolean',
                'default' => false,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'boolean',
                'description' => 'Enable OpenAI API integration'
            ],
            
            // UI/Theme Configuration
            'ui.theme.default' => [
                'group' => 'ui',
                'label' => 'Default Theme',
                'type' => 'select',
                'default' => 'auto',
                'options' => ['light', 'dark', 'auto'],
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'in:light,dark,auto',
                'description' => 'Default color theme for new users'
            ],
            
            'ui.shortcuts.enabled' => [
                'group' => 'ui',
                'label' => 'Keyboard Shortcuts',
                'type' => 'boolean',
                'default' => true,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'boolean',
                'description' => 'Enable keyboard shortcuts in admin interface'
            ],
            
            // Feature Flags
            'features.session_recording' => [
                'group' => 'features',
                'label' => 'Session Recording',
                'type' => 'boolean',
                'default' => false,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'boolean',
                'description' => 'Enable privacy-compliant session recording'
            ],
            
            'features.privacy_redaction' => [
                'group' => 'features',
                'label' => 'Privacy Redaction',
                'type' => 'boolean',
                'default' => true,
                'is_secret' => false,
                'is_advanced' => false,
                'validator' => 'boolean',
                'description' => 'Automatically redact sensitive data in logs'
            ]
        ];
        
        self::$initialized = true;
    }
    
    /**
     * Get setting value with precedence: ENV > DB > Default
     */
    public static function get(string $key, $default = null)
    {
        self::init();
        
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // 1. Check environment variable
        $envKey = strtoupper(str_replace('.', '_', $key));
        $envValue = $_ENV[$envKey] ?? getenv($envKey);
        if ($envValue !== false) {
            $value = self::castValue($envValue, $key);
            self::$cache[$key] = $value;
            return $value;
        }
        
        // 2. Check database
        $dbValue = self::getFromDatabase($key);
        if ($dbValue !== null) {
            self::$cache[$key] = $dbValue;
            return $dbValue;
        }
        
        // 3. Use schema default
        if (isset(self::$schema[$key])) {
            $value = self::$schema[$key]['default'];
            self::$cache[$key] = $value;
            return $value;
        }
        
        // 4. Return provided default
        return $default;
    }
    
    /**
     * Set setting value in database
     */
    public static function set(string $key, $value, int $userId = null): bool
    {
        self::init();
        
        if (!isset(self::$schema[$key])) {
            throw new \InvalidArgumentException("Setting key '{$key}' not found in schema");
        }
        
        // Validate value
        if (!self::validateValue($key, $value)) {
            throw new \InvalidArgumentException("Invalid value for setting '{$key}'");
        }
        
        $db = self::getDatabaseConnection();
        
        try {
            // Insert or update setting
            $stmt = $db->prepare("
                INSERT INTO cis_settings (setting_key, setting_value, updated_by, updated_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ");
            
            $serialized = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;
            $result = $stmt->execute([$key, $serialized, $userId]);
            
            if ($result) {
                // Add to history
                self::addToHistory($key, $value, $userId);
                // Clear cache
                unset(self::$cache[$key]);
                return true;
            }
            
        } catch (\Exception $e) {
            error_log("Settings error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Get all settings grouped by category
     */
    public static function getAllGrouped(): array
    {
        self::init();
        
        $grouped = [];
        foreach (self::$schema as $key => $config) {
            $group = $config['group'];
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            
            $grouped[$group][$key] = array_merge($config, [
                'current_value' => self::get($key),
                'is_env_override' => self::isEnvironmentOverride($key),
                'key' => $key
            ]);
        }
        
        return $grouped;
    }
    
    /**
     * Check if setting is overridden by environment
     */
    public static function isEnvironmentOverride(string $key): bool
    {
        $envKey = strtoupper(str_replace('.', '_', $key));
        $envValue = $_ENV[$envKey] ?? getenv($envKey);
        return $envValue !== false;
    }
    
    /**
     * Get setting schema
     */
    public static function getSchema(string $key = null): array
    {
        self::init();
        return $key ? (self::$schema[$key] ?? []) : self::$schema;
    }
    
    /**
     * Validate setting value against schema
     */
    private static function validateValue(string $key, $value): bool
    {
        if (!isset(self::$schema[$key])) {
            return false;
        }
        
        $schema = self::$schema[$key];
        $validator = $schema['validator'] ?? '';
        
        // Basic type validation
        switch ($schema['type']) {
            case 'boolean':
                return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
            
            case 'number':
                return is_numeric($value);
            
            case 'select':
                return in_array($value, $schema['options'] ?? [], true);
            
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            
            default:
                return true; // Basic validation for text/password types
        }
    }
    
    /**
     * Cast value to appropriate type
     */
    private static function castValue($value, string $key)
    {
        if (!isset(self::$schema[$key])) {
            return $value;
        }
        
        switch (self::$schema[$key]['type']) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            
            case 'number':
                return is_numeric($value) ? (float)$value : $value;
            
            default:
                return $value;
        }
    }
    
    /**
     * Get setting from database
     */
    private static function getFromDatabase(string $key)
    {
        try {
            $db = self::getDatabaseConnection();
            $stmt = $db->prepare("SELECT setting_value FROM cis_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if ($result) {
                $value = $result['setting_value'];
                
                // Try to decode JSON
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                
                return self::castValue($value, $key);
            }
        } catch (\Exception $e) {
            error_log("Database settings error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Add setting change to audit history
     */
    private static function addToHistory(string $key, $value, int $userId = null): void
    {
        try {
            $db = self::getDatabaseConnection();
            $stmt = $db->prepare("
                INSERT INTO cis_settings_history 
                (setting_key, old_value, new_value, changed_by, changed_at) 
                SELECT ?, 
                       COALESCE((SELECT setting_value FROM cis_settings WHERE setting_key = ?), 'NULL'),
                       ?, ?, NOW()
            ");
            
            $serialized = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;
            $stmt->execute([$key, $key, $serialized, $userId]);
            
        } catch (\Exception $e) {
            error_log("Settings history error: " . $e->getMessage());
        }
    }
    
    /**
     * Clear all cached settings
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
    
    /**
     * Get database connection using CIS credentials
     */
    private static function getDatabaseConnection(): \PDO
    {
        static $pdo = null;
        
        if ($pdo === null) {
            // Use provided credentials
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $user = getenv('DB_USER') ?: 'jcepnzzkmj';
            $pass = getenv('DB_PASS') ?: 'wprKh9Jq63';
            $db   = getenv('DB_NAME') ?: 'jcepnzzkmj';
            
            try {
                $pdo = new \PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]);
            } catch (\Exception $e) {
                throw new \RuntimeException("Settings database connection failed: " . $e->getMessage());
            }
        }
        
        return $pdo;
    }
}
