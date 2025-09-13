<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Shared\Config\ConfigService;
use App\Shared\Logging\Logger;

/**
 * Seeds Controller - Test User & Data Seeding Management
 * 
 * Admin-only interface for managing test users, roles, and sample data
 * with idempotent operations and comprehensive reporting.
 * 
 * @author CIS V2 System
 * @version 2.0.0-alpha.2
 * @last_modified 2025-09-09T15:20:00Z
 */
class SeedsController extends BaseController
{
    private Logger $logger;
    private bool $seedingEnabled;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = Logger::getInstance();
        $this->seedingEnabled = ConfigService::get('tools.seed.enabled', true);
    }
    
    /**
     * Show seeding dashboard
     */
    public function index(): void
    {
        // Check admin permission
        if (!$this->hasPermission('admin')) {
            $this->redirect('/dashboard');
            return;
        }
        
        $data = [
            'seeding_enabled' => $this->seedingEnabled,
            'test_user_email' => $_ENV['TEST_USER_EMAIL'] ?? 'pearce.stephens@gmail.com',
            'environment' => $_ENV['APP_ENV'] ?? 'development',
            'current_users' => $this->getCurrentTestUsers()
        ];
        
        $this->render('admin/tools/seed', $data);
    }
    
    /**
     * Run test users seeding
     */
    public function seedTestUsers(): void
    {
        header('Content-Type: application/json');
        
        // Check admin permission
        if (!$this->hasPermission('admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        if (!$this->seedingEnabled) {
            echo json_encode(['success' => false, 'error' => 'Seeding is disabled']);
            return;
        }
        
        try {
            // Load and run migration
            require_once __DIR__ . '/../../../migrations/20250909_151500_seed_test_roles_users.php';
            
            $migration = new \SeedTestRolesUsers();
            $result = $migration->up();
            
            // Add timestamp and environment info
            $result['timestamp'] = date('Y-m-d H:i:s T');
            $result['environment'] = $_ENV['APP_ENV'] ?? 'development';
            $result['seeder_version'] = '2.0.0-alpha.2';
            
            $this->logger->info('Test users seeding executed', [
                'component' => 'seeds_controller',
                'action' => 'seed_test_users',
                'result' => [
                    'success' => $result['success'],
                    'inserted' => $result['inserted'],
                    'updated' => $result['updated']
                ]
            ]);
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            $this->logger->error('Seeding failed', [
                'component' => 'seeds_controller',
                'action' => 'seed_test_users',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s T')
            ]);
        }
    }
    
    /**
     * Reset test user passwords
     */
    public function resetTestPasswords(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        if (!$this->seedingEnabled) {
            echo json_encode(['success' => false, 'error' => 'Seeding is disabled']);
            return;
        }
        
        try {
            // This essentially re-runs the seeding which updates passwords
            require_once __DIR__ . '/../../../migrations/20250909_151500_seed_test_roles_users.php';
            
            $migration = new \SeedTestRolesUsers();
            $result = $migration->up();
            
            // Modify result for password reset context
            $result['action'] = 'password_reset';
            $result['reset_count'] = $result['updated'] + $result['inserted'];
            $result['timestamp'] = date('Y-m-d H:i:s T');
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s T')
            ]);
        }
    }
    
    /**
     * Show current test users
     */
    public function showCurrentUsers(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        try {
            $users = $this->getCurrentTestUsers();
            
            echo json_encode([
                'success' => true,
                'users' => $users,
                'count' => count($users),
                'timestamp' => date('Y-m-d H:i:s T')
            ], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s T')
            ]);
        }
    }
    
    /**
     * Password hash utility
     */
    public function hashPassword(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->hasPermission('admin')) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        $plain = $_GET['plain'] ?? '';
        
        if (empty($plain)) {
            echo json_encode([
                'success' => false,
                'error' => 'Parameter "plain" is required'
            ]);
            return;
        }
        
        try {
            $hash = password_hash($plain, PASSWORD_DEFAULT);
            $info = password_get_info($hash);
            
            // Never log the plain password
            $this->logger->info('Password hash generated', [
                'component' => 'seeds_controller',
                'action' => 'hash_password',
                'algorithm' => $info['algo'],
                'cost' => $info['options']['cost'] ?? null
            ]);
            
            echo json_encode([
                'success' => true,
                'algorithm' => password_algos()[$info['algo']] ?? 'Unknown',
                'algo_id' => $info['algo'],
                'bcrypt_cost' => $info['options']['cost'] ?? null,
                'hash' => $hash,
                'hash_length' => strlen($hash),
                'timestamp' => date('Y-m-d H:i:s T')
            ], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s T')
            ]);
        }
    }
    
    /**
     * Get current test users from database
     */
    private function getCurrentTestUsers(): array
    {
        try {
            $testEmails = [
                $_ENV['TEST_USER_EMAIL'] ?? 'pearce.stephens@gmail.com',
                $_ENV['TEST_MANAGER_EMAIL'] ?? 'manager.cis@test.local',
                $_ENV['TEST_STAFF_EMAIL'] ?? 'staff.cis@test.local',
                $_ENV['TEST_VIEWER_EMAIL'] ?? 'viewer.cis@test.local'
            ];
            
            $placeholders = str_repeat('?,', count($testEmails) - 1) . '?';
            $sql = "SELECT id, name, email, role, status, login_attempts, 
                           locked_until, must_change_password, last_login, 
                           created_at, updated_at 
                    FROM users 
                    WHERE email IN ($placeholders)
                    ORDER BY 
                        CASE role 
                            WHEN 'admin' THEN 1 
                            WHEN 'manager' THEN 2 
                            WHEN 'staff' THEN 3 
                            WHEN 'viewer' THEN 4 
                            ELSE 5 
                        END";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($testEmails);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Mask email addresses for display
            foreach ($users as &$user) {
                $user['email_masked'] = $this->maskEmail($user['email']);
                $user['is_locked'] = !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
                $user['days_since_login'] = $user['last_login'] ? 
                    floor((time() - strtotime($user['last_login'])) / 86400) : null;
            }
            
            return $users;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get current test users', [
                'component' => 'seeds_controller',
                'action' => 'get_current_users',
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Mask email for display
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
        
        return false; // Only admin can seed
    }
}
