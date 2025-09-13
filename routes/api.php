<?php
declare(strict_types=1);

/**
 * API Routes
 * Define routes for API endpoints
 */

use App\Http\Router;

/** @var Router $router */

// API routes group
$router->group(['prefix' => '/api', 'name' => 'api'], function (Router $router) {
    
    // Health endpoint
    $router->get('/health', function() {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'healthy', 'timestamp' => date('c')]);
    }, 'health');

/**
 * API Routes - REST API endpoints
 * 
 * @author CIS V2 System  
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T14:45:00Z
 */

use App\Http\Router;

/** @var Router $router */

// Health endpoints (no CSRF required)
$router->get('/health', 'HealthController@health', 'api.health');
$router->get('/selftest', 'HealthController@selftest', 'api.selftest');
$router->get('/ready', 'HealthController@ready', 'api.ready');

// Authentication API
$router->post('/auth/login', 'AuthController@apiLogin', 'api.auth.login');
$router->post('/auth/logout', 'AuthController@apiLogout', 'api.auth.logout')
       ->middleware(['App\Http\Middlewares\AuthMiddleware']);
$router->get('/auth/user', 'AuthController@user', 'api.auth.user')
       ->middleware(['App\Http\Middlewares\AuthMiddleware']);

// Protected API routes
$router->group(['middleware' => ['App\Http\Middlewares\AuthMiddleware']], function(Router $router) {
    
    // Settings API
    $router->get('/settings', 'SettingsController@apiIndex', 'api.settings.index');
    $router->put('/settings/{key}', 'SettingsController@apiUpdate', 'api.settings.update')
           ->middleware(['App\Http\Middlewares\RBACMiddleware:admin']);
    
    // Feed API
    $router->get('/feed', 'FeedController@index', 'api.feed.index');
    $router->post('/feed', 'FeedController@create', 'api.feed.create')
           ->middleware(['App\Http\Middlewares\RBACMiddleware:admin,manager']);
    
    // Admin API routes
    $router->group(['prefix' => '/admin', 'middleware' => ['App\Http\Middlewares\RBACMiddleware:admin']], function(Router $router) {
        $router->get('/users', 'AdminController@apiUsers', 'api.admin.users');
        $router->get('/system/status', 'AdminController@apiSystemStatus', 'api.admin.system.status');
        $router->post('/automation/run', 'AutomationController@apiRun', 'api.admin.automation.run');
    });
});
    $router->get('/ready', 'Api\HealthController@ready', 'ready');
    
    // Authentication
    $router->post('/auth/login', 'Api\AuthController@login', 'auth.login');
    $router->post('/auth/logout', 'Api\AuthController@logout', 'auth.logout');
    $router->post('/auth/refresh', 'Api\AuthController@refresh', 'auth.refresh');
    
    // Admin API routes
    $router->group(['prefix' => '/admin', 'name' => 'admin'], function (Router $router) {
        
        // Users API
        $router->get('/users', 'Api\Admin\UsersController@index', 'users.index');
        $router->post('/users', 'Api\Admin\UsersController@store', 'users.store');
        $router->get('/users/{id}', 'Api\Admin\UsersController@show', 'users.show');
        $router->put('/users/{id}', 'Api\Admin\UsersController@update', 'users.update');
        $router->delete('/users/{id}', 'Api\Admin\UsersController@destroy', 'users.destroy');
        
        // Roles API
        $router->get('/roles', 'Api\Admin\RolesController@index', 'roles.index');
        $router->post('/roles', 'Api\Admin\RolesController@store', 'roles.store');
        $router->get('/roles/{id}', 'Api\Admin\RolesController@show', 'roles.show');
        $router->put('/roles/{id}', 'Api\Admin\RolesController@update', 'roles.update');
        $router->delete('/roles/{id}', 'Api\Admin\RolesController@destroy', 'roles.destroy');
        
        // Configuration API
        $router->get('/config', 'Api\Admin\ConfigController@index', 'config.index');
        $router->post('/config', 'Api\Admin\ConfigController@update', 'config.update');
        $router->get('/config/{key}', 'Api\Admin\ConfigController@show', 'config.show');
        $router->put('/config/{key}', 'Api\Admin\ConfigController@updateKey', 'config.update_key');
        
        // Profiler API
        $router->get('/profiler/requests', 'Api\Admin\ProfilerController@requests', 'profiler.requests');
        $router->get('/profiler/slow-queries', 'Api\Admin\ProfilerController@slowQueries', 'profiler.slow_queries');
        
        // System API
        $router->get('/system/status', 'Api\Admin\SystemController@status', 'system.status');
        $router->get('/system/logs', 'Api\Admin\SystemController@logs', 'system.logs');
        $router->get('/system/events', 'Api\Admin\SystemController@events', 'system.events');
        
    });
    
    // Feed API (Module 1) - AI Timeline & Newsfeed
    $router->group(['prefix' => '/feed', 'name' => 'feed'], function (Router $router) {
        $router->get('/events', 'FeedController@getEvents', 'events');
        $router->get('/events/{id}', 'FeedController@getEvent', 'event');
        $router->post('/events/mark-read', 'FeedController@markAsRead', 'mark_read');
        $router->post('/events/ingest', 'FeedController@ingestEvent', 'ingest');
        $router->get('/digest/daily', 'FeedController@getDailyDigest', 'daily_digest');
        $router->get('/outlets/overview', 'FeedController@getOutletsOverview', 'outlets_overview');
    });
    
    // Outlets API (Module 2)
    $router->group(['prefix' => '/outlets', 'name' => 'outlets'], function (Router $router) {
        $router->get('/', 'Api\OutletsController@index', 'index');
        $router->get('/{id}', 'Api\OutletsController@show', 'show');
        $router->get('/{id}/metrics', 'Api\OutletsController@metrics', 'metrics');
        $router->get('/{id}/kpis', 'Api\OutletsController@kpis', 'kpis');
        $router->get('/{id}/brief', 'Api\OutletsController@brief', 'brief');
    });
    
    // Staff Portal API (Module 3)
    $router->group(['prefix' => '/portal', 'name' => 'portal'], function (Router $router) {
        $router->get('/dashboard', 'Api\Portal\DashboardController@index', 'dashboard');
        $router->get('/tasks', 'Api\Portal\TasksController@index', 'tasks');
        $router->post('/tasks/{id}/complete', 'Api\Portal\TasksController@complete', 'tasks.complete');
        $router->get('/my-store', 'Api\Portal\StoreController@show', 'my_store');
        $router->get('/coach/tips', 'Api\Portal\CoachController@tips', 'coach.tips');
    });
    
    // Session Replay API
    $router->group(['prefix' => '/session-replay', 'name' => 'session_replay'], function (Router $router) {
        $router->post('/events', 'Api\SessionReplayController@storeEvents', 'store_events');
        $router->post('/consent', 'Api\SessionReplayController@consent', 'consent');
        $router->get('/sessions', 'Api\SessionReplayController@sessions', 'sessions');
        $router->get('/sessions/{id}', 'Api\SessionReplayController@showSession', 'show_session');
    });
    
    // Notifications API
    $router->get('/notifications', 'Api\NotificationsController@index', 'notifications');
    $router->post('/notifications/{id}/read', 'Api\NotificationsController@markRead', 'notifications.read');
    $router->post('/notifications/{id}/dismiss', 'Api\NotificationsController@dismiss', 'notifications.dismiss');
    
});
