<?php
/**
 * Enhanced Security Monitoring Dashboard - Stage 12 Second Hardening Pass
 * File: app/Http/Controllers/Admin/SecurityDashboardController.php
 * Purpose: Real-time security monitoring with IDS/IPS integration
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Security\IDSEngine;
use App\Http\Middlewares\IDSMiddleware;

class SecurityDashboardController extends BaseController
{
    private IDSEngine $idsEngine;
    private string $alertLogPath;
    private string $auditLogPath;
    
    public function __construct()
    {
        parent::__construct();
        $this->idsEngine = new IDSEngine();
        $this->alertLogPath = __DIR__ . '/../../../var/logs/ids_alerts.log';
        $this->auditLogPath = __DIR__ . '/../../../var/logs/security_audit.log';
    }
    
    /**
     * Security dashboard main view
     */
    public function dashboard()
    {
        $this->requirePermission('security.dashboard');
        
        $data = [
            'page_title' => 'Security Dashboard',
            'security_metrics' => $this->getSecurityMetrics(),
            'recent_alerts' => $this->getRecentAlerts(),
            'threat_summary' => $this->getThreatSummary(),
            'blocked_ips' => $this->getBlockedIPs(),
            'system_status' => $this->getSystemSecurityStatus()
        ];
        
        return $this->render('admin/security_dashboard', $data);
    }
    
    /**
     * Get real-time security metrics
     */
    public function getSecurityMetrics(): array
    {
        $metrics = [
            'timestamp' => date('c'),
            'ids_status' => 'active',
            'total_violations' => 0,
            'blocked_ips' => 0,
            'alerts_last_24h' => 0,
            'threat_level' => 'low',
            'violations_by_type' => [],
            'hourly_stats' => []
        ];
        
        // Get IDS statistics
        $idsStats = $this->idsEngine->getStatistics();
        $metrics['total_violations'] = $idsStats['total_violations'];
        $metrics['blocked_ips'] = $idsStats['blocked_ips'];
        $metrics['violations_by_type'] = $idsStats['violations_by_type'];
        
        // Calculate threat level
        $alertsLast24h = $this->countAlertsLast24Hours();
        $metrics['alerts_last_24h'] = $alertsLast24h;
        
        if ($alertsLast24h > 100) {
            $metrics['threat_level'] = 'critical';
        } elseif ($alertsLast24h > 50) {
            $metrics['threat_level'] = 'high';
        } elseif ($alertsLast24h > 20) {
            $metrics['threat_level'] = 'medium';
        }
        
        // Get hourly statistics for the last 24 hours
        $metrics['hourly_stats'] = $this->getHourlyStats();
        
        return $metrics;
    }
    
    /**
     * Get recent security alerts
     */
    public function getRecentAlerts(int $limit = 50): array
    {
        $alerts = [];
        
        if (file_exists($this->alertLogPath)) {
            $lines = file($this->alertLogPath, FILE_IGNORE_NEW_LINES);
            $lines = array_reverse($lines); // Get most recent first
            
            $count = 0;
            foreach ($lines as $line) {
                if ($count >= $limit) break;
                
                $alert = json_decode($line, true);
                if ($alert) {
                    // Enhance alert with risk assessment
                    $alert = $this->enhanceAlertData($alert);
                    $alerts[] = $alert;
                    $count++;
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get threat summary for dashboard
     */
    public function getThreatSummary(): array
    {
        $summary = [
            'active_threats' => 0,
            'mitigated_threats' => 0,
            'false_positives' => 0,
            'top_attack_types' => [],
            'top_source_countries' => [],
            'attack_timeline' => []
        ];
        
        if (file_exists($this->alertLogPath)) {
            $lines = file($this->alertLogPath, FILE_IGNORE_NEW_LINES);
            
            $threatTypes = [];
            $countries = [];
            $timeline = [];
            
            $last24Hours = time() - (24 * 3600);
            
            foreach ($lines as $line) {
                $alert = json_decode($line, true);
                if (!$alert) continue;
                
                $alertTime = strtotime($alert['timestamp'] ?? '');
                if ($alertTime < $last24Hours) continue;
                
                // Count threat types
                $rule = $alert['rule'] ?? 'unknown';
                $threatTypes[$rule] = ($threatTypes[$rule] ?? 0) + 1;
                
                // Get country from IP (placeholder - would use GeoIP)
                $country = $this->getCountryFromIP($alert['ip'] ?? '');
                if ($country) {
                    $countries[$country] = ($countries[$country] ?? 0) + 1;
                }
                
                // Build timeline
                $hour = date('Y-m-d H:00', $alertTime);
                $timeline[$hour] = ($timeline[$hour] ?? 0) + 1;
                
                $summary['active_threats']++;
            }
            
            // Sort and limit top results
            arsort($threatTypes);
            arsort($countries);
            
            $summary['top_attack_types'] = array_slice($threatTypes, 0, 10, true);
            $summary['top_source_countries'] = array_slice($countries, 0, 10, true);
            $summary['attack_timeline'] = $timeline;
        }
        
        return $summary;
    }
    
    /**
     * Get list of blocked IPs with details
     */
    public function getBlockedIPs(): array
    {
        $blockedFile = __DIR__ . '/../../../var/cache/blocked_ips.json';
        $blocked = [];
        
        if (file_exists($blockedFile)) {
            $data = json_decode(file_get_contents($blockedFile), true);
            
            foreach ($data as $ip => $info) {
                // Check if block is still active
                if ($info['expires'] > time()) {
                    $blocked[] = [
                        'ip' => $ip,
                        'reason' => $info['reason'],
                        'blocked_at' => date('c', $info['blocked_at']),
                        'expires_at' => date('c', $info['expires']),
                        'remaining_hours' => round(($info['expires'] - time()) / 3600, 1),
                        'country' => $this->getCountryFromIP($ip)
                    ];
                }
            }
        }
        
        return $blocked;
    }
    
    /**
     * Get system security status
     */
    public function getSystemSecurityStatus(): array
    {
        return [
            'ids_engine' => [
                'status' => 'active',
                'rules_loaded' => 5,
                'last_update' => date('c')
            ],
            'firewall' => [
                'status' => 'active',
                'blocked_ips' => count($this->getBlockedIPs())
            ],
            'ssl_certificate' => [
                'status' => $this->checkSSLStatus(),
                'expires' => $this->getSSLExpiration()
            ],
            'file_integrity' => [
                'status' => $this->checkFileIntegrity(),
                'last_scan' => $this->getLastIntegrityCheck()
            ],
            'backup_status' => [
                'status' => $this->getBackupStatus(),
                'last_backup' => $this->getLastBackupTime()
            ]
        ];
    }
    
    /**
     * API endpoint for real-time metrics
     */
    public function metricsAPI()
    {
        header('Content-Type: application/json');
        
        $action = $_GET['action'] ?? 'metrics';
        
        switch ($action) {
            case 'metrics':
                echo json_encode($this->getSecurityMetrics());
                break;
                
            case 'alerts':
                $limit = intval($_GET['limit'] ?? 20);
                echo json_encode($this->getRecentAlerts($limit));
                break;
                
            case 'threats':
                echo json_encode($this->getThreatSummary());
                break;
                
            case 'blocked':
                echo json_encode($this->getBlockedIPs());
                break;
                
            case 'status':
                echo json_encode($this->getSystemSecurityStatus());
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    }
    
    /**
     * Manual IP blocking/unblocking
     */
    public function manageBlocks()
    {
        $this->requirePermission('security.manage_blocks');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $ip = $_POST['ip'] ?? '';
            $reason = $_POST['reason'] ?? 'Manual block';
            
            switch ($action) {
                case 'block':
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $this->idsEngine->blockIP($ip, $reason);
                        $this->auditLog('IP_MANUALLY_BLOCKED', ['ip' => $ip, 'reason' => $reason]);
                        $this->setFlash('success', "IP {$ip} has been blocked");
                    } else {
                        $this->setFlash('error', 'Invalid IP address');
                    }
                    break;
                    
                case 'unblock':
                    if ($this->unblockIP($ip)) {
                        $this->auditLog('IP_MANUALLY_UNBLOCKED', ['ip' => $ip]);
                        $this->setFlash('success', "IP {$ip} has been unblocked");
                    } else {
                        $this->setFlash('error', 'Failed to unblock IP');
                    }
                    break;
            }
            
            $this->redirect('/admin/security/dashboard');
        }
        
        return $this->render('admin/security_manage_blocks', [
            'blocked_ips' => $this->getBlockedIPs()
        ]);
    }
    
    /**
     * Security audit log viewer
     */
    public function auditLog()
    {
        $this->requirePermission('security.audit_log');
        
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 100);
        
        $logs = $this->getAuditLogs($page, $limit);
        
        return $this->render('admin/security_audit_log', [
            'logs' => $logs,
            'page' => $page,
            'limit' => $limit
        ]);
    }
    
    /**
     * Generate security report
     */
    public function generateReport()
    {
        $this->requirePermission('security.reports');
        
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        $report = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'summary' => $this->getReportSummary($startDate, $endDate),
            'violations' => $this->getViolationsReport($startDate, $endDate),
            'blocked_ips' => $this->getBlockedIPsReport($startDate, $endDate),
            'recommendations' => $this->getSecurityRecommendations()
        ];
        
        if ($_GET['format'] === 'json') {
            header('Content-Type: application/json');
            echo json_encode($report, JSON_PRETTY_PRINT);
            return;
        }
        
        return $this->render('admin/security_report', ['report' => $report]);
    }
    
    // Helper methods
    
    private function enhanceAlertData(array $alert): array
    {
        $alert['risk_score'] = $this->calculateRiskScore($alert);
        $alert['country'] = $this->getCountryFromIP($alert['ip'] ?? '');
        return $alert;
    }
    
    private function calculateRiskScore(array $alert): int
    {
        $score = 0;
        
        switch ($alert['severity'] ?? 'low') {
            case 'critical': $score += 40; break;
            case 'high': $score += 30; break;
            case 'medium': $score += 20; break;
            case 'low': $score += 10; break;
        }
        
        // Add points for repeated violations from same IP
        // This would require tracking IP history
        
        return min($score, 100);
    }
    
    private function countAlertsLast24Hours(): int
    {
        $count = 0;
        $cutoff = time() - (24 * 3600);
        
        if (file_exists($this->alertLogPath)) {
            $lines = file($this->alertLogPath, FILE_IGNORE_NEW_LINES);
            
            foreach ($lines as $line) {
                $alert = json_decode($line, true);
                if ($alert && strtotime($alert['timestamp'] ?? '') > $cutoff) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    private function getHourlyStats(): array
    {
        $stats = [];
        $cutoff = time() - (24 * 3600);
        
        if (file_exists($this->alertLogPath)) {
            $lines = file($this->alertLogPath, FILE_IGNORE_NEW_LINES);
            
            foreach ($lines as $line) {
                $alert = json_decode($line, true);
                if (!$alert) continue;
                
                $alertTime = strtotime($alert['timestamp'] ?? '');
                if ($alertTime < $cutoff) continue;
                
                $hour = date('H', $alertTime);
                $stats[$hour] = ($stats[$hour] ?? 0) + 1;
            }
        }
        
        // Fill in missing hours with 0
        for ($i = 0; $i < 24; $i++) {
            $hour = sprintf('%02d', $i);
            if (!isset($stats[$hour])) {
                $stats[$hour] = 0;
            }
        }
        
        ksort($stats);
        return $stats;
    }
    
    private function getCountryFromIP(string $ip): ?string
    {
        // Placeholder - would use GeoIP database
        return null;
    }
    
    private function checkSSLStatus(): string
    {
        return 'active'; // Placeholder
    }
    
    private function getSSLExpiration(): ?string
    {
        return null; // Placeholder
    }
    
    private function checkFileIntegrity(): string
    {
        return 'good'; // Placeholder
    }
    
    private function getLastIntegrityCheck(): ?string
    {
        return date('c'); // Placeholder
    }
    
    private function getBackupStatus(): string
    {
        $backupFile = __DIR__ . '/../../../var/logs/encrypted_backup.log';
        
        if (file_exists($backupFile)) {
            $lines = file($backupFile, FILE_IGNORE_NEW_LINES);
            $lastLine = end($lines);
            $lastEntry = json_decode($lastLine, true);
            
            if ($lastEntry && $lastEntry['event'] === 'COMPLETE_BACKUP') {
                $lastBackup = strtotime($lastEntry['timestamp']);
                $hoursAgo = (time() - $lastBackup) / 3600;
                
                if ($hoursAgo < 25) { // Within last 25 hours
                    return 'good';
                } elseif ($hoursAgo < 49) { // Within 48 hours
                    return 'warning';
                } else {
                    return 'critical';
                }
            }
        }
        
        return 'unknown';
    }
    
    private function getLastBackupTime(): ?string
    {
        $backupFile = __DIR__ . '/../../../var/logs/encrypted_backup.log';
        
        if (file_exists($backupFile)) {
            $lines = file($backupFile, FILE_IGNORE_NEW_LINES);
            $lastLine = end($lines);
            $lastEntry = json_decode($lastLine, true);
            
            if ($lastEntry) {
                return $lastEntry['timestamp'];
            }
        }
        
        return null;
    }
    
    private function unblockIP(string $ip): bool
    {
        $blockedFile = __DIR__ . '/../../../var/cache/blocked_ips.json';
        
        if (file_exists($blockedFile)) {
            $data = json_decode(file_get_contents($blockedFile), true);
            
            if (isset($data[$ip])) {
                unset($data[$ip]);
                file_put_contents($blockedFile, json_encode($data));
                return true;
            }
        }
        
        return false;
    }
    
    private function auditLog(string $event, array $data): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'user_id' => $this->getCurrentUserId(),
            'event' => $event,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $logDir = dirname($this->auditLogPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->auditLogPath, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    private function getAuditLogs(int $page, int $limit): array
    {
        $logs = [];
        
        if (file_exists($this->auditLogPath)) {
            $lines = file($this->auditLogPath, FILE_IGNORE_NEW_LINES);
            $lines = array_reverse($lines); // Most recent first
            
            $start = ($page - 1) * $limit;
            $end = $start + $limit;
            
            for ($i = $start; $i < $end && $i < count($lines); $i++) {
                $log = json_decode($lines[$i], true);
                if ($log) {
                    $logs[] = $log;
                }
            }
        }
        
        return $logs;
    }
    
    private function getReportSummary(string $startDate, string $endDate): array
    {
        // Placeholder implementation
        return [
            'total_violations' => 0,
            'blocked_ips' => 0,
            'false_positives' => 0,
            'top_threats' => []
        ];
    }
    
    private function getViolationsReport(string $startDate, string $endDate): array
    {
        return []; // Placeholder
    }
    
    private function getBlockedIPsReport(string $startDate, string $endDate): array
    {
        return []; // Placeholder
    }
    
    private function getSecurityRecommendations(): array
    {
        return [
            'Update IDS rules regularly',
            'Monitor for new attack patterns',
            'Review blocked IPs monthly',
            'Maintain backup integrity'
        ];
    }
}
