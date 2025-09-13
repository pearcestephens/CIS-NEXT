<?php
/**
 * AI Admin Controller - Key management and testing interface
 * File: app/Http/Controllers/AIAdminController.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Admin interface for AI system management, testing, and monitoring
 */

namespace App\Http\Controllers;

use App\Integrations\OpenAI\Client as OpenAIClient;
use App\Integrations\Claude\Client as ClaudeClient;
use App\Shared\AI\Orchestrator;
use App\Shared\AI\Events;
use App\Shared\Secrets;
use App\Shared\ValidationHelper;
use App\Shared\SecurityHelper;
use Exception;

class AIAdminController extends BaseController {
    private array $ai_config;
    private ValidationHelper $validator;
    
    public function __construct() {
        parent::__construct();
        $this->ai_config = include __DIR__ . '/../../../config/ai.php';
        $this->validator = new ValidationHelper();
        
        // Require admin permissions
        $this->requirePermission('ai_admin');
    }
    
    /**
     * AI Dashboard - Main admin interface
     */
    public function dashboard(): string {
        try {
            // Get AI system status
            $openai_status = $this->checkProviderStatus('openai');
            $claude_status = $this->checkProviderStatus('claude');
            
            // Get recent events summary
            $events_summary = $this->getEventsStaSummary();
            
            // Get job statistics
            $job_stats = $this->getJobStatistics();
            
            // Get API key status
            $key_status = $this->getAPIKeyStatus();
            
            return $this->render('ai_admin/dashboard', [
                'ai_config' => $this->ai_config,
                'openai_status' => $openai_status,
                'claude_status' => $claude_status,
                'events_summary' => $events_summary,
                'job_stats' => $job_stats,
                'key_status' => $key_status,
                'page_title' => 'AI System Dashboard'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('AI dashboard error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to load AI dashboard: ' . $e->getMessage());
        }
    }
    
    /**
     * API Key Management Interface
     */
    public function keys(): string {
        try {
            // Get all API keys (masked)
            $keys = $this->getAPIKeys();
            
            return $this->render('ai_admin/keys', [
                'keys' => $keys,
                'page_title' => 'AI API Key Management'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('AI keys page error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to load API keys: ' . $e->getMessage());
        }
    }
    
    /**
     * Store/Update API Key
     */
    public function storeKey(): array {
        try {
            $this->requireCSRF();
            
            $provider = $this->validator->required($_POST['provider'], 'string');
            $key_name = $this->validator->required($_POST['key_name'], 'string');
            $api_key = $this->validator->required($_POST['api_key'], 'string');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $priority = $this->validator->optional($_POST['priority'], 'integer', 100);
            
            // Validate provider
            if (!in_array($provider, ['openai', 'claude'])) {
                throw new Exception('Invalid provider');
            }
            
            // Test the API key before storing
            $test_result = $this->testAPIKey($provider, $api_key);
            if (!$test_result['valid']) {
                return $this->jsonResponse(false, 'API key validation failed: ' . $test_result['error']);
            }
            
            // Encrypt and store the key
            $encrypted_key = Secrets::encrypt($api_key);
            
            global $mysqli;
            
            // Check if key already exists
            $check_sql = "SELECT id FROM cis_ai_keys WHERE provider = ? AND key_name = ?";
            $check_stmt = $mysqli->prepare($check_sql);
            $check_stmt->bind_param('ss', $provider, $key_name);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            
            if ($existing) {
                // Update existing key
                $sql = "UPDATE cis_ai_keys 
                       SET encrypted_key = ?, is_active = ?, priority = ?, updated_at = NOW(), updated_by = ?
                       WHERE id = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('siiii', $encrypted_key, $is_active, $priority, $_SESSION['user_id'], $existing['id']);
            } else {
                // Insert new key
                $sql = "INSERT INTO cis_ai_keys (provider, key_name, encrypted_key, is_active, priority, created_by) 
                       VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('sssiii', $provider, $key_name, $encrypted_key, $is_active, $priority, $_SESSION['user_id']);
            }
            
            $stmt->execute();
            
            $this->logger->info('AI API key stored', [
                'provider' => $provider,
                'key_name' => $key_name,
                'action' => $existing ? 'updated' : 'created',
                'user_id' => $_SESSION['user_id']
            ]);
            
            return $this->jsonResponse(true, 'API key saved successfully', [
                'provider' => $provider,
                'key_name' => $key_name,
                'test_result' => $test_result
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Store AI key error', ['error' => $e->getMessage()]);
            return $this->jsonResponse(false, 'Failed to store API key: ' . $e->getMessage());
        }
    }
    
    /**
     * Test API Key validity
     */
    public function testKey(): array {
        try {
            $this->requireCSRF();
            
            $provider = $this->validator->required($_POST['provider'], 'string');
            $key_id = $this->validator->optional($_POST['key_id'], 'integer');
            $test_key = $this->validator->optional($_POST['test_key'], 'string');
            
            if ($key_id) {
                // Test existing stored key
                $key_data = $this->getAPIKeyById($key_id);
                if (!$key_data) {
                    return $this->jsonResponse(false, 'API key not found');
                }
                $api_key = Secrets::decrypt($key_data['encrypted_key']);
            } else {
                // Test provided key
                $api_key = $test_key;
            }
            
            if (!$api_key) {
                return $this->jsonResponse(false, 'No API key provided');
            }
            
            $test_result = $this->testAPIKey($provider, $api_key);
            
            return $this->jsonResponse($test_result['valid'], $test_result['message'], [
                'provider' => $provider,
                'test_result' => $test_result
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Test AI key error', ['error' => $e->getMessage()]);
            return $this->jsonResponse(false, 'Failed to test API key: ' . $e->getMessage());
        }
    }
    
    /**
     * AI Testing Interface
     */
    public function testing(): string {
        try {
            // Get available models and providers
            $providers = $this->getAvailableProviders();
            
            return $this->render('ai_admin/testing', [
                'providers' => $providers,
                'page_title' => 'AI Testing & Validation'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('AI testing page error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to load AI testing: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute AI Test
     */
    public function executeTest(): array {
        try {
            $this->requireCSRF();
            
            $provider = $this->validator->required($_POST['provider'], 'string');
            $operation = $this->validator->required($_POST['operation'], 'string');
            $test_data = json_decode($_POST['test_data'] ?? '{}', true);
            $options = json_decode($_POST['options'] ?? '{}', true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse(false, 'Invalid JSON in test data or options');
            }
            
            // Execute test using orchestrator
            $orchestrator = new Orchestrator();
            $result = $orchestrator->executeSingle($provider, $operation, $test_data, $options);
            
            $this->logger->info('AI test executed', [
                'provider' => $provider,
                'operation' => $operation,
                'success' => $result['success'],
                'job_id' => $result['job_id'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            
            return $this->jsonResponse($result['success'], 
                $result['success'] ? 'Test completed successfully' : 'Test failed',
                $result
            );
            
        } catch (Exception $e) {
            $this->logger->error('Execute AI test error', ['error' => $e->getMessage()]);
            return $this->jsonResponse(false, 'Failed to execute test: ' . $e->getMessage());
        }
    }
    
    /**
     * Orchestration Testing Interface
     */
    public function orchestration(): string {
        try {
            return $this->render('ai_admin/orchestration', [
                'job_types' => [
                    Events::JOB_TYPE_SINGLE => 'Single Operation',
                    Events::JOB_TYPE_CHAIN => 'Chain Execution',
                    Events::JOB_TYPE_FANOUT => 'Fan-Out (Parallel)',
                    Events::JOB_TYPE_FANIN => 'Fan-In (Aggregate)'
                ],
                'page_title' => 'AI Orchestration Testing'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('AI orchestration page error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to load orchestration page: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute Orchestration Test
     */
    public function executeOrchestration(): array {
        try {
            $this->requireCSRF();
            
            $job_type = $this->validator->required($_POST['job_type'], 'string');
            $config = json_decode($_POST['config'] ?? '{}', true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse(false, 'Invalid JSON configuration');
            }
            
            $orchestrator = new Orchestrator();
            
            switch ($job_type) {
                case Events::JOB_TYPE_CHAIN:
                    $result = $orchestrator->executeChain($config);
                    break;
                case Events::JOB_TYPE_FANOUT:
                    $result = $orchestrator->executeFanOut($config);
                    break;
                case Events::JOB_TYPE_FANIN:
                    $result = $orchestrator->executeFanIn($config);
                    break;
                default:
                    return $this->jsonResponse(false, 'Invalid job type');
            }
            
            $this->logger->info('AI orchestration executed', [
                'job_type' => $job_type,
                'success' => $result['success'],
                'job_id' => $result['job_id'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            
            return $this->jsonResponse($result['success'], 
                $result['success'] ? 'Orchestration completed successfully' : 'Orchestration failed',
                $result
            );
            
        } catch (Exception $e) {
            $this->logger->error('Execute orchestration error', ['error' => $e->getMessage()]);
            return $this->jsonResponse(false, 'Failed to execute orchestration: ' . $e->getMessage());
        }
    }
    
    /**
     * Events & Jobs Monitoring
     */
    public function monitoring(): string {
        try {
            $page = $this->validator->optional($_GET['page'], 'integer', 1);
            $limit = $this->validator->optional($_GET['limit'], 'integer', 50);
            $filter_provider = $this->validator->optional($_GET['provider'], 'string');
            $filter_status = $this->validator->optional($_GET['status'], 'string');
            
            // Get recent events
            $events = $this->getEventsWithPagination($page, $limit, $filter_provider, $filter_status);
            
            // Get recent jobs
            $jobs = $this->getJobsWithPagination($page, $limit);
            
            return $this->render('ai_admin/monitoring', [
                'events' => $events,
                'jobs' => $jobs,
                'page' => $page,
                'limit' => $limit,
                'filters' => [
                    'provider' => $filter_provider,
                    'status' => $filter_status
                ],
                'page_title' => 'AI Events & Jobs Monitoring'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('AI monitoring page error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to load monitoring page: ' . $e->getMessage());
        }
    }
    
    /**
     * Get Job Details
     */
    public function jobDetails(): array {
        try {
            $job_id = $this->validator->required($_GET['job_id'], 'string');
            
            $orchestrator = new Orchestrator();
            $result = $orchestrator->getJobStatus($job_id);
            
            if (!$result['success']) {
                return $this->jsonResponse(false, 'Job not found');
            }
            
            return $this->jsonResponse(true, 'Job details retrieved', $result);
            
        } catch (Exception $e) {
            $this->logger->error('Get job details error', ['error' => $e->getMessage()]);
            return $this->jsonResponse(false, 'Failed to get job details: ' . $e->getMessage());
        }
    }
    
    // Helper Methods
    
    private function checkProviderStatus(string $provider): array {
        try {
            switch ($provider) {
                case 'openai':
                    $client = new OpenAIClient();
                    break;
                case 'claude':
                    $client = new ClaudeClient();
                    break;
                default:
                    return ['available' => false, 'error' => 'Unknown provider'];
            }
            
            // Simple test to check if provider is available
            $test_result = $this->testAPIKey($provider, null);
            
            return [
                'available' => true,
                'has_keys' => $this->hasActiveKeys($provider),
                'test_result' => $test_result
            ];
            
        } catch (Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function testAPIKey(string $provider, ?string $api_key): array {
        try {
            if (!$api_key) {
                // Try to get an active key for testing
                $key_data = $this->getActiveKey($provider);
                if (!$key_data) {
                    return ['valid' => false, 'error' => 'No active API key found', 'message' => 'No API key available for testing'];
                }
                $api_key = Secrets::decrypt($key_data['encrypted_key']);
            }
            
            switch ($provider) {
                case 'openai':
                    $client = new OpenAIClient();
                    $client->setApiKey($api_key);
                    
                    // Simple test - get models list
                    $result = $client->chat([
                        ['role' => 'user', 'content' => 'Hello']
                    ], ['model' => 'gpt-3.5-turbo', 'max_tokens' => 5]);
                    
                    return [
                        'valid' => $result['success'],
                        'error' => $result['success'] ? null : $result['error'],
                        'message' => $result['success'] ? 'API key is valid' : $result['error']
                    ];
                    
                case 'claude':
                    $client = new ClaudeClient();
                    $client->setApiKey($api_key);
                    
                    $result = $client->chat([
                        ['role' => 'user', 'content' => 'Hello']
                    ], ['model' => 'claude-3-haiku-20240307', 'max_tokens' => 5]);
                    
                    return [
                        'valid' => $result['success'],
                        'error' => $result['success'] ? null : $result['error'],
                        'message' => $result['success'] ? 'API key is valid' : $result['error']
                    ];
                    
                default:
                    return ['valid' => false, 'error' => 'Unknown provider', 'message' => 'Provider not supported'];
            }
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'message' => 'API key test failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function getAPIKeys(): array {
        global $mysqli;
        
        $sql = "SELECT id, provider, key_name, is_active, priority, created_at, updated_at 
                FROM cis_ai_keys 
                ORDER BY provider, priority DESC, created_at DESC";
        
        $result = $mysqli->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getAPIKeyById(int $key_id): ?array {
        global $mysqli;
        
        $sql = "SELECT * FROM cis_ai_keys WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $key_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }
    
    private function getActiveKey(string $provider): ?array {
        global $mysqli;
        
        $sql = "SELECT * FROM cis_ai_keys 
                WHERE provider = ? AND is_active = 1 
                ORDER BY priority DESC, created_at DESC 
                LIMIT 1";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $provider);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }
    
    private function hasActiveKeys(string $provider): bool {
        global $mysqli;
        
        $sql = "SELECT COUNT(*) as count FROM cis_ai_keys WHERE provider = ? AND is_active = 1";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $provider);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] > 0;
    }
    
    private function getEventsStaSummary(): array {
        global $mysqli;
        
        $sql = "SELECT 
                    provider,
                    status,
                    COUNT(*) as count,
                    AVG(tokens_used) as avg_tokens,
                    AVG(response_time_ms) as avg_response_time
                FROM cis_ai_events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY provider, status";
        
        $result = $mysqli->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getJobStatistics(): array {
        global $mysqli;
        
        $sql = "SELECT 
                    job_type,
                    status,
                    COUNT(*) as count,
                    AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration
                FROM cis_ai_orchestration_jobs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY job_type, status";
        
        $result = $mysqli->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getAPIKeyStatus(): array {
        global $mysqli;
        
        $sql = "SELECT provider, COUNT(*) as total, SUM(is_active) as active 
                FROM cis_ai_keys 
                GROUP BY provider";
        
        $result = $mysqli->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getAvailableProviders(): array {
        return [
            'openai' => [
                'name' => 'OpenAI',
                'models' => ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo'],
                'operations' => ['chat', 'embedding', 'image']
            ],
            'claude' => [
                'name' => 'Claude (Anthropic)',
                'models' => ['claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307'],
                'operations' => ['chat', 'vision']
            ]
        ];
    }
    
    private function getEventsWithPagination(int $page, int $limit, ?string $provider = null, ?string $status = null): array {
        global $mysqli;
        
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        $types = '';
        
        if ($provider) {
            $where_conditions[] = "provider = ?";
            $params[] = $provider;
            $types .= 's';
        }
        
        if ($status) {
            $where_conditions[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT * FROM cis_ai_events 
                {$where_clause}
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $mysqli->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getJobsWithPagination(int $page, int $limit): array {
        global $mysqli;
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM cis_ai_orchestration_jobs 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
