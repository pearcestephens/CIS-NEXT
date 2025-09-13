<?php
/**
 * SR-12 Master Test Runner
 * File: tools/sr12_runner.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Orchestrate all SR-12 reliability tests end-to-end
 */

require_once __DIR__ . '/../functions/config.php';

class SR12TestRunner {
    
    private array $results = [
        'ok' => false,
        'timestamp' => '',
        'execution_time_ms' => 0,
        'tests' => [],
        'summary' => [],
        'failures' => [],
        'sr12_score' => 0
    ];
    
    private array $testSuite = [
        'file_check' => [
            'name' => 'File Compliance Check',
            'file' => 'file_check.php',
            'required' => true,
            'weight' => 10
        ],
        'sr12_lint' => [
            'name' => 'Code Quality Lint',
            'file' => 'sr12_lint.php',
            'required' => true,
            'weight' => 15
        ],
        'load_test' => [
            'name' => 'Performance Load Test',
            'file' => 'load_test.php',
            'required' => true,
            'weight' => 20
        ],
        'soak_test' => [
            'name' => 'Stability Soak Test',
            'file' => 'soak_test.php',
            'required' => true,
            'weight' => 20
        ],
        'chaos_test' => [
            'name' => 'Chaos Engineering Test',
            'file' => 'chaos_test.php',
            'required' => true,
            'weight' => 15
        ],
        'backup_restore_test' => [
            'name' => 'Backup & Restore Test',
            'file' => 'backup_restore_test.php',
            'required' => true,
            'weight' => 10
        ],
        'migration_test' => [
            'name' => 'Migration Idempotency Test',
            'file' => 'migration_test.php',
            'required' => true,
            'weight' => 10
        ]
    ];
    
    public function __construct() {
        $this->results['timestamp'] = date('c');
    }
    
