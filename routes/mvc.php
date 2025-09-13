<?php
/**
 * MVC Routes Configuration
 * 
 * Route definitions for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

use App\Core\Router;

// Initialize Router
$router = new Router();

// Health and monitoring routes
$router->get('/health', 'App\\Controllers\\HealthController@health');
$router->get('/ready', 'App\\Controllers\\HealthController@ready');
$router->get('/metrics', 'App\\Controllers\\HealthController@metrics');

// Home routes
$router->get('/', 'App\\Controllers\\HomeController@index');
$router->get('/home', 'App\\Controllers\\HomeController@index');
$router->get('/dashboard', 'App\\Controllers\\HomeController@dashboard');

// Authentication routes
$router->get('/login', 'App\\Controllers\\AuthController@showLogin');
$router->post('/login', 'App\\Controllers\\AuthController@login');
$router->post('/logout', 'App\\Controllers\\AuthController@logout');

// User management routes (authenticated)
$router->group(['middleware' => 'auth'], function ($router) {
    $router->get('/users', 'App\\Controllers\\UserController@index');
    $router->get('/users/create', 'App\\Controllers\\UserController@create');
    $router->post('/users', 'App\\Controllers\\UserController@store');
    $router->get('/users/{id}', 'App\\Controllers\\UserController@show');
    $router->get('/users/{id}/edit', 'App\\Controllers\\UserController@edit');
    $router->put('/users/{id}', 'App\\Controllers\\UserController@update');
    $router->delete('/users/{id}', 'App\\Controllers\\UserController@destroy');
});

// API routes
$router->group(['prefix' => 'api/v1', 'middleware' => 'api'], function ($router) {
    // System API
    $router->get('/system/status', 'App\\Controllers\\Api\\SystemController@status');
    $router->get('/system/info', 'App\\Controllers\\Api\\SystemController@info');
    
    // Authentication API
    $router->post('/auth/login', 'App\\Controllers\\Api\\AuthController@login');
    $router->post('/auth/refresh', 'App\\Controllers\\Api\\AuthController@refresh');
    $router->post('/auth/logout', 'App\\Controllers\\Api\\AuthController@logout');
    
    // Protected API routes
    $router->group(['middleware' => 'auth'], function ($router) {
        $router->get('/users', 'App\\Controllers\\Api\\UserController@index');
        $router->post('/users', 'App\\Controllers\\Api\\UserController@store');
        $router->get('/users/{id}', 'App\\Controllers\\Api\\UserController@show');
        $router->put('/users/{id}', 'App\\Controllers\\Api\\UserController@update');
        $router->delete('/users/{id}', 'App\\Controllers\\Api\\UserController@destroy');
    });
});

// Admin routes (admin role required)
$router->group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function ($router) {
    $router->get('/', 'App\\Controllers\\AdminController@index');
    $router->get('/config', 'App\\Controllers\\AdminController@config');
    $router->post('/config', 'App\\Controllers\\AdminController@updateConfig');
    $router->get('/logs', 'App\\Controllers\\AdminController@logs');
    $router->get('/performance', 'App\\Controllers\\AdminController@performance');
    $router->get('/security', 'App\\Controllers\\AdminController@security');
});

// Development routes (only in development environment)
if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
    $router->get('/dev/routes', 'App\\Controllers\\DevController@routes');
    $router->get('/dev/config', 'App\\Controllers\\DevController@config');
    $router->get('/dev/phpinfo', 'App\\Controllers\\DevController@phpinfo');
    $router->get('/dev/test-db', 'App\\Controllers\\DevController@testDatabase');
}

// Error handling routes
$router->get('/error/404', 'App\\Controllers\\ErrorController@notFound');
$router->get('/error/500', 'App\\Controllers\\ErrorController@serverError');
$router->get('/error/403', 'App\\Controllers\\ErrorController@forbidden');

return $router;
