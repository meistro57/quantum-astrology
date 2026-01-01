<?php
# api/resolve_map_link.php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Support\InputValidator;

header('Content-Type: application/json');

/**
 * Emit a JSON response with a consistent shape.
 */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Attempt to follow redirects for shortened mapping URLs (e.g. maps.app.goo.gl).
 */
function resolveFinalUrl(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'QuantumAstrology/LocationResolver (+https://quantum-astrology.example)',
        CURLOPT_TIMEOUT        => 5,
    ]);

    $result = curl_exec($ch);
    $final  = $result === false ? '' : (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    return $final !== '' ? $final : $url;
}

/**
 * Extract latitude/longitude pairs from common Google Maps style URLs.
 * Supports the resolved long-form URLs and the original shortlink as a fallback.
 */
function extractCoordinates(string $url): ?array
{
    $decoded = urldecode($url);
    $patterns = [
        '/@\s*(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
        '/[?&](?:q|query)=\s*(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/i',
        '/!3d\s*(-?\d+(?:\.\d+)?)!4d\s*(-?\d+(?:\.\d+)?)/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $decoded, $matches)) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
            ];
        }
    }

    return null;
}

$rawBody = file_get_contents('php://input');
$payload = null;
if ($rawBody !== false && trim($rawBody) !== '') {
    $payload = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(400, [
            'ok'    => false,
            'error' => [
                'code'    => 'INVALID_JSON',
                'message' => 'Request body must be valid JSON.',
            ],
        ]);
    }
}

if (!is_array($payload)) {
    $payload = $_POST ?: [];
}

$url = trim((string) ($payload['url'] ?? ''));
if ($url === '') {
    respond(400, [
        'ok'    => false,
        'error' => [
            'code'    => 'URL_REQUIRED',
            'message' => 'Please provide a Google Maps share link to resolve coordinates.',
        ],
    ]);
}

$finalUrl = resolveFinalUrl($url);
$coordinates = extractCoordinates($finalUrl);
if ($coordinates === null && $finalUrl !== $url) {
    $coordinates = extractCoordinates($url);
}

if ($coordinates === null) {
    respond(422, [
        'ok'    => false,
        'error' => [
            'code'    => 'UNABLE_TO_RESOLVE',
            'message' => 'Could not extract latitude/longitude from the provided link. Please double-check the URL.',
        ],
    ]);
}

try {
    $latitude  = InputValidator::parseLatitude($coordinates['lat']);
    $longitude = InputValidator::parseLongitude($coordinates['lng']);
} catch (InvalidArgumentException $e) {
    respond(422, [
        'ok'    => false,
        'error' => [
            'code'    => 'INVALID_COORDINATES',
            'message' => $e->getMessage(),
        ],
    ]);
}

respond(200, [
    'ok'           => true,
    'latitude'     => $latitude,
    'longitude'    => $longitude,
    'resolved_url' => $finalUrl,
]);
