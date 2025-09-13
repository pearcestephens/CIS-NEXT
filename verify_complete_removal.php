<?php
/**
 * Final Integration Cleanup Verification
 * File: verify_complete_removal.php
 * Purpose: Verify 100% removal of all integration references
 */

echo "🔍 FINAL INTEGRATION REMOVAL VERIFICATION\n";
echo "=========================================\n";
echo "Timestamp: " . date('c') . "\n\n";

$base_path = __DIR__;

// Files to scan for any remaining references
$files_to_scan = [
    'app/Http/Controllers/IntegrationController.php',
    'routes/integrations.php',
    'app/Shared/Database/PrefixManager.php',
    'tools/module_inventory_auditor.php',
    'execute_module_inventory.php'
];

echo "🔍 SCANNING FILES FOR INTEGRATION REFERENCES:\n";
echo "==============================================\n";

$total_refs_found = 0;
$clean_files = 0;

foreach ($files_to_scan as $file) {
    $full_path = $base_path . '/' . $file;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        
        // Count case-insensitive references
        $vend_count = substr_count(strtolower($content), 'vend');
        $deputy_count = substr_count(strtolower($content), 'deputy');
        $xero_count = substr_count(strtolower($content), 'xero');
        
        $total_refs = $vend_count + $deputy_count + $xero_count;
        $total_refs_found += $total_refs;
        
        if ($total_refs > 0) {
            echo "⚠️ $file contains references:\n";
            if ($vend_count > 0) echo "   - Vend: $vend_count references\n";
            if ($deputy_count > 0) echo "   - Deputy: $deputy_count references\n";
            if ($xero_count > 0) echo "   - Xero: $xero_count references\n";
            
            // Show where the references are (first few lines containing them)
            $lines = explode("\n", $content);
            $ref_lines = [];
            foreach ($lines as $line_num => $line) {
                $lower_line = strtolower($line);
                if (strpos($lower_line, 'vend') !== false || 
                    strpos($lower_line, 'deputy') !== false || 
                    strpos($lower_line, 'xero') !== false) {
                    $ref_lines[] = "Line " . ($line_num + 1) . ": " . trim($line);
                    if (count($ref_lines) >= 3) break; // Show max 3 lines
                }
            }
            foreach ($ref_lines as $ref_line) {
                echo "   📍 $ref_line\n";
            }
            echo "\n";
        } else {
            echo "✅ $file - CLEAN (no references found)\n";
            $clean_files++;
        }
    } else {
        echo "❓ $file - FILE NOT FOUND\n";
    }
}

// Check integration directories
echo "\n📁 INTEGRATION DIRECTORIES:\n";
echo "===========================\n";

$integration_dirs = [
    'app/Integrations/Vend',
    'app/Integrations/Deputy',
    'app/Integrations/Xero'
];

$dirs_removed = 0;
foreach ($integration_dirs as $dir) {
    $full_path = $base_path . '/' . $dir;
    if (is_dir($full_path)) {
        echo "❌ $dir - STILL EXISTS\n";
    } else {
        echo "✅ $dir - REMOVED\n";
        $dirs_removed++;
    }
}

// Overall assessment
echo "\n📊 FINAL ASSESSMENT:\n";
echo "====================\n";

$all_dirs_removed = ($dirs_removed === count($integration_dirs));
$all_files_clean = ($total_refs_found === 0);

echo "Integration Directories Removed: " . ($all_dirs_removed ? "✅ YES" : "❌ NO") . " ($dirs_removed/" . count($integration_dirs) . ")\n";
echo "Code References Removed: " . ($all_files_clean ? "✅ YES" : "❌ NO") . " ($total_refs_found total references found)\n";
echo "Clean Files: $clean_files/" . count($files_to_scan) . "\n";

if ($all_dirs_removed && $all_files_clean) {
    echo "\n🎉 SUCCESS: 100% INTEGRATION REMOVAL COMPLETE!\n";
    echo "✅ All directories removed\n";
    echo "✅ All code references removed\n";
    echo "✅ System is completely clean\n";
} else {
    echo "\n⚠️ INCOMPLETE REMOVAL DETECTED:\n";
    if (!$all_dirs_removed) {
        echo "❌ Some integration directories still exist\n";
    }
    if (!$all_files_clean) {
        echo "❌ $total_refs_found integration references still found in code\n";
    }
}

// Core systems check
echo "\n⚙️ CORE SYSTEMS STATUS:\n";
echo "======================\n";

$queue_exists = file_exists($base_path . '/app/Shared/Queue/QueueManager.php');
$backup_exists = file_exists($base_path . '/app/Shared/Backup/BackupManager.php');

echo ($queue_exists ? "✅" : "❌") . " Queue System: " . ($queue_exists ? "READY" : "MISSING") . "\n";
echo ($backup_exists ? "✅" : "❌") . " Backup System: " . ($backup_exists ? "READY" : "MISSING") . "\n";

if ($all_dirs_removed && $all_files_clean && $queue_exists && $backup_exists) {
    echo "\n🏆 MISSION ACCOMPLISHED!\n";
    echo "🗑️ Integrations: 100% REMOVED\n";
    echo "⚙️ Queue System: 100% READY\n";
    echo "💾 Backup System: 100% READY\n";
}

?>
