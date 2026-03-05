<?php
declare(strict_types=1);

namespace QuantumAstrology\Support;

/**
 * Shared API JSON response helper.
 *
 * Envelope:
 * - success: bool
 * - data: mixed|null
 * - meta: array<string,mixed>
 *
 * Backward compatibility:
 * - Optional legacy fields can be merged into success responses.
 * - Error responses keep top-level "error" as a string for existing clients.
 */
final class ApiResponse
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $legacy
     */
    public static function sendSuccess(
        array $data = [],
        array $meta = [],
        int $statusCode = 200,
        array $legacy = []
    ): void {
        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => $meta,
        ];

        if ($legacy !== []) {
            $payload = array_merge($payload, $legacy);
        }

        self::emit($payload, $statusCode);
    }

    /**
     * @param array<string,mixed> $details
     * @param array<string,mixed> $meta
     */
    public static function sendError(
        string $message,
        string $code = 'API_ERROR',
        int $statusCode = 400,
        array $details = [],
        array $meta = []
    ): void {
        $payload = [
            'success' => false,
            'error' => $message,
            'error_code' => $code,
            'error_details' => $details,
            'data' => null,
            'meta' => $meta,
        ];

        self::emit($payload, $statusCode);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function emit(array $payload, int $statusCode): void
    {
        http_response_code($statusCode);
        if (ob_get_level() > 0 && ob_get_length() > 0) {
            ob_clean();
        }

        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}
