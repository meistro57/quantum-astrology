<?php
# api/chart_get.php
declare(strict_types=1);

require __DIR__ . '/../classes/autoload.php';

use QuantumAstrology\Charts\ChartService;

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id required'], JSON_PRETTY_PRINT);
    exit;
}

$chart = ChartService::get($id);
if (!$chart) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not found'], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode(['ok' => true, 'chart' => $chart], JSON_PRETTY_PRINT);
