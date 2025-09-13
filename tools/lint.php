<?php
/**
 * PHP Lint Tool - Syntax validation for STRICT VALIDATION ORDER
 * File: tools/lint.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Comprehensive PHP syntax checking and validation
 */

class LintTool {
    private array $php_files = [];
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    
    public function __construct() {
        $this->php_files = [
            // AI Integration files
            'app/Integrations/OpenAI/Client.php',
            'app/Integrations/Claude/Client.php',
            'app/Shared/AI/Orchestrator.php',
            'app/Shared/AI/Events.php',
            'migrations/012_create_ai_integrations.php',
            'config/ai.php',
            
            // Business Integration files
            'app/Integrations/Vend/Client.php',
            'app/Integrations/Deputy/Client.php',
            'app/Integrations/Xero/Client.php',
            'app/Shared/Sec/Secrets.php',
            'migrations/013_create_integration_secrets.php',
            
            // Controllers
            'app/Http/Controllers/AIAdminController.php',
            'app/Http/Controllers/IntegrationController.php',
            
            // Tools
            'tools/ai_verification.php',
            'tools/file_check.php',
            'tools/lint.php'
        ];
    }
    
    public function lint(): array {
        $timestamp = date('c');
        $results = [
            'timestamp' => $timestamp,
            'validation_type' => 'PHP_SYNTAX_CHECK',
            'total_files' => count($this->php_files),
            'passed' => 0,
            'failed' => 0,
            'files' => []
        ];
        
        foreach ($this->php_files as $file) {
            $file_result = $this->lintFile($file);
            $results['files'][] = $file_result;
            
            if ($file_result['syntax_valid']) {
                $results['passed']++;
            } else {
                $results['failed']++;
            }
        }
        
        $results['success_percentage'] = round(($results['passed'] / $results['total_files']) * 100, 2);
        $results['overall_status'] = $results['failed'] === 0 ? 'ALL_PASSED' : 'SOME_FAILED';
        
        return $results;
    }
    
    private function lintFile(string $file): array {
        $full_path = __DIR__ . '/../' . $file;
        
        $result = [
            'file' => $file,
            'full_path' => $full_path,
            'exists' => false,
            'syntax_valid' => false,
            'error' => null,
            'size_bytes' => 0,
            'line_count' => 0
        ];
        
        // Check if file exists
        if (!file_exists($full_path)) {
            $result['error'] = 'File does not exist';
            return $result;
        }
        
        $result['exists'] = true;
        $result['size_bytes'] = filesize($full_path);
        
        // Count lines
        $content = file_get_contents($full_path);
        $result['line_count'] = substr_count($content, "\n") + 1;
        
        // PHP syntax check
        $output = [];
        $return_code = 0;
        
        // Use php -l to check syntax
        exec("php -l " . escapeshellarg($full_path) . " 2>&1", $output, $return_code);
        
        if ($return_code === 0) {
            $result['syntax_valid'] = true;
        } else {
            $result['syntax_valid'] = false;
            $result['error'] = implode("\n", $output);
        }
        
        // Additional checks for PHP files
        if ($result['syntax_valid'] && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $additional_checks = $this->performAdditionalChecks($full_path, $content);
            $result = array_merge($result, $additional_checks);
        }
        
        return $result;
    }
    
    private function performAdditionalChecks(string $full_path, string $content): array {
        $checks = [
            'has_php_open_tag' => false,
            'has_namespace' => false,
            'has_class_or_function' => false,
            'potential_security_issues' => [],
            'warnings' => []
        ];
        
        // Check for PHP open tag
        if (strpos($content, '<?php') !== false) {
            $checks['has_php_open_tag'] = true;
        }
        
        // Check for namespace
        if (preg_match('/^namespace\s+[A-Za-z_\\\\][A-Za-z0-9_\\\\]*;/m', $content)) {
            $checks['has_namespace'] = true;
        }
        
        // Check for class or function
        if (preg_match('/(class|function|interface|trait)\s+[A-Za-z_][A-Za-z0-9_]*/m', $content)) {
            $checks['has_class_or_function'] = true;
        }
        
        // Security checks
        $security_patterns = [
            'eval(' => 'Use of eval() function',
            'exec(' => 'Use of exec() function without validation',
            'system(' => 'Use of system() function',
            'shell_exec(' => 'Use of shell_exec() function',
            'passthru(' => 'Use of passthru() function',
            '$_GET[' => 'Direct $_GET usage without validation',
            '$_POST[' => 'Direct $_POST usage without validation',
            'mysql_query(' => 'Use of deprecated mysql_query()',
            'md5(' => 'Use of weak MD5 hashing'
        ];
        
        foreach ($security_patterns as $pattern => $issue) {
            if (strpos($content, $pattern) !== false) {
                $checks['potential_security_issues'][] = $issue;
            }
        }
        
        // Additional warnings
        if (strpos($content, 'TODO') !== false || strpos($content, 'FIXME') !== false) {
            $checks['warnings'][] = 'Contains TODO or FIXME comments';
        }
        
        if (strpos($content, 'var_dump(') !== false || strpos($content, 'print_r(') !== false) {
            $checks['warnings'][] = 'Contains debug functions (var_dump/print_r)';
        }
        
        return $checks;
    }
    
