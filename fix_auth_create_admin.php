<?php
declare(strict_types=1);

/**
 * Quick User Creation for CIS Authentication Fix
 * Creates admin user to enable login functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Bootstrap the application
require_once __DIR__ . '/app/Shared/Bootstrap.php';
App\Shared\Bootstrap::init(__DIR__);

use App\Infra\Persistence\MariaDB\Database;

try {
    echo "=== CIS Authentication Fix - Creating Admin User ===" . PHP_EOL;
    
    $db = Database::getInstance();
    
    // First check if authentication table exists
    $tablesResult = $db->execute("SHOW TABLES LIKE 'cis_users'");
    if (!$tablesResult->fetch()) {
        echo "❌ ERROR: 'cis_users' table does not exist. Need to run migrations first." . PHP_EOL;
        exit(1);
    }
    
    // Get super_admin role from cis_roles
    $roleResult = $db->execute("SELECT id FROM cis_roles WHERE name = 'super_admin' LIMIT 1");
    $role = $roleResult->fetch();
    
    if (!$role) {
        echo "❌ ERROR: Super admin role not found in cis_roles table" . PHP_EOL;
        exit(1);
    } else {
        $roleId = $role['id'];
        echo "   ✅ Super admin role found in cis_roles (ID: {$roleId})" . PHP_EOL;
    }
    
    // Create/update admin user in cis_users table (the real auth table)
    $adminEmail = 'admin@ecigdis.co.nz';
    $adminPassword = 'admin123';  // Simple password for testing
    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    echo "Creating admin user..." . PHP_EOL;
    echo "   Email: {$adminEmail}" . PHP_EOL;
    echo "   Password: {$adminPassword}" . PHP_EOL;
    
    // Check if admin user exists in cis_users
    $userResult = $db->execute("SELECT id FROM cis_users WHERE email = ?", [$adminEmail]);
    $existingUser = $userResult->fetch();
    
    if ($existingUser) {
        // Update existing user in cis_users
        $db->execute("
            UPDATE cis_users 
            SET email = ?, password_hash = ?, first_name = ?, last_name = ?, 
                role_id = ?, status = 'active', updated_at = NOW() 
            WHERE id = ?
        ", [$adminEmail, $passwordHash, 'System', 'Administrator', $roleId, $existingUser['id']]);
        
        echo "   ✅ Admin user updated in cis_users (ID: {$existingUser['id']})" . PHP_EOL;
    } else {
        // Create new user in cis_users
        $db->execute("
            INSERT INTO cis_users (email, password_hash, first_name, last_name, role_id, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ", [$adminEmail, $passwordHash, 'System', 'Administrator', $roleId]);
        
        $userId = $db->execute("SELECT LAST_INSERT_ID() as id")->fetch()['id'];
        echo "   ✅ Admin user created in cis_users (ID: {$userId})" . PHP_EOL;
    }
    
    // Verify the user can authenticate
    echo "Verifying authentication..." . PHP_EOL;
    
    $authResult = $db->execute("
        SELECT u.id, u.email, u.password_hash, u.status, r.name as role_name 
        FROM cis_users u 
        LEFT JOIN cis_roles r ON u.role_id = r.id 
        WHERE u.email = ?
    ", [$adminEmail]);
    
    $user = $authResult->fetch();
    
    if ($user && password_verify($adminPassword, $user['password_hash'])) {
        echo "   ✅ Authentication test successful!" . PHP_EOL;
        echo "   User ID: {$user['id']}" . PHP_EOL;
        echo "   Email: {$user['email']}" . PHP_EOL;
        echo "   Role: {$user['role_name']}" . PHP_EOL;
        echo "   Status: {$user['status']}" . PHP_EOL;
    } else {
        echo "   ❌ Authentication test failed!" . PHP_EOL;
        if (!$user) {
            echo "   Error: User not found after creation" . PHP_EOL;
        } else {
            echo "   Error: Password verification failed" . PHP_EOL;
            echo "   Debug - User found but password check failed" . PHP_EOL;
            echo "   Password hash: " . substr($user['password_hash'], 0, 20) . "..." . PHP_EOL;
        }
        exit(1);
    }
    
    echo PHP_EOL . "=== SUCCESS ===" . PHP_EOL;
    echo "Admin user is ready!" . PHP_EOL;
    echo "Login URL: https://cis.dev.ecigdis.co.nz/login" . PHP_EOL;
    echo "Email: {$adminEmail}" . PHP_EOL;
    echo "Password: {$adminPassword}" . PHP_EOL;
    echo PHP_EOL . "You can now login to the CIS admin system!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
