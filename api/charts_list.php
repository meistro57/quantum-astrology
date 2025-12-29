<?php
# api/charts_list.php
declare(strict_types=1);
session_start();

require __DIR__ . '/../config.php';

use QuantumAstrology\Core\DB;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false, 'error'=>'Login required']);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$pdo = DB::conn();
$stmt = $pdo->prepare("SELECT id, name, created_at FROM charts WHERE user_id = :uid ORDER BY id DESC LIMIT 100");
$stmt->execute([':uid' => $uid]);
$rows = $stmt->fetchAll();

echo json_encode(['ok'=>true, 'charts'=>$rows], JSON_PRETTY_PRINT);
