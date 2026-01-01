<?php
declare(strict_types=1);

require_once __DIR__ . '/../../classes/autoload.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

// Require authentication
Auth::requireLogin();

try {
    $chartId = (int) ($_GET['id'] ?? $_GET['chart_id'] ?? 0);
    $format = $_GET['format'] ?? 'json'; // json, pdf, png, csv

    if ($chartId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid chart ID']);
        exit;
    }

    $chart = Chart::find($chartId);
    if (!$chart) {
        http_response_code(404);
        echo json_encode(['error' => 'Chart not found']);
        exit;
    }

    // Verify user owns the chart
    $user = Auth::user();
    if ($chart->getUserId() !== $user->getId()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    switch ($format) {
        case 'json':
            exportJSON($chart);
            break;

        case 'pdf':
            exportPDF($chart);
            break;

        case 'png':
            exportPNG($chart);
            break;

        case 'csv':
            exportCSV($chart);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported export format']);
            exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Export failed: ' . $e->getMessage()
    ]);
}

function exportJSON(Chart $chart): void
{
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="chart_' . $chart->getId() . '.json"');

    $data = $chart->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
}

function exportPDF(Chart $chart): void
{
    require_once __DIR__ . '/../../classes/autoload.php';
    $generator = new \QuantumAstrology\Reports\ReportGenerator('natal');
    $pdfContent = $generator->generateNatalReport($chart->getId());

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="chart_' . $chart->getId() . '.pdf"');
    header('Content-Length: ' . strlen($pdfContent));

    echo $pdfContent;
}

function exportPNG(Chart $chart): void
{
    // Get the SVG chart
    $svgUrl = "http://localhost:8080/api/chart_svg.php?id=" . $chart->getId() . "&size=1200";
    $svgContent = @file_get_contents($svgUrl);

    if (!$svgContent) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to generate chart image']);
        return;
    }

    // For now, just return the SVG (PNG conversion would require ImageMagick or similar)
    header('Content-Type: image/svg+xml');
    header('Content-Disposition: attachment; filename="chart_' . $chart->getId() . '.svg"');
    echo $svgContent;
}

function exportCSV(Chart $chart): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="chart_' . $chart->getId() . '.csv"');

    $data = $chart->toArray();
    $output = fopen('php://output', 'w');

    // Chart information
    fputcsv($output, ['Chart Information']);
    fputcsv($output, ['Name', $chart->getName()]);
    fputcsv($output, ['Birth Date', $chart->getBirthDatetime() ? $chart->getBirthDatetime()->format('Y-m-d H:i:s') : '']);
    fputcsv($output, ['Location', $chart->getBirthLocation()]);
    fputcsv($output, ['Latitude', $chart->getBirthLatitude()]);
    fputcsv($output, ['Longitude', $chart->getBirthLongitude()]);
    fputcsv($output, []);

    // Planetary positions
    fputcsv($output, ['Planetary Positions']);
    fputcsv($output, ['Planet', 'Sign', 'Degree', 'House', 'Retrograde']);

    $planets = json_decode($data['planetary_positions'] ?? '{}', true);
    foreach ($planets as $name => $planet) {
        fputcsv($output, [
            ucfirst(str_replace('_', ' ', $name)),
            $planet['sign'] ?? '',
            $planet['degree'] ?? '',
            $planet['house'] ?? '',
            ($planet['retrograde'] ?? false) ? 'Yes' : 'No'
        ]);
    }

    fputcsv($output, []);

    // House cusps
    fputcsv($output, ['House Cusps']);
    fputcsv($output, ['House', 'Sign', 'Degree']);

    $houses = json_decode($data['house_cusps'] ?? '[]', true);
    foreach ($houses as $index => $cusp) {
        fputcsv($output, [
            $index + 1,
            $cusp['sign'] ?? '',
            $cusp['degree'] ?? ''
        ]);
    }

    fputcsv($output, []);

    // Aspects
    fputcsv($output, ['Aspects']);
    fputcsv($output, ['Planet 1', 'Aspect', 'Planet 2', 'Orb']);

    $aspects = json_decode($data['aspects'] ?? '[]', true);
    foreach ($aspects as $aspect) {
        fputcsv($output, [
            ucfirst(str_replace('_', ' ', $aspect['planet1'] ?? '')),
            ucfirst($aspect['aspect'] ?? $aspect['type'] ?? ''),
            ucfirst(str_replace('_', ' ', $aspect['planet2'] ?? '')),
            $aspect['orb'] ?? ''
        ]);
    }

    fclose($output);
}
