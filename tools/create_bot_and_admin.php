<?php
declare(strict_types=1);

/**
 * Create Bot User and Admin User
 * Sets up authentication for CIS Bot (ID 18) and Admin (ID 1)
 */

require_once __DIR__ . '/app/Shared/Bootstrap.php';
App\Shared\Bootstrap::init(__DIR__);

use App\Infra\Persistence\MariaDB\Database;

try {
    $db = Database::getInstance();
    
    echo "=== Creating CIS Users ===" . PHP_EOL;
    
    // Create Admin User (ID 1)
    echo "1. Creating Admin User (Pearce Stephens)..." . PHP_EOL;
    
    $adminEmail = 'pearce.stephens@gmail.com';
    $adminPassword = 'fmsADMINED2013!!';
    
    // Check if admin exists
    $existingAdmin = $db->execute('SELECT id FROM users WHERE id = 1 OR email = ?', [$adminEmail])->fetch();
    
    if (!$existingAdmin) {
        $db->execute('INSERT INTO users (id, email, first_name, last_name, password_hash, role_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
            1,
            $adminEmail,
            'Pearce',
            'Stephens',
            password_hash($adminPassword, PASSWORD_DEFAULT),
            1, // Super Admin role
            'active'
        ]);
        echo "   ✅ Admin user created: $adminEmail (ID: 1)" . PHP_EOL;
    } else {
        // Update existing admin
        $db->execute('UPDATE users SET email = ?, first_name = ?, last_name = ?, password_hash = ?, role_id = ?, status = ?, updated_at = NOW() WHERE id = 1', [
            $adminEmail,
            'Pearce',
            'Stephens',
            password_hash($adminPassword, PASSWORD_DEFAULT),
            1,
            'active'
        ]);
        echo "   ✅ Admin user updated: $adminEmail (ID: 1)" . PHP_EOL;
    }
    
    // Create Bot User (ID 18)
    echo "2. Creating Bot User..." . PHP_EOL;
    
    $botEmail = 'bot@ecigdis.co.nz';
    $botToken = 'cis_bot_token_' . bin2hex(random_bytes(16));
    
    // Check if bot exists
    $existingBot = $db->execute('SELECT id FROM users WHERE id = 18 OR email = ?', [$botEmail])->fetch();
    
    if (!$existingBot) {
        $db->execute('INSERT INTO users (id, email, first_name, last_name, password_hash, role_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
            18,
            $botEmail,
            'CIS',
            'Bot',
            password_hash('bot_secure_password_' . bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            1, // Admin role for full access
            'active'
        ]);
        echo "   ✅ Bot user created: $botEmail (ID: 18)" . PHP_EOL;
    } else {
        echo "   ✅ Bot user already exists: ID 18" . PHP_EOL;
    }
    
    // Store bot token in configuration
    $db->execute('INSERT INTO configuration (config_key, config_value, is_sensitive, description, updated_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()', [
        'bot_auth_token',
        $botToken,
        1,
        'Static authentication token for CIS Bot (User ID 18)',
        1 // Updated by admin user
    ]);
    
    echo "   ✅ Bot token stored in configuration" . PHP_EOL;
    
    echo "" . PHP_EOL;
    echo "=== Authentication Details ===" . PHP_EOL;
    echo "Admin Login:" . PHP_EOL;
    echo "  Email: $adminEmail" . PHP_EOL;
    echo "  Password: $adminPassword" . PHP_EOL;
    echo "  URL: http://localhost:8081/login" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Bot Authentication:" . PHP_EOL;
    echo "  Token: $botToken" . PHP_EOL;
    echo "  URL: http://localhost:8081/admin?bot_token=$botToken" . PHP_EOL;
    echo "  API: http://localhost:8081/api/feed?bot_token=$botToken" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Testing Bypass:" . PHP_EOL;
    echo "  URL: http://localhost:8081/admin?bypass=enable" . PHP_EOL;
    echo "" . PHP_EOL;
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
