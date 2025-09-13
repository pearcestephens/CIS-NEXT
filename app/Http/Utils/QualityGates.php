<?php
declare(strict_types=1);

namespace App\Http\Utils;

/**
 * Quality Gates System
 * Automated quality checks for CSP, A11y, Performance, and Routes
 * 
 * @author CIS Developer Bot
 * @created 2025-09-13
 */
class QualityGates
{
    private array $results = [];
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'base_url' => 'https://staff.vapeshed.co.nz',
            'admin_pages' => [
                '/',
                '/_selftest.php',
                '/bot_order_verification.php',
                '/current_state_analysis.php',
                '/execute_admin_system_completion.php',
                '/final_validation_and_completion.php',
                '/privacy_compliance_demo.php',
                '/quick_service_scan.php',
                '/test_database_connection.php',
                '/validate_migration.php'
            ],
            'performance_targets' => [
                'desktop_performance' => 90,
                'desktop_accessibility' => 95,
                'desktop_best_practices' => 95,
                'desktop_seo' => 100,
                'mobile_performance' => 85,
                'mobile_accessibility' => 95
            ],
            'timeout' => 30,
            'report_path' => 'var/reports/'
        ], $config);
    }
    
    /**
     * Run all quality gates
     */
    public function runAll(): array
    {
        echo "🚀 Running Quality Gates...\n";
        
        $this->results = [
            'timestamp' => date('c'),
            'csp_check' => $this->checkCSP(),
            'routes_check' => $this->checkRoutes(),
            'accessibility_check' => $this->checkAccessibility(),
            'performance_check' => $this->checkPerformance(),
            'summary' => []
        ];
        
        $this->generateSummary();
        $this->saveReport();
        
        return $this->results;
    }
    
    /**
     * Check Content Security Policy compliance
     */
    private function checkCSP(): array
    {
        echo "🔒 Checking CSP compliance...\n";
        
        $cspResults = [
            'has_csp_header' => false,
            'nonce_usage' => [],
            'inline_handlers' => [],
            'violations' => [],
            'score' => 0
        ];
        
        try {
            // Check main admin page for CSP header
            $headers = get_headers($this->config['base_url'] . '/admin', true);
            $cspHeader = null;
            
            foreach ($headers as $key => $value) {
                if (is_string($key) && stripos($key, 'content-security-policy') !== false) {
                    $cspHeader = is_array($value) ? $value[0] : $value;
                    $cspResults['has_csp_header'] = true;
                    break;
                }
            }
            
            if (!$cspHeader) {
                $cspResults['violations'][] = 'Missing Content-Security-Policy header';
            } else {
                echo "   ✅ CSP header present: " . substr($cspHeader, 0, 100) . "...\n";
            }
            
            // Check for nonce usage in admin pages
            foreach (array_slice($this->config['admin_pages'], 0, 3) as $page) {
                $this->checkPageCSP($page, $cspResults);
            }
            
            // Calculate CSP score
            $cspResults['score'] = $this->calculateCSPScore($cspResults);
            
        } catch (\Exception $e) {
            $cspResults['violations'][] = "CSP check failed: " . $e->getMessage();
        }
        
        return $cspResults;
    }
    
    /**
     * Check individual page CSP compliance
     */
    private function checkPageCSP(string $path, array &$results): void
    {
        try {
            $url = $this->config['base_url'] . $path;
            $content = $this->fetchPageContent($url);
            
            if ($content) {
                // Check for nonce usage
                if (preg_match_all('/nonce=["\']([^"\']+)["\']/', $content, $matches)) {
                    $results['nonce_usage'][$path] = count($matches[0]);
                    echo "   ✅ {$path}: " . count($matches[0]) . " nonces found\n";
                } else {
                    $results['violations'][] = "{$path}: No nonces found in scripts";
                    echo "   ❌ {$path}: No nonces found\n";
                }
                
                // Check for inline event handlers
                if (preg_match_all('/\s(onclick|onload|onchange|onsubmit)=/i', $content, $matches)) {
                    $results['inline_handlers'][$path] = $matches[0];
                    $results['violations'][] = "{$path}: " . count($matches[0]) . " inline event handlers found";
                    echo "   ❌ {$path}: " . count($matches[0]) . " inline handlers found\n";
                }
            }
            
        } catch (\Exception $e) {
            $results['violations'][] = "{$path}: Failed to check - " . $e->getMessage();
        }
    }
    
    /**
     * Check all routes return 200 status
     */
    private function checkRoutes(): array
    {
        echo "🔗 Checking route accessibility...\n";
        
        $routeResults = [
            'total_routes' => count($this->config['admin_pages']),
            'successful_routes' => [],
            'failed_routes' => [],
            'response_times' => [],
            'content_types' => [],
            'score' => 0
        ];
        
        foreach ($this->config['admin_pages'] as $route) {
            $this->checkRoute($route, $routeResults);
        }
        
        $routeResults['score'] = $this->calculateRoutesScore($routeResults);
        
        return $routeResults;
    }
    
    /**
     * Check individual route
     */
    private function checkRoute(string $route, array &$results): void
    {
        $url = $this->config['base_url'] . $route;
        $startTime = microtime(true);
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $this->config['timeout'],
                    'method' => 'HEAD',
                    'header' => "User-Agent: CIS-QualityGates/1.0\r\n"
                ]
            ]);
            
            $headers = get_headers($url, true, $context);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            if ($headers && strpos($headers[0], '200') !== false) {
                $results['successful_routes'][] = $route;
                $results['response_times'][$route] = round($responseTime, 2);
                
                // Check content type
                $contentType = 'unknown';
                foreach ($headers as $key => $value) {
                    if (is_string($key) && stripos($key, 'content-type') !== false) {
                        $contentType = is_array($value) ? $value[0] : $value;
                        break;
                    }
                }
                $results['content_types'][$route] = $contentType;
                
                echo "   ✅ {$route}: 200 OK ({$responseTime}ms)\n";
            } else {
                $results['failed_routes'][] = [
                    'route' => $route,
                    'status' => $headers[0] ?? 'No response',
                    'response_time' => $responseTime
                ];
                echo "   ❌ {$route}: " . ($headers[0] ?? 'No response') . "\n";
            }
            
        } catch (\Exception $e) {
            $results['failed_routes'][] = [
                'route' => $route,
                'status' => 'Error: ' . $e->getMessage(),
                'response_time' => (microtime(true) - $startTime) * 1000
            ];
            echo "   ❌ {$route}: Error - " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Run accessibility checks (basic implementation)
     */
    private function checkAccessibility(): array
    {
        echo "♿ Checking accessibility compliance...\n";
        
        $a11yResults = [
            'pages_checked' => 0,
            'issues_found' => [],
            'critical_issues' => 0,
            'warnings' => 0,
            'score' => 0
        ];
        
        // Check a few key admin pages for basic accessibility
        $pagesToCheck = array_slice($this->config['admin_pages'], 0, 5);
        
        foreach ($pagesToCheck as $page) {
            $this->checkPageAccessibility($page, $a11yResults);
        }
        
        $a11yResults['score'] = $this->calculateA11yScore($a11yResults);
        
        return $a11yResults;
    }
    
    /**
     * Check page accessibility (simplified)
     */
    private function checkPageAccessibility(string $path, array &$results): void
    {
        try {
            $url = $this->config['base_url'] . $path;
            $content = $this->fetchPageContent($url);
            
            if (!$content) {
                return;
            }
            
            $results['pages_checked']++;
            $issues = [];
            
            // Check for missing alt attributes on images
            if (preg_match_all('/<img[^>]*(?!alt=)[^>]*>/i', $content, $matches)) {
                $issues[] = [
                    'type' => 'critical',
                    'rule' => 'Images must have alt attributes',
                    'count' => count($matches[0])
                ];
                $results['critical_issues'] += count($matches[0]);
            }
            
            // Check for form inputs without labels
            if (preg_match_all('/<input[^>]*(?!aria-label|id=)[^>]*>/i', $content, $matches)) {
                // This is a simplified check - would need more sophisticated parsing
                $unlabeledInputs = 0;
                foreach ($matches[0] as $input) {
                    if (!preg_match('/type=["\']?(hidden|submit|button)["\']?/i', $input)) {
                        $unlabeledInputs++;
                    }
                }
                
                if ($unlabeledInputs > 0) {
                    $issues[] = [
                        'type' => 'warning',
                        'rule' => 'Form inputs should have associated labels',
                        'count' => $unlabeledInputs
                    ];
                    $results['warnings'] += $unlabeledInputs;
                }
            }
            
            // Check for heading hierarchy
            if (!preg_match('/<h1[^>]*>/i', $content)) {
                $issues[] = [
                    'type' => 'warning',
                    'rule' => 'Page should have an H1 heading',
                    'count' => 1
                ];
                $results['warnings']++;
            }
            
            if (!empty($issues)) {
                $results['issues_found'][$path] = $issues;
                echo "   ⚠️ {$path}: " . count($issues) . " accessibility issues\n";
            } else {
                echo "   ✅ {$path}: No critical accessibility issues\n";
            }
            
        } catch (\Exception $e) {
            echo "   ❌ {$path}: Could not check accessibility - " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Check performance (simplified Lighthouse-style checks)
     */
    private function checkPerformance(): array
    {
        echo "⚡ Checking performance metrics...\n";
        
        $perfResults = [
            'pages_tested' => 0,
            'load_times' => [],
            'resource_counts' => [],
            'content_sizes' => [],
            'performance_scores' => [],
            'avg_load_time' => 0,
            'score' => 0
        ];
        
        // Test a few key pages
        $pagesToTest = array_slice($this->config['admin_pages'], 0, 3);
        
        foreach ($pagesToTest as $page) {
            $this->checkPagePerformance($page, $perfResults);
        }
        
        if (!empty($perfResults['load_times'])) {
            $perfResults['avg_load_time'] = round(array_sum($perfResults['load_times']) / count($perfResults['load_times']), 2);
        }
        
        $perfResults['score'] = $this->calculatePerformanceScore($perfResults);
        
        return $perfResults;
    }
    
    /**
     * Check individual page performance
     */
    private function checkPagePerformance(string $path, array &$results): void
    {
        try {
            $url = $this->config['base_url'] . $path;
            $startTime = microtime(true);
            
            $content = $this->fetchPageContent($url);
            $loadTime = (microtime(true) - $startTime) * 1000;
            
            if ($content) {
                $results['pages_tested']++;
                $results['load_times'][] = $loadTime;
                $results['content_sizes'][$path] = strlen($content);
                
                // Count resources
                $resources = [
                    'css' => preg_match_all('/<link[^>]*\.css[^>]*>/i', $content),
                    'js' => preg_match_all('/<script[^>]*\.js[^>]*>/i', $content),
                    'images' => preg_match_all('/<img[^>]*>/i', $content)
                ];
                $results['resource_counts'][$path] = $resources;
                
                // Simple performance scoring
                $score = 100;
                if ($loadTime > 3000) $score -= 30;
                elseif ($loadTime > 1500) $score -= 15;
                elseif ($loadTime > 700) $score -= 5;
                
                if (strlen($content) > 500000) $score -= 10;
                elseif (strlen($content) > 200000) $score -= 5;
                
                $results['performance_scores'][$path] = $score;
                
                echo "   📊 {$path}: {$loadTime}ms, " . number_format(strlen($content)) . " bytes (Score: {$score})\n";
            }
            
        } catch (\Exception $e) {
            echo "   ❌ {$path}: Performance check failed - " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Fetch page content with timeout
     */
    private function fetchPageContent(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->config['timeout'],
                'user_agent' => 'CIS-QualityGates/1.0'
            ]
        ]);
        
        $result = @file_get_contents($url, false, $context);
        return $result !== false ? $result : null;
    }
    
    /**
     * Calculate scoring methods
     */
    private function calculateCSPScore(array $results): int
    {
        $score = 100;
        
        if (!$results['has_csp_header']) {
            $score -= 50;
        }
        
        $score -= count($results['violations']) * 10;
        $score -= array_sum($results['inline_handlers']) * 5;
        
        return max(0, $score);
    }
    
    private function calculateRoutesScore(array $results): int
    {
        $total = $results['total_routes'];
        $successful = count($results['successful_routes']);
        
        return $total > 0 ? (int) round(($successful / $total) * 100) : 0;
    }
    
    private function calculateA11yScore(array $results): int
    {
        $score = 100;
        $score -= $results['critical_issues'] * 15;
        $score -= $results['warnings'] * 5;
        
        return max(0, $score);
    }
    
    private function calculatePerformanceScore(array $results): int
    {
        if (empty($results['performance_scores'])) {
            return 0;
        }
        
        return (int) round(array_sum($results['performance_scores']) / count($results['performance_scores']));
    }
    
    /**
     * Generate summary with red/amber/green badges
     */
    private function generateSummary(): void
    {
        $summary = [];
        
        // CSP Summary
        $cspScore = $this->results['csp_check']['score'];
        $summary['csp'] = [
            'score' => $cspScore,
            'badge' => $cspScore >= 80 ? '🟢' : ($cspScore >= 60 ? '🟡' : '🔴'),
            'status' => $cspScore >= 80 ? 'PASS' : ($cspScore >= 60 ? 'WARN' : 'FAIL')
        ];
        
        // Routes Summary
        $routesScore = $this->results['routes_check']['score'];
        $summary['routes'] = [
            'score' => $routesScore,
            'badge' => $routesScore >= 95 ? '🟢' : ($routesScore >= 80 ? '🟡' : '🔴'),
            'status' => $routesScore >= 95 ? 'PASS' : ($routesScore >= 80 ? 'WARN' : 'FAIL')
        ];
        
        // Accessibility Summary
        $a11yScore = $this->results['accessibility_check']['score'];
        $summary['accessibility'] = [
            'score' => $a11yScore,
            'badge' => $a11yScore >= 90 ? '🟢' : ($a11yScore >= 70 ? '🟡' : '🔴'),
            'status' => $a11yScore >= 90 ? 'PASS' : ($a11yScore >= 70 ? 'WARN' : 'FAIL')
        ];
        
        // Performance Summary
        $perfScore = $this->results['performance_check']['score'];
        $summary['performance'] = [
            'score' => $perfScore,
            'badge' => $perfScore >= 80 ? '🟢' : ($perfScore >= 60 ? '🟡' : '🔴'),
            'status' => $perfScore >= 80 ? 'PASS' : ($perfScore >= 60 ? 'WARN' : 'FAIL')
        ];
        
        // Overall Summary
        $overallScore = round(($cspScore + $routesScore + $a11yScore + $perfScore) / 4);
        $summary['overall'] = [
            'score' => $overallScore,
            'badge' => $overallScore >= 85 ? '🟢' : ($overallScore >= 70 ? '🟡' : '🔴'),
            'status' => $overallScore >= 85 ? 'PASS' : ($overallScore >= 70 ? 'WARN' : 'FAIL')
        ];
        
        $this->results['summary'] = $summary;
        
        // Print summary to console
        echo "\n📋 Quality Gates Summary:\n";
        foreach ($summary as $gate => $result) {
            echo "   {$result['badge']} " . ucfirst($gate) . ": {$result['score']}/100 ({$result['status']})\n";
        }
    }
    
    /**
     * Save comprehensive report
     */
    private function saveReport(): void
    {
        if (!is_dir($this->config['report_path'])) {
            mkdir($this->config['report_path'], 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        
        // Save JSON report
        $jsonFile = $this->config['report_path'] . "ui_audit_summary_{$timestamp}.json";
        file_put_contents($jsonFile, json_encode($this->results, JSON_PRETTY_PRINT));
        
        // Save Markdown report
        $markdownFile = $this->config['report_path'] . "UI_AUDIT_SUMMARY_{$timestamp}.md";
        file_put_contents($markdownFile, $this->generateMarkdownReport());
        
        echo "\n📄 Audit reports saved:\n";
        echo "   JSON: {$jsonFile}\n";
        echo "   Markdown: {$markdownFile}\n";
    }
    
    /**
     * Generate Markdown report
     */
    private function generateMarkdownReport(): string
    {
        $report = "# UI Audit Summary Report\n\n";
        $report .= "**Generated:** " . date('Y-m-d H:i:s T') . "\n\n";
        
        // Executive Summary
        $summary = $this->results['summary'];
        $report .= "## Executive Summary\n\n";
        $report .= "| Quality Gate | Score | Status | Badge |\n";
        $report .= "|--------------|-------|--------|---------|\n";
        
        foreach ($summary as $gate => $result) {
            $report .= "| " . ucfirst($gate) . " | {$result['score']}/100 | {$result['status']} | {$result['badge']} |\n";
        }
        
        $overallStatus = $summary['overall']['status'];
        $report .= "\n**Overall Status:** {$summary['overall']['badge']} {$overallStatus} ({$summary['overall']['score']}/100)\n\n";
        
        // Detailed Results
        $report .= "## Detailed Results\n\n";
        
        // CSP Results
        $csp = $this->results['csp_check'];
        $report .= "### 🔒 Content Security Policy\n\n";
        $report .= "- **CSP Header Present:** " . ($csp['has_csp_header'] ? '✅ Yes' : '❌ No') . "\n";
        $report .= "- **Violations Found:** " . count($csp['violations']) . "\n";
        $report .= "- **Nonce Usage:** " . array_sum($csp['nonce_usage']) . " total nonces\n\n";
        
        if (!empty($csp['violations'])) {
            $report .= "**CSP Violations:**\n";
            foreach ($csp['violations'] as $violation) {
                $report .= "- ❌ {$violation}\n";
            }
            $report .= "\n";
        }
        
        // Routes Results
        $routes = $this->results['routes_check'];
        $report .= "### 🔗 Route Accessibility\n\n";
        $report .= "- **Total Routes:** {$routes['total_routes']}\n";
        $report .= "- **Successful:** " . count($routes['successful_routes']) . "\n";
        $report .= "- **Failed:** " . count($routes['failed_routes']) . "\n\n";
        
        if (!empty($routes['failed_routes'])) {
            $report .= "**Failed Routes:**\n";
            foreach ($routes['failed_routes'] as $failure) {
                $report .= "- ❌ `{$failure['route']}`: {$failure['status']}\n";
            }
            $report .= "\n";
        }
        
        // Performance Results
        $perf = $this->results['performance_check'];
        $report .= "### ⚡ Performance Metrics\n\n";
        $report .= "- **Pages Tested:** {$perf['pages_tested']}\n";
        $report .= "- **Average Load Time:** {$perf['avg_load_time']}ms\n\n";
        
        if (!empty($perf['load_times'])) {
            $report .= "**Load Time Breakdown:**\n";
            foreach ($this->config['admin_pages'] as $i => $page) {
                if (isset($perf['load_times'][$i])) {
                    $loadTime = $perf['load_times'][$i];
                    $status = $loadTime < 700 ? '🟢' : ($loadTime < 1500 ? '🟡' : '🔴');
                    $report .= "- {$status} `{$page}`: {$loadTime}ms\n";
                }
            }
        }
        
        return $report;
    }
}
