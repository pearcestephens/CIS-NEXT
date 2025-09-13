<?php
declare(strict_types=1);

/**
 * Seed Management Model
 * File: app/Models/SeedModel.php
 * Purpose: Test data seeding and management using AdminDAL
 */

namespace App\Models;

use RuntimeException;
use InvalidArgumentException;

class SeedModel
{
    private AdminDAL $dal;
    private array $seed_definitions;

    public function __construct()
    {
        $this->dal = new AdminDAL();
        $this->seed_definitions = $this->initializeSeedDefinitions();
    }

    /**
     * Get all available seed configurations and their status
     */
    public function getSeedStatus(): array
    {
        try {
            $seed_status = [];
            
            foreach ($this->seed_definitions as $seed_name => $definition) {
                $status = $this->checkSeedStatus($seed_name, $definition);
                $seed_status[$seed_name] = array_merge($definition, $status);
            }

            // Get seeding history
            $history = $this->getSeedingHistory();

            return [
                'success' => true,
                'seeds' => $seed_status,
                'summary' => $this->generateSeedSummary($seed_status),
                'history' => $history
            ];

        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to get seed status: " . $e->getMessage());
        }
    }

    /**
     * Execute specific seeds with transaction safety
     */
    public function executeSeed(array $seed_names, array $options = []): array
    {
        try {
            $force_reseed = $options['force'] ?? false;
            $dry_run = $options['dry_run'] ?? false;
            $results = [];

            // Validate seed names
            foreach ($seed_names as $seed_name) {
                if (!isset($this->seed_definitions[$seed_name])) {
                    throw new InvalidArgumentException("Unknown seed: {$seed_name}");
                }
            }

            // Check dependencies
            $execution_order = $this->resolveDependencies($seed_names);

            if ($dry_run) {
                return [
                    'success' => true,
                    'dry_run' => true,
                    'execution_order' => $execution_order,
                    'operations' => $this->previewSeedOperations($execution_order)
                ];
            }

            // Execute seeds in dependency order
            $this->dal->begin();
            
            foreach ($execution_order as $seed_name) {
                try {
                    $result = $this->executeSingleSeed($seed_name, $force_reseed);
                    $results[$seed_name] = $result;
                    
                    if (!$result['success']) {
                        $this->dal->rollback();
                        return [
                            'success' => false,
                            'error' => "Seed '{$seed_name}' failed: " . $result['error'],
                            'results' => $results
                        ];
                    }

                } catch (RuntimeException $e) {
                    $this->dal->rollback();
                    return [
                        'success' => false,
                        'error' => "Seed '{$seed_name}' threw exception: " . $e->getMessage(),
                        'results' => $results
                    ];
                }
            }

            $this->dal->commit();

            // Log seeding operation
            $this->logSeedingOperation($seed_names, $results);

            return [
                'success' => true,
                'message' => 'All seeds executed successfully',
                'results' => $results,
                'execution_order' => $execution_order
            ];

        } catch (RuntimeException $e) {
            if ($this->dal->in_transaction ?? false) {
                $this->dal->rollback();
            }
            throw new RuntimeException("Seeding failed: " . $e->getMessage());
        }
    }

    /**
     * Clear specific seed data
     */
    public function clearSeed(array $seed_names, array $options = []): array
    {
        try {
            $confirm_clear = $options['confirm'] ?? false;
            $results = [];

            if (!$confirm_clear) {
                return [
                    'success' => false,
                    'error' => 'Clear operation requires confirmation'
                ];
            }

            $this->dal->begin();

            foreach ($seed_names as $seed_name) {
                if (!isset($this->seed_definitions[$seed_name])) {
                    throw new InvalidArgumentException("Unknown seed: {$seed_name}");
                }

                try {
                    $result = $this->clearSingleSeed($seed_name);
                    $results[$seed_name] = $result;

                } catch (RuntimeException $e) {
                    $this->dal->rollback();
                    return [
                        'success' => false,
                        'error' => "Failed to clear seed '{$seed_name}': " . $e->getMessage(),
                        'results' => $results
                    ];
                }
            }

            $this->dal->commit();

            return [
                'success' => true,
                'message' => 'Seed data cleared successfully',
                'results' => $results
            ];

        } catch (RuntimeException $e) {
            if ($this->dal->in_transaction ?? false) {
                $this->dal->rollback();
            }
            throw new RuntimeException("Clear operation failed: " . $e->getMessage());
        }
    }