    /**
     * Run complete SR-12 test suite
     */
    public function runCompleteSuite(array $options = []): array {
        $startTime = microtime(true);
        
        try {
            $this->initializeRunner($options);
            $this->runAllTests();
            $this->generateSummary();
            $this->calculateScore();
            $this->exportResults();
            
        } catch (Exception $e) {
            $this->results['ok'] = false;
            $this->results['error'] = $e->getMessage();
        }
        
        $this->results['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        return $this->results;
    }
    
    /**
     * Initialize runner with options
     */
    private function initializeRunner(array $options): void {
        $this->initializeReportDirectory();
        
        // Apply options
        if (isset($options['skip_soak']) && $options['skip_soak']) {
            unset($this->testSuite['soak_test']);
        }
        
        if (isset($options['chaos_mode']) && $options['chaos_mode']) {
            $this->testSuite['chaos_test']['weight'] = 25;
        }
        
        if (isset($options['fast_mode']) && $options['fast_mode']) {
            // Reduce test durations for fast mode
            $this->testSuite['load_test']['args'] = ['--requests=50', '--concurrent=2'];
            $this->testSuite['soak_test']['args'] = ['--mode=short'];
        }
    }
    
    /**
     * Initialize report directory
     */
    private function initializeReportDirectory(): void {
        $reportDir = '/var/reports/sr12';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        // Create run-specific directory
        $runId = date('Y-m-d_H-i-s');
        $runDir = "$reportDir/run_$runId";
        mkdir($runDir, 0755, true);
        
        $this->results['run_directory'] = $runDir;
        $this->results['run_id'] = $runId;
    }
    
    /**
     * Run all tests in the suite
     */
    private function runAllTests(): void {
        $this->results['tests'] = [];
        
        foreach ($this->testSuite as $testId => $testConfig) {
            $testResult = $this->runSingleTest($testId, $testConfig);
            $this->results['tests'][$testId] = $testResult;
            
            if ($testConfig['required'] && !$testResult['ok']) {
                $this->results['failures'][] = [
                    'test' => $testId,
                    'name' => $testConfig['name'],
                    'error' => $testResult['error'] ?? 'Test failed'
                ];
            }
        }
    }
    
    /**
     * Run a single test
     */
    private function runSingleTest(string $testId, array $testConfig): array {
        $testResult = [
            'id' => $testId,
            'name' => $testConfig['name'],
            'started_at' => microtime(true),
            'ok' => false,
            'duration_ms' => 0,
            'output' => '',
            'error' => null
        ];
        
        try {
            $testFile = __DIR__ . '/' . $testConfig['file'];
            
            if (!file_exists($testFile)) {
                throw new Exception("Test file not found: {$testConfig['file']}");
            }
            
            // Build command
            $command = "php $testFile run";
            if (isset($testConfig['args'])) {
                $command .= ' ' . implode(' ', $testConfig['args']);
            }
            
            // Execute test
            $output = [];
            $returnCode = null;
            
            exec($command, $output, $returnCode);
            
            $testResult['output'] = implode("\n", $output);
            $testResult['return_code'] = $returnCode;
            $testResult['ok'] = $returnCode === 0;
            
            // Parse JSON output if available
            $jsonOutput = json_decode($testResult['output'], true);
            if ($jsonOutput) {
                $testResult['parsed_result'] = $jsonOutput;
            }
            
        } catch (Exception $e) {
            $testResult['error'] = $e->getMessage();
        }
        
        $testResult['duration_ms'] = round((microtime(true) - $testResult['started_at']) * 1000, 2);
        
        return $testResult;
    }
    
    /**
     * Generate test summary
     */
    private function generateSummary(): void {
        $totalTests = count($this->testSuite);
        $passedTests = 0;
        $failedTests = 0;
        $totalDuration = 0;
        
        foreach ($this->results['tests'] as $testResult) {
            if ($testResult['ok']) {
                $passedTests++;
            } else {
                $failedTests++;
            }
            
            $totalDuration += $testResult['duration_ms'];
        }
        
        $this->results['summary'] = [
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $failedTests,
            'pass_rate_percent' => round(($passedTests / $totalTests) * 100, 1),
            'total_duration_seconds' => round($totalDuration / 1000, 2)
        ];
        
        $this->results['ok'] = $failedTests === 0;
    }
    
    /**
     * Calculate SR-12 score
     */
    private function calculateScore(): void {
        $totalWeight = 0;
        $achievedWeight = 0;
        
        foreach ($this->testSuite as $testId => $testConfig) {
            $totalWeight += $testConfig['weight'];
            
            if (isset($this->results['tests'][$testId]) && $this->results['tests'][$testId]['ok']) {
                $achievedWeight += $testConfig['weight'];
            }
        }
        
        $this->results['sr12_score'] = round(($achievedWeight / $totalWeight) * 100, 1);
        
        // Determine grade
        if ($this->results['sr12_score'] >= 95) {
            $grade = 'A+';
        } elseif ($this->results['sr12_score'] >= 90) {
            $grade = 'A';
        } elseif ($this->results['sr12_score'] >= 85) {
            $grade = 'B+';
        } elseif ($this->results['sr12_score'] >= 80) {
            $grade = 'B';
        } elseif ($this->results['sr12_score'] >= 70) {
            $grade = 'C';
        } else {
            $grade = 'F';
        }
        
        $this->results['sr12_grade'] = $grade;
    }
    
    /**
     * Export results to multiple formats
     */
    private function exportResults(): void {
        $runDir = $this->results['run_directory'];
        
        // JSON report
        $jsonFile = "$runDir/sr12_results.json";
        file_put_contents($jsonFile, json_encode($this->results, JSON_PRETTY_PRINT));
        
        // HTML report
        $htmlFile = "$runDir/sr12_report.html";
        $this->generateHTMLReport($htmlFile);
        
        // Summary badge
        $badgeFile = "$runDir/sr12_badge.svg";
        $this->generateBadge($badgeFile);
        
        // CSV summary
        $csvFile = "$runDir/sr12_summary.csv";
        $this->generateCSVSummary($csvFile);
        
        $this->results['reports'] = [
            'json' => $jsonFile,
            'html' => $htmlFile,
            'badge' => $badgeFile,
            'csv' => $csvFile
        ];
    }
    
    /**
     * Generate HTML report
     */
    private function generateHTMLReport(string $filename): void {
        $html = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>SR-12 Reliability Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; }
        .score { font-size: 2em; font-weight: bold; }
        .grade-A, .grade-A\\+ { color: #28a745; }
        .grade-B, .grade-B\\+ { color: #ffc107; }
        .grade-C { color: #fd7e14; }
        .grade-F { color: #dc3545; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
        .test-card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; }
        .test-pass { border-left: 5px solid #28a745; }
        .test-fail { border-left: 5px solid #dc3545; }
        .summary-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .summary-table th, .summary-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .summary-table th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>SR-12 Reliability Test Report</h1>
        <p><strong>Run ID:</strong> {$this->results['run_id']}</p>
        <p><strong>Timestamp:</strong> {$this->results['timestamp']}</p>
        <div class='score grade-{$this->results['sr12_grade']}'>{$this->results['sr12_score']}% - Grade {$this->results['sr12_grade']}</div>
    </div>
    
    <h2>Summary</h2>
    <table class='summary-table'>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Total Tests</td><td>{$this->results['summary']['total_tests']}</td></tr>
        <tr><td>Passed Tests</td><td>{$this->results['summary']['passed_tests']}</td></tr>
        <tr><td>Failed Tests</td><td>{$this->results['summary']['failed_tests']}</td></tr>
        <tr><td>Pass Rate</td><td>{$this->results['summary']['pass_rate_percent']}%</td></tr>
        <tr><td>Total Duration</td><td>{$this->results['summary']['total_duration_seconds']}s</td></tr>
    </table>
    
    <h2>Test Results</h2>
    <div class='test-grid'>";
    
    foreach ($this->results['tests'] as $testId => $test) {
        $status = $test['ok'] ? 'pass' : 'fail';
        $statusText = $test['ok'] ? 'PASS' : 'FAIL';
        
        $html .= "<div class='test-card test-$status'>
            <h3>{$test['name']}</h3>
            <p><strong>Status:</strong> $statusText</p>
            <p><strong>Duration:</strong> {$test['duration_ms']}ms</p>";
        
        if (!$test['ok'] && isset($test['error'])) {
            $html .= "<p><strong>Error:</strong> " . htmlspecialchars($test['error']) . "</p>";
        }
        
        $html .= "</div>";
    }
    
    $html .= "</div>
    
    <h2>Failures</h2>";
    
    if (empty($this->results['failures'])) {
        $html .= "<p>No failures detected.</p>";
    } else {
        $html .= "<ul>";
        foreach ($this->results['failures'] as $failure) {
            $html .= "<li><strong>{$failure['name']}:</strong> " . htmlspecialchars($failure['error']) . "</li>";
        }
        $html .= "</ul>";
    }
    
    $html .= "
    <hr>
    <p><em>Generated by SR-12 Test Runner at " . date('Y-m-d H:i:s') . "</em></p>
</body>
</html>";
    
    file_put_contents($filename, $html);
}

/**
 * Generate SVG badge
 */
private function generateBadge(string $filename): void {
    $score = $this->results['sr12_score'];
    $grade = $this->results['sr12_grade'];
    
    // Determine color based on grade
    $colors = [
        'A+' => '#28a745', 'A' => '#28a745',
        'B+' => '#ffc107', 'B' => '#ffc107',
        'C' => '#fd7e14', 'F' => '#dc3545'
    ];
    
    $color = $colors[$grade] ?? '#6c757d';
    
    $svg = "<?xml version='1.0' encoding='UTF-8'?>
<svg width='120' height='20' xmlns='http://www.w3.org/2000/svg'>
    <linearGradient id='b' x2='0' y2='100%'>
        <stop offset='0' stop-color='#bbb' stop-opacity='.1'/>
        <stop offset='1' stop-opacity='.1'/>
    </linearGradient>
    <clipPath id='a'>
        <rect width='120' height='20' rx='3' fill='#fff'/>
    </clipPath>
    <g clip-path='url(#a)'>
        <path fill='#555' d='M0 0h63v20H0z'/>
        <path fill='$color' d='M63 0h57v20H63z'/>
        <path fill='url(#b)' d='M0 0h120v20H0z'/>
    </g>
    <g font-family='DejaVu Sans,Verdana,Geneva,sans-serif' font-size='110'>
        <text x='5' y='15' fill='#fff'>SR-12</text>
        <text x='68' y='15' fill='#fff'>$score% ($grade)</text>
    </g>
</svg>";
    
    file_put_contents($filename, $svg);
}

/**
 * Generate CSV summary
 */
private function generateCSVSummary(string $filename): void {
    $csv = "Test Name,Status,Duration (ms),Weight,Error\n";
    
    foreach ($this->results['tests'] as $testId => $test) {
        $status = $test['ok'] ? 'PASS' : 'FAIL';
        $weight = $this->testSuite[$testId]['weight'] ?? 0;
        $error = $test['error'] ?? '';
        
        $csv .= sprintf(
            '"%s","%s",%d,%d,"%s"' . "\n",
            $test['name'],
            $status,
            $test['duration_ms'],
            $weight,
            str_replace('"', '""', $error)
        );
    }
    
    file_put_contents($filename, $csv);
}

/**
 * Get status for web interface
 */
public function getStatus(): array {
    $reportDir = '/var/reports/sr12';
    
    if (!is_dir($reportDir)) {
        return ['status' => 'not_run', 'message' => 'No tests have been run'];
    }
    
    // Find latest run
    $runDirs = glob("$reportDir/run_*");
    if (empty($runDirs)) {
        return ['status' => 'not_run', 'message' => 'No test runs found'];
    }
    
    rsort($runDirs);
    $latestRun = $runDirs[0];
    
    $jsonFile = "$latestRun/sr12_results.json";
    if (!file_exists($jsonFile)) {
        return ['status' => 'incomplete', 'message' => 'Test run incomplete'];
    }
    
    $results = json_decode(file_get_contents($jsonFile), true);
    
    return [
        'status' => $results['ok'] ? 'pass' : 'fail',
        'score' => $results['sr12_score'],
        'grade' => $results['sr12_grade'],
        'run_id' => basename($latestRun),
        'timestamp' => $results['timestamp'],
        'reports' => $results['reports'] ?? []
    ];
}

/**
 * Clean old test runs (keep last 10)
 */
public function cleanOldRuns(): array {
    $reportDir = '/var/reports/sr12';
    
    if (!is_dir($reportDir)) {
        return ['cleaned' => 0, 'message' => 'No report directory found'];
    }
    
    $runDirs = glob("$reportDir/run_*");
    rsort($runDirs); // Newest first
    
    $cleaned = 0;
    $keepCount = 10;
    
    for ($i = $keepCount; $i < count($runDirs); $i++) {
        $this->removeDirectory($runDirs[$i]);
        $cleaned++;
    }
    
    return ['cleaned' => $cleaned, 'kept' => min(count($runDirs), $keepCount)];
}

/**
 * Remove directory recursively
 */
private function removeDirectory(string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    
    rmdir($directory);
}
}

// CLI interface
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'run';
    
    switch ($action) {
        case 'run':
            $options = [];
            
            // Parse options
            for ($i = 2; $i < count($argv); $i++) {
                switch ($argv[$i]) {
                    case '--fast':
                        $options['fast_mode'] = true;
                        break;
                    case '--skip-soak':
                        $options['skip_soak'] = true;
                        break;
                    case '--chaos':
                        $options['chaos_mode'] = true;
                        break;
                }
            }
            
            echo "Running SR-12 complete reliability test suite...\n";
            $runner = new SR12TestRunner();
            $result = $runner->runCompleteSuite($options);
            
            echo "Test run completed!\n";
            echo "Score: {$result['sr12_score']}% (Grade {$result['sr12_grade']})\n";
            echo "Reports available in: {$result['run_directory']}\n";
            
            exit($result['ok'] ? 0 : 1);
            
        case 'status':
            $runner = new SR12TestRunner();
            $status = $runner->getStatus();
            echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'clean':
            $runner = new SR12TestRunner();
            $cleaned = $runner->cleanOldRuns();
            echo "Cleaned {$cleaned['cleaned']} old runs, kept {$cleaned['kept']}\n";
            break;
            
        default:
            echo "Usage: php sr12_runner.php [run|status|clean] [options]\n";
            echo "Options:\n";
            echo "  --fast       Fast mode (reduced test durations)\n";
            echo "  --skip-soak  Skip soak testing\n";
            echo "  --chaos      Enhanced chaos engineering mode\n";
            exit(1);
    }
}

// Web interface
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? 'status';
    $runner = new SR12TestRunner();
    
    switch ($action) {
        case 'run':
            $options = [];
            if (isset($_GET['fast'])) $options['fast_mode'] = true;
            if (isset($_GET['skip_soak'])) $options['skip_soak'] = true;
            if (isset($_GET['chaos'])) $options['chaos_mode'] = true;
            
            $result = $runner->runCompleteSuite($options);
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'status':
            $status = $runner->getStatus();
            echo json_encode($status, JSON_PRETTY_PRINT);
            break;
            
        case 'clean':
            $cleaned = $runner->cleanOldRuns();
            echo json_encode($cleaned, JSON_PRETTY_PRINT);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}
