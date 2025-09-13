<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Models\Permission;

/**
 * RBAC Middleware
 * Role-Based Access Control for fine-grained permissions
 */
class RBAC
{
    public function handle(array $request, callable $next): mixed
    {
        $path = $request['REQUEST_URI'] ?? '/';
        $method = $request['REQUEST_METHOD'] ?? 'GET';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        // Check if user is authenticated
        if (!isset($_REQUEST['_user'])) {
            return $next($request);
        }
        
        $user = $_REQUEST['_user'];
        $requiredPermission = $this->getRequiredPermission($path, $method);
        
        if ($requiredPermission && !$this->hasPermission($user, $requiredPermission)) {
            if (str_starts_with($path, '/api/')) {
                header('HTTP/1.1 403 Forbidden');
                return [
                    'json' => [
                        'success' => false,
                        'error' => [
                            'code' => 'INSUFFICIENT_PERMISSIONS',
                            'message' => 'You do not have permission to access this resource',
                            'required_permission' => $requiredPermission,
                        ],
                        'meta' => [
                            'request_id' => $request['HTTP_X_REQUEST_ID'] ?? 'unknown',
                        ],
                    ]
                ];
            }
            
            return ['view' => $this->render403Page()];
        }
        
        return $next($request);
    }
    
    private function getRequiredPermission(string $path, string $method): ?string
    {
        // Permission mapping for different routes
        $permissionMap = [
            // Admin routes
            '/admin' => 'admin.access',
            '/admin/users' => 'users.view',
            '/admin/users/create' => 'users.create',
            '/admin/users/{id}/edit' => 'users.edit',
            '/admin/users/{id}/delete' => 'users.delete',
            
            '/admin/roles' => 'roles.view',
            '/admin/roles/create' => 'roles.create',
            '/admin/roles/{id}/edit' => 'roles.edit',
            '/admin/roles/{id}/delete' => 'roles.delete',
            
            '/admin/config' => 'config.view',
            '/admin/config/edit' => 'config.edit',
            
            '/admin/profiler' => 'profiler.view',
            '/admin/logs' => 'logs.view',
            '/admin/system' => 'system.view',
            
            // Feed routes
            '/admin/feed' => 'feed.view',
            '/admin/feed/manage' => 'feed.manage',
            
            // Analytics routes
            '/admin/analytics' => 'analytics.view',
            '/admin/reports' => 'reports.view',
            
            // API routes
            '/api/admin' => 'api.admin',
            '/api/users' => $method === 'GET' ? 'users.view' : 'users.manage',
            '/api/config' => $method === 'GET' ? 'config.view' : 'config.edit',
        ];
        
        // Check for exact matches
        if (isset($permissionMap[$path])) {
            return $permissionMap[$path];
        }
        
        // Check for pattern matches
        foreach ($permissionMap as $pattern => $permission) {
            if (strpos($pattern, '{') !== false) {
                $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
                if (preg_match('#^' . str_replace('/', '\/', $regex) . '$#', $path)) {
                    return $permission;
                }
            }
        }
        
        return null;
    }
    
    private function hasPermission(array $user, string $permission): bool
    {
        $permissions = $user['permissions'] ?? [];
        
        // Super admin has all permissions
        if (in_array('*', $permissions)) {
            return true;
        }
        
        // Check exact permission
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        // Check wildcard permissions
        $parts = explode('.', $permission);
        for ($i = count($parts) - 1; $i > 0; $i--) {
            $wildcardPermission = implode('.', array_slice($parts, 0, $i)) . '.*';
            if (in_array($wildcardPermission, $permissions)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function render403Page(): string
    {
        ob_start();
        include __DIR__ . '/../Views/errors/403.php';
        return ob_get_clean();
    }
}
