<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Audit;

/**
 * User Management Controller
 * 
 * Handles user CRUD operations and administration
 */
class UserController extends BaseController
{
    private User $userModel;
    private Role $roleModel;
    private Permission $permissionModel;
    private Audit $auditModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
        $this->roleModel = new Role();
        $this->permissionModel = new Permission();
        $this->auditModel = new Audit();
    }

    /**
     * List users
     */
    public function index(): void
    {
        $this->requirePermission('users.view');

        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 25);
        $search = $_GET['search'] ?? '';
        $roleFilter = $_GET['role'] ?? '';

        try {
            $users = $this->userModel->getUsers($page, $limit, $search, $roleFilter);
            $roles = $this->roleModel->all();

            if ($this->isJsonRequest()) {
                $this->jsonSuccess($users);
            } else {
                $this->render('users/index', [
                    'title' => 'User Management',
                    'users' => $users['data'],
                    'pagination' => $users['pagination'],
                    'roles' => $roles,
                    'search' => $search,
                    'roleFilter' => $roleFilter
                ]);
            }

        } catch (\Exception $e) {
            $this->handleError($e, 'index');
        }
    }

    /**
     * Show user details
     */
    public function show(): void
    {
        $this->requirePermission('users.view');

        $userId = (int) ($_GET['id'] ?? 0);

        if ($userId <= 0) {
            if ($this->isJsonRequest()) {
                $this->jsonError('Invalid user ID');
            } else {
                $this->redirect('/users');
            }
            return;
        }

        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                if ($this->isJsonRequest()) {
                    $this->jsonError('User not found', 404);
                } else {
                    $this->redirect('/users');
                }
                return;
            }

            $userRoles = $this->roleModel->getUserRoles($userId);
            $userPermissions = $this->permissionModel->getUserPermissions($userId);
            $recentActivity = $this->auditModel->getUserAudit($userId, 20);

            if ($this->isJsonRequest()) {
                $this->jsonSuccess([
                    'user' => $user,
                    'roles' => $userRoles,
                    'permissions' => $userPermissions,
                    'recent_activity' => $recentActivity
                ]);
            } else {
                $this->render('users/show', [
                    'title' => "User: {$user['username']}",
                    'user' => $user,
                    'roles' => $userRoles,
                    'permissions' => $userPermissions,
                    'recent_activity' => $recentActivity
                ]);
            }

        } catch (\Exception $e) {
            $this->handleError($e, 'show');
        }
    }

    /**
     * Show create user form
     */
    public function create(): void
    {
        $this->requirePermission('users.create');

        $roles = $this->roleModel->getAssignableRoles($this->hasPermission($_SESSION['user_id'], 'roles.system'));

        $this->render('users/create', [
            'title' => 'Create User',
            'roles' => $roles,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * Store new user
     */
    public function store(): void
    {
        $this->requirePermission('users.create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users/create');
            return;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonError('Invalid CSRF token', 403);
            return;
        }

        $input = $this->sanitize($this->getInput());

        // Validation rules
        $rules = [
            'username' => [
                'required' => true,
                'min_length' => 3,
                'max_length' => 50,
                'pattern' => '/^[a-zA-Z0-9_-]+$/',
                'pattern_message' => 'Username can only contain letters, numbers, underscores, and hyphens'
            ],
            'email' => [
                'required' => true,
                'email' => true,
                'max_length' => 255
            ],
            'password' => [
                'required' => true,
                'min_length' => 8,
                'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'pattern_message' => 'Password must contain at least one uppercase letter, lowercase letter, number, and special character'
            ],
            'first_name' => [
                'max_length' => 100
            ],
            'last_name' => [
                'max_length' => 100
            ]
        ];

        $errors = $this->validate($input, $rules);

        // Check for duplicate username/email
        if (empty($errors['username']) && $this->userModel->usernameExists($input['username'])) {
            $errors['username'] = 'Username is already taken';
        }

        if (empty($errors['email']) && $this->userModel->emailExists($input['email'])) {
            $errors['email'] = 'Email address is already registered';
        }

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 400, $errors);
            return;
        }

        try {
            $userId = $this->userModel->createUser([
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => $input['password'],
                'first_name' => $input['first_name'] ?? null,
                'last_name' => $input['last_name'] ?? null,
                'is_active' => !empty($input['is_active'])
            ]);

            // Assign roles
            if (!empty($input['roles']) && is_array($input['roles'])) {
                $this->roleModel->syncUserRoles($userId, array_map('intval', $input['roles']));
            }

            // Log user creation
            $this->auditModel->logAction(
                $_SESSION['user_id'],
                'users.create',
                'User',
                $userId,
                null,
                [
                    'username' => $input['username'],
                    'email' => $input['email'],
                    'roles' => $input['roles'] ?? []
                ]
            );

            $this->jsonSuccess([
                'message' => 'User created successfully',
                'user_id' => $userId,
                'redirect' => "/users/{$userId}"
            ]);

        } catch (\Exception $e) {
            $this->handleError($e, 'store');
        }
    }

    /**
     * Show edit user form
     */
    public function edit(): void
    {
        $this->requirePermission('users.edit');

        $userId = (int) ($_GET['id'] ?? 0);

        if ($userId <= 0) {
            $this->redirect('/users');
            return;
        }

        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                $this->redirect('/users');
                return;
            }

            $userRoles = $this->roleModel->getUserRoles($userId);
            $availableRoles = $this->roleModel->getAssignableRoles($this->hasPermission($_SESSION['user_id'], 'roles.system'));

            $this->render('users/edit', [
                'title' => "Edit User: {$user['username']}",
                'user' => $user,
                'user_roles' => array_column($userRoles, 'id'),
                'available_roles' => $availableRoles,
                'csrf_token' => $this->generateCsrfToken()
            ]);

        } catch (\Exception $e) {
            $this->handleError($e, 'edit');
        }
    }

    /**
     * Update user
     */
    public function update(): void
    {
        $this->requirePermission('users.edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
            return;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonError('Invalid CSRF token', 403);
            return;
        }

        $userId = (int) ($_POST['id'] ?? 0);
        $input = $this->sanitize($this->getInput());

        if ($userId <= 0) {
            $this->jsonError('Invalid user ID');
            return;
        }

        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                $this->jsonError('User not found', 404);
                return;
            }

            // Validation rules
            $rules = [
                'username' => [
                    'required' => true,
                    'min_length' => 3,
                    'max_length' => 50,
                    'pattern' => '/^[a-zA-Z0-9_-]+$/',
                    'pattern_message' => 'Username can only contain letters, numbers, underscores, and hyphens'
                ],
                'email' => [
                    'required' => true,
                    'email' => true,
                    'max_length' => 255
                ],
                'first_name' => [
                    'max_length' => 100
                ],
                'last_name' => [
                    'max_length' => 100
                ]
            ];

            // Add password validation if password is being changed
            if (!empty($input['password'])) {
                $rules['password'] = [
                    'min_length' => 8,
                    'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                    'pattern_message' => 'Password must contain at least one uppercase letter, lowercase letter, number, and special character'
                ];
            }

            $errors = $this->validate($input, $rules);

            // Check for duplicate username/email (excluding current user)
            if (empty($errors['username']) && $input['username'] !== $user['username'] && $this->userModel->usernameExists($input['username'])) {
                $errors['username'] = 'Username is already taken';
            }

            if (empty($errors['email']) && $input['email'] !== $user['email'] && $this->userModel->emailExists($input['email'])) {
                $errors['email'] = 'Email address is already registered';
            }

            if (!empty($errors)) {
                $this->jsonError('Validation failed', 400, $errors);
                return;
            }

            // Prepare update data
            $updateData = [
                'username' => $input['username'],
                'email' => $input['email'],
                'first_name' => $input['first_name'] ?? null,
                'last_name' => $input['last_name'] ?? null,
                'is_active' => !empty($input['is_active'])
            ];

            // Add password if provided
            if (!empty($input['password'])) {
                $updateData['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            // Store old values for audit
            $oldValues = [
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'is_active' => $user['is_active']
            ];

            // Update user
            $this->userModel->update($userId, $updateData);

            // Update roles if provided
            if (isset($input['roles']) && is_array($input['roles'])) {
                $oldRoles = array_column($this->roleModel->getUserRoles($userId), 'id');
                $newRoles = array_map('intval', $input['roles']);
                
                $this->roleModel->syncUserRoles($userId, $newRoles);
                
                // Log role changes
                if ($oldRoles !== $newRoles) {
                    $this->auditModel->logAction(
                        $_SESSION['user_id'],
                        'users.roles_updated',
                        'User',
                        $userId,
                        ['roles' => $oldRoles],
                        ['roles' => $newRoles]
                    );
                }
            }

            // Log user update
            $this->auditModel->logAction(
                $_SESSION['user_id'],
                'users.update',
                'User',
                $userId,
                $oldValues,
                array_intersect_key($updateData, $oldValues)
            );

            $this->jsonSuccess([
                'message' => 'User updated successfully',
                'redirect' => "/users/{$userId}"
            ]);

        } catch (\Exception $e) {
            $this->handleError($e, 'update');
        }
    }

    /**
     * Delete user
     */
    public function delete(): void
    {
        $this->requirePermission('users.delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
            return;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonError('Invalid CSRF token', 403);
            return;
        }

        $userId = (int) ($_POST['id'] ?? 0);

        if ($userId <= 0) {
            $this->jsonError('Invalid user ID');
            return;
        }

        // Prevent self-deletion
        if ($userId === (int) $_SESSION['user_id']) {
            $this->jsonError('Cannot delete your own account');
            return;
        }

        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                $this->jsonError('User not found', 404);
                return;
            }

            // Check if user can be deleted
            $canDelete = $this->userModel->canDelete($userId);
            if (!$canDelete['can_delete']) {
                $this->jsonError($canDelete['reason']);
                return;
            }

            // Soft delete or hard delete based on business rules
            $deleted = $this->userModel->safeDelete($userId);

            if ($deleted) {
                // Log deletion
                $this->auditModel->logAction(
                    $_SESSION['user_id'],
                    'users.delete',
                    'User',
                    $userId,
                    [
                        'username' => $user['username'],
                        'email' => $user['email']
                    ]
                );

                $this->jsonSuccess([
                    'message' => 'User deleted successfully'
                ]);
            } else {
                $this->jsonError('Failed to delete user');
            }

        } catch (\Exception $e) {
            $this->handleError($e, 'delete');
        }
    }

    /**
     * Activate/Deactivate user
     */
    public function toggleActive(): void
    {
        $this->requirePermission('users.edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
            return;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonError('Invalid CSRF token', 403);
            return;
        }

        $userId = (int) ($_POST['id'] ?? 0);

        if ($userId <= 0) {
            $this->jsonError('Invalid user ID');
            return;
        }

        // Prevent self-deactivation
        if ($userId === (int) $_SESSION['user_id']) {
            $this->jsonError('Cannot deactivate your own account');
            return;
        }

        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                $this->jsonError('User not found', 404);
                return;
            }

            $newStatus = !$user['is_active'];
            $this->userModel->update($userId, ['is_active' => $newStatus]);

            // Log status change
            $this->auditModel->logAction(
                $_SESSION['user_id'],
                $newStatus ? 'users.activate' : 'users.deactivate',
                'User',
                $userId,
                ['is_active' => $user['is_active']],
                ['is_active' => $newStatus]
            );

            $this->jsonSuccess([
                'message' => $newStatus ? 'User activated successfully' : 'User deactivated successfully',
                'is_active' => $newStatus
            ]);

        } catch (\Exception $e) {
            $this->handleError($e, 'toggleActive');
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(): void
    {
        $this->requirePermission('users.edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed', 405);
            return;
        }

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonError('Invalid CSRF token', 403);
            return;
        }

        $userId = (int) ($_POST['id'] ?? 0);

        if ($userId <= 0) {
            $this->jsonError('Invalid user ID');
            return;
        }

        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                $this->jsonError('User not found', 404);
                return;
            }

            // Generate temporary password
            $tempPassword = $this->generateTemporaryPassword();
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

            // Update password and set force reset flag
            $this->userModel->update($userId, [
                'password' => $hashedPassword,
                'password_reset_required' => true,
                'password_reset_token' => null,
                'password_reset_expires' => null
            ]);

            // Log password reset
            $this->auditModel->logAction(
                $_SESSION['user_id'],
                'users.password_reset',
                'User',
                $userId,
                null,
                ['reset_by_admin' => true]
            );

            // TODO: Send email with temporary password
            $this->logger->info('Password reset by admin', [
                'user_id' => $userId,
                'username' => $user['username'],
                'temp_password' => $tempPassword,
                'reset_by' => $_SESSION['user_id']
            ]);

            $this->jsonSuccess([
                'message' => 'Password reset successfully. User will receive new login credentials.',
                'temp_password' => $tempPassword // Remove this in production
            ]);

        } catch (\Exception $e) {
            $this->handleError($e, 'resetPassword');
        }
    }

    /**
     * Generate temporary password
     */
    private function generateTemporaryPassword(): string
    {
        $length = 12;
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#$%&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }

    /**
     * Export users
     */
    public function export(): void
    {
        $this->requirePermission('users.view');

        try {
            $users = $this->userModel->getAllForExport();
            $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');
            
            // CSV header
            fputcsv($output, [
                'ID', 'Username', 'Email', 'First Name', 'Last Name', 
                'Is Active', 'Roles', 'Created At', 'Last Login'
            ]);

            // CSV data
            foreach ($users as $user) {
                fputcsv($output, [
                    $user['id'],
                    $user['username'],
                    $user['email'],
                    $user['first_name'],
                    $user['last_name'],
                    $user['is_active'] ? 'Yes' : 'No',
                    $user['roles'] ?? '',
                    $user['created_at'],
                    $user['last_login_at']
                ]);
            }

            fclose($output);

            // Log export
            $this->auditModel->logAction(
                $_SESSION['user_id'],
                'users.export',
                'System',
                null,
                null,
                ['export_count' => count($users)]
            );

        } catch (\Exception $e) {
            $this->handleError($e, 'export');
        }
    }
}
