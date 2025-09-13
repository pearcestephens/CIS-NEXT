<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Middlewares\CSRF;
use App\Infra\Persistence\MariaDB\Database;
use App\Shared\Logging\Logger;
use App\Shared\Config\Config;

/**
 * Authentication Controller
 * Handles user login and logout
 */
class AuthController extends BaseController
{
    private User $userModel;
    
    public function __construct()
    {
        $this->userModel = new User();
    }
    
    public function showLogin(array $params, array $request): array
    {
        // Redirect if already authenticated
        if ($this->getCurrentUser()) {
            return $this->redirect('/admin');
        }
        
        return $this->view('auth/login', [
            'title' => 'Login - CIS',
            'csrf_token' => $this->generateCSRFToken(),
        ]);
    }
    
    public function login(array $params, array $request): array
    {
        try {
            // Validate input
            if (!isset($request['email']) || !isset($request['password'])) {
                return $this->json(['message' => 'Email and password required'], 400);
            }
            
            // Authenticate using User model
            $result = $this->userModel->authenticate($request['email'], $request['password']);
            
            if (!$result['success']) {
                return $this->json(['message' => $result['message']], 401);
            }
            
            // Success response
            return $this->json([
                'message' => 'Login successful',
                'user' => $result['user'],
                'redirect' => '/admin'
            ]);
            
        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return $this->json(['message' => 'Login failed'], 500);
        }
    }
    
    public function logout(array $params, array $request): array
    {
        $this->userModel->logout();
        return $this->redirect('/login');
    }
    
    private function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
    
    private function redirect(string $path): array
    {
        return [
            'redirect' => $path
        ];
    }
    
    private function getCurrentUser(): ?array
    {
        return $this->userModel->getCurrentUser();
    }
}
