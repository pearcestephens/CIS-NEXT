<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Utils\SettingsRegistry;

/**
 * Admin Settings Controller
 * Manages system settings and configuration
 */
class SettingsController extends BaseController
{
    private SettingsRegistry $settings;
    
    public function __construct()
    {
        parent::__construct();
        $this->settings = new SettingsRegistry();
    }
    
    public function index()
    {
        return $this->render('admin/settings', [
            'title' => 'System Settings',
            'settings' => $this->getSettingsGroups()
        ]);
    }
    
    public function update()
    {
        // TODO: Implement settings update with CSRF validation
        // This will be expanded in Phase 1 with performance settings
        
        $response = ['success' => true, 'message' => 'Settings updated successfully'];
        
        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode($response);
            return;
        }
        
        $_SESSION['flash_message'] = $response['message'];
        header('Location: /admin/settings');
    }
    
    private function getSettingsGroups(): array
    {
        return [
            'General' => [
                'app_name' => $this->settings->get('app_name'),
                'app_version' => $this->settings->get('app_version'),
                'debug_mode' => $this->settings->get('debug_mode')
            ],
            'Security' => [
                'session_timeout' => $this->settings->get('session_timeout'),
                'max_login_attempts' => $this->settings->get('max_login_attempts')
            ]
        ];
    }
}
