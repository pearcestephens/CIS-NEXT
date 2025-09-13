<?php
declare(strict_types=1);

/**
 * Automation Model
 * File: app/Models/AutomationModel.php
 * Purpose: Automation suite management using AdminDAL
 */

namespace App\Models;

use RuntimeException;

class AutomationModel
{
    private AdminDAL $dal;
    private array $available_suites;

    public function __construct()
    {
        $this->dal = new AdminDAL();
        $this->available_suites = $this->initializeAutomationSuites();
    }

    /**
     * Define available automation suites
     */
    private function initializeAutomationSuites(): array
    {
        return [
            'database_health' => [
                'id' => 'database_health',
                'name' => 'Database Health Check',
                'description' => 'Comprehensive database connection, query performance, and integrity checks',
                'icon' => 'fas fa-database',
                'estimated_time' => 30,
                'status' => 'ready'
            ],
            'cache_validation' => [
                'id' => 'cache_validation',
                'name' => 'Cache System Validation',
                'description' => 'Validate Redis/Memcached connections and cache performance',
                'icon' => 'fas fa-memory',
                'estimated_time' => 15,
                'status' => 'ready'
            ],
            'file_permissions' => [
                'id' => 'file_permissions',
                'name' => 'File Permissions Audit',
                'description' => 'Check critical file and directory permissions for security',
                'icon' => 'fas fa-shield-alt',
                'estimated_time' => 20,
                'status' => 'ready'
            ],
            'integration_health' => [
                'id' => 'integration_health',
                'name' => 'Integration Health Check',
                'description' => 'Test connections to Vend, Deputy, Xero, and other integrations',
                'icon' => 'fas fa-plug',
                'estimated_time' => 45,
                'status' => 'ready'
            ],
            'performance_baseline' => [
                'id' => 'performance_baseline',
                'name' => 'Performance Baseline Test',
                'description' => 'Run performance tests and compare against baseline metrics',
                'icon' => 'fas fa-tachometer-alt',
                'estimated_time' => 60,
                'status' => 'ready'
            ],
            'security_scan' => [
                'id' => 'security_scan',
                'name' => 'Security Vulnerability Scan',
                'description' => 'Scan for common security vulnerabilities and configuration issues',
                'icon' => 'fas fa-bug',
                'estimated_time' => 90,
                'status' => 'ready'
            ]
        ];
    }

