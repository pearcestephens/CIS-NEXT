<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Configuration Model
 * 
 * Handles system configuration with validation and audit trails
 */
class Configuration extends BaseModel
{
    protected string $table = 'cis_system_config';
    protected array $fillable = [
        'config_key',
        'config_value',
        'config_type',
        'is_sensitive',
        'description',
        'validation_rules'
    ];

    // Configuration types
    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';
    const TYPE_ENCRYPTED = 'encrypted';

    /**
     * Get configuration value with type conversion
     */
    public function getValue(string $key, $default = null)
    {
        $config = $this->where(['config_key' => $key]);
        
        if (empty($config)) {
            return $default;
        }

        $item = $config[0];
        $value = $item['config_value'];
        
        // Handle encrypted values
        if ($item['config_type'] === self::TYPE_ENCRYPTED) {
            $value = $this->decrypt($value);
        }

        // Type conversion
        return $this->convertValue($value, $item['config_type']);
    }

    /**
     * Set configuration value with validation
     */
    public function setValue(string $key, $value, ?int $userId = null): bool
    {
        // Get existing config to check validation rules
        $existing = $this->where(['config_key' => $key]);
        
        if (!empty($existing)) {
            $config = $existing[0];
            
            // Validate value
            if (!$this->validateValue($value, $config)) {
                throw new \InvalidArgumentException("Invalid value for configuration key: {$key}");
            }
            
            $oldValue = $config['config_value'];
            $newValue = $this->prepareValue($value, $config['config_type'], $config['is_sensitive']);
            
            // Update existing
            $result = $this->update($config['id'], ['config_value' => $newValue]);
            
            // Log audit trail
            if ($result && $userId) {
                $auditModel = new Audit();
                $auditModel->logConfigChange($userId, $key, 
                    $config['is_sensitive'] ? '[REDACTED]' : $oldValue,
                    $config['is_sensitive'] ? '[REDACTED]' : $newValue
                );
            }
            
            return $result;
        } else {
            throw new \InvalidArgumentException("Configuration key not found: {$key}");
        }
    }

    /**
     * Create new configuration entry
     */
    public function createConfig(
        string $key,
        $value,
        string $type = self::TYPE_STRING,
        bool $isSensitive = false,
        ?string $description = null,
        ?array $validationRules = null,
        ?int $userId = null
    ): int {
        // Check if key already exists
        $existing = $this->where(['config_key' => $key]);
        if (!empty($existing)) {
            throw new \InvalidArgumentException("Configuration key already exists: {$key}");
        }

        $preparedValue = $this->prepareValue($value, $type, $isSensitive);

        $id = $this->create([
            'config_key' => $key,
            'config_value' => $preparedValue,
            'config_type' => $type,
            'is_sensitive' => $isSensitive,
            'description' => $description,
            'validation_rules' => $validationRules ? json_encode($validationRules) : null
        ]);

        // Log audit trail
        if ($userId) {
            $auditModel = new Audit();
            $auditModel->logAction(
                $userId,
                'config.create',
                'Configuration',
                $id,
                null,
                [
                    'key' => $key,
                    'value' => $isSensitive ? '[REDACTED]' : $value,
                    'type' => $type
                ]
            );
        }

        return $id;
    }

    /**
     * Get all configuration for admin display
     */
    public function getAllConfig(bool $includeSensitive = false): array
    {
        $configs = $this->all();
        
        foreach ($configs as &$config) {
            // Mask sensitive values
            if ($config['is_sensitive'] && !$includeSensitive) {
                $config['config_value'] = '[REDACTED]';
            } elseif ($config['config_type'] === self::TYPE_ENCRYPTED && $includeSensitive) {
                $config['config_value'] = $this->decrypt($config['config_value']);
            }
            
            // Decode validation rules
            $config['validation_rules'] = $config['validation_rules'] ? 
                json_decode($config['validation_rules'], true) : null;
        }
        
        return $configs;
    }

    /**
     * Get configuration by category (prefix)
     */
    public function getByCategory(string $prefix): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE config_key LIKE ? 
                ORDER BY config_key";
        
        $stmt = $this->database->executeQuery($sql, [$prefix . '%']);
        $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($configs as $config) {
            $key = $config['config_key'];
            $value = $config['config_value'];
            
            // Handle encrypted values
            if ($config['config_type'] === self::TYPE_ENCRYPTED) {
                $value = $this->decrypt($value);
            }
            
            $result[$key] = $this->convertValue($value, $config['config_type']);
        }
        
