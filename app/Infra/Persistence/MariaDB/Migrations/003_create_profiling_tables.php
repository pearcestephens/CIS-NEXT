<?php
declare(strict_types=1);

use App\Infra\Persistence\MariaDB\Migration;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Create Profiling Tables
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        // Create profiling_request table
        $db->execute("
            CREATE TABLE profiling_request (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(50) NOT NULL,
                route VARCHAR(255),
                method VARCHAR(10) NOT NULL,
                user_id INT UNSIGNED NULL,
                uri TEXT NOT NULL,
                status_code INT NOT NULL,
                total_ms DECIMAL(10,2) NOT NULL,
                memory_peak BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_request_id (request_id),
                INDEX idx_user (user_id),
                INDEX idx_route (route),
                INDEX idx_status (status_code),
                INDEX idx_created (created_at),
                INDEX idx_total_ms (total_ms),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create profiling_query table
        $db->execute("
            CREATE TABLE profiling_query (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(50) NOT NULL,
                sequence_no INT NOT NULL,
                sql_text TEXT NOT NULL,
                params_json JSON NULL,
                row_count INT NOT NULL DEFAULT 0,
                ms DECIMAL(10,2) NOT NULL,
                explain_json JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_request_id (request_id),
                INDEX idx_ms (ms),
                INDEX idx_created (created_at),
                INDEX idx_request_sequence (request_id, sequence_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create slow_query_log table
        $db->execute("
            CREATE TABLE slow_query_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sql_hash VARCHAR(32) NOT NULL UNIQUE,
                sample_sql TEXT NOT NULL,
                avg_ms DECIMAL(10,2) NOT NULL,
                max_ms DECIMAL(10,2) NOT NULL,
                calls BIGINT UNSIGNED NOT NULL DEFAULT 1,
                last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_hash (sql_hash),
                INDEX idx_avg_ms (avg_ms),
                INDEX idx_max_ms (max_ms),
                INDEX idx_calls (calls),
                INDEX idx_last_seen (last_seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS slow_query_log');
        $db->execute('DROP TABLE IF EXISTS profiling_query');
        $db->execute('DROP TABLE IF EXISTS profiling_request');
    }
};
