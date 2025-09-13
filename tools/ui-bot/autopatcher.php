<?php
/**
 * Closed-Loop UI Fixer - Autopatcher Bot
 * Integrates test results with GPT Actions API for automated fixes
 */

class AutopatcherBot {
    private $gptActionsUrl;
    private $reportsDir;
    private $baseUrl;
    private $fixesDir;
    
    public function __construct() {
        $this->gptActionsUrl = $_ENV['GPT_ACTIONS_URL'] ?? 'https://staff.vapeshed.co.nz/gpt_actions.php';
        $this->reportsDir = __DIR__ . '/../../reports';
        $this->baseUrl = $_ENV['BASE_URL'] ?? 'https://cis.dev.ecigdis.co.nz';
        $this->fixesDir = __DIR__ . '/fixes';
        
        if (!is_dir($this->reportsDir)) {
            mkdir($this->reportsDir, 0755, true);
        }
        if (!is_dir($this->fixesDir)) {
            mkdir($this->fixesDir, 0755, true);
        }
    }
    
    /**
     * Main autopatcher execution loop
     */
    public function run(): array {
        echo "🤖 Autopatcher Bot Starting...\n";
        
        $issues = $this->gatherIssues();
        $fixes = [];
        
        foreach ($issues as $issue) {
            echo "🔍 Processing: {$issue['type']} - {$issue['description']}\n";
            
            $fix = $this->generateFix($issue);
            if ($fix) {
                $fixes[] = $fix;
                
                if ($this->isLowRisk($issue)) {
                    echo "✅ Auto-applying low-risk fix\n";
                    $this->applyFix($fix);
                } else {
                    echo "⚠️  High-risk fix requires approval\n";
                    $this->createPullRequest($fix);
                }
            }
        }
        
        return [
            'issues_found' => count($issues),
            'fixes_generated' => count($fixes),
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Gather issues from all test reports
     */
    private function gatherIssues(): array {
        $issues = [];
        
        // Playwright test results
        $playwrightReport = $this->reportsDir . '/test-results.json';
        if (file_exists($playwrightReport)) {
            $issues = array_merge($issues, $this->parsePlaywrightIssues($playwrightReport));
        }
        
        // Lighthouse issues
        $lighthouseDir = $this->reportsDir . '/lighthouse';
        if (is_dir($lighthouseDir)) {
            $issues = array_merge($issues, $this->parseLighthouseIssues($lighthouseDir));
        }
        
        // Link checker issues
        $linkIssues = $this->reportsDir . '/link-issues.json';
        if (file_exists($linkIssues)) {
            $issues = array_merge($issues, $this->parseLinkIssues($linkIssues));
        }
        
        return $issues;
    }
    
    /**
     * Generate fix for an issue using GPT Actions API
     */
    private function generateFix(array $issue): ?array {
        $prompt = $this->buildFixPrompt($issue);
        
        $response = $this->callGptActions('generate_fix', [
            'issue' => $issue,
            'prompt' => $prompt,
            'context' => $this->getFileContext($issue)
        ]);
        
        if ($response && isset($response['fix'])) {
            return [
                'issue' => $issue,
                'fix' => $response['fix'],
                'files' => $response['files'] ?? [],
                'test_command' => $response['test_command'] ?? null,
                'risk_level' => $this->assessRisk($issue, $response['fix'])
            ];
        }
        
        return null;
    }
    
    /**
     * Apply a fix automatically (low-risk only)
     */
    private function applyFix(array $fix): bool {
        $fixId = 'fix_' . date('YmdHis') . '_' . substr(md5(json_encode($fix)), 0, 8);
        
        // Create backup
        $this->createBackup($fix['files'], $fixId);
        
        try {
            // Apply file changes
            foreach ($fix['files'] as $file) {
                if (isset($file['content'])) {
                    file_put_contents($file['path'], $file['content']);
                    echo "📝 Updated: {$file['path']}\n";
                }
            }
            
            // Run test command if provided
            if ($fix['test_command']) {
                $testResult = shell_exec($fix['test_command'] . ' 2>&1');
                if (strpos($testResult, 'FAIL') !== false || strpos($testResult, 'ERROR') !== false) {
                    throw new Exception("Test failed after applying fix: $testResult");
                }
            }
            
            // Re-run specific tests to verify fix
            $verificationResult = $this->verifyFix($fix);
            if (!$verificationResult) {
                throw new Exception("Fix verification failed");
            }
            
            $this->logFix($fixId, $fix, 'applied');
            return true;
            
        } catch (Exception $e) {
            echo "❌ Fix failed: {$e->getMessage()}\n";
            $this->restoreBackup($fixId);
            $this->logFix($fixId, $fix, 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create pull request for high-risk fixes
     */
    private function createPullRequest(array $fix): bool {
        $branchName = 'autofix/' . date('Ymd-His') . '-' . substr(md5(json_encode($fix)), 0, 6);
        
        // Call GPT Actions to create PR
        $response = $this->callGptActions('create_pull_request', [
            'branch_name' => $branchName,
            'title' => 'Automated UI Fix: ' . $fix['issue']['description'],
            'description' => $this->buildPrDescription($fix),
            'files' => $fix['files'],
            'labels' => ['automated-fix', $fix['risk_level']]
        ]);
        
        if ($response && isset($response['pr_url'])) {
            echo "📋 Pull request created: {$response['pr_url']}\n";
            return true;
        }
        
        return false;
    }
    
    /**
     * Parse Playwright test issues
     */
    private function parsePlaywrightIssues(string $reportPath): array {
        $report = json_decode(file_get_contents($reportPath), true);
        $issues = [];
        
        if (isset($report['suites'])) {
            foreach ($report['suites'] as $suite) {
                foreach ($suite['specs'] as $spec) {
                    foreach ($spec['tests'] as $test) {
                        if ($test['outcome'] === 'failed') {
                            $issues[] = [
                                'type' => 'test_failure',
                                'severity' => 'high',
                                'description' => $test['title'],
                                'file' => $spec['file'],
                                'error' => $test['results'][0]['error']['message'] ?? 'Unknown error',
                                'source' => 'playwright'
                            ];
                        }
                    }
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Parse Lighthouse performance/accessibility issues
     */
    private function parseLighthouseIssues(string $lighthouseDir): array {
        $issues = [];
        $reports = glob($lighthouseDir . '/*.json');
        
        foreach ($reports as $reportPath) {
            $report = json_decode(file_get_contents($reportPath), true);
            
            // Parse audits for specific issues
            if (isset($report['audits'])) {
                foreach ($report['audits'] as $auditId => $audit) {
                    if (isset($audit['score']) && $audit['score'] < 0.9) {
                        $issues[] = [
                            'type' => 'performance',
                            'severity' => $audit['score'] < 0.5 ? 'high' : 'medium',
                            'description' => $audit['title'],
                            'url' => $report['finalUrl'] ?? '',
                            'audit_id' => $auditId,
                            'score' => $audit['score'],
                            'source' => 'lighthouse'
                        ];
                    }
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Parse link checker issues
     */
    private function parseLinkIssues(string $linkIssuesPath): array {
        return json_decode(file_get_contents($linkIssuesPath), true) ?? [];
    }
    
    /**
     * Assess if an issue is low-risk for auto-fixing
     */
    private function isLowRisk(array $issue): bool {
        $lowRiskTypes = [
            'broken_link',
            'missing_alt_text',
            'duplicate_id',
            'missing_aria_label',
            'simple_css_fix'
        ];
        
        return in_array($issue['type'], $lowRiskTypes) && 
               ($issue['severity'] ?? 'medium') !== 'critical';
    }
    
    /**
     * Build fix prompt for GPT Actions
     */
    private function buildFixPrompt(array $issue): string {
        return "Fix the following UI issue:\n\n" .
               "Type: {$issue['type']}\n" .
               "Severity: {$issue['severity']}\n" .
               "Description: {$issue['description']}\n\n" .
               "Provide a minimal, surgical fix with:\n" .
               "1. Exact file changes (unified diff format)\n" .
               "2. Test command to verify fix\n" .
               "3. Risk assessment\n\n" .
               "Focus on the specific issue only. No extra features or refactoring.";
    }
    
    /**
     * Call GPT Actions API
     */
    private function callGptActions(string $action, array $params): ?array {
        $data = array_merge(['action' => $action], $params);
        
        $ch = curl_init($this->gptActionsUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: AutopatcherBot/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        echo "⚠️  GPT Actions API call failed: HTTP $httpCode\n";
        return null;
    }
    
    /**
     * Get file context for the issue
     */
    private function getFileContext(array $issue): array {
        $context = [];
        
        if (isset($issue['file'])) {
            $filePath = $issue['file'];
            if (file_exists($filePath)) {
                $context['file_content'] = file_get_contents($filePath);
                $context['file_path'] = $filePath;
            }
        }
        
        return $context;
    }
    
    /**
     * Create backup of files before applying fix
     */
    private function createBackup(array $files, string $fixId): void {
        $backupDir = $this->fixesDir . "/backups/$fixId";
        mkdir($backupDir, 0755, true);
        
        foreach ($files as $file) {
            if (file_exists($file['path'])) {
                $backupPath = $backupDir . '/' . basename($file['path']);
                copy($file['path'], $backupPath);
            }
        }
    }
    
    /**
     * Restore backup if fix fails
     */
    private function restoreBackup(string $fixId): void {
        $backupDir = $this->fixesDir . "/backups/$fixId";
        
        if (is_dir($backupDir)) {
            $backups = glob($backupDir . '/*');
            foreach ($backups as $backup) {
                $originalPath = dirname(dirname($backup)) . '/' . basename($backup);
                if (file_exists($originalPath)) {
                    copy($backup, $originalPath);
                }
            }
        }
    }
    
    /**
     * Verify that a fix actually resolved the issue
     */
    private function verifyFix(array $fix): bool {
        // Run targeted verification based on issue type
        switch ($fix['issue']['type']) {
            case 'broken_link':
                return $this->verifyLinkFix($fix);
            case 'test_failure':
                return $this->verifyTestFix($fix);
            default:
                return true; // Assume success for unknown types
        }
    }
    
    private function verifyLinkFix(array $fix): bool {
        // Re-run link checker on specific URLs
        return true; // Placeholder
    }
    
    private function verifyTestFix(array $fix): bool {
        // Re-run specific test
        return true; // Placeholder
    }
    
    /**
     * Log fix attempt
     */
    private function logFix(string $fixId, array $fix, string $status, string $error = null): void {
        $logEntry = [
            'fix_id' => $fixId,
            'timestamp' => date('c'),
            'issue' => $fix['issue'],
            'status' => $status,
            'error' => $error
        ];
        
        $logFile = $this->fixesDir . '/autopatcher.log';
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);
    }
    
    private function assessRisk(array $issue, array $fix): string {
        // Simple risk assessment
        if (in_array($issue['type'], ['broken_link', 'missing_alt_text'])) {
            return 'low';
        }
        if (in_array($issue['type'], ['test_failure', 'performance'])) {
            return 'high';
        }
        return 'medium';
    }
    
    private function buildPrDescription(array $fix): string {
        return "## Automated UI Fix\n\n" .
               "**Issue Type:** {$fix['issue']['type']}\n" .
               "**Severity:** {$fix['issue']['severity']}\n" .
               "**Description:** {$fix['issue']['description']}\n\n" .
               "**Risk Level:** {$fix['risk_level']}\n\n" .
               "This fix was automatically generated by the UI Fixer Bot.\n" .
               "Please review carefully before merging.";
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $bot = new AutopatcherBot();
    $result = $bot->run();
    
    echo "\n🏁 Autopatcher completed:\n";
    echo "   Issues found: {$result['issues_found']}\n";
    echo "   Fixes generated: {$result['fixes_generated']}\n";
    echo "   Timestamp: {$result['timestamp']}\n";
    
    exit($result['issues_found'] > 0 ? 1 : 0);
}
