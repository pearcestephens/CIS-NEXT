<?php
/**
 * Quick path verification for comprehensive_demo.php
 */

echo "=== COMPREHENSIVE DEMO PATH VERIFICATION ===\n";

// Test the paths used in comprehensive_demo.php
$toolsDir = __DIR__;
$baseDir = dirname($toolsDir);

echo "Tools directory: {$toolsDir}\n";
echo "Base directory: {$baseDir}\n";

// Check var/reports path
$reportsDir = $baseDir . '/var/reports';
echo "Reports directory: {$reportsDir}\n";
echo "Reports exists: " . (is_dir($reportsDir) ? 'YES' : 'NO') . "\n";
echo "Reports writable: " . (is_writable($reportsDir) ? 'YES' : 'NO') . "\n";

// Check var/screenshots path  
$screenshotsDir = $baseDir . '/var/screenshots';
echo "Screenshots directory: {$screenshotsDir}\n";
echo "Screenshots exists: " . (is_dir($screenshotsDir) ? 'YES' : 'NO') . "\n";
echo "Screenshots writable: " . (is_writable($screenshotsDir) ? 'YES' : 'NO') . "\n";

// Test creating directories
@mkdir($reportsDir, 0755, true);
@mkdir($screenshotsDir, 0755, true);

echo "\nAfter mkdir attempts:\n";
echo "Reports exists: " . (is_dir($reportsDir) ? 'YES' : 'NO') . "\n";
echo "Screenshots exists: " . (is_dir($screenshotsDir) ? 'YES' : 'NO') . "\n";

// Test file operations
$testFile = $reportsDir . '/path_test.tmp';
$writeTest = file_put_contents($testFile, 'test') !== false;
echo "\nFile write test: " . ($writeTest ? 'SUCCESS' : 'FAILED') . "\n";

if ($writeTest && file_exists($testFile)) {
    unlink($testFile);
    echo "Test file cleaned up.\n";
}

echo "\n✅ Path verification complete!\n";
?>
