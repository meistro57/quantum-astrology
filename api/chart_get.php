<?php
# api/chart_get.php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Charts\ChartService;

header('Content-Type: application/json');

function respond_error(int $status, string $code, string $message, array $fields = []): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'fields' => $fields,
        ],
    ], JSON_PRETTY_PRINT);
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    respond_error(400, 'INVALID_REQUEST', 'A positive chart id is required.', [
        'id' => 'Provide a numeric chart id greater than zero.',
    ]);
}

$chart = ChartService::get($id);
if (!$chart) {
    respond_error(404, 'NOT_FOUND', 'Chart not found.');
}

echo json_encode(['ok' => true, 'chart' => $chart], JSON_PRETTY_PRINT);