        return $result;
    }

    /**
     * Prepare value for storage based on type
     */
    protected function prepareValue($value, string $type, bool $isSensitive): string
    {
        // Convert value to string representation
        switch ($type) {
            case self::TYPE_BOOLEAN:
                $stringValue = $value ? '1' : '0';
                break;
            case self::TYPE_JSON:
                $stringValue = json_encode($value);
                break;
            case self::TYPE_INTEGER:
                $stringValue = (string) (int) $value;
                break;
            case self::TYPE_ENCRYPTED:
                $stringValue = $this->encrypt((string) $value);
                break;
            default:
                $stringValue = (string) $value;
        }
        
        return $stringValue;
    }

    /**
     * Convert stored string value to proper type
     */
    protected function convertValue(string $value, string $type)
    {
        switch ($type) {
            case self::TYPE_BOOLEAN:
                return $value === '1' || strtolower($value) === 'true';
            case self::TYPE_INTEGER:
                return (int) $value;
            case self::TYPE_JSON:
                return json_decode($value, true);
            case self::TYPE_ENCRYPTED:
            case self::TYPE_STRING:
            default:
                return $value;
        }
    }

    /**
     * Validate value against rules
     */
    protected function validateValue($value, array $config): bool
    {
        if (!$config['validation_rules']) {
            return true;
        }
        
        $rules = json_decode($config['validation_rules'], true);
        
        foreach ($rules as $rule => $ruleValue) {
            switch ($rule) {
                case 'min_length':
                    if (strlen((string) $value) < $ruleValue) {
                        return false;
                    }
                    break;
                case 'max_length':
                    if (strlen((string) $value) > $ruleValue) {
                        return false;
                    }
                    break;
                case 'min_value':
                    if (is_numeric($value) && $value < $ruleValue) {
                        return false;
                    }
                    break;
                case 'max_value':
                    if (is_numeric($value) && $value > $ruleValue) {
                        return false;
                    }
                    break;
                case 'regex':
                    if (!preg_match($ruleValue, (string) $value)) {
                        return false;
                    }
                    break;
                case 'allowed_values':
                    if (!in_array($value, $ruleValue)) {
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    }

    /**
     * Encrypt sensitive value
     */
    protected function encrypt(string $value): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive value
     */
    protected function decrypt(string $encryptedValue): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($encryptedValue);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get encryption key from environment
     */
    protected function getEncryptionKey(): string
    {
        $key = $_ENV['CONFIG_ENCRYPTION_KEY'] ?? 'default-key-change-in-production';
        return hash('sha256', $key, true);
    }

    /**
     * Delete configuration
     */
    public function deleteConfig(string $key, ?int $userId = null): bool
    {
        $existing = $this->where(['config_key' => $key]);
        
        if (empty($existing)) {
            return false;
        }
        
        $config = $existing[0];
        $result = $this->delete($config['id']);
        
        // Log audit trail
        if ($result && $userId) {
            $auditModel = new Audit();
            $auditModel->logAction(
                $userId,
                'config.delete',
                'Configuration',
                $config['id'],
                [
                    'key' => $key,
                    'value' => $config['is_sensitive'] ? '[REDACTED]' : $config['config_value']
                ]
            );
        }
        
        return $result;
    }

    /**
     * Reset configuration to defaults
     */
    public function resetToDefaults(?int $userId = null): int
    {
        $defaults = $this->getDefaultConfigs();
        $resetCount = 0;
        
        foreach ($defaults as $key => $config) {
            try {
                $this->setValue($key, $config['value'], $userId);
                $resetCount++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to reset config', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $resetCount;
    }

    /**
     * Get default configuration values
     */
    protected function getDefaultConfigs(): array
    {
        return [
            'app.name' => [
                'value' => 'CIS Application',
                'type' => self::TYPE_STRING,
                'description' => 'Application name'
            ],
            'app.debug' => [
                'value' => false,
                'type' => self::TYPE_BOOLEAN,
                'description' => 'Enable debug mode'
            ],
            'security.session_timeout' => [
                'value' => 3600,
                'type' => self::TYPE_INTEGER,
                'description' => 'Session timeout in seconds'
            ],
            'performance.cache_enabled' => [
                'value' => true,
                'type' => self::TYPE_BOOLEAN,
                'description' => 'Enable caching'
            ],
            'audit.retention_days' => [
                'value' => 90,
                'type' => self::TYPE_INTEGER,
                'description' => 'Audit log retention period in days'
            ]
        ];
    }

    /**
     * Export configuration for backup
     */
    public function exportConfig(bool $includeSensitive = false): array
    {
        $configs = $this->getAllConfig($includeSensitive);
        
        $export = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'include_sensitive' => $includeSensitive,
            'configs' => []
        ];
        
        foreach ($configs as $config) {
            $export['configs'][$config['config_key']] = [
                'value' => $config['config_value'],
                'type' => $config['config_type'],
                'is_sensitive' => $config['is_sensitive'],
                'description' => $config['description'],
                'validation_rules' => $config['validation_rules']
            ];
        }
        
        return $export;
    }
}
