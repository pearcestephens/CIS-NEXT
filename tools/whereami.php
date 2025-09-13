<?php
/**
 * Domain Detection Tool
 * 
 * Detects the correct APP_URL for CIS deployment by checking
 * various common domain patterns and server configurations.
 */
declare(strict_types=1);

header('Content-Type: application/json');

$possibleUrls = [
    'https://staff.vapeshed.co.nz',
    'https://cis.dev.ecigdis.co.nz', 
    'https://cis.ecigdis.co.nz',
    'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
];

$detectedUrl = null;
$serverInfo = [];

// Check environment variables first
$envUrl = $_ENV['APP_URL'] ?? getenv('APP_URL');
if ($envUrl) {
    $detectedUrl = rtrim($envUrl, '/');
}

// If no env URL, test possible URLs
if (!$detectedUrl) {
    foreach ($possibleUrls as $url) {
        $testUrl = $url . '/_health';
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($testUrl, false, $context);
        if ($response !== false && strpos($response, '"ok":true') !== false) {
            $detectedUrl = $url;
            break;
        }
    }
}

// Fallback to current server
if (!$detectedUrl) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $detectedUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// Server information
$serverInfo = [
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'HTTPS' => $_SERVER['HTTPS'] ?? 'off',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'PHP_VERSION' => PHP_VERSION,
    'TIMEZONE' => date_default_timezone_get(),
    'index_exists' => is_file(dirname(__DIR__) . '/index.php'),
    'routes_exists' => is_file(dirname(__DIR__) . '/routes/web.php')
];

echo json_encode([
    'detected_url' => $detectedUrl,
    'tested_urls' => $possibleUrls,
    'server_info' => $serverInfo,
    'timestamp' => date('c')
], JSON_PRETTY_PRINT);
