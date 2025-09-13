<?php
declare(strict_types=1);

/**
 * Web Routes
 * Define routes for web interface
 */

use App\Http\Router;

/** @var Router $router */

// Public routes
$router->get('/', function() {
    http_response_code(200);
    echo 'OK';
}, 'home');

// Health check - FIXED: Handle database unavailability gracefully
$router->get('/_health', function() {
    header('Content-Type: application/json');
    
    $health = [
        'ok' => true,
        'timestamp' => date('c'),
        'service' => 'CIS'
    ];
    
    // Only check database if it's available (handle bootstrap failures)
    if (!defined('DB_UNAVAILABLE')) {
        try {
            $health['db_connect'] = \App\Infra\Persistence\MariaDB\Database::health();
        } catch (\Throwable $e) {
            $health['db_connect'] = false;
            $health['db_error'] = 'Connection failed';
        }
    } else {
        $health['db_connect'] = false;
        $health['db_error'] = 'Database unavailable during bootstrap';
    }
    
    echo json_encode($health);
}, 'health');

// Alternative health endpoints
$router->get('/health', function() {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'service' => 'CIS'
    ]);
}, 'health.alt');

$router->get('/ready', function() {
    header('Content-Type: application/json');
    echo json_encode([
        'ready' => true,
        'timestamp' => date('c'),
        'routes' => 'loaded'
    ]);
}, 'ready');

// Authentication routes - WORKING VERSION
$router->get('/login', 'WorkingAuthController@showLogin', 'login.working');
$router->post('/login', 'WorkingAuthController@login', 'login.working.post');
$router->post('/logout', 'WorkingAuthController@logout', 'logout.working');

// Original authentication routes - FIXED RESTORED
$router->get('/login-old', 'AuthController@showLogin', 'login');
$router->post('/login-old', 'AuthController@login', 'login.post');
$router->post('/logout-old', 'AuthController@logout', 'logout');

// Protected dashboard
$router->get('/dashboard', 'DashboardController@index', 'dashboard')
       ->middleware(['App\Http\Middlewares\AuthMiddleware']);

// Admin area with full middleware stack
$router->group([
    'prefix' => '/admin', 
    'middleware' => [
        'App\Http\Middlewares\AuthMiddleware',
        'App\Http\Middlewares\RBACMiddleware:admin',
        'App\Http\Middlewares\RequestNonce',
        'App\Http\Middlewares\CSRFMiddleware'
    ]
], function(Router $router) {
    
    // Core admin pages
    $router->get('/', 'Admin\DashboardController@index', 'admin.index');
    $router->get('/dashboard', 'Admin\DashboardController@dashboard', 'admin.dashboard');
    
    // Tools section
    $router->get('/tools', 'Admin\ToolsController@index', 'admin.tools');
    
    // Database tools
    $router->get('/database/prefix-manager', 'Admin\DatabaseController@prefixManager', 'admin.database.prefix_manager');
    
    // Integration management
    $router->get('/integrations', 'Admin\IntegrationsController@index', 'admin.integrations');
    $router->get('/integrations/health', 'Admin\IntegrationsController@health', 'admin.integrations.health');
    
    // Settings management
    $router->get('/settings', 'Admin\SettingsController@index', 'admin.settings');
    $router->post('/settings', 'Admin\SettingsController@update', 'admin.settings.update');
    
    // Analytics
    $router->get('/analytics', 'Admin\AnalyticsController@index', 'admin.analytics');
    
    // User management
    $router->get('/users', 'Admin\UsersController@index', 'admin.users');
    $router->get('/users/create', 'Admin\UsersController@create', 'admin.users.create');
    $router->post('/users', 'Admin\UsersController@store', 'admin.users.store');
    
    // System monitoring
    $router->get('/system', 'Admin\SystemController@index', 'admin.system');
    $router->get('/logs', 'Admin\SystemController@logs', 'admin.logs');
    
});

// Admin API routes for real-time data (require authentication)
$router->group([
    'prefix' => '/api/admin',
    'middleware' => [
        'App\Http\Middlewares\AuthMiddleware',
        'App\Http\Middlewares\RBACMiddleware:admin'
    ]
], function(Router $router) {
    $router->get('/metrics', 'Api\Admin\DashboardApiController@getDashboardMetrics');
    $router->get('/dashboard-metrics', 'Api\Admin\DashboardApiController@getDashboardMetrics');
    $router->get('/performance', 'Api\Admin\DashboardApiController@getPerformanceData');
    $router->get('/activities', 'Api\Admin\DashboardApiController@getRecentActivities');
    $router->get('/alerts', 'Api\Admin\DashboardApiController@getSystemAlerts');
});

// Test route for dashboard (remove in production)
$router->get('/test-admin-dashboard', function() {
    // Set up test session
    $_SESSION = [
        'user_id' => 1,
        'user' => ['name' => 'Test Admin', 'role' => 'admin'],
        'csrf_token' => 'test_token'
    ];
    
    $controller = new \App\Http\Controllers\Admin\DashboardController();
    return $controller->dashboard();
});

// Working admin route with only AuthMiddleware (for testing)
$router->get('/admin-dashboard', 'Admin\DashboardController@index', 'admin.working')
       ->middleware(['App\Http\Middlewares\AuthMiddleware']);
