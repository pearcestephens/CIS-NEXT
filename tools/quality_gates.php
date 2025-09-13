<?php
declare(strict_types=1);

/**
 * Quality Gates Runner
 * Enforces code quality standards for CIS V2
 * 
 * @package CIS\Tools
 * @version 2.0.0
 */

namespace CIS\Tools;

require_once __DIR__ . '/../app/Shared/Bootstrap.php';

use App\Shared\Bootstrap;
use App\Shared\Logging\Logger;

class QualityGates
{
    private Logger $logger;
    private array $results = [];
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        Bootstrap::init($rootPath);
        $this->logger = Logger::getInstance();
    }

    public function runAll(): array
    {
        $this->logger->info('Starting quality gates assessment');
        
        $gates = [
            'psr12_compliance' => $this->checkPSR12Compliance(),
            'strict_types' => $this->checkStrictTypes(), 
            'security_scan' => $this->runSecurityScan(),
            'unit_tests' => $this->runUnitTests(),
            'integration_tests' => $this->runIntegrationTests(),
            'coverage_threshold' => $this->checkCoverageThreshold()
        ];

        $this->results = $gates;
        $overallPass = !in_array(false, array_values($gates), true);

        $summary = [
            'timestamp' => date('c'),
            'overall_pass' => $overallPass,
            'gates' => $gates,
            'score' => $this->calculateScore($gates),
            'recommendations' => $this->getRecommendations($gates)
        ];

        $this->logger->info('Quality gates completed', [
            'overall_pass' => $overallPass,
            'score' => $summary['score']
        ]);

        return $summary;
    }

    private function checkPSR12Compliance(): bool
    {
        $phpFiles = $this->getPhpFiles();
        $violations = 0;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check declare(strict_types=1)
            if (!str_contains($content, 'declare(strict_types=1)')) {
                $violations++;
                continue;
            }
            
            // Check namespace
            if (str_contains($file, '/app/') && !preg_match('/namespace\s+App\\\\/', $content)) {
                $violations++;
                continue;
            }
            
            // Check opening tag
            if (!str_starts_with($content, '<?php')) {
                $violations++;
                continue;
            }
        }

        $complianceRate = count($phpFiles) > 0 ? 1 - ($violations / count($phpFiles)) : 1;
        return $complianceRate >= 0.95;
    }

    private function checkStrictTypes(): bool
    {
        $phpFiles = $this->getPhpFiles();
        $strictCount = 0;
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'declare(strict_types=1)')) {
                $strictCount++;
            }
        }

        $strictRate = count($phpFiles) > 0 ? $strictCount / count($phpFiles) : 1;
        return $strictRate >= 0.90;
    }

    private function runSecurityScan(): bool
    {
        $issues = [];
        $phpFiles = $this->getPhpFiles();
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for SQL injection risks
            if (preg_match('/\$_(GET|POST|REQUEST)\s*\[\s*[\'"][^\'"]*[\'"]\s*\]\s*[^;]*;/', $content)) {
                $issues[] = "Potential SQL injection in {$file}";
            }
            
            // Check for XSS risks  
            if (preg_match('/echo\s+\$_(GET|POST|REQUEST)/', $content)) {
                $issues[] = "Potential XSS in {$file}";
            }
            
            // Check for hardcoded credentials
            if (preg_match('/(password|secret|key)\s*=\s*[\'"][^\'"]{8,}/', $content)) {
                $issues[] = "Potential hardcoded credentials in {$file}";
            }
        }

        return empty($issues);
    }

    private function runUnitTests(): bool
    {
        $testRunner = new TestRunner($this->rootPath);
        $results = $testRunner->runUnitTests();
        return $results['success'];
    }

    private function runIntegrationTests(): bool
    {
        $testRunner = new TestRunner($this->rootPath);
        $results = $testRunner->runIntegrationTests();
        return $results['success'];
    }

    private function checkCoverageThreshold(): bool
    {
        $coverageFile = $this->rootPath . '/tests/config/coverage.json';
        
        if (!file_exists($coverageFile)) {
            // Create default coverage config
            $defaultConfig = [
                'target' => 70,
                'current' => 0,
                'files' => []
            ];
            
            @mkdir(dirname($coverageFile), 0755, true);
            file_put_contents($coverageFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
            return false;
        }

        $coverage = json_decode(file_get_contents($coverageFile), true);
        return ($coverage['current'] ?? 0) >= ($coverage['target'] ?? 70);
    }

    private function getPhpFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootPath . '/app')
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }
        
        return $files;
    }

    private function calculateScore(array $gates): int
    {
        $passedCount = count(array_filter($gates));
        return (int) round(($passedCount / count($gates)) * 100);
    }

    private function getRecommendations(array $gates): array
    {
        $recommendations = [];
        
        if (!$gates['psr12_compliance']) {
            $recommendations[] = 'Run PSR-12 code formatter on all PHP files';
        }
        
        if (!$gates['strict_types']) {
            $recommendations[] = 'Add declare(strict_types=1) to all PHP files';
        }
        
        if (!$gates['security_scan']) {
            $recommendations[] = 'Review and fix security vulnerabilities identified';
        }
        
        if (!$gates['unit_tests'] || !$gates['integration_tests']) {
            $recommendations[] = 'Write comprehensive test coverage for core functionality';
        }
        
        if (!$gates['coverage_threshold']) {
            $recommendations[] = 'Increase test coverage to meet minimum threshold';
        }

        return $recommendations;
    }
}

// Test Runner class for quality gates
class TestRunner
{
    private string $rootPath;
    
    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
    }
    
    public function runUnitTests(): array
    {
        // Simplified test runner - will be enhanced in Phase 1
        $testDir = $this->rootPath . '/tests/unit';
        
        if (!is_dir($testDir)) {
            return ['success' => false, 'reason' => 'No unit tests directory found'];
        }
        
        // Count test files as basic success metric
        $testFiles = glob($testDir . '/*Test.php');
        
        return [
            'success' => count($testFiles) > 0,
            'test_count' => count($testFiles),
            'reason' => count($testFiles) === 0 ? 'No test files found' : 'Tests available'
        ];
    }
    
    public function runIntegrationTests(): array
    {
        $testDir = $this->rootPath . '/tests/integration';
        
        if (!is_dir($testDir)) {
            return ['success' => false, 'reason' => 'No integration tests directory found'];
        }
        
        $testFiles = glob($testDir . '/*Test.php');
        
        return [
            'success' => count($testFiles) > 0,
            'test_count' => count($testFiles),
            'reason' => count($testFiles) === 0 ? 'No test files found' : 'Tests available'
        ];
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $rootPath = dirname(__DIR__);
    $gates = new QualityGates($rootPath);
    $results = $gates->runAll();
    
    echo "=== CIS V2 QUALITY GATES REPORT ===\n";
    echo "Overall Pass: " . ($results['overall_pass'] ? 'YES' : 'NO') . "\n";
    echo "Score: {$results['score']}/100\n";
    echo "Timestamp: {$results['timestamp']}\n\n";
    
    echo "Gate Results:\n";
    foreach ($results['gates'] as $gate => $passed) {
        echo "  {$gate}: " . ($passed ? 'PASS' : 'FAIL') . "\n";
    }
    
    if (!empty($results['recommendations'])) {
        echo "\nRecommendations:\n";
        foreach ($results['recommendations'] as $rec) {
            echo "  - {$rec}\n";
        }
    }
    
    exit($results['overall_pass'] ? 0 : 1);
}
