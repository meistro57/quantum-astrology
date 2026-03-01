<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Charts\ChartWheel;
use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\Session;

Session::start();

/**
 * Return an SVG-formatted error payload so <img> previews fail gracefully.
 */
function respond_svg_error(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: image/svg+xml');
    $safe = htmlspecialchars($message, ENT_QUOTES);
    echo <<<SVG
<svg width="800" height="200" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="#fee2e2"/>
    <text x="10" y="32" font-family="monospace" font-size="14" fill="#991b1b" font-weight="bold">Chart Preview Error</text>
    <text x="10" y="64" font-family="monospace" font-size="12" fill="#b91c1c">{$safe}</text>
</svg>
SVG;
    exit;
}

try {
    $chartId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $size = isset($_GET['size']) ? (int) $_GET['size'] : 900;
    $size = max(320, min(1800, $size));

    if ($chartId <= 0) {
        respond_svg_error('A positive chart id is required.', 400);
    }

    $chart = Chart::findById($chartId);
    if (!$chart) {
        respond_svg_error('Chart not found.', 404);
    }

    $currentUser = Auth::user();
    if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
        respond_svg_error('Access denied.', 403);
    }

    // Normalize stored chart rows (list format with lon/lat) into wheel format.
    $planets = [];
    foreach ($chart->getPlanetaryPositions() as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = is_string($key) && !is_int($key)
            ? strtolower($key)
            : strtolower((string) ($row['planet'] ?? $row['name'] ?? ''));
        $longitude = $row['longitude'] ?? $row['lon'] ?? null;
        if ($name === '' || !is_numeric($longitude)) {
            continue;
        }
        $planets[$name] = [
            'longitude' => (float) $longitude,
            'latitude' => isset($row['latitude']) && is_numeric($row['latitude'])
                ? (float) $row['latitude']
                : (isset($row['lat']) && is_numeric($row['lat']) ? (float) $row['lat'] : 0.0),
        ];
    }

    if (empty($planets)) {
        respond_svg_error('Chart has no planetary data.', 422);
    }

    $housesRaw = $chart->getHousePositions();
    $houses = [];

    if (isset($housesRaw['cusps']) && is_array($housesRaw['cusps'])) {
        foreach ($housesRaw['cusps'] as $house => $cusp) {
            if (is_numeric($house) && is_numeric($cusp)) {
                $houses[(int) $house] = ['cusp' => (float) $cusp];
            }
        }
    } else {
        foreach ($housesRaw as $house => $row) {
            if (!is_numeric($house)) {
                continue;
            }
            if (is_array($row) && isset($row['cusp']) && is_numeric($row['cusp'])) {
                $houses[(int) $house] = ['cusp' => (float) $row['cusp']];
            } elseif (is_numeric($row)) {
                $houses[(int) $house] = ['cusp' => (float) $row];
            }
        }
    }

    $aspects = [];
    foreach ($chart->getAspects() as $aspect) {
        if (!is_array($aspect)) {
            continue;
        }
        $planet1 = strtolower((string) ($aspect['planet1'] ?? ''));
        $planet2 = strtolower((string) ($aspect['planet2'] ?? ''));
        $aspectType = strtolower((string) ($aspect['aspect'] ?? $aspect['type'] ?? ''));
        if ($planet1 === '' || $planet2 === '' || $aspectType === '') {
            continue;
        }
        $aspects[] = [
            'planet1' => $planet1,
            'planet2' => $planet2,
            'aspect' => $aspectType,
        ];
    }

    $wheel = new ChartWheel($size);
    $cacheKey = 'chart_svg_' . $chartId . '_' . $size . '_' . md5(json_encode([$planets, $houses, $aspects], JSON_UNESCAPED_SLASHES));
    $svg = $wheel->generateWheelWithCache($cacheKey, $planets, $houses, $aspects);

    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $svg;
} catch (Throwable $e) {
    Logger::error('chart_svg endpoint failed', ['error' => $e->getMessage()]);
    respond_svg_error('Internal error while generating chart SVG.', 500);
}
