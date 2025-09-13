<?php
/**
 * CIS - Central Information System
 * app/Domain/Services/AuthService.php
 * 
 * Authentication service for handling user login, session management,
 * and security operations.
 *
 * @package CIS
 * @version 1.0.0
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Models\User;
use App\Infra\Persistence\MariaDB\Database;
use App\Shared\Logging\Logger;
use Exception;

class AuthService
{
    private Database $database;
    private Logger $logger;
    private array $config;

    public function __construct(Database $database, Logger $logger, array $config)
    {
        $this->database = $database;
        $this->logger = $logger;
        $this->config = $config;
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
                        'message' => 'Invalid email or password'
                    ],
                    'meta' => ['duration_ms' => round((microtime(true) - $startTime) * 1000, 2)],
                    'request_id' => $requestId
                ];
            }

            // Check if user is active
            if ($user['status'] !== 'active') {
                $this->logger->warning('Authentication attempt for inactive user', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'status' => $user['status'],
                    'request_id' => $requestId
                ]);

                return [
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_ACCOUNT_DISABLED',
                        'message' => 'Account is disabled'
                    ],
                    'meta' => ['duration_ms' => round((microtime(true) - $startTime) * 1000, 2)],
                    'request_id' => $requestId
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->logger->warning('Failed password verification', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'request_id' => $requestId
                ]);

                return [
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_INVALID_CREDENTIALS',
                        'message' => 'Invalid email or password'
                    ],
                    'meta' => ['duration_ms' => round((microtime(true) - $startTime) * 1000, 2)],
                    'request_id' => $requestId
                ];
            }

        // Create session
        $sessionData = $this->createSession($user);

        // Load user permissions
        $permissions = $this->loadUserPermissions($user['role_id']);

        $this->logger->info('User authenticated successfully', [
            'user_id' => $user['id'],
            'email' => $email,
            'session_id' => $sessionData['session_id'],
            'permissions_count' => count($permissions),
            'request_id' => $requestId
        ]);

        return [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role_name'] ?? 'user',
                    'permissions' => $permissions
                ],
                'session' => $sessionData
            ],
            'meta' => ['duration_ms' => round((microtime(true) - $startTime) * 1000, 2)],
            'request_id' => $requestId
        ];        } catch (Exception $e) {
            $this->logger->error('Authentication service error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'AUTH_SERVICE_ERROR',
                    'message' => 'Authentication service unavailable'
                ],
                'meta' => ['duration_ms' => round((microtime(true) - $startTime) * 1000, 2)],
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Create a new session for authenticated user
     */
    private function createSession(array $user): array
    {
        $sessionId = bin2hex(random_bytes(32));
        $sessionLifetime = $this->config['session_lifetime'] ?? 3600; // Default 1 hour
        $expiresAt = date('Y-m-d H:i:s', time() + $sessionLifetime);

        // Store session in database
        $this->database->insert('user_sessions', [
            'session_token' => $sessionId,
            'user_id' => $user['id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => $expiresAt
        ]);

        // Set session in PHP session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['authenticated'] = true;
        $_SESSION['role'] = $user['role_name'] ?? 'user';

        return [
            'session_id' => $sessionId,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Find user by email address
     */
    private function findUserByEmail(string $email): ?array
    {
        return $this->database->execute(
            'SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.email = ?',
            [$email]
        )->fetch();
    }

    /**
     * Load permissions for a given role ID
     */
    private function loadUserPermissions(int $roleId): array
    {
        $result = $this->database->execute(
            'SELECT p.code FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = ?',
            [$roleId]
        );
        
        $permissions = [];
        while ($row = $result->fetch()) {
            $permissions[] = $row['code'];
        }
        
        return $permissions;
    }

    /**
     * Validate current session
     */
    public function validateSession(): array
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        // Check for testing bypass mode first
        if ($this->checkTestingBypass()) {
            $user = $this->getBypassUser();
            if ($user) {
                return [
                    'success' => true,
                    'data' => [
                        'user' => $user,
                        'session' => [
                            'session_token' => $_SESSION['session_id'] ?? 'bypass_session',
                            'expires_at' => date('Y-m-d H:i:s', time() + 3600)
                        ]
                    ],
                    'request_id' => $requestId
                ];
            }
        }

        if (!isset($_SESSION['session_id'], $_SESSION['user_id'])) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'AUTH_NO_SESSION',
                    'message' => 'No active session'
                ],
                'request_id' => $requestId
            ];
        }

        try {
            $session = $this->database
                ->select('user_sessions')
                ->where('session_token', '=', $_SESSION['session_id'])
                ->where('user_id', '=', $_SESSION['user_id'])
                ->first();

            if (!$session) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_INVALID_SESSION',
                        'message' => 'Invalid session'
                    ],
                    'request_id' => $requestId
                ];
            }

            // Check if session expired
            if (strtotime($session['expires_at']) < time()) {
                $this->destroySession();
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_SESSION_EXPIRED',
                        'message' => 'Session has expired'
                    ],
                    'request_id' => $requestId
                ];
            }

            // Get current user data
            $user = $this->database
                ->select('users')
                ->where('id', '=', $session['user_id'])
                ->first();

            if (!$user || $user['status'] !== 'active') {
                $this->destroySession();
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_USER_INACTIVE',
                        'message' => 'User account is inactive'
                    ],
                    'request_id' => $requestId
                ];
            }

            // Update session last activity
            $this->database->update('user_sessions', [
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                ['session_token', '=', $_SESSION['session_id']]
            ]);

            return [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'role' => $user['role']
                    ],
                    'session' => [
                        'session_id' => $session['session_id'],
                        'expires_at' => $session['expires_at']
                    ]
                ],
                'request_id' => $requestId
            ];

        } catch (Exception $e) {
            $this->logger->error('Session validation error', [
                'session_id' => $_SESSION['session_id'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null,
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'AUTH_VALIDATION_ERROR',
                    'message' => 'Session validation failed'
                ],
                'request_id' => $requestId
            ];
        }
    }

    /**
     * Destroy current session
     */
    public function destroySession(): bool
    {
        try {
            if (isset($_SESSION['session_id'])) {
                // Remove from database
                $this->database->delete('user_sessions', [
                    ['session_token', '=', $_SESSION['session_id']]
                ]);
            }

            // Clear PHP session
            session_destroy();
            $_SESSION = [];

            return true;

        } catch (Exception $e) {
            $this->logger->error('Session destruction error', [
                'session_id' => $_SESSION['session_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        try {
            $userPermissions = $this->database->raw("
                SELECT p.name 
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN users u ON u.role = rp.role_name
                WHERE u.id = ? AND u.status = 'active'
            ", [$_SESSION['user_id']]);

            $permissions = array_column($userPermissions, 'name');
            return in_array($permission, $permissions, true);

        } catch (Exception $e) {
            $this->logger->error('Permission check error', [
                'user_id' => $_SESSION['user_id'],
                'permission' => $permission,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get current user's permissions
     */
    public function getUserPermissions(): array
    {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }

        try {
            $userPermissions = $this->database->raw("
                SELECT p.name, p.description 
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN users u ON u.role = rp.role_name
                WHERE u.id = ? AND u.status = 'active'
                ORDER BY p.name
            ", [$_SESSION['user_id']]);

            return $userPermissions;

        } catch (Exception $e) {
            $this->logger->error('Get user permissions error', [
                'user_id' => $_SESSION['user_id'],
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Check if testing bypass mode is enabled
     */
    private function checkTestingBypass(): bool
    {
        // Include bypass configuration if it exists
        $bypassFile = __DIR__ . '/../../../functions/config.php';
        if (file_exists($bypassFile)) {
            require_once $bypassFile;
            
            // Check if TestingBypass class exists and is enabled
            if (class_exists('TestingBypass')) {
                return \TestingBypass::isEnabled();
            }
        }
        
        return false;
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
        $bypassFile = __DIR__ . '/../../../functions/config.php';
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
