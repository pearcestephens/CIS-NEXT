<?php
/**
 * AI Event Schema and Types
 * File: app/Shared/AI/Events.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Define event schema for AI orchestration pipeline
 */

namespace App\Shared\AI;

class Events {
    
    // Event status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_TIMEOUT = 'timeout';
    
    // Job types for orchestration
    const JOB_TYPE_SINGLE = 'single';
    const JOB_TYPE_CHAIN = 'chain';
    const JOB_TYPE_FANOUT = 'fanout';
    const JOB_TYPE_FANIN = 'fanin';
    
    // Provider constants
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_CLAUDE = 'claude';
    
    // Operation types
    const OP_CHAT = 'chat';
    const OP_EMBEDDING = 'embedding';
    const OP_UPLOAD = 'upload';
    const OP_FUNCTION_CALL = 'function_call';
    const OP_ASSISTANT = 'assistant';
    const OP_IMAGE_GENERATION = 'image_generation';
    
    /**
     * Create standardized AI event data structure
     */
    public static function createEvent(
        string $job_id,
        string $provider,
        string $operation,
        array $request_data,
        ?string $model = null,
        ?int $parent_event_id = null
    ): array {
        return [
            'job_id' => $job_id,
            'trace_id' => self::generateTraceId(),
            'parent_event_id' => $parent_event_id,
            'provider' => $provider,
            'model' => $model ?? self::getDefaultModel($provider),
            'operation' => $operation,
            'request_data' => self::sanitizeRequestData($request_data),
            'status' => self::STATUS_PENDING,
            'tokens_used' => 0,
            'response_time_ms' => 0,
            'retry_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Create orchestration job structure
     */
    public static function createOrchestrationJob(
        string $job_type,
        array $pipeline_config,
        array $input_data,
        ?int $created_by = null
    ): array {
        return [
            'job_id' => self::generateJobId(),
            'job_type' => $job_type,
            'pipeline_config' => $pipeline_config,
            'input_data' => $input_data,
            'status' => 'pending',
            'progress_percentage' => 0.00,
            'total_events' => 0,
            'completed_events' => 0,
            'failed_events' => 0,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Create event completion data
     */
    public static function createEventCompletion(
        array $response_data,
        int $tokens_used,
        int $response_time_ms,
        ?string $error = null
    ): array {
        $status = $error ? self::STATUS_FAILED : self::STATUS_COMPLETED;
        
        return [
            'response_data' => $error ? null : self::sanitizeResponseData($response_data),
            'status' => $status,
            'error_message' => $error,
            'tokens_used' => $tokens_used,
            'response_time_ms' => $response_time_ms,
            'completed_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Chain event configuration
     */
    public static function createChainConfig(array $steps): array {
        return [
            'type' => self::JOB_TYPE_CHAIN,
            'steps' => $steps,
            'continue_on_error' => false,
            'timeout_seconds' => 300
        ];
    }
    
    /**
     * Fan-out event configuration
     */
    public static function createFanOutConfig(array $parallel_operations): array {
        return [
            'type' => self::JOB_TYPE_FANOUT,
            'operations' => $parallel_operations,
            'wait_for_all' => true,
            'timeout_seconds' => 300
        ];
    }
    
    /**
     * Fan-in aggregation configuration
     */
    public static function createFanInConfig(string $aggregation_method, array $inputs): array {
        return [
            'type' => self::JOB_TYPE_FANIN,
            'aggregation_method' => $aggregation_method, // 'concat', 'merge', 'vote', 'custom'
            'input_events' => $inputs,
            'timeout_seconds' => 60
        ];
    }
    
    /**
     * Generate unique job ID
     */
    public static function generateJobId(): string {
        return 'job_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }
    
    /**
     * Generate unique trace ID for distributed tracing
     */
    public static function generateTraceId(): string {
        return 'trace_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    /**
     * Get default model for provider
     */
    public static function getDefaultModel(string $provider): string {
        $config = include __DIR__ . '/../../../config/ai.php';
        
        switch ($provider) {
            case self::PROVIDER_OPENAI:
                return $config['providers']['openai']['default_model'] ?? 'gpt-4-turbo-preview';
            case self::PROVIDER_CLAUDE:
                return $config['providers']['claude']['default_model'] ?? 'claude-3-opus-20240229';
            default:
                return 'unknown';
        }
    }
    
    /**
     * Sanitize request data (remove sensitive information)
     */
    public static function sanitizeRequestData(array $data): array {
        $sanitized = $data;
        
        // Remove or mask sensitive keys
        $sensitive_keys = ['api_key', 'authorization', 'token', 'password', 'secret'];
        
        foreach ($sensitive_keys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '[REDACTED]';
            }
        }
        
        // Truncate very long messages for storage efficiency
        if (isset($sanitized['messages']) && is_array($sanitized['messages'])) {
            foreach ($sanitized['messages'] as &$message) {
                if (isset($message['content']) && strlen($message['content']) > 5000) {
                    $message['content'] = substr($message['content'], 0, 5000) . '[TRUNCATED]';
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize response data (remove sensitive information)
     */
    public static function sanitizeResponseData(array $data): array {
        $sanitized = $data;
        
        // Keep important metrics but remove potentially sensitive content
        $keep_keys = ['id', 'object', 'created', 'model', 'usage', 'choices', 'content', 'message'];
        
        // If response is very large, truncate content but keep metadata
        if (isset($sanitized['choices']) && is_array($sanitized['choices'])) {
            foreach ($sanitized['choices'] as &$choice) {
                if (isset($choice['message']['content']) && strlen($choice['message']['content']) > 10000) {
                    $choice['message']['content'] = substr($choice['message']['content'], 0, 10000) . '[TRUNCATED]';
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate event data structure
     */
    public static function validateEvent(array $event): array {
        $errors = [];
        
        $required_fields = ['job_id', 'provider', 'operation', 'request_data'];
        
        foreach ($required_fields as $field) {
            if (!isset($event[$field]) || empty($event[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        // Validate provider
        if (isset($event['provider']) && !in_array($event['provider'], [self::PROVIDER_OPENAI, self::PROVIDER_CLAUDE])) {
            $errors[] = "Invalid provider: {$event['provider']}";
        }
        
        // Validate operation
        $valid_operations = [self::OP_CHAT, self::OP_EMBEDDING, self::OP_UPLOAD, self::OP_FUNCTION_CALL, self::OP_ASSISTANT, self::OP_IMAGE_GENERATION];
        if (isset($event['operation']) && !in_array($event['operation'], $valid_operations)) {
            $errors[] = "Invalid operation: {$event['operation']}";
        }
        
        return $errors;
    }
    
    /**
     * Create sample event for testing
     */
    public static function createSampleEvent(string $provider): array {
        $job_id = self::generateJobId();
        
        switch ($provider) {
            case self::PROVIDER_OPENAI:
                return self::createEvent(
                    $job_id,
                    self::PROVIDER_OPENAI,
                    self::OP_CHAT,
                    [
                        'model' => 'gpt-4-turbo-preview',
                        'messages' => [
                            ['role' => 'user', 'content' => 'Hello, this is a test message for the AI system.']
                        ],
                        'max_tokens' => 100
                    ]
                );
                
            case self::PROVIDER_CLAUDE:
                return self::createEvent(
                    $job_id,
                    self::PROVIDER_CLAUDE,
                    self::OP_CHAT,
                    [
                        'model' => 'claude-3-opus-20240229',
                        'messages' => [
                            ['role' => 'user', 'content' => 'Hello, this is a test message for the Claude AI system.']
                        ],
                        'max_tokens' => 100
                    ]
                );
                
            default:
                throw new \InvalidArgumentException("Unknown provider: {$provider}");
        }
    }
}