    /**
     * Initialize seed definitions
     */
    private function initializeSeedDefinitions(): array
    {
        return [
            'users' => [
                'name' => 'User Accounts',
                'description' => 'Create admin and test user accounts',
                'table' => 'users',
                'dependencies' => ['roles'],
                'check_column' => 'username',
                'check_values' => ['admin', 'test_user'],
                'seeder_method' => 'seedUsers'
            ],
            'roles' => [
                'name' => 'User Roles',
                'description' => 'Create system roles and permissions',
                'table' => 'user_roles',
                'dependencies' => [],
                'check_column' => 'role_name',
                'check_values' => ['super_admin', 'admin', 'user'],
                'seeder_method' => 'seedRoles'
            ],
            'configuration' => [
                'name' => 'System Configuration',
                'description' => 'Default system settings and feature flags',
                'table' => 'configuration',
                'dependencies' => [],
                'check_column' => 'config_key',
                'check_values' => ['app_name', 'maintenance_mode', 'debug_mode'],
                'seeder_method' => 'seedConfiguration'
            ],
            'test_data' => [
                'name' => 'Test Data',
                'description' => 'Sample data for development and testing',
                'table' => 'test_entities',
                'dependencies' => ['users', 'configuration'],
                'check_column' => 'entity_type',
                'check_values' => ['sample_products', 'sample_orders'],
                'seeder_method' => 'seedTestData'
            ],
            'integration_secrets' => [
                'name' => 'Integration Secrets',
                'description' => 'Test API keys and integration settings',
                'table' => 'integration_secrets',
                'dependencies' => [],
                'check_column' => 'service_name',
                'check_values' => ['vend_test', 'xero_test', 'deputy_test'],
                'seeder_method' => 'seedIntegrationSecrets'
            ]
        ];
    }

    /**
     * Check if a seed has been run and its current status
     */
    private function checkSeedStatus(string $seed_name, array $definition): array
    {
        try {
            $table_name = $this->dal->table($definition['table']);
            
            // Check if table exists
            $table_exists = $this->dal->query("SHOW TABLES LIKE ?", [$table_name], 's');
            
            if (empty($table_exists)) {
                return [
                    'status' => 'table_missing',
                    'seeded' => false,
                    'record_count' => 0,
                    'can_seed' => false,
                    'message' => 'Table does not exist'
                ];
            }

            // Check for seed data
            $check_column = $definition['check_column'];
            $check_values = $definition['check_values'];
            
            $placeholders = str_repeat('?,', count($check_values) - 1) . '?';
            $found_records = $this->dal->query(
                "SELECT COUNT(*) as count FROM {$table_name} WHERE {$check_column} IN ({$placeholders})",
                $check_values,
                str_repeat('s', count($check_values))
            );

            $found_count = $found_records[0]['count'] ?? 0;
            $expected_count = count($check_values);

            return [
                'status' => $found_count >= $expected_count ? 'seeded' : 'partial',
                'seeded' => $found_count >= $expected_count,
                'record_count' => (int)$found_count,
                'expected_count' => $expected_count,
                'can_seed' => true,
                'message' => $found_count >= $expected_count ? 'Fully seeded' : "Partial ({$found_count}/{$expected_count})"
            ];

        } catch (RuntimeException $e) {
            return [
                'status' => 'error',
                'seeded' => false,
                'record_count' => 0,
                'can_seed' => false,
                'message' => 'Error checking status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute a single seed
     */
    private function executeSingleSeed(string $seed_name, bool $force_reseed): array
    {
        $definition = $this->seed_definitions[$seed_name];
        $method_name = $definition['seeder_method'];

        // Check if already seeded
        if (!$force_reseed) {
            $status = $this->checkSeedStatus($seed_name, $definition);
            if ($status['seeded']) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Already seeded',
                    'records_inserted' => 0
                ];
            }
        }

        // Execute the seeder method
        if (!method_exists($this, $method_name)) {
            throw new RuntimeException("Seeder method '{$method_name}' not found");
        }

        try {
            $result = $this->$method_name($force_reseed);
            
            return [
                'success' => true,
                'skipped' => false,
                'message' => 'Seeded successfully',
                'records_inserted' => $result['records_inserted'] ?? 0,
                'details' => $result['details'] ?? []
            ];

        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'records_inserted' => 0
            ];
        }
    }

    /**
     * Seed user roles
     */
    private function seedRoles(bool $force = false): array
    {
        $table = $this->dal->table('user_roles');
        $records_inserted = 0;

        if ($force) {
            $this->dal->exec("DELETE FROM {$table} WHERE role_name IN ('super_admin', 'admin', 'user')");
        }

        $roles = [
            [
                'role_name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'permissions' => json_encode(['*']),
                'is_active' => 1
            ],
            [
                'role_name' => 'admin',
                'display_name' => 'Administrator',
                'permissions' => json_encode(['admin.*', 'user.read', 'config.read']),
                'is_active' => 1
            ],
            [
                'role_name' => 'user',
                'display_name' => 'Standard User',
                'permissions' => json_encode(['user.read']),
                'is_active' => 1
            ]
        ];

        foreach ($roles as $role) {
            $this->dal->exec("
                INSERT INTO {$table} (role_name, display_name, permissions, is_active, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name),
                permissions = VALUES(permissions),
                updated_at = NOW()
            ", [
                $role['role_name'],
                $role['display_name'],
                $role['permissions'],
                $role['is_active']
            ], 'sssi');
            
            $records_inserted++;
        }

