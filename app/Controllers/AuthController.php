<?php
/**
 * Authentication Controller
 * 
 * Handle user authentication for CIS MVC Platform
 * Author: GitHub Copilot
 * Last Modified: <?= date('Y-m-d H:i:s') ?>
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin(): void
    {
        // If already logged in, redirect to dashboard
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
            return;
        }

        $data = [
            'title' => 'Login - CIS MVC Platform',
            'error' => $this->getFlash()['error'] ?? null,
            'success' => $this->getFlash()['success'] ?? null,
        ];

        $this->view('auth.login', $data);
    }

    /**
     * Process login
     */
    public function login(): void
    {
        // Validate CSRF token
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token. Please try again.');
            $this->redirect('/login');
            return;
        }

        // Rate limiting
        $clientIp = $this->security->getClientIp();
        if (!$this->security->checkRateLimit("login_{$clientIp}", 5, 300)) {
            $this->flash('error', 'Too many login attempts. Please try again in 5 minutes.');
            $this->redirect('/login');
            return;
        }

        // Validate input
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ];

        try {
            $input = $this->validate($rules);
        } catch (\Exception $e) {
            $this->flash('error', 'Please provide valid email and password.');
            $this->redirect('/login');
            return;
        }

        $email = $input['email'];
        $password = $input['password'];

        // For demo purposes, create a simple authentication
        // In production, this would check against database
        $validCredentials = [
            'admin@ecigdis.co.nz' => [
                'password' => 'admin123',
                'name' => 'Administrator',
                'role' => 'admin',
                'id' => 1,
            ],
            'user@ecigdis.co.nz' => [
                'password' => 'user123',
                'name' => 'Regular User',
                'role' => 'user',
                'id' => 2,
            ],
        ];

        if (isset($validCredentials[$email]) && $validCredentials[$email]['password'] === $password) {
            $user = $validCredentials[$email];
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Log successful login
            $this->security->logSecurityEvent('user_login', [
                'user_id' => $user['id'],
                'email' => $email,
                'success' => true,
            ]);
            
            $this->flash('success', 'Login successful. Welcome back!');
            $this->redirect('/dashboard');
        } else {
            // Log failed login attempt
            $this->security->logSecurityEvent('user_login_failed', [
                'email' => $email,
                'ip' => $clientIp,
            ]);
            
            $this->flash('error', 'Invalid email or password.');
            $this->redirect('/login');
        }
    }

    /**
     * Process logout
     */
    public function logout(): void
    {
        // Validate CSRF token
        if (!$this->validateCsrf()) {
            $this->jsonError('Invalid security token', 'CSRF_TOKEN_INVALID', 403);
            return;
        }

        // Log logout event
        if (isset($_SESSION['user_id'])) {
            $this->security->logSecurityEvent('user_logout', [
                'user_id' => $_SESSION['user_id'],
                'session_duration' => time() - ($_SESSION['login_time'] ?? time()),
            ]);
        }

        // Clear session
        session_unset();
        session_destroy();

        // Start new session
        session_start();
        session_regenerate_id(true);

        $this->flash('success', 'You have been logged out successfully.');
        $this->redirect('/');
    }

    /**
     * Show registration form (if needed)
     */
    public function showRegister(): void
    {
        $data = [
            'title' => 'Register - CIS MVC Platform',
        ];

        $this->view('auth.register', $data);
    }

    /**
     * Process registration
     */
    public function register(): void
    {
        // Validate CSRF token
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token. Please try again.');
            $this->redirect('/register');
            return;
        }

        // Rate limiting
        $clientIp = $this->security->getClientIp();
        if (!$this->security->checkRateLimit("register_{$clientIp}", 3, 3600)) {
            $this->flash('error', 'Too many registration attempts. Please try again later.');
            $this->redirect('/register');
            return;
        }

        // Validate input
        $rules = [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'password_confirm' => 'required',
        ];

        try {
            $input = $this->validate($rules);
        } catch (\Exception $e) {
            $this->flash('error', 'Please fill in all required fields correctly.');
            $this->redirect('/register');
            return;
        }

        // Check password confirmation
        if ($input['password'] !== $input['password_confirm']) {
            $this->flash('error', 'Passwords do not match.');
            $this->redirect('/register');
            return;
        }

        // In production, would save to database
        // For demo, just show success message
        $this->security->logSecurityEvent('user_registration_attempt', [
            'email' => $input['email'],
            'name' => $input['name'],
        ]);

        $this->flash('success', 'Registration successful. You can now login.');
        $this->redirect('/login');
    }

    /**
     * Password reset request
     */
    public function forgotPassword(): void
    {
        $data = [
            'title' => 'Forgot Password - CIS MVC Platform',
        ];

        $this->view('auth.forgot', $data);
    }

    /**
     * Process password reset request
     */
    public function sendResetLink(): void
    {
        // Validate CSRF token
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token. Please try again.');
            $this->redirect('/forgot-password');
            return;
        }

        // Rate limiting
        $clientIp = $this->security->getClientIp();
        if (!$this->security->checkRateLimit("reset_{$clientIp}", 3, 3600)) {
            $this->flash('error', 'Too many reset attempts. Please try again later.');
            $this->redirect('/forgot-password');
            return;
        }

        $email = $this->input('email');
        
        if (!$this->security->validateEmail($email)) {
            $this->flash('error', 'Please provide a valid email address.');
            $this->redirect('/forgot-password');
            return;
        }

        // Log password reset request
        $this->security->logSecurityEvent('password_reset_request', [
            'email' => $email,
        ]);

        // In production, would send email with reset link
        $this->flash('success', 'If an account exists with that email, a reset link has been sent.');
        $this->redirect('/login');
    }
}
