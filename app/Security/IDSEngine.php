<?php
/**
 * IDS/IPS Security Engine - Stage 12 Second Hardening Pass
 * File: app/Security/IDSEngine.php
 * Purpose: Real-time intrusion detection and prevention system
 */

declare(strict_types=1);

namespace App\Security;

class IDSEngine
{
    private array $rules;
    private array $blockedIPs;
    private array $suspiciousPatterns;
    private string $logPath;
    private int $maxViolations;
    
    public function __construct()
    {
        $this->logPath = __DIR__ . '/../../var/logs/ids_alerts.log';
        $this->maxViolations = 5;
        $this->initializeRules();
        $this->loadBlockedIPs();
    }
    
    /**
     * Initialize IDS/IPS detection rules
     */
    private function initializeRules(): void
    {
        $this->rules = [
            'sql_injection' => [
                'patterns' => [
                    '/(\bunion\b.*\bselect\b)/i',
                    '/(\bor\b\s+\d+\s*=\s*\d+)/i',  
                    '/(\band\b\s+\d+\s*=\s*\d+)/i',
                    '/(\bdrop\b\s+table)/i',
                    '/(\binsert\b\s+into)/i',
                    '/(\bdelete\b\s+from)/i',
                    '/(\bupdate\b.*\bset\b)/i',
                    '/(\bexec\b\s*\()/i'
                ],
                'severity' => 'HIGH',
                'action' => 'BLOCK',
                'description' => 'SQL Injection Attempt'
            ],
            'xss_injection' => [
                'patterns' => [
                    '/<script[^>]*>.*?<\/script>/i',
                    '/javascript\s*:/i',
                    '/on\w+\s*=\s*["\'].*?["\']>/i',
                    '/<iframe[^>]*>/i',
                    '/<embed[^>]*>/i',
                    '/<object[^>]*>/i'
                ],
                'severity' => 'HIGH', 
                'action' => 'SANITIZE',
                'description' => 'XSS Injection Attempt'
            ],
            'file_inclusion' => [
                'patterns' => [
                    '/\.\.\//i',
                    '/\.\.\\\\/i',
                    '/\/etc\/passwd/i',
                    '/\/proc\/self\/environ/i',
                    '/php:\/\/filter/i',
                    '/data:\/\/text\/plain/i'
                ],
                'severity' => 'CRITICAL',
                'action' => 'BLOCK',
                'description' => 'File Inclusion Attack'
            ],
            'command_injection' => [
                'patterns' => [
                    '/;\s*\w+/i',
                    '/\|\s*\w+/i',
                    '/`[^`]*`/i',
                    '/\$\([^)]*\)/i',
                    '/&&\s*\w+/i',
                    '/\|\|\s*\w+/i'
                ],
                'severity' => 'CRITICAL',
                'action' => 'BLOCK',
                'description' => 'Command Injection Attempt'
            ],
            'brute_force' => [
                'patterns' => [
                    'rate_limit' => true,
                    'max_attempts' => 5,
                    'time_window' => 300 // 5 minutes
                ],
                'severity' => 'MEDIUM',
                'action' => 'RATE_LIMIT',
                'description' => 'Brute Force Attack'
            ]
        ];
    }
    
    /**
     * Load blocked IP addresses from database/cache
     */
    private function loadBlockedIPs(): void
    {
        $cacheFile = __DIR__ . '/../../var/cache/blocked_ips.json';
        
        if (file_exists($cacheFile)) {
            $this->blockedIPs = json_decode(file_get_contents($cacheFile), true) ?? [];
        } else {
            $this->blockedIPs = [];
        }
    }
    
    /**
     * Main IDS scanning function
     */
    public function scanRequest(array $requestData): array
    {
        $clientIP = $this->getClientIP();
        $violations = [];
        
        // Check if IP is already blocked
        if ($this->isIPBlocked($clientIP)) {
            return [
                'status' => 'BLOCKED',
                'reason' => 'IP Address Blocked',
                'action' => 'DENY_REQUEST',
                'ip' => $clientIP
            ];
        }
        
        // Scan all request data
        $allData = $this->flattenRequestData($requestData);
        
        foreach ($this->rules as $ruleName => $rule) {
            if ($ruleName === 'brute_force') {
                $bruteForceCheck = $this->checkBruteForce($clientIP);
                if ($bruteForceCheck['violation']) {
                    $violations[] = $bruteForceCheck;
                }
                continue;
            }
            
            foreach ($rule['patterns'] as $pattern) {
                foreach ($allData as $key => $value) {
                    if (is_string($value) && preg_match($pattern, $value)) {
                        $violations[] = [
                            'rule' => $ruleName,
                            'pattern' => $pattern,
                            'field' => $key,
                            'value' => $this->sanitizeLogValue($value),
                            'severity' => $rule['severity'],
                            'action' => $rule['action'],
                            'description' => $rule['description'],
                            'timestamp' => time(),
                            'ip' => $clientIP
                        ];
                    }
                }
            }
        }
        
        // Process violations
        return $this->processViolations($violations, $clientIP);
    }
    
