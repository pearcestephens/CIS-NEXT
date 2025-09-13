<?php
/**
 * SR-12 Lint Tool
 * File: tools/lint.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Repo-wide PHP syntax scan using token_get_all
 */

require_once __DIR__ . '/../functions/config.php';

class PHPLintTool {
    
    private array $results = [
        'ok' => false,
        'timestamp' => '',
        'files_scanned' => 0,
        'syntax_errors' => [],
        'warnings' => [],
        'statistics' => []
    ];
    
    private array $excludePatterns = [
        'vendor/*',
        'node_modules/*',
        'var/cache/*',
        'var/logs/*',
        'backups/*',
        '.git/*',
        '*.min.js',
        '*.min.css'
    ];
    
    public function __construct() {
        $this->results['timestamp'] = date('c');
    }
    
    /**
     * Run comprehensive lint scan
     */
    public function runLintScan(): array {
        $startTime = microtime(true);
        
        try {
            $this->scanDirectory(__DIR__ . '/..');
            $this->generateStatistics();
            $this->results['ok'] = empty($this->results['syntax_errors']);
            $this->results['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            
        } catch (Exception $e) {
            $this->results['ok'] = false;
            $this->results['error'] = $e->getMessage();
        }
        
        return $this->results;
    }
    
    /**
     * Scan directory recursively
     */
    private function scanDirectory(string $directory): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $relativePath = substr($file->getPathname(), strlen($directory) + 1);
            
            // Skip excluded patterns
            if ($this->shouldExcludeFile($relativePath)) {
                continue;
            }
            
            // Only scan PHP files
            if ($file->getExtension() === 'php') {
                $this->lintFile($file->getPathname(), $relativePath);
                $this->results['files_scanned']++;
            }
        }
    }
    
    /**
     * Check if file should be excluded
     */
    private function shouldExcludeFile(string $relativePath): bool {
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Lint individual PHP file
     */
    private function lintFile(string $filePath, string $relativePath): void {
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            $this->addWarning($relativePath, 'Cannot read file');
            return;
        }
        
        // Basic syntax check using php -l equivalent
        $this->checkSyntax($filePath, $relativePath, $content);
        
        // Token analysis
        $this->analyzeTokens($filePath, $relativePath, $content);
    }
    
    /**
     * Check PHP syntax
     */
    private function checkSyntax(string $filePath, string $relativePath, string $content): void {
        // Capture PHP syntax errors
        $output = [];
        $returnCode = 0;
        
        // Create temporary file for syntax check
        $tempFile = tempnam(sys_get_temp_dir(), 'php_lint_');
        file_put_contents($tempFile, $content);
        
        exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $returnCode);
        
        unlink($tempFile);
        
        if ($returnCode !== 0) {
            $errorMessage = implode("\n", $output);
            // Clean up the error message to show relative path
            $errorMessage = str_replace($tempFile, $relativePath, $errorMessage);
            
            $this->addSyntaxError($relativePath, $errorMessage, 0);
        }
    }
    
    /**
     * Analyze PHP tokens for potential issues
     */
    private function analyzeTokens(string $filePath, string $relativePath, string $content): void {
        // Suppress warnings for token_get_all on invalid syntax
        $tokens = @token_get_all($content);
        
        if ($tokens === false) {
            $this->addWarning($relativePath, 'Cannot tokenize file - likely syntax error');
            return;
        }
        
        $this->checkTokenPatterns($relativePath, $tokens);
    }
    
    /**
     * Check for problematic token patterns
     */
    private function checkTokenPatterns(string $relativePath, array $tokens): void {
        $lineNumber = 1;
        $inString = false;
        $stringDelimiter = null;
        
        foreach ($tokens as $i => $token) {
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenValue = $token[1];
                $tokenLine = $token[2];
                
                // Update line number
                $lineNumber = $tokenLine;
                
                // Check for potential security issues
                $this->checkSecurityPatterns($relativePath, $tokenType, $tokenValue, $lineNumber);
                
                // Check for code quality issues
                $this->checkQualityPatterns($relativePath, $tokenType, $tokenValue, $lineNumber, $tokens, $i);
                
            } else {
                // Single character token
                if ($token === "\n") {
                    $lineNumber++;
                }
            }
        }
    }
    
    /**
     * Check for security-related patterns
     */
    private function checkSecurityPatterns(string $relativePath, int $tokenType, string $tokenValue, int $lineNumber): void {
        // Check for eval usage
        if ($tokenType === T_EVAL) {
            $this->addWarning($relativePath, "Use of eval() detected", $lineNumber);
        }
        
        // Check for potential SQL injection patterns
        if ($tokenType === T_STRING && in_array(strtolower($tokenValue), ['mysql_query', 'mysqli_query'])) {
            $this->addWarning($relativePath, "Direct SQL query function usage - consider prepared statements", $lineNumber);
        }
        
        // Check for file inclusion with variables
        if ($tokenType === T_INCLUDE || $tokenType === T_REQUIRE || 
            $tokenType === T_INCLUDE_ONCE || $tokenType === T_REQUIRE_ONCE) {
            // This would need more complex analysis to be useful
        }
        
        // Check for potential XSS patterns
        if ($tokenType === T_ECHO || $tokenType === T_PRINT) {
            // This would need more complex analysis to be useful
        }
    }
    
    /**
     * Check for code quality patterns
     */
    private function checkQualityPatterns(string $relativePath, int $tokenType, string $tokenValue, int $lineNumber, array $tokens, int $index): void {
        // Check for TODO/FIXME comments
        if ($tokenType === T_COMMENT || $tokenType === T_DOC_COMMENT) {
            if (preg_match('/\b(TODO|FIXME|HACK|XXX)\b/i', $tokenValue)) {
                $this->addWarning($relativePath, "TODO/FIXME comment found", $lineNumber);
            }
        }
        
        // Check for var_dump, print_r in production code
        if ($tokenType === T_STRING && in_array(strtolower($tokenValue), ['var_dump', 'print_r', 'var_export'])) {
            $this->addWarning($relativePath, "Debug function '{$tokenValue}' found - should be removed in production", $lineNumber);
        }
        
        // Check for short PHP tags
        if ($tokenType === T_OPEN_TAG && $tokenValue === '<?') {
            $this->addWarning($relativePath, "Short PHP tag '<?>' used - consider using '<?php'", $lineNumber);
        }
        
        // Check for missing semicolons (this is complex, simplified check)
        if ($tokenType === T_STRING && isset($tokens[$index + 1])) {
            $nextToken = $tokens[$index + 1];
            if (is_array($nextToken) && $nextToken[0] === T_WHITESPACE) {
                // More complex analysis would be needed for accurate semicolon checking
            }
        }
    }
    
    /**
     * Add syntax error
     */
    private function addSyntaxError(string $file, string $message, int $line): void {
        $this->results['syntax_errors'][] = [
            'file' => $file,
            'message' => $message,
            'line' => $line,
            'severity' => 'error'
        ];
    }
    
    /**
     * Add warning
     */
    private function addWarning(string $file, string $message, int $line = 0): void {
        $this->results['warnings'][] = [
            'file' => $file,
            'message' => $message,
            'line' => $line,
            'severity' => 'warning'
        ];
    }
    
    /**
     * Generate statistics
     */
    private function generateStatistics(): void {
        $this->results['statistics'] = [
            'total_files' => $this->results['files_scanned'],
            'error_count' => count($this->results['syntax_errors']),
            'warning_count' => count($this->results['warnings']),
            'clean_files' => $this->results['files_scanned'] - count($this->getFilesWithIssues()),
            'files_with_issues' => count($this->getFilesWithIssues()),
        ];
        
        // Calculate quality score
        $totalIssues = $this->results['statistics']['error_count'] + $this->results['statistics']['warning_count'];
        $this->results['statistics']['quality_score'] = $this->results['files_scanned'] > 0 ? 
            round((1 - ($totalIssues / max($this->results['files_scanned'], 1))) * 100, 1) : 100;
    }
    
    /**
     * Get files with issues
     */
    private function getFilesWithIssues(): array {
        $filesWithIssues = [];
        
        foreach ($this->results['syntax_errors'] as $error) {
            $filesWithIssues[$error['file']] = true;
        }
        
        foreach ($this->results['warnings'] as $warning) {
            $filesWithIssues[$warning['file']] = true;
        }
        
        return array_keys($filesWithIssues);
    }
    
    /**
     * Get summary report
     */
    public function getSummaryReport(): array {
        return [
            'ok' => $this->results['ok'],
            'files_scanned' => $this->results['files_scanned'],
            'syntax_errors' => count($this->results['syntax_errors']),
            'warnings' => count($this->results['warnings']),
            'quality_score' => $this->results['statistics']['quality_score'] ?? 0,
            'message' => $this->generateSummaryMessage()
        ];
    }
    
    /**
     * Generate summary message
     */
    private function generateSummaryMessage(): string {
        $errorCount = count($this->results['syntax_errors']);
        $warningCount = count($this->results['warnings']);
        
        if ($errorCount === 0 && $warningCount === 0) {
            return "All {$this->results['files_scanned']} PHP files passed lint check";
        } elseif ($errorCount === 0) {
            return "{$warningCount} warnings found in {$this->results['files_scanned']} PHP files";
        } else {
            return "{$errorCount} syntax errors and {$warningCount} warnings found";
        }
    }
}

// CLI interface
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'scan';
    
    switch ($action) {
        case 'scan':
            echo "Running PHP lint scan...\n";
            $linter = new PHPLintTool();
            $result = $linter->runLintScan();
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            exit($result['ok'] ? 0 : 1);
            
        case 'summary':
            $linter = new PHPLintTool();
            $linter->runLintScan();
            $summary = $linter->getSummaryReport();
            echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
            break;
            
        default:
            echo "Usage: php lint.php [scan|summary]\n";
            exit(1);
    }
}

// Web interface
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    
    $linter = new PHPLintTool();
    $result = $linter->runLintScan();
    
    http_response_code($result['ok'] ? 200 : 400);
    echo json_encode($result, JSON_PRETTY_PRINT);
}
