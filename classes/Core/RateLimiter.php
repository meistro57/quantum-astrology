<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use PDO;

/**
 * API Rate Limiter with usage tracking and analytics
 */
class RateLimiter
{
    private PDO $pdo;
    private int $defaultLimit;
    private int $windowSeconds;

    // Rate limit tiers
    public const TIER_FREE = 'free';
    public const TIER_PRO = 'pro';
    public const TIER_ENTERPRISE = 'enterprise';

    private const TIER_LIMITS = [
        self::TIER_FREE => 100,        // 100 requests per hour
        self::TIER_PRO => 1000,        // 1000 requests per hour
        self::TIER_ENTERPRISE => 10000 // 10000 requests per hour
    ];

    public function __construct(?PDO $pdo = null, int $defaultLimit = 100, int $windowSeconds = 3600)
    {
        $this->pdo = $pdo ?? DB::conn();
        $this->defaultLimit = $defaultLimit;
        $this->windowSeconds = $windowSeconds;

        $this->ensureTablesExist();
    }

    /**
     * Check if request is allowed under rate limit
     */
    public function isAllowed(int $userId, string $endpoint, ?string $tier = null): bool
    {
        $tier = $tier ?? self::TIER_FREE;
        $limit = self::TIER_LIMITS[$tier] ?? $this->defaultLimit;

        // Get current request count in the time window
        $count = $this->getRequestCount($userId, $endpoint);

        // Log the request
        $this->logRequest($userId, $endpoint, $count < $limit);

        return $count < $limit;
    }

    /**
     * Get current request count for user/endpoint in time window
     */
    public function getRequestCount(int $userId, string $endpoint): int
    {
        $windowStart = time() - $this->windowSeconds;

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM api_rate_limit_log
            WHERE user_id = :user_id
            AND endpoint = :endpoint
            AND timestamp >= :window_start
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':endpoint' => $endpoint,
            ':window_start' => $windowStart
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get remaining requests in current window
     */
    public function getRemainingRequests(int $userId, string $endpoint, ?string $tier = null): int
    {
        $tier = $tier ?? self::TIER_FREE;
        $limit = self::TIER_LIMITS[$tier] ?? $this->defaultLimit;
        $count = $this->getRequestCount($userId, $endpoint);

        return max(0, $limit - $count);
    }

    /**
     * Get time until window resets (in seconds)
     */
    public function getResetTime(int $userId, string $endpoint): int
    {
        $stmt = $this->pdo->prepare("
            SELECT MIN(timestamp)
            FROM api_rate_limit_log
            WHERE user_id = :user_id
            AND endpoint = :endpoint
            AND timestamp >= :window_start
        ");

        $windowStart = time() - $this->windowSeconds;
        $stmt->execute([
            ':user_id' => $userId,
            ':endpoint' => $endpoint,
            ':window_start' => $windowStart
        ]);

        $firstRequestTime = $stmt->fetchColumn();

        if (!$firstRequestTime) {
            return $this->windowSeconds;
        }

        $resetTime = (int)$firstRequestTime + $this->windowSeconds;
        return max(0, $resetTime - time());
    }

    /**
     * Log API request for analytics
     */
    private function logRequest(int $userId, string $endpoint, bool $allowed): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO api_rate_limit_log
            (user_id, endpoint, timestamp, allowed)
            VALUES (:user_id, :endpoint, :timestamp, :allowed)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':endpoint' => $endpoint,
            ':timestamp' => time(),
            ':allowed' => $allowed ? 1 : 0
        ]);