    /**
     * Flatten request data for scanning
     */
    private function flattenRequestData(array $data, string $prefix = ''): array
    {
        $flattened = [];
        
        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $flattened = array_merge($flattened, $this->flattenRequestData($value, $newKey));
            } else {
                $flattened[$newKey] = $value;
            }
        }
        
        return $flattened;
    }
    
    /**
     * Check for brute force attacks
     */
    private function checkBruteForce(string $ip): array
    {
        $cacheKey = "bf_attempts_{$ip}";
        $attempts = $this->getCacheValue($cacheKey, 0);
        $rule = $this->rules['brute_force'];
        
        if ($attempts >= $rule['patterns']['max_attempts']) {
            return [
                'violation' => true,
                'rule' => 'brute_force',
                'attempts' => $attempts,
                'severity' => $rule['severity'],
                'action' => $rule['action'],
                'description' => $rule['description'],
                'ip' => $ip
            ];
        }
        
        return ['violation' => false];
    }
    
    /**
     * Process violations and determine response
     */
    private function processViolations(array $violations, string $ip): array
    {
        if (empty($violations)) {
            return [
                'status' => 'CLEAN',
                'violations' => 0,
                'action' => 'ALLOW'
            ];
        }
        
        // Log all violations
        foreach ($violations as $violation) {
            $this->logViolation($violation);
        }
        
        // Determine highest severity action
        $highestSeverity = 'LOW';
        $primaryAction = 'LOG';
        $criticalViolations = 0;
        
        foreach ($violations as $violation) {
            if ($violation['severity'] === 'CRITICAL') {
                $highestSeverity = 'CRITICAL';
                $primaryAction = 'BLOCK';
                $criticalViolations++;
            } elseif ($violation['severity'] === 'HIGH' && $highestSeverity !== 'CRITICAL') {
                $highestSeverity = 'HIGH';
                $primaryAction = $violation['action'];
            }
        }
        
        // Auto-block for multiple critical violations
        if ($criticalViolations >= 2) {
            $this->blockIP($ip, 'Multiple critical violations');
            $primaryAction = 'BLOCK';
        }
        
        return [
            'status' => 'VIOLATIONS_DETECTED',
            'violations' => count($violations),
            'highest_severity' => $highestSeverity,
            'action' => $primaryAction,
            'details' => $violations,
            'ip' => $ip,
            'blocked' => in_array($primaryAction, ['BLOCK'])
        ];
    }
    
    /**
     * Block IP address
     */
    public function blockIP(string $ip, string $reason): void
    {
        $this->blockedIPs[$ip] = [
            'blocked_at' => time(),
            'reason' => $reason,
            'expires' => time() + (24 * 3600) // 24 hours
        ];
        
        // Save to cache
        $cacheFile = __DIR__ . '/../../var/cache/blocked_ips.json';
        file_put_contents($cacheFile, json_encode($this->blockedIPs));
        
        // Log blocking action
        $this->logAlert([
            'type' => 'IP_BLOCKED',
            'ip' => $ip,
            'reason' => $reason,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Check if IP is blocked
     */
    private function isIPBlocked(string $ip): bool
    {
        if (!isset($this->blockedIPs[$ip])) {
            return false;
        }
        
        $blockInfo = $this->blockedIPs[$ip];
        
        // Check if block has expired
        if ($blockInfo['expires'] < time()) {
            unset($this->blockedIPs[$ip]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log security violation
     */
    private function logViolation(array $violation): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'type' => 'SECURITY_VIOLATION',
            'severity' => $violation['severity'],
            'rule' => $violation['rule'],
            'description' => $violation['description'],
            'ip' => $violation['ip'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'field' => $violation['field'] ?? 'Unknown',
            'pattern_matched' => $violation['pattern'] ?? 'Unknown'
        ];
        
        $this->writeLog(json_encode($logEntry));
    }
    
    /**
     * Log security alert
     */
    private function logAlert(array $alert): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'type' => 'SECURITY_ALERT',
            'alert' => $alert
        ];
        
        $this->writeLog(json_encode($logEntry));
    }
    
    /**
     * Write to log file
     */
    private function writeLog(string $message): void
    {
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->logPath, $message . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Sanitize value for logging
     */
    private function sanitizeLogValue(string $value): string
    {
        return substr(addslashes($value), 0, 200);
    }
    
    /**
     * Get cache value (simple file-based cache)
     */
    private function getCacheValue(string $key, $default = null)
    {
        $cacheFile = __DIR__ . "/../../var/cache/{$key}.cache";
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && $data['expires'] > time()) {
                return $data['value'];
            }
        }
        
        return $default;
    }
    
    /**
     * Set cache value
     */
    private function setCacheValue(string $key, $value, int $ttl = 3600): void
    {
        $cacheFile = __DIR__ . "/../../var/cache/{$key}.cache";
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($cacheFile, json_encode($data));
    }
    
    /**
     * Get IDS statistics
     */
    public function getStatistics(): array
    {
        $logFile = $this->logPath;
        $stats = [
            'total_violations' => 0,
            'blocked_ips' => count($this->blockedIPs),
            'violations_by_type' => [],
            'violations_by_severity' => [],
            'recent_violations' => []
        ];
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES);
            
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry && $entry['type'] === 'SECURITY_VIOLATION') {
                    $stats['total_violations']++;
                    
                    $rule = $entry['rule'] ?? 'unknown';
                    $severity = $entry['severity'] ?? 'unknown';
                    
                    $stats['violations_by_type'][$rule] = ($stats['violations_by_type'][$rule] ?? 0) + 1;
                    $stats['violations_by_severity'][$severity] = ($stats['violations_by_severity'][$severity] ?? 0) + 1;
                    
                    if (count($stats['recent_violations']) < 10) {
                        $stats['recent_violations'][] = $entry;
                    }
                }
            }
        }
        
        return $stats;
    }
}
