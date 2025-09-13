<?php
/**
 * Enhanced Automation Suite with Integration Coverage
 * File: tools/automation/enhanced_integration_automation.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Test all endpoints including integration health checks
 */

require_once __DIR__ . '/../../functions/config.php';

class IntegrationAutomationSuite {
    private string $base_url;
    private array $results = [];
    private int $total_tests = 0;
    private int $passed_tests = 0;
    private float $start_time;
    
    public function __construct(string $base_url = 'http://localhost') {
        $this->base_url = rtrim($base_url, '/');
        $this->start_time = microtime(true);
    }
    
    public function runAll(): array {
        echo "🤖 Enhanced Integration Automation Suite Starting...\n";
        
        // Core system tests
        $this->testHealthEndpoint();
        $this->testLoginSystem();
        
        // Integration health tests
        $this->testVendHealth();
        $this->testDeputyHealth();
        $this->testXeroHealth();
        $this->testAllIntegrationsHealth();
        
        // File verification
        $this->testIntegrationFileCheck();
        
        // Migration test
        $this->testMigrationSystem();
        
        return $this->generateReport();
    }
    
    private function testHealthEndpoint(): void {
        $this->runTest('Core Health Check', function() {
            $response = $this->makeRequest('GET', '/_health');
            
            if ($response['status'] !== 200) {
                throw new Exception("Health check failed with status: {$response['status']}");
            }
            
            $data = json_decode($response['body'], true);
            if (!$data || !$data['ok']) {
                throw new Exception("Health check returned not OK");
            }
            
            return [
                'status' => $response['status'],
                'response_time_ms' => $response['time_ms'],
                'db_status' => $data['db_connect'] ?? 'unknown'
            ];
        });
    }
    
    private function testLoginSystem(): void {
        $this->runTest('Login System', function() {
            // Test login page
            $login_response = $this->makeRequest('GET', '/login');
            if ($login_response['status'] !== 200) {
                throw new Exception("Login page not accessible");
            }
            
            return [
                'login_page_status' => $login_response['status'],
                'response_time_ms' => $login_response['time_ms']
            ];
        });
    }
    
    private function testVendHealth(): void {
        $this->runTest('Vend Integration Health', function() {
            $response = $this->makeRequest('GET', '/admin/integrations/vend/health');
            
            $data = json_decode($response['body'], true);
            if (!$data) {
                throw new Exception("Invalid JSON response");
            }
            
            return [
                'status' => $response['status'],
                'service' => $data['service'] ?? 'unknown',
                'ok' => $data['ok'] ?? false,
                'response_time_ms' => $data['response_time_ms'] ?? 0,
                'error' => $data['error'] ?? null
            ];
        });
    }
    
    private function testDeputyHealth(): void {
        $this->runTest('Deputy Integration Health', function() {
            $response = $this->makeRequest('GET', '/admin/integrations/deputy/health');
            
            $data = json_decode($response['body'], true);
            if (!$data) {
                throw new Exception("Invalid JSON response");
            }
            
            return [
                'status' => $response['status'],
                'service' => $data['service'] ?? 'unknown',
                'ok' => $data['ok'] ?? false,
                'response_time_ms' => $data['response_time_ms'] ?? 0,
                'error' => $data['error'] ?? null
            ];
        });
    }
    
    private function testXeroHealth(): void {
        $this->runTest('Xero Integration Health', function() {
            $response = $this->makeRequest('GET', '/admin/integrations/xero/health');
            
            $data = json_decode($response['body'], true);
            if (!$data) {
                throw new Exception("Invalid JSON response");
            }
            
            return [
                'status' => $response['status'],
                'service' => $data['service'] ?? 'unknown',
                'ok' => $data['ok'] ?? false,
                'response_time_ms' => $data['response_time_ms'] ?? 0,
                'error' => $data['error'] ?? null
            ];
        });
    }
    
    private function testAllIntegrationsHealth(): void {
        $this->runTest('All Integrations Health', function() {
            $response = $this->makeRequest('GET', '/admin/integrations/health');
            
            $data = json_decode($response['body'], true);
            if (!$data) {
                throw new Exception("Invalid JSON response");
            }
            
            return [
                'status' => $response['status'],
                'all_ok' => $data['ok'] ?? false,
                'total_response_time_ms' => $data['total_response_time_ms'] ?? 0,
                'services_count' => count($data['services'] ?? []),
                'services' => $data['services'] ?? []
            ];
        });
    }
    
