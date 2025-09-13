<?php
declare(strict_types=1);

require_once __DIR__ . '/app/Shared/Bootstrap.php';

use App\Shared\Bootstrap;
use App\Infra\Persistence\MariaDB\Database;

try {
    // Initialize system
    Bootstrap::init(__DIR__);
    $db = Database::getInstance();
    
    echo "🎲 Creating Sample Feed Events\n";
    echo "==============================\n\n";
    echo "✅ Database connection established\n";
    
    // Sample events data
    $sampleEvents = [
        [
            'source' => 'stock',
            'source_id' => 'low_stock_elux',
            'outlet_id' => 5,
            'severity' => 'warn',
            'title' => 'Low Stock Alert',
            'summary' => 'ELUX Legend 3500 down to 2 units at Queen Street',
            'metadata' => json_encode(['product' => 'ELUX Legend 3500', 'current_stock' => 2, 'reorder_level' => 10]),
            'entity_url' => '/admin/inventory/products/elux-legend-3500',
            'ts' => date('Y-m-d H:i:s', time() - 2400) // 40 minutes ago
        ],
        [
            'source' => 'vend',
            'source_id' => 'sale_12345',
            'outlet_id' => 1,
            'severity' => 'info',
            'title' => 'High Value Sale',
            'summary' => 'Premium starter kit sold for $220 - customer John Smith',
            'metadata' => json_encode(['sale_amount' => 220, 'customer' => 'John Smith', 'items' => 3]),
            'entity_url' => '/admin/sales/12345',
            'ts' => date('Y-m-d H:i:s', time() - 300) // 5 minutes ago
        ],
        [
            'source' => 'po',
            'source_id' => 'po_recv_789',
            'outlet_id' => 2,
            'severity' => 'warn',
            'title' => 'Partial PO Delivery',
            'summary' => 'PO #789 delivered with 3 items missing from shipment',
            'metadata' => json_encode(['po_number' => 'PO-789', 'missing_items' => 3, 'supplier' => 'VapeSupply Ltd']),
            'entity_url' => '/admin/purchase-orders/789',
            'ts' => date('Y-m-d H:i:s', time() - 1800) // 30 minutes ago
        ],
        [
            'source' => 'qa',
            'source_id' => 'qa_check_456',
            'outlet_id' => 1,
            'severity' => 'error',
            'title' => 'QA Checklist Failed',
            'summary' => 'Daily quality checklist failed - cleaning standards not met',
            'metadata' => json_encode(['checklist_id' => 456, 'failed_items' => 2, 'inspector' => 'Manager Sarah']),
            'entity_url' => '/admin/qa/456',
            'ts' => date('Y-m-d H:i:s', time() - 3600) // 1 hour ago
        ],
        [
            'source' => 'ciswatch',
            'source_id' => 'alert_001',
            'outlet_id' => 3,
            'severity' => 'critical',
            'title' => 'Security Alert',
            'summary' => 'Motion detected after hours in storage area',
            'metadata' => json_encode(['camera_id' => 'CAM03', 'area' => 'storage', 'confidence' => 95]),
            'entity_url' => '/admin/security/alerts/001',
            'ts' => date('Y-m-d H:i:s', time() - 7200) // 2 hours ago
        ],
        [
            'source' => 'training',
            'source_id' => 'cert_complete_123',
            'outlet_id' => 1,
            'severity' => 'info',
            'title' => 'Training Completed',
            'summary' => 'Staff member Jane completed Vape Safety Certification',
            'metadata' => json_encode(['employee' => 'Jane Smith', 'course' => 'Vape Safety', 'score' => 92]),
            'entity_url' => '/admin/training/certificates/123',
            'ts' => date('Y-m-d H:i:s', time() - 10800) // 3 hours ago
        ],
        [
            'source' => 'system',
            'source_id' => 'slow_query_001',
            'outlet_id' => null,
            'severity' => 'warn',
            'title' => 'Slow Database Query Detected',
            'summary' => 'Query took 2.3 seconds to execute - possible optimization needed',
            'metadata' => json_encode(['execution_time' => 2.31, 'query' => 'SELECT * FROM vend_sales...', 'endpoint' => '/admin/reports']),
            'entity_url' => '/admin/profiler/slow-queries',
            'ts' => date('Y-m-d H:i:s', time() - 14400) // 4 hours ago
        ],
        [
            'source' => 'vend',
            'source_id' => 'refund_456',
            'outlet_id' => 2,
            'severity' => 'warn',
            'title' => 'Large Refund Issued',
            'summary' => 'Refund of $180 issued for defective device - 2nd refund this week',
            'metadata' => json_encode(['amount' => 180, 'reason' => 'defective', 'customer_id' => 789, 'weekly_refunds' => 2]),
            'entity_url' => '/admin/refunds/456',
            'ts' => date('Y-m-d H:i:s', time() - 18000) // 5 hours ago
        ],
        [
            'source' => 'vend',
            'source_id' => 'stock_low_001',
            'outlet_id' => 4,
            'severity' => 'warn',
            'title' => 'Stock Level Critical',
            'summary' => 'Premium Juice Blue Raspberry down to 2 units - reorder needed',
            'metadata' => json_encode(['product' => 'Premium Juice Blue Raspberry', 'current_stock' => 2, 'reorder_level' => 10]),
            'entity_url' => '/admin/inventory/products/001',
            'ts' => date('Y-m-d H:i:s', time() - 21600) // 6 hours ago
        ]
    ];
    
    $inserted = 0;
    echo "📝 Processing " . count($sampleEvents) . " sample events...\n\n";
    
    foreach ($sampleEvents as $event) {
        // Check if event already exists
        $result = $db->execute("SELECT COUNT(*) as count FROM feed_events WHERE source = ? AND source_id = ?", 
            [$event['source'], $event['source_id']]);
        $exists = $result->fetch()['count'] > 0;
        
        if (!$exists) {
            try {
                $db->execute("
                    INSERT INTO feed_events (
                        source, source_id, outlet_id, severity, title, summary, 
                        meta_json, entity_url, ts, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ", [
                    $event['source'],
                    $event['source_id'], 
                    $event['outlet_id'],
                    $event['severity'],
                    $event['title'],
                    $event['summary'],
                    $event['metadata'],
                    $event['entity_url'],
                    $event['ts']
                ]);
                
                echo "✅ Created event: {$event['title']}\n";
                $inserted++;
            } catch (Exception $insertError) {
                echo "❌ Failed to create event: {$event['title']}\n";
                echo "   Error: " . $insertError->getMessage() . "\n";
                echo "   Data: source={$event['source']}, severity={$event['severity']}\n";
                echo "   Title length: " . strlen($event['title']) . "\n";
                echo "   Summary length: " . strlen($event['summary']) . "\n\n";
            }
        } else {
            echo "⏭️  Skipping existing event: {$event['title']}\n";
        }
    }
    
    echo "\n🎉 Sample event creation completed!\n";
    echo "📊 Events inserted: {$inserted}\n";
    echo "📋 Total events in database: ";
    
    $result = $db->execute("SELECT COUNT(*) as total FROM feed_events");
    $total = $result->fetch()['total'];
    echo "{$total}\n\n";
    
    echo "🔗 View your timeline at: https://staff.vapeshed.co.nz/admin/feed/timeline\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n"; 
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
