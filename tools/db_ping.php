<?php
declare(strict_types=1);
header('Content-Type: application/json');

function loadEnv(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k,$v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        $v = preg_replace_callback('/\$\{([^}]+)\}/', fn($m)=>$_ENV[$m[1]]??getenv($m[1])??'', $v);
        putenv("$k=$v"); $_ENV[$k] = $v;
    }
}
$root = dirname(__DIR__);
loadEnv($root.'/.env');

$host = ($_ENV['DB_HOST'] ?? '127.0.0.1');
if ($host === 'localhost') $host = '127.0.0.1';
$port = (int)($_ENV['DB_PORT'] ?? 3306);
$db   = $_ENV['DB_NAME'] ?? $_ENV['DB_DATABASE'] ?? 'cis';
$user = $_ENV['DB_USER'] ?? $_ENV['DB_USERNAME'] ?? 'cisuser';
$pass = $_ENV['DB_PASS'] ?? $_ENV['DB_PASSWORD'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$resp = ['ok'=>false,'dsn'=>"$host:$port/$db",'error'=>null];
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $row = $pdo->query("SELECT 
        @@version AS version,
        @@character_set_server AS srv_charset, @@collation_server AS srv_collation,
        @@character_set_database AS db_charset, @@collation_database AS db_collation"
    )->fetch();
    $resp['ok'] = true;
    $resp['server'] = $row;
} catch (Throwable $e) {
    $resp['error'] = ['type'=>get_class($e),'message'=>$e->getMessage()];
}
echo json_encode($resp, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
