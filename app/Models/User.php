<?php
/**
 * CIS - Central Information System
 * app/Models/User.php
 * 
 * User model for handling user authentication, session management,
 * and security operations in pure MVC pattern.
 *
 * @package CIS
 * @version 1.0.0
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

declare(strict_types=1);

namespace App\Models;

use App\Shared\Logging\Logger;
use Exception;

class User extends BaseModel
{
    protected string $table = 'users';
    private Logger $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = Logger::getInstance();
    }
    
    protected function getTable(): string
    {
        return $this->db->table($this->table);
    }

    /**
     * Attempt to authenticate a user with email and password
     */
    public function authenticate(string $email, string $password): array
    {
        $startTime = microtime(true);
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        try {
            // Find user by email
            $user = $this->findUserByEmail($email);
            if (!$user) {
                $this->logger->warning('Authentication attempt for non-existent user', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'request_id' => $requestId
                ]);

                return [
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_INVALID_CREDENTIALS',
                        'message' => 'Invalid email or password',
                        'details' => []
                    ],
                    'request_id' => $requestId
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->logger->warning('Authentication attempt with invalid password', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'request_id' => $requestId
                ]);

                return [
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_INVALID_CREDENTIALS',
                        'message' => 'Invalid email or password',
                        'details' => []
                    ],
                    'request_id' => $requestId
                ];
            }

            // Load user permissions
            $user['permissions'] = $this->loadUserPermissions($user['id']);

            // Create session
            $sessionResult = $this->createSession($user);
            if (!$sessionResult['success']) {
                return $sessionResult;
            }

            $duration = microtime(true) - $startTime;
            $this->logger->info('User authenticated successfully', [
                'user_id' => $user['id'],
                'email' => $email,
                'role' => $user['role'],
                'session_id' => $sessionResult['data']['session_id'],
                'duration_ms' => round($duration * 1000, 2),
                'request_id' => $requestId
            ]);

            return [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'permissions' => $user['permissions'],
                        'created_at' => $user['created_at']
                    ],
                    'session_id' => $sessionResult['data']['session_id']
                ],
                'meta' => [
                    'duration_ms' => round($duration * 1000, 2)
                ],
                'request_id' => $requestId
            ];

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logger->error('Authentication error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round($duration * 1000, 2),
                'request_id' => $requestId
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'AUTH_ERROR',
                    'message' => 'An error occurred during authentication',
                    'details' => $this->config['debug'] ? ['exception' => $e->getMessage()] : []
                ],
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Find user by email with role information
     */
    private function findUserByEmail(string $email): ?array
    {
        try {
            $sql = "
                SELECT u.id, u.email, u.password_hash, u.role_id, u.created_at, u.updated_at,
                       r.name as role_name, r.description as role_description
                FROM {users} u
                LEFT JOIN {roles} r ON u.role_id = r.id
                WHERE u.email = ? AND u.is_active = 1
                LIMIT 1
            ";
            
            $result = $this->query($sql, [$email]);
            $user = $result->fetch();
            
            return $user ?: null;
            
        } catch (Exception $e) {
            $this->logger->error('Error finding user by email', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Load user permissions based on role
     */
    private function loadUserPermissions(int $userId): array
    {
        try {
            $sql = "
                SELECT DISTINCT p.name as permission
                FROM {users} u
                JOIN {roles} r ON u.role_id = r.id
                JOIN {role_permissions} rp ON r.id = rp.role_id
                JOIN {permissions} p ON rp.permission_id = p.id
                WHERE u.id = ? AND u.is_active = 1
            ";
            
            $result = $this->query($sql, [$userId]);
            $permissions = [];
            
            while ($row = $result->fetch()) {
                $permissions[] = $row['permission'];
            }
            
            return $permissions;
            
        } catch (Exception $e) {
            $this->logger->error('Error loading user permissions', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Create a new session for authenticated user
     */
    private function createSession(array $user): array
    {
        try {
            session_start();
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            $sessionId = session_id();
            
            // Store user data in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['permissions'] = $user['permissions'] ?? [];
            $_SESSION['authenticated'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Save session to database
            $this->saveSessionToDatabase($sessionId, $user['id']);
            
            return [
                'success' => true,
                'data' => [
                    'session_id' => $sessionId
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Error creating session', [
                'user_id' => $user['id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'SESSION_ERROR',
                    'message' => 'Failed to create session',
                    'details' => []
                ]
            ];
        }
    }

    /**
     * Save session information to database
     */
    private function saveSessionToDatabase(string $sessionId, int $userId): void
    {
        try {
            $sql = "
                INSERT INTO {user_sessions} (session_id, user_id, ip_address, user_agent, created_at, last_activity)
                VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                last_activity = NOW(),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent)
            ";
            
            $this->query($sql, [
                $sessionId,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Error saving session to database', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            return false;
        }

        // Check session timeout
        $timeout = $this->config['session_timeout'] ?? 3600; // 1 hour default
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
            $this->logout();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'permissions' => $_SESSION['permissions'] ?? [],
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null
        ];
    }

    /**
     * Check if current user has permission
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $permissions = $_SESSION['permissions'] ?? [];
        return in_array($permission, $permissions, true);
    }

    /**
     * Check if current user has role
     */
    public function hasRole(string $role): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        return ($_SESSION['user_role'] ?? '') === $role;
    }

    /**
     * Logout user and destroy session
     */
    public function logout(): bool
    {
        try {
            $sessionId = session_id();
            
            // Remove session from database
            if ($sessionId) {
                $sql = "DELETE FROM {user_sessions} WHERE session_id = ?";
                $this->query($sql, [$sessionId]);
            }
            
            // Clear session data
            $_SESSION = [];
            
            // Destroy session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy session
            session_destroy();
            
            $this->logger->info('User logged out successfully', [
                'session_id' => $sessionId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Error during logout', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Check if testing bypass is enabled
     */
    private function checkTestingBypass(): bool
    {
        // Only allow in development
        if (($this->config['environment'] ?? 'production') !== 'development') {
            return false;
        }

        // Check for bypass parameter
        if (!isset($_GET['bypass']) || $_GET['bypass'] !== 'enable') {
            return false;
        }

        return true;
    }

    /**
     * Get bypass user data
     */
    private function getBypassUser(): ?array
    {
        if (!$this->checkTestingBypass()) {
            return null;
        }

        // Include bypass configuration
        $bypassFile = __DIR__ . '/../../functions/config.php';
        if (file_exists($bypassFile)) {
            require_once $bypassFile;
            
            if (class_exists('TestingBypass')) {
                $user = \TestingBypass::getCurrentUser();
                if ($user && isset($_SESSION['bot_auth'])) {
                    // Return bot user data
                    return \TestingBypass::getBotUser();
                }
                return $user;
            }
        }
        
        return null;
    }
}
