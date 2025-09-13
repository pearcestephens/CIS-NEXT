<?php
declare(strict_types=1);

use App\Infra\Persistence\MariaDB\Migration;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Create Feed Engine Tables (Module 1)
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        // Create feed_events table
        $db->execute("
            CREATE TABLE feed_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ts TIMESTAMP NOT NULL,
                source VARCHAR(50) NOT NULL,
                source_id VARCHAR(100) NOT NULL,
                outlet_id INT UNSIGNED NULL,
                severity ENUM('info', 'warn', 'error', 'critical') DEFAULT 'info',
                title VARCHAR(255) NOT NULL,
                summary TEXT NOT NULL,
                meta_json JSON NULL,
                entity_url VARCHAR(500),
                is_ai_generated TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_source_event (source, source_id),
                INDEX idx_ts (ts),
                INDEX idx_source (source),
                INDEX idx_outlet (outlet_id),
                INDEX idx_severity (severity),
                INDEX idx_created (created_at),
                INDEX idx_outlet_ts (outlet_id, ts),
                INDEX idx_severity_ts (severity, ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create feed_user_prefs table
        $db->execute("
            CREATE TABLE feed_user_prefs (
                user_id INT UNSIGNED PRIMARY KEY,
                preferences_json JSON NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create feed_clusters table for AI clustering
        $db->execute("
            CREATE TABLE feed_clusters (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cluster_hash VARCHAR(64) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                summary TEXT NOT NULL,
                event_count INT UNSIGNED NOT NULL,
                severity ENUM('info', 'warn', 'error', 'critical') DEFAULT 'info',
                outlet_id INT UNSIGNED NULL,
                first_event_at TIMESTAMP NOT NULL,
                last_event_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_hash (cluster_hash),
                INDEX idx_outlet (outlet_id),
                INDEX idx_severity (severity),
                INDEX idx_last_event (last_event_at),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create feed_cluster_events junction table
        $db->execute("
            CREATE TABLE feed_cluster_events (
                cluster_id INT UNSIGNED NOT NULL,
                event_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (cluster_id, event_id),
                FOREIGN KEY (cluster_id) REFERENCES feed_clusters(id) ON DELETE CASCADE,
                FOREIGN KEY (event_id) REFERENCES feed_events(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS feed_cluster_events');
        $db->execute('DROP TABLE IF EXISTS feed_clusters');
        $db->execute('DROP TABLE IF EXISTS feed_user_prefs');
        $db->execute('DROP TABLE IF EXISTS feed_events');
    }
};
