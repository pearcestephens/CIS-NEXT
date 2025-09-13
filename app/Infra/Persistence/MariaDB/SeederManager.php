<?php
declare(strict_types=1);

namespace App\Infra\Persistence\MariaDB;

use App\Shared\Logging\Logger;

/**
 * Seeder Manager
 * Handles database seeding
 */
class SeederManager
{
    private Database $db;
    private Logger $logger;
    private string $seedsPath;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
        $this->seedsPath = dirname(dirname(dirname(__DIR__))) . '/seeds';
    }
    
    public function seed(?string $seederClass = null): void
    {
        if ($seederClass) {
            $this->runSeeder($seederClass);
        } else {
            $this->runAllSeeders();
        }
    }
    
    private function runAllSeeders(): void
    {
        $this->logger->info('Starting database seeding');
        
        $seeders = [
            'RolesAndPermissionsSeeder',
            'AdminUserSeeder',
            'ConfigurationSeeder',
            'NotificationsSeeder',
        ];
        
        foreach ($seeders as $seeder) {
            $this->runSeeder($seeder);
        }
        
        $this->logger->info('Database seeding completed');
    }
    
    private function runSeeder(string $seederClass): void
    {
        $this->logger->info('Running seeder', ['seeder' => $seederClass]);
        
        $file = $this->seedsPath . '/' . $seederClass . '.php';
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Seeder file not found: {$file}");
        }
        
        try {
            $seederInstance = include $file;
            
            if ($seederInstance instanceof Seeder) {
                $seederInstance->run($this->db);
            } else {
                throw new \RuntimeException("Invalid seeder format: {$seederClass}");
            }
            
            $this->logger->info('Seeder completed', ['seeder' => $seederClass]);
            
        } catch (\Exception $e) {
            $this->logger->error('Seeder failed', [
                'seeder' => $seederClass,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}
