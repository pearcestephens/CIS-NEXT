<?php
declare(strict_types=1);

use App\Infra\Persistence\MariaDB\Migration;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Create Notifications and Session Replay Tables
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        // Create notifications table
        $db->execute("
            CREATE TABLE notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kind VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                severity ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
                status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                user_id INT UNSIGNED NULL,
                meta_json JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                read_at TIMESTAMP NULL,
                dismissed_at TIMESTAMP NULL,
                INDEX idx_kind (kind),
                INDEX idx_severity (severity),
                INDEX idx_status (status),
                INDEX idx_user (user_id),
                INDEX idx_created (created_at),
                INDEX idx_unread (user_id, read_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create session replay consent table
        $db->execute("
            CREATE TABLE sr_consent (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                session_id VARCHAR(100) NOT NULL,
                consented TINYINT(1) NOT NULL DEFAULT 0,
                fields_masked_json JSON NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_session (user_id, session_id),
                INDEX idx_session (session_id),
                INDEX idx_consented (consented),
                INDEX idx_created (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create session replay events table
        $db->execute("
            CREATE TABLE sr_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(100) NOT NULL,
                ts_ms BIGINT UNSIGNED NOT NULL,
                type ENUM('click', 'scroll', 'dom', 'route', 'perf', 'error') NOT NULL,
                data_json JSON NOT NULL,
                redaction_applied TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session (session_id),
                INDEX idx_type (type),
                INDEX idx_ts (ts_ms),
                INDEX idx_created (created_at),
                INDEX idx_session_ts (session_id, ts_ms)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS sr_events');
        $db->execute('DROP TABLE IF EXISTS sr_consent');
        $db->execute('DROP TABLE IF EXISTS notifications');
    }
};
