<?php
/**
 * Privacy-Compliant Session Recording System
 * File: app/Monitoring/SessionRecorder.php
 * Purpose: GDPR/Privacy-compliant user activity monitoring
 */

declare(strict_types=1);

namespace App\Monitoring;

class SessionRecorder
{
    private bool $recordingEnabled;
    private array $consentedUsers;
    private array $redactionRules;
    private string $recordingPath;
    
    public function __construct()
    {
        $this->recordingPath = __DIR__ . '/../../var/recordings';
        $this->recordingEnabled = false; // Disabled by default
        $this->loadConsentedUsers();
        $this->initializeRedactionRules();
    }
    
    /**
     * Initialize data redaction rules for privacy compliance
     */
    private function initializeRedactionRules(): void
    {
        $this->redactionRules = [
            // Password fields - NEVER record
            'password_fields' => [
                'input[type="password"]',
                'input[name*="password"]',
                'input[name*="passwd"]',
                'input[id*="password"]'
            ],
            
            // Sensitive form fields
            'sensitive_fields' => [
                'input[name*="ssn"]',
                'input[name*="social"]', 
                'input[name*="credit"]',
                'input[name*="card"]',
                'input[name*="cvv"]',
                'input[name*="pin"]'
            ],
            
            // PII fields that require masking
            'pii_fields' => [
                'input[name*="email"]',
                'input[name*="phone"]',
                'input[name*="address"]'
            ],
            
            // Elements to completely exclude from recording
            'excluded_elements' => [
                '.sensitive-data',
                '.confidential',
                '.no-record',
                '[data-sensitive="true"]'
            ]
        ];
    }
    
    /**
     * Check if user has consented to monitoring
     */
    public function hasUserConsented(int $userId): bool
    {
        return isset($this->consentedUsers[$userId]) && 
               $this->consentedUsers[$userId]['active'] === true &&
               $this->consentedUsers[$userId]['expires'] > time();
    }
    
