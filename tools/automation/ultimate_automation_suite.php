<?php
declare(strict_types=1);

/**
 * Ultimate Automation Suite - Enhanced Web Testing Framework
 * 
 * Comprehensive browser-executable test suite with session management,
 * CSRF handling, route discovery, security validation, and performance monitoring.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T14:45:00Z
 */

require_once __DIR__ . '/../../functions/config.php';
require_once __DIR__ . '/../../app/Shared/Bootstrap.php';
require_once __DIR__ . '/../../app/Tools/BrowserlessTestClient.php';
require_once __DIR__ . '/../../app/Tools/RouteDiscovery.php';

use App\Shared\Bootstrap;
use App\Tools\BrowserlessTestClient;
use App\Tools\RouteDiscovery;
use App\Shared\Logging\Logger;

class UltimateAutomationSuite
{
    private BrowserlessTestClient $client;
    private RouteDiscovery $discovery;
    private Logger $logger;
    private array $config;
    private array $results = [];
    private array $snapshots = [];
    private array $performanceData = [];
    private bool $isAuthenticated = false;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->client = new BrowserlessTestClient($this->logger);
        $this->discovery = new RouteDiscovery($this->logger);
        
        $this->config = [
            'base_url' => $_ENV['APP_URL'] ?? 'https://staff.vapeshed.co.nz',
            'test_user_email' => $_ENV['TEST_USER_EMAIL'] ?? 'admin@ecigdis.co.nz',
            'test_user_password' => $_ENV['TEST_USER_PASSWORD'] ?? 'admin123',
            'automation_enabled' => (bool) ($_ENV['TOOLS_AUTOMATION_ENABLED'] ?? true),
            'use_env_creds' => (bool) ($_ENV['TESTS_USE_ENV_CREDS'] ?? true),
            'performance_runs' => 5,
            'snapshot_dir' => __DIR__ . '/../../var/reports/snapshots',
            'reports_dir' => __DIR__ . '/../../var/reports'
        ];
        