        // Update usage analytics
        $this->updateAnalytics($userId, $endpoint, $allowed);
    }

    /**
     * Update usage analytics aggregates
     */
    private function updateAnalytics(int $userId, string $endpoint, bool $allowed): void
    {
        $today = date('Y-m-d');

        $stmt = $this->pdo->prepare("
            INSERT INTO api_usage_analytics
            (user_id, endpoint, date, total_requests, allowed_requests, blocked_requests)
            VALUES (:user_id, :endpoint, :date, 1, :allowed, :blocked)
            ON CONFLICT(user_id, endpoint, date) DO UPDATE SET
                total_requests = total_requests + 1,
                allowed_requests = allowed_requests + :allowed,
                blocked_requests = blocked_requests + :blocked
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':endpoint' => $endpoint,
            ':date' => $today,
            ':allowed' => $allowed ? 1 : 0,
            ':blocked' => $allowed ? 0 : 1
        ]);
    }

    /**
     * Get usage analytics for user
     */
    public function getAnalytics(int $userId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-$days days"));

        $stmt = $this->pdo->prepare("
            SELECT
                endpoint,
                date,
                total_requests,
                allowed_requests,
                blocked_requests
            FROM api_usage_analytics
            WHERE user_id = :user_id
            AND date >= :start_date
            ORDER BY date DESC, total_requests DESC
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':start_date' => $startDate
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top endpoints by usage
     */
    public function getTopEndpoints(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                endpoint,
                SUM(total_requests) as total_requests,
                SUM(allowed_requests) as allowed_requests,
                SUM(blocked_requests) as blocked_requests
            FROM api_usage_analytics
            WHERE user_id = :user_id
            AND date >= date('now', '-30 days')
            GROUP BY endpoint
            ORDER BY total_requests DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get usage summary for user
     */
    public function getUsageSummary(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(total_requests) as total_requests,
                SUM(allowed_requests) as allowed_requests,
                SUM(blocked_requests) as blocked_requests,
                COUNT(DISTINCT endpoint) as unique_endpoints,
                COUNT(DISTINCT date) as active_days
            FROM api_usage_analytics
            WHERE user_id = :user_id
            AND date >= date('now', '-30 days')
        ");

        $stmt->execute([':user_id' => $userId]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Add current window stats
        $summary['current_hour_requests'] = $this->getCurrentHourRequests($userId);

        return $summary;
    }

    /**
     * Get request count for current hour
     */
    private function getCurrentHourRequests(int $userId): int
    {
        $hourStart = strtotime(date('Y-m-d H:00:00'));

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM api_rate_limit_log
            WHERE user_id = :user_id
            AND timestamp >= :hour_start
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':hour_start' => $hourStart
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Clean old log entries (older than 7 days)
     */
    public function cleanOldLogs(): int
    {
        $sevenDaysAgo = time() - (7 * 24 * 3600);

        $stmt = $this->pdo->prepare("
            DELETE FROM api_rate_limit_log
            WHERE timestamp < :cutoff
        ");

        $stmt->execute([':cutoff' => $sevenDaysAgo]);

        return $stmt->rowCount();
    }

    /**
     * Ensure database tables exist
     */
    private function ensureTablesExist(): void
    {
        // Rate limit log table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS api_rate_limit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                endpoint TEXT NOT NULL,
                timestamp INTEGER NOT NULL,
                allowed INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_rate_limit_user_endpoint
            ON api_rate_limit_log(user_id, endpoint, timestamp)
        ");

        // Analytics aggregation table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS api_usage_analytics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                endpoint TEXT NOT NULL,
                date TEXT NOT NULL,
                total_requests INTEGER DEFAULT 0,
                allowed_requests INTEGER DEFAULT 0,
                blocked_requests INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, endpoint, date)
            )
        ");

        $this->pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_analytics_user_date
            ON api_usage_analytics(user_id, date)
        ");
    }

    /**
     * Set HTTP headers for rate limit info
     */
    public function setRateLimitHeaders(int $userId, string $endpoint, ?string $tier = null): void
    {
        $tier = $tier ?? self::TIER_FREE;
        $limit = self::TIER_LIMITS[$tier] ?? $this->defaultLimit;
        $remaining = $this->getRemainingRequests($userId, $endpoint, $tier);
        $reset = time() + $this->getResetTime($userId, $endpoint);

        header("X-RateLimit-Limit: $limit");
        header("X-RateLimit-Remaining: $remaining");
        header("X-RateLimit-Reset: $reset");
        header("X-RateLimit-Window: {$this->windowSeconds}");
    }

    /**
     * Send rate limit exceeded response
     */
    public function sendRateLimitExceededResponse(int $userId, string $endpoint, ?string $tier = null): void
    {
        $resetTime = $this->getResetTime($userId, $endpoint);

        http_response_code(429);
        header('Content-Type: application/json');
        $this->setRateLimitHeaders($userId, $endpoint, $tier);

        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $resetTime,
            'reset_time' => date('Y-m-d H:i:s', time() + $resetTime)
        ]);
    }
}
