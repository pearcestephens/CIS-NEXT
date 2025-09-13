<?php
declare(strict_types=1);

/**
 * Web Routes - Phase 0 Clean Implementation
 * Purpose: Define all routes with working controllers
 * Quality: Production-ready route definitions
 * Justification: Phase 0 needs clean, working routes for validation
 */

use App\Http\Router;

/** @var Router $router */

// ============================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================

// Home page
$router->get('/', function() {
    http_response_code(200);
    echo '<h1>CIS System - Phase 0</h1><p>Main routes working. <a href="/admin/">Go to Admin</a></p>';
}, 'home');

// Health check endpoints
$router->get('/_health', function() {
    header('Content-Type: application/json');
    
    $health = [
        'ok' => true,
        'timestamp' => date('c'),
        'phase' => 'Phase 0 - Routes & CSP Foundation',
        'router' => 'working',
        'session' => session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive'
    ];
    
    echo json_encode($health, JSON_PRETTY_PRINT);
}, 'health');

$router->get('/health', function() {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'service' => 'CIS Phase 0'
    ], JSON_PRETTY_PRINT);
}, 'health.alt');

$router->get('/ready', function() {
    header('Content-Type: application/json');
    echo json_encode([
        'ready' => true,
        'timestamp' => date('c'),
        'routes' => 'loaded',
        'phase' => 'Phase 0'
    ], JSON_PRETTY_PRINT);
}, 'ready');

// ============================================
// USER DASHBOARD (Will have auth in Phase 1)
// ============================================

$router->get('/dashboard', function() {
    echo '<h1>CIS Dashboard - Phase 0</h1>';
    echo '<p>Basic dashboard working. Middleware will be added in Phase 1.</p>';
    echo '<p><a href="/admin/">Go to Admin Dashboard</a></p>';
}, 'dashboard');

// ============================================
// ADMIN ROUTES (Phase 0: No middleware for testing)
// ============================================

$router->group([
    'prefix' => '/admin'
], function(Router $router) {
    
    // Admin Dashboard
    $router->get('/', 'Admin\DashboardController@index', 'admin.index');
    $router->get('/dashboard', 'Admin\DashboardController@dashboard', 'admin.dashboard');
    
    // Tools
    $router->get('/tools', 'Admin\ToolsController@index', 'admin.tools');
    
    // Settings
    $router->get('/settings', 'Admin\SettingsController@index', 'admin.settings');
    $router->post('/settings', 'Admin\SettingsController@update', 'admin.settings.update');
    
    // User Management
    $router->get('/users', 'Admin\UsersController@index', 'admin.users');
    $router->get('/users/create', 'Admin\UsersController@create', 'admin.users.create');
    $router->post('/users', 'Admin\UsersController@store', 'admin.users.store');
    
    // Integrations
    $router->get('/integrations', 'Admin\IntegrationsController@index', 'admin.integrations');
    $router->get('/integrations/health', 'Admin\IntegrationsController@health', 'admin.integrations.health');
    
    // Analytics
    $router->get('/analytics', 'Admin\AnalyticsController@index', 'admin.analytics');
    
    // Database Tools
    $router->get('/database/prefix-manager', 'Admin\DatabaseController@prefixManager', 'admin.database.prefix_manager');
    
});

// ============================================
// AUTHENTICATION (Future Phase 1)
// ============================================

$router->get('/login', function() {
    echo '<h1>Login - Phase 0</h1><p>Authentication will be implemented in Phase 1</p>';
}, 'login');

$router->post('/login', function() {
    echo json_encode(['message' => 'Authentication coming in Phase 1']);
}, 'login.post');

$router->post('/logout', function() {
    echo json_encode(['message' => 'Logout coming in Phase 1']);
}, 'logout');