        // Ensure directories exist
        @mkdir($this->config['snapshot_dir'], 0755, true);
        @mkdir($this->config['reports_dir'], 0755, true);
    }
    
    /**
     * Run complete test suite
     */
    public function runFullSuite(): array
    {
        $startTime = microtime(true);
        
        $this->logger->info('Automation suite started', [
            'component' => 'automation_suite',
            'action' => 'suite_start',
            'config' => [
                'base_url' => $this->config['base_url'],
                'auth_enabled' => $this->config['use_env_creds'],
                'automation_enabled' => $this->config['automation_enabled']
            ]
        ]);
        
        if (!$this->config['automation_enabled']) {
            return [
                'success' => false,
                'error' => 'Automation suite disabled via feature flag',
                'results' => []
            ];
        }
        
        try {
            // Phase 1: Route Discovery
            $routes = $this->discovery->discoverRoutes();
            $this->results['route_discovery'] = [
                'total_routes' => count($routes),
                'routes' => $routes
            ];
            
            // Phase 2: Authentication Setup
            if ($this->config['use_env_creds']) {
                $this->runAuthenticationTests();
            }
            
            // Phase 3: Route Testing
            $this->runRouteTests($routes);
            
            // Phase 4: Security Validation
            $this->runSecurityTests();
            
            // Phase 5: Performance Analysis
            $this->runPerformanceTests($routes);
            
            // Phase 6: Snapshot Comparison
            $this->runSnapshotTests($routes);
            
            $duration = microtime(true) - $startTime;
            
            $summary = $this->generateSummary($duration);
            
            // Generate reports
            $this->generateReports($summary);
            
            return [
                'success' => true,
                'summary' => $summary,
                'results' => $this->results
            ];
            
        } catch (\Throwable $e) {
            $this->logger->error('Automation suite failed', [
                'component' => 'automation_suite',
                'action' => 'suite_error',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $this->results
            ];
        }
    }
    
    /**
     * Test authentication flow
     */
    private function runAuthenticationTests(): void
    {
        $this->logger->info('Starting authentication tests', [
            'component' => 'automation_suite',
            'action' => 'auth_test_start'
        ]);
        
        // Test invalid login
        $invalidLogin = $this->client->login('invalid@test.com', 'wrongpassword');
        
        // Test valid login
        $validLogin = $this->client->login(
            $this->config['test_user_email'],
            $this->config['test_user_password']
        );
        
        $this->isAuthenticated = $validLogin['success'];
        
        $this->results['authentication'] = [
            'invalid_login' => [
                'success' => !$invalidLogin['success'],
                'status_code' => $invalidLogin['response']['status_code'] ?? null
            ],
            'valid_login' => [
                'success' => $validLogin['success'],
                'status_code' => $validLogin['response']['status_code'] ?? null,
                'has_session' => !empty($this->client->getCookies())
            ]
        ];
    }
    
    /**
     * Test all discovered routes
     */
    private function runRouteTests(array $routes): void
    {
        $this->logger->info('Starting route tests', [
            'component' => 'automation_suite',
            'action' => 'route_test_start',
            'total_routes' => count($routes)
        ]);
        
        $this->results['route_tests'] = [];
        
        foreach ($routes as $route) {
            if ($route['method'] !== 'GET') {
                continue; // Only test GET routes for now
            }
            
            $testResult = $this->testRoute($route);
            $this->results['route_tests'][] = $testResult;
        }
    }
    
    /**
     * Test individual route
     */
    private function testRoute(array $route): array
    {
        $url = $this->config['base_url'] . $route['path'];
        $response = $this->client->get($url);
        
        $result = [
            'route' => $route,
            'url' => $url,
            'success' => $response['success'],
            'status_code' => $response['status_code'] ?? 0,
            'timing' => $response['timing'] ?? [],
            'size' => $response['timing']['size_download'] ?? 0,
            'assertions' => []
        ];
        
        if ($response['success']) {
            // Basic assertions
            $result['assertions']['valid_status'] = in_array($response['status_code'], [200, 302, 401, 403]);
            $result['assertions']['has_content'] = !empty($response['body']);
            
            // Title assertion for HTML responses
            if (str_contains($response['headers']['Content-Type'] ?? '', 'text/html')) {
                $result['assertions']['has_title'] = $this->client->assertElementExists($response['body'], 'title');
            }
            
            // JSON structure assertion for API responses
            if (str_contains($response['headers']['Content-Type'] ?? '', 'application/json')) {
                $result['assertions']['valid_json'] = json_decode($response['body']) !== null;
            }
            
            // Security headers
            $result['security_headers'] = $this->checkSecurityHeaders($response['headers']);
        }
        
        return $result;
    }
    
    /**
     * Run security validation tests
     */
    private function runSecurityTests(): void
    {
        $this->logger->info('Starting security tests', [
            'component' => 'automation_suite',
            'action' => 'security_test_start'
        ]);
        
        $securityTests = [
            '/' => $this->client->get($this->config['base_url'] . '/'),
            '/login' => $this->client->get($this->config['base_url'] . '/login')
        ];
        
        $this->results['security_tests'] = [];
        
        foreach ($securityTests as $path => $response) {
            if ($response['success']) {
                $this->results['security_tests'][$path] = [
                    'headers' => $this->checkSecurityHeaders($response['headers']),
                    'no_source_leakage' => !str_contains($response['body'], '<?php'),
                    'csrf_protected' => str_contains($response['body'], 'csrf_token') || 
                                      str_contains($response['body'], 'csrf-token')
                ];
            }
        }
    }
    
    /**
     * Run performance tests with multiple runs
     */
    private function runPerformanceTests(array $routes): void
    {
        $this->logger->info('Starting performance tests', [
            'component' => 'automation_suite',
            'action' => 'performance_test_start',
            'runs_per_route' => $this->config['performance_runs']
        ]);
        
        $this->results['performance'] = [];
        
        // Test key routes multiple times
        $keyRoutes = array_filter($routes, fn($r) => 
            $r['method'] === 'GET' && 
            in_array($r['path'], ['/', '/login', '/dashboard', '/_health'])
        );
        
        foreach ($keyRoutes as $route) {
            $times = [];
            $url = $this->config['base_url'] . $route['path'];
            
            for ($i = 0; $i < $this->config['performance_runs']; $i++) {
                $response = $this->client->get($url);
                if ($response['success']) {
                    $times[] = $response['timing']['total'] * 1000; // Convert to ms
                }
            }
            
            if (!empty($times)) {
                sort($times);
                $this->results['performance'][$route['path']] = [
                    'runs' => count($times),
                    'min' => min($times),
                    'max' => max($times),
                    'avg' => array_sum($times) / count($times),
                    'p50' => $times[intval(count($times) * 0.5)],
                    'p95' => $times[intval(count($times) * 0.95)],
                    'times' => $times
                ];
            }
        }
    }
    
    /**
     * Run snapshot comparison tests
     */
    private function runSnapshotTests(array $routes): void
    {
        $this->logger->info('Starting snapshot tests', [
            'component' => 'automation_suite',
            'action' => 'snapshot_test_start'
        ]);
        
        $this->results['snapshots'] = [];
        
        foreach ($routes as $route) {
            if ($route['method'] !== 'GET' || $route['auth_required']) {
                continue; // Only snapshot public GET routes
            }
            
            $url = $this->config['base_url'] . $route['path'];
            $response = $this->client->get($url);
            
            if ($response['success'] && str_contains($response['headers']['Content-Type'] ?? '', 'text/html')) {
                $snapshot = $this->captureSnapshot($route['path'], $response['body']);
                $this->results['snapshots'][$route['path']] = $snapshot;
            }
        }
    }
    
    /**
     * Capture and compare HTML snapshot
     */
    private function captureSnapshot(string $path, string $html): array
    {
        $filename = $this->config['snapshot_dir'] . '/' . str_replace('/', '_', $path) . '.html';
        $hash = hash('sha256', $html);
        
        $snapshot = [
            'path' => $path,
            'filename' => $filename,
            'hash' => $hash,
            'size' => strlen($html),
            'changed' => false
        ];
        
        if (file_exists($filename)) {
            $previousHash = hash('sha256', file_get_contents($filename));
            $snapshot['changed'] = $hash !== $previousHash;
            $snapshot['previous_hash'] = $previousHash;
        }
        
        file_put_contents($filename, $html);
        
        return $snapshot;
    }
    
    /**
     * Check security headers (4 required headers)
     */
    private function checkSecurityHeaders(array $headers): array
    {
        $requiredHeaders = [
            'csp' => isset($headers['Content-Security-Policy']),
            'hsts' => isset($headers['Strict-Transport-Security']),
            'x_frame_options' => isset($headers['X-Frame-Options']),
            'x_content_type_options' => isset($headers['X-Content-Type-Options'])
        ];
        
        $requiredHeaders['all_present'] = array_sum($requiredHeaders) === 4;
        return $requiredHeaders;
    }
    
    /**
     * Generate test summary
     */
    private function generateSummary(float $duration): array
    {
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        
        // Count route tests
        foreach ($this->results['route_tests'] ?? [] as $test) {
            $totalTests++;
            if ($test['success'] && in_array($test['status_code'], [200, 302])) {
                $passedTests++;
            } else {
                $failedTests++;
            }
        }
        
        // Count auth tests
        if (isset($this->results['authentication'])) {
            $totalTests += 2;
            $passedTests += ($this->results['authentication']['invalid_login']['success'] ? 1 : 0);
            $passedTests += ($this->results['authentication']['valid_login']['success'] ? 1 : 0);
            $failedTests += ($this->results['authentication']['invalid_login']['success'] ? 0 : 1);
            $failedTests += ($this->results['authentication']['valid_login']['success'] ? 0 : 1);
        }
        
        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
        
        return [
            'duration' => round($duration, 2),
            'total_tests' => $totalTests,
            'passed' => $passedTests,
            'failed' => $failedTests,
            'pass_rate' => $passRate,
            'routes_discovered' => count($this->results['route_discovery']['routes'] ?? []),
            'snapshots_captured' => count($this->results['snapshots'] ?? []),
            'performance_profiles' => count($this->results['performance'] ?? []),
            'authenticated' => $this->isAuthenticated,
            'timestamp' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ];
    }
    
    /**
     * Generate HTML and JSON reports
     */
    private function generateReports(array $summary): void
    {
        $timestamp = date('Ymd_His');
        
        // JSON Report
        $jsonReport = [
            'summary' => $summary,
            'results' => $this->results,
            'config' => [
                'base_url' => $this->config['base_url'],
                'performance_runs' => $this->config['performance_runs']
            ]
        ];
        
        file_put_contents(
            $this->config['reports_dir'] . "/automation_report_{$timestamp}.json",
            json_encode($jsonReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        // HTML Report
        $htmlReport = $this->generateHtmlReport($summary);
        file_put_contents(
            $this->config['reports_dir'] . "/automation_report_{$timestamp}.html",
            $htmlReport
        );
        
        $this->logger->info('Reports generated', [
            'component' => 'automation_suite',
            'action' => 'reports_generated',
            'json_report' => "automation_report_{$timestamp}.json",
            'html_report' => "automation_report_{$timestamp}.html"
        ]);
    }
    
    /**
     * Generate HTML report
     */
    private function generateHtmlReport(array $summary): string
    {
        $passRateColor = $summary['pass_rate'] >= 90 ? 'success' : ($summary['pass_rate'] >= 70 ? 'warning' : 'danger');
        $authStatus = $this->isAuthenticated ? 'Successful' : 'Failed/Disabled';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS V2 Automation Report - {$summary['timestamp']}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-robot mr-2"></i>CIS V2 Automation Report</h1>
                <p class="text-muted">Generated on {$summary['timestamp']} ({$summary['timezone']})</p>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="card-title text-{$passRateColor}">{$summary['pass_rate']}%</h3>
                        <p class="card-text">Pass Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="card-title">{$summary['total_tests']}</h3>
                        <p class="card-text">Total Tests</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="card-title">{$summary['routes_discovered']}</h3>
                        <p class="card-text">Routes Discovered</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="card-title">{$summary['duration']}s</h3>
                        <p class="card-text">Duration</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line mr-2"></i>Test Results</h5>
                    </div>
                    <div class="card-body">
                        <p>Detailed test results available in JSON format.</p>
                        <p><strong>Authentication:</strong> {$authStatus}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-camera mr-2"></i>Screenshots</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Screenshot capture functionality available in visual testing tools.</p>
                        <a href="/admin/tools/visual-capture" class="btn btn-sm btn-outline-primary">Access Visual Capture Tool</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $suite = new UltimateAutomationSuite();
    $result = $suite->runFullSuite();
    
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit($result['success'] ? 0 : 1);
}

// Web execution
if (isset($_GET['run']) || isset($_POST['run'])) {
    header('Content-Type: application/json');
    
    $suite = new UltimateAutomationSuite();
    $result = $suite->runFullSuite();
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// Web interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS V2 Ultimate Automation Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-robot mr-2"></i>Ultimate Automation Suite</h4>
                    </div>
                    <div class="card-body">
                        <p>Comprehensive test suite with route discovery, authentication testing, security validation, and performance monitoring.</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <button id="runSuite" class="btn btn-primary btn-lg btn-block">
                                    <i class="fas fa-play mr-2"></i>Run Full Suite
                                </button>
                            </div>
                            <div class="col-md-6">
                                <a href="../../var/reports/" class="btn btn-outline-secondary btn-lg btn-block">
                                    <i class="fas fa-folder-open mr-2"></i>View Reports
                                </a>
                            </div>
                        </div>
                        
                        <div id="results" class="mt-4" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Test Results</h5>
                                </div>
                                <div class="card-body">
                                    <pre id="resultOutput"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $('#runSuite').click(function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Running...');
            
            $.ajax({
                url: '?run=1',
                method: 'GET',
                timeout: 120000,
                success: function(data) {
                    $('#resultOutput').text(JSON.stringify(data, null, 2));
                    $('#results').show();
                },
                error: function(xhr, status, error) {
                    $('#resultOutput').text('Error: ' + error);
                    $('#results').show();
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-play mr-2"></i>Run Full Suite');
                }
            });
        });
    </script>
</body>
</html>
    }
    
    public function testConnectivity() {
        $this->log("🌐 TESTING CONNECTIVITY");
        $this->log("======================");
        
        $tests = [];
        
        // Basic connectivity
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        $tests['basic_connection'] = [
            'status' => $httpCode == 200 ? 'PASS' : 'FAIL',
            'http_code' => $httpCode,
            'response_time' => $totalTime,
            'details' => "HTTP $httpCode in {$totalTime}s"
        ];
        
        $this->log("   Basic Connection: {$tests['basic_connection']['status']} ({$tests['basic_connection']['details']})");
        
        // Test specific endpoints
        $endpoints = ['/login', '/api/health', '/dashboard'];
        foreach ($endpoints as $endpoint) {
            $url = $this->baseUrl . $endpoint;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $tests["endpoint_$endpoint"] = [
                'status' => $httpCode < 500 ? 'PASS' : 'FAIL',
                'http_code' => $httpCode,
                'url' => $url
            ];
            
            $this->log("   Endpoint $endpoint: {$tests["endpoint_$endpoint"]['status']} (HTTP $httpCode)");
        }
        
        return $tests;
    }
    
    public function testAuthentication() {
        $this->log("🔑 TESTING AUTHENTICATION");
        $this->log("========================");
        
        $tests = [];
        
        // Test login page access
        $ch = curl_init($this->baseUrl . '/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $tests['login_page_access'] = [
            'status' => $httpCode == 200 ? 'PASS' : 'FAIL',
            'http_code' => $httpCode,
            'has_form' => strpos($response, '<form') !== false,
            'has_csrf' => strpos($response, 'csrf') !== false
        ];
        
        $this->log("   Login Page Access: {$tests['login_page_access']['status']} (HTTP $httpCode)");
        $this->log("   CSRF Token Present: " . ($tests['login_page_access']['has_csrf'] ? 'YES' : 'NO'));
        
        // Test protected route (should redirect)
        $ch = curl_init($this->baseUrl . '/dashboard');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $tests['protected_route'] = [
            'status' => in_array($httpCode, [302, 401, 403]) ? 'PASS' : 'FAIL',
            'http_code' => $httpCode,
            'redirects_properly' => $httpCode == 302
        ];
        
        $this->log("   Protected Route: {$tests['protected_route']['status']} (HTTP $httpCode)");
        
        return $tests;
    }
    
    public function testAllPages() {
        $this->log("📄 TESTING ALL PAGES");
        $this->log("===================");
        
        $pages = ['/', '/login', '/register', '/dashboard', '/profile'];
        $tests = [];
        
        foreach ($pages as $page) {
            $url = $this->baseUrl . $page;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'CIS-Automation-Suite/1.0'
            ]);
            
            $start = microtime(true);
            $response = curl_exec($ch);
            $loadTime = microtime(true) - $start;
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $tests["page_$page"] = [
                'status' => $httpCode < 500 ? 'PASS' : 'FAIL',
                'http_code' => $httpCode,
                'load_time' => round($loadTime, 3),
                'response_size' => strlen($response),
                'has_title' => preg_match('/<title[^>]*>.*<\/title>/i', $response),
                'url' => $url
            ];
            
            $this->log("   Page $page: {$tests["page_$page"]['status']} (HTTP $httpCode, {$tests["page_$page"]['load_time']}s)");
        }
        
        return $tests;
    }
    
    public function testForms() {
        $this->log("📝 TESTING FORMS");
        $this->log("===============");
        
        $tests = [];
        
        // Test login form submission (with invalid credentials)
        $ch = curl_init($this->baseUrl . '/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'email' => 'test@invalid.com',
                'password' => 'wrongpassword'
            ]),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $tests['login_form'] = [
            'status' => in_array($httpCode, [302, 400, 401, 422]) ? 'PASS' : 'FAIL',
            'http_code' => $httpCode,
            'handles_invalid_login' => $httpCode != 500
        ];
        
        $this->log("   Login Form: {$tests['login_form']['status']} (HTTP $httpCode)");
        
        return $tests;
    }
    
    public function testAPI() {
        $this->log("🔌 TESTING API ENDPOINTS");
        $this->log("=======================");
        
        $endpoints = [
            '/api/health' => 'GET',
            '/api/status' => 'GET',
            '/api/version' => 'GET'
        ];
        
        $tests = [];
        
        foreach ($endpoints as $endpoint => $method) {
            $url = $this->baseUrl . $endpoint;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => ['Accept: application/json']
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $jsonResponse = json_decode($response, true);
            
            $tests["api_$endpoint"] = [
                'status' => $httpCode < 500 ? 'PASS' : 'FAIL',
                'http_code' => $httpCode,
                'valid_json' => $jsonResponse !== null,
                'response' => $response
            ];
            
            $this->log("   API $endpoint: {$tests["api_$endpoint"]['status']} (HTTP $httpCode)");
        }
        
        return $tests;
    }
    
    public function testPerformance() {
        $this->log("⚡ TESTING PERFORMANCE");
        $this->log("====================");
        
        $tests = [];
        $urls = [$this->baseUrl, $this->baseUrl . '/login'];
        
        foreach ($urls as $url) {
            $times = [];
            
            // Run 5 tests for average
            for ($i = 0; $i < 5; $i++) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30
                ]);
                
                $start = microtime(true);
                $response = curl_exec($ch);
                $time = microtime(true) - $start;
                $times[] = $time;
                curl_close($ch);
                
                usleep(100000); // 100ms delay between requests
            }
            
            $avgTime = array_sum($times) / count($times);
            $minTime = min($times);
            $maxTime = max($times);
            
            $tests["performance_$url"] = [
                'status' => $avgTime < 2.0 ? 'PASS' : 'FAIL',
                'avg_time' => round($avgTime, 3),
                'min_time' => round($minTime, 3),
                'max_time' => round($maxTime, 3),
                'url' => $url
            ];
            
            $this->log("   Performance $url: {$tests["performance_$url"]['status']} (avg: {$tests["performance_$url"]['avg_time']}s)");
        }
        
        return $tests;
    }
    
    public function testSecurity() {
        $this->log("🔒 TESTING SECURITY");
        $this->log("==================");
        
        $tests = [];
        
        // Test security headers
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $securityHeaders = [
            'X-Frame-Options',
            'X-XSS-Protection',
            'X-Content-Type-Options',
            'Strict-Transport-Security'
        ];
        
        $headerTests = [];
        foreach ($securityHeaders as $header) {
            $headerTests[$header] = stripos($response, $header) !== false;
        }
        
        $tests['security_headers'] = [
            'status' => count(array_filter($headerTests)) >= 2 ? 'PASS' : 'WARN',
            'headers' => $headerTests,
            'score' => count(array_filter($headerTests)) . '/' . count($securityHeaders)
        ];
        
        $this->log("   Security Headers: {$tests['security_headers']['status']} ({$tests['security_headers']['score']})");
        
        return $tests;
    }
    
    public function testDatabase() {
        $this->log("🗄️  TESTING DATABASE");
        $this->log("===================");
        
        $tests = [];
        
        try {
            // Load environment variables
            $envFile = '/var/www/cis.dev.ecigdis.co.nz/public_html/.env';
            if (file_exists($envFile)) {
                $env = parse_ini_file($envFile);
                
                $pdo = new PDO(
                    "mysql:host={$env['DB_HOST']};dbname={$env['DB_DATABASE']}",
                    $env['DB_USERNAME'],
                    $env['DB_PASSWORD']
                );
                
                // Test basic connection
                $stmt = $pdo->query("SELECT 1");
                $tests['connection'] = [
                    'status' => 'PASS',
                    'details' => 'Database connection successful'
                ];
                
                // Count tables
                $stmt = $pdo->query("SHOW TABLES");
                $tableCount = $stmt->rowCount();
                
                $tests['table_count'] = [
                    'status' => $tableCount > 0 ? 'PASS' : 'FAIL',
                    'count' => $tableCount,
                    'details' => "$tableCount tables found"
                ];
                
                $this->log("   Database Connection: {$tests['connection']['status']}");
                $this->log("   Table Count: {$tests['table_count']['status']} ({$tests['table_count']['details']})");
                
            } else {
                $tests['connection'] = [
                    'status' => 'FAIL',
                    'details' => '.env file not found'
                ];
                $this->log("   Database Connection: FAIL (.env file not found)");
            }
            
        } catch (Exception $e) {
            $tests['connection'] = [
                'status' => 'FAIL',
                'details' => $e->getMessage()
            ];
            $this->log("   Database Connection: FAIL ({$e->getMessage()})");
        }
        
        return $tests;
    }
    
    public function generateReport($results) {
        $timestamp = date('Y-m-d H:i:s');
        $reportFile = $this->reportDir . '/automation_report_' . date('Ymd_His') . '.html';
        
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($results as $category => $tests) {
            foreach ($tests as $test) {
                $totalTests++;
                if (isset($test['status']) && $test['status'] === 'PASS') {
                    $passedTests++;
                }
            }
        }
        
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
        
        $html = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>CIS Automation Report - $timestamp</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f5f5f5; padding: 20px; border-radius: 5px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat { background: #fff; border: 1px solid #ddd; padding: 15px; text-align: center; border-radius: 5px; }
        .pass { color: #28a745; }
        .fail { color: #dc3545; }
        .warn { color: #ffc107; }
        .test-section { margin: 20px 0; }
        .test-result { padding: 10px; margin: 5px 0; border-left: 4px solid #ddd; background: #f9f9f9; }
        .test-result.pass { border-left-color: #28a745; }
        .test-result.fail { border-left-color: #dc3545; }
        .test-result.warn { border-left-color: #ffc107; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🚀 CIS Ultimate Automation Report</h1>
        <p><strong>Generated:</strong> $timestamp</p>
        <p><strong>Base URL:</strong> {$this->baseUrl}</p>
    </div>
    
    <div class='summary'>
        <div class='stat'>
            <h3>Total Tests</h3>
            <div style='font-size: 2em; font-weight: bold;'>$totalTests</div>
        </div>
        <div class='stat'>
            <h3>Passed</h3>
            <div style='font-size: 2em; font-weight: bold; color: #28a745;'>$passedTests</div>
        </div>
        <div class='stat'>
            <h3>Success Rate</h3>
            <div style='font-size: 2em; font-weight: bold; color: " . ($successRate >= 80 ? '#28a745' : '#dc3545') . ";'>{$successRate}%</div>
        </div>
    </div>";
        
        foreach ($results as $category => $tests) {
            $html .= "<div class='test-section'>";
            $html .= "<h2>" . ucfirst(str_replace('_', ' ', $category)) . "</h2>";
            
            foreach ($tests as $testName => $test) {
                $status = strtolower($test['status'] ?? 'unknown');
                $html .= "<div class='test-result $status'>";
                $html .= "<strong>" . ucfirst(str_replace('_', ' ', $testName)) . ":</strong> ";
                $html .= "<span class='$status'>" . strtoupper($test['status'] ?? 'UNKNOWN') . "</span>";
                
                if (isset($test['details'])) {
                    $html .= " - " . htmlspecialchars($test['details']);
                }
                
                $html .= "</div>";
            }
            
            $html .= "</div>";
        }
        
        $html .= "</body></html>";
        
        file_put_contents($reportFile, $html);
        $this->log("📊 Report generated: $reportFile");
    }
}

// Run if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "🚀 Starting CIS Ultimate Automation Suite...\n\n";
    
    $suite = new CISAutomationSuite();
    $results = $suite->runFullSuite();
    
    echo "\n✅ Automation suite complete!\n";
    echo "Check the generated report for detailed results.\n";
}
?>
