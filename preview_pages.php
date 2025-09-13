<?php
/**
 * Quick HTML Preview - Shows actual page content
 */

declare(strict_types=1);

$routes = [
    '/' => 'Home Page',
    '/_health' => 'Health Check (JSON)',
    '/admin/' => 'Admin Dashboard',
    '/admin/tools' => 'Admin Tools',
    '/admin/settings' => 'Admin Settings'
];

echo "<html><head><title>CIS Phase 0 - Page Preview</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.page-preview { background: white; margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
.page-header { background: #007bff; color: white; padding: 10px; margin: -20px -20px 20px -20px; border-radius: 5px 5px 0 0; }
.page-content { border: 1px solid #eee; padding: 15px; background: #fafafa; max-height: 400px; overflow-y: auto; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
.status { padding: 5px 10px; border-radius: 3px; color: white; font-weight: bold; }
.success { background: #28a745; }
.error { background: #dc3545; }
</style></head><body>";

echo "<h1>🚀 CIS Phase 0 - Live Page Preview</h1>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s T') . "</p>";

foreach ($routes as $path => $description) {
    echo "<div class='page-preview'>";
    echo "<div class='page-header'>";
    echo "<h2>{$description}</h2>";
    echo "<code>{$path}</code>";
    echo "</div>";
    
    // Test the route
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => $path,
        'HTTP_HOST' => 'cis.dev.ecigdis.co.nz',
        'REMOTE_ADDR' => '127.0.0.1'
    ];
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    ob_start();
    $error = null;
    
    try {
        include '/var/www/cis.dev.ecigdis.co.nz/public_html/index.php';
        $output = ob_get_contents();
        
        if (!empty($output)) {
            // Check if it's JSON
            $decoded = json_decode($output);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "<div class='status success'>✅ JSON Response Working</div>";
                echo "<div class='page-content'>";
                echo "<pre>" . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . "</pre>";
                echo "</div>";
            } else {
                echo "<div class='status success'>✅ HTML Response Working</div>";
                echo "<div class='page-content'>";
                echo htmlspecialchars($output);
                echo "</div>";
            }
        } else {
            echo "<div class='status error'>❌ Empty Response</div>";
        }
        
    } catch (\Throwable $e) {
        echo "<div class='status error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='page-content'>";
        echo "<strong>Exception:</strong> " . htmlspecialchars(get_class($e)) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "<br>";
        echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    
    ob_end_clean();
    echo "</div>";
}

echo "<div class='page-preview'>";
echo "<div class='page-header'><h2>🔗 All Admin Routes</h2></div>";
echo "<div class='page-content'>";
echo "<p>Additional admin routes available:</p>";
echo "<ul>";
echo "<li><a href='/admin/dashboard'>Admin Dashboard</a></li>";
echo "<li><a href='/admin/users'>User Management</a></li>";
echo "<li><a href='/admin/integrations'>Integrations</a></li>";
echo "<li><a href='/admin/analytics'>Analytics</a></li>";
echo "<li><a href='/admin/database/prefix-manager'>Database Tools</a></li>";
echo "</ul>";
echo "</div>";
echo "</div>";

echo "</body></html>";
