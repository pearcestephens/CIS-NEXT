<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Shared\Logging\Logger;

/**
 * Automation Controller - System Automation & Testing Suite
 * 
 * Admin interface for running comprehensive system verification,
 * automated testing, and validation suites.
 * 
 * @version 2.0.0-alpha.2
 */
class AutomationController extends BaseController
{
    private Logger $logger;
    private array $automationSuites;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = Logger::getInstance();
        $this->automationSuites = [
            'comprehensive' => [
                'name' => 'Comprehensive Suite',
                'description' => 'Full system verification with test accounts',
                'script' => 'enhanced_automation_with_seeds.php',
                'estimated_time' => '30-60 seconds'
            ],
            'basic' => [
                'name' => 'Basic Suite',
                'description' => 'Core system checks without seeding',
                'script' => 'ultimate_automation_suite.php',
                'estimated_time' => '15-30 seconds'
            ],
            'seeding' => [
                'name' => 'Test Data Seeding',
                'description' => 'Seed test accounts and sample data',
                'script' => '../seed_test_users.php',
                'estimated_time' => '5-10 seconds'
            ]
        ];
    }
    
    /**
     * Show automation dashboard
     */
    public function index(): void
    {
        if (!$this->hasPermission('admin')) {
            $this->redirect('/dashboard');
            return;
        }
        
        $data = [
            'automation_suites' => $this->automationSuites,
            'recent_runs' => $this->getRecentRuns(),
            'system_status' => $this->getSystemStatus()
        ];
        
        $this->render('admin/tools/automation', $data);
    }
    
    /**
     * Run automation suite
     */
    public function run(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        $suite = $_POST['suite'] ?? 'comprehensive';
        
        if (!isset($this->automationSuites[$suite])) {
            echo json_encode(['success' => false, 'error' => 'Invalid automation suite']);
            return;
        }
        
        try {
            $startTime = microtime(true);
            $suiteConfig = $this->automationSuites[$suite];
            
            $this->logger->info('Automation suite started', [
                'component' => 'automation_controller',
                'suite' => $suite,
                'script' => $suiteConfig['script']
            ]);
            
            // Execute automation suite
            $result = $this->executeSuite($suite, $suiteConfig);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log the run
            $this->logger->info('Automation suite completed', [
                'component' => 'automation_controller',
                'suite' => $suite,
                'success' => $result['success'],
                'execution_time_ms' => $executionTime
            ]);
            
            // Store run record
            $this->storeRunRecord($suite, $result, $executionTime);
            
            $response = [
                'success' => true,
                'suite' => $suite,
                'suite_name' => $suiteConfig['name'],
                'execution_time_ms' => $executionTime,
                'timestamp' => date('Y-m-d H:i:s T'),
                'result' => $result
            ];
            
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            $this->logger->error('Automation suite failed', [
                'component' => 'automation_controller',
                'suite' => $suite,
                'error' => $e->getMessage()
            ]);
            
            echo json_encode([
                'success' => false,
                'suite' => $suite,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s T')
            ]);
        }
    }
    
    /**
     * Get system status for dashboard
     */
    public function status(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        try {
            echo json_encode([
                'success' => true,
                'system_status' => $this->getSystemStatus(),
                'timestamp' => date('Y-m-d H:i:s T')
            ], JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s T')
            ]);
        }
    }
    
    /**
     * Execute automation suite
     */
    private function executeSuite(string $suite, array $suiteConfig): array
    {
        $scriptPath = __DIR__ . '/../../../tools/automation/' . $suiteConfig['script'];
        
        // Handle special case for seeding script
        if ($suite === 'seeding') {
            $scriptPath = __DIR__ . '/../../../tools/seed_test_users.php';
        }
        
        if (!file_exists($scriptPath)) {
            throw new Exception("Automation script not found: {$suiteConfig['script']}");
        }
        
        // Capture output and result
        ob_start();
        
        try {
            // Set up environment for automation script
            $_GET['action'] = ($suite === 'seeding') ? 'seed' : 'run';
            
            // Include and execute script
            $result = include $scriptPath;
            
            $output = ob_get_clean();
            
            // If result is not explicitly returned, parse output
            if (!is_array($result)) {
                $result = $this->parseAutomationOutput($output);
            }
            
            return [
                'success' => true,
                'output' => $output,
                'parsed_result' => $result,
                'script_path' => basename($scriptPath)
            ];
            
        } catch (Exception $e) {
            $output = ob_get_clean();
            
            throw new Exception("Script execution failed: " . $e->getMessage() . "\nOutput: " . $output);
        }
    }
    
    /**
     * Parse automation output for structured data
     */
    private function parseAutomationOutput(string $output): array
    {
        // Try to extract JSON from output
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Fallback parsing
        return [
            'raw_output' => $output,
            'parsed' => false,
            'success' => strpos($output, 'error') === false && strpos($output, 'Error') === false
        ];
    }
    
    /**
     * Get system status information
     */
    private function getSystemStatus(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'server_time' => date('Y-m-d H:i:s T'),
            'uptime' => $this->getSystemUptime(),
            'disk_free' => disk_free_space(__DIR__),
            'load_average' => sys_getloadavg(),
            'available_suites' => count($this->automationSuites)
        ];
    }
    
    /**
     * Get system uptime (approximate)
     */
    private function getSystemUptime(): string
    {
        if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptimeSeconds = (float) explode(' ', $uptime)[0];
            
            $days = floor($uptimeSeconds / 86400);
            $hours = floor(($uptimeSeconds % 86400) / 3600);
            $minutes = floor(($uptimeSeconds % 3600) / 60);
            
            return sprintf('%dd %dh %dm', $days, $hours, $minutes);
        }
        
        return 'Unknown';
    }
    
    /**
     * Store automation run record
     */
    private function storeRunRecord(string $suite, array $result, float $executionTime): void
    {
        // Store in session for recent runs (could be database in full implementation)
        if (!isset($_SESSION['automation_runs'])) {
            $_SESSION['automation_runs'] = [];
        }
        
        array_unshift($_SESSION['automation_runs'], [
            'suite' => $suite,
            'success' => $result['success'] ?? false,
            'execution_time_ms' => $executionTime,
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => $this->extractSummary($result)
        ]);
        
        // Keep only last 10 runs
        $_SESSION['automation_runs'] = array_slice($_SESSION['automation_runs'], 0, 10);
    }
    
    /**
     * Extract summary from automation result
     */
    private function extractSummary(array $result): string
    {
        if (isset($result['parsed_result']['applied_count'])) {
            return "Applied {$result['parsed_result']['applied_count']} changes";
        }
        
        if (isset($result['parsed_result']['phases'])) {
            $phases = count($result['parsed_result']['phases']);
            return "Completed {$phases} phases";
        }
        
        if (isset($result['success']) && $result['success']) {
            return 'Execution completed successfully';
        }
        
        return 'Execution attempted';
    }
    
    /**
     * Get recent automation runs
     */
    private function getRecentRuns(): array
    {
        return $_SESSION['automation_runs'] ?? [];
    }
    
    /**
     * Check if current user has permission
     */
    private function hasPermission(string $permission): bool
    {
        $user = $_SESSION['user'] ?? null;
        
        if (!$user) {
            return false;
        }
        
        // Admin has all permissions
        if ($user['role'] === 'admin') {
            return true;
        }
        
        return false;
    }
}
