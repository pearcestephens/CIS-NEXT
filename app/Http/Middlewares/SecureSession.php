<?php
/**
 * Hardened Session Management
 * File: app/Http/Middlewares/SecureSession.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Advanced session security with rotation, strict cookies, and privilege tracking
 */

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Shared\Logging\Logger;

class SecureSession {
    
    private Logger $logger;
    private array $config;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->config = [
            'session_name' => 'CIS_SESSION',
            'session_lifetime' => 3600,        // 1 hour
            'session_regenerate_threshold' => 300, // 5 minutes
            'privilege_change_regenerate' => true,
            'strict_ip_binding' => true,
            'user_agent_validation' => true,
            'session_fingerprint' => true,
            'secure_cookies' => true,
            'same_site' => 'Strict',
            'http_only' => true,
            'max_concurrent_sessions' => 3,
            'session_timeout_warning' => 300   // 5 minutes before expiry
        ];
        
        $this->configureSessionSecurity();
    }
    
    /**
     * Configure session security settings
     */
    private function configureSessionSecurity(): void {
        // Session configuration
        ini_set('session.name', $this->config['session_name']);
        ini_set('session.cookie_lifetime', (string)$this->config['session_lifetime']);
        ini_set('session.cookie_httponly', $this->config['http_only'] ? '1' : '0');
        ini_set('session.cookie_secure', $this->config['secure_cookies'] ? '1' : '0');
        ini_set('session.cookie_samesite', $this->config['same_site']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.entropy_length', '32');
        ini_set('session.hash_function', 'sha256');
        ini_set('session.gc_maxlifetime', (string)$this->config['session_lifetime']);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        
        // Session save path (secure directory)
        $sessionPath = __DIR__ . '/../../../var/sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0700, true);
        }
        ini_set('session.save_path', $sessionPath);
    }
    
    /**
     * Session middleware handler
     */
    public function handle($request, $next) {
        $this->startSecureSession();
        
        $response = $next($request);
        
        $this->finalizeSession();
        
        return $response;
    }
    
    /**
     * Start secure session with validation
     */
    private function startSecureSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        session_start();
        
        // Validate existing session
        if ($this->isSessionValid()) {
            $this->updateSessionActivity();
            
            // Check if session needs regeneration
            if ($this->shouldRegenerateSession()) {
                $this->regenerateSession(false);
            }
        } else {
            $this->destroySession();
            $this->startNewSession();
        }
        
        // Set security headers for session management
        $this->setSessionSecurityHeaders();
    }
    
    /**
     * Validate session integrity and security
     */
    private function isSessionValid(): bool {
        // Check if session is initialized
        if (!isset($_SESSION['initialized'])) {
            return false;
        }
        
        // Check session expiry
        if (isset($_SESSION['expires_at']) && $_SESSION['expires_at'] < time()) {
            $this->logger->info('Session expired', [
                'session_id' => session_id(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'expired_at' => $_SESSION['expires_at']
            ]);
            return false;
        }
        
        // Validate session fingerprint
        if ($this->config['session_fingerprint'] && !$this->validateFingerprint()) {
            $this->logger->warning('Session fingerprint mismatch', [
                'session_id' => session_id(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return false;
        }
        
        // Validate IP binding
        if ($this->config['strict_ip_binding'] && !$this->validateIpBinding()) {
            $this->logger->warning('Session IP mismatch', [
                'session_id' => session_id(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'session_ip' => $_SESSION['client_ip'] ?? 'unknown',
                'current_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return false;
        }
        
        // Validate User-Agent
        if ($this->config['user_agent_validation'] && !$this->validateUserAgent()) {
            $this->logger->warning('Session User-Agent mismatch', [
                'session_id' => session_id(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate session fingerprint
     */
    private function validateFingerprint(): bool {
        if (!isset($_SESSION['fingerprint'])) {
            return false;
        }
        
        $currentFingerprint = $this->generateFingerprint();
        return hash_equals($_SESSION['fingerprint'], $currentFingerprint);
    }
    
    /**
     * Generate session fingerprint
     */
    private function generateFingerprint(): string {
        $components = [
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    /**
     * Validate IP binding
     */
    private function validateIpBinding(): bool {
        if (!isset($_SESSION['client_ip'])) {
            return false;
        }
        
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        return $_SESSION['client_ip'] === $currentIp;
    }
    
    /**
     * Validate User-Agent
     */
    private function validateUserAgent(): bool {
        if (!isset($_SESSION['user_agent_hash'])) {
            return false;
        }
        
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $currentHash = hash('sha256', $currentUserAgent);
        
        return hash_equals($_SESSION['user_agent_hash'], $currentHash);
    }
    
    /**
     * Check if session should be regenerated
     */
    private function shouldRegenerateSession(): bool {
        // Regenerate based on time threshold
        if (isset($_SESSION['last_regenerated'])) {
            $timeSinceRegen = time() - $_SESSION['last_regenerated'];
            if ($timeSinceRegen > $this->config['session_regenerate_threshold']) {
                return true;
            }
        }
        
        // Force regeneration if not set
        if (!isset($_SESSION['last_regenerated'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Regenerate session ID
     */
    public function regenerateSession(bool $privilegeChange = false): void {
        $oldSessionId = session_id();
        $userId = $_SESSION['user_id'] ?? null;
        
        // Regenerate session ID
        session_regenerate_id(true);
        
        // Update session metadata
        $_SESSION['last_regenerated'] = time();
        $_SESSION['regeneration_count'] = ($_SESSION['regeneration_count'] ?? 0) + 1;
        
        if ($privilegeChange) {
            $_SESSION['privilege_changed_at'] = time();
        }
        
        $this->logger->info('Session regenerated', [
            'old_session_id' => $oldSessionId,
            'new_session_id' => session_id(),
            'user_id' => $userId,
            'privilege_change' => $privilegeChange,
            'regeneration_count' => $_SESSION['regeneration_count']
        ]);
    }
    
    /**
     * Start new session
     */
    private function startNewSession(): void {
        session_regenerate_id(true);
        
        $_SESSION['initialized'] = true;
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['last_regenerated'] = time();
        $_SESSION['expires_at'] = time() + $this->config['session_lifetime'];
        $_SESSION['client_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $_SESSION['fingerprint'] = $this->generateFingerprint();
        $_SESSION['regeneration_count'] = 0;
        
        $this->logger->info('New session started', [
            'session_id' => session_id(),
            'client_ip' => $_SESSION['client_ip']
        ]);
    }
    
    /**
     * Update session activity
     */
    private function updateSessionActivity(): void {
        $_SESSION['last_activity'] = time();
        $_SESSION['expires_at'] = time() + $this->config['session_lifetime'];
        
        // Update session in database if user is logged in
        if (isset($_SESSION['user_id'])) {
            $this->updateSessionInDatabase();
        }
    }
    
    /**
     * Update session record in database
     */
    private function updateSessionInDatabase(): void {
        global $mysqli;
        
        if (!$mysqli) {
            return;
        }
        
        $stmt = $mysqli->prepare("
            UPDATE cis_sessions 
            SET last_activity = NOW(), 
                expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                ip_address = ?,
                user_agent = ?
            WHERE session_id = ? AND user_id = ?
        ");
        
        if ($stmt) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            
            $stmt->bind_param(
                'isssi',
                $this->config['session_lifetime'],
                $clientIp,
                $userAgent,
                session_id(),
                $_SESSION['user_id']
            );
            
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Handle user login and create session record
     */
    public function handleLogin(int $userId, array $userData): void {
        // Regenerate session on login (privilege change)
        $this->regenerateSession(true);
        
        // Set session data
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $userData['email'] ?? '';
        $_SESSION['role_id'] = $userData['role_id'] ?? 0;
        $_SESSION['logged_in_at'] = time();
        $_SESSION['last_privilege_change'] = time();
        
        // Create session record in database
        $this->createSessionRecord($userId);
        
        // Clean up old sessions for this user
        $this->cleanupOldUserSessions($userId);
        
        $this->logger->info('User logged in', [
            'user_id' => $userId,
            'session_id' => session_id(),
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }
    
    /**
     * Handle user logout
     */
    public function handleLogout(): void {
        $userId = $_SESSION['user_id'] ?? null;
        $sessionId = session_id();
        
        // Remove session from database
        if ($userId) {
            $this->removeSessionRecord($sessionId, $userId);
        }
        
        $this->logger->info('User logged out', [
            'user_id' => $userId,
            'session_id' => $sessionId
        ]);
        
        $this->destroySession();
    }
    
    /**
     * Handle privilege changes (role updates, permissions changes)
     */
    public function handlePrivilegeChange(): void {
        if ($this->config['privilege_change_regenerate']) {
            $this->regenerateSession(true);
            
            $this->logger->info('Session regenerated due to privilege change', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'session_id' => session_id()
            ]);
        }
    }
    
    /**
     * Create session record in database
     */
    private function createSessionRecord(int $userId): void {
        global $mysqli;
        
        if (!$mysqli) {
            return;
        }
        
        $stmt = $mysqli->prepare("
            INSERT INTO cis_sessions (
                session_id, user_id, ip_address, user_agent, 
                created_at, last_activity, expires_at
            ) VALUES (?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
        ");
        
        if ($stmt) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            
            $stmt->bind_param(
                'sissi',
                session_id(),
                $userId,
                $clientIp,
                $userAgent,
                $this->config['session_lifetime']
            );
            
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Remove session record from database
     */
    private function removeSessionRecord(string $sessionId, int $userId): void {
        global $mysqli;
        
        if (!$mysqli) {
            return;
        }
        
        $stmt = $mysqli->prepare("DELETE FROM cis_sessions WHERE session_id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $sessionId, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Clean up old sessions for user (enforce concurrent session limit)
     */
    private function cleanupOldUserSessions(int $userId): void {
        global $mysqli;
        
        if (!$mysqli) {
            return;
        }
        
        // Keep only the most recent sessions up to the limit
        $stmt = $mysqli->prepare("
            DELETE FROM cis_sessions 
            WHERE user_id = ? 
            AND session_id NOT IN (
                SELECT session_id FROM (
                    SELECT session_id 
                    FROM cis_sessions 
                    WHERE user_id = ? 
                    ORDER BY last_activity DESC 
                    LIMIT ?
                ) AS recent_sessions
            )
        ");
        
        if ($stmt) {
            $stmt->bind_param('iii', $userId, $userId, $this->config['max_concurrent_sessions']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Destroy session completely
     */
    private function destroySession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        
        // Clear session cookie
        if (isset($_COOKIE[$this->config['session_name']])) {
            setcookie(
                $this->config['session_name'],
                '',
                time() - 3600,
                '/',
                '',
                $this->config['secure_cookies'],
                $this->config['http_only']
            );
        }
    }
    
    /**
     * Set session security headers
     */
    private function setSessionSecurityHeaders(): void {
        // Prevent session fixation
        header('X-Session-Timeout: ' . $this->config['session_lifetime']);
        
        if (isset($_SESSION['expires_at'])) {
            $remaining = $_SESSION['expires_at'] - time();
            header('X-Session-Remaining: ' . max(0, $remaining));
            
            // Warning header if session expires soon
            if ($remaining <= $this->config['session_timeout_warning']) {
                header('X-Session-Warning: expires-soon');
            }
        }
    }
    
    /**
     * Finalize session processing
     */
    private function finalizeSession(): void {
        // Session is automatically saved by PHP
        // Additional cleanup can be done here if needed
    }
    
    /**
     * Get session statistics
     */
    public function getSessionStats(): array {
        return [
            'session_id' => session_id(),
            'created_at' => $_SESSION['created_at'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'expires_at' => $_SESSION['expires_at'] ?? null,
            'regeneration_count' => $_SESSION['regeneration_count'] ?? 0,
            'user_id' => $_SESSION['user_id'] ?? null,
            'logged_in_at' => $_SESSION['logged_in_at'] ?? null,
            'last_privilege_change' => $_SESSION['last_privilege_change'] ?? null
        ];
    }
}
