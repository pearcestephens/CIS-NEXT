<?php
declare(strict_types=1);

/**
 * Working Login Controller for CIS
 * Fixed to work with actual database structure and routing
 */

namespace App\Http\Controllers;

use App\Infra\Persistence\MariaDB\Database;

class WorkingAuthController extends BaseController
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Show login form
     */
    public function showLogin()
    {
        // If already logged in, redirect to admin
        if (isset($_SESSION['user_id'])) {
            header('Location: /admin');
            exit;
        }
        
        // Return simple HTML login form
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .login-card { background: white; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                        <h2 class="text-primary">CIS Login</h2>
                        <p class="text-muted">Central Information System</p>
                    </div>
                    
                    <form method="POST" action="/login">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="admin@ecigdis.co.nz" placeholder="Enter your email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required
                                   value="admin123" placeholder="Enter your password">
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            Test Credentials:<br>
                            Email: admin@ecigdis.co.nz<br>
                            Password: admin123
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
        
        echo $html;
        exit;
    }
    
    /**
     * Process login
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->showLoginWithError('Email and password are required');
            return;
        }
        
        try {
            // Get user from database
            $result = $this->db->execute(
                'SELECT id, first_name, last_name, email, role, password_hash, active FROM users WHERE email = ? AND active = 1',
                [$email]
            );
            
            $user = $result->fetch();
            
            if (!$user) {
                $this->showLoginWithError('Invalid email or password');
                return;
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->showLoginWithError('Invalid email or password');
                return;
            }
            
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            $_SESSION['user_role'] = strtolower($user['role']);
            
            // Update last login
            $this->db->execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
            
            // Redirect to working admin dashboard
            header('Location: /test-admin-dashboard');
            exit;
            
        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $this->showLoginWithError('An error occurred during login');
        }
    }
    
    /**
     * Logout
     */
    public function logout()
    {
        session_destroy();
        header('Location: /login');
        exit;
    }
    
    /**
     * Show login form with error
     */
    private function showLoginWithError(string $error)
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .login-card { background: white; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                        <h2 class="text-primary">CIS Login</h2>
                        <p class="text-muted">Central Information System</p>
                    </div>
                    
                    <div class="alert alert-danger" role="alert">
                        ' . htmlspecialchars($error) . '
                    </div>
                    
                    <form method="POST" action="/login">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="' . htmlspecialchars($_POST['email'] ?? 'admin@ecigdis.co.nz') . '">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            Test Credentials:<br>
                            Email: admin@ecigdis.co.nz<br>
                            Password: admin123
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
        
        echo $html;
        exit;
    }
}
