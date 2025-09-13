<?php
/**
 * CIS Application Services Scanner
 * Comprehensive analysis of all services in the application
 */

declare(strict_types=1);

class ApplicationServiceScanner
{
    private string $docroot;
    private array $services = [];
    
    public function __construct(string $docroot = null)
    {
        $this->docroot = $docroot ?? __DIR__;
    }
    
    public function scan(): array
    {
        $this->services = [
            'core_services' => [],
            'controllers' => [],
            'middlewares' => [],
            'integrations' => [],
            'domain_services' => [],
            'shared_services' => [],
            'tools_services' => [],
            'monitoring_services' => [],
            'security_services' => []
        ];
        
        // Scan all service categories
        $this->scanCoreServices();
        $this->scanControllers();
        $this->scanMiddlewares();
        $this->scanIntegrations();
        $this->scanDomainServices();
        $this->scanSharedServices();
        $this->scanToolServices();
        $this->scanMonitoringServices();
        $this->scanSecurityServices();
        
        return $this->services;
    }
    
    private function scanCoreServices(): void
    {
        // Bootstrap and core initialization
        $coreFiles = [
            'app/Shared/Bootstrap.php' => 'Application Bootstrap - DI, config, error handling',
            'app/Http/Router.php' => 'HTTP Router - Request routing with middleware support',
            'app/Shared/MiddlewarePipeline.php' => 'Middleware Pipeline - Global middleware orchestration',
            'index.php' => 'Application Entry Point'
        ];
        
        foreach ($coreFiles as $file => $description) {
            if (file_exists($this->docroot . '/' . $file)) {
                $this->services['core_services'][] = [
                    'name' => basename($file, '.php'),
                    'file' => $file,
                    'description' => $description,
                    'exists' => true
                ];
            }
        }
    }
    
    private function scanControllers(): void
    {
        $controllerDirs = [
            'app/Http/Controllers',
            'app/Http/Controllers/Admin',
            'app/Http/Controllers/Api'
        ];
        
        foreach ($controllerDirs as $dir) {
            $fullDir = $this->docroot . '/' . $dir;
            if (is_dir($fullDir)) {
                foreach (glob($fullDir . '/*.php') as $file) {
                    $className = basename($file, '.php');
                    $relativePath = str_replace($this->docroot . '/', '', $file);
                    
                    // Try to determine purpose from filename
                    $purpose = $this->determinePurpose($className);
                    
                    $this->services['controllers'][] = [
                        'name' => $className,
                        'file' => $relativePath,
                        'purpose' => $purpose,
                        'category' => basename($dir)
                    ];
                }
            }
        }
    }
    
    private function scanMiddlewares(): void
    {
        $middlewareDir = $this->docroot . '/app/Http/Middlewares';
        if (is_dir($middlewareDir)) {
            foreach (glob($middlewareDir . '/*.php') as $file) {
                $className = basename($file, '.php');
                $relativePath = str_replace($this->docroot . '/', '', $file);
                
                $this->services['middlewares'][] = [
                    'name' => $className,
                    'file' => $relativePath,
                    'purpose' => $this->determineMiddlewarePurpose($className)
                ];
            }
        }
    }
    
    private function scanIntegrations(): void
    {
        $integrationDir = $this->docroot . '/app/Integrations';
        if (is_dir($integrationDir)) {
            foreach (glob($integrationDir . '/*/Client.php') as $file) {
                $serviceName = basename(dirname($file));
                $relativePath = str_replace($this->docroot . '/', '', $file);
                
                // Check if it's a real integration or backup
                $content = file_get_contents($file);
                $isActive = !empty($content) && strpos($content, 'class Client') !== false;
                
                $this->services['integrations'][] = [
                    'name' => $serviceName,
                    'file' => $relativePath,
                    'active' => $isActive,
                    'purpose' => $this->determineIntegrationPurpose($serviceName)
                ];
            }
        }
    }
    
    private function scanDomainServices(): void
    {
        $domainDir = $this->docroot . '/app/Domain/Services';
        if (is_dir($domainDir)) {
            foreach (glob($domainDir . '/*.php') as $file) {
                $className = basename($file, '.php');
                $relativePath = str_replace($this->docroot . '/', '', $file);
                
                $this->services['domain_services'][] = [
                    'name' => $className,
                    'file' => $relativePath,
                    'purpose' => $this->determinePurpose($className)
                ];
            }
        }
    }
    
    private function scanSharedServices(): void
    {
        $sharedDirs = [
            'app/Shared/Config',
            'app/Shared/Logging',
            'app/Shared/Queue',
            'app/Shared/Backup',
            'app/Shared/Mail',
            'app/Shared/AI',
            'app/Shared/Sec'
        ];
        
        foreach ($sharedDirs as $dir) {
            $fullDir = $this->docroot . '/' . $dir;
            if (is_dir($fullDir)) {
                foreach (glob($fullDir . '/*.php') as $file) {
                    $className = basename($file, '.php');
                    $relativePath = str_replace($this->docroot . '/', '', $file);
                    
                    $this->services['shared_services'][] = [
                        'name' => $className,
                        'file' => $relativePath,
                        'category' => basename($dir),
                        'purpose' => $this->determinePurpose($className)
                    ];
                }
            }
        }
    }
    
