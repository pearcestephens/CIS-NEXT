<?php
declare(strict_types=1);

namespace App\Http\Utils;

/**
 * Admin Page Registry
 * Central registry of all admin pages with routes, icons, and permissions
 * 
 * @author CIS Developer Bot
 * @created 2025-09-13
 */
class AdminPageRegistry
{
    private static array $pages = [];
    
    /**
     * Initialize registry with all admin pages
     */
    public static function init(): void
    {
        if (!empty(self::$pages)) {
            return;
        }
        
        self::$pages = [
            'overview' => [
                'route' => '/admin',
                'controller' => 'DashboardController@index',
                'view' => 'admin/dashboard.php',
                'icon' => 'fa-solid fa-gauge-high',
                'label' => 'Overview',
                'permissions' => ['admin'],
                'group' => 'overview',
                'priority' => 100,
                'implemented' => true
            ],
            
            'backups' => [
                'route' => '/admin/backups',
                'controller' => 'Admin\\BackupController@index',
                'view' => 'admin/backups.php',
                'icon' => 'fa-solid fa-shield-halved',
                'label' => 'Backups',
                'permissions' => ['admin'],
                'group' => 'system',
                'priority' => 90,
                'implemented' => false
            ],
            
            'migrations' => [
                'route' => '/admin/migrations',
                'controller' => 'Admin\\MigrationsController@index',
                'view' => 'admin/migrations.php',
                'icon' => 'fa-solid fa-database',
                'label' => 'Migrations',
                'permissions' => ['admin'],
                'group' => 'system',
                'priority' => 85,
                'implemented' => true // exists in tools/
            ],
            
            'seeds' => [
                'route' => '/admin/seeds',
                'controller' => 'Admin\\SeedsController@index',
                'view' => 'admin/seeds.php',
                'icon' => 'fa-solid fa-seedling',
                'label' => 'Seeds',
                'permissions' => ['admin'],
                'group' => 'system',
                'priority' => 80,
                'implemented' => false
            ],
            
            'cache' => [
                'route' => '/admin/cache',
                'controller' => 'Admin\\CacheController@index',
                'view' => 'admin/cache.php',
                'icon' => 'fa-solid fa-gauge',
                'label' => 'Cache',
                'permissions' => ['admin'],
                'group' => 'system',
                'priority' => 75,
                'implemented' => false
            ],
            
            'queue' => [
                'route' => '/admin/queue',
                'controller' => 'Admin\\QueueController@index',
                'view' => 'admin/queue.php',
                'icon' => 'fa-solid fa-list-check',
                'label' => 'Queue',
                'permissions' => ['admin'],
                'group' => 'system',
                'priority' => 70,
                'implemented' => false
            ],
            
            'cron' => [
                'route' => '/admin/cron',
                'controller' => 'Admin\\CronController@index',
                'view' => 'admin/cron.php',
                'icon' => 'fa-solid fa-clock',
                'label' => 'Cron Jobs',
                'permissions' => ['admin'],
                'group' => 'system',
                'priority' => 65,
                'implemented' => false
            ],
            
            'logs' => [
                'route' => '/admin/logs',
                'controller' => 'Admin\\LogsController@index',
                'view' => 'admin/logs.php',
                'icon' => 'fa-solid fa-file-lines',
                'label' => 'Logs',
                'permissions' => ['admin'],
                'group' => 'monitoring',
                'priority' => 60,
                'implemented' => false
            ],
            
            'security' => [
                'route' => '/admin/security',
                'controller' => 'Admin\\SecurityController@index',
                'view' => 'admin/security.php',
                'icon' => 'fa-solid fa-shield',
                'label' => 'Security',
                'permissions' => ['admin'],
                'group' => 'monitoring',
                'priority' => 55,
                'implemented' => true // security_dashboard.php exists
            ],
            
            'analytics' => [
                'route' => '/admin/analytics',
                'controller' => 'UserAnalyticsController@dashboard',
                'view' => 'admin/analytics.php',
                'icon' => 'fa-solid fa-chart-line',
                'label' => 'Analytics',
                'permissions' => ['admin'],
                'group' => 'monitoring',
                'priority' => 50,
                'implemented' => false
            ],
            
            'integrations' => [
                'route' => '/admin/integrations',
                'controller' => 'IntegrationController@dashboard',
                'view' => 'admin/integrations.php',
                'icon' => 'fa-solid fa-plug',
                'label' => 'Integrations',
                'permissions' => ['admin'],
                'group' => 'config',
                'priority' => 45,
                'implemented' => true // has controller
            ],
            
            'settings' => [
                'route' => '/admin/settings',
                'controller' => 'Admin\\SettingsController@index',
                'view' => 'admin/settings.php',
                'icon' => 'fa-solid fa-gear',
                'label' => 'Settings',
                'permissions' => ['admin'],
                'group' => 'config',
                'priority' => 40,
                'implemented' => false
            ],
            
            'users' => [
                'route' => '/admin/users',
                'controller' => 'Admin\\UsersController@index',
                'view' => 'admin/users.php',
                'icon' => 'fa-solid fa-users',
                'label' => 'Users',
                'permissions' => ['admin'],
                'group' => 'config',
                'priority' => 35,
                'implemented' => true // has controller
            ],
            
            'modules' => [
                'route' => '/admin/modules',
                'controller' => 'Admin\\ModulesController@index',
                'view' => 'admin/modules.php',
                'icon' => 'fa-solid fa-cubes',
                'label' => 'Module Inventory',
                'permissions' => ['super_admin'],
                'group' => 'system_admin',
                'priority' => 30,
                'implemented' => false
            ],
            
            'database' => [
                'route' => '/admin/database',
                'controller' => 'Admin\\DatabaseController@index',
                'view' => 'admin/database.php',
                'icon' => 'fa-solid fa-database',
                'label' => 'Database Tools',
                'permissions' => ['super_admin'],
                'group' => 'system_admin',
                'priority' => 25,
                'implemented' => false
            ]
        ];
    }
    
