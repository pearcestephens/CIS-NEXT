<?php
declare(strict_types=1);

namespace App\Http\Utils;

/**
 * Prefix Safety Linter
 * Ensures all database table references use PrefixManager::prefix()
 * 
 * @author CIS Developer Bot
 * @created 2025-09-13
 */
class PrefixLinter
{
    private array $results = [];
    private array $config;
    private int $filesScanned = 0;
    private int $violations = 0;
    private int $autoFixed = 0;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'scan_paths' => ['app/', 'tools/', 'migrations/'],
            'extensions' => ['php'],
            'exclude_files' => ['PrefixManager.php', 'Database.php'],
            'exclude_dirs' => ['vendor/', 'node_modules/', 'backups/', 'var/'],
            'auto_fix' => false,
            'report_path' => 'var/reports/',
            'fail_on_violations' => true
        ], $config);
    }
    
    /**
     * Run the linting process
     */
    public function run(): array
    {
        $this->results = [
            'timestamp' => date('c'),
            'scan_summary' => [],
            'violations' => [],
            'auto_fixes' => [],
            'suggestions' => [],
            'stats' => []
        ];
        
        echo "🔍 Starting Prefix Safety Linting...\n";
        
        foreach ($this->config['scan_paths'] as $path) {
            if (is_dir($path)) {
                $this->scanDirectory($path);
            } elseif (file_exists($path)) {
                $this->scanFile($path);
            }
        }
        
        $this->generateStats();
        $this->saveReport();
        
        echo "\n📊 Scan Complete:\n";
        echo "   Files Scanned: {$this->filesScanned}\n";
        echo "   Violations: {$this->violations}\n";
        echo "   Auto-Fixed: {$this->autoFixed}\n";
        
        if ($this->config['fail_on_violations'] && $this->violations > 0) {
            echo "\n❌ LINTING FAILED: {$this->violations} prefix violations found\n";
            exit(1);
        }
        
        echo "\n✅ Prefix linting passed\n";
        return $this->results;
    }
    
    /**
     * Scan directory recursively
     */
    private function scanDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $this->shouldScanFile($file->getPathname())) {
                $this->scanFile($file->getPathname());
            }
        }
    }
    
    /**
     * Check if file should be scanned
     */
    private function shouldScanFile(string $filepath): bool
    {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        
        if (!in_array($extension, $this->config['extensions'])) {
            return false;
        }
        
        $filename = basename($filepath);
        if (in_array($filename, $this->config['exclude_files'])) {
            return false;
        }
        
        foreach ($this->config['exclude_dirs'] as $excludeDir) {
            if (strpos($filepath, $excludeDir) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Scan individual file for violations
     */
    private function scanFile(string $filepath): void
    {
        $this->filesScanned++;
        
        $content = file_get_contents($filepath);
        if (!$content) {
            return;
        }
        
        $lines = explode("\n", $content);
        $violations = [];
        $autoFixes = [];
        
        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;
            
            // Check for raw cis_ table references
            if (preg_match_all('/\b(cis_[a-z0-9_]+)\b/', $line, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $tableName = $match[0];
                    $position = $match[1];
                    
                    // Skip if it's already using PrefixManager
                    if (strpos($line, 'PrefixManager::prefix(') !== false) {
                        continue;
                    }
                    
                    // Skip if it's in a comment
                    if (preg_match('/^\s*(?:\/\/|\*|#)/', $line)) {
                        continue;
                    }
                    
                    $violation = [
                        'type' => 'raw_table_name',
                        'file' => $filepath,
                        'line' => $lineNumber,
                        'column' => $position,
                        'table_name' => $tableName,
                        'context' => trim($line),
                        'severity' => 'error',
                        'message' => "Raw table name '{$tableName}' found. Use PrefixManager::prefix('{$tableName}') instead."
                    ];
                    
                    // Auto-fix if enabled and it's a safe pattern
                    if ($this->config['auto_fix'] && $this->canAutoFix($line, $tableName)) {
                        $fixed = $this->autoFixLine($line, $tableName);
                        if ($fixed !== $line) {
                            $autoFixes[] = [
                                'line' => $lineNumber,
                                'original' => trim($line),
                                'fixed' => trim($fixed),
                                'table_name' => $tableName
                            ];
                            $lines[$lineNum] = $fixed;
                            $this->autoFixed++;
                            continue; // Don't add as violation if auto-fixed
                        }
                    }
                    
                    $violations[] = $violation;
                    $this->violations++;
                }
            }
            
            // Check for bare table names in SQL patterns
            $sqlPatterns = [
                '/FROM\s+([a-z_][a-z0-9_]*)\b/i',
                '/JOIN\s+([a-z_][a-z0-9_]*)\b/i',
                '/INSERT\s+INTO\s+([a-z_][a-z0-9_]*)\b/i',
                '/UPDATE\s+([a-z_][a-z0-9_]*)\b/i',
                '/DELETE\s+FROM\s+([a-z_][a-z0-9_]*)\b/i',
                '/TABLE\s+([a-z_][a-z0-9_]*)\b/i'
            ];
            
            foreach ($sqlPatterns as $pattern) {
                if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[1] as $match) {
                        $tableName = $match[0];
                        $position = $match[1];
                        
                        // Skip system tables and non-CIS tables
                        if (!$this->isCisTable($tableName)) {
                            continue;
                        }
                        
                        // Skip if already using PrefixManager
                        if (strpos($line, 'PrefixManager::prefix(') !== false) {
                            continue;
                        }
                        
                        $violations[] = [
                            'type' => 'bare_table_name',
                            'file' => $filepath,
                            'line' => $lineNumber,
                            'column' => $position,
                            'table_name' => $tableName,
                            'context' => trim($line),
                            'severity' => 'error',
                            'message' => "Bare table name '{$tableName}' found in SQL. Use PrefixManager::prefix('{$tableName}') instead."
                        ];
                        
                        $this->violations++;
                    }
                }
            }
        }
        
        // Save auto-fixes if any were made
        if (!empty($autoFixes) && $this->config['auto_fix']) {
            file_put_contents($filepath, implode("\n", $lines));
            $this->results['auto_fixes'][$filepath] = $autoFixes;
        }
        
        // Store violations for this file
        if (!empty($violations)) {
            $this->results['violations'][$filepath] = $violations;
        }
    }
    
    /**
     * Check if table name appears to be a CIS table
     */
    private function isCisTable(string $tableName): bool
    {
        // Known CIS table patterns
        $cisPatterns = [
            '/^cis_/',
            '/^vend_/',
            '/^ai_/',
            '/^cam_/',
            '/^users$/',
            '/^roles$/',
            '/^permissions$/',
            '/^settings$/',
            '/^sessions$/',
            '/^logs$/',
            '/^jobs$/',
            '/^migrations$/'
        ];
        
        foreach ($cisPatterns as $pattern) {
            if (preg_match($pattern, $tableName)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if line can be auto-fixed safely
     */
    private function canAutoFix(string $line, string $tableName): bool
    {
        // Only auto-fix simple cases in strings and simple SQL
        $safePatterns = [
            '/SELECT.*FROM\s+' . preg_quote($tableName) . '/i',
            '/INSERT\s+INTO\s+' . preg_quote($tableName) . '/i',
            '/"' . preg_quote($tableName) . '"/',
            "/'" . preg_quote($tableName) . "'/"
        ];
        
        foreach ($safePatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Auto-fix a line by replacing table name with PrefixManager call
     */
    private function autoFixLine(string $line, string $tableName): string
    {
        // Replace quoted table names
        $line = str_replace("'{$tableName}'", "PrefixManager::prefix('{$tableName}')", $line);
        $line = str_replace("\"{$tableName}\"", "PrefixManager::prefix('{$tableName}')", $line);
        
        // Replace in common SQL patterns
        $line = preg_replace(
            '/\bFROM\s+' . preg_quote($tableName) . '\b/i',
            'FROM " . PrefixManager::prefix(\'' . $tableName . '\') . "',
            $line
        );
        
        $line = preg_replace(
            '/\bINTO\s+' . preg_quote($tableName) . '\b/i',
            'INTO " . PrefixManager::prefix(\'' . $tableName . '\') . "',
            $line
        );
        
        return $line;
    }
    
    /**
     * Generate statistics
     */
    private function generateStats(): void
    {
        $this->results['stats'] = [
            'files_scanned' => $this->filesScanned,
            'total_violations' => $this->violations,
            'auto_fixes_applied' => $this->autoFixed,
            'files_with_violations' => count($this->results['violations']),
            'violation_types' => [],
            'severity_counts' => ['error' => 0, 'warning' => 0],
            'most_common_tables' => []
        ];
        
        $tableFreq = [];
        
        foreach ($this->results['violations'] as $file => $violations) {
            foreach ($violations as $violation) {
                $type = $violation['type'];
                $severity = $violation['severity'];
                $table = $violation['table_name'];
                
                if (!isset($this->results['stats']['violation_types'][$type])) {
                    $this->results['stats']['violation_types'][$type] = 0;
                }
                $this->results['stats']['violation_types'][$type]++;
                
                $this->results['stats']['severity_counts'][$severity]++;
                
                if (!isset($tableFreq[$table])) {
                    $tableFreq[$table] = 0;
                }
                $tableFreq[$table]++;
            }
        }
        
        arsort($tableFreq);
        $this->results['stats']['most_common_tables'] = array_slice($tableFreq, 0, 10, true);
        
        // Generate suggestions
        $this->generateSuggestions();
    }
    
    /**
     * Generate improvement suggestions
     */
    private function generateSuggestions(): void
    {
        $suggestions = [];
        
        if ($this->violations > 0) {
            $suggestions[] = [
                'type' => 'prefix_manager',
                'priority' => 'high',
                'message' => 'Use PrefixManager::prefix() for all table name references to ensure database prefix compatibility.',
                'example' => 'Replace "cis_users" with PrefixManager::prefix("cis_users")'
            ];
        }
        
        if (count($this->results['stats']['most_common_tables']) > 0) {
            $topTable = array_key_first($this->results['stats']['most_common_tables']);
            $suggestions[] = [
                'type' => 'common_table',
                'priority' => 'medium',
                'message' => "Table '{$topTable}' appears most frequently. Consider creating a helper constant or method.",
                'example' => "const TABLE_{$topTable} = '{$topTable}';"
            ];
        }
        
        if ($this->autoFixed > 0 && !$this->config['auto_fix']) {
            $suggestions[] = [
                'type' => 'auto_fix',
                'priority' => 'low',
                'message' => 'Many violations can be auto-fixed. Run with --auto-fix flag to apply safe corrections automatically.',
                'example' => 'php tools/prefix_linter.php --auto-fix'
            ];
        }
        
        $this->results['suggestions'] = $suggestions;
    }
    
    /**
     * Save report to file
     */
    private function saveReport(): void
    {
        if (!is_dir($this->config['report_path'])) {
            mkdir($this->config['report_path'], 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        
        // Save JSON report
        $jsonFile = $this->config['report_path'] . "prefix_hardening_{$timestamp}.json";
        file_put_contents($jsonFile, json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        // Save Markdown report
        $markdownFile = $this->config['report_path'] . "PREFIX_ENFORCEMENT_REPORT.md";
        file_put_contents($markdownFile, $this->generateMarkdownReport());
        
        echo "\n📄 Reports saved:\n";
        echo "   JSON: {$jsonFile}\n";
        echo "   Markdown: {$markdownFile}\n";
    }
    
    /**
     * Generate Markdown report
     */
    private function generateMarkdownReport(): string
    {
        $report = "# Prefix Enforcement Report\n\n";
        $report .= "**Generated:** " . date('Y-m-d H:i:s T') . "\n";
        $report .= "**Scanner:** CIS Prefix Linter\n\n";
        
        $stats = $this->results['stats'];
        
        $report .= "## Summary\n\n";
        $report .= "| Metric | Count |\n";
        $report .= "|--------|-------|\n";
        $report .= "| Files Scanned | {$stats['files_scanned']} |\n";
        $report .= "| Total Violations | {$stats['total_violations']} |\n";
        $report .= "| Files with Violations | {$stats['files_with_violations']} |\n";
        $report .= "| Auto-Fixes Applied | {$stats['auto_fixes_applied']} |\n";
        
        if ($stats['total_violations'] === 0) {
            $report .= "\n✅ **All prefix checks passed!** No violations found.\n";
        } else {
            $report .= "\n❌ **{$stats['total_violations']} violations found** that need attention.\n";
        }
        
        // Violation breakdown
        if (!empty($this->results['violations'])) {
            $report .= "\n## Violations by File\n\n";
            
            foreach ($this->results['violations'] as $file => $violations) {
                $report .= "### `{$file}`\n\n";
                $report .= "| Line | Type | Table | Message |\n";
                $report .= "|------|------|-------|----------|\n";
                
                foreach ($violations as $violation) {
                    $report .= "| {$violation['line']} | {$violation['type']} | `{$violation['table_name']}` | {$violation['message']} |\n";
                }
                
                $report .= "\n";
            }
        }
        
        // Auto-fixes
        if (!empty($this->results['auto_fixes'])) {
            $report .= "## Auto-Fixes Applied\n\n";
            
            foreach ($this->results['auto_fixes'] as $file => $fixes) {
                $report .= "### `{$file}`\n\n";
                
                foreach ($fixes as $fix) {
                    $report .= "**Line {$fix['line']}:** Fixed `{$fix['table_name']}`\n";
                    $report .= "```diff\n";
                    $report .= "- {$fix['original']}\n";
                    $report .= "+ {$fix['fixed']}\n";
                    $report .= "```\n\n";
                }
            }
        }
        
        // Suggestions
        if (!empty($this->results['suggestions'])) {
            $report .= "## Recommendations\n\n";
            
            foreach ($this->results['suggestions'] as $suggestion) {
                $priority = strtoupper($suggestion['priority']);
                $report .= "### {$priority}: {$suggestion['message']}\n\n";
                
                if (!empty($suggestion['example'])) {
                    $report .= "**Example:**\n";
                    $report .= "```php\n";
                    $report .= "{$suggestion['example']}\n";
                    $report .= "```\n\n";
                }
            }
        }
        
        $report .= "## Most Common Tables\n\n";
        if (!empty($stats['most_common_tables'])) {
            $report .= "| Table | Violations |\n";
            $report .= "|-------|------------|\n";
            
            foreach ($stats['most_common_tables'] as $table => $count) {
                $report .= "| `{$table}` | {$count} |\n";
            }
        } else {
            $report .= "No violations found.\n";
        }
        
        return $report;
    }
}
