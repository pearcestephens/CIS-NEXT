<?php
/**
 * Comprehensive System Validation Suite
 * File: tools/system_validation_suite.php
 * 
 * Runs complete validation of prefix consolidation and system hardening
 */

declare(strict_types=1);
date_default_timezone_set('Pacific/Auckland');

require_once __DIR__ . '/../functions/config.php';

// CLI color codes
const RED = "\033[0;31m";
const GREEN = "\033[0;32m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const RESET = "\033[0m";

function colorize(string $text, string $color): string {
    return $color . $text . RESET;
}

$execution_id = 'VALIDATION_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
$base_path = dirname(__DIR__);

echo colorize("🔍 COMPREHENSIVE SYSTEM VALIDATION SUITE", BLUE) . "\n";
echo "=========================================\n";
echo "Execution ID: $execution_id\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "Base Path: $base_path\n\n";

$validation_results = [
    'execution_id' => $execution_id,
    'timestamp' => date('c'),
    'timezone' => 'Pacific/Auckland',
    'tests' => []
];

// Test 1: File Check Enhanced
echo colorize("📋 TEST 1: LEGACY PATTERN DETECTION", BLUE) . "\n";
echo "====================================\n";

$file_check_script = $base_path . '/tools/file_check_enhanced.php';
$test1_result = ['name' => 'Legacy Pattern Detection', 'status' => 'unknown', 'details' => []];

if (file_exists($file_check_script)) {
    ob_start();
    include $file_check_script;
    $output = ob_get_clean();
    
    // Parse output for violations
    if (strpos($output, 'legacy_pattern_violations') !== false && strpos($output, '"count": 0') !== false) {
        $test1_result['status'] = 'PASS';
        echo colorize("✅ No legacy patterns found", GREEN) . "\n";
    } else {
        $test1_result['status'] = 'FAIL';
        echo colorize("❌ Legacy patterns still exist", RED) . "\n";
    }
    
    $test1_result['details']['output_length'] = strlen($output);
} else {
    $test1_result['status'] = 'SKIP';
    echo colorize("⚠️  file_check_enhanced.php not found", YELLOW) . "\n";
}

$validation_results['tests']['legacy_patterns'] = $test1_result;

// Test 2: Database Connectivity 
echo "\n" . colorize("🗄️  TEST 2: DATABASE CONNECTIVITY", BLUE) . "\n";
echo "=================================\n";

$test2_result = ['name' => 'Database Connectivity', 'status' => 'unknown', 'details' => []];

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception($mysqli->connect_error);
    }
    
    $result = $mysqli->query("SELECT VERSION()");
    $version = $result->fetch_row()[0];
    
    $test2_result['status'] = 'PASS';
    $test2_result['details']['database_version'] = $version;
    $test2_result['details']['host'] = DB_HOST;
    $test2_result['details']['database'] = DB_NAME;
    
    echo colorize("✅ Database connected successfully", GREEN) . "\n";
    echo "Version: $version\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    $test2_result['status'] = 'FAIL';
    $test2_result['details']['error'] = $e->getMessage();
    echo colorize("❌ Database connection failed: " . $e->getMessage(), RED) . "\n";
}

$validation_results['tests']['database_connectivity'] = $test2_result;

// Test 3: Table Prefix Compliance
echo "\n" . colorize("🏷️  TEST 3: TABLE PREFIX COMPLIANCE", BLUE) . "\n";
echo "==================================\n";

$test3_result = ['name' => 'Table Prefix Compliance', 'status' => 'unknown', 'details' => []];

