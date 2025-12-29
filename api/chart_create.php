<?php
# api/chart_create.php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Charts\ChartService;
use QuantumAstrology\Support\InputValidator;

header('Content-Type: application/json');

/**
 * Send a JSON error response with a consistent payload structure.
 */
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

$rawBody = file_get_contents('php://input');
$payload = null;
if ($rawBody !== false && trim($rawBody) !== '') {
    $payload = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond_error(400, 'INVALID_JSON', 'Request body must be valid JSON.', [
            'body' => json_last_error_msg(),
        ]);
    }
}

if (!is_array($payload)) {
    $payload = $_POST ?: [];
}

$name       = trim((string) ($payload['name'] ?? ''));
$birthDate  = trim((string) ($payload['birth_date'] ?? ''));
$birthTime  = trim((string) ($payload['birth_time'] ?? ''));
$houseInput = strtoupper(trim((string) ($payload['house_system'] ?? 'P')));
$houseSystem = $houseInput !== '' ? $houseInput : 'P';

$errors = [];

if ($name === '') {
    $errors['name'] = 'Chart name is required.';
}
if ($birthDate === '') {
    $errors['birth_date'] = 'Birth date is required.';
}
if ($birthTime === '') {
    $errors['birth_time'] = 'Birth time is required.';
}

$timezoneInput = $payload['birth_timezone'] ?? '';
$timezoneInput = is_string($timezoneInput) ? trim($timezoneInput) : '';
$birthTimezone = 'UTC';
if ($timezoneInput !== '') {
    try {
        $birthTimezone = InputValidator::normaliseTimezone($timezoneInput);
    } catch (\InvalidArgumentException $e) {
        $errors['birth_timezone'] = $e->getMessage();
    }
}

try {
    $birthLatitude = InputValidator::parseLatitude($payload['birth_latitude'] ?? null);
} catch (\InvalidArgumentException $e) {
    $errors['birth_latitude'] = $e->getMessage();
}

try {
    $birthLongitude = InputValidator::parseLongitude($payload['birth_longitude'] ?? null);
} catch (\InvalidArgumentException $e) {
    $errors['birth_longitude'] = $e->getMessage();
}

if (!empty($errors)) {
    respond_error(422, 'VALIDATION_ERROR', 'There were problems with the data you submitted.', $errors);
}

try {
    $chart = ChartService::create(
        $name,
        $birthDate,
        $birthTime,
        $birthTimezone,
        $birthLatitude,
        $birthLongitude,
        $houseSystem
    );

    if (!$chart) {
        respond_error(500, 'CHART_CREATE_FAILED', 'Unable to create chart at this time.');
    }

    http_response_code(201);
    echo json_encode(['ok' => true, 'chart' => $chart], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    respond_error(500, 'CHART_CREATE_FAILED', 'Unable to create chart at this time.');
}