    private function testIntegrationFileCheck(): void {
        $this->runTest('Integration File Verification', function() {
            $file_path = __DIR__ . '/../integration_file_check.php';
            
            if (!file_exists($file_path)) {
                throw new Exception("Integration file check tool not found");
            }
            
            ob_start();
            include $file_path;
            $output = ob_get_clean();
            
            $data = json_decode($output, true);
            if (!$data) {
                throw new Exception("Invalid JSON from file check tool");
            }
            
            return [
                'files_found' => $data['files_check']['summary']['found'] ?? 0,
                'files_checked' => $data['files_check']['summary']['files_checked'] ?? 0,
                'all_found' => $data['files_check']['summary']['all_found'] ?? false,
                'secrets_test_passed' => $data['secrets_test']['success'] ?? false,
                'execution_time_ms' => $data['performance']['execution_time_ms'] ?? 0
            ];
        });
    }
    
    private function testMigrationSystem(): void {
        $this->runTest('Migration System', function() {
            $migration_file = __DIR__ . '/../../migrations/011_create_integration_secrets.php';
            
            if (!file_exists($migration_file)) {
                throw new Exception("Migration 011 file not found");
            }
            
            // Test migration syntax
            $syntax_check = exec("php -l " . escapeshellarg($migration_file) . " 2>&1", $output, $return_code);
            
            if ($return_code !== 0) {
                throw new Exception("Migration syntax error: " . $syntax_check);
            }
            
            return [
                'migration_file_exists' => true,
                'syntax_valid' => true,
                'file_size' => filesize($migration_file)
            ];
        });
    }
    
    private function runTest(string $name, callable $test): void {
        $this->total_tests++;
        $start_time = microtime(true);
        
        try {
            $result = $test();
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            $this->results[] = [
                'test_name' => $name,
                'status' => 'PASSED',
                'execution_time_ms' => $execution_time,
                'data' => $result
            ];
            
            $this->passed_tests++;
            echo "✅ {$name} - PASSED ({$execution_time}ms)\n";
            
        } catch (Exception $e) {
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            $this->results[] = [
                'test_name' => $name,
                'status' => 'FAILED',
                'execution_time_ms' => $execution_time,
                'error' => $e->getMessage()
            ];
            
            echo "❌ {$name} - FAILED: {$e->getMessage()} ({$execution_time}ms)\n";
        }
    }
    
    private function makeRequest(string $method, string $endpoint): array {
        $url = $this->base_url . $endpoint;
        $start_time = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'CIS-Integration-Test/1.0'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        
        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $time_ms = round((microtime(true) - $start_time) * 1000, 2);
        curl_close($ch);
        
        return [
            'status' => $http_code,
            'body' => $response_body,
            'time_ms' => $time_ms
        ];
    }
    
    private function generateReport(): array {
        $total_time = round((microtime(true) - $this->start_time) * 1000, 2);
        $success_rate = $this->total_tests > 0 ? round(($this->passed_tests / $this->total_tests) * 100, 1) : 0;
        
        return [
            'automation_suite' => 'Enhanced Integration Testing',
            'version' => '1.0',
            'execution_timestamp' => date('c'),
            'summary' => [
                'total_tests' => $this->total_tests,
                'passed_tests' => $this->passed_tests,
                'failed_tests' => $this->total_tests - $this->passed_tests,
                'success_rate_percentage' => $success_rate,
                'total_execution_time_ms' => $total_time
            ],
            'test_results' => $this->results,
            'integration_coverage' => [
                'vend_health_tested' => true,
                'deputy_health_tested' => true,
                'xero_health_tested' => true,
                'all_integrations_tested' => true,
                'file_verification_tested' => true
            ]
        ];
    }
}

// Execute if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $suite = new IntegrationAutomationSuite();
    $report = $suite->runAll();
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "📊 INTEGRATION AUTOMATION REPORT\n";
    echo str_repeat('=', 60) . "\n";
    
    echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
    
    // Return appropriate exit code
    exit($report['summary']['failed_tests'] === 0 ? 0 : 1);
}