    /**
     * Get all pages
     */
    public static function getAll(): array
    {
        self::init();
        return self::$pages;
    }
    
    /**
     * Get pages by group
     */
    public static function getByGroup(string $group): array
    {
        self::init();
        return array_filter(self::$pages, fn($page) => $page['group'] === $group);
    }
    
    /**
     * Get pages for sidebar navigation
     */
    public static function getForSidebar(array $userPermissions = []): array
    {
        self::init();
        
        $pages = self::$pages;
        
        // Filter by permissions if provided
        if (!empty($userPermissions)) {
            $pages = array_filter($pages, function($page) use ($userPermissions) {
                return !empty(array_intersect($page['permissions'], $userPermissions));
            });
        }
        
        // Group by category
        $grouped = [];
        foreach ($pages as $key => $page) {
            $grouped[$page['group']][$key] = $page;
        }
        
        // Sort by priority within groups
        foreach ($grouped as &$group) {
            uasort($group, fn($a, $b) => $b['priority'] <=> $a['priority']);
        }
        
        return $grouped;
    }
    
    /**
     * Get missing pages (not implemented)
     */
    public static function getMissingPages(): array
    {
        self::init();
        return array_filter(self::$pages, fn($page) => !$page['implemented']);
    }
    
    /**
     * Check if page exists in filesystem
     */
    public static function checkImplementation(): array
    {
        self::init();
        $status = [];
        
        foreach (self::$pages as $key => $page) {
            $viewPath = __DIR__ . '/../../Views/' . $page['view'];
            $controllerExists = self::checkControllerExists($page['controller']);
            
            $status[$key] = [
                'page' => $page,
                'view_exists' => file_exists($viewPath),
                'controller_exists' => $controllerExists,
                'route_exists' => self::checkRouteExists($page['route']),
                'fully_implemented' => file_exists($viewPath) && $controllerExists
            ];
        }
        
        return $status;
    }
    
    /**
     * Check if controller exists
     */
    private static function checkControllerExists(string $controller): bool
    {
        [$class, $method] = explode('@', $controller);
        
        // Convert namespace to file path
        $path = str_replace(['App\\Http\\Controllers\\', '\\'], ['', '/'], $class) . '.php';
        $fullPath = __DIR__ . '/../../Controllers/' . $path;
        
        if (!file_exists($fullPath)) {
            return false;
        }
        
        // Check if method exists (basic check)
        $content = file_get_contents($fullPath);
        return strpos($content, "function {$method}") !== false;
    }
    
    /**
     * Check if route exists in web.php
     */
    private static function checkRouteExists(string $route): bool
    {
        $webRoutesPath = __DIR__ . '/../../../routes/web.php';
        if (!file_exists($webRoutesPath)) {
            return false;
        }
        
        $content = file_get_contents($webRoutesPath);
        $routePattern = str_replace('/admin', '', $route);
        return strpos($content, "'{$routePattern}'") !== false || 
               strpos($content, "\"{$routePattern}\"") !== false;
    }
    
    /**
     * Generate scaffolding report
     */
    public static function generateScaffoldingReport(): array
    {
        $implementation = self::checkImplementation();
        $missing = [];
        $existing = [];
        
        foreach ($implementation as $key => $status) {
            if (!$status['fully_implemented']) {
                $missing[$key] = $status;
            } else {
                $existing[$key] = $status;
            }
        }
        
        return [
            'total_pages' => count(self::$pages),
            'implemented' => count($existing),
            'missing' => count($missing),
            'missing_pages' => $missing,
            'existing_pages' => $existing,
            'completion_percentage' => round((count($existing) / count(self::$pages)) * 100, 2)
        ];
    }
}