    public function outputJSON(): void {
        header('Content-Type: application/json');
        echo json_encode($this->lint(), JSON_PRETTY_PRINT);
    }
    
    public function outputText(): void {
        $results = $this->lint();
        
        echo "PHP LINT VALIDATION RESULTS\n";
        echo "===========================\n";
        echo "Timestamp: {$results['timestamp']}\n";
        echo "Files checked: {$results['total_files']}\n";
        echo "Passed: {$results['passed']}\n";
        echo "Failed: {$results['failed']}\n";
        echo "Success rate: {$results['success_percentage']}%\n";
        echo "Overall status: {$results['overall_status']}\n\n";
        
        foreach ($results['files'] as $file) {
            $status = $file['syntax_valid'] ? '✅ PASS' : '❌ FAIL';
            echo "{$status} - {$file['file']} ({$file['size_bytes']} bytes, {$file['line_count']} lines)\n";
            
            if (!$file['exists']) {
                echo "   ERROR: File does not exist\n";
            } elseif (!$file['syntax_valid']) {
                echo "   ERROR: {$file['error']}\n";
            } else {
                // Show additional check results
                if (isset($file['potential_security_issues']) && !empty($file['potential_security_issues'])) {
                    echo "   ⚠️  Security concerns: " . implode(', ', $file['potential_security_issues']) . "\n";
                }
                if (isset($file['warnings']) && !empty($file['warnings'])) {
                    echo "   ⚠️  Warnings: " . implode(', ', $file['warnings']) . "\n";
                }
            }
        }
        
        echo "\n";
        if ($results['failed'] === 0) {
            echo "🎉 ALL FILES PASSED SYNTAX VALIDATION\n";
        } else {
            echo "💥 {$results['failed']} FILES FAILED SYNTAX VALIDATION\n";
        }
    }
}

// Legacy Linter class for backward compatibility
declare(strict_types=1);

namespace CIS\Tools;

class Linter
{
    private array $violations = [];
    private int $totalFiles = 0;
    
    public function lintDirectory(string $directory): array
    {
        $this->violations = [];
        $this->totalFiles = 0;
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $this->lintFile($file->getRealPath());
                $this->totalFiles++;
            }
        }
        
        return [
            'total_files' => $this->totalFiles,
            'total_violations' => count($this->violations),
            'violations' => $this->violations,
            'success' => empty($this->violations)
        ];
    }
    
    private function lintFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $relativePath = str_replace(dirname(__DIR__) . '/', '', $filePath);
        
        // Check opening tag
        if (!str_starts_with($content, '<?php')) {
            $this->addViolation($relativePath, 1, 'Must start with <?php tag');
        }
        
        // Check strict types declaration
        if (!str_contains($content, 'declare(strict_types=1)')) {
            $this->addViolation($relativePath, 2, 'Missing declare(strict_types=1)');
        }
        
        // Check namespace for app files
        if (str_contains($filePath, '/app/') && !preg_match('/^namespace\s+App\\\\/', $content)) {
            $this->addViolation($relativePath, 3, 'Missing or invalid namespace declaration');
        }
        
        foreach ($lines as $lineNum => $line) {
            $lineNum++; // 1-indexed
            
            // Check line length (soft limit 120 chars)
            if (strlen($line) > 120) {
                $this->addViolation($relativePath, $lineNum, 'Line exceeds 120 characters');
            }
            
            // Check trailing whitespace
            if (rtrim($line) !== $line) {
                $this->addViolation($relativePath, $lineNum, 'Trailing whitespace found');
            }
            
            // Check function/method naming
            if (preg_match('/function\s+([A-Z][a-zA-Z0-9_]*)\s*\(/', $line, $matches)) {
                $this->addViolation($relativePath, $lineNum, "Function '{$matches[1]}' should be camelCase");
            }
            
            // Check class naming
            if (preg_match('/class\s+([a-z][a-zA-Z0-9_]*)\s/', $line, $matches)) {
                $this->addViolation($relativePath, $lineNum, "Class '{$matches[1]}' should be PascalCase");
            }
        }
    }
    
    private function addViolation(string $file, int $line, string $message): void
    {
        $this->violations[] = [
            'file' => $file,
            'line' => $line,
            'message' => $message,
            'severity' => 'warning'
        ];
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $directory = $argv[1] ?? dirname(__DIR__) . '/app';
    $linter = new Linter();
    $results = $linter->lintDirectory($directory);
    
    echo "=== CIS V2 PSR-12 LINTER REPORT ===\n";
    echo "Files checked: {$results['total_files']}\n";
    echo "Violations found: {$results['total_violations']}\n";
    echo "Status: " . ($results['success'] ? 'PASS' : 'FAIL') . "\n\n";
    
    if (!empty($results['violations'])) {
        echo "Violations:\n";
        foreach ($results['violations'] as $violation) {
            echo "  {$violation['file']}:{$violation['line']} - {$violation['message']}\n";
        }
    }
    
    exit($results['success'] ? 0 : 1);
}
