<?php
/**
 * AI Configuration Settings
 * File: config/ai.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: AI integration feature flags and configuration
 */

return [
    // Global AI feature toggle
    'enabled' => $_ENV['AI_ENABLED'] ?? true,
    
    // Orchestration mode
    'orchestration' => [
        'mode' => $_ENV['AI_ORCHESTRATION_MODE'] ?? 'bus', // 'bus' or 'direct'
        'max_concurrent_jobs' => (int)($_ENV['AI_MAX_CONCURRENT_JOBS'] ?? 10),
        'job_timeout_seconds' => (int)($_ENV['AI_JOB_TIMEOUT'] ?? 300),
        'retry_attempts' => (int)($_ENV['AI_RETRY_ATTEMPTS'] ?? 3),
        'retry_delay_seconds' => (int)($_ENV['AI_RETRY_DELAY'] ?? 5)
    ],
    
    // Key rotation settings
    'key_rotation' => [
        'enabled' => $_ENV['AI_KEY_ROTATION'] ?? true,
        'rotation_interval_hours' => (int)($_ENV['AI_ROTATION_INTERVAL'] ?? 24),
        'failover_threshold_errors' => (int)($_ENV['AI_FAILOVER_THRESHOLD'] ?? 5),
        'health_check_interval_minutes' => (int)($_ENV['AI_HEALTH_CHECK_INTERVAL'] ?? 15)
    ],
    
    // Provider-specific settings
    'providers' => [
        'openai' => [
            'enabled' => $_ENV['OPENAI_ENABLED'] ?? true,
            'base_url' => $_ENV['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1',
            'timeout_seconds' => (int)($_ENV['OPENAI_TIMEOUT'] ?? 30),
            'default_model' => $_ENV['OPENAI_DEFAULT_MODEL'] ?? 'gpt-4-turbo-preview',
            'models' => [
                'chat' => ['gpt-4-turbo-preview', 'gpt-4', 'gpt-3.5-turbo'],
                'embedding' => ['text-embedding-3-large', 'text-embedding-ada-002'],
                'image' => ['dall-e-3', 'dall-e-2']
            ],
            'rate_limits' => [
                'requests_per_minute' => (int)($_ENV['OPENAI_RPM'] ?? 500),
                'tokens_per_minute' => (int)($_ENV['OPENAI_TPM'] ?? 200000)
            ],
            'features' => [
                'assistants_v2' => $_ENV['OPENAI_ASSISTANTS_V2'] ?? true,
                'function_calling' => $_ENV['OPENAI_FUNCTION_CALLING'] ?? true,
                'file_upload' => $_ENV['OPENAI_FILE_UPLOAD'] ?? true,
                'streaming' => $_ENV['OPENAI_STREAMING'] ?? true
            ]
        ],
        
        'claude' => [
            'enabled' => $_ENV['CLAUDE_ENABLED'] ?? true,
            'base_url' => $_ENV['CLAUDE_BASE_URL'] ?? 'https://api.anthropic.com/v1',
            'timeout_seconds' => (int)($_ENV['CLAUDE_TIMEOUT'] ?? 30),
            'default_model' => $_ENV['CLAUDE_DEFAULT_MODEL'] ?? 'claude-3-opus-20240229',
            'models' => [
                'chat' => ['claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307'],
                'legacy' => ['claude-2.1', 'claude-instant-1.2']
            ],
            'max_tokens' => (int)($_ENV['CLAUDE_MAX_TOKENS'] ?? 200000),
            'rate_limits' => [
                'requests_per_minute' => (int)($_ENV['CLAUDE_RPM'] ?? 60),
                'tokens_per_minute' => (int)($_ENV['CLAUDE_TPM'] ?? 40000)
            ],
            'features' => [
                'tool_use' => $_ENV['CLAUDE_TOOL_USE'] ?? true,
                'streaming' => $_ENV['CLAUDE_STREAMING'] ?? true,
                'vision' => $_ENV['CLAUDE_VISION'] ?? true,
                'max_context' => $_ENV['CLAUDE_MAX_CONTEXT'] ?? true
            ]
        ]
    ],
    
    // Event tracking and logging
    'logging' => [
        'enabled' => $_ENV['AI_LOGGING_ENABLED'] ?? true,
        'log_requests' => $_ENV['AI_LOG_REQUESTS'] ?? true,
        'log_responses' => $_ENV['AI_LOG_RESPONSES'] ?? true,
        'sanitize_sensitive' => $_ENV['AI_SANITIZE_SENSITIVE'] ?? true,
        'retention_days' => (int)($_ENV['AI_LOG_RETENTION_DAYS'] ?? 30)
    ],
    
    // Performance monitoring
    'monitoring' => [
        'enabled' => $_ENV['AI_MONITORING_ENABLED'] ?? true,
        'track_tokens' => $_ENV['AI_TRACK_TOKENS'] ?? true,
        'track_latency' => $_ENV['AI_TRACK_LATENCY'] ?? true,
        'alert_slow_requests_ms' => (int)($_ENV['AI_ALERT_SLOW_MS'] ?? 10000),
        'alert_error_rate_threshold' => (float)($_ENV['AI_ALERT_ERROR_RATE'] ?? 0.1)
    ],
    
    // Security settings
    'security' => [
        'encrypt_keys' => $_ENV['AI_ENCRYPT_KEYS'] ?? true,
        'key_rotation_webhook' => $_ENV['AI_KEY_ROTATION_WEBHOOK'] ?? null,
        'allowed_ip_ranges' => array_filter(explode(',', $_ENV['AI_ALLOWED_IPS'] ?? '')),
        'require_auth' => $_ENV['AI_REQUIRE_AUTH'] ?? true
    ]
];
