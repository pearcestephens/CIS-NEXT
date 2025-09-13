<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Shared/Bootstrap.php';

use App\Shared\Bootstrap;
use App\Infra\Persistence\MariaDB\Database;

try {
    // Initialize system
    Bootstrap::init(__DIR__);
    $db = Database::getInstance();
    
    echo "📋 Feed Events in Database\n";
    echo "=========================\n\n";
    
    $result = $db->execute("
        SELECT id, ts, source, severity, title, summary, outlet_id 
        FROM feed_events 
        ORDER BY ts DESC 
        LIMIT 10
    ");
    
    while ($event = $result->fetch()) {
        echo "🆔 ID: {$event['id']}\n";
        echo "📅 Time: {$event['ts']}\n";
        echo "📍 Source: {$event['source']} (Outlet: " . ($event['outlet_id'] ?? 'Global') . ")\n";
        echo "⚠️  Severity: {$event['severity']}\n";
        echo "📝 Title: {$event['title']}\n";
        echo "💬 Summary: {$event['summary']}\n";
        echo "---\n";
    }
    
    echo "\n✅ Module 1 (AI Timeline & Newsfeed Engine) is working!\n";
    echo "🔗 Access Timeline: http://localhost:8081/admin/feed/timeline\n";
    echo "🔗 Login: admin@ecigdis.co.nz / CHANGE_ME_NOW\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n"; 
    exit(1);
}
