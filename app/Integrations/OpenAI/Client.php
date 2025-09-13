<?php
/**
 * OpenAI API Integration Client
 * File: app/Integrations/OpenAI/Client.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: OpenAI API integration with key rotation, failover, and full feature support
 */

namespace App\Integrations\OpenAI;

use App\Shared\Sec\Secrets;
use App\Shared\AI\Events;
use Exception;

class Client {
    private array $api_keys = [];
    private string $base_url;
    private int $timeout;
    private array $config;
    private ?string $current_key = null;
    private int $current_key_id = 0;
    
    public function __construct() {
        $this->config = include __DIR__ . '/../../../config/ai.php';
        $this->base_url = $this->config['providers']['openai']['base_url'];
        $this->timeout = $this->config['providers']['openai']['timeout_seconds'];
        $this->loadApiKeys();
    }
    
    /**
     * Health check for OpenAI API
     */
    public function health(): array {
        $start_time = microtime(true);
        
        try {
            if (empty($this->api_keys)) {
                return [
                    'ok' => false,
                    'service' => 'openai',
                    'error' => 'No API keys configured',
                    'response_time_ms' => 0
                ];
            }
            
            // Test with models endpoint (lightweight)
            $response = $this->makeRequest('GET', '/models');
            $response_time = round((microtime(true) - $start_time) * 1000);
            
            $this->logApiCall('GET', '/models', $response['status'], $response_time, $response['error'] ?? null);
            
            if ($response['status'] === 200) {
                $models = $response['data']['data'] ?? [];
                return [
                    'ok' => true,
                    'service' => 'openai',
                    'response_time_ms' => $response_time,
                    'active_key' => $this->getCurrentKeyName(),
                    'models_available' => count($models),
                    'gpt4_available' => $this->hasModel($models, 'gpt-4')
                ];
            } else {
                // Try failover
                if ($this->failoverToNextKey()) {
                    return $this->health(); // Retry with next key
                }
                
                return [
                    'ok' => false,
                    'service' => 'openai',
                    'error' => $response['error'] ?? 'API request failed',
                    'status_code' => $response['status'],
                    'response_time_ms' => $response_time
                ];
            }
            
        } catch (Exception $e) {
            $response_time = round((microtime(true) - $start_time) * 1000);
            $this->logApiCall('GET', '/models', 0, $response_time, $e->getMessage());
            
            return [
                'ok' => false,
                'service' => 'openai',
                'error' => $e->getMessage(),
                'response_time_ms' => $response_time
            ];
        }
    }
    
