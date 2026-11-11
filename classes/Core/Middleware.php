<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

/**
 * Middleware for request processing
 */
class Middleware
{
    private static ?RateLimiter $rateLimiter = null;
    private static bool $apiRequestChecked = false;

    /**
     * Enforce the rate limit for the current API request exactly once.
     *
     * This is called from config.php so that endpoints executed directly
     * (bypassing the front controller) are limited too, and again from the
     * router as a safety net.
     */
    public static function enforceApiRequest(string $path): void
    {
        if (self::$apiRequestChecked || self::isRateLimitExempt($path)) {
            self::$apiRequestChecked = true;
            return;
        }
        self::$apiRequestChecked = true;

        try {
            $currentUser = Auth::user();
            $isAdmin = $currentUser !== null && AdminGate::canAccess($currentUser);

            $identifier = $currentUser !== null
                ? (int) $currentUser->getId()
                : RateLimiter::anonymousKey(self::clientIpAddress());

            $tier = $isAdmin ? RateLimiter::TIER_ENTERPRISE : RateLimiter::TIER_FREE;

            self::applyRateLimit($identifier, $path, $tier);
        } catch (\Throwable $e) {
            // Rate limiting must never take the API down if the store fails.
            Logger::warning('Rate limit check skipped', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
        }
    }

    /**
     * Best-effort client IP for anonymous limits. Authenticated requests are
     * keyed by user id, so proxy headers only affect public endpoints.
     */
    private static function clientIpAddress(): string
    {
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        $realIp = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? '');
        if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP) !== false) {
            return $realIp;
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function isRateLimitExempt(string $path): bool
    {
        return $path === '/api/health'
            || $path === '/api/health.php'
            || str_starts_with($path, '/api/health/');
    }

    /**
     * Apply rate limiting to API request.
     *
     * Emits rate limit headers, records the attempt, and sends a 429
     * response when the limit is exceeded.
     */
    public static function applyRateLimit(int $userId, string $endpoint, ?string $tier = null): bool
    {
        $limiter = self::getRateLimiter();
        $decision = $limiter->evaluate($userId, $endpoint, $tier);

        $limiter->recordRequest($userId, $decision['endpoint'], $decision['allowed']);
        $limiter->emitRateLimitHeaders($decision);

        if (!$decision['allowed']) {
            $limiter->sendRateLimitExceededResponse($userId, $decision['endpoint'], $tier, $decision);
            exit;
        }

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
