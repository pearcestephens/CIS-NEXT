<?php
/**
 * Integration Routes
 * File: routes/integrations.php
 * Author: CIS Developer Bot
 * Updated: 2025-09-13
 * Purpose: Routes for integration system
 */

use App\Http\Controllers\IntegrationController;

$router->group(['prefix' => 'integrations'], function($router) {
    
    // Core integration dashboard
    $router->get('/dashboard', [IntegrationController::class, 'dashboard']);
    
    // Health check endpoint
    $router->get('/health', [IntegrationController::class, 'allHealth']);
    
    // Future integrations can be added here
});

// API routes for integrations
$router->group(['prefix' => 'api/integrations'], function($router) {
    
    // Health check API
    $router->get('/health', [IntegrationController::class, 'allHealth']);
    
    // Future API endpoints can be added here
});
