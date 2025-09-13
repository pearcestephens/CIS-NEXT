<?php
declare(strict_types=1);

/**
 * Screenshot Upload Handler
 * 
 * CSRF-protected endpoint for storing captured screenshots
 * from the visual testing interface.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Protection
function validateCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return !empty($token) && hash_equals($sessionToken, $token);
}

// Input validation and sanitization
function sanitizeFilename(string $input): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $input);
}

function validateBase64Image(string $dataUrl): array
{
    if (!preg_match('/^data:image\/png;base64,(.+)$/', $dataUrl, $matches)) {
        return ['valid' => false, 'error' => 'Invalid image format'];
    }
    
    $base64Data = $matches[1];
    $imageData = base64_decode($base64Data);
    
    if ($imageData === false) {
        return ['valid' => false, 'error' => 'Invalid base64 encoding'];
    }
    
    // Check if it's a valid PNG
    if (substr($imageData, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return ['valid' => false, 'error' => 'Not a valid PNG image'];
    }
    
    return ['valid' => true, 'data' => $imageData];
}

header('Content-Type: application/json');

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }

    // Validate CSRF token
    if (!validateCsrf()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF token validation failed']);
        exit;
    }

    // Get required parameters
    $stage = $_POST['stage'] ?? '';
    $route = $_POST['route'] ?? '';
    $pngBase64 = $_POST['png_base64'] ?? '';
    $html = $_POST['html'] ?? '';

    if (empty($stage) || empty($route) || empty($pngBase64)) {
        throw new Exception('Missing required parameters');
    }

    // Validate and decode image
    $imageValidation = validateBase64Image($pngBase64);
    if (!$imageValidation['valid']) {
        throw new Exception($imageValidation['error']);
    }

    // Create directory structure
    $baseDir = __DIR__ . '/../var/screenshots';
    $dateDir = $baseDir . '/' . date('Ymd');
    
    @mkdir($baseDir, 0755, true);
    @mkdir($dateDir, 0755, true);

    // Generate filename
    $sanitizedStage = sanitizeFilename($stage);
    $sanitizedRoute = sanitizeFilename(str_replace('/', '_', ltrim($route, '/')));
    $timestamp = date('His');
    
    $filename = "{$sanitizedStage}_{$sanitizedRoute}_{$timestamp}.png";
    $filepath = $dateDir . '/' . $filename;

    // Save PNG file
    if (file_put_contents($filepath, $imageValidation['data']) === false) {
        throw new Exception('Failed to save image file');
    }

    // Optionally save HTML snapshot
    if (!empty($html)) {
        $htmlFilename = "{$sanitizedStage}_{$sanitizedRoute}_{$timestamp}.html";
        $htmlFilepath = $dateDir . '/' . $htmlFilename;
        file_put_contents($htmlFilepath, $html);
    }

    // Success response
    echo json_encode([
        'ok' => true,
        'filename' => $filename,
        'path' => 'var/screenshots/' . date('Ymd') . '/' . $filename,
        'size' => filesize($filepath),
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
