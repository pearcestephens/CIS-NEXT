<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;

/**
 * Authentication Controller - Simplified Version
 * Handles user login and logout without dependency injection issues
 */
class AuthController extends BaseController
{
    private User $userModel;
    
    public function __construct()
    {
        $this->userModel = new User();
    }
    
    public function showLogin($params = [], $request = null): array
    {
        // Convert request to array if it's an object
        if (is_object($request)) {
            $request = (array) $request;
        }
        if (!is_array($request)) {
            $request = [];
        }
        
        // Redirect if already authenticated  
        if ($this->getCurrentUser()) {
            return $this->redirect('/admin');
        }
        
        return $this->view('auth/login', [
            'title' => 'Login - CIS',
            'csrf_token' => $this->generateCSRFToken(),
        ]);
    }
    
    public function login($params = [], $request = null): array
    {
        try {
            // Convert request to array if it's an object
            if (is_object($request)) {
                $request = (array) $request;
            }
            if (!is_array($request)) {
                $request = [];
            }
            
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validate input
            if (empty($email) || empty($password)) {
                return $this->json(['message' => 'Email and password are required'], 400);
            }
            
            // Authenticate using User model
            $result = $this->userModel->authenticate($email, $password);
            
            if (!$result['success']) {
                return $this->json(['message' => $result['error']['message'] ?? 'Authentication failed'], 401);
            }
            
            // Start session for web login
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $result['data']['user']['id'];
            $_SESSION['user_email'] = $result['data']['user']['email'];
            $_SESSION['role_id'] = $result['data']['user']['role'] ?? 'user';
            $_SESSION['permissions'] = $result['data']['user']['permissions'] ?? [];
            
            session_write_close();
            
            // Success response
            return $this->json([
                'message' => 'Login successful',
                'user' => $result['data']['user'],
                'redirect' => '/admin'
            ]);
            
        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return $this->json(['message' => 'Login failed'], 500);
        }
    }
    
    public function logout(array $params, array $request): array
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            session_destroy();
            
            return $this->redirect('/login');
            
        } catch (\Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return $this->redirect('/login');
        }
    }
    
    private function generateCSRFToken(): string
    {
        // Session already managed by middleware, just generate token
        $token = bin2hex(random_bytes(32));
        
        // Reopen session briefly to store token
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $_SESSION['csrf_token'] = $token;
        session_write_close(); // Close immediately
        
        return $token;
    }
    
    protected function redirect(string $path): array
    {
        return [
            'redirect' => $path
        ];
    }
}
