<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Consent - CIS Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .consent-modal {
            background: rgba(0,0,0,0.8);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .consent-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .privacy-icon {
            font-size: 3rem;
            color: #007bff;
            margin-bottom: 1rem;
        }
        
        .monitoring-features {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .feature-icon {
            color: #28a745;
            margin-right: 0.5rem;
            width: 20px;
        }
        
        .privacy-controls {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .consent-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .btn-consent {
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: bold;
            min-width: 120px;
        }
    </style>
</head>
<body>
    <!-- Consent Modal -->
    <div id="consentModal" class="consent-modal">
        <div class="consent-card">
            <div class="text-center">
                <i class="fas fa-shield-alt privacy-icon"></i>
                <h3>Session Monitoring Consent</h3>
                <p class="text-muted">CIS requires your explicit consent for session monitoring</p>
            </div>
            
            <div class="monitoring-features">
                <h5><i class="fas fa-info-circle"></i> What We Monitor:</h5>
                
                <div class="feature-item">
                    <i class="fas fa-mouse-pointer feature-icon"></i>
                    <span>Mouse movements and clicks (for UX improvement)</span>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-keyboard feature-icon"></i>
                    <span>Non-sensitive keystrokes (passwords are NEVER recorded)</span>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-route feature-icon"></i>
                    <span>Page navigation and user journey analysis</span>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-clock feature-icon"></i>
                    <span>Time spent on pages and features</span>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-chart-bar feature-icon"></i>
                    <span>Performance metrics and error tracking</span>
                </div>
            </div>
            
            <div class="privacy-controls">
                <h6><i class="fas fa-lock"></i> Your Privacy Rights:</h6>
                <ul class="mb-0">
                    <li><strong>Opt-out anytime:</strong> Click the monitoring banner to stop recording</li>
                    <li><strong>Data retention:</strong> Recordings deleted after 30 days</li>
                    <li><strong>Sensitive data:</strong> Passwords, PII, and payment info are never recorded</li>
                    <li><strong>Access control:</strong> Only authorized security personnel can view recordings</li>
                    <li><strong>GDPR compliance:</strong> You can request data deletion at any time</li>
                </ul>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Purpose:</strong> This monitoring helps us improve security, detect unauthorized access, 
                and enhance user experience. Your data is never shared with third parties.
            </div>
            
            <div class="consent-buttons">
                <button onclick="grantConsent()" class="btn btn-success btn-consent">
                    <i class="fas fa-check"></i> I Consent
                </button>
                <button onclick="denyConsent()" class="btn btn-outline-secondary btn-consent">
                    <i class="fas fa-times"></i> Decline
                </button>
            </div>
            
            <div class="text-center mt-3">
                <small class="text-muted">
                    By clicking "I Consent", you agree to session monitoring under our 
                    <a href="/privacy-policy" target="_blank">Privacy Policy</a>
                </small>
            </div>
        </div>
    </div>
    
    <!-- Post-consent monitoring banner (hidden initially) -->
    <div id="monitoringBanner" class="alert alert-info alert-dismissible" style="display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 9999; margin: 0; border-radius: 0;">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <span>
                <i class="fas fa-record-vinyl text-danger"></i>
                <strong>Session Recording Active</strong> - Your activity is being monitored for security purposes
            </span>
            <div>
                <button onclick="stopMonitoring()" class="btn btn-sm btn-outline-dark">
                    <i class="fas fa-stop"></i> Stop Recording
                </button>
                <button type="button" class="close" onclick="minimizeBanner()">
                    <span>&times;</span>
                </button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        // Consent Management System
        const ConsentManager = {
            userId: <?= $current_user_id ?? 0 ?>,
            consentGranted: false,
            
            // Check existing consent
            checkExistingConsent: function() {
                fetch('/api/monitoring/check-consent?user_id=' + this.userId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_consent) {
                            this.consentGranted = true;
                            this.hideConsentModal();
                            this.startMonitoring();
                        } else {
                            this.showConsentModal();
                        }
                    })
                    .catch(err => {
                        console.error('Failed to check consent:', err);
                        // Show consent modal by default for safety
                        this.showConsentModal();
                    });
            },
            
            showConsentModal: function() {
                document.getElementById('consentModal').style.display = 'flex';
            },
            
            hideConsentModal: function() {
                document.getElementById('consentModal').style.display = 'none';
            },
            
            startMonitoring: function() {
                // Show monitoring banner
                document.getElementById('monitoringBanner').style.display = 'block';
                
                // Load and initialize monitoring script
                this.loadMonitoringScript();
            },
            
            loadMonitoringScript: function() {
                fetch('/api/monitoring/get-script?user_id=' + this.userId)
                    .then(response => response.text())
                    .then(script => {
                        // Create and execute monitoring script
                        const scriptElement = document.createElement('script');
                        scriptElement.textContent = script;
                        document.head.appendChild(scriptElement);
                    })
                    .catch(err => {
                        console.error('Failed to load monitoring script:', err);
                    });
            }
        };
        
        // Global functions for consent buttons
        function grantConsent() {
            // Record consent
            fetch('/api/monitoring/grant-consent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    user_id: ConsentManager.userId,
                    consent_type: 'session_recording',
                    duration_days: 30
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    ConsentManager.consentGranted = true;
                    ConsentManager.hideConsentModal();
                    ConsentManager.startMonitoring();
                    
                    // Show success message
                    showNotification('Consent granted. Session monitoring is now active.', 'success');
                } else {
                    showNotification('Failed to record consent. Please try again.', 'error');
                }
            })
            .catch(err => {
                console.error('Failed to grant consent:', err);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }
        
        function denyConsent() {
            // Record denial
            fetch('/api/monitoring/deny-consent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    user_id: ConsentManager.userId,
                    reason: 'user_declined'
                })
            })
            .then(() => {
                ConsentManager.hideConsentModal();
                showNotification('Monitoring declined. You can change this in your privacy settings.', 'info');
            });
        }
        
        function stopMonitoring() {
            if (window.CISMonitor) {
                window.CISMonitor.stopRecording();
            }
            
            // Revoke consent
            fetch('/api/monitoring/revoke-consent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    user_id: ConsentManager.userId
                })
            })
            .then(() => {
                document.getElementById('monitoringBanner').style.display = 'none';
                showNotification('Session monitoring stopped. Your privacy settings have been updated.', 'success');
            });
        }
        
        function minimizeBanner() {
            const banner = document.getElementById('monitoringBanner');
            banner.style.fontSize = '12px';
            banner.style.padding = '5px 15px';
            banner.querySelector('.container-fluid').innerHTML = `
                <span><i class="fas fa-record-vinyl text-danger"></i> Recording</span>
                <button onclick="stopMonitoring()" class="btn btn-sm btn-outline-dark" style="padding: 2px 8px; font-size: 10px;">
                    Stop
                </button>
            `;
        }
        
        function showNotification(message, type) {
            // Simple notification system
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible`;
            notification.style.cssText = 'position: fixed; top: 60px; right: 20px; z-index: 10001; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="close" onclick="this.parentElement.remove()">
                    <span>&times;</span>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Initialize consent check when page loads
        document.addEventListener('DOMContentLoaded', function() {
            ConsentManager.checkExistingConsent();
        });
    </script>
</body>
</html>
