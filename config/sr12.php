<?php
/**
 * SR-12 Reliability Configuration
 * File: config/sr12.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: SR-12 reliability thresholds and configuration parameters
 */

return [
    // Performance Thresholds (milliseconds)
    'performance' => [
        'health_p95' => 800,      // /_selftest p95 < 800ms
        'login_p95' => 2000,      // /login p95 < 2000ms
        'dashboard_p95' => 2500,  // /dashboard p95 < 2500ms
        'api_p95' => 1000,        // API endpoints p95 < 1000ms
        'admin_p95' => 3000,      // Admin pages p95 < 3000ms
    ],
    
    // Error Rate Thresholds
    'errors' => [
        'max_5xx_rate' => 0.5,    // 5xx rate < 0.5% in load tests
        'max_timeout_rate' => 1.0, // Timeout rate < 1.0%
        'max_connection_failures' => 2.0, // Connection failures < 2.0%
    ],
    
    // Logging & Telemetry
    'logs' => [
        'max_drop_rate' => 1.0,   // Log drop rate < 1%
        'min_audit_completeness' => 99.0, // Audit completeness ≥ 99%
        'required_fields' => ['timestamp', 'level', 'message', 'context'],
    ],
    
    // Telemetry Requirements
    'telemetry' => [
        'consent_required' => true,
        'masking_required' => true,
        'opt_out_honored' => true,
        'retention_days' => 90,
    ],
    
    // Backup Thresholds
    'backups' => [
        'max_duration_minutes' => 15, // Backup complete < 15 min (dev)
        'max_restore_test_age_days' => 7, // Last restore test < 7 days
        'min_retention_days' => 7,
        'max_retention_days' => 30,
    ],
    
    // Migration Requirements
    'migrations' => [
        'require_idempotent' => true,
        'max_drift_tolerance' => 0, // Zero schema drift allowed
        'rollback_required' => true,
    ],
    
    // Queue Reliability
    'queue' => [
        'min_success_rate' => 99.0, // Success ≥ 99% for 100 jobs
        'max_dlq_rate' => 1.0,      // DLQ rate < 1%
        'max_avg_latency_ms' => 500, // Average job latency < 500ms
        'test_job_count' => 100,
    ],
    
    // Load Test Configuration
    'load_test' => [
        'rps' => 2,              // 2 RPS (dev-safe)
        'duration_minutes' => 3,  // 3 minutes
        'endpoints' => [
            '/',
            '/login',
            '/dashboard',
            '/_selftest',
            '/admin/monitor',
        ],
    ],
    
    // Soak Test Configuration  
    'soak_test' => [
        'short_duration_minutes' => 10, // 10 minutes short mode
        'full_duration_minutes' => 60,  // 60 minutes full mode
        'memory_drift_threshold_mb' => 50, // Memory drift < 50MB
        'latency_variance_threshold' => 20, // Latency variance < 20%
    ],
    
    // Chaos Engineering
    'chaos' => [
        'enabled' => true,
        'feature_flags' => [
            'chaos.db_down' => false,
            'chaos.redis_down' => false,
            'chaos.ai_timeout' => false,
            'chaos.disk_full' => false,
            'chaos.http_error_injection' => false,
        ],
        'error_injection_rate' => 10, // 10% of requests during chaos
    ],
    
    // Security Requirements
    'security' => [
        'required_headers' => [
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'X-Frame-Options',
            'X-Content-Type-Options',
        ],
        'csrf_protection' => true,
        'rate_limiting' => true,
    ],
    
    // Integration Health
    'integrations' => [
        'ai_providers' => ['openai', 'claude'],
        'business_providers' => ['vend', 'deputy', 'xero'],
        'timeout_seconds' => 10,
        'retry_attempts' => 3,
        'circuit_breaker_threshold' => 5, // Failures before circuit opens
    ],
    
    // Reporting
    'reporting' => [
        'output_dir' => '/var/reports/sr12',
        'retention_days' => 30,
        'formats' => ['json', 'html', 'csv'],
    ],
];