if ($test2_result['status'] === 'PASS') {
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $result = $mysqli->query("SHOW TABLES");
        
        $tables = [];
        $prefixed_count = 0;
        $unprefixed_count = 0;
        
        while ($row = $result->fetch_row()) {
            $table = $row[0];
            $tables[] = $table;
            
            if (strpos($table, 'cis_') === 0) {
                $prefixed_count++;
            } else {
                $unprefixed_count++;
            }
        }
        
        $test3_result['details']['total_tables'] = count($tables);
        $test3_result['details']['prefixed_tables'] = $prefixed_count;
        $test3_result['details']['unprefixed_tables'] = $unprefixed_count;
        
        if ($unprefixed_count === 0) {
            $test3_result['status'] = 'PASS';
            echo colorize("✅ All tables have cis_ prefix", GREEN) . "\n";
        } else {
            $test3_result['status'] = 'FAIL';
            echo colorize("❌ $unprefixed_count tables missing cis_ prefix", RED) . "\n";
        }
        
        echo "Total tables: " . count($tables) . "\n";
        echo "Prefixed: $prefixed_count\n";
        echo "Unprefixed: $unprefixed_count\n";
        
        $mysqli->close();
        
    } catch (Exception $e) {
        $test3_result['status'] = 'ERROR';
        $test3_result['details']['error'] = $e->getMessage();
        echo colorize("❌ Error checking table prefixes: " . $e->getMessage(), RED) . "\n";
    }
} else {
    $test3_result['status'] = 'SKIP';
    echo colorize("⚠️  Skipped due to database connection failure", YELLOW) . "\n";
}

$validation_results['tests']['table_prefix_compliance'] = $test3_result;

// Test 4: Migration Idempotency
echo "\n" . colorize("🔄 TEST 4: MIGRATION IDEMPOTENCY", BLUE) . "\n";
echo "=================================\n";

$test4_result = ['name' => 'Migration Idempotency', 'status' => 'unknown', 'details' => []];

$migration_script = $base_path . '/run_all_migrations.php';

if (file_exists($migration_script)) {
    echo "Running migrations first time...\n";
    
    ob_start();
    $first_run_start = microtime(true);
    include $migration_script;
    $first_run_time = microtime(true) - $first_run_start;
    $first_output = ob_get_clean();
    
    echo "Running migrations second time...\n";
    
    ob_start();
    $second_run_start = microtime(true);
    include $migration_script;
    $second_run_time = microtime(true) - $second_run_start;
    $second_output = ob_get_clean();
    
    // Check if second run shows no changes
    if (strpos($second_output, 'No migrations to run') !== false || 
        strpos($second_output, '0 changes') !== false ||
        strlen($second_output) < strlen($first_output) / 2) {
        
        $test4_result['status'] = 'PASS';
        echo colorize("✅ Migrations are idempotent", GREEN) . "\n";
    } else {
        $test4_result['status'] = 'FAIL';
        echo colorize("❌ Second migration run made changes", RED) . "\n";
    }
    
    $test4_result['details']['first_run_time_ms'] = round($first_run_time * 1000, 2);
    $test4_result['details']['second_run_time_ms'] = round($second_run_time * 1000, 2);
    $test4_result['details']['first_output_length'] = strlen($first_output);
    $test4_result['details']['second_output_length'] = strlen($second_output);
    
} else {
    $test4_result['status'] = 'SKIP';
    echo colorize("⚠️  run_all_migrations.php not found", YELLOW) . "\n";
}

$validation_results['tests']['migration_idempotency'] = $test4_result;

// Test 5: Layout Compliance
echo "\n" . colorize("🎨 TEST 5: LAYOUT COMPLIANCE", BLUE) . "\n";
echo "=============================\n";

$test5_result = ['name' => 'Layout Compliance', 'status' => 'unknown', 'details' => []];

$layout_files = [
    'app/Http/Views/admin/layout.php' => ['csrf-token', 'assets/css/admin.css', 'assets/js/admin.js'],
    'app/Http/Views/admin/tools/automation.php' => ['ob_start()', 'layout.php'],
    'app/Http/Views/admin/tools/migrations.php' => ['ob_start()', 'layout.php'],
    'app/Http/Views/admin/tools/seed.php' => ['ob_start()', 'layout.php'],
    'assets/js/admin.js' => ['AdminPanel.showAlert']
];

$compliant_files = 0;
$total_files = count($layout_files);

foreach ($layout_files as $file => $requirements) {
    $full_path = $base_path . '/' . $file;
    
    if (!file_exists($full_path)) {
        echo colorize("❌ Missing: $file", RED) . "\n";
        continue;
    }
    
    $content = file_get_contents($full_path);
    $file_compliant = true;
    
    foreach ($requirements as $requirement) {
        if (strpos($content, $requirement) === false) {
            echo colorize("❌ $file: missing $requirement", RED) . "\n";
            $file_compliant = false;
            break;
        }
    }
    
    if ($file_compliant) {
        echo colorize("✅ $file: compliant", GREEN) . "\n";
        $compliant_files++;
    }
}

