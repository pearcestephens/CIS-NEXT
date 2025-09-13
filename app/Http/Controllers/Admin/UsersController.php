<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;

/**
 * Admin Users Controller
 * Manages user accounts and permissions
 */
class UsersController extends BaseController
{
    public function index()
    {
        return $this->render('admin/users', [
            'title' => 'User Management',
            'users' => $this->getUsers()
        ]);
    }
    
    public function create()
    {
        return $this->render('admin/users/create', [
            'title' => 'Create New User'
        ]);
    }
    
    public function store()
    {
        // TODO: Implement user creation with validation
        $response = ['success' => true, 'message' => 'User created successfully'];
        
        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode($response);
            return;
        }
        
        $_SESSION['flash_message'] = $response['message'];
        header('Location: /admin/users');
    }
    
    private function getUsers(): array
    {
        // TODO: Implement actual user fetching
        return [
            ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@ecigdis.co.nz', 'role' => 'admin', 'status' => 'active'],
            ['id' => 2, 'name' => 'Test User', 'email' => 'test@ecigdis.co.nz', 'role' => 'user', 'status' => 'active']
        ];
    }
}
