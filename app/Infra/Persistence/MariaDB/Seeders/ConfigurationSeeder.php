<?php
declare(strict_types=1);

use App\Infra\Persistence\MariaDB\Seeder;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Configuration Seeder
 */
return new class extends Seeder
{
    public function run(Database $db): void
    {
        $configurations = [
            // Application settings
            ['key' => 'app.name', 'value' => 'CIS - Central Information System', 'type' => 'string', 'sensitive' => false, 'description' => 'Application name'],
            ['key' => 'app.version', 'value' => '1.0.0', 'type' => 'string', 'sensitive' => false, 'description' => 'Application version'],
            ['key' => 'app.maintenance_mode', 'value' => 'false', 'type' => 'bool', 'sensitive' => false, 'description' => 'Enable maintenance mode'],
            
            // Security settings
            ['key' => 'security.session_timeout', 'value' => '3600', 'type' => 'int', 'sensitive' => false, 'description' => 'Session timeout in seconds'],
            ['key' => 'security.login_attempts_limit', 'value' => '5', 'type' => 'int', 'sensitive' => false, 'description' => 'Maximum login attempts before lockout'],
            ['key' => 'security.password_min_length', 'value' => '8', 'type' => 'int', 'sensitive' => false, 'description' => 'Minimum password length'],
            
            // Session Replay settings
            ['key' => 'session_replay.enabled', 'value' => 'true', 'type' => 'bool', 'sensitive' => false, 'description' => 'Enable session replay feature'],
            ['key' => 'session_replay.consent_required', 'value' => 'true', 'type' => 'bool', 'sensitive' => false, 'description' => 'Require explicit consent for session replay'],
            ['key' => 'session_replay.retention_days', 'value' => '30', 'type' => 'int', 'sensitive' => false, 'description' => 'Session replay data retention period'],
            
            // Profiler settings
            ['key' => 'profiler.enabled', 'value' => 'true', 'type' => 'bool', 'sensitive' => false, 'description' => 'Enable request profiling'],
            ['key' => 'profiler.slow_query_threshold', 'value' => '500', 'type' => 'int', 'sensitive' => false, 'description' => 'Slow query threshold in milliseconds'],
            ['key' => 'profiler.retention_days', 'value' => '7', 'type' => 'int', 'sensitive' => false, 'description' => 'Profiler data retention period'],
            
            // Feed settings
            ['key' => 'feed.ai_clustering_enabled', 'value' => 'true', 'type' => 'bool', 'sensitive' => false, 'description' => 'Enable AI-powered event clustering'],
            ['key' => 'feed.max_events_per_page', 'value' => '50', 'type' => 'int', 'sensitive' => false, 'description' => 'Maximum events per page in feed'],
            ['key' => 'feed.retention_days', 'value' => '90', 'type' => 'int', 'sensitive' => false, 'description' => 'Feed event retention period'],
            
            // Analytics settings
            ['key' => 'analytics.dashboard_refresh_seconds', 'value' => '300', 'type' => 'int', 'sensitive' => false, 'description' => 'Dashboard auto-refresh interval'],
            ['key' => 'analytics.cache_ttl_minutes', 'value' => '15', 'type' => 'int', 'sensitive' => false, 'description' => 'Analytics cache TTL'],
            
            // Demo mode settings
            ['key' => 'demo.enabled', 'value' => 'false', 'type' => 'bool', 'sensitive' => false, 'description' => 'Enable demo mode with sample data'],
            ['key' => 'demo.reset_interval_hours', 'value' => '24', 'type' => 'int', 'sensitive' => false, 'description' => 'Demo data reset interval'],
            
            // Integration settings (placeholder - actual values should be set manually)
            ['key' => 'integrations.openai_api_key', 'value' => '', 'type' => 'string', 'sensitive' => true, 'description' => 'OpenAI API key for AI features'],
            ['key' => 'integrations.vend_token', 'value' => '', 'type' => 'string', 'sensitive' => true, 'description' => 'Vend API token'],
            ['key' => 'integrations.xero_client_id', 'value' => '', 'type' => 'string', 'sensitive' => true, 'description' => 'Xero API client ID'],
            ['key' => 'integrations.deputy_token', 'value' => '', 'type' => 'string', 'sensitive' => true, 'description' => 'Deputy API token'],
            
            // Notification settings
            ['key' => 'notifications.email_enabled', 'value' => 'true', 'type' => 'bool', 'sensitive' => false, 'description' => 'Enable email notifications'],
            ['key' => 'notifications.slack_enabled', 'value' => 'false', 'type' => 'bool', 'sensitive' => false, 'description' => 'Enable Slack notifications'],
            ['key' => 'notifications.retention_days', 'value' => '30', 'type' => 'int', 'sensitive' => false, 'description' => 'Notification retention period'],
        ];
        
        foreach ($configurations as $config) {
            $db->execute("
                INSERT IGNORE INTO configuration (
                    config_key, config_value, type, is_sensitive, description
                ) VALUES (?, ?, ?, ?, ?)
            ", [
                $config['key'],
                $config['value'],
                $config['type'],
                $config['sensitive'] ? 1 : 0,
                $config['description']
            ]);
        }
        
        // Insert system settings
        $systemSettings = [
            ['key' => 'last_maintenance_check', 'value' => date('Y-m-d H:i:s')],
            ['key' => 'database_version', 'value' => '1.0.0'],
            ['key' => 'installation_date', 'value' => date('Y-m-d H:i:s')],
        ];
        
        foreach ($systemSettings as $setting) {
            $db->execute("
                INSERT IGNORE INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?)
            ", [$setting['key'], $setting['value']]);
        }
    }
};
