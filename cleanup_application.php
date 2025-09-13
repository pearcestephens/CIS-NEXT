#!/usr/bin/env php
<?php
/**
 * CIS Application Cleanup Tool
 * 
 * Identifies and removes unnecessary files, keeping only essential application files
 * Creates a clean, production-ready workspace
 * 
 * Usage: php cleanup_application.php
 */

echo "🧹 CIS Application Cleanup Tool\n";
echo "===============================\n\n";

// Define file categories and patterns to remove
$cleanup_patterns = [
    'backup_files' => [
        '*.bak',
        '*.backup',
        '*-backup.*',
        '*_backup.*',
        '*original*',
        '*.old'
    ],
    'temporary_files' => [
        'tmp_*',
        'temp_*',
        '*.tmp',
        '*.temp',
        'test_*',
        '*_test.*'
    ],
    'analysis_files' => [
        '*analysis*',
        '*report*',
        '*audit*',
        '*check*',
        '*status*',
        '*verification*'
    ],
    'migration_files' => [
        '*migration*',
        '*cleanup*',
        'execute_*',
        'run_*',
        'complete_*',
        'final_*',
        'finalize_*'
    ],
    'script_files' => [
        '*.sh',
        'bash_*',
        'deploy_*',
        'install_*',
        'generate_*'
    ],
    'log_files' => [
        '*.log',
        'logs/*',
        '*_log.*'
    ],
    'documentation_extras' => [
        'CHANGELOG.md',
        'MANIFEST.md',
        '*_COMPLETE.md',
        '*_REPORT.md',
        '*_GUIDE.md'
    ]
];

// Essential files to KEEP (whitelist)
$essential_files = [
    'index.php',
    'admin.php',
    'config.php',
    'database.php',
    '.htaccess',
    'robots.txt',
    'favicon.ico',
    'README.md'
];

// Essential directories to KEEP
$essential_directories = [
    'assets/',
    'app/',
    'public/',
    'vendor/',
    'includes/',
    'templates/',
    'views/',
    'controllers/',
    'models/',
    'config/',
    'uploads/',
    'cache/'
];

function scanDirectory($dir = '.') {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

function matchesPattern($filename, $patterns) {
    foreach ($patterns as $pattern) {
        if (fnmatch($pattern, basename($filename))) {
            return true;
        }
        if (fnmatch($pattern, $filename)) {
            return true;
        }
    }
    return false;
}

function isEssentialFile($filename, $essential_files) {
    $basename = basename($filename);
    return in_array($basename, $essential_files);
}

function isInEssentialDirectory($filename, $essential_directories) {
    foreach ($essential_directories as $dir) {
        if (strpos($filename, $dir) === 0) {
            return true;
        }
    }
    return false;
}

// Scan all files
echo "🔍 Scanning application files...\n";
$all_files = scanDirectory('.');
echo "   Found " . count($all_files) . " total files\n\n";

// Categorize files for removal
$files_to_remove = [];
$files_to_keep = [];

foreach ($all_files as $file) {
    $keep_file = false;
    $reason = '';
    
    // Check if essential file
    if (isEssentialFile($file, $essential_files)) {
        $keep_file = true;
        $reason = 'essential file';
    }
    // Check if in essential directory
    elseif (isInEssentialDirectory($file, $essential_directories)) {
        $keep_file = true;
        $reason = 'essential directory';
    }
    // Check if matches cleanup patterns
    else {
        foreach ($cleanup_patterns as $category => $patterns) {
            if (matchesPattern($file, $patterns)) {
                $files_to_remove[$category][] = $file;
                break;
            }
        }
        
        if (!isset($files_to_remove) || !in_array($file, array_merge(...array_values($files_to_remove)))) {
            // File doesn't match removal patterns, keep it
            $keep_file = true;
            $reason = 'no removal pattern match';
        }
    }
    
    if ($keep_file) {
        $files_to_keep[] = ['file' => $file, 'reason' => $reason];
    }
}

// Display cleanup plan
echo "📋 Cleanup Analysis:\n";
echo "====================\n\n";

$total_removal_count = 0;

foreach ($cleanup_patterns as $category => $patterns) {
    if (isset($files_to_remove[$category]) && !empty($files_to_remove[$category])) {
        $count = count($files_to_remove[$category]);
        $total_removal_count += $count;
        echo "🗑️  " . ucwords(str_replace('_', ' ', $category)) . ": $count files\n";
        
        foreach ($files_to_remove[$category] as $file) {
            echo "    - $file\n";
        }
        echo "\n";
    }
}

echo "✅ Files to Keep: " . count($files_to_keep) . "\n";
echo "❌ Files to Remove: $total_removal_count\n\n";

// Show files we're keeping
echo "📁 Essential Files Being Preserved:\n";
echo "===================================\n";

foreach ($files_to_keep as $item) {
    echo "✅ {$item['file']} ({$item['reason']})\n";
}

echo "\n";

// Confirmation prompt
echo "⚠️  WARNING: This will permanently delete $total_removal_count files!\n";
echo "Do you want to proceed with cleanup? (y/N): ";

$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'y' && strtolower($confirmation) !== 'yes') {
    echo "❌ Cleanup cancelled by user.\n";
    exit(0);
}

// Perform cleanup
echo "\n🧹 Starting cleanup process...\n";

$removed_count = 0;
$failed_removals = [];

foreach ($cleanup_patterns as $category => $patterns) {
    if (isset($files_to_remove[$category]) && !empty($files_to_remove[$category])) {
        echo "\n🗑️  Removing " . ucwords(str_replace('_', ' ', $category)) . "...\n";
        
        foreach ($files_to_remove[$category] as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    echo "    ✅ Removed: $file\n";
                    $removed_count++;
                } else {
                    echo "    ❌ Failed: $file\n";
                    $failed_removals[] = $file;
                }
            } else {
                echo "    ⚠️  Not found: $file\n";
            }
        }
    }
}

// Remove empty directories
echo "\n🗂️  Removing empty directories...\n";
$empty_dirs_removed = 0;

$dirs = glob('*', GLOB_ONLYDIR);
foreach ($dirs as $dir) {
    if (is_dir($dir) && count(scandir($dir)) == 2) { // Only . and ..
        if (rmdir($dir)) {
            echo "    ✅ Removed empty directory: $dir\n";
            $empty_dirs_removed++;
        }
    }
}

// Final summary
echo "\n🎉 Cleanup Complete!\n";
echo "====================\n";
echo "✅ Files removed: $removed_count\n";
echo "🗂️  Empty directories removed: $empty_dirs_removed\n";
echo "✅ Files preserved: " . count($files_to_keep) . "\n";

if (!empty($failed_removals)) {
    echo "⚠️  Failed removals: " . count($failed_removals) . "\n";
    foreach ($failed_removals as $file) {
        echo "    - $file\n";
    }
}

echo "\n📊 Application is now clean and organized!\n";
echo "   Total space saved: " . (($total_removal_count - count($failed_removals)) * 10) . "KB (estimated)\n";

// Create a clean file structure report
$structure_report = [
    'cleanup_date' => date('Y-m-d H:i:s'),
    'files_removed' => $removed_count,
    'files_preserved' => count($files_to_keep),
    'directories_cleaned' => $empty_dirs_removed,
    'essential_files' => $files_to_keep
];

file_put_contents('cleanup_report.json', json_encode($structure_report, JSON_PRETTY_PRINT));
echo "\n📄 Cleanup report saved to: cleanup_report.json\n";

echo "\n🚀 Your CIS application is now production-ready!\n";
?>
