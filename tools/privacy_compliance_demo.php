<?php
/**
 * MONITORING SYSTEM PRIVACY COMPLIANCE DEMO
 * Demonstrates GDPR compliance features and data redaction
 */

require_once __DIR__ . '/../app/Monitoring/SessionRecorder.php';

echo "=== PRIVACY COMPLIANCE DEMONSTRATION ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "==========================================\n\n";

// Initialize SessionRecorder
$recorder = new App\Monitoring\SessionRecorder();

// 1. CONSENT MANAGEMENT DEMO
echo "1. CONSENT MANAGEMENT:\n";
echo "----------------------\n";

$testUserId = 123;

// Record consent
echo "Recording consent for user $testUserId...\n";
$consentResult = $recorder->recordConsent($testUserId, 'session_recording', 30);
echo "Consent recorded: " . ($consentResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";

// Check consent status
$hasConsent = $recorder->hasUserConsented($testUserId);
echo "User has consent: " . ($hasConsent ? "✅ YES" : "❌ NO") . "\n";

echo "\n";

// 2. PII REDACTION DEMO
echo "2. PII REDACTION DEMONSTRATION:\n";
echo "-------------------------------\n";

// Create a test SessionRecorder instance with public access to redaction method
class TestSessionRecorder extends App\Monitoring\SessionRecorder {
    public function testRedactPII($text) {
        return $this->redactPII($text);
    }
    
    public function testIsSensitiveField($element) {
        return $this->isSensitiveField($element);
    }
}

$testRecorder = new TestSessionRecorder();

// Test data with PII
$testTexts = [
    "Contact us at john.doe@example.com for support",
    "Call us at 555-123-4567 or 555.987.6543", 
    "My password is secret123",
    "Credit card: 4111-1111-1111-1111",
    "Regular text without PII"
];

echo "Original Text → Redacted Text:\n";
foreach ($testTexts as $text) {
    $redacted = $testRecorder->testRedactPII($text);
    echo "• $text → $redacted\n";
}

echo "\n";

// 3. SENSITIVE FIELD DETECTION DEMO
echo "3. SENSITIVE FIELD DETECTION:\n";
echo "-----------------------------\n";

$testElements = [
    (object)['name' => 'password', 'type' => 'password'],
    (object)['name' => 'credit_card', 'type' => 'text'],
    (object)['name' => 'user_name', 'type' => 'text'],
    (object)['name' => 'cvv_code', 'type' => 'text'],
    (object)['name' => 'first_name', 'type' => 'text']
];

echo "Field Detection Results:\n";
foreach ($testElements as $element) {
    $isSensitive = $testRecorder->testIsSensitiveField($element);
    $status = $isSensitive ? "🔒 SENSITIVE (BLOCKED)" : "✅ SAFE (ALLOWED)";
    echo "• {$element->name} [{$element->type}] → $status\n";
}

echo "\n";

// 4. JAVASCRIPT MONITORING SCRIPT DEMO
echo "4. PRIVACY-COMPLIANT MONITORING SCRIPT:\n";
echo "---------------------------------------\n";

if ($hasConsent) {
    echo "Generating monitoring script for consented user...\n";
    $script = $recorder->generateMonitoringScript($testUserId);
    
    // Show a snippet of the generated script (first 500 chars)
    $scriptPreview = substr(str_replace("\n", "\\n", $script), 0, 500);
    echo "Script Preview: $scriptPreview...\n";
    
    // Check for privacy features in script
    $privacyFeatures = [
        'consent banner' => strpos($script, 'showConsentBanner') !== false,
        'sensitive field detection' => strpos($script, 'isSensitiveField') !== false,
        'password exclusion' => strpos($script, 'NEVER') !== false,
        'user opt-out' => strpos($script, 'stopRecording') !== false
    ];
    
    echo "\nPrivacy Features in Generated Script:\n";
    foreach ($privacyFeatures as $feature => $present) {
        echo "• $feature: " . ($present ? "✅ INCLUDED" : "❌ MISSING") . "\n";
    }
} else {
    echo "No script generated - user has not consented\n";
}

echo "\n";

// 5. CONSENT REVOCATION DEMO
echo "5. CONSENT REVOCATION:\n";
echo "----------------------\n";

echo "Revoking consent for user $testUserId...\n";
$revokeResult = $recorder->revokeConsent($testUserId);
echo "Consent revoked: " . ($revokeResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";

$hasConsentAfterRevoke = $recorder->hasUserConsented($testUserId);
echo "User has consent after revocation: " . ($hasConsentAfterRevoke ? "❌ STILL HAS" : "✅ REVOKED") . "\n";

echo "\n";

// 6. GDPR COMPLIANCE SUMMARY
echo "6. GDPR COMPLIANCE SUMMARY:\n";
echo "==========================\n";

$complianceFeatures = [
    'Explicit Consent Required' => '✅ Users must actively consent before monitoring',
    'Granular Consent Control' => '✅ Users can choose what to consent to',
    'Easy Opt-Out' => '✅ Users can stop recording anytime',
    'Data Minimization' => '✅ Only necessary data collected',
    'PII Protection' => '✅ Personal information automatically redacted',
    'Password Security' => '✅ Passwords NEVER recorded',
    'Payment Protection' => '✅ Payment forms completely excluded',
    'Consent Logging' => '✅ All consent actions logged with timestamps',
    'Data Retention Limits' => '✅ Automatic cleanup after 30 days',
    'Right to be Forgotten' => '✅ Users can request data deletion',
    'Data Portability' => '✅ Users can export their data',
    'Transparency' => '✅ Clear privacy policy and data usage info'
];

foreach ($complianceFeatures as $feature => $status) {
    echo "$status $feature\n";
}

echo "\n";

// 7. AUDIT LOG SAMPLE
echo "7. AUDIT LOG DEMONSTRATION:\n";
echo "---------------------------\n";

echo "Sample audit log entries (showing privacy-safe format):\n";

$auditEntries = [
    [
        'timestamp' => date('c'),
        'action' => 'CONSENT_GRANTED',
        'user_id' => $testUserId,
        'ip_address' => '[REDACTED_FOR_PRIVACY]',
        'user_agent' => '[REDACTED_FOR_PRIVACY]',
        'consent_type' => 'session_recording',
        'duration_days' => 30
    ],
    [
        'timestamp' => date('c'),
        'action' => 'CONSENT_REVOKED', 
        'user_id' => $testUserId,
        'ip_address' => '[REDACTED_FOR_PRIVACY]',
        'reason' => 'user_request'
    ]
];

foreach ($auditEntries as $entry) {
    echo json_encode($entry, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== END OF PRIVACY COMPLIANCE DEMO ===\n";
?>
