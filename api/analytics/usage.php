<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\RateLimiter;

header('Content-Type: application/json');

// Require authentication
Auth::requireLogin();

try {
    $user = Auth::user();
    $userId = $user->getId();

    $action = $_GET['action'] ?? 'summary';
    $rateLimiter = new RateLimiter();
    $limitConfig = $rateLimiter->getLimitConfig();

    $response = match ($action) {
        'summary' => [
            'summary' => $rateLimiter->getUsageSummary($userId),
            'top_endpoints' => $rateLimiter->getTopEndpoints($userId, 5),
            'limits' => [
                'requests_per_window' => $limitConfig['free_per_hour'],
                'ai_per_window' => $limitConfig['ai_per_hour'],
                'window_seconds' => $limitConfig['window_seconds'],
            ],
        ],
        'analytics' => [
            'daily_usage' => $rateLimiter->getAnalytics($userId, 30)
        ],
        'top_endpoints' => [
            'endpoints' => $rateLimiter->getTopEndpoints($userId, 20)
        ],
        default => ['error' => 'Unknown action']
    };

    if (isset($response['error'])) {
        http_response_code(400);
    }

    echo json_encode($response);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch analytics',
        'message' => $e->getMessage()
    ]);
}