        return [
            'records_inserted' => $records_inserted,
            'details' => ['roles_created' => array_column($roles, 'role_name')]
        ];
    }

    /**
     * Seed user accounts
     */
    private function seedUsers(bool $force = false): array
    {
        $table = $this->dal->table('users');
        $records_inserted = 0;

        if ($force) {
            $this->dal->exec("DELETE FROM {$table} WHERE username IN ('admin', 'test_user')");
        }

        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@vapeshed.co.nz',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role_name' => 'super_admin',
                'is_active' => 1
            ],
            [
                'username' => 'test_user',
                'email' => 'test@vapeshed.co.nz',
                'password' => password_hash('test123', PASSWORD_DEFAULT),
                'role_name' => 'user',
                'is_active' => 1
            ]
        ];

        foreach ($users as $user) {
            $this->dal->exec("
                INSERT INTO {$table} (username, email, password_hash, role_name, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                password_hash = VALUES(password_hash),
                role_name = VALUES(role_name),
                updated_at = NOW()
            ", [
                $user['username'],
                $user['email'],
                $user['password'],
                $user['role_name'],
                $user['is_active']
            ], 'ssssi');
            
            $records_inserted++;
        }

        return [
            'records_inserted' => $records_inserted,
            'details' => ['users_created' => array_column($users, 'username')]
        ];
    }

    /**
     * Seed system configuration
     */
    private function seedConfiguration(bool $force = false): array
    {
        $table = $this->dal->table('configuration');
        $records_inserted = 0;

        if ($force) {
            $this->dal->exec("DELETE FROM {$table} WHERE config_key IN ('app_name', 'maintenance_mode', 'debug_mode')");
        }

        $configs = [
            [
                'config_key' => 'app_name',
                'config_value' => 'CIS Admin System',
                'config_type' => 'string',
                'is_sensitive' => 0,
                'description' => 'Application display name'
            ],
            [
                'config_key' => 'maintenance_mode',
                'config_value' => '0',
                'config_type' => 'boolean',
                'is_sensitive' => 0,
                'description' => 'System maintenance mode flag'
            ],
            [
                'config_key' => 'debug_mode',
                'config_value' => '1',
                'config_type' => 'boolean',
                'is_sensitive' => 0,
                'description' => 'Development debug mode'
            ]
        ];

        foreach ($configs as $config) {
            $this->dal->exec("
                INSERT INTO {$table} (config_key, config_value, config_type, is_sensitive, description, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                config_value = VALUES(config_value),
                description = VALUES(description),
                updated_at = NOW()
            ", [
                $config['config_key'],
                $config['config_value'],
                $config['config_type'],
                $config['is_sensitive'],
                $config['description']
            ], 'sssis');
            
            $records_inserted++;
        }

        return [
            'records_inserted' => $records_inserted,
            'details' => ['configs_created' => array_column($configs, 'config_key')]
        ];
    }

    /**
     * Seed test data
     */
    private function seedTestData(bool $force = false): array
    {
        // This would create sample test entities
        // Implementation depends on your test data structure
        return [
            'records_inserted' => 0,
            'details' => ['message' => 'Test data seeding not yet implemented']
        ];
    }

    /**
     * Seed integration secrets (test values only)
     */
    private function seedIntegrationSecrets(bool $force = false): array
    {
        $table = $this->dal->table('integration_secrets');
        $records_inserted = 0;

        if ($force) {
            $this->dal->exec("DELETE FROM {$table} WHERE service_name IN ('vend_test', 'xero_test', 'deputy_test')");
        }

        $secrets = [
            [
                'service_name' => 'vend_test',
                'secret_key' => 'api_token',
                'secret_value' => 'test_vend_token_12345',
                'is_active' => 1
            ],
            [
                'service_name' => 'xero_test',
                'secret_key' => 'client_id',
                'secret_value' => 'test_xero_client_67890',
                'is_active' => 1
            ],
            [
                'service_name' => 'deputy_test',
                'secret_key' => 'api_key',
                'secret_value' => 'test_deputy_key_abcdef',
                'is_active' => 1
            ]
        ];

        foreach ($secrets as $secret) {
            $this->dal->exec("
                INSERT INTO {$table} (service_name, secret_key, secret_value, is_active, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                secret_value = VALUES(secret_value),
                updated_at = NOW()
            ", [
                $secret['service_name'],
                $secret['secret_key'],
                $secret['secret_value'],
                $secret['is_active']
            ], 'sssi');
            
            $records_inserted++;
        }

        return [
            'records_inserted' => $records_inserted,
            'details' => ['secrets_created' => array_column($secrets, 'service_name')]
        ];
    }

    /**
     * Clear a single seed's data
     */
    private function clearSingleSeed(string $seed_name): array
    {
        $definition = $this->seed_definitions[$seed_name];
        $table = $this->dal->table($definition['table']);
        $check_column = $definition['check_column'];
        $check_values = $definition['check_values'];

        $placeholders = str_repeat('?,', count($check_values) - 1) . '?';
        $result = $this->dal->exec(
            "DELETE FROM {$table} WHERE {$check_column} IN ({$placeholders})",
            $check_values,
            str_repeat('s', count($check_values))
        );

        return [
            'records_deleted' => $result['affected_rows'] ?? 0
        ];
    }

    /**
     * Resolve seed dependencies and return execution order
     */
    private function resolveDependencies(array $seed_names): array
    {
        $resolved = [];
        $visited = [];

        foreach ($seed_names as $seed_name) {
            $this->resolveSingleDependency($seed_name, $resolved, $visited);
        }

        return array_unique($resolved);
    }

    /**
     * Recursive dependency resolution
     */
    private function resolveSingleDependency(string $seed_name, array &$resolved, array &$visited): void
    {
        if (in_array($seed_name, $visited)) {
            throw new RuntimeException("Circular dependency detected for seed: {$seed_name}");
        }

        if (in_array($seed_name, $resolved)) {
            return;
        }

        $visited[] = $seed_name;
        $definition = $this->seed_definitions[$seed_name];

        foreach ($definition['dependencies'] as $dependency) {
            $this->resolveSingleDependency($dependency, $resolved, $visited);
        }

        $resolved[] = $seed_name;
        $visited = array_diff($visited, [$seed_name]);
    }

    /**
     * Preview seed operations without executing
     */
    private function previewSeedOperations(array $execution_order): array
    {
        $operations = [];

        foreach ($execution_order as $seed_name) {
            $definition = $this->seed_definitions[$seed_name];
            $status = $this->checkSeedStatus($seed_name, $definition);

            $operations[] = [
                'seed' => $seed_name,
                'name' => $definition['name'],
                'table' => $definition['table'],
                'current_status' => $status['status'],
                'will_execute' => !$status['seeded'],
                'expected_records' => $definition['expected_count'] ?? count($definition['check_values'])
            ];
        }

        return $operations;
    }

    /**
     * Generate summary of seed status
     */
    private function generateSeedSummary(array $seed_status): array
    {
        $summary = [
            'total' => count($seed_status),
            'seeded' => 0,
            'partial' => 0,
            'missing' => 0,
            'errors' => 0
        ];

        foreach ($seed_status as $status) {
            switch ($status['status']) {
                case 'seeded':
                    $summary['seeded']++;
                    break;
                case 'partial':
                    $summary['partial']++;
                    break;
                case 'table_missing':
                    $summary['missing']++;
                    break;
                case 'error':
                    $summary['errors']++;
                    break;
            }
        }

        return $summary;
    }

    /**
     * Get seeding history from audit logs
     */
    private function getSeedingHistory(): array
    {
        try {
            return $this->dal->query("
                SELECT 
                    user_id,
                    details,
                    ip_address,
                    created_at
                FROM {$this->dal->table('audit_logs')}
                WHERE action = 'seed_management'
                ORDER BY created_at DESC
                LIMIT 20
            ");

        } catch (RuntimeException $e) {
            return [];
        }
    }

    /**
     * Log seeding operation for audit trail
     */
    private function logSeedingOperation(array $seed_names, array $results): void
    {
        try {
            $this->dal->exec("
                INSERT INTO {$this->dal->table('audit_logs')} 
                (user_id, action, details, ip_address, created_at)
                VALUES (?, 'seed_management', ?, ?, NOW())
            ", [
                $_SESSION['user_id'] ?? null,
                json_encode([
                    'seeds_executed' => $seed_names,
                    'results' => $results,
                    'total_records' => array_sum(array_column($results, 'records_inserted'))
                ]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ], 'iss');

        } catch (RuntimeException $e) {
            // Don't fail the operation if audit logging fails
            error_log("Failed to log seeding operation: " . $e->getMessage());
        }
    }
}
?>
