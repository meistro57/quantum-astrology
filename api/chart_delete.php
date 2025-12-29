<?php
declare(strict_types=1);
session_start();

require __DIR__ . '/../config.php';

use QuantumAstrology\Core\DB;

header('Content-Type: application/json');

// Require login
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>['code'=>'UNAUTHENTICATED','message'=>'Login required']]);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Require JSON POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>['code'=>'METHOD_NOT_ALLOWED','message'=>'Use POST']]);
    exit;
}
$body = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>['code'=>'INVALID_JSON','message'=>'Invalid JSON body']]);
    exit;
}

// Validate inputs
$id   = isset($body['id']) ? (int)$body['id'] : 0;
$csrf = (string)($body['csrf'] ?? '');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>['code'=>'INVALID_REQUEST','message'=>'A positive chart id is required']]);
    exit;
}
if (!isset($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>['code'=>'CSRF','message'=>'Bad or missing CSRF token']]);
    exit;
}

try {
    $pdo = DB::conn();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure ownership, then delete
    $stmt = $pdo->prepare("DELETE FROM charts WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id'=>$id, ':uid'=>$uid]);
    $rows = $stmt->rowCount();

    if ($rows === 0) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'Chart not found or not owned by you']]);
        exit;
    }

    echo json_encode(['ok'=>true,'deleted'=>$id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>['code'=>'SERVER_ERROR','message'=>$e->getMessage()]]);
}
