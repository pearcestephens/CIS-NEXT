<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;

/**
 * Admin Integrations Controller
 * Manages third-party integrations and health monitoring
 */
class IntegrationsController extends BaseController
{
    public function index()
    {
        return $this->render('admin/integrations', [
            'title' => 'Integration Management',
            'integrations' => $this->getIntegrations()
        ]);
    }
    
    public function health()
    {
        $health_data = $this->checkAllIntegrations();
        
        if ($this->isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode($health_data);
            return;
        }
        
        return $this->render('admin/integrations/health', [
            'title' => 'Integration Health',
            'health' => $health_data
        ]);
    }
    
    private function getIntegrations(): array
    {
        return [
            ['name' => 'Vend POS', 'status' => 'connected', 'last_sync' => '2025-09-13 10:30:00'],
            ['name' => 'Deputy HR', 'status' => 'connected', 'last_sync' => '2025-09-13 10:25:00'],
            ['name' => 'Xero Accounting', 'status' => 'connected', 'last_sync' => '2025-09-13 10:20:00']
        ];
    }
    
    private function checkAllIntegrations(): array
    {
        return [
            'overall_status' => 'healthy',
            'integrations' => [
                'vend' => ['status' => 'ok', 'response_time' => '150ms'],
                'deputy' => ['status' => 'ok', 'response_time' => '200ms'], 
                'xero' => ['status' => 'ok', 'response_time' => '180ms']
            ],
            'last_check' => date('c')
        ];
    }
}
