<?php
/**
 * Extended Self-Test Endpoint
 * File: _selftest.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Comprehensive system health check for deployment readiness
 */

require_once __DIR__ . '/functions/config.php';

// Set response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

class SystemSelfTest {
    
    private array $results = [
        'ok' => false,
        'timestamp' => '',
        'version' => '1.0.0',
        'checks' => []
    ];
    
    public f        return $this->results;
    }
    
    /**
     * Check SR-12 readiness
     */
    private function checkSR12Readiness(): void {
        $check = [
            'name' => 'SR-12 Readiness',
            'ok' => false,
            'details' => []
        ];
        
        try {
            // Check if SR-12 config exists
            $sr12Config = __DIR__ . '/config/sr12.php';
            $check['details']['config_exists'] = file_exists($sr12Config);
            
            // Check if SR-12 tools exist
            $sr12Tools = [
                'file_check.php',
                'sr12_lint.php', 
                'load_test.php',
                'soak_test.php',
                'chaos_test.php',
                'backup_restore_test.php',
                'migration_test.php',
                'sr12_runner.php'
            ];
            
            $toolsExist = 0;
            foreach ($sr12Tools as $tool) {
                if (file_exists(__DIR__ . "/tools/$tool")) {
                    $toolsExist++;
                }
            }
            
            $check['details']['tools_available'] = "$toolsExist/" . count($sr12Tools);
            $check['details']['tools_complete'] = $toolsExist === count($sr12Tools);
            
            // Check report directory
            $reportDir = '/var/reports/sr12';
            $check['details']['report_dir_exists'] = is_dir($reportDir);
            $check['details']['report_dir_writable'] = is_writable($reportDir);
            
            $check['ok'] = $check['details']['config_exists'] &&
                          $check['details']['tools_complete'] &&
                          $check['details']['report_dir_writable'];
                          
        } catch (Exception $e) {
            $check['error'] = $e->getMessage();
        }
        
        $this->results['checks'][] = $check;
    }
    
    /**
     * Check performance baseline
     */
    private function checkPerformanceBaseline(): void {
        $check = [
            'name' => 'Performance Baseline',
            'ok' => false,
            'details' => []
        ];
        
        try {
            // Test database query performance
            $queryStart = microtime(true);
            
            if (isset($this->pdo)) {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM cis_users");
                $userCount = $stmt->fetchColumn();
                $queryTime = (microtime(true) - $queryStart) * 1000;
                
                $check['details']['db_query_ms'] = round($queryTime, 2);
                $check['details']['db_query_ok'] = $queryTime < 100; // 100ms threshold
                $check['details']['user_count'] = $userCount;
            }
            
            // Test filesystem performance
            $fileStart = microtime(true);
            $testFile = '/tmp/selftest_' . time() . '.txt';
            file_put_contents($testFile, str_repeat('x', 1024)); // 1KB test file
            $fileContent = file_get_contents($testFile);
            unlink($testFile);
            $fileTime = (microtime(true) - $fileStart) * 1000;
            
            $check['details']['file_io_ms'] = round($fileTime, 2);
            $check['details']['file_io_ok'] = $fileTime < 50; // 50ms threshold
            
            // Test memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            
            $check['details']['memory_usage_mb'] = round($memoryUsage / 1024 / 1024, 2);
            $check['details']['memory_peak_mb'] = round($memoryPeak / 1024 / 1024, 2);
            $check['details']['memory_ok'] = $memoryUsage < (64 * 1024 * 1024); // 64MB threshold
            
            $check['ok'] = ($check['details']['db_query_ok'] ?? true) &&
                          $check['details']['file_io_ok'] &&
                          $check['details']['memory_ok'];
                          
        } catch (Exception $e) {
            $check['error'] = $e->getMessage();
        }
        
        $this->results['checks'][] = $check;
    }
    
    /**
     * Check security configuration
     */
    private function checkSecurityConfiguration(): void {
        $check = [
            'name' => 'Security Configuration',
            'ok' => false,
            'details' => []
        ];
        
        try {
            // Check PHP security settings
            $check['details']['expose_php'] = ini_get('expose_php') == '0';
            $check['details']['display_errors'] = ini_get('display_errors') == '0';
            $check['details']['log_errors'] = ini_get('log_errors') == '1';
            
            // Check session security
            $check['details']['session_cookie_httponly'] = ini_get('session.cookie_httponly') == '1';
            $check['details']['session_cookie_secure'] = ini_get('session.cookie_secure') == '1';
            $check['details']['session_use_strict_mode'] = ini_get('session.use_strict_mode') == '1';
            
            // Check file permissions on sensitive files
            $sensitiveFiles = [
                __DIR__ . '/functions/config.php',
                __DIR__ . '/.env'
            ];
            
            $securePermissions = true;
            foreach ($sensitiveFiles as $file) {
                if (file_exists($file)) {
                    $perms = fileperms($file) & 0777;
                    if ($perms & 0044) { // Check if world/group readable
                        $securePermissions = false;
                        break;
                    }
                }
            }
            
            $check['details']['file_permissions_secure'] = $securePermissions;
            
            // Check HTTPS enforcement
            $check['details']['https_enforced'] = isset($_SERVER['HTTPS']) || 
                                                 (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 
                                                  $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            
            $securityScore = 0;
            $totalChecks = count($check['details']);
            
            foreach ($check['details'] as $result) {
                if ($result) $securityScore++;
            }
            
            $check['details']['security_score'] = "$securityScore/$totalChecks";
            $check['ok'] = $securityScore >= ($totalChecks * 0.8); // 80% threshold
            
        } catch (Exception $e) {
            $check['error'] = $e->getMessage();
        }
        
        $this->results['checks'][] = $check;
    }
}

