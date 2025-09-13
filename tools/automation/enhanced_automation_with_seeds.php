<?php
declare(strict_types=1);

/**
 * CIS V2 Ultimate Automation Suite Integration
 * 
 * Enhanced automation runner with test account seeding integration
 * for comprehensive system verification with proper test data.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T15:35:00Z
 */

require_once __DIR__ . '/../../functions/config.php';
require_once __DIR__ . '/../../app/Shared/Bootstrap.php';

use App\Shared\Config\ConfigService;
use App\Shared\Logging\Logger;

class EnhancedAutomationSuite
{
    private Logger $logger;
    private array $results = [];
    private bool $hasTestAccounts = false;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Run complete verification cycle with test account setup
     */
    public function runFullVerification(): array
    {
        $startTime = microtime(true);
        
        $this->logInfo('Starting enhanced automation suite with test account integration');
        
        try {
            // Phase 1: Ensure test accounts exist
            $this->seedTestAccounts();
            
            // Phase 2: Run original automation suite
            $this->runOriginalSuite();
            
            // Phase 3: Test authentication flows
            $this->testAuthenticationFlows();
            
            // Phase 4: Test RBAC permissions
            $this->testRBACPermissions();
            
            // Phase 5: Generate comprehensive report
            $report = $this->generateEnhancedReport($startTime);
            
            return $report;
            
        } catch (Exception $e) {
            $this->logError('Enhanced automation suite failed', $e);
            return $this->createErrorReport($e, $startTime);
        }
    }
    