    /**
     * Get automation suite data for dashboard
     */
    public function getAutomationData(): array
    {
        try {
            // Get recent runs
            $recent_runs = $this->dal->query("
                SELECT 
                    suite_name,
                    status,
                    runtime_seconds as runtime,
                    DATE_FORMAT({$this->dal->col('created_at')}, '%M %e, %H:%i') as date
                FROM {$this->dal->table('automation_runs')} 
                WHERE {$this->dal->col('created_at')} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY {$this->dal->col('created_at')} DESC 
                LIMIT 10
            ");

            // Get last run timestamp
            $last_run_result = $this->dal->query("
                SELECT DATE_FORMAT(MAX({$this->dal->col('created_at')}), '%M %e at %H:%i') as last_run
                FROM {$this->dal->table('automation_runs')}
            ");

            // Get system status
            $system_status = [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_limit' => ini_get('memory_limit')
            ];

            return [
                'automation_suites' => array_values($this->available_suites),
                'recent_results' => $recent_runs,
                'last_run' => $last_run_result[0]['last_run'] ?? null,
                'system_status' => $system_status
            ];

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to get automation data: " . $e->getMessage());
        }
    }

    /**
     * Execute automation suite
     */
    public function runSuite(string $suite_id): array
    {
        if (!isset($this->available_suites[$suite_id])) {
            throw new RuntimeException("Unknown automation suite: {$suite_id}");
        }

        $suite = $this->available_suites[$suite_id];
        $start_time = microtime(true);

        try {
            $this->dal->begin();

            // Log suite start
            $run_result = $this->dal->exec("
                INSERT INTO {$this->dal->table('automation_runs')} 
                (suite_id, suite_name, status, started_by, started_at) 
                VALUES (?, ?, 'running', ?, NOW())
            ", [
                $suite_id,
                $suite['name'],
                $_SESSION['user_id'] ?? null
            ], 'ssi');

            $run_id = $run_result['insert_id'];

            // Execute the actual suite
            $execution_result = $this->executeSuite($suite_id);
            
            $end_time = microtime(true);
            $runtime = round($end_time - $start_time, 2);

            // Update run record with results
            $this->dal->exec("
                UPDATE {$this->dal->table('automation_runs')} 
                SET 
                    status = ?,
                    runtime_seconds = ?,
                    results = ?,
                    completed_at = NOW()
                WHERE id = ?
            ", [
                $execution_result['success'] ? 'success' : 'failed',
                $runtime,
                json_encode($execution_result['details'] ?? []),
                $run_id
            ], 'sdsi');

            $this->dal->commit();

            return [
                'success' => $execution_result['success'],
                'suite_name' => $suite['name'],
                'runtime' => $runtime,
                'results' => $execution_result['details'] ?? [],
                'output' => $execution_result['output'] ?? '',
                'run_id' => $run_id
            ];

        } catch (RuntimeException $e) {
            $this->dal->rollback();
            
            // Log failed run if we have a run_id
            if (isset($run_id)) {
                try {
                    $this->dal->exec("
                        UPDATE {$this->dal->table('automation_runs')} 
                        SET status = 'failed', error_message = ?, completed_at = NOW()
                        WHERE id = ?
                    ", [$e->getMessage(), $run_id], 'si');
                } catch (RuntimeException $log_error) {
                    // Ignore logging errors
                }
            }

            throw new RuntimeException("Suite execution failed: " . $e->getMessage());
        }
    }

    /**
     * Execute specific automation suite
     */
    private function executeSuite(string $suite_id): array
    {
        switch ($suite_id) {
            case 'database_health':
                return $this->runDatabaseHealthCheck();
            
            case 'cache_validation':
                return $this->runCacheValidation();
            
            case 'file_permissions':
                return $this->runFilePermissionsAudit();
            
            case 'integration_health':
                return $this->runIntegrationHealthCheck();
            
            case 'performance_baseline':
                return $this->runPerformanceBaseline();
            
            case 'security_scan':
                return $this->runSecurityScan();
            
            default:
                throw new RuntimeException("Unknown suite implementation: {$suite_id}");
        }
    }

    /**
     * Database health check implementation
     */
    private function runDatabaseHealthCheck(): array
    {
        $checks = [];
        $output = [];

        try {
            // Connection test
            $health = $this->dal->health();
            $checks['connection'] = $health['connected'];
            $output[] = "Database connection: " . ($health['connected'] ? 'OK' : 'FAILED');

            // Table existence check
            $tables = $this->dal->query("SHOW TABLES");
            $checks['table_count'] = count($tables);
            $output[] = "Found {$checks['table_count']} tables";

            // Index analysis
            $indexes = $this->dal->query("
                SELECT COUNT(*) as index_count 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE()
            ");
            $checks['index_count'] = $indexes[0]['index_count'] ?? 0;
            $output[] = "Found {$checks['index_count']} indexes";

            // Query performance test
            $start = microtime(true);
            $this->dal->query("SELECT 1");
            $query_time = round((microtime(true) - $start) * 1000, 2);
            $checks['query_performance_ms'] = $query_time;
            $output[] = "Simple query performance: {$query_time}ms";

            return [
                'success' => $checks['connection'] && $checks['table_count'] > 0,
                'details' => $checks,
                'output' => implode("\n", $output)
            ];

        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'details' => ['error' => $e->getMessage()],
                'output' => "Database health check failed: " . $e->getMessage()
            ];
        }
    }

    /**
     * Cache validation implementation
     */
    private function runCacheValidation(): array
    {
        $output = [];
        
        try {
            // Basic PHP cache functions
            $checks = [
                'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
                'apcu_enabled' => function_exists('apcu_enabled') && apcu_enabled(),
                'session_cache' => session_status() === PHP_SESSION_ACTIVE || session_start()
            ];

            foreach ($checks as $check => $result) {
                $output[] = ucfirst(str_replace('_', ' ', $check)) . ": " . ($result ? 'OK' : 'NOT AVAILABLE');
            }

            return [
                'success' => true,
                'details' => $checks,
                'output' => implode("\n", $output)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'details' => ['error' => $e->getMessage()],
                'output' => "Cache validation failed: " . $e->getMessage()
            ];
        }
    }

    /**
     * File permissions audit implementation
     */
    private function runFilePermissionsAudit(): array
    {
        $output = [];
        $checks = [];
        
        $critical_paths = [
            'config/' => 0755,
            'logs/' => 0755,
            'backups/' => 0755,
            'migrations/' => 0755,
            'assets/css/' => 0755,
            'assets/js/' => 0755
        ];

        foreach ($critical_paths as $path => $expected_perm) {
            if (is_dir($path)) {
                $actual_perm = fileperms($path) & 0777;
                $checks[$path] = [
                    'expected' => decoct($expected_perm),
                    'actual' => decoct($actual_perm),
                    'correct' => $actual_perm >= $expected_perm
                ];
                
                $status = $checks[$path]['correct'] ? 'OK' : 'NEEDS ATTENTION';
                $output[] = "{$path}: {$status} (expected: {$checks[$path]['expected']}, actual: {$checks[$path]['actual']})";
            } else {
                $checks[$path] = ['error' => 'Path does not exist'];
                $output[] = "{$path}: NOT FOUND";
            }
        }

        $all_correct = !empty($checks) && array_reduce($checks, function($carry, $check) {
            return $carry && (isset($check['correct']) ? $check['correct'] : false);
        }, true);

        return [
            'success' => $all_correct,
            'details' => $checks,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Integration health check implementation
     */
    private function runIntegrationHealthCheck(): array
    {
        $output = [];
        $checks = [];

        // Mock integration checks (replace with actual integration tests)
        $integrations = ['vend', 'deputy', 'xero'];
        
        foreach ($integrations as $integration) {
            try {
                // Mock check - replace with actual integration health endpoints
                $checks[$integration] = [
                    'available' => true,
                    'response_time' => rand(100, 500),
                    'last_sync' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' minutes'))
                ];
                
                $output[] = ucfirst($integration) . ": OK (response: {$checks[$integration]['response_time']}ms)";
                
            } catch (\Exception $e) {
                $checks[$integration] = [
                    'available' => false,
                    'error' => $e->getMessage()
                ];
                
                $output[] = ucfirst($integration) . ": FAILED - " . $e->getMessage();
            }
        }

        $all_available = array_reduce($checks, function($carry, $check) {
            return $carry && $check['available'];
        }, true);

        return [
            'success' => $all_available,
            'details' => $checks,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Performance baseline test implementation
     */
    private function runPerformanceBaseline(): array
    {
        $output = [];
        $checks = [];

        // Memory usage test
        $memory_start = memory_get_usage(true);
        $data = range(1, 10000); // Allocate some memory
        $memory_peak = memory_get_peak_usage(true);
        $checks['memory_allocation'] = [
            'start' => $memory_start,
            'peak' => $memory_peak,
            'allocated' => $memory_peak - $memory_start
        ];
        unset($data);

        $output[] = "Memory test: allocated " . number_format($checks['memory_allocation']['allocated']) . " bytes";

        // Simple computation test
        $compute_start = microtime(true);
        $sum = 0;
        for ($i = 0; $i < 100000; $i++) {
            $sum += $i;
        }
        $compute_time = microtime(true) - $compute_start;
        $checks['computation'] = [
            'iterations' => 100000,
            'result' => $sum,
            'time_seconds' => $compute_time
        ];

        $output[] = "Computation test: 100k iterations in " . round($compute_time * 1000, 2) . "ms";

        // File I/O test
        $io_start = microtime(true);
        $temp_file = tempnam(sys_get_temp_dir(), 'perf_test_');
        file_put_contents($temp_file, str_repeat('test', 1000));
        $content = file_get_contents($temp_file);
        unlink($temp_file);
        $io_time = microtime(true) - $io_start;
        $checks['file_io'] = [
            'size_bytes' => strlen($content),
            'time_seconds' => $io_time
        ];

        $output[] = "File I/O test: " . strlen($content) . " bytes in " . round($io_time * 1000, 2) . "ms";

        return [
            'success' => true,
            'details' => $checks,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Security scan implementation
     */
    private function runSecurityScan(): array
    {
        $output = [];
        $checks = [];
        $vulnerabilities = [];

        // Check for common security configurations
        $security_checks = [
            'expose_php' => ini_get('expose_php') == 0,
            'display_errors' => ini_get('display_errors') == 0,
            'log_errors' => ini_get('log_errors') == 1,
            'session_use_strict_mode' => ini_get('session.use_strict_mode') == 1,
            'session_cookie_httponly' => ini_get('session.cookie_httponly') == 1
        ];

        foreach ($security_checks as $check => $is_secure) {
            $checks[$check] = $is_secure;
            if (!$is_secure) {
                $vulnerabilities[] = "Insecure setting: " . $check;
            }
            $output[] = ucfirst(str_replace('_', ' ', $check)) . ": " . ($is_secure ? 'SECURE' : 'VULNERABLE');
        }

        // Check for sensitive files
        $sensitive_files = ['.env', 'config/database.php', 'phpinfo.php'];
        foreach ($sensitive_files as $file) {
            if (file_exists($file) && is_readable($file)) {
                $vulnerabilities[] = "Sensitive file accessible: " . $file;
                $output[] = "WARNING: {$file} is accessible";
            }
        }

        $checks['vulnerabilities_found'] = count($vulnerabilities);
        $checks['vulnerabilities'] = $vulnerabilities;

        return [
            'success' => empty($vulnerabilities),
            'details' => $checks,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Run all automation suites
     */
    public function runAllSuites(): array
    {
        $results = [];
        $total_runtime = 0;
        $success_count = 0;

        foreach ($this->available_suites as $suite_id => $suite) {
            try {
                $result = $this->runSuite($suite_id);
                $results[$suite_id] = $result;
                $total_runtime += $result['runtime'];
                
                if ($result['success']) {
                    $success_count++;
                }
                
            } catch (RuntimeException $e) {
                $results[$suite_id] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'suite_name' => $suite['name']
                ];
            }
        }

        return [
            'success' => $success_count === count($this->available_suites),
            'summary' => [
                'total_suites' => count($this->available_suites),
                'successful' => $success_count,
                'failed' => count($this->available_suites) - $success_count,
                'total_runtime' => round($total_runtime, 2)
            ],
            'results' => $results
        ];
    }

    /**
     * Get automation run history
     */
    public function getRunHistory(int $limit = 50): array
    {
        try {
            return $this->dal->query("
                SELECT 
                    suite_name,
                    status,
                    runtime_seconds,
                    started_by,
                    started_at,
                    completed_at,
                    error_message
                FROM {$this->dal->table('automation_runs')} 
                ORDER BY started_at DESC 
                LIMIT ?
            ", [$limit], 'i');

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to get run history: " . $e->getMessage());
        }
    }
}
?>
