<?php
# api/resolve_location.php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Support\InputValidator;

header('Content-Type: application/json');

/**
 * Emit a consistent JSON response.
 */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Query Nominatim for city/state geocoding.
 */
function geocodeCityState(string $city, string $state): ?array
{
    $mockPayload = env('QA_MOCK_RESOLVE_LOCATION_JSON', '');
    if (is_string($mockPayload) && trim($mockPayload) !== '') {
        $decoded = json_decode($mockPayload, true);
        if (!is_array($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            return null;
        }

        return $decoded[0];
    }

    $query = trim($city . ', ' . $state . ', USA');
    $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&addressdetails=1&q=' . rawurlencode($query);
    $body = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'QuantumAstrology/1.0 (location resolver)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            return null;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/json\r\nUser-Agent: QuantumAstrology/1.0 (location resolver)\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }
    }

    $payload = json_decode($body, true);
    if (!is_array($payload) || !isset($payload[0]) || !is_array($payload[0])) {
        return null;
    }

    return $payload[0];
}

$rawBody = file_get_contents('php://input');
$payload = null;
if ($rawBody !== false && trim($rawBody) !== '') {
    $payload = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(400, [
            'ok' => false,
            'error' => [
                'code' => 'INVALID_JSON',
                'message' => 'Request body must be valid JSON.',
            ],
        ]);
    }
}

if (!is_array($payload)) {
    $payload = $_POST ?: [];
}

$city = trim((string) ($payload['city'] ?? ''));
$state = trim((string) ($payload['state'] ?? ''));
if ($city === '' || $state === '') {
    respond(400, [
        'ok' => false,
        'error' => [
            'code' => 'CITY_STATE_REQUIRED',
            'message' => 'Please provide both city and state.',
        ],
    ]);
}

$result = geocodeCityState($city, $state);
if ($result === null || !isset($result['lat'], $result['lon'])) {
    respond(422, [
        'ok' => false,
        'error' => [
            'code' => 'UNABLE_TO_RESOLVE',
            'message' => 'Could not resolve coordinates for that city and state.',
        ],
    ]);
}

try {
    $latitude = InputValidator::parseLatitude($result['lat']);
    $longitude = InputValidator::parseLongitude($result['lon']);
} catch (InvalidArgumentException $e) {
    respond(422, [
        'ok' => false,
        'error' => [
            'code' => 'INVALID_COORDINATES',
            'message' => $e->getMessage(),
        ],
    ]);
}

respond(200, [
    'ok' => true,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'display_name' => (string) ($result['display_name'] ?? ''),
]);
