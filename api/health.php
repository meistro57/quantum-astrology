<?php
# api/health.php
declare(strict_types=1);

use QuantumAstrology\Core\Env;

header('Content-Type: application/json');

require __DIR__ . '/../classes/Core/Env.php';
Env::load(__DIR__ . '/../.env');

$resp = [
  'ok' => true,
  'php' => PHP_VERSION,
  'time' => gmdate('c'),
  'db' => ['ok' => false],
  'swetest' => ['present' => false, 'version' => null, 'path' => null],
];

try {
    $dsn  = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', Env::get('DB_HOST', 'localhost'), Env::get('DB_NAME', 'quantum_astrology'));
    $user = Env::get('DB_USER', 'root');
    $pass = Env::get('DB_PASS', '');
    $pdo  = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->query('SELECT 1');
    $resp['db']['ok'] = true;
} catch (Throwable $e) {
    $resp['ok'] = false;
    $resp['db']['error'] = $e->getMessage();
}

$path = Env::get('SWEPH_PATH');
if ($path && is_file($path) && is_executable($path)) {
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
    $resp['ok'] = false;
    $resp['swetest']['error'] = 'swetest not found or not executable';
}

http_response_code($resp['ok'] ? 200 : 500);
echo json_encode($resp, JSON_PRETTY_PRINT);
