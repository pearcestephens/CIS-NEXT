<?php
declare(strict_types=1);

namespace App\Shared\Logging;

use App\Shared\Config\Config;

/**
 * Logger
 * Structured JSON logging with PSR-3 compatibility
 */
class Logger
{
    private static ?Logger $instance = null;
    
    private string $logPath;
    private string $level;
    private array $context = [];
    
    private const LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];
    
    private function __construct(array $config)
    {
        $this->logPath = $config['path'] ?? '/tmp';
        $this->level = $config['level'] ?? 'info';
        
        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    public static function initialize(array $config): void
    {
        self::$instance = new self($config);
    }
    
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Logger not initialized');
        }
        
        return self::$instance;
    }
    
    public function withContext(array $context): self
    {
        $logger = clone $this;
        $logger->context = array_merge($this->context, $context);
        return $logger;
    }
    
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }
    
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
    
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }
    
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
    
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $entry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => array_merge($this->context, $context),
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? $this->generateRequestId(),
            'memory' => memory_get_usage(true),
            'process_id' => getmypid(),
        ];
        
        // Add request context if available
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $entry['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
        }
        
        $this->writeToFile($entry);
        
        // Also write to system events table if database is available
        $this->writeToDatabase($entry);
    }
    
    private function shouldLog(string $level): bool
    {
        $configLevel = self::LEVELS[$this->level] ?? 6;
        $messageLevel = self::LEVELS[$level] ?? 6;
        
        return $messageLevel <= $configLevel;
    }
    
    private function writeToFile(array $entry): void
    {
        $filename = $this->logPath . '/app_' . date('Y-m-d') . '.log';
        $json = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
        
        file_put_contents($filename, $json, FILE_APPEND | LOCK_EX);
    }
    
    private function writeToDatabase(array $entry): void
    {
        try {
            // Only write error and above to database
            $level = strtolower($entry['level']);
            if (isset(self::LEVELS[$level]) && self::LEVELS[$level] <= 3) {
                $db = \App\Infra\Persistence\MariaDB\Database::getInstance();
                $prefix = $db->getTablePrefix();
                
                $stmt = $db->prepare("
                    INSERT INTO {$prefix}system_events (level, channel, message, context, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $level,
                    'application',
                    $entry['message'],
                    json_encode($entry['context'])
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail database logging to prevent infinite loops
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }
    
    private function generateRequestId(): string
    {
        return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 12);
    }
    
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
