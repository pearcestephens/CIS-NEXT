<?php
declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * Home Controller
 * Handles the main landing page
 */
class HomeController extends BaseController
{
    public function index(array $params, array $request): array
    {
        $user = $this->getCurrentUser();
        
        // Redirect authenticated users to their dashboard
        if ($user) {
            return $this->redirect('/admin');
        }
        
        // Show public landing page
        return $this->view('home', [
            'title' => 'CIS - Central Information System',
            'subtitle' => 'Ecigdis Limited / The Vape Shed'
        ]);
    }
}
