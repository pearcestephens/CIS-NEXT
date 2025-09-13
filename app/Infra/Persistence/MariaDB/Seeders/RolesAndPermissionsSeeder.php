<?php
declare(strict_types=1);

use App\Infra\Persistence\MariaDB\Seeder;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Roles and Permissions Seeder
 */
return new class extends Seeder
{
    public function run(Database $db): void
    {
        // Create roles
        $roles = [
            ['name' => 'super_admin', 'description' => 'Super Administrator with all permissions'],
            ['name' => 'admin', 'description' => 'Administrator with most permissions'],
            ['name' => 'manager', 'description' => 'Store Manager with outlet-specific permissions'],
            ['name' => 'staff', 'description' => 'Staff member with basic permissions'],
            ['name' => 'viewer', 'description' => 'Read-only access to reports and analytics'],
        ];
        
        foreach ($roles as $role) {
            $db->execute("
                INSERT IGNORE INTO roles (name, description) 
                VALUES (?, ?)
            ", [$role['name'], $role['description']]);
        }
        
        // Create permissions
        $permissions = [
            // Super admin
            ['code' => '*', 'description' => 'All permissions'],
            
            // Admin permissions
            ['code' => 'admin.*', 'description' => 'All admin permissions'],
            ['code' => 'admin.access', 'description' => 'Access admin panel'],
            
            // User management
            ['code' => 'users.*', 'description' => 'All user management permissions'],
            ['code' => 'users.view', 'description' => 'View users'],
            ['code' => 'users.create', 'description' => 'Create users'],
            ['code' => 'users.edit', 'description' => 'Edit users'],
            ['code' => 'users.delete', 'description' => 'Delete users'],
            
            // Role management
            ['code' => 'roles.*', 'description' => 'All role management permissions'],
            ['code' => 'roles.view', 'description' => 'View roles'],
            ['code' => 'roles.create', 'description' => 'Create roles'],
            ['code' => 'roles.edit', 'description' => 'Edit roles'],
            ['code' => 'roles.delete', 'description' => 'Delete roles'],
            
            // Configuration
            ['code' => 'config.*', 'description' => 'All configuration permissions'],
            ['code' => 'config.view', 'description' => 'View configuration'],
            ['code' => 'config.edit', 'description' => 'Edit configuration'],
            
            // System monitoring
            ['code' => 'system.*', 'description' => 'All system permissions'],
            ['code' => 'system.view', 'description' => 'View system status'],
            ['code' => 'logs.view', 'description' => 'View system logs'],
            ['code' => 'profiler.view', 'description' => 'View profiler data'],
            
            // Feed management
            ['code' => 'feed.*', 'description' => 'All feed permissions'],
            ['code' => 'feed.view', 'description' => 'View feed'],
            ['code' => 'feed.manage', 'description' => 'Manage feed settings'],
            
            // Analytics and reporting
            ['code' => 'analytics.*', 'description' => 'All analytics permissions'],
            ['code' => 'analytics.view', 'description' => 'View analytics'],
            ['code' => 'reports.view', 'description' => 'View reports'],
            ['code' => 'reports.export', 'description' => 'Export reports'],
            
            // Outlets/Stores
            ['code' => 'outlets.*', 'description' => 'All outlet permissions'],
            ['code' => 'outlets.view', 'description' => 'View outlet information'],
            ['code' => 'outlets.manage', 'description' => 'Manage outlet settings'],
            
            // Staff portal
            ['code' => 'portal.*', 'description' => 'All portal permissions'],
            ['code' => 'portal.access', 'description' => 'Access staff portal'],
            ['code' => 'portal.profile', 'description' => 'Manage own profile'],
            
            // API access
            ['code' => 'api.*', 'description' => 'All API permissions'],
            ['code' => 'api.admin', 'description' => 'Admin API access'],
        ];
        
        foreach ($permissions as $permission) {
            $db->execute("
                INSERT IGNORE INTO permissions (code, description) 
                VALUES (?, ?)
            ", [$permission['code'], $permission['description']]);
        }
        
        // Assign permissions to roles
        $rolePermissions = [
            'super_admin' => ['*'],
            'admin' => [
                'admin.*', 'users.*', 'roles.*', 'config.*', 'system.*', 
                'feed.*', 'analytics.*', 'reports.*', 'outlets.*', 
                'portal.access', 'api.admin'
            ],
            'manager' => [
                'admin.access', 'users.view', 'feed.view', 'analytics.view',
                'reports.view', 'outlets.view', 'outlets.manage', 'portal.*'
            ],
            'staff' => [
                'portal.*', 'outlets.view'
            ],
            'viewer' => [
                'admin.access', 'feed.view', 'analytics.view', 'reports.view',
                'outlets.view'
            ],
        ];
        
        foreach ($rolePermissions as $roleName => $permissionCodes) {
            // Get role ID
            $stmt = $db->execute("SELECT id FROM roles WHERE name = ?", [$roleName]);
            $role = $stmt->fetch();
            
            if ($role) {
                foreach ($permissionCodes as $code) {
                    // Get permission ID
                    $stmt = $db->execute("SELECT id FROM permissions WHERE code = ?", [$code]);
                    $permission = $stmt->fetch();
                    
                    if ($permission) {
                        $db->execute("
                            INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                            VALUES (?, ?)
                        ", [$role['id'], $permission['id']]);
                    }
                }
            }
        }
    }
};