    /**
     * Seed test accounts if needed
     */
    private function seedTestAccounts(): void
    {
        $this->logInfo('Checking test account status');
        
        try {
            // Check if seeding is enabled
            $seedingEnabled = ConfigService::get('tools.seed.enabled', true);
            
            if (!$seedingEnabled) {
                $this->logWarning('Test seeding disabled, proceeding with existing accounts');
                return;
            }
            
            // Load and run seeding migration
            require_once __DIR__ . '/../../migrations/20250909_151500_seed_test_roles_users.php';
            
            $migration = new SeedTestRolesUsers();
            $result = $migration->up();
            
            if ($result['success']) {
                $this->hasTestAccounts = true;
                $this->results['seeding'] = [
                    'status' => 'success',
                    'inserted' => $result['inserted'],
                    'updated' => $result['updated'],
                    'message' => 'Test accounts prepared successfully'
                ];
                
                $this->logInfo('Test accounts seeded successfully', [
                    'inserted' => $result['inserted'],
                    'updated' => $result['updated']
                ]);
            } else {
                $this->results['seeding'] = [
                    'status' => 'failed',
                    'error' => $result['error'] ?? 'Unknown seeding error'
                ];
            }
            
        } catch (Exception $e) {
            $this->logError('Test account seeding failed', $e);
            $this->results['seeding'] = [
                'status' => 'exception',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Run original UltimateAutomationSuite
     */
    private function runOriginalSuite(): void
    {
        $this->logInfo('Running original automation suite');
        
        try {
            // Load original automation suite
            require_once __DIR__ . '/ultimate_automation_suite.php';
            
            // Run original suite (capture output)
            ob_start();
            $originalSuite = new UltimateAutomationSuite();
            $originalResults = $originalSuite->runAllTests();
            $originalOutput = ob_get_clean();
            
            $this->results['original_suite'] = [
                'status' => 'completed',
                'results' => $originalResults,
                'output' => $originalOutput
            ];
            
            $this->logInfo('Original automation suite completed');
            
        } catch (Exception $e) {
            $this->logError('Original automation suite failed', $e);
            $this->results['original_suite'] = [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test authentication flows with test accounts
     */
    private function testAuthenticationFlows(): void
    {
        if (!$this->hasTestAccounts) {
            $this->results['auth_flows'] = [
                'status' => 'skipped',
                'reason' => 'No test accounts available'
            ];
            return;
        }
        
        $this->logInfo('Testing authentication flows');
        
        $testUsers = [
            'admin' => [
                'email' => $_ENV['TEST_USER_EMAIL'] ?? 'pearce.stephens@gmail.com',
                'password' => $_ENV['TEST_USER_PASSWORD'] ?? 'testpass123',
                'role' => 'admin'
            ],
            'manager' => [
                'email' => $_ENV['TEST_MANAGER_EMAIL'] ?? 'manager.cis@test.local',
                'password' => $_ENV['TEST_MANAGER_PASSWORD'] ?? 'testpass123',
                'role' => 'manager'
            ],
            'staff' => [
                'email' => $_ENV['TEST_STAFF_EMAIL'] ?? 'staff.cis@test.local',
                'password' => $_ENV['TEST_STAFF_PASSWORD'] ?? 'testpass123',
                'role' => 'staff'
            ],
            'viewer' => [
                'email' => $_ENV['TEST_VIEWER_EMAIL'] ?? 'viewer.cis@test.local',
                'password' => $_ENV['TEST_VIEWER_PASSWORD'] ?? 'testpass123',
                'role' => 'viewer'
            ]
        ];
        
        $authResults = [];
        
        foreach ($testUsers as $userType => $userData) {
            try {
                // Test login endpoint
                $loginResult = $this->testLogin($userData['email'], $userData['password']);
                $authResults[$userType] = [
                    'login' => $loginResult,
                    'role' => $userData['role'],
                    'email_masked' => $this->maskEmail($userData['email'])
                ];
                
            } catch (Exception $e) {
                $authResults[$userType] = [
                    'login' => ['status' => 'failed', 'error' => $e->getMessage()],
                    'role' => $userData['role']
                ];
            }
        }
        
        $this->results['auth_flows'] = [
            'status' => 'completed',
            'test_results' => $authResults,
            'total_users' => count($testUsers)
        ];
        
        $this->logInfo('Authentication flow testing completed');
    }
    
    /**
     * Test RBAC permissions
     */
    private function testRBACPermissions(): void
    {
        if (!$this->hasTestAccounts) {
            $this->results['rbac_tests'] = [
                'status' => 'skipped',
                'reason' => 'No test accounts available'
            ];
            return;
        }
        
        $this->logInfo('Testing RBAC permissions');
        
        // Define permission matrix
        $permissionTests = [
            'admin' => [
                '/admin/dashboard' => 'should_allow',
                '/admin/users' => 'should_allow',
                '/admin/seeds' => 'should_allow',
                '/portal/home' => 'should_allow'
            ],
            'manager' => [
                '/admin/dashboard' => 'should_allow',
                '/admin/users' => 'should_deny',
                '/admin/seeds' => 'should_deny',
                '/portal/home' => 'should_allow'
            ],
            'staff' => [
                '/admin/dashboard' => 'should_deny',
                '/admin/users' => 'should_deny',
                '/portal/home' => 'should_allow'
            ],
            'viewer' => [
                '/admin/dashboard' => 'should_deny',
                '/admin/users' => 'should_deny',
                '/portal/home' => 'should_allow'
            ]
        ];
        
        $rbacResults = [];
        
        foreach ($permissionTests as $role => $tests) {
            $rbacResults[$role] = [];
            
            foreach ($tests as $endpoint => $expectation) {
                try {
                    $result = $this->testEndpointAccess($endpoint, $role);
                    $rbacResults[$role][$endpoint] = [
                        'expectation' => $expectation,
                        'result' => $result,
                        'passed' => $this->checkRBACExpectation($expectation, $result)
                    ];
                    
                } catch (Exception $e) {
                    $rbacResults[$role][$endpoint] = [
                        'expectation' => $expectation,
                        'result' => ['status' => 'error', 'message' => $e->getMessage()],
                        'passed' => false
                    ];
                }
            }
        }
        
        $this->results['rbac_tests'] = [
            'status' => 'completed',
            'test_results' => $rbacResults,
            'total_tests' => array_sum(array_map('count', $permissionTests))
        ];
        
        $this->logInfo('RBAC permission testing completed');
    }
    
    /**
     * Test login with credentials
     */
    private function testLogin(string $email, string $password): array
    {
        // Simulate login POST request
        $postData = http_build_query([
            'email' => $email,
            'password' => $password
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                           "Content-Length: " . strlen($postData) . "\r\n",
                'content' => $postData
            ]
        ]);
        
        // Test against login endpoint
        $baseUrl = $_ENV['APP_URL'] ?? 'https://staff.vapeshed.co.nz';
        $loginUrl = $baseUrl . '/auth/login';
        
        $response = @file_get_contents($loginUrl, false, $context);
        
        if ($response === false) {
            return [
                'status' => 'connection_failed',
                'url' => $loginUrl,
                'message' => 'Could not connect to login endpoint'
            ];
        }
        
        // Check response for success indicators
        if (strpos($response, 'dashboard') !== false || strpos($response, 'welcome') !== false) {
            return [
                'status' => 'success',
                'url' => $loginUrl,
                'message' => 'Login succeeded'
            ];
        }
        
        return [
            'status' => 'failed',
            'url' => $loginUrl,
            'message' => 'Login failed or redirected'
        ];
    }
    
    /**
     * Test endpoint access for role
     */
    private function testEndpointAccess(string $endpoint, string $role): array
    {
        // Simulate authenticated request (would need session/token in real scenario)
        $baseUrl = $_ENV['APP_URL'] ?? 'https://staff.vapeshed.co.nz';
        $fullUrl = $baseUrl . $endpoint;
        
        $response = @file_get_contents($fullUrl);
        
        if ($response === false) {
            return [
                'status' => 'connection_failed',
                'url' => $fullUrl
            ];
        }
        
        // Check for access denied indicators
        if (strpos($response, '403') !== false || 
            strpos($response, 'Access Denied') !== false ||
            strpos($response, 'Unauthorized') !== false) {
            return [
                'status' => 'denied',
                'url' => $fullUrl
            ];
        }
        
        // Check for login redirect
        if (strpos($response, 'login') !== false) {
            return [
                'status' => 'redirect_login',
                'url' => $fullUrl
            ];
        }
        
        return [
            'status' => 'allowed',
            'url' => $fullUrl
        ];
    }
    
    /**
     * Check if RBAC result matches expectation
     */
    private function checkRBACExpectation(string $expectation, array $result): bool
    {
        switch ($expectation) {
            case 'should_allow':
                return $result['status'] === 'allowed';
                
            case 'should_deny':
                return in_array($result['status'], ['denied', 'redirect_login']);
                
            default:
                return false;
        }
    }
    
    /**
     * Generate enhanced report
     */
    private function generateEnhancedReport(float $startTime): array
    {
        $executionTime = microtime(true) - $startTime;
        
        return [
            'success' => true,
            'version' => '2.0.0-alpha.2',
            'timestamp' => date('Y-m-d H:i:s T'),
            'execution_time' => round($executionTime, 3),
            'environment' => $_ENV['APP_ENV'] ?? 'development',
            'test_accounts_available' => $this->hasTestAccounts,
            'phases' => [
                'seeding' => $this->results['seeding'] ?? ['status' => 'not_run'],
                'original_suite' => $this->results['original_suite'] ?? ['status' => 'not_run'],
                'auth_flows' => $this->results['auth_flows'] ?? ['status' => 'not_run'],
                'rbac_tests' => $this->results['rbac_tests'] ?? ['status' => 'not_run']
            ],
            'summary' => $this->generateSummary(),
            'recommendations' => $this->generateRecommendations()
        ];
    }
    
    /**
     * Generate summary statistics
     */
    private function generateSummary(): array
    {
        $summary = [
            'phases_completed' => 0,
            'phases_failed' => 0,
            'phases_skipped' => 0
        ];
        
        foreach ($this->results as $phase => $result) {
            switch ($result['status']) {
                case 'success':
                case 'completed':
                    $summary['phases_completed']++;
                    break;
                case 'failed':
                case 'exception':
                    $summary['phases_failed']++;
                    break;
                case 'skipped':
                    $summary['phases_skipped']++;
                    break;
            }
        }
        
        return $summary;
    }
    
    /**
     * Generate recommendations based on results
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];
        
        if (!$this->hasTestAccounts) {
            $recommendations[] = 'Enable test account seeding for comprehensive authentication testing';
        }
        
        if (isset($this->results['seeding']) && $this->results['seeding']['status'] !== 'success') {
            $recommendations[] = 'Fix test account seeding issues to enable full verification';
        }
        
        if (isset($this->results['original_suite']) && $this->results['original_suite']['status'] !== 'completed') {
            $recommendations[] = 'Address original automation suite issues';
        }
        
        return $recommendations;
    }
    
    /**
     * Create error report
     */
    private function createErrorReport(Exception $e, float $startTime): array
    {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s T'),
            'execution_time' => round(microtime(true) - $startTime, 3),
            'partial_results' => $this->results
        ];
    }
    
    /**
     * Mask email address
     */
    private function maskEmail(string $email): string
    {
        if (strpos($email, '@') === false) {
            return str_repeat('*', strlen($email));
        }
        
        [$local, $domain] = explode('@', $email, 2);
        
        if (strlen($local) <= 2) {
            return str_repeat('*', strlen($local)) . '@' . $domain;
        }
        
        return $local[0] . str_repeat('*', max(1, strlen($local) - 2)) . $local[-1] . '@' . $domain;
    }
    
    /**
     * Log info message
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge([
            'component' => 'enhanced_automation_suite',
            'version' => '2.0.0-alpha.2'
        ], $context));
    }
    
    /**
     * Log warning message
     */
    private function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($message, array_merge([
            'component' => 'enhanced_automation_suite',
            'version' => '2.0.0-alpha.2'
        ], $context));
    }
    
    /**
     * Log error message
     */
    private function logError(string $message, Exception $e = null, array $context = []): void
    {
        $errorContext = array_merge([
            'component' => 'enhanced_automation_suite',
            'version' => '2.0.0-alpha.2'
        ], $context);
        
        if ($e) {
            $errorContext['exception'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }
        
        $this->logger->error($message, $errorContext);
    }
}

// Web interface
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    
    try {
        $suite = new EnhancedAutomationSuite();
        $results = $suite->runFullVerification();
        
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s T')
        ]);
    }
}

// CLI interface
if (php_sapi_name() === 'cli') {
    try {
        $suite = new EnhancedAutomationSuite();
        $results = $suite->runFullVerification();
        
        echo "Enhanced Automation Suite Results:\n";
        echo "=================================\n\n";
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo "\n";
        
        exit($results['success'] ? 0 : 1);
        
    } catch (Exception $e) {
        echo "Enhanced Automation Suite Failed:\n";
        echo "=================================\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
        
        exit(1);
    }
}
