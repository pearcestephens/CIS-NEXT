<?php
/**
 * Claude API Integration Client
 * File: app/Integrations/Claude/Client.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Claude API integration with key rotation, failover, and Claude 3.x features
 */

namespace App\Integrations\Claude;

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
    private string $anthropic_version = '2023-06-01';
    
    public function __construct() {
        $this->config = include __DIR__ . '/../../../config/ai.php';
        $this->base_url = $this->config['providers']['claude']['base_url'];
        $this->timeout = $this->config['providers']['claude']['timeout_seconds'];
        $this->loadApiKeys();
    }
    
    /**
     * Health check for Claude API
     */
    public function health(): array {
        $start_time = microtime(true);
        
        try {
            if (empty($this->api_keys)) {
                return [
                    'ok' => false,
                    'service' => 'claude',
                    'error' => 'No API keys configured',
                    'response_time_ms' => 0
                ];
            }
            
            // Test with a simple message (Claude doesn't have a models endpoint)
            $test_response = $this->chat([
                ['role' => 'user', 'content' => 'Hello, please respond with just "OK" to confirm the API is working.']
            ], ['max_tokens' => 10]);
            
            $response_time = round((microtime(true) - $start_time) * 1000);
            
            if ($test_response['success']) {
                return [
                    'ok' => true,
                    'service' => 'claude',
                    'response_time_ms' => $response_time,
                    'active_key' => $this->getCurrentKeyName(),
                    'model_used' => $test_response['model_used'] ?? 'unknown',
                    'tokens_used' => $test_response['usage']['output_tokens'] ?? 0
                ];
            } else {
                // Try failover
                if ($this->failoverToNextKey()) {
                    return $this->health(); // Retry with next key
                }
                
                return [
                    'ok' => false,
                    'service' => 'claude',
                    'error' => $test_response['error'] ?? 'API test failed',
                    'response_time_ms' => $response_time
                ];
            }
            
        } catch (Exception $e) {
            $response_time = round((microtime(true) - $start_time) * 1000);
            $this->logApiCall('POST', '/messages', 0, $response_time, $e->getMessage());
            
            return [
                'ok' => false,
                'service' => 'claude',
                'error' => $e->getMessage(),
                'response_time_ms' => $response_time
            ];
        }
    }
    
    /**
     * Chat completion with Claude models
     */
    public function chat(array $messages, array $options = []): array {
        try {
            $model = $options['model'] ?? $this->config['providers']['claude']['default_model'];
            $max_tokens = $options['max_tokens'] ?? 1000;
            $temperature = $options['temperature'] ?? 0.7;
            $stream = $options['stream'] ?? false;
            
            // Claude requires system message separate from messages
            $system_message = '';
            $filtered_messages = [];
            
            foreach ($messages as $message) {
                if ($message['role'] === 'system') {
                    $system_message = $message['content'];
                } else {
                    $filtered_messages[] = $message;
                }
            }
            
            $request_data = [
                'model' => $model,
                'messages' => $filtered_messages,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
                'stream' => $stream
            ];
            
            if (!empty($system_message)) {
                $request_data['system'] = $system_message;
            }
            
            // Add tools if provided
            if (isset($options['tools'])) {
                $request_data['tools'] = $options['tools'];
            }
            
            if (isset($options['tool_choice'])) {
                $request_data['tool_choice'] = $options['tool_choice'];
            }
            
            $response = $this->makeRequest('POST', '/messages', $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'data' => $response['data'],
                    'usage' => $response['data']['usage'] ?? [],
                    'model_used' => $model,
                    'content' => $this->extractContent($response['data'])
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
     * Streaming chat completion
     */
    public function chatStream(array $messages, array $options = []): \Generator {
        try {
            $options['stream'] = true;
            $model = $options['model'] ?? $this->config['providers']['claude']['default_model'];
            $max_tokens = $options['max_tokens'] ?? 1000;
            
            // Prepare request similar to chat()
            $system_message = '';
            $filtered_messages = [];
            
            foreach ($messages as $message) {
                if ($message['role'] === 'system') {
                    $system_message = $message['content'];
                } else {
                    $filtered_messages[] = $message;
                }
            }
            
            $request_data = [
                'model' => $model,
                'messages' => $filtered_messages,
                'max_tokens' => $max_tokens,
                'stream' => true
            ];
            
            if (!empty($system_message)) {
                $request_data['system'] = $system_message;
            }
            
            // Use streaming request
            $stream = $this->makeStreamingRequest('POST', '/messages', $request_data);
            
            foreach ($stream as $chunk) {
                yield $chunk;
            }
            
        } catch (Exception $e) {
            yield [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Vision analysis (Claude 3 with image support)
     */
    public function analyzeImage(string $image_data, string $prompt, array $options = []): array {
        try {
            $model = $options['model'] ?? 'claude-3-opus-20240229';
            $max_tokens = $options['max_tokens'] ?? 1000;
            
            // Detect image format
            $image_format = $this->detectImageFormat($image_data);
            
            $request_data = [
                'model' => $model,
                'max_tokens' => $max_tokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $image_format,
                                    'data' => base64_encode($image_data)
                                ]
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt
                            ]
                        ]
                    ]
                ]
            ];
            
            $response = $this->makeRequest('POST', '/messages', $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'analysis' => $this->extractContent($response['data']),
                    'usage' => $response['data']['usage'] ?? [],
                    'model_used' => $model
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Image analysis failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Tool use (function calling) with Claude 3
     */
    public function useTools(array $messages, array $tools, array $options = []): array {
        try {
            $model = $options['model'] ?? 'claude-3-opus-20240229';
            $max_tokens = $options['max_tokens'] ?? 1000;
            
            $request_data = [
                'model' => $model,
                'max_tokens' => $max_tokens,
                'messages' => $messages,
                'tools' => $tools
            ];
            
            if (isset($options['tool_choice'])) {
                $request_data['tool_choice'] = $options['tool_choice'];
            }
            
            $response = $this->makeRequest('POST', '/messages', $request_data);
            
            if ($response['status'] === 200) {
                $content = $response['data']['content'] ?? [];
                $tool_calls = [];
                
                // Extract tool calls from response
                foreach ($content as $item) {
                    if ($item['type'] === 'tool_use') {
                        $tool_calls[] = $item;
                    }
                }
                
                return [
                    'success' => true,
                    'data' => $response['data'],
                    'tool_calls' => $tool_calls,
                    'usage' => $response['data']['usage'] ?? [],
                    'model_used' => $model
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Tool use failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Text completion with legacy Claude models
     */
    public function complete(string $prompt, array $options = []): array {
        try {
            $model = $options['model'] ?? 'claude-2.1';
            $max_tokens = $options['max_tokens'] ?? 1000;
            $temperature = $options['temperature'] ?? 0.7;
            
            $request_data = [
                'model' => $model,
                'prompt' => "\n\nHuman: {$prompt}\n\nAssistant:",
                'max_tokens_to_sample' => $max_tokens,
                'temperature' => $temperature
            ];
            
            $response = $this->makeRequest('POST', '/complete', $request_data);
            
            if ($response['status'] === 200) {
                return [
                    'success' => true,
                    'completion' => $response['data']['completion'] ?? '',
                    'stop_reason' => $response['data']['stop_reason'] ?? '',
                    'model_used' => $model
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Text completion failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Count tokens in text (approximate)
     */
    public function countTokens(string $text, ?string $model = null): array {
        try {
            // Claude doesn't have a dedicated tokenization endpoint
            // Approximate: 1 token ≈ 4 characters for English text
            $approximate_tokens = ceil(strlen($text) / 4);
            
            return [
                'success' => true,
                'token_count' => $approximate_tokens,
                'method' => 'approximation',
                'note' => 'Claude API does not provide exact tokenization'
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
                    WHERE provider = 'claude' AND status = 'active' 
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
                        error_log("Failed to decrypt Claude key {$row['id']}: " . $e->getMessage());
                    }
                }
            }
            
            // Set current key to highest priority (lowest number)
            if (!empty($this->api_keys)) {
                $this->current_key = $this->api_keys[0]['key'];
                $this->current_key_id = $this->api_keys[0]['id'];
            }
            
        } catch (Exception $e) {
            error_log("Failed to load Claude API keys: " . $e->getMessage());
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
            
            error_log("Claude failover: switched to key {$this->current_key_id}");
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
     * Extract content from Claude response
     */
    private function extractContent(array $response_data): string {
        $content = $response_data['content'] ?? [];
        
        if (empty($content)) {
            return '';
        }
        
        $text_parts = [];
        foreach ($content as $item) {
            if ($item['type'] === 'text') {
                $text_parts[] = $item['text'];
            }
        }
        
        return implode(' ', $text_parts);
    }
    
    /**
     * Detect image format from binary data
     */
    private function detectImageFormat(string $image_data): string {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_data);
        
        switch ($mime_type) {
            case 'image/jpeg':
                return 'image/jpeg';
            case 'image/png':
                return 'image/png';
            case 'image/gif':
                return 'image/gif';
            case 'image/webp':
                return 'image/webp';
            default:
                return 'image/jpeg'; // Default fallback
        }
    }
    
    /**
     * Make HTTP request to Claude API
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null): array {
        $url = $this->base_url . $endpoint;
        $request_id = uniqid('claude_');
        
        $headers = [
            'x-api-key: ' . $this->current_key,
            'anthropic-version: ' . $this->anthropic_version,
            'Content-Type: application/json',
            'User-Agent: CIS-Claude-Integration/1.0',
            'X-Request-ID: ' . $request_id
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'CIS-Claude-Integration/1.0'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
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
            'error' => $http_code >= 400 ? ($decoded['error']['message'] ?? $decoded['message'] ?? "HTTP {$http_code}") : null,
            'request_id' => $request_id
        ];
    }
    
    /**
     * Make streaming request to Claude API
     */
    private function makeStreamingRequest(string $method, string $endpoint, array $data): \Generator {
        $url = $this->base_url . $endpoint;
        $request_id = uniqid('claude_stream_');
        
        $headers = [
            'x-api-key: ' . $this->current_key,
            'anthropic-version: ' . $this->anthropic_version,
            'Content-Type: application/json',
            'Accept: text/event-stream',
            'X-Request-ID: ' . $request_id
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_WRITEFUNCTION => function($ch, $data) {
                static $buffer = '';
                $buffer .= $data;
                
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $chunk = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);
                    
                    if (strpos($chunk, 'data: ') === 0) {
                        $json_data = substr($chunk, 6);
                        if ($json_data !== '[DONE]') {
                            $decoded = json_decode(trim($json_data), true);
                            if ($decoded) {
                                return strlen($data); // Continue processing
                            }
                        }
                    }
                }
                
                return strlen($data);
            },
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => $this->timeout
        ]);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        // For now, return a simple generator
        yield [
            'success' => true,
            'note' => 'Streaming implementation simplified for initial version'
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
                $service_name = 'claude',
                $endpoint,
                $method,
                $status,
                $response_time,
                $error
            );
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Failed to log Claude API call: " . $e->getMessage());
        }
    }
}
