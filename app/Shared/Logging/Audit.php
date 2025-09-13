<?php
declare(strict_types=1);

namespace App\Shared\Logging;

use App\Shared\Logging\Logger;
use App\Infra\Persistence\MariaDB\Database;

/**
 * Audit Trail System
 * 
 * Captures CRUD operations with PII redaction for compliance
 * 
 * @package App\Shared\Logging
 */
class Audit
{
    private static ?self $instance = null;
    private Logger $logger;
    private Database $database;

    private function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->database = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log CRUD operation with PII redaction
     */
    public function logCrud(
        string $action,
        string $tableName,
        int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): bool {
        try {
            // Redact PII from values
            $redactedOld = $oldValues ? $this->redactPii($oldValues) : null;
            $redactedNew = $newValues ? $this->redactPii($newValues) : null;

            // Get request context
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            // Insert audit record
            $sql = "INSERT INTO cis_audit_log 
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $params = [
                $userId,
                strtoupper($action),
                $tableName,
                $recordId,
                $redactedOld ? json_encode($redactedOld) : null,
                $redactedNew ? json_encode($redactedNew) : null,
                $ipAddress,
                $userAgent
            ];

            $this->database->executeQuery($sql, $params);

            $this->logger->info('Audit entry created', [
                'action' => $action,
                'table' => $tableName,
                'record_id' => $recordId,
                'user_id' => $userId
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to create audit entry', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'action' => $action,
                'table' => $tableName
            ]);

            return false;
        }
    }

    /**
     * Retrieve audit records with pagination
     */
    public function getAuditRecords(
        ?string $tableName = null,
        ?int $userId = null,
        ?string $action = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $conditions = [];
        $params = [];

        if ($tableName) {
            $conditions[] = "table_name = ?";
            $params[] = $tableName;
        }

        if ($userId) {
            $conditions[] = "user_id = ?";
            $params[] = $userId;
        }

        if ($action) {
            $conditions[] = "action = ?";
            $params[] = strtoupper($action);
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT 
                    id, user_id, action, table_name, record_id, 
                    old_values, new_values, ip_address, user_agent, created_at
                FROM cis_audit_log 
                {$whereClause}
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->database->executeQuery($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get audit record count
     */
    public function getAuditCount(?string $tableName = null, ?int $userId = null): int
    {
        $conditions = [];
        $params = [];

        if ($tableName) {
            $conditions[] = "table_name = ?";
            $params[] = $tableName;
        }

        if ($userId) {
            $conditions[] = "user_id = ?";
            $params[] = $userId;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT COUNT(*) as count FROM cis_audit_log {$whereClause}";
        
        $stmt = $this->database->executeQuery($sql, $params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return (int) $result['count'];
    }

    /**
     * Redact personally identifiable information
     */
    private function redactPii(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            // Email redaction
            if (in_array($lowerKey, ['email', 'email_address', 'user_email'])) {
                $redacted[$key] = $this->maskEmail((string) $value);
            }
            // Phone redaction
            elseif (in_array($lowerKey, ['phone', 'phone_number', 'mobile', 'telephone'])) {
                $redacted[$key] = $this->maskPhone((string) $value);
            }
            // Token/password redaction
            elseif (in_array($lowerKey, ['password', 'token', 'api_key', 'secret', 'hash'])) {
                $redacted[$key] = '***REDACTED***';
            }
            // Credit card redaction
            elseif (in_array($lowerKey, ['card_number', 'credit_card', 'cc_number'])) {
                $redacted[$key] = $this->maskCreditCard((string) $value);
            }
            // Keep other fields as-is
            else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Mask email addresses
     */
    private function maskEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '***INVALID_EMAIL***';
        }

        $parts = explode('@', $email);
        $username = $parts[0];
        $domain = $parts[1];

        // Show first and last character of username
        if (strlen($username) <= 2) {
            $maskedUsername = str_repeat('*', strlen($username));
        } else {
            $maskedUsername = $username[0] . str_repeat('*', strlen($username) - 2) . $username[-1];
        }

        return $maskedUsername . '@' . $domain;
    }

    /**
     * Mask phone numbers
     */
    private function maskPhone(string $phone): string
    {
        // Remove non-digit characters
        $digits = preg_replace('/\D/', '', $phone);
        
        if (strlen($digits) < 4) {
            return str_repeat('*', strlen($phone));
        }

        // Show last 4 digits
        $masked = str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
        
        return $masked;
    }

    /**
     * Mask credit card numbers
     */
    private function maskCreditCard(string $cardNumber): string
    {
        $digits = preg_replace('/\D/', '', $cardNumber);
        
        if (strlen($digits) < 4) {
            return str_repeat('*', strlen($cardNumber));
        }

        // Show last 4 digits
        return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
    }

    /**
     * Clean up old audit records
     */
    public function cleanupOldRecords(int $daysToKeep = 365): int
    {
        $sql = "DELETE FROM cis_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->database->executeQuery($sql, [$daysToKeep]);
        $deletedCount = $stmt->rowCount();

        $this->logger->info('Audit records cleanup completed', [
            'days_kept' => $daysToKeep,
            'records_deleted' => $deletedCount
        ]);

        return $deletedCount;
    }
}
