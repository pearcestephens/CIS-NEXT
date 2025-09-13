<?php
/**
 * Secrets Management with Libsodium Encryption
 * File: app/Shared/Sec/Secrets.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Secure storage and retrieval of integration secrets
 */

namespace App\Shared\Sec;

use Exception;

class Secrets {
    private static ?string $master_key = null;
    
    /**
     * Initialize encryption with master key from environment
     */
    private static function initMasterKey(): void {
        if (self::$master_key === null) {
            $env_key = $_ENV['CIS_MASTER_ENCRYPTION_KEY'] ?? '';
            if (empty($env_key)) {
                // Generate a key if none exists (dev only)
                self::$master_key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
                error_log("WARNING: Generated temporary encryption key for development");
            } else {
                self::$master_key = $env_key;
            }
        }
    }
    
    /**
     * Encrypt a secret value using libsodium
     */
    public static function encrypt(string $plaintext): array {
        self::initMasterKey();
        
        try {
            $key = base64_decode(self::$master_key);
            if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new Exception("Invalid master key length");
            }
            
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
            
            return [
                'encrypted_value' => base64_encode($ciphertext),
                'nonce' => base64_encode($nonce)
            ];
            
        } catch (Exception $e) {
            error_log("Encryption failed: " . $e->getMessage());
            throw new Exception("Failed to encrypt secret");
        }
    }
    
    /**
     * Decrypt a secret value using libsodium
     */
    public static function decrypt(string $encrypted_value, string $nonce): string {
        self::initMasterKey();
        
        try {
            $key = base64_decode(self::$master_key);
            $ciphertext = base64_decode($encrypted_value);
            $nonce_bytes = base64_decode($nonce);
            
            if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new Exception("Invalid master key length");
            }
            
            if (strlen($nonce_bytes) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new Exception("Invalid nonce length");
            }
            
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce_bytes, $key);
            
            if ($plaintext === false) {
                throw new Exception("Decryption verification failed");
            }
            
            return $plaintext;
            
        } catch (Exception $e) {
            error_log("Decryption failed: " . $e->getMessage());
            throw new Exception("Failed to decrypt secret");
        }
    }
    
    /**
     * Store encrypted secret in database
     */
    public static function store(string $service_name, string $secret_key, string $plaintext_value, int $created_by = 1): bool {
        global $mysqli;
        
        try {
            $encrypted = self::encrypt($plaintext_value);
            
            $sql = "INSERT INTO cis_integration_secrets 
                    (service_name, secret_key, encrypted_value, encryption_nonce, created_by) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    encrypted_value = VALUES(encrypted_value),
                    encryption_nonce = VALUES(encryption_nonce),
                    updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ssssi', 
                $service_name, 
                $secret_key, 
                $encrypted['encrypted_value'], 
                $encrypted['nonce'], 
                $created_by
            );
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Failed to store secret: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve and decrypt secret from database
     */
    public static function get(string $service_name, string $secret_key): ?string {
        global $mysqli;
        
        try {
            $sql = "SELECT encrypted_value, encryption_nonce 
                    FROM cis_integration_secrets 
                    WHERE service_name = ? AND secret_key = ? AND is_active = 1";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ss', $service_name, $secret_key);
            $stmt->execute();
            
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return self::decrypt($row['encrypted_value'], $row['encryption_nonce']);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Failed to retrieve secret: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * List all secret keys for a service (without values)
     */
    public static function listKeys(string $service_name): array {
        global $mysqli;
        
        try {
            $sql = "SELECT secret_key, created_at, updated_at 
                    FROM cis_integration_secrets 
                    WHERE service_name = ? AND is_active = 1 
                    ORDER BY secret_key";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('s', $service_name);
            $stmt->execute();
            
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to list secret keys: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete a secret
     */
    public static function delete(string $service_name, string $secret_key): bool {
        global $mysqli;
        
        try {
            $sql = "UPDATE cis_integration_secrets 
                    SET is_active = 0, updated_at = CURRENT_TIMESTAMP 
                    WHERE service_name = ? AND secret_key = ?";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ss', $service_name, $secret_key);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Failed to delete secret: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test encryption/decryption functionality
     */
    public static function test(): array {
        try {
            $test_data = "test_secret_value_" . time();
            $encrypted = self::encrypt($test_data);
            $decrypted = self::decrypt($encrypted['encrypted_value'], $encrypted['nonce']);
            
            return [
                'success' => ($decrypted === $test_data),
                'original' => $test_data,
                'decrypted' => $decrypted,
                'match' => ($decrypted === $test_data)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
