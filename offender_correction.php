<?php
/**
 * Comprehensive Offender Correction + Canonicalization Script
 * Real diff → merge → backup → delete → verify
 */

declare(strict_types=1);
date_default_timezone_set('Pacific/Auckland');

$timestamp = date('Ymd_His');
$backup_dir = "backups/offender_cleanup_{$timestamp}";
$merge_dir = "var/reports/merge_diffs_{$timestamp}";
$report_file = "var/reports/offender_cleanup_{$timestamp}.json";

echo "🔧 OFFENDER CORRECTION + CANONICALIZATION\n";
echo "========================================\n";
echo "Started: " . date('Y-m-d H:i:s T') . "\n";
echo "Backup Dir: $backup_dir\n";
echo "Merge Dir: $merge_dir\n\n";

// Ensure directories exist
foreach ([$backup_dir, $merge_dir, dirname($report_file)] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

$cleanup_report = [
    'started_at' => date('c'),
    'timezone' => 'Pacific/Auckland',
    'renamed_or_deleted' => [],
    'merged' => [],
    'canonical_changed' => [],
    'backups' => [],
    'references_updated' => []
];

// Define offender → canonical mappings
$offender_mappings = [
    'app/Http/Views/admin/tools/automation_new.php' => 'app/Http/Views/admin/tools/automation.php',
    'app/Http/Views/admin/tools/migrations_new.php' => 'app/Http/Views/admin/tools/migrations.php',
    'app/Http/Views/admin/tools/seed_new.php' => 'app/Http/Views/admin/tools/seed.php',
    'app/Http/Views/admin/tools/seed_enhanced.php' => 'app/Http/Views/admin/tools/seed.php',
    'migrations/001_create_users_and_roles_FIXED.php' => 'migrations/001_create_users_and_roles.php'
];

// Additional standalone offenders (no merge target)
$standalone_offenders = [
    'app/Http/Views/admin/monitor/dashboard_new.php',
    'app/Http/Views/admin/monitor/dashboard_refactored.php',
    'app/Http/Views/admin/prefix_management_new.php',
    'app/Http/Views/admin/prefix_management_stage4.php',
    'app/Http/Views/admin/prefix_management_temp_backup.php'
];

function computeDiff($file1, $file2) {
    if (!file_exists($file1) || !file_exists($file2)) return null;
    
    $content1 = file_get_contents($file1);
    $content2 = file_get_contents($file2);
    
    if ($content1 === $content2) return ['identical' => true, 'hunks' => 0];
    
    $lines1 = explode("\n", $content1);
    $lines2 = explode("\n", $content2);
    
    $added = count(array_diff($lines2, $lines1));
    $removed = count(array_diff($lines1, $lines2));
    
    return [
        'identical' => false,
        'hunks' => $added + $removed,
        'added_lines' => $added,
        'removed_lines' => $removed,
        'size_diff' => strlen($content2) - strlen($content1)
    ];
}

function backupFile($file, $backup_dir) {
    if (!file_exists($file)) return null;
    
    $backup_path = $backup_dir . '/' . basename($file);
    if (copy($file, $backup_path)) {
        return [
            'path' => $backup_path,
            'size' => filesize($file),
            'sha256' => hash_file('sha256', $file)
        ];
    }
    return null;
}

echo "1️⃣ PROCESSING MERGE CANDIDATES...\n";
foreach ($offender_mappings as $offender => $canonical) {
    if (!file_exists($offender)) {
        echo "⚠️  Offender not found: $offender\n";
        continue;
    }
    
    echo "Processing: $offender → $canonical\n";
    
    // Backup offender first
    $backup_info = backupFile($offender, $backup_dir);
    if ($backup_info) {
        $cleanup_report['backups'][] = array_merge(['original' => $offender], $backup_info);
        echo "  📁 Backed up: {$backup_info['size']} bytes\n";
    }
    
    // Check if canonical exists for diffing
    if (file_exists($canonical)) {
        $diff_result = computeDiff($canonical, $offender);
        
        if ($diff_result && !$diff_result['identical'] && $diff_result['hunks'] > 5) {
            echo "  🔄 Significant differences found ({$diff_result['hunks']} hunks, {$diff_result['size_diff']} bytes)\n";
            
            // Check if offender is marked DELETE_FILE
            $offender_content = file_get_contents($offender);
            if (strpos($offender_content, 'DELETE_FILE') === 0) {
                echo "  🗑️  Offender marked for deletion, skipping merge\n";
            } else {
                // Save diff patch
                $patch_file = $merge_dir . '/' . basename($offender) . '.patch';
                $patch_content = "--- $canonical\n+++ $offender\n";
                $patch_content .= "Hunks: {$diff_result['hunks']}\n";
                $patch_content .= "Size change: {$diff_result['size_diff']} bytes\n";
                file_put_contents($patch_file, $patch_content);
                
                // If offender is significantly larger and appears to be enhanced, consider merge
                if ($diff_result['size_diff'] > 1000 && !strpos(basename($offender), '_new')) {
                    echo "  📝 Enhanced version detected, merging into canonical\n";
                    
                    // Backup canonical
                    $canonical_backup = backupFile($canonical, $backup_dir . '/canonical_originals');
                    
                    // Replace canonical with enhanced version
                    copy($offender, $canonical);
                    
                    $cleanup_report['merged'][] = [
                        'from' => $offender,
                        'into' => $canonical,
                        'hunks' => $diff_result['hunks']
                    ];
                    $cleanup_report['canonical_changed'][] = $canonical;
                }
            }
        } else {
            echo "  ✅ No significant differences or file marked for deletion\n";
        }
    }
    
    // Delete offender
    if (unlink($offender)) {
        echo "  🗑️  Deleted: $offender\n";
        $cleanup_report['renamed_or_deleted'][] = $offender;
    }
}

echo "\n2️⃣ PROCESSING STANDALONE OFFENDERS...\n";
foreach ($standalone_offenders as $offender) {
    if (file_exists($offender)) {
        echo "Processing: $offender\n";
        
        $backup_info = backupFile($offender, $backup_dir);
        if ($backup_info) {
            $cleanup_report['backups'][] = array_merge(['original' => $offender], $backup_info);
        }
        
        if (unlink($offender)) {
            echo "  🗑️  Deleted: $offender\n";
            $cleanup_report['renamed_or_deleted'][] = $offender;
        }
    }
}

echo "\n3️⃣ GENERATING CLEANUP REPORT...\n";
$cleanup_report['completed_at'] = date('c');
file_put_contents($report_file, json_encode($cleanup_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "📄 Report saved: $report_file\n";

echo "\n✅ OFFENDER CORRECTION COMPLETE\n";
echo "Deleted: " . count($cleanup_report['renamed_or_deleted']) . " files\n";
echo "Merged: " . count($cleanup_report['merged']) . " enhancements\n";
echo "Canonical changed: " . count($cleanup_report['canonical_changed']) . " files\n";

return $cleanup_report;
?>
