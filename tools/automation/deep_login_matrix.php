<?php
declare(strict_types=1);

/**
 * Deep Login Matrix Test Suite
 * 
 * Comprehensive testing of authentication flows including CSRF validation,
 * cookie security, lockout mechanisms, and RBAC verification.
 */

require_once __DIR__ . '/../../functions/config.php';
require_once __DIR__ . '/../../app/Shared/Bootstrap.php';
require_once __DIR__ . '/../../app/Tools/BrowserlessTestClient.php';

use App\Shared\Bootstrap;
use App\Tools\BrowserlessTestClient;
use App\Shared\Logging\Logger;

class DeepLoginMatrixSuite
{
    private BrowserlessTestClient $client;
    private Logger $logger;
    private array $config;
    private array $results = [];
    private array $screenshots = [];

    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->client = new BrowserlessTestClient($this->logger);
        
        $this->config = [
            'base_url' => $this->detectBaseUrl(),
            'test_user_email' => 'admin@ecigdis.co.nz',
            'test_user_password' => 'admin123',
            'lockout_user_email' => 'lockout_test@ecigdis.co.nz',
            'viewer_user_email' => 'viewer@ecigdis.co.nz',
            'viewer_user_password' => 'viewer123',
            'max_login_attempts' => 5,
            'screenshots_dir' => __DIR__ . '/../../var/screenshots/' . date('Ymd')
        ];
        
        @mkdir($this->config['screenshots_dir'], 0755, true);
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
            
