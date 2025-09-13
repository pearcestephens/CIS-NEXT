<?php
declare(strict_types=1);

/**
 * CIS Testing Bypass Configuration
 * Provides authentication bypass for testing purposes
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @version 1.0.0
 */

// Testing bypass configuration
class TestingBypass
{
    private static bool $enabled = false;
    private static array $bypassUser = [
        'id' => 1,
        'email' => 'admin@ecigdis.co.nz',
        'first_name' => 'Test',
        'last_name' => 'Admin',
        'role_id' => 1,
        'status' => 'active'
    ];
    
    /**
     * Enable testing bypass mode
     */
    public static function enable(): void
    {
        self::$enabled = true;
        
        // Set up bypass session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $_SESSION['user_id'] = self::$bypassUser['id'];
        $_SESSION['user_email'] = self::$bypassUser['email'];
        $_SESSION['role_id'] = self::$bypassUser['role_id'];
        $_SESSION['session_id'] = 'bypass_session_' . uniqid();
        $_SESSION['bypass_mode'] = true;
        
        // Set permissions for super admin
        $_SESSION['permissions'] = [
            'admin.access',
            'admin.view',
            'admin.manage',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'feed.view',
            'feed.create',
            'feed.edit',
            'feed.delete',
            'config.view',
            'config.edit',
            'profiler.view'
        ];
        
        error_log("CIS Testing Bypass: ENABLED for user " . self::$bypassUser['email']);
    }
    
    /**
     * Disable testing bypass mode
     */
    public static function disable(): void
    {
        self::$enabled = false;
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['bypass_mode']);
            session_destroy();
            $_SESSION = [];
        }
        
        error_log("CIS Testing Bypass: DISABLED");
    }
    
    /**
     * Check if bypass mode is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled || ($_SESSION['bypass_mode'] ?? false);
    }
    
    /**
     * Get bypass user data
     */
    public static function getBypassUser(): array
    {
        return self::$bypassUser;
    }
    
    /**
     * Bypass authentication middleware
     */
    public static function bypassAuthentication(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        
        // Ensure session is properly set
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            self::enable(); // Re-enable if session was lost
        }
        
        return true;
    }
    
    /**
     * Get current user for bypass mode
     */
    public static function getCurrentUser(): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? self::$bypassUser['id'],
            'email' => $_SESSION['user_email'] ?? self::$bypassUser['email'],
            'first_name' => self::$bypassUser['first_name'],
            'last_name' => self::$bypassUser['last_name'],
            'role_id' => $_SESSION['role_id'] ?? self::$bypassUser['role_id'],
            'status' => self::$bypassUser['status'],
            'permissions' => $_SESSION['permissions'] ?? []
        ];
    }
    
    /**
     * Check if user has permission in bypass mode
     */
    public static function hasPermission(string $permission): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        
        $permissions = $_SESSION['permissions'] ?? [];
        return in_array($permission, $permissions) || in_array('admin.manage', $permissions);
    }
    
    /**
     * Authenticate via bot token
     */
    public static function authenticateByBotToken(string $token): bool
    {
        // Simple token validation (in production, you'd want to check against database)
        if (strpos($token, 'cis_bot_token_') === 0 && strlen($token) === 46) {
            // Set up bot session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            $_SESSION['user_id'] = 18;
            $_SESSION['user_email'] = 'bot@ecigdis.co.nz';
            $_SESSION['role_id'] = 1;
            $_SESSION['session_id'] = 'bot_session_' . uniqid();
            $_SESSION['bot_auth'] = true;
            $_SESSION['bot_token'] = $token;
            
            // Set permissions for bot (full admin access)
            $_SESSION['permissions'] = [
                'admin.access',
                'admin.view',
                'admin.manage',
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'feed.view',
                'feed.create',
                'feed.edit',
                'feed.delete',
                'config.view',
                'config.edit',
                'profiler.view'
            ];
            
            error_log("CIS Bot Authentication: SUCCESS with token " . substr($token, 0, 20) . "...");
            return true;
        }
        
        return false;
    }
    
    /**
     * Get bot user data
     */
    public static function getBotUser(): array
    {
        return [
            'id' => 18,
            'email' => 'bot@ecigdis.co.nz',
            'first_name' => 'CIS',
            'last_name' => 'Bot',
            'role_id' => 1,
            'status' => 'active'
        ];
    }
}

/**
 * Enable bypass mode for testing
 * Call this function to enable authentication bypass
 */
function enableTestingBypass(): void
{
    TestingBypass::enable();
}

/**
 * Disable bypass mode
 */
function disableTestingBypass(): void
{
    TestingBypass::disable();
}

/**
 * Check if we're in bypass mode
 */
function isTestingBypass(): bool
{
    return TestingBypass::isEnabled();
}

// Auto-enable bypass mode if requested via environment or query parameter
if (
    ($_GET['bypass'] ?? '') === 'enable' ||
    ($_ENV['TESTING_BYPASS'] ?? '') === 'true' ||
    getenv('TESTING_BYPASS') === 'true'
) {
    TestingBypass::enable();
}

// Auto-enable bot authentication if bot_token is provided
if (isset($_GET['bot_token']) && !empty($_GET['bot_token'])) {
    if (TestingBypass::authenticateByBotToken($_GET['bot_token'])) {
        TestingBypass::enable(); // Enable bypass mode for bot
    }
}

// Auto-disable if requested
if (($_GET['bypass'] ?? '') === 'disable') {
    TestingBypass::disable();
}

// ===========================
// LOAD ENVIRONMENT VARIABLES
// ===========================

// Simple .env file loader
function loadEnvFile($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue; // Skip comments
        }
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            
            if (!array_key_exists($name, $_ENV) && !getenv($name)) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
    return true;
}

// Load .env file from project root
$envPath = __DIR__ . '/../.env';
loadEnvFile($envPath);

// ===========================
// MYSQLI CONNECTION FOR MIGRATIONS
// ===========================

global $mysqli;

if (!isset($mysqli)) {
    $mysqli = new mysqli(
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_USER'] ?? 'cis_user',
        $_ENV['DB_PASS'] ?? 'cis_password', 
        $_ENV['DB_NAME'] ?? 'cis_database'
    );
    
    if ($mysqli->connect_error) {
        error_log("MySQL Connection failed: " . $mysqli->connect_error);
        if (php_sapi_name() === 'cli') {
            echo "MySQL Connection failed: " . $mysqli->connect_error . "\n";
        }
    } else {
        $mysqli->set_charset('utf8mb4');
        if (php_sapi_name() === 'cli') {
            echo "MySQL Connected successfully\n";
        }
    }
}

// Legacy compatibility - provide $connection alias
global $connection;
$connection = $mysqli;

// ===========================
// DEFINE DATABASE CONSTANTS FOR LEGACY COMPATIBILITY
// ===========================

// Define constants for legacy code that expects DB_HOST, DB_USER, etc.
if (!defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int)($_ENV['DB_PORT'] ?? 3306));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'cis');
}
if (!defined('DB_USER')) {
    define('DB_USER', $_ENV['DB_USER'] ?? 'cisuser');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $_ENV['DB_PASS'] ?? 'StrongPassword123!');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
}