if ($compliant_files === $total_files) {
    $test5_result['status'] = 'PASS';
} else {
    $test5_result['status'] = 'FAIL';
}

$test5_result['details']['total_files'] = $total_files;
$test5_result['details']['compliant_files'] = $compliant_files;

$validation_results['tests']['layout_compliance'] = $test5_result;

// Test 6: Syntax Validation
echo "\n" . colorize("🔍 TEST 6: SYNTAX VALIDATION", BLUE) . "\n";
echo "=============================\n";

$test6_result = ['name' => 'Syntax Validation', 'status' => 'unknown', 'details' => []];

$php_files = glob($base_path . '/{*.php,app/**/*.php,tools/*.php}', GLOB_BRACE);
$syntax_errors = [];
$checked_files = 0;

foreach ($php_files as $file) {
    // Skip backup directories
    if (strpos($file, '/backups/') !== false) continue;
    
    $output = [];
    $return_code = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_code);
    
    if ($return_code !== 0) {
        $syntax_errors[] = [
            'file' => str_replace($base_path . '/', '', $file),
            'error' => implode("\n", $output)
        ];
    }
    
    $checked_files++;
}

if (empty($syntax_errors)) {
    $test6_result['status'] = 'PASS';
    echo colorize("✅ All PHP files have valid syntax", GREEN) . "\n";
} else {
    $test6_result['status'] = 'FAIL';
    echo colorize("❌ " . count($syntax_errors) . " files have syntax errors", RED) . "\n";
    
    foreach ($syntax_errors as $error) {
        echo "  " . $error['file'] . ": " . $error['error'] . "\n";
    }
}

$test6_result['details']['files_checked'] = $checked_files;
$test6_result['details']['syntax_errors'] = $syntax_errors;

$validation_results['tests']['syntax_validation'] = $test6_result;

// Generate final summary
echo "\n" . str_repeat("=", 60) . "\n";
echo colorize("VALIDATION SUMMARY", BLUE) . "\n";
echo str_repeat("=", 60) . "\n";

$total_tests = count($validation_results['tests']);
$passed_tests = 0;
$failed_tests = 0;
$skipped_tests = 0;

foreach ($validation_results['tests'] as $test_name => $test) {
    $status_color = match($test['status']) {
        'PASS' => GREEN,
        'FAIL', 'ERROR' => RED,
        'SKIP' => YELLOW,
        default => RESET
    };
    
    echo sprintf("%-25s: %s\n", $test_name, colorize($test['status'], $status_color));
    
    switch ($test['status']) {
        case 'PASS': $passed_tests++; break;
        case 'FAIL': 
        case 'ERROR': $failed_tests++; break;
        case 'SKIP': $skipped_tests++; break;
    }
}

echo "\nTotals:\n";
echo "  " . colorize("Passed: $passed_tests", GREEN) . "\n";
echo "  " . colorize("Failed: $failed_tests", $failed_tests > 0 ? RED : GREEN) . "\n";
echo "  " . colorize("Skipped: $skipped_tests", YELLOW) . "\n";
echo "  Total: $total_tests\n";

$validation_results['summary'] = [
    'total_tests' => $total_tests,
    'passed' => $passed_tests,
    'failed' => $failed_tests,
    'skipped' => $skipped_tests,
    'success_rate' => $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0
];

// Save detailed report
$report_file = $base_path . '/var/reports/prefix_and_hardening_validation_' . date('Ymd_His') . '.json';
file_put_contents($report_file, json_encode($validation_results, JSON_PRETTY_PRINT));

echo "\nDetailed report: " . colorize(str_replace($base_path . '/', '', $report_file), YELLOW) . "\n";

// Overall status
if ($failed_tests === 0) {
    echo "\n" . colorize("🎉 ALL VALIDATIONS PASSED", GREEN) . "\n";
    echo "System is ready for staging deployment.\n";
    exit(0);
} else {
    echo "\n" . colorize("⚠️  VALIDATION FAILURES DETECTED", RED) . "\n";
    echo "Review failed tests before deployment.\n";
    exit(1);
}

echo str_repeat("=", 60) . "\n";
