<?php
# api/transits.php
declare(strict_types=1);
session_start();

require __DIR__ . '/../classes/autoload.php';

use QuantumAstrology\Charts\TransitService;

header('Content-Type: application/json');

/** @param array<string,mixed> $payload */
function respond_success(array $payload): void
{
    echo json_encode(['ok' => true] + $payload, JSON_PRETTY_PRINT);
    exit;
}

/** @param array<string,string|array> $fields */
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

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    respond_error(401, 'UNAUTHENTICATED', 'Login required.');
}

$userId = (int)$_SESSION['user_id'];
$chartId = isset($_GET['chart_id']) ? (int)$_GET['chart_id'] : (int)($_GET['id'] ?? 0);
if ($chartId <= 0) {
    respond_error(400, 'INVALID_REQUEST', 'A positive chart_id parameter is required.', [
        'chart_id' => 'Provide a numeric chart id greater than zero.',
    ]);
}

$dateRaw = trim((string)($_GET['date'] ?? ''));
if ($dateRaw === '') {
    respond_error(400, 'INVALID_REQUEST', 'A date parameter in YYYY-MM-DD format is required.', [
        'date' => 'Specify a target date for the transit calculation.',
    ]);
}

$timeRaw = trim((string)($_GET['time'] ?? '00:00'));
if ($timeRaw === '') {
    $timeRaw = '00:00';
}
if (preg_match('/^\d{2}:\d{2}$/', $timeRaw)) {
    $timeRaw .= ':00';
}
if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeRaw)) {
    respond_error(400, 'INVALID_REQUEST', 'Time must be provided as HH:MM or HH:MM:SS.', [
        'time' => 'Use HH:MM or HH:MM:SS format.',
    ]);
}

$timezoneRaw = trim((string)($_GET['tz'] ?? $_GET['timezone'] ?? 'UTC'));
try {
    $tz = new DateTimeZone($timezoneRaw === '' ? 'UTC' : $timezoneRaw);
} catch (Throwable $e) {
    respond_error(400, 'INVALID_REQUEST', 'Unknown timezone supplied.', [
        'timezone' => 'Use a valid PHP timezone identifier.',
    ]);
}

$dateTimeString = sprintf('%s %s', $dateRaw, $timeRaw);
$target = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTimeString, $tz);
$errors = DateTimeImmutable::getLastErrors();
if (!$target || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
    respond_error(400, 'INVALID_REQUEST', 'Unable to parse date/time combination.', [
        'date' => 'Use YYYY-MM-DD format.',
        'time' => 'Use HH:MM or HH:MM:SS format.',
    ]);
}

try {
    $data = TransitService::calculate($chartId, $userId, $target);
    respond_success(['data' => $data]);
} catch (InvalidArgumentException $e) {
    respond_error(400, 'INVALID_REQUEST', $e->getMessage());
} catch (RuntimeException $e) {
    $message = $e->getMessage();
    $code = 'UNPROCESSABLE';
    $status = 422;
    $msgLower = strtolower($message);
    if (str_contains($msgLower, 'not found') || str_contains($msgLower, 'not accessible')) {
        $code = 'NOT_FOUND';
        $status = 404;
    }
    respond_error($status, $code, $message);
} catch (Throwable $e) {
    respond_error(500, 'SERVER_ERROR', 'Transit calculation failed.');
}
