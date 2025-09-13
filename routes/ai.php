<?php
/**
 * AI Routes - Web and API routes for AI administration
 * File: routes/ai.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Route definitions for AI admin interface and API endpoints
 */

use App\Http\Controllers\AIAdminController;

// AI Admin Web Routes (require authentication and ai_admin permission)
$router->group(['prefix' => 'ai-admin', 'middleware' => ['auth', 'permission:ai_admin']], function() use ($router) {
    
    // Dashboard
    $router->get('/', [AIAdminController::class, 'dashboard']);
    $router->get('/dashboard', [AIAdminController::class, 'dashboard']);
    
    // API Key Management
    $router->get('/keys', [AIAdminController::class, 'keys']);
    $router->post('/keys/store', [AIAdminController::class, 'storeKey']);
    $router->post('/keys/test', [AIAdminController::class, 'testKey']);
    
    // AI Testing Interface
    $router->get('/testing', [AIAdminController::class, 'testing']);
    $router->post('/testing/execute', [AIAdminController::class, 'executeTest']);
    
    // Orchestration Testing
    $router->get('/orchestration', [AIAdminController::class, 'orchestration']);
    $router->post('/orchestration/execute', [AIAdminController::class, 'executeOrchestration']);
    
    // Events & Jobs Monitoring
    $router->get('/monitoring', [AIAdminController::class, 'monitoring']);
    $router->get('/monitoring/job-details', [AIAdminController::class, 'jobDetails']);
    
});

// AI API Routes (for programmatic access)
$router->group(['prefix' => 'api/ai', 'middleware' => ['auth', 'api']], function() use ($router) {
    
    // Health check
    $router->get('/health', function() {
        $ai_config = include __DIR__ . '/../config/ai.php';
        
        return [
            'success' => true,
            'status' => 'healthy',
            'ai_enabled' => $ai_config['enabled'] ?? false,
            'timestamp' => date('c'),
            'version' => '1.0.0'
        ];
    });
    
    // Provider status
    $router->get('/providers/status', [AIAdminController::class, 'getProviderStatus']);
    
    // Execute single AI operation
    $router->post('/execute/single', [AIAdminController::class, 'executeAPIOperation']);
    
    // Execute orchestration
    $router->post('/execute/chain', [AIAdminController::class, 'executeAPIChain']);
    $router->post('/execute/fanout', [AIAdminController::class, 'executeAPIFanOut']);
    $router->post('/execute/fanin', [AIAdminController::class, 'executeAPIFanIn']);
    
    // Job management
    $router->get('/jobs/{job_id}/status', [AIAdminController::class, 'getAPIJobStatus']);
    $router->post('/jobs/{job_id}/cancel', [AIAdminController::class, 'cancelAPIJob']);
    
    // Events querying
    $router->get('/events', [AIAdminController::class, 'getAPIEvents']);
    $router->get('/events/{event_id}', [AIAdminController::class, 'getAPIEvent']);
    
});

// AI Webhook Routes (for external integrations)
$router->group(['prefix' => 'webhooks/ai'], function() use ($router) {
    
    // OpenAI webhook (if needed for future features)
    $router->post('/openai', [AIAdminController::class, 'openaiWebhook']);
    
    // Claude webhook (if needed for future features)
    $router->post('/claude', [AIAdminController::class, 'claudeWebhook']);
    
});
