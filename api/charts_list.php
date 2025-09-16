<?php
# api/charts_list.php
declare(strict_types=1);

require __DIR__ . '/../classes/autoload.php';

use QuantumAstrology\Core\DB;

header('Content-Type: application/json');

$pdo = DB::conn();
$rows = $pdo->query("SELECT id, name, created_at FROM charts ORDER BY id DESC LIMIT 50")->fetchAll();
echo json_encode(['ok'=>true, 'charts'=>$rows], JSON_PRETTY_PRINT);
