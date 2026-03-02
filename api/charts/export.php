<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Core\Auth;

Auth::requireLogin();

function export_error(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

$chartId = (int) ($_GET['id'] ?? 0);
$format = strtolower((string) ($_GET['format'] ?? 'json'));

if ($chartId <= 0) {
    export_error(400, 'Invalid chart id.');
}

$chart = Chart::findById($chartId);
if (!$chart) {
    export_error(404, 'Chart not found.');
}

$user = Auth::user();
if (!$user || ((int)$chart->getUserId() !== (int)$user->getId() && !$chart->isPublic())) {
    export_error(403, 'Access denied.');
}

$payload = [
    'id' => $chart->getId(),
    'user_id' => $chart->getUserId(),
    'name' => $chart->getName(),
    'birth_datetime' => $chart->getBirthDatetime()?->format('Y-m-d H:i:s'),
    'birth_latitude' => $chart->getBirthLatitude(),
    'birth_longitude' => $chart->getBirthLongitude(),
    'house_system' => $chart->getHouseSystem(),
    'planetary_positions' => $chart->getPlanetaryPositions(),
    'house_positions' => $chart->getHousePositions(),
    'aspects' => $chart->getAspects(),
    'is_public' => $chart->isPublic(),
];

if ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="chart_' . $chartId . '.json"');
    echo json_encode(['ok' => true, 'chart' => $payload], JSON_PRETTY_PRINT);
    exit;
}

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="chart_' . $chartId . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['field', 'value']);
    fputcsv($out, ['id', (string) $payload['id']]);
    fputcsv($out, ['user_id', (string) $payload['user_id']]);
    fputcsv($out, ['name', (string) $payload['name']]);
    fputcsv($out, ['birth_datetime', (string) ($payload['birth_datetime'] ?? '')]);
    fputcsv($out, ['birth_latitude', (string) $payload['birth_latitude']]);
    fputcsv($out, ['birth_longitude', (string) $payload['birth_longitude']]);
    fputcsv($out, ['house_system', (string) $payload['house_system']]);
    fclose($out);
    exit;
}

if ($format === 'svg') {
    $query = http_build_query(['id' => $chartId, 'size' => 1200]);
    header('Location: /api/chart_svg.php?' . $query, true, 302);
    exit;
}

export_error(400, 'Unsupported format. Use json, csv, or svg.');