    /**
     * Chat completion with GPT models
     */
    public function chat(array $messages, array $options = []): array {
        try {
            $model = $options['model'] ?? $this->config['providers']['openai']['default_model'];
            $max_tokens = $options['max_tokens'] ?? 1000;
            $temperature = $options['temperature'] ?? 0.7;
            $stream = $options['stream'] ?? false;
            
            $request_data = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
                'stream' => $stream
            ];
            
            // Add function calling if provided
            if (isset($options['functions'])) {
                $request_data['functions'] = $options['functions'];
            }
            
            if (isset($options['function_call'])) {
                $request_data['function_call'] = $options['function_call'];
            }
            
            // Add tools if provided (new format)
            if (isset($options['tools'])) {
                $request_data['tools'] = $options['tools'];
            }
            
            $response = $this->makeRequest('POST', '/chat/completions', $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'data' => $response['data'],
                    'usage' => $response['data']['usage'] ?? [],
                    'model_used' => $model
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Chat completion failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create embeddings for text
     */
    public function embedding(string $text, ?string $model = null): array {
        try {
            $model = $model ?? 'text-embedding-3-large';
            
            $request_data = [
                'model' => $model,
                'input' => $text,
                'encoding_format' => 'float'
            ];
            
            $response = $this->makeRequest('POST', '/embeddings', $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'embedding' => $response['data']['data'][0]['embedding'] ?? [],
                    'usage' => $response['data']['usage'] ?? [],
                    'model_used' => $model
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Embedding generation failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * File upload for assistants
     */
    public function uploadFile(string $file_path, string $purpose = 'assistants'): array {
        try {
            if (!file_exists($file_path)) {
                throw new Exception("File not found: {$file_path}");
            }
            
            $curl_file = new \CURLFile($file_path);
            
            $request_data = [
                'file' => $curl_file,
                'purpose' => $purpose
            ];
            
            $response = $this->makeRequest('POST', '/files', $request_data, true); // multipart
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'file' => $response['data'],
                    'file_id' => $response['data']['id'] ?? null
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'File upload failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create assistant (Assistants API v2)
     */
    public function createAssistant(array $config): array {
        try {
            $request_data = [
                'model' => $config['model'] ?? $this->config['providers']['openai']['default_model'],
                'name' => $config['name'] ?? 'CIS Assistant',
                'description' => $config['description'] ?? '',
                'instructions' => $config['instructions'] ?? '',
                'tools' => $config['tools'] ?? []
            ];
            
            if (isset($config['file_ids'])) {
                $request_data['file_ids'] = $config['file_ids'];
            }
            
            $response = $this->makeRequest('POST', '/assistants', $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'assistant' => $response['data'],
                    'assistant_id' => $response['data']['id'] ?? null
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Assistant creation failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create thread for assistant conversation
     */
    public function createThread(array $messages = []): array {
        try {
            $request_data = [];
            
            if (!empty($messages)) {
                $request_data['messages'] = $messages;
            }
            
            $response = $this->makeRequest('POST', '/threads', $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'thread' => $response['data'],
                    'thread_id' => $response['data']['id'] ?? null
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Thread creation failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Run assistant on thread
     */
    public function runAssistant(string $thread_id, string $assistant_id, array $options = []): array {
        try {
            $request_data = [
                'assistant_id' => $assistant_id
            ];
            
            if (isset($options['instructions'])) {
                $request_data['instructions'] = $options['instructions'];
            }
            
            $response = $this->makeRequest('POST', "/threads/{$thread_id}/runs", $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'run' => $response['data'],
                    'run_id' => $response['data']['id'] ?? null,
                    'status' => $response['data']['status'] ?? 'unknown'
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Assistant run failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate images with DALL-E
     */
    public function generateImage(string $prompt, array $options = []): array {
        try {
            $model = $options['model'] ?? 'dall-e-3';
            $size = $options['size'] ?? '1024x1024';
            $quality = $options['quality'] ?? 'standard';
            $n = $options['n'] ?? 1;
            
            $request_data = [
                'model' => $model,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'n' => $n
            ];
            
            $response = $this->makeRequest('POST', '/images/generations', $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'images' => $response['data']['data'] ?? [],
                    'model_used' => $model
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Image generation failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Load API keys from database
     */
    private function loadApiKeys(): void {
        global $mysqli;
        
        try {
            $sql = "SELECT id, key_name, encrypted_key, encryption_nonce, priority, status 
                    FROM cis_ai_keys 
                    WHERE provider = 'openai' AND status = 'active' 
                    ORDER BY priority ASC, last_used_at ASC";
            
            $result = $mysqli->query($sql);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    try {
                        $decrypted_key = Secrets::decrypt($row['encrypted_key'], $row['encryption_nonce']);
                        $this->api_keys[] = [
                            'id' => $row['id'],
                            'name' => $row['key_name'],
                            'key' => $decrypted_key,
                            'priority' => $row['priority']
                        ];
                    } catch (Exception $e) {
                        error_log("Failed to decrypt OpenAI key {$row['id']}: " . $e->getMessage());
                    }
                }
            }
            
            // Set current key to highest priority (lowest number)
            if (!empty($this->api_keys)) {
                $this->current_key = $this->api_keys[0]['key'];
                $this->current_key_id = $this->api_keys[0]['id'];
            }
            
        } catch (Exception $e) {
            error_log("Failed to load OpenAI API keys: " . $e->getMessage());
        }
    }
    
    /**
     * Failover to next available key
     */
    private function failoverToNextKey(): bool {
        $current_index = 0;
        
        // Find current key index
        foreach ($this->api_keys as $index => $key_info) {
            if ($key_info['id'] === $this->current_key_id) {
                $current_index = $index;
                break;
            }
        }
        
        // Try next key
        $next_index = ($current_index + 1) % count($this->api_keys);
        if ($next_index !== $current_index && isset($this->api_keys[$next_index])) {
            $this->current_key = $this->api_keys[$next_index]['key'];
            $this->current_key_id = $this->api_keys[$next_index]['id'];
            
            error_log("OpenAI failover: switched to key {$this->current_key_id}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Get current key name for reporting
     */
    private function getCurrentKeyName(): string {
        foreach ($this->api_keys as $key_info) {
            if ($key_info['id'] === $this->current_key_id) {
                return $key_info['name'];
            }
        }
        return 'unknown';
    }
    
    /**
     * Check if specific model is available
     */
    private function hasModel(array $models, string $model_prefix): bool {
        foreach ($models as $model) {
            if (isset($model['id']) && strpos($model['id'], $model_prefix) === 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Make HTTP request to OpenAI API
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null, bool $multipart = false): array {
        $url = $this->base_url . $endpoint;
        $request_id = uniqid('openai_');
        
        $headers = [
            'Authorization: Bearer ' . $this->current_key,
            'User-Agent: CIS-OpenAI-Integration/1.0',
            'X-Request-ID: ' . $request_id
        ];
        
        if (!$multipart) {
            $headers[] = 'Content-Type: application/json';
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'CIS-OpenAI-Integration/1.0'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                if ($multipart) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }
        }
        
        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        $decoded = json_decode($response_body, true);
        
        return [
            'status' => $http_code,
            'data' => $decoded,
            'error' => $http_code >= 400 ? ($decoded['error']['message'] ?? "HTTP {$http_code}") : null,
            'request_id' => $request_id
        ];
    }
    
    /**
     * Log API call for audit and monitoring
     */
    private function logApiCall(string $method, string $endpoint, int $status, int $response_time, ?string $error = null): void {
        global $mysqli;
        
        try {
            // Update key usage
            $update_sql = "UPDATE cis_ai_keys SET last_used_at = NOW() WHERE id = ?";
            $stmt = $mysqli->prepare($update_sql);
            $stmt->bind_param('i', $this->current_key_id);
            $stmt->execute();
            
            // Log to integration logs
            $log_sql = "INSERT INTO cis_integration_logs 
                        (service_name, endpoint, request_method, response_status, response_time_ms, error_message) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $mysqli->prepare($log_sql);
            $stmt->bind_param('sssiss',
                $service_name = 'openai',
                $endpoint,
                $method,
                $status,
                $response_time,
                $error
            );
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Failed to log OpenAI API call: " . $e->getMessage());
        }
    }
}