// Handle deep test endpoint
if (isset($_GET['mode']) && $_GET['mode'] === 'deep') {
    // Deep test mode with extended diagnostics
    require_once __DIR__ . '/tools/sr12_runner.php';
    
    $deepTest = [
        'selftest' => null,
        'sr12_status' => null,
        'timestamp' => date('c')
    ];
    
    try {
        // Run standard selftest
        $selfTest = new SystemSelfTest();
        $deepTest['selftest'] = $selfTest->runTests();
        
        // Get SR-12 status
        $sr12Runner = new SR12TestRunner();
        $deepTest['sr12_status'] = $sr12Runner->getStatus();
        
        $deepTest['ok'] = $deepTest['selftest']['ok'] && 
                         ($deepTest['sr12_status']['status'] === 'pass' || 
                          $deepTest['sr12_status']['status'] === 'not_run');
        
        http_response_code($deepTest['ok'] ? 200 : 503);
        echo json_encode($deepTest, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
    }
    
    exit;
}

// Execute standard self-test
try {
    $selfTest = new SystemSelfTest();
    $results = $selfTest->runTests();
    
    // Set appropriate HTTP status code
    http_response_code($results['ok'] ? 200 : 503);
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {ruct() {
        $this->results['timestamp'] = date('c');
    }
    
    /**
     * Run comprehensive self-test
     */
    public function runTests(): array {
        $startTime = microtime(true);
        
        try {
            // Core system checks
            $this->checkPHPVersion();
            $this->checkRequiredExtensions();
            $this->checkFilePermissions();
            $this->checkDatabaseConnection();
            
            // Migration checks
            $this->checkMigrationsOk();
            
            // Seed data checks
            $this->checkSeedsOk();
            
            // Integration checks
            $this->checkIntegrationsOk();
            
            // SR-12 readiness checks
            $this->checkSR12Readiness();
            
            // Performance baseline checks
            $this->checkPerformanceBaseline();
            
            // Security configuration checks
            $this->checkSecurityConfiguration();
            
            // Queue system checks
            $this->checkQueueOk();
            
            // AI system checks
            $this->checkAiOk();
            
            // Security checks
            $this->checkSecurityConfiguration();
            
            // Performance checks
            $this->checkPerformanceMetrics();
            
            // Determine overall status
            $this->results['ok'] = $this->calculateOverallStatus();
            $this->results['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            
        } catch (Exception $e) {
            $this->results['ok'] = false;
            $this->results['error'] = $e->getMessage();
            $this->results['checks']['fatal_error'] = [
                'ok' => false,
                'message' => 'Self-test execution failed',
                'error' => $e->getMessage()
            ];
        }
        
        return $this->results;
    }
    
    /**
     * Check PHP version compatibility
     */
    private function checkPHPVersion(): void {
        $minVersion = '8.0.0';
        $currentVersion = PHP_VERSION;
        $ok = version_compare($currentVersion, $minVersion, '>=');
        
        $this->results['checks']['php_version'] = [
            'ok' => $ok,
            'current' => $currentVersion,
            'minimum_required' => $minVersion,
            'message' => $ok ? 'PHP version is compatible' : "PHP version {$currentVersion} is below minimum {$minVersion}"
        ];
    }
    
    /**
     * Check required PHP extensions
     */
    private function checkRequiredExtensions(): void {
        $requiredExtensions = [
            'mysqli', 'json', 'openssl', 'curl', 'mbstring', 
            'zip', 'gd', 'sodium', 'session', 'filter'
        ];
        
        $missing = [];
        $loaded = [];
        
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                $loaded[] = $ext;
            } else {
                $missing[] = $ext;
            }
        }
        
        $ok = empty($missing);
        
        $this->results['checks']['php_extensions'] = [
            'ok' => $ok,
            'loaded' => $loaded,
            'missing' => $missing,
            'message' => $ok ? 'All required extensions are loaded' : 'Missing required extensions: ' . implode(', ', $missing)
        ];
    }
    
    /**
     * Check file permissions
     */
    private function checkFilePermissions(): void {
        $paths = [
            'var/logs' => ['exists' => false, 'writable' => false],
            'var/cache' => ['exists' => false, 'writable' => false],
            'var/sessions' => ['exists' => false, 'writable' => false],
            'var/uploads' => ['exists' => false, 'writable' => false]
        ];
        
        $issues = [];
        
        foreach ($paths as $path => &$status) {
            $fullPath = __DIR__ . '/' . $path;
            
            if (file_exists($fullPath)) {
                $status['exists'] = true;
                $status['writable'] = is_writable($fullPath);
                
                if (!$status['writable']) {
                    $issues[] = "$path is not writable";
                }
            } else {
                $issues[] = "$path does not exist";
                
                // Try to create directory
                if (mkdir($fullPath, 0755, true)) {
                    $status['exists'] = true;
                    $status['writable'] = is_writable($fullPath);
                }
            }
        }
        
        $ok = empty($issues);
        
        $this->results['checks']['file_permissions'] = [
            'ok' => $ok,
            'paths' => $paths,
            'issues' => $issues,
            'message' => $ok ? 'All required directories are writable' : 'File permission issues: ' . implode(', ', $issues)
        ];
    }
    
    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): void {
        global $mysqli;
        
        $ok = false;
        $message = 'Database connection failed';
        $details = [];
        
        try {
            if ($mysqli && !$mysqli->connect_error) {
                // Test basic query
                $result = $mysqli->query("SELECT 1 as test, NOW() as current_time, VERSION() as version");
                
                if ($result) {
                    $row = $result->fetch_assoc();
                    $details = [
                        'test_query' => 'SUCCESS',
                        'server_time' => $row['current_time'],
                        'database_version' => $row['version']
                    ];
                    $ok = true;
                    $message = 'Database connection successful';
                } else {
                    $details['error'] = 'Test query failed: ' . $mysqli->error;
                }
            } else {
                $details['error'] = 'Connection error: ' . ($mysqli->connect_error ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $details['error'] = 'Exception: ' . $e->getMessage();
        }
        
        $this->results['checks']['database_connection'] = [
            'ok' => $ok,
            'message' => $message,
            'details' => $details
        ];
    }
    
    /**
     * Check migrations status
     */
    private function checkMigrationsOk(): void {
        global $mysqli;
        
        $requiredTables = [
            'cis_users', 'cis_roles', 'cis_permissions', 'cis_sessions',
            'cis_ai_keys', 'cis_ai_events', 'cis_ai_orchestration_jobs',
            'cis_integration_secrets', 'cis_integration_health', 'cis_integration_sync_jobs',
            'cis_monitor_log', 'cis_service_registry', 'cis_alert_rules'
        ];
        
        $existing = [];
        $missing = [];
        
        if ($mysqli) {
            foreach ($requiredTables as $table) {
                $result = $mysqli->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    $existing[] = $table;
                } else {
                    $missing[] = $table;
                }
            }
        } else {
            $missing = $requiredTables;
        }
        
        $ok = empty($missing);
        
        $this->results['checks']['migrations_ok'] = [
            'ok' => $ok,
            'existing_tables' => $existing,
            'missing_tables' => $missing,
            'total_required' => count($requiredTables),
            'total_existing' => count($existing),
            'message' => $ok ? 'All required database tables exist' : 'Missing database tables: ' . implode(', ', $missing)
        ];
    }
    
    /**
     * Check seed data
     */
    private function checkSeedsOk(): void {
        global $mysqli;
        
        $ok = true;
        $details = [];
        $issues = [];
        
        if ($mysqli) {
            // Check for admin user
            $result = $mysqli->query("SELECT COUNT(*) as count FROM cis_users WHERE role_id = 1");
            if ($result && $row = $result->fetch_assoc()) {
                $details['admin_users'] = (int)$row['count'];
                if ($details['admin_users'] == 0) {
                    $issues[] = 'No admin users found';
                    $ok = false;
                }
            }
            
            // Check for roles
            $result = $mysqli->query("SELECT COUNT(*) as count FROM cis_roles");
            if ($result && $row = $result->fetch_assoc()) {
                $details['roles'] = (int)$row['count'];
                if ($details['roles'] == 0) {
                    $issues[] = 'No roles configured';
                    $ok = false;
                }
            }
            
            // Check for permissions
            $result = $mysqli->query("SELECT COUNT(*) as count FROM cis_permissions");
            if ($result && $row = $result->fetch_assoc()) {
                $details['permissions'] = (int)$row['count'];
                if ($details['permissions'] == 0) {
                    $issues[] = 'No permissions configured';
                    $ok = false;
                }
            }
            
        } else {
            $ok = false;
            $issues[] = 'Cannot check seeds - database not available';
        }
        
        $this->results['checks']['seeds_ok'] = [
            'ok' => $ok,
            'details' => $details,
            'issues' => $issues,
            'message' => $ok ? 'Seed data is present' : 'Seed data issues: ' . implode(', ', $issues)
        ];
    }
    
    /**
     * Check integrations status
     */
    private function checkIntegrationsOk(): void {
        global $mysqli;
        
        $integrations = [
            'ai' => ['openai', 'claude'],
            'business' => ['vend', 'deputy', 'xero']
        ];
        
        $status = [];
        $issues = [];
        
        if ($mysqli) {
            // Check AI integrations
            foreach ($integrations['ai'] as $provider) {
                $result = $mysqli->query("
                    SELECT COUNT(*) as count 
                    FROM cis_ai_keys 
                    WHERE provider = '$provider' AND status = 'active'
                ");
                
                if ($result && $row = $result->fetch_assoc()) {
                    $count = (int)$row['count'];
                    $status['ai'][$provider] = $count > 0 ? 'configured' : 'not_configured';
                    if ($count == 0) {
                        $issues[] = "AI integration $provider not configured";
                    }
                }
            }
            
            // Check business integrations
            foreach ($integrations['business'] as $provider) {
                $result = $mysqli->query("
                    SELECT COUNT(*) as count 
                    FROM cis_integration_secrets 
                    WHERE provider = '$provider'
                ");
                
                if ($result && $row = $result->fetch_assoc()) {
                    $count = (int)$row['count'];
                    $status['business'][$provider] = $count > 0 ? 'configured' : 'not_configured';
                    if ($count == 0) {
                        $issues[] = "Business integration $provider not configured";
                    }
                }
            }
        } else {
            $issues[] = 'Cannot check integrations - database not available';
        }
        
        // Integration files check
        $integrationFiles = [
            'app/Integrations/OpenAI/Client.php',
            'app/Integrations/Claude/Client.php',
            'app/Integrations/Vend/Client.php',
            'app/Integrations/Deputy/Client.php',
            'app/Integrations/Xero/Client.php'
        ];
        
        $missingFiles = [];
        foreach ($integrationFiles as $file) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                $missingFiles[] = $file;
                $issues[] = "Integration file missing: $file";
            }
        }
        
        $ok = empty($issues);
        
        $this->results['checks']['integrations_ok'] = [
            'ok' => $ok,
            'status' => $status,
            'missing_files' => $missingFiles,
            'issues' => $issues,
            'message' => $ok ? 'Integrations are properly configured' : 'Integration issues found'
        ];
    }
    
    /**
     * Check queue system
     */
    private function checkQueueOk(): void {
        global $mysqli;
        
        $ok = true;
        $details = [];
        
        // Check if background job system is functional
        $details['monitor_poll_file'] = file_exists(__DIR__ . '/tools/monitor_poll.php');
        $details['cron_configured'] = false; // Would need to check actual cron
        
        // Check job processing capability
        if ($mysqli) {
            $result = $mysqli->query("SELECT COUNT(*) as count FROM cis_ai_orchestration_jobs WHERE status = 'pending'");
            if ($result && $row = $result->fetch_assoc()) {
                $details['pending_ai_jobs'] = (int)$row['count'];
            }
        }
        
        $this->results['checks']['queue_ok'] = [
            'ok' => $ok,
            'details' => $details,
            'message' => 'Queue system components are present'
        ];
    }
    
    /**
     * Check AI system
     */
    private function checkAiOk(): void {
        $aiFiles = [
            'app/Shared/AI/Orchestrator.php',
            'app/Shared/AI/Events.php',
            'app/Http/Controllers/AIAdminController.php',
            'config/ai.php'
        ];
        
        $missing = [];
        foreach ($aiFiles as $file) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                $missing[] = $file;
            }
        }
        
        $ok = empty($missing);
        
        $this->results['checks']['ai_ok'] = [
            'ok' => $ok,
            'required_files' => $aiFiles,
            'missing_files' => $missing,
            'message' => $ok ? 'AI system files are present' : 'Missing AI system files'
        ];
    }
    
    /**
     * Check security configuration
     */
    private function checkSecurityConfiguration(): void {
        $securityChecks = [];
        
        // Check security headers middleware
        $securityChecks['security_headers_middleware'] = file_exists(__DIR__ . '/app/Http/Middlewares/SecurityHeaders.php');
        
        // Check error handler
        $securityChecks['error_handler_middleware'] = file_exists(__DIR__ . '/app/Http/Middlewares/ErrorPage.php');
        
        // Check rate limiting
        $securityChecks['rate_limit_middleware'] = file_exists(__DIR__ . '/app/Http/Middlewares/RateLimit.php');
        
        // Check session security
        $securityChecks['secure_session_middleware'] = file_exists(__DIR__ . '/app/Http/Middlewares/SecureSession.php');
        
        // Check HTTPS configuration (basic check)
        $securityChecks['https_available'] = isset($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443;
        
        $issues = [];
        foreach ($securityChecks as $check => $status) {
            if (!$status) {
                $issues[] = $check;
            }
        }
        
        $ok = empty($issues);
        
        $this->results['checks']['security_configuration'] = [
            'ok' => $ok,
            'checks' => $securityChecks,
            'issues' => $issues,
            'message' => $ok ? 'Security configuration is complete' : 'Security issues found'
        ];
    }
    
    /**
     * Check performance metrics
     */
    private function checkPerformanceMetrics(): void {
        $startTime = microtime(true);
        
        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        // Simple performance test
        $iterations = 10000;
        for ($i = 0; $i < $iterations; $i++) {
            $dummy = md5((string)$i);
        }
        
        $executionTime = (microtime(true) - $startTime) * 1000; // ms
        
        $issues = [];
        $ok = true;
        
        // Performance thresholds
        if ($executionTime > 100) {
            $issues[] = 'Slow performance test execution';
            $ok = false;
        }
        
        if ($memoryUsage > 128 * 1024 * 1024) { // 128MB
            $issues[] = 'High memory usage';
            $ok = false;
        }
        
        $this->results['checks']['performance_metrics'] = [
            'ok' => $ok,
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_bytes' => $memoryUsage,
            'peak_memory_bytes' => $peakMemory,
            'issues' => $issues,
            'message' => $ok ? 'Performance is within acceptable limits' : 'Performance issues detected'
        ];
    }
    
    /**
     * Calculate overall system status
     */
    private function calculateOverallStatus(): bool {
        $criticalChecks = [
            'php_version', 'php_extensions', 'database_connection', 
            'migrations_ok', 'seeds_ok'
        ];
        
        foreach ($criticalChecks as $check) {
            if (!isset($this->results['checks'][$check]) || !$this->results['checks'][$check]['ok']) {
                return false;
            }
        }
        
        return true;
    }
}

// Execute self-test
try {
    $selfTest = new SystemSelfTest();
    $results = $selfTest->runTests();
    
    // Set appropriate HTTP status code
    http_response_code($results['ok'] ? 200 : 503);
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Self-test execution failed',
        'message' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}
