<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Role Model
 * 
 * Handles user roles and role-based access control
 */
class Role extends BaseModel
{
    protected string $table = 'cis_roles';
    protected array $fillable = [
        'name',
        'description',
        'is_system_role'
    ];

    /**
     * Get all roles with permission counts
     */
    public function getAllWithCounts(): array
    {
        $sql = "SELECT 
                    r.*,
                    COUNT(DISTINCT rp.permission_id) as permission_count,
                    COUNT(DISTINCT ur.user_id) as user_count
                FROM {$this->table} r
                LEFT JOIN cis_role_permissions rp ON r.id = rp.role_id
                LEFT JOIN cis_user_roles ur ON r.id = ur.role_id
                GROUP BY r.id
                ORDER BY r.name";
        
        $stmt = $this->database->executeQuery($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get user roles
     */
    public function getUserRoles(int $userId): array
    {
        $sql = "SELECT r.*
                FROM {$this->table} r
                INNER JOIN cis_user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
                ORDER BY r.name";
        
        $stmt = $this->database->executeQuery($sql, [$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Assign role to user
     */
    public function assignToUser(int $roleId, int $userId): bool
    {
        // Check if already assigned
        $checkSql = "SELECT COUNT(*) as count FROM cis_user_roles 
                     WHERE user_id = ? AND role_id = ?";
        $stmt = $this->database->executeQuery($checkSql, [$userId, $roleId]);
        
        if ($stmt->fetch(\PDO::FETCH_ASSOC)['count'] > 0) {
            return true; // Already assigned
        }

        $sql = "INSERT INTO cis_user_roles (user_id, role_id, created_at) 
                VALUES (?, ?, NOW())";
        
        $stmt = $this->database->executeQuery($sql, [$userId, $roleId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Remove role from user
     */
    public function removeFromUser(int $roleId, int $userId): bool
    {
        $sql = "DELETE FROM cis_user_roles 
                WHERE user_id = ? AND role_id = ?";
        
        $stmt = $this->database->executeQuery($sql, [$userId, $roleId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get role by name
     */
    public function getByName(string $name): ?array
    {
        $roles = $this->where(['name' => $name]);
        return $roles ? $roles[0] : null;
    }

    /**
     * Create default roles
     */
    public function createDefaults(): array
    {
        $defaultRoles = [
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'is_system_role' => true
            ],
            [
                'name' => 'Administrator',
                'description' => 'System administration with most permissions',
                'is_system_role' => true
            ],
            [
                'name' => 'Manager',
                'description' => 'Management level access to business functions',
                'is_system_role' => false
            ],
            [
                'name' => 'User',
                'description' => 'Standard user access',
                'is_system_role' => false
            ],
            [
                'name' => 'Viewer',
                'description' => 'Read-only access to basic functions',
                'is_system_role' => false
            ]
        ];

        $created = [];
        foreach ($defaultRoles as $role) {
            // Check if role already exists
            $existing = $this->where(['name' => $role['name']]);
            if (empty($existing)) {
                $id = $this->create($role);
                $created[] = array_merge($role, ['id' => $id]);
            }
        }

        return $created;
    }

    /**
     * Sync user roles (replace all with new set)
     */
    public function syncUserRoles(int $userId, array $roleIds): bool
    {
        try {
            // Start transaction
            $this->database->query('START TRANSACTION');
            
            // Remove existing roles
            $deleteSql = "DELETE FROM cis_user_roles WHERE user_id = ?";
            $this->database->executeQuery($deleteSql, [$userId]);
            
            // Add new roles
            foreach ($roleIds as $roleId) {
                $this->assignToUser((int) $roleId, $userId);
            }
            
            $this->database->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $this->database->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Get roles available for assignment (non-system or by admin)
     */
    public function getAssignableRoles(bool $canAssignSystem = false): array
    {
        $conditions = [];
        $params = [];
        
        if (!$canAssignSystem) {
            $conditions[] = "is_system_role = ?";
            $params[] = false;
        }
        
        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY name";
        $stmt = $this->database->executeQuery($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if role can be deleted
     */
    public function canDelete(int $roleId): array
    {
        $role = $this->find($roleId);
        if (!$role) {
            return ['can_delete' => false, 'reason' => 'Role not found'];
        }

        // System roles cannot be deleted
        if ($role['is_system_role']) {
            return ['can_delete' => false, 'reason' => 'System roles cannot be deleted'];
        }

        // Check if role has users
        $userSql = "SELECT COUNT(*) as count FROM cis_user_roles WHERE role_id = ?";
        $stmt = $this->database->executeQuery($userSql, [$roleId]);
        $userCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

        if ($userCount > 0) {
            return [
                'can_delete' => false, 
                'reason' => "Role is assigned to {$userCount} user(s)"
            ];
        }

        return ['can_delete' => true, 'reason' => null];
    }

    /**
     * Delete role with safety checks
     */
    public function safeDelete(int $roleId): bool
    {
        $canDelete = $this->canDelete($roleId);
        
        if (!$canDelete['can_delete']) {
            throw new \InvalidArgumentException($canDelete['reason']);
        }

        try {
            // Start transaction
            $this->database->query('START TRANSACTION');
            
            // Remove role permissions
            $deletePermsSql = "DELETE FROM cis_role_permissions WHERE role_id = ?";
            $this->database->executeQuery($deletePermsSql, [$roleId]);
            
            // Delete role
            $result = $this->delete($roleId);
            
            $this->database->query('COMMIT');
            return $result;
        } catch (\Exception $e) {
            $this->database->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Get role hierarchy (if implementing hierarchical roles)
     */
    public function getHierarchy(): array
    {
        // Simple role hierarchy based on system roles and naming
        $roles = $this->all();
        $hierarchy = [];
        
        $order = [
            'Super Admin' => 1,
            'Administrator' => 2,
            'Manager' => 3,
            'User' => 4,
            'Viewer' => 5
        ];
        
        foreach ($roles as $role) {
            $role['hierarchy_level'] = $order[$role['name']] ?? 99;
            $hierarchy[] = $role;
        }
        
        // Sort by hierarchy level
        usort($hierarchy, function($a, $b) {
            return $a['hierarchy_level'] <=> $b['hierarchy_level'];
        });
        
        return $hierarchy;
    }

    /**
     * Check if user can manage target role
     */
    public function canManageRole(int $userRoleId, int $targetRoleId): bool
    {
        $userRole = $this->find($userRoleId);
        $targetRole = $this->find($targetRoleId);
        
        if (!$userRole || !$targetRole) {
            return false;
        }
        
        // Super Admin can manage everything
        if ($userRole['name'] === 'Super Admin') {
            return true;
        }
        
        // System roles can only be managed by Super Admin
        if ($targetRole['is_system_role'] && $userRole['name'] !== 'Super Admin') {
            return false;
        }
        
        // Admins can manage non-system roles
        if ($userRole['name'] === 'Administrator' && !$targetRole['is_system_role']) {
            return true;
        }
        
        return false;
    }

    /**
     * Get role statistics
     */
    public function getRoleStats(): array
    {
        // Total roles
        $totalSql = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->database->executeQuery($totalSql);
        $total = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
        
        // System roles
        $systemSql = "SELECT COUNT(*) as count FROM {$this->table} WHERE is_system_role = 1";
        $stmt = $this->database->executeQuery($systemSql);
        $systemCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
        
        // Role usage
        $usageSql = "SELECT 
                        r.name,
                        r.is_system_role,
                        COUNT(ur.user_id) as user_count
                     FROM {$this->table} r
                     LEFT JOIN cis_user_roles ur ON r.id = ur.role_id
                     GROUP BY r.id
                     ORDER BY user_count DESC";
        $stmt = $this->database->executeQuery($usageSql);
        $usage = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'total_roles' => (int) $total,
            'system_roles' => (int) $systemCount,
            'custom_roles' => (int) $total - (int) $systemCount,
            'role_usage' => $usage
        ];
    }

    /**
     * Clone role with new name
     */
    public function cloneRole(int $sourceRoleId, string $newName, ?string $newDescription = null): int
    {
        $sourceRole = $this->find($sourceRoleId);
        if (!$sourceRole) {
            throw new \InvalidArgumentException('Source role not found');
        }
        
        // Check if new name already exists
        if ($this->getByName($newName)) {
            throw new \InvalidArgumentException('Role name already exists');
        }
        
        try {
            // Start transaction
            $this->database->query('START TRANSACTION');
            
            // Create new role
            $newRoleId = $this->create([
                'name' => $newName,
                'description' => $newDescription ?: $sourceRole['description'] . ' (Copy)',
                'is_system_role' => false // Cloned roles are never system roles
            ]);
            
            // Copy permissions
            $permissionModel = new Permission();
            $sourcePermissions = $permissionModel->getRolePermissions($sourceRoleId);
            
            foreach ($sourcePermissions as $permission) {
                $permissionModel->assignToRole($permission['id'], $newRoleId);
            }
            
            $this->database->query('COMMIT');
            return $newRoleId;
        } catch (\Exception $e) {
            $this->database->query('ROLLBACK');
            throw $e;
        }
    }
}
