<?php
# api/chart_create.php
declare(strict_types=1);

require __DIR__ . '/../classes/autoload.php';

use QuantumAstrology\Charts\ChartService;

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input') ?: 'null', true);
    if (!is_array($payload)) $payload = $_POST ?: [];

    $chart = ChartService::create(
        trim($payload['name'] ?? ''),
        trim($payload['birth_date'] ?? ''),
        trim($payload['birth_time'] ?? ''),
        trim($payload['birth_timezone'] ?? 'UTC'),
        (float)($payload['birth_latitude'] ?? 0),
        (float)($payload['birth_longitude'] ?? 0),
        strtoupper(trim($payload['house_system'] ?? 'P'))
    );

    http_response_code(201);
    echo json_encode(['ok' => true, 'chart' => $chart], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