    private function scanToolServices(): void
    {
        $toolsDir = $this->docroot . '/tools';
        if (is_dir($toolsDir)) {
            $importantTools = [
                'integration_client.php' => 'CIS Integration Client SDK',
                'queue_worker.php' => 'Background Job Worker',
                'backup_system.php' => 'Backup Management System',
                'cache_manager.php' => 'Cache Management',
                'database_audit.php' => 'Database Auditing',
                'migration_runner.php' => 'Migration Management'
            ];
            
            foreach ($importantTools as $file => $description) {
                if (file_exists($toolsDir . '/' . $file)) {
                    $this->services['tools_services'][] = [
                        'name' => basename($file, '.php'),
                        'file' => 'tools/' . $file,
                        'purpose' => $description
                    ];
                }
            }
        }
    }
    
    private function scanMonitoringServices(): void
    {
        $monitoringFiles = [
            'app/Monitoring/SessionRecorder.php' => 'Session Recording and Replay',
            'app/Http/Controllers/MonitorController.php' => 'Real-time System Monitoring'
        ];
        
        foreach ($monitoringFiles as $file => $description) {
            if (file_exists($this->docroot . '/' . $file)) {
                $this->services['monitoring_services'][] = [
                    'name' => basename($file, '.php'),
                    'file' => $file,
                    'purpose' => $description
                ];
            }
        }
    }
    
    private function scanSecurityServices(): void
    {
        $securityFiles = [
            'app/Security/IDSEngine.php' => 'Intrusion Detection System',
            'app/Shared/Sec/Secrets.php' => 'Secrets Management'
        ];
        
        foreach ($securityFiles as $file => $description) {
            if (file_exists($this->docroot . '/' . $file)) {
                $this->services['security_services'][] = [
                    'name' => basename($file, '.php'),
                    'file' => $file,
                    'purpose' => $description
                ];
            }
        }
    }
    
    private function determinePurpose(string $className): string
    {
        $purposes = [
            'Auth' => 'Authentication Management',
            'User' => 'User Management',
            'Dashboard' => 'Dashboard Interface',
            'Health' => 'Health Monitoring',
            'Monitor' => 'System Monitoring',
            'Backup' => 'Backup Management',
            'Cache' => 'Cache Management',
            'Config' => 'Configuration Management',
            'Logger' => 'Logging Service',
            'Mailer' => 'Email Service',
            'Queue' => 'Job Queue Management',
            'Feed' => 'Activity Feed Management',
            'Permission' => 'Permission Management',
            'Migration' => 'Database Migration',
            'Seed' => 'Database Seeding',
            'Automation' => 'System Automation'
        ];
        
        foreach ($purposes as $keyword => $purpose) {
            if (stripos($className, $keyword) !== false) {
                return $purpose;
            }
        }
        
        return 'General Service';
    }
    
    private function determineMiddlewarePurpose(string $className): string
    {
        $purposes = [
            'Auth' => 'Authentication Middleware',
            'CSRF' => 'CSRF Protection',
            'RBAC' => 'Role-Based Access Control',
            'RateLimit' => 'Rate Limiting',
            'Security' => 'Security Headers',
            'Session' => 'Session Management',
            'Profiler' => 'Performance Profiling',
            'ErrorHandler' => 'Error Handling',
            'IDS' => 'Intrusion Detection'
        ];
        
        foreach ($purposes as $keyword => $purpose) {
            if (stripos($className, $keyword) !== false) {
                return $purpose;
            }
        }
        
        return 'General Middleware';
    }
    
    private function determineIntegrationPurpose(string $serviceName): string
    {
        $purposes = [
            'OpenAI' => 'OpenAI API Integration - Chat, Completions, Embeddings',
            'Claude' => 'Claude AI Integration - Anthropic API',
            'Vend' => 'Vend POS Integration - Product/Sales Sync',
            'Deputy' => 'Deputy Workforce Management',
            'Xero' => 'Xero Accounting Integration'
        ];
        
        return $purposes[$serviceName] ?? 'External Service Integration';
    }
    
    public function generateReport(): string
    {
        $services = $this->scan();
        $report = "# CIS APPLICATION SERVICES SCAN REPORT\n";
        $report .= "Generated: " . date('Y-m-d H:i:s T') . "\n\n";
        
        foreach ($services as $category => $items) {
            if (empty($items)) continue;
            
            $categoryName = strtoupper(str_replace('_', ' ', $category));
            $report .= "## {$categoryName}\n\n";
            
            foreach ($items as $service) {
                $name = $service['name'];
                $file = $service['file'];
                $purpose = $service['purpose'] ?? $service['description'] ?? 'N/A';
                
                if (isset($service['active'])) {
                    $status = $service['active'] ? '✅ ACTIVE' : '❌ INACTIVE';
                    $report .= "- **{$name}** ({$status})\n";
                } else {
                    $report .= "- **{$name}**\n";
                }
                
                $report .= "  - File: `{$file}`\n";
                $report .= "  - Purpose: {$purpose}\n";
                
                if (isset($service['category'])) {
                    $report .= "  - Category: {$service['category']}\n";
                }
                
                $report .= "\n";
            }
        }
        
        return $report;
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    $scanner = new ApplicationServiceScanner();
    echo $scanner->generateReport();
} else {
    header('Content-Type: text/plain');
    $scanner = new ApplicationServiceScanner();
    echo $scanner->generateReport();
}
