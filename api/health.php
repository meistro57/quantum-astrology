<?php
# api/health.php
declare(strict_types=1);

use QuantumAstrology\Core\Env;

header('Content-Type: application/json');

require_once __DIR__ . '/../classes/autoload.php';
require_once __DIR__ . '/../config.php';
Env::load(__DIR__ . '/../.env');

$resp = [
  'ok' => true,
  'php' => PHP_VERSION,
  'time' => gmdate('c'),
  'db' => ['status' => 'error', 'ok' => false, 'type' => 'sqlite'],
  'swetest' => ['status' => 'warn', 'present' => false, 'version' => null, 'path' => null],
  'warnings' => [],
];

// Check database based on configured driver.
$dbDriver = strtolower(trim((string) Env::get('DB_DRIVER', 'sqlite')));
$resp['db']['type'] = $dbDriver;

try {
    if ($dbDriver === 'mysql') {
        $host = (string) Env::get('DB_HOST', '127.0.0.1');
        $port = (int) Env::get('DB_PORT', 3306);
        $name = (string) Env::get('DB_NAME', 'quantum_astrology');
        $user = (string) Env::get('DB_USER', 'root');
        $pass = (string) Env::get('DB_PASS', '');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $resp['db']['host'] = $host;
        $resp['db']['port'] = $port;
        $resp['db']['name'] = $name;
    } else {
        $dbPath = (string) Env::get('DB_SQLITE_PATH', __DIR__ . '/../storage/database.sqlite');
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $resp['db']['path'] = $dbPath;
    }

    $pdo->query('SELECT 1');
    $resp['db']['status'] = 'ok';
    $resp['db']['ok'] = true;
} catch (Throwable $e) {
    $resp['ok'] = false;
    $resp['db']['status'] = 'error';
    $resp['db']['ok'] = false;
    $resp['db']['error'] = $e->getMessage();
}

$path = trim((string) Env::get('SWEPH_PATH', ''));
if ($path && is_file($path) && is_executable($path)) {
    $resp['swetest']['status'] = 'ok';
    $resp['swetest']['present'] = true;
    $resp['swetest']['path'] = $path;
    // exec may be disabled; handle safely
    if (function_exists('exec')) {
        @exec(escapeshellarg($path) . ' -v 2>&1', $out, $code);
        if ($code === 0 && !empty($out)) {
            $resp['swetest']['version'] = trim($out[0]);
        }
    }
} else {
    $resp['swetest']['error'] = 'swetest not found or not executable';
    $resp['warnings'][] = 'Swiss Ephemeris CLI unavailable; analytical fallback will be used.';
}

http_response_code($resp['ok'] ? 200 : 500);
echo json_encode($resp, JSON_PRETTY_PRINT);