            if ($response !== false && strpos($response, '"ok":true') !== false) {
                return $url;
            }
        }

        return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /**
     * Run complete login matrix test suite
     */
    public function runLoginMatrixTests(): array
    {
        $startTime = microtime(true);
        
        $this->logger->info('Deep login matrix tests started', [
            'component' => 'login_matrix',
            'action' => 'suite_start',
            'base_url' => $this->config['base_url']
        ]);

        try {
            // Phase 1: CSRF Protection Tests
            $this->testCSRFProtection();
            
            // Phase 2: Valid Login Flow with Cookie Security
            $this->testValidLoginFlow();
            
            // Phase 3: Login Lockout Mechanism
            $this->testLoginLockout();
            
            // Phase 4: RBAC Quick Check
            $this->testRBACAccess();
            
            $duration = microtime(true) - $startTime;
            
            $summary = [
                'success' => true,
                'duration' => round($duration, 3),
                'tests_run' => count($this->results),
                'screenshots_captured' => count($this->screenshots),
                'base_url' => $this->config['base_url']
            ];

            return [
                'success' => true,
                'summary' => $summary,
                'login_matrix' => $this->results,
                'screenshots' => $this->screenshots
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Login matrix tests failed', [
                'component' => 'login_matrix',
                'action' => 'suite_error',
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'partial_results' => $this->results
            ];
        }
    }

    /**
     * Test CSRF protection mechanisms
     */
    private function testCSRFProtection(): void
    {
        $this->logger->info('Testing CSRF protection', ['component' => 'login_matrix', 'action' => 'csrf_test_start']);

        // Test 1: POST /login without CSRF token
        $response1 = $this->client->post($this->config['base_url'] . '/login', [
            'email' => 'test@example.com',
            'password' => 'testpassword'
        ]);

        $this->results['csrf_no_token'] = [
            'test' => 'POST /login without CSRF token',
            'expected' => 403,
            'actual' => $response1['status_code'],
            'pass' => $response1['status_code'] === 403,
            'response_time' => $response1['timing']['total'] ?? 0
        ];

        // Test 2: GET login page and extract CSRF token
        $loginPage = $this->client->get($this->config['base_url'] . '/login');
        $csrfToken = $this->client->extractCSRFToken($loginPage['body']);

        $this->results['csrf_token_extraction'] = [
            'test' => 'CSRF token extraction from login page',
            'expected' => 'token found',
            'actual' => $csrfToken ? 'token found' : 'no token',
            'pass' => !empty($csrfToken),
            'token_length' => $csrfToken ? strlen($csrfToken) : 0
        ];

        // Capture pre-login screenshot
        $this->captureScreenshot('pre_login', $loginPage['body']);

        // Test 3: POST /login with CSRF token but invalid credentials
        if ($csrfToken) {
            $response3 = $this->client->post($this->config['base_url'] . '/login', [
                'email' => 'invalid@example.com',
                'password' => 'wrongpassword',
                'csrf_token' => $csrfToken
            ]);

            $this->results['csrf_with_invalid_creds'] = [
                'test' => 'POST /login with CSRF token + invalid credentials',
                'expected' => [401, 422],
                'actual' => $response3['status_code'],
                'pass' => in_array($response3['status_code'], [401, 422]),
                'no_500_error' => $response3['status_code'] !== 500,
                'response_time' => $response3['timing']['total'] ?? 0
            ];

            // Capture invalid login screenshot (should show error message)
            $this->captureScreenshot('invalid_login', $response3['body']);
        }
    }

    /**
     * Test valid login flow with cookie security validation
     */
    private function testValidLoginFlow(): void
    {
        $this->logger->info('Testing valid login flow', ['component' => 'login_matrix', 'action' => 'valid_login_test_start']);

        // Clear cookies first
        $this->client->clearCookies();

        // Get fresh login page and CSRF token
        $loginPage = $this->client->get($this->config['base_url'] . '/login');
        $csrfToken = $this->client->extractCSRFToken($loginPage['body']);

        if (!$csrfToken) {
            throw new \Exception('Could not extract CSRF token for valid login test');
        }

        // Perform valid login
        $loginResponse = $this->client->post($this->config['base_url'] . '/login', [
            'email' => $this->config['test_user_email'],
            'password' => $this->config['test_user_password'],
            'csrf_token' => $csrfToken
        ]);

        $this->results['valid_login'] = [
            'test' => 'Valid login with correct credentials',
            'expected' => [200, 302],
            'actual' => $loginResponse['status_code'],
            'pass' => in_array($loginResponse['status_code'], [200, 302]),
            'redirect_to_dashboard' => isset($loginResponse['headers']['location']) ? 
                str_contains($loginResponse['headers']['location'], '/dashboard') : false,
            'response_time' => $loginResponse['timing']['total'] ?? 0
        ];

        // Test dashboard access after login
        $dashboardResponse = $this->client->get($this->config['base_url'] . '/dashboard');
        
        $this->results['post_login_dashboard_access'] = [
            'test' => 'Dashboard access after valid login',
            'expected' => 200,
            'actual' => $dashboardResponse['status_code'],
            'pass' => $dashboardResponse['status_code'] === 200,
            'has_content' => !empty($dashboardResponse['body'])
        ];

        // Capture dashboard screenshot
        if ($dashboardResponse['status_code'] === 200) {
            $this->captureScreenshot('valid_login_dashboard', $dashboardResponse['body']);
        }

        // Validate cookie security flags
        $cookies = $this->client->getCookies();
        $sessionCookieName = null;
        
        foreach ($cookies as $name => $cookie) {
            if (in_array($name, ['PHPSESSID', 'cis_session', 'session'])) {
                $sessionCookieName = $name;
                break;
            }
        }

        if ($sessionCookieName) {
            $cookieFlags = $this->client->getCookieFlags($sessionCookieName);
            
            $this->results['cookie_security'] = [
                'test' => 'Session cookie security flags',
                'cookie_name' => $sessionCookieName,
                'httponly' => $cookieFlags['httponly'] ?? false,
                'secure' => $cookieFlags['secure'] ?? false,
                'samesite' => $cookieFlags['samesite'] ?? 'none',
                'httponly_pass' => $cookieFlags['httponly'] === true,
                'samesite_pass' => in_array($cookieFlags['samesite'], ['strict', 'lax']),
                'secure_pass' => $cookieFlags['secure'] === true || !str_starts_with($this->config['base_url'], 'https')
            ];
        }
    }

    /**
     * Test login lockout mechanism
     */
    private function testLoginLockout(): void
    {
        $this->logger->info('Testing login lockout', ['component' => 'login_matrix', 'action' => 'lockout_test_start']);

        $lockoutEmail = $this->config['lockout_user_email'];
        $attemptCount = 0;
        $lockoutTriggered = false;
        $lockoutAttempt = 0;

        // Clear cookies for clean test
        $this->client->clearCookies();

        while ($attemptCount < $this->config['max_login_attempts'] + 2 && !$lockoutTriggered) {
            $attemptCount++;
            
            // Get fresh CSRF token for each attempt
            $loginPage = $this->client->get($this->config['base_url'] . '/login');
            $csrfToken = $this->client->extractCSRFToken($loginPage['body']);

            if (!$csrfToken) {
                throw new \Exception("Could not get CSRF token for lockout attempt {$attemptCount}");
            }

            // Make invalid login attempt
            $response = $this->client->post($this->config['base_url'] . '/login', [
                'email' => $lockoutEmail,
                'password' => 'invalid_password_' . $attemptCount,
                'csrf_token' => $csrfToken
            ]);

            if (in_array($response['status_code'], [423, 429])) {
                $lockoutTriggered = true;
                $lockoutAttempt = $attemptCount;
                
                // Capture lockout screenshot
                $this->captureScreenshot('lockout_state', $response['body']);
            }
        }

        $this->results['login_lockout'] = [
            'test' => 'Login lockout mechanism',
            'max_attempts_configured' => $this->config['max_login_attempts'],
            'lockout_triggered' => $lockoutTriggered,
            'lockout_at_attempt' => $lockoutAttempt,
            'final_status_code' => $response['status_code'] ?? 0,
            'lockout_pass' => $lockoutTriggered && $lockoutAttempt <= $this->config['max_login_attempts'],
            'retry_after_header' => isset($response['headers']['retry-after']) ? $response['headers']['retry-after'] : null
        ];
    }

    /**
     * Test RBAC access control
     */
    private function testRBACAccess(): void
    {
        $this->logger->info('Testing RBAC access', ['component' => 'login_matrix', 'action' => 'rbac_test_start']);

        // Test 1: Non-admin user trying to access admin area
        $this->client->clearCookies();
        
        // Login as viewer user first
        $loginPage = $this->client->get($this->config['base_url'] . '/login');
        $csrfToken = $this->client->extractCSRFToken($loginPage['body']);
        
        if ($csrfToken) {
            $viewerLogin = $this->client->post($this->config['base_url'] . '/login', [
                'email' => $this->config['viewer_user_email'],
                'password' => $this->config['viewer_user_password'],
                'csrf_token' => $csrfToken
            ]);

            // Try to access admin area as non-admin
            $adminResponse = $this->client->get($this->config['base_url'] . '/admin');
            
            $this->results['rbac_non_admin_deny'] = [
                'test' => 'Non-admin user accessing admin area',
                'expected' => [403, 302], // Either forbidden or redirect
                'actual' => $adminResponse['status_code'],
                'pass' => in_array($adminResponse['status_code'], [403, 302]),
                'user_email' => $this->config['viewer_user_email']
            ];

            // Capture admin deny screenshot
            $this->captureScreenshot('admin_deny', $adminResponse['body']);
        }

        // Test 2: Admin user accessing admin area
        $this->client->clearCookies();
        
        $loginPage = $this->client->get($this->config['base_url'] . '/login');
        $csrfToken = $this->client->extractCSRFToken($loginPage['body']);
        
        if ($csrfToken) {
            $adminLogin = $this->client->post($this->config['base_url'] . '/login', [
                'email' => $this->config['test_user_email'],
                'password' => $this->config['test_user_password'],
                'csrf_token' => $csrfToken
            ]);

            // Try to access admin area as admin
            $adminResponse = $this->client->get($this->config['base_url'] . '/admin');
            
            $this->results['rbac_admin_allow'] = [
                'test' => 'Admin user accessing admin area',
                'expected' => 200,
                'actual' => $adminResponse['status_code'],
                'pass' => $adminResponse['status_code'] === 200,
                'user_email' => $this->config['test_user_email']
            ];

            // Capture admin allow screenshot
            if ($adminResponse['status_code'] === 200) {
                $this->captureScreenshot('admin_allow', $adminResponse['body']);
            }
        }
    }

    /**
     * Capture screenshot for visual testing
     */
    private function captureScreenshot(string $stage, string $html): void
    {
        $filename = $stage . '_' . date('His') . '.html';
        $filepath = $this->config['screenshots_dir'] . '/' . $filename;
        
        if (file_put_contents($filepath, $html)) {
            $this->screenshots[] = [
                'stage' => $stage,
                'filename' => $filename,
                'path' => 'var/screenshots/' . date('Ymd') . '/' . $filename,
                'size' => filesize($filepath),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Generate summary report
     */
    public function generateReport(): array
    {
        $passCount = 0;
        $totalCount = 0;
        
        foreach ($this->results as $testName => $result) {
            $totalCount++;
            if ($result['pass'] ?? false) {
                $passCount++;
            }
        }

        return [
            'summary' => [
                'total_tests' => $totalCount,
                'passed_tests' => $passCount,
                'failed_tests' => $totalCount - $passCount,
                'pass_rate' => $totalCount > 0 ? round(($passCount / $totalCount) * 100, 2) : 0,
                'go_no_go' => $passCount === $totalCount ? 'GO' : 'NO-GO'
            ],
            'results' => $this->results,
            'screenshots' => $this->screenshots
        ];
    }
}

// Web execution
if (isset($_GET['run']) || isset($_POST['run'])) {
    header('Content-Type: application/json');
    
    $suite = new DeepLoginMatrixSuite();
    $result = $suite->runLoginMatrixTests();
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $suite = new DeepLoginMatrixSuite();
    $result = $suite->runLoginMatrixTests();
    
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
    <title>Deep Login Matrix Test Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-shield-alt mr-2"></i>Deep Login Matrix Test Suite</h4>
                    </div>
                    <div class="card-body">
                        <p>Comprehensive authentication testing including CSRF, cookie security, lockout, and RBAC verification.</p>
                        
                        <button id="runTests" class="btn btn-danger btn-lg">
                            <i class="fas fa-play mr-2"></i>Run Deep Login Tests
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
                    $('#results').html('<pre>' + JSON.stringify(data, null, 2) + '</pre>').show();
                },
                error: function() {
                    $('#results').html('<div class="alert alert-danger">Test execution failed</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).html('<i class="fas fa-play mr-2"></i>Run Deep Login Tests');
                }
            });
        });
    });
    </script>
</body>
</html>