    /**
     * Record user consent for monitoring
     */
    public function recordConsent(int $userId, string $consentType = 'session_recording', int $durationDays = 30): bool
    {
        // Log consent action
        $this->logConsentAction($userId, 'GRANTED', $consentType);
        
        $this->consentedUsers[$userId] = [
            'consent_date' => time(),
            'consent_type' => $consentType,
            'expires' => time() + ($durationDays * 24 * 3600),
            'active' => true,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $this->saveConsentData();
        return true;
    }
    
    /**
     * Revoke user consent
     */
    public function revokeConsent(int $userId): bool
    {
        if (isset($this->consentedUsers[$userId])) {
            $this->consentedUsers[$userId]['active'] = false;
            $this->consentedUsers[$userId]['revoked_date'] = time();
            
            $this->logConsentAction($userId, 'REVOKED', 'session_recording');
            $this->saveConsentData();
            
            // Stop any active recordings for this user
            $this->stopUserRecording($userId);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate privacy-compliant monitoring script
     */
    public function generateMonitoringScript(int $userId): string
    {
        if (!$this->hasUserConsented($userId)) {
            return '// User has not consented to monitoring';
        }
        
        return $this->buildConsentCompliantScript($userId);
    }
    
    /**
     * Build JavaScript monitoring script with privacy controls
     */
    private function buildConsentCompliantScript(int $userId): string
    {
        $sessionId = uniqid('session_', true);
        $redactionRules = json_encode($this->redactionRules);
        
        return <<<JAVASCRIPT
// CIS Privacy-Compliant Session Recorder
(function() {
    'use strict';
    
    const CISMonitor = {
        userId: {$userId},
        sessionId: '{$sessionId}',
        recordingActive: true,
        redactionRules: {$redactionRules},
        events: [],
        
        // Initialize monitoring with privacy controls
        init: function() {
            this.showConsentBanner();
            this.setupEventListeners();
            this.startHeartbeat();
            
            console.log('CIS Monitoring: Privacy-compliant session recording active');
        },
        
        // Show consent banner to user
        showConsentBanner: function() {
            const banner = document.createElement('div');
            banner.id = 'cis-monitoring-banner';
            banner.style.cssText = `
                position: fixed; top: 0; left: 0; right: 0; z-index: 10000;
                background: #1e3a8a; color: white; padding: 10px; text-align: center;
                font-family: Arial, sans-serif; font-size: 14px;
            `;
            banner.innerHTML = `
                🔒 Session Recording Active - Your activity is being monitored for security purposes. 
                <button onclick="CISMonitor.stopRecording()" style="margin-left: 10px; padding: 5px 10px;">
                    Stop Recording
                </button>
            `;
            document.body.insertBefore(banner, document.body.firstChild);
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                banner.style.opacity = '0.7';
                banner.style.fontSize = '12px';
                banner.style.padding = '5px';
            }, 10000);
        },
        
        // Setup privacy-compliant event listeners
        setupEventListeners: function() {
            // Mouse movement (throttled)
            let mouseThrottle = false;
            document.addEventListener('mousemove', (e) => {
                if (mouseThrottle || !this.recordingActive) return;
                mouseThrottle = true;
                
                this.recordEvent('mouse_move', {
                    x: e.clientX,
                    y: e.clientY,
                    timestamp: Date.now()
                });
                
                setTimeout(() => mouseThrottle = false, 100); // 10fps max
            });
            
            // Click events (with element info but no sensitive data)
            document.addEventListener('click', (e) => {
                if (!this.recordingActive) return;
                
                const element = this.getElementInfo(e.target);
                this.recordEvent('click', {
                    element: element,
                    x: e.clientX,
                    y: e.clientY,
                    timestamp: Date.now()
                });
            });
            
            // Keyboard events (filtered for sensitive fields)
            document.addEventListener('keydown', (e) => {
                if (!this.recordingActive) return;
                
                const target = e.target;
                
                // NEVER record keystrokes in sensitive fields
                if (this.isSensitiveField(target)) {
                    this.recordEvent('keydown_sensitive', {
                        field: 'REDACTED_SENSITIVE_FIELD',
                        timestamp: Date.now()
                    });
                    return;
                }
                
                // Record non-sensitive keystrokes
                this.recordEvent('keydown', {
                    key: e.key === ' ' ? 'Space' : (e.key.length === 1 ? 'Char' : e.key),
                    element: this.getElementInfo(target),
                    timestamp: Date.now()
                });
            });
            
            // Page navigation
            window.addEventListener('beforeunload', () => {
                this.flushEvents();
            });
            
            // Focus/blur events
            window.addEventListener('focus', () => {
                this.recordEvent('window_focus', { timestamp: Date.now() });
            });
            
            window.addEventListener('blur', () => {
                this.recordEvent('window_blur', { timestamp: Date.now() });
            });
        },
        
        // Check if field contains sensitive data
        isSensitiveField: function(element) {
            if (!element || !element.tagName) return false;
            
            // Check password fields
            if (element.type === 'password') return true;
            
            // Check by name/id attributes
            const name = (element.name || '').toLowerCase();
            const id = (element.id || '').toLowerCase();
            const className = (element.className || '').toLowerCase();
            
            const sensitivePatterns = [
                'password', 'passwd', 'pwd', 'pin', 'cvv', 'ssn', 'social',
                'credit', 'card', 'bank', 'account', 'routing'
            ];
            
            return sensitivePatterns.some(pattern => 
                name.includes(pattern) || id.includes(pattern) || className.includes(pattern)
            );
        },
        
        // Get safe element information
        getElementInfo: function(element) {
            if (!element || !element.tagName) return null;
            
            return {
                tag: element.tagName.toLowerCase(),
                type: element.type || null,
                id: element.id || null,
                className: element.className || null,
                // NEVER include value for form elements
                text: element.tagName === 'BUTTON' ? element.textContent : null
            };
        },
        
        // Record event with privacy filtering
        recordEvent: function(type, data) {
            if (!this.recordingActive) return;
            
            this.events.push({
                type: type,
                data: data,
                url: window.location.pathname,
                timestamp: Date.now()
            });
            
            // Batch upload events when buffer is full
            if (this.events.length >= 50) {
                this.flushEvents();
            }
        },
        
        // Upload events to server
        flushEvents: function() {
            if (this.events.length === 0) return;
            
            const payload = {
                userId: this.userId,
                sessionId: this.sessionId,
                events: this.events,
                timestamp: Date.now()
            };
            
            fetch('/api/monitoring/record-events', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(payload)
            }).catch(err => {
                console.warn('CIS Monitoring: Failed to upload events', err);
            });
            
            this.events = []; // Clear buffer
        },
        
        // Heartbeat to maintain session
        startHeartbeat: function() {
            setInterval(() => {
                if (!this.recordingActive) return;
                
                fetch('/api/monitoring/heartbeat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userId: this.userId,
                        sessionId: this.sessionId,
                        timestamp: Date.now()
                    })
                });
            }, 30000); // Every 30 seconds
        },
        
        // Allow user to stop recording
        stopRecording: function() {
            this.recordingActive = false;
            
            // Remove banner
            const banner = document.getElementById('cis-monitoring-banner');
            if (banner) banner.remove();
            
            // Flush remaining events
            this.flushEvents();
            
            // Notify server
            fetch('/api/monitoring/stop-recording', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    userId: this.userId,
                    sessionId: this.sessionId,
                    timestamp: Date.now()
                })
            });
            
            console.log('CIS Monitoring: Recording stopped by user');
        }
    };
    
    // Make globally accessible
    window.CISMonitor = CISMonitor;
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => CISMonitor.init());
    } else {
        CISMonitor.init();
    }
})();
JAVASCRIPT;
    }
    
    /**
     * Process recorded events from JavaScript
     */
    public function processRecordedEvents(array $eventData): bool
    {
        $userId = $eventData['userId'];
        $sessionId = $eventData['sessionId'];
        
        // Verify user still has consent
        if (!$this->hasUserConsented($userId)) {
            return false;
        }
        
        // Additional server-side filtering
        $filteredEvents = $this->filterSensitiveEvents($eventData['events']);
        
        // Store events
        return $this->storeEvents($userId, $sessionId, $filteredEvents);
    }
    
    /**
     * Additional server-side event filtering
     */
    private function filterSensitiveEvents(array $events): array
    {
        $filtered = [];
        
        foreach ($events as $event) {
            // Skip events from sensitive pages
            if ($this->isSensitivePage($event['url'] ?? '')) {
                continue;
            }
            
            // Additional redaction for PII
            if (isset($event['data']['element']['text'])) {
                $event['data']['element']['text'] = $this->redactPII($event['data']['element']['text']);
            }
            
            $filtered[] = $event;
        }
        
        return $filtered;
    }
    
    // Helper methods...
    
    private function loadConsentedUsers(): void
    {
        $consentFile = $this->recordingPath . '/consents.json';
        if (file_exists($consentFile)) {
            $this->consentedUsers = json_decode(file_get_contents($consentFile), true) ?: [];
        } else {
            $this->consentedUsers = [];
        }
    }
    
    private function saveConsentData(): void
    {
        if (!is_dir($this->recordingPath)) {
            mkdir($this->recordingPath, 0755, true);
        }
        
        $consentFile = $this->recordingPath . '/consents.json';
        file_put_contents($consentFile, json_encode($this->consentedUsers, JSON_PRETTY_PRINT));
    }
    
    private function logConsentAction(int $userId, string $action, string $type): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'user_id' => $userId,
            'action' => $action,
            'type' => $type,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $logFile = $this->recordingPath . '/consent_log.json';
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    private function storeEvents(int $userId, string $sessionId, array $events): bool
    {
        $dateDir = $this->recordingPath . '/' . date('Y-m-d');
        if (!is_dir($dateDir)) {
            mkdir($dateDir, 0755, true);
        }
        
        $sessionFile = $dateDir . "/{$sessionId}.json";
        
        $sessionData = [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'start_time' => $events[0]['timestamp'] ?? time() * 1000,
            'events' => $events,
            'event_count' => count($events)
        ];
        
        return file_put_contents($sessionFile, json_encode($sessionData)) !== false;
    }
    
    private function isSensitivePage(string $url): bool
    {
        $sensitivePatterns = [
            '/admin/users/edit',
            '/admin/security',
            '/profile/password',
            '/payment'
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function redactPII(string $text): string
    {
        // Email redaction
        $text = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[EMAIL_REDACTED]', $text);
        
        // Phone number redaction
        $text = preg_replace('/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', '[PHONE_REDACTED]', $text);
        
        return $text;
    }
    
    private function stopUserRecording(int $userId): void
    {
        // Implementation to stop active recordings for user
        // Would integrate with real-time systems
    }
}
