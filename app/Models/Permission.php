<?php
/**
 * CIS - Central Information System
 * app/Models/Permission.php
 * 
 * Permission model for RBAC operations, role assignments, 
 * and access control in pure MVC pattern.
 *
 * @package CIS
 * @version 1.0.0
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

declare(strict_types=1);

namespace App\Models;

use App\Shared\Logging\Logger;
use Exception;

class Permission extends BaseModel
{
    protected string $table = 'permissions';
    private Logger $logger;

    public function __construct(Logger $logger = null)
    {
        parent::__construct();
        $this->logger = $logger ?? Logger::getInstance();
    }
    
    protected function getTable(): string
    {
        return $this->db->table($this->table);
    }

    /**
     * Get all roles with their permissions
     */
    public function getAllRolesWithPermissions(): array
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        try {
            $sql = "
                SELECT 
                    r.name as role_name,
                    r.description as role_description,
                    r.level as role_level,
                    GROUP_CONCAT(p.name ORDER BY p.name) as permissions,
                    COUNT(p.id) as permission_count
                FROM {roles} r
                LEFT JOIN role_permissions rp ON r.name = rp.role_name
                LEFT JOIN permissions p ON rp.permission_name = p.name
                GROUP BY r.name, r.description, r.level
                ORDER BY r.level DESC, r.name
            ";
            
            $result = $this->query($sql);
            $roles = $result->fetchAll();

            // Process the results to format permissions as arrays
            $formattedRoles = [];
            foreach ($roles as $role) {
                $formattedRoles[] = [
                    'name' => $role['role_name'],
                    'description' => $role['role_description'],
                    'level' => (int) $role['role_level'],
                    'permissions' => $role['permissions'] ? explode(',', $role['permissions']) : [],
                    'permission_count' => (int) $role['permission_count']
                ];
            }

            return [
                'success' => true,
                'data' => $formattedRoles,
                'meta' => ['total' => count($formattedRoles)],
                'request_id' => $requestId
            ];

        } catch (Exception $e) {
            $this->logger->error('Get all roles with permissions error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'PERMISSION_ERROR',
                    'message' => 'Failed to retrieve roles and permissions'
                ],
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Get permissions for a specific role
     */
    public function getRolePermissions(string $roleName): array
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        try {
            $sql = "
                SELECT 
                    p.id,
                    p.name,
                    p.description,
                    p.category
                FROM {permissions} p
                JOIN role_permissions rp ON p.name = rp.permission_name
                WHERE rp.role_name = ?
                ORDER BY p.category, p.name
            ";
            
            $result = $this->query($sql, [$roleName]);
            $permissions = $result->fetchAll();

            return [
                'success' => true,
                'data' => [
                    'role' => $roleName,
                    'permissions' => $permissions,
                    'total' => count($permissions)
                ],
                'request_id' => $requestId
            ];

        } catch (Exception $e) {
            $this->logger->error('Get role permissions error', [
                'role' => $roleName,
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'PERMISSION_ERROR',
                    'message' => 'Failed to retrieve role permissions'
                ],
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Check if a user has a specific permission
     */
    public function userHasPermission(int $userId, string $permission): array
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        try {
            $sql = "
                SELECT COUNT(*) as has_permission
                FROM {permissions} p
                JOIN role_permissions rp ON p.name = rp.permission_name
                JOIN users u ON rp.role_name = u.role
                WHERE u.id = ? AND p.name = ? AND u.is_active = 1
            ";
            
            $result = $this->query($sql, [$userId, $permission]);
            $row = $result->fetch();
            $hasPermission = (int) $row['has_permission'] > 0;

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'permission' => $permission,
                    'has_permission' => $hasPermission
                ],
                'request_id' => $requestId
            ];

        } catch (Exception $e) {
            $this->logger->error('Check user permission error', [
                'user_id' => $userId,
                'permission' => $permission,
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'PERMISSION_ERROR',
                    'message' => 'Failed to check user permission'
                ],
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Get user's role and permissions
     */
    public function getUserRoleAndPermissions(int $userId): array
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        try {
            $sql = "
                SELECT 
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.role,
                    r.description as role_description,
                    r.level as role_level,
                    GROUP_CONCAT(p.name ORDER BY p.name) as permissions
                FROM users u
                JOIN roles r ON u.role = r.name
                LEFT JOIN role_permissions rp ON r.name = rp.role_name
                LEFT JOIN permissions p ON rp.permission_name = p.name
                WHERE u.id = ? AND u.is_active = 1
                GROUP BY u.id, u.email, u.first_name, u.last_name, u.role, r.description, r.level
            ";
            
            $result = $this->query($sql, [$userId]);
            $userData = $result->fetchAll();

            if (empty($userData)) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => 'User not found or inactive'
                    ],
                    'request_id' => $requestId
                ];
            }

            $user = $userData[0];
            $permissions = $user['permissions'] ? explode(',', $user['permissions']) : [];

            return [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => (int) $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'full_name' => trim($user['first_name'] . ' ' . $user['last_name'])
                    ],
                    'role' => [
                        'name' => $user['role'],
                        'description' => $user['role_description'],
                        'level' => (int) $user['role_level']
                    ],
                    'permissions' => $permissions,
                    'permission_count' => count($permissions)
                ],
                'request_id' => $requestId
            ];

        } catch (Exception $e) {
            $this->logger->error('Get user role and permissions error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'PERMISSION_ERROR',
                    'message' => 'Failed to retrieve user role and permissions'
                ],
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Update user role
     */
    public function updateUserRole(int $userId, string $newRole): array
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        try {
            // Check if role exists
            $sql = "SELECT * FROM {roles} WHERE name = ?";
            $result = $this->query($sql, [$newRole]);
            $role = $result->fetch();

            if (!$role) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'ROLE_NOT_FOUND',
                        'message' => 'Role does not exist'
                    ],
                    'request_id' => $requestId
                ];
            }

            // Get current user data
            $sql = "SELECT * FROM users WHERE id = ?";
            $result = $this->query($sql, [$userId]);
            $user = $result->fetch();

            if (!$user) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => 'User not found'
                    ],
                    'request_id' => $requestId
                ];
            }

            $oldRole = $user['role'];

            // Update user role
            $sql = "UPDATE {users} SET role = ?, updated_at = NOW() WHERE id = ?";
            $this->query($sql, [$newRole, $userId]);

            $this->logger->info('User role updated', [
                'user_id' => $userId,
                'user_email' => $user['email'],
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'request_id' => $requestId
            ]);

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'request_id' => $requestId
            ];

        } catch (Exception $e) {
            $this->logger->error('Update user role error', [
                'user_id' => $userId,
                'new_role' => $newRole,
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'PERMISSION_ERROR',
                    'message' => 'Failed to update user role'
                ],
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Create a new role
     */
    public function createRole(string $name, string $description, int $level): array
    {
        try {
            $sql = "INSERT INTO {roles} (name, description, level, created_at) VALUES (?, ?, ?, NOW())";
            $this->query($sql, [$name, $description, $level]);

            return [
                'success' => true,
                'message' => 'Role created successfully',
                'role_name' => $name
            ];

        } catch (Exception $e) {
            $this->logger->error('Error creating role', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create role'
            ];
        }
    }

    /**
     * Create a new permission
     */
    public function createPermission(string $name, string $description, string $category): array
    {
        try {
            $sql = "INSERT INTO {permissions} (name, description, category, created_at) VALUES (?, ?, ?, NOW())";
            $this->query($sql, [$name, $description, $category]);

            return [
                'success' => true,
                'message' => 'Permission created successfully',
                'permission_name' => $name
            ];

        } catch (Exception $e) {
            $this->logger->error('Error creating permission', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create permission'
            ];
        }
    }

    /**
     * Assign permission to role
     */
    public function assignPermissionToRole(string $roleName, string $permissionName): array
    {
        try {
            $sql = "INSERT IGNORE INTO {role_permissions} (role_name, permission_name, created_at) VALUES (?, ?, NOW())";
            $this->query($sql, [$roleName, $permissionName]);

            return [
                'success' => true,
                'message' => 'Permission assigned to role successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Error assigning permission to role', [
                'role' => $roleName,
                'permission' => $permissionName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to assign permission to role'
            ];
        }
    }
}
