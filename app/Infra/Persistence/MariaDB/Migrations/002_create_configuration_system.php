<?php
declare(strict_types=1);

use App\Infra\Persistence\MariaDB\Migration;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Create Configuration and System Tables
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        // Create configuration table
        $db->execute("
            CREATE TABLE configuration (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) NOT NULL UNIQUE,
                config_value TEXT,
                type ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
                is_sensitive TINYINT(1) DEFAULT 0,
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT UNSIGNED,
                INDEX idx_key (config_key),
                INDEX idx_sensitive (is_sensitive),
                FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create system_settings table
        $db->execute("
            CREATE TABLE system_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create audit_log table
        $db->execute("
            CREATE TABLE audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                actor_type ENUM('user', 'system', 'api') NOT NULL,
                actor_id INT UNSIGNED NULL,
                action VARCHAR(100) NOT NULL,
                target_type VARCHAR(50) NOT NULL,
                target_id VARCHAR(50) NULL,
                summary TEXT NOT NULL,
                details_json JSON NULL,
                ip VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_actor (actor_type, actor_id),
                INDEX idx_action (action),
                INDEX idx_target (target_type, target_id),
                INDEX idx_created (created_at),
                FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create system_events table
        $db->execute("
            CREATE TABLE system_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level ENUM('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug') NOT NULL,
                component VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                context_json JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_level (level),
                INDEX idx_component (component),
                INDEX idx_created (created_at),
                INDEX idx_level_created (level, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS system_events');
        $db->execute('DROP TABLE IF EXISTS audit_log');
        $db->execute('DROP TABLE IF EXISTS system_settings');
        $db->execute('DROP TABLE IF EXISTS configuration');
    }
};
