<?php

echo "# CIS APPLICATION SERVICES SCAN REPORT\n";
echo "Generated: " . date('Y-m-d H:i:s T') . "\n\n";

echo "## CURRENTLY ACTIVE INTEGRATIONS\n\n";

// Check actual Integrations directory
$integrationDir = __DIR__ . '/app/Integrations';
if (is_dir($integrationDir)) {
    foreach (glob($integrationDir . '/*/Client.php') as $file) {
        $serviceName = basename(dirname($file));
        $content = file_get_contents($file);
        $isActive = !empty($content) && strpos($content, 'class Client') !== false;
        $status = $isActive ? '✅ ACTIVE' : '❌ INACTIVE';
        echo "- **{$serviceName}** ({$status})\n";
        echo "  - File: " . str_replace(__DIR__ . '/', '', $file) . "\n\n";
    }
} else {
    echo "No integrations directory found.\n\n";
}

echo "## CONTROLLERS\n\n";

$controllerDirs = [
    'app/Http/Controllers' => 'Main Controllers',
    'app/Http/Controllers/Admin' => 'Admin Controllers', 
    'app/Http/Controllers/Api' => 'API Controllers'
];

foreach ($controllerDirs as $dir => $category) {
    $fullDir = __DIR__ . '/' . $dir;
    if (is_dir($fullDir)) {
        echo "### {$category}\n\n";
        foreach (glob($fullDir . '/*.php') as $file) {
            $className = basename($file, '.php');
            echo "- **{$className}**\n";
        }
        echo "\n";
    }
}

echo "## MIDDLEWARES\n\n";

$middlewareDir = __DIR__ . '/app/Http/Middlewares';
if (is_dir($middlewareDir)) {
    foreach (glob($middlewareDir . '/*.php') as $file) {
        $className = basename($file, '.php');
        echo "- **{$className}**\n";
    }
}

echo "\n## DOMAIN SERVICES\n\n";

$domainDir = __DIR__ . '/app/Domain/Services';
if (is_dir($domainDir)) {
    foreach (glob($domainDir . '/*.php') as $file) {
        $className = basename($file, '.php');
        echo "- **{$className}**\n";
    }
}

echo "\n## SHARED SERVICES\n\n";

$sharedDirs = [
    'AI', 'Backup', 'Config', 'Logging', 'Mail', 'Queue', 'Sec'
];

foreach ($sharedDirs as $category) {
    $dir = __DIR__ . '/app/Shared/' . $category;
    if (is_dir($dir)) {
        echo "### {$category}\n";
        foreach (glob($dir . '/*.php') as $file) {
            $className = basename($file, '.php');
            echo "- **{$className}**\n";
        }
        echo "\n";
    }
}

echo "## MONITORING & SECURITY SERVICES\n\n";

$specialServices = [
    'app/Monitoring/SessionRecorder.php' => 'Session Recording and Replay',
    'app/Security/IDSEngine.php' => 'Intrusion Detection System'
];

foreach ($specialServices as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $name = basename($file, '.php');
        echo "- **{$name}**: {$description}\n";
    }
}

echo "\n## KEY TOOLS & UTILITIES\n\n";

$toolsDir = __DIR__ . '/tools';
$importantTools = [
    'integration_client.php' => 'CIS Integration Client SDK',
    'queue_worker.php' => 'Background Job Worker',
    'backup_system.php' => 'Backup Management System',
    'cache_manager.php' => 'Cache Management',
    'database_audit.php' => 'Database Auditing'
];

foreach ($importantTools as $file => $description) {
    if (file_exists($toolsDir . '/' . $file)) {
        echo "- **{$file}**: {$description}\n";
    }
}
