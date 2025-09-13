<?php
declare(strict_types=1);

use App\Infra\Persistence\MariaDB\Seeder;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Admin User Seeder
 */
return new class extends Seeder
{
    public function run(Database $db): void
    {
        // Get super_admin role ID
        $stmt = $db->execute("SELECT id FROM roles WHERE name = 'super_admin'");
        $role = $stmt->fetch();
        
        if (!$role) {
            throw new \RuntimeException('Super admin role not found');
        }
        
        // Check if admin user already exists
        $stmt = $db->execute("SELECT id FROM users WHERE email = 'admin@ecigdis.co.nz'");
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            echo "Admin user already exists\n";
            return;
        }
        
        // Create admin user
        $passwordHash = password_hash('CHANGE_ME_NOW', PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        $db->execute("
            INSERT INTO users (
                email, password_hash, first_name, last_name, 
                role_id, status, email_verified_at
            ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ", [
            'admin@ecigdis.co.nz',
            $passwordHash,
            'System',
            'Administrator',
            $role['id']
        ]);
        
        echo "Admin user created: admin@ecigdis.co.nz / CHANGE_ME_NOW\n";
        
        // Create additional test users for development
        $testUsers = [
            [
                'email' => 'manager@ecigdis.co.nz',
                'first_name' => 'Store',
                'last_name' => 'Manager',
                'role' => 'manager'
            ],
            [
                'email' => 'staff@ecigdis.co.nz',
                'first_name' => 'Staff',
                'last_name' => 'Member',
                'role' => 'staff'
            ],
            [
                'email' => 'viewer@ecigdis.co.nz',
                'first_name' => 'Report',
                'last_name' => 'Viewer',
                'role' => 'viewer'
            ],
        ];
        
        foreach ($testUsers as $userData) {
            // Get role ID
            $stmt = $db->execute("SELECT id FROM roles WHERE name = ?", [$userData['role']]);
            $userRole = $stmt->fetch();
            
            if ($userRole) {
                $userPasswordHash = password_hash('password123', PASSWORD_ARGON2ID);
                
                $db->execute("
                    INSERT INTO users (
                        email, password_hash, first_name, last_name, 
                        role_id, status, email_verified_at
                    ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
                ", [
                    $userData['email'],
                    $userPasswordHash,
                    $userData['first_name'],
                    $userData['last_name'],
                    $userRole['id']
                ]);
                
                echo "Test user created: {$userData['email']} / password123\n";
            }
        }
    }
};
