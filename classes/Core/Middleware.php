<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

/**
 * Middleware for request processing
 */
class Middleware
{
    private static ?RateLimiter $rateLimiter = null;

    /**
     * Apply rate limiting to API request
     */
    public static function applyRateLimit(int $userId, string $endpoint, ?string $tier = null): bool
    {
        $limiter = self::getRateLimiter();

        // Check if request is allowed
        if (!$limiter->isAllowed($userId, $endpoint, $tier)) {
            $limiter->sendRateLimitExceededResponse($userId, $endpoint, $tier);
            exit;
        }

        // Set rate limit headers
        $limiter->setRateLimitHeaders($userId, $endpoint, $tier);

        return true;
    }

    /**
     * Get or create rate limiter instance
     */
    public static function getRateLimiter(): RateLimiter
    {
        if (self::$rateLimiter === null) {
            self::$rateLimiter = new RateLimiter();
        }

        return self::$rateLimiter;
    }

    /**
     * Log API request (without rate limiting)
     */
    public static function logApiRequest(int $userId, string $endpoint, array $metadata = []): void
    {
        try {
            $pdo = DB::conn();

            $stmt = $pdo->prepare("
                INSERT INTO api_request_log
                (user_id, endpoint, method, ip_address, user_agent, metadata, created_at)
                VALUES (:user_id, :endpoint, :method, :ip, :ua, :metadata, datetime('now'))
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':endpoint' => $endpoint,
                ':method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                ':metadata' => json_encode($metadata)
            ]);
        } catch (\Throwable $e) {
            // Silently fail - logging shouldn't break the API
            error_log("Failed to log API request: " . $e->getMessage());
        }
    }

    /**
     * Ensure API logging table exists
     */
    public static function ensureApiLogTable(): void
    {
        $pdo = DB::conn();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_request_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                endpoint TEXT NOT NULL,
                method TEXT NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                metadata TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_api_log_user_endpoint
            ON api_request_log(user_id, endpoint, created_at)
        ");
    }
}
