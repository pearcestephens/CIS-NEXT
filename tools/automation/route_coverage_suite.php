<?php
declare(strict_types=1);

/**
 * Route Coverage, Performance & Security Test Suite
 * 
 * Tests discovered routes for coverage, measures performance metrics,
 * and validates security headers across the application.
 */

require_once __DIR__ . '/../../functions/config.php';
require_once __DIR__ . '/../../app/Shared/Bootstrap.php';
require_once __DIR__ . '/../../app/Tools/BrowserlessTestClient.php';
require_once __DIR__ . '/../../app/Tools/RouteDiscovery.php';

use App\Shared\Bootstrap;
use App\Tools\BrowserlessTestClient;
use App\Tools\RouteDiscovery;
use App\Shared\Logging\Logger;

class RoutePerformanceSecuritySuite
{
    private BrowserlessTestClient $client;
    private RouteDiscovery $discovery;
    private Logger $logger;
    private array $config;
    private array $results = [];
    private array $performanceData = [];
    private array $securityResults = [];

    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->client = new BrowserlessTestClient($this->logger);
        $this->discovery = new RouteDiscovery($this->logger);
        
        $this->config = [
            'base_url' => $this->detectBaseUrl(),
            'performance_runs' => 5,
            'coverage_target' => 95, // 95% coverage target
            'performance_targets' => [
                '/_health' => 800,  // p95 < 800ms
                '/login' => 2000,   // p95 < 2000ms
                '/' => 1500,        // p95 < 1500ms
                '/dashboard' => 2000
            ],
            'required_security_headers' => [
                'Content-Security-Policy',
                'Strict-Transport-Security', 
                'X-Frame-Options',
                'X-Content-Type-Options'
            ]
        ];
    }

    /**
     * Detect the correct base URL for testing
     */
    private function detectBaseUrl(): string
    {
        $possibleUrls = [
            'https://staff.vapeshed.co.nz',
            'https://cis.dev.ecigdis.co.nz',
            'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        ];

        foreach ($possibleUrls as $url) {
            $testUrl = $url . '/_health';
            $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
            $response = @file_get_contents($testUrl, false, $context);
            
            if ($response !== false && str_contains($response, '"ok":true')) {
                return $url;
            }
        }

        return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /**
     * Run complete route coverage, performance and security test suite
     */
    public function runCompleteSuite(): array
    {
        $startTime = microtime(true);
        
        $this->logger->info('Route coverage, performance and security tests started', [
            'component' => 'route_perf_security',
            'action' => 'suite_start',
            'base_url' => $this->config['base_url']
        ]);

        try {
            // Phase 1: Route Discovery
            $routes = $this->discovery->discoverRoutes();
            
            // Phase 2: Route Coverage Testing
            $this->testRouteCoverage($routes);
            
            // Phase 3: Performance Testing
            $this->testPerformance($routes);
            
            // Phase 4: Security Headers Validation
            $this->testSecurityHeaders($routes);
            
            $duration = microtime(true) - $startTime;
            
            $summary = $this->generateSummary($routes, $duration);

            return [
                'success' => true,
                'summary' => $summary,
                'route_coverage' => $this->results,
                'performance' => $this->performanceData,
                'security_headers' => $this->securityResults
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Route coverage tests failed', [
                'component' => 'route_perf_security',
                'action' => 'suite_error',
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'partial_results' => [
                    'coverage' => $this->results,
                    'performance' => $this->performanceData,
                    'security' => $this->securityResults
                ]
            ];
        }
    }

    /**
     * Test route coverage - test all discoverable GET routes
     */
    private function testRouteCoverage(array $routes): void
    {
        $this->logger->info('Testing route coverage', [
            'component' => 'route_perf_security',
            'action' => 'coverage_test_start',
            'total_routes' => count($routes)
        ]);

        $testableRoutes = array_filter($routes, function($route) {
            // Only test GET routes for coverage
            return $route['method'] === 'GET';
        });

        $testedCount = 0;
        $passedCount = 0;
        $untestableRoutes = [];

        foreach ($testableRoutes as $route) {
            $url = $this->config['base_url'] . $route['path'];
            
            // Skip routes that require complex setup or are inherently untestable
            if ($this->isUntestableRoute($route)) {
                $untestableRoutes[] = [
                    'route' => $route,
                    'reason' => $this->getUntestableReason($route)
                ];
                continue;
            }
            
            $testedCount++;
            $result = $this->testSingleRoute($route, $url);
            
            if ($result['pass']) {
                $passedCount++;
            }
            
            $this->results[] = $result;
        }

        $this->results['coverage_summary'] = [
            'total_routes' => count($routes),
            'testable_routes' => count($testableRoutes),
            'tested_routes' => $testedCount,
            'passed_routes' => $passedCount,
            'untestable_routes' => $untestableRoutes,
            'coverage_percentage' => count($testableRoutes) > 0 ? 
                round(($testedCount / count($testableRoutes)) * 100, 2) : 0,
            'pass_percentage' => $testedCount > 0 ? 
                round(($passedCount / $testedCount) * 100, 2) : 0
        ];
    }

    /**
     * Test performance of key routes with multiple runs
     */
    private function testPerformance(array $routes): void
    {
        $this->logger->info('Testing performance metrics', [
            'component' => 'route_perf_security',
            'action' => 'performance_test_start',
            'runs_per_route' => $this->config['performance_runs']
        ]);

        // Test key routes defined in performance targets
        $keyRoutes = array_keys($this->config['performance_targets']);
        
        foreach ($keyRoutes as $routePath) {
            $url = $this->config['base_url'] . $routePath;
            $times = [];
            
            // Run multiple times to get reliable metrics
            for ($i = 0; $i < $this->config['performance_runs']; $i++) {
                $response = $this->client->get($url);
                
                if ($response['success'] && isset($response['timing']['total'])) {
                    $times[] = $response['timing']['total'] * 1000; // Convert to ms
                }
            }
            
            if (!empty($times)) {
                sort($times);
                $p50 = $times[intval(count($times) * 0.5)];
                $p95 = $times[intval(count($times) * 0.95)];
                $target = $this->config['performance_targets'][$routePath];
                
                $this->performanceData[$routePath] = [
                    'runs' => count($times),
                    'min' => min($times),
                    'max' => max($times),
                    'avg' => round(array_sum($times) / count($times), 2),
                    'p50' => round($p50, 2),
                    'p95' => round($p95, 2),
                    'target_p95' => $target,
                    'meets_target' => $p95 <= $target,
                    'all_times' => $times
                ];
            }
        }
    }

    /**
     * Test security headers on HTML pages
     */
    private function testSecurityHeaders(array $routes): void
    {
        $this->logger->info('Testing security headers', [
            'component' => 'route_perf_security',
            'action' => 'security_test_start',
            'required_headers' => $this->config['required_security_headers']
        ]);

        // Test key HTML routes for security headers
        $htmlRoutes = ['/', '/login', '/dashboard', '/admin'];
        
        foreach ($htmlRoutes as $routePath) {
            $url = $this->config['base_url'] . $routePath;
            $response = $this->client->get($url);
            
            if ($response['success']) {
                $contentType = $response['headers']['content-type'] ?? '';
                
                // Only check security headers on HTML responses
                if (str_contains($contentType, 'text/html')) {
                    $headerResults = [];
                    $allPresent = true;
                    
                    foreach ($this->config['required_security_headers'] as $header) {
                        $normalizedHeader = strtolower(str_replace('_', '-', $header));
                        $present = isset($response['headers'][$normalizedHeader]);
                        $headerResults[$header] = [
                            'present' => $present,
                            'value' => $present ? $response['headers'][$normalizedHeader] : null
                        ];
                        
                        if (!$present) {
                            $allPresent = false;
                        }
                    }
                    
                    $this->securityResults[$routePath] = [
                        'url' => $url,
                        'status_code' => $response['status_code'],
                        'content_type' => $contentType,
                        'headers' => $headerResults,
                        'all_required_present' => $allPresent,
                        'security_score' => round((array_sum(array_column($headerResults, 'present')) / count($headerResults)) * 100, 1)
                    ];
                }
            }
        }
    }

    /**
     * Test a single route
     */
    private function testSingleRoute(array $route, string $url): array
    {
        $response = $this->client->get($url);
        
        $result = [
            'route' => $route,
            'url' => $url,
            'success' => $response['success'],
            'status_code' => $response['status_code'] ?? 0,
            'response_time' => isset($response['timing']['total']) ? 
                round($response['timing']['total'] * 1000, 2) : 0,
            'content_length' => isset($response['headers']['content-length']) ? 
                intval($response['headers']['content-length']) : strlen($response['body'] ?? ''),
            'content_type' => $response['headers']['content-type'] ?? 'unknown'
        ];
        
        // Determine if the test passed
        $acceptableStatusCodes = [200, 301, 302, 401, 403]; // Normal expected codes
        $result['pass'] = $response['success'] && in_array($response['status_code'], $acceptableStatusCodes);
        
        // Additional validations
        if ($response['success']) {
            $result['validations'] = [
                'no_500_error' => $response['status_code'] !== 500,
                'has_content' => !empty($response['body']),
                'reasonable_response_time' => $result['response_time'] < 5000 // < 5 seconds
            ];
        }
        
        return $result;
    }

    /**
     * Check if a route is untestable
     */
    private function isUntestableRoute(array $route): bool
    {
        // Routes that require authentication
        if ($route['auth_required']) {
            return true;
        }
        
        // POST/PUT/DELETE routes require CSRF and data
        if (in_array($route['method'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }
        
        // API routes that require authentication
        if (str_starts_with($route['path'], '/api/') && $route['auth_required']) {
            return true;
        }
        
        return false;
    }

    /**
     * Get reason why a route is untestable
     */
    private function getUntestableReason(array $route): string
    {
        if ($route['auth_required']) {
            return 'Requires authentication';
        }
        
        if (in_array($route['method'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return 'Requires CSRF token and request data';
        }
        
        return 'Complex setup required';
    }

    /**
     * Generate comprehensive summary
     */
    private function generateSummary(array $routes, float $duration): array
    {
        $coverageSummary = $this->results['coverage_summary'] ?? [];
        $coveragePercentage = $coverageSummary['coverage_percentage'] ?? 0;
        
        // Performance summary
        $performancePass = true;
        $performanceSummary = [];
        foreach ($this->performanceData as $route => $data) {
            $performanceSummary[$route] = [
                'p50' => $data['p50'],
                'p95' => $data['p95'],
                'target' => $data['target_p95'],
                'pass' => $data['meets_target']
            ];
            
            if (!$data['meets_target']) {
                $performancePass = false;
            }
        }
        
        // Security summary
        $securityPass = true;
        $securitySummary = [];
        foreach ($this->securityResults as $route => $data) {
            $securitySummary[$route] = [
                'score' => $data['security_score'],
                'all_headers' => $data['all_required_present']
            ];
            
            if (!$data['all_required_present']) {
                $securityPass = false;
            }
        }
        
        return [
            'duration' => round($duration, 3),
            'coverage' => [
                'percentage' => $coveragePercentage,
                'meets_target' => $coveragePercentage >= $this->config['coverage_target'],
                'tested_routes' => $coverageSummary['tested_routes'] ?? 0,
                'total_routes' => $coverageSummary['total_routes'] ?? 0
            ],
            'performance' => [
                'meets_targets' => $performancePass,
                'routes_tested' => count($this->performanceData),
                'summary' => $performanceSummary
            ],
            'security' => [
                'all_headers_present' => $securityPass,
                'routes_tested' => count($this->securityResults),
                'summary' => $securitySummary
            ],
            'overall_pass' => $coveragePercentage >= $this->config['coverage_target'] && 
                            $performancePass && $securityPass
        ];
    }
}

// Web execution
if (isset($_GET['run']) || isset($_POST['run'])) {
    header('Content-Type: application/json');
    
    $suite = new RoutePerformanceSecuritySuite();
    $result = $suite->runCompleteSuite();
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $suite = new RoutePerformanceSecuritySuite();
    $result = $suite->runCompleteSuite();
    
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit($result['success'] ? 0 : 1);
}

// Interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Coverage, Performance & Security Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-tachometer-alt mr-2"></i>Route Coverage, Performance & Security Suite</h4>
                    </div>
                    <div class="card-body">
                        <p>Comprehensive testing of route coverage, performance metrics, and security header validation.</p>
                        
                        <div class="alert alert-info">
                            <strong>Targets:</strong> 95%+ coverage, /_health p95 &lt; 800ms, /login p95 &lt; 2000ms, 4/4 security headers
                        </div>
                        
                        <button id="runTests" class="btn btn-primary btn-lg">
                            <i class="fas fa-play mr-2"></i>Run Coverage & Performance Tests
                        </button>
                        
                        <div id="results" class="mt-4" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#runTests').click(function() {
            const button = $(this);
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Running Tests...');
            
            $.ajax({
                url: '?run=1',
                method: 'GET',
                success: function(data) {
                    const summary = data.summary || {};
                    let html = '<div class="row">';
                    
                    // Coverage
                    html += '<div class="col-md-4"><div class="card text-center">';
                    html += '<div class="card-body">';
                    html += '<h3 class="card-title">' + (summary.coverage?.percentage || 0) + '%</h3>';
                    html += '<p class="card-text">Coverage</p>';
                    html += '</div></div></div>';
                    
                    // Performance
                    html += '<div class="col-md-4"><div class="card text-center">';
                    html += '<div class="card-body">';
                    html += '<h3 class="card-title">' + (summary.performance?.meets_targets ? 'PASS' : 'FAIL') + '</h3>';
                    html += '<p class="card-text">Performance</p>';
                    html += '</div></div></div>';
                    
                    // Security
                    html += '<div class="col-md-4"><div class="card text-center">';
                    html += '<div class="card-body">';
                    html += '<h3 class="card-title">' + (summary.security?.all_headers_present ? 'PASS' : 'FAIL') + '</h3>';
                    html += '<p class="card-text">Security</p>';
                    html += '</div></div></div>';
                    
                    html += '</div>';
                    html += '<div class="mt-4"><pre>' + JSON.stringify(data, null, 2) + '</pre></div>';
                    
                    $('#results').html(html).show();
                },
                error: function() {
                    $('#results').html('<div class="alert alert-danger">Test execution failed</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).html('<i class="fas fa-play mr-2"></i>Run Coverage & Performance Tests');
                }
            });
        });
    });
    </script>
</body>
</html>
