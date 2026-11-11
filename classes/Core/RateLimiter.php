<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use PDO;
use QuantumAstrology\Support\ApiResponse;

/**
 * API rate limiter with usage tracking and analytics.
 *
 * Limits are resolved in this order: constructor overrides, admin settings
 * (system_settings), environment variables, then built-in defaults.
 */
class RateLimiter
{
    private PDO $pdo;
    private int $defaultLimit;
    private int $windowSeconds;
    private int $aiLimit;

    /** @var array<string,int> */
    private array $tierLimits;

    // Rate limit tiers
    public const TIER_FREE = 'free';
    public const TIER_PRO = 'pro';
    public const TIER_ENTERPRISE = 'enterprise';

    private const DEFAULT_LIMITS = [
        'free_per_hour' => 100,
        'pro_per_hour' => 1000,
        'enterprise_per_hour' => 10000,
        'ai_per_hour' => 20,
        'window_seconds' => 3600,
    ];

    public function __construct(
        ?PDO $pdo = null,
        ?int $defaultLimit = null,
        ?int $windowSeconds = null,
        ?int $aiLimit = null
    ) {
        $config = self::resolveConfig($pdo === null);

        $this->pdo = $pdo ?? DB::conn();
        $this->windowSeconds = max(1, $windowSeconds ?? (int) $config['window_seconds']);
        $this->aiLimit = max(1, $aiLimit ?? (int) $config['ai_per_hour']);

        $free = max(1, $defaultLimit ?? (int) $config['free_per_hour']);
        $this->defaultLimit = $free;
        $this->tierLimits = [
            self::TIER_FREE => $free,
            self::TIER_PRO => max($free, (int) $config['pro_per_hour']),
            self::TIER_ENTERPRISE => max($free, (int) $config['enterprise_per_hour']),
        ];

        $this->ensureTablesExist();
    }

    /**
     * @return array{free_per_hour:int,pro_per_hour:int,enterprise_per_hour:int,ai_per_hour:int,window_seconds:int}
     */
    public static function defaults(): array
    {
        return [
            'free_per_hour' => self::envInt('RATE_LIMIT_FREE_PER_HOUR', self::DEFAULT_LIMITS['free_per_hour']),
            'pro_per_hour' => self::envInt('RATE_LIMIT_PRO_PER_HOUR', self::DEFAULT_LIMITS['pro_per_hour']),
            'enterprise_per_hour' => self::envInt('RATE_LIMIT_ENTERPRISE_PER_HOUR', self::DEFAULT_LIMITS['enterprise_per_hour']),
            'ai_per_hour' => self::envInt('RATE_LIMIT_AI_PER_HOUR', self::DEFAULT_LIMITS['ai_per_hour']),
            'window_seconds' => self::envInt('RATE_LIMIT_WINDOW_SECONDS', self::DEFAULT_LIMITS['window_seconds']),
        ];
    }

    /**
     * @return array{free_per_hour:int,pro_per_hour:int,enterprise_per_hour:int,ai_per_hour:int,window_seconds:int}
     */
    public static function resolveConfig(bool $includeStored = true): array
    {
        $config = self::defaults();

        if ($includeStored && class_exists(SystemSettings::class)) {
            try {
                $config = SystemSettings::getRateLimitConfig($config);
            } catch (\Throwable) {
                // Fall back to environment/defaults when settings cannot be read.
            }
        }

        return $config;
    }

    /**
     * @return array{free_per_hour:int,pro_per_hour:int,enterprise_per_hour:int,ai_per_hour:int,window_seconds:int}
     */
    public function getLimitConfig(): array
    {
        return [
            'free_per_hour' => $this->tierLimits[self::TIER_FREE] ?? $this->defaultLimit,
            'pro_per_hour' => $this->tierLimits[self::TIER_PRO] ?? $this->defaultLimit,
            'enterprise_per_hour' => $this->tierLimits[self::TIER_ENTERPRISE] ?? $this->defaultLimit,
            'ai_per_hour' => $this->aiLimit,
            'window_seconds' => $this->windowSeconds,
        ];
    }

    /**
     * Evaluate a request against the applicable limit without recording it.
     *
     * @return array{
     *   allowed:bool,
     *   limit:int,
     *   used:int,
     *   remaining:int,
     *   reset_in:int,
     *   window_seconds:int,
     *   endpoint:string,
     *   tier:string,
     *   is_ai:bool
     * }
     */
    public function evaluate(int $userId, string $endpoint, ?string $tier = null): array
    {
        $endpoint = $this->normalizeEndpoint($endpoint);
        $tier = $tier ?? self::TIER_FREE;
        $limit = $this->limitFor($endpoint, $tier);

        $stats = $this->windowStats($userId, $endpoint);
        $used = $stats['count'];
        $allowed = $used < $limit;

        $resetIn = $stats['first'] === null
            ? $this->windowSeconds
            : max(0, $stats['first'] + $this->windowSeconds - time());

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used - ($allowed ? 1 : 0)),
            'reset_in' => $resetIn,
            'window_seconds' => $this->windowSeconds,
            'endpoint' => $endpoint,
            'tier' => $tier,
            'is_ai' => $this->isAiEndpoint($endpoint),
        ];
    }

    /**
     * Check if request is allowed under rate limit.
     *
     * The evaluated request is recorded either way so analytics reflects
     * blocked attempts.
     */
    public function isAllowed(int $userId, string $endpoint, ?string $tier = null): bool
    {
        $decision = $this->evaluate($userId, $endpoint, $tier);
        $this->recordRequest($userId, $decision['endpoint'], $decision['allowed']);

        return $decision['allowed'];
    }

    /**
     * Record an API request.
     */
    public function recordRequest(int $userId, string $endpoint, bool $allowed = true): void
    {
        $this->logRequest($userId, $this->normalizeEndpoint($endpoint), $allowed);
    }

    public function getWindowSeconds(): int
    {
        return $this->windowSeconds;
    }

    /**
     * Resolve the effective limit for an endpoint and tier.
     */
    public function limitFor(string $endpoint, ?string $tier = null): int
    {
        $tier = $tier ?? self::TIER_FREE;
        $tierLimit = $this->tierLimits[$tier] ?? $this->defaultLimit;

        if ($this->isAiEndpoint($this->normalizeEndpoint($endpoint))) {
            return min($tierLimit, $this->aiLimit);
        }

        return $tierLimit;
    }

    /**
     * Normalize an endpoint so path identifiers share one bucket.
     *
     * Example: /api/charts/42/interpretation/ai -> /api/charts/{id}/interpretation/ai
     */
    public function normalizeEndpoint(string $endpoint): string
    {
        $path = parse_url($endpoint, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $endpoint;

        $normalized = preg_replace('#/\d+(?=/|$)#', '/{id}', $path);
        if (!is_string($normalized) || $normalized === '') {
            return '/';
        }

        return $normalized;
    }

    /**
     * AI endpoints are more expensive, so they get a stricter limit.
     */
    public function isAiEndpoint(string $endpoint): bool
    {
        $path = $this->normalizeEndpoint($endpoint);

        if (preg_match('#/api/reports/(ai_summary|ai_master_document|horoscope)(?:\.php)?$#', $path) === 1) {
            return true;
        }

        return str_contains($path, '/interpretation/ai');
    }

    /**
     * Stable synthetic identifier for unauthenticated clients.
     *
     * Values live in the 2^30..2^31 range so they never collide with
     * auto-increment user ids while still fitting every supported column type.
     */
    public static function anonymousKey(string $ipAddress): int
    {
        $hash = crc32('qa-anon:' . $ipAddress) & 0x7FFFFFFF;

        return 0x40000000 + ($hash % 0x40000000);
    }

    /**
     * Get current request count for user/endpoint in the time window.
     */
    public function getRequestCount(int $userId, string $endpoint): int
    {
        $stats = $this->windowStats($userId, $this->normalizeEndpoint($endpoint));

        return $stats['count'];
    }

    /**
     * Get remaining requests in current window.
     */
    public function getRemainingRequests(int $userId, string $endpoint, ?string $tier = null): int
    {
        $endpoint = $this->normalizeEndpoint($endpoint);
        $limit = $this->limitFor($endpoint, $tier);

        return max(0, $limit - $this->getRequestCount($userId, $endpoint));
    }

    /**
     * Get time until window resets (in seconds).
     */
    public function getResetTime(int $userId, string $endpoint): int
    {
        $stats = $this->windowStats($userId, $this->normalizeEndpoint($endpoint));

        if ($stats['first'] === null) {
            return $this->windowSeconds;
        }

        return max(0, $stats['first'] + $this->windowSeconds - time());
    }

    /**
     * @return array{count:int,first:?int}
     */
    private function windowStats(int $userId, string $endpoint): array
    {
        $windowStart = time() - $this->windowSeconds;

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total, MIN(timestamp) AS first_ts
            FROM api_rate_limit_log
            WHERE user_id = :user_id
            AND endpoint = :endpoint
            AND timestamp >= :window_start
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':endpoint' => $endpoint,
            ':window_start' => $windowStart,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'count' => (int) ($row['total'] ?? 0),
            'first' => isset($row['first_ts']) && $row['first_ts'] !== null ? (int) $row['first_ts'] : null,
        ];
    }

    /**
     * Log API request for analytics.
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
            ':allowed' => $allowed ? 1 : 0,
        ]);

        $this->updateAnalytics($userId, $endpoint, $allowed);
    }

    /**
     * Update usage analytics aggregates.
     */
    private function updateAnalytics(int $userId, string $endpoint, bool $allowed): void
    {
        $today = date('Y-m-d');
        $allowedValue = $allowed ? 1 : 0;
        $blockedValue = $allowed ? 0 : 1;

        if ($this->isMySql()) {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_usage_analytics
                (user_id, endpoint, date, total_requests, allowed_requests, blocked_requests)
                VALUES (:user_id, :endpoint, :date, 1, :allowed, :blocked)
                ON DUPLICATE KEY UPDATE
                    total_requests = total_requests + 1,
                    allowed_requests = allowed_requests + VALUES(allowed_requests),
                    blocked_requests = blocked_requests + VALUES(blocked_requests)
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':endpoint' => $endpoint,
                ':date' => $today,
                ':allowed' => $allowedValue,
                ':blocked' => $blockedValue,
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO api_usage_analytics
            (user_id, endpoint, date, total_requests, allowed_requests, blocked_requests)
            VALUES (:user_id, :endpoint, :date, 1, :allowed, :blocked)
            ON CONFLICT(user_id, endpoint, date) DO UPDATE SET
                total_requests = total_requests + 1,
                allowed_requests = allowed_requests + excluded.allowed_requests,
                blocked_requests = blocked_requests + excluded.blocked_requests
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':endpoint' => $endpoint,
            ':date' => $today,
            ':allowed' => $allowedValue,
            ':blocked' => $blockedValue,
        ]);
    }

    /**
     * Get usage analytics for user.
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
            ':start_date' => $startDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top endpoints by usage.
     */
    public function getTopEndpoints(int $userId, int $limit = 10): array
    {
        $startDate = date('Y-m-d', strtotime('-30 days'));

        $stmt = $this->pdo->prepare("
            SELECT
                endpoint,
                SUM(total_requests) as total_requests,
                SUM(allowed_requests) as allowed_requests,
                SUM(blocked_requests) as blocked_requests
            FROM api_usage_analytics
            WHERE user_id = :user_id
            AND date >= :start_date
            GROUP BY endpoint
            ORDER BY total_requests DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get usage summary for user.
     */
    public function getUsageSummary(int $userId): array
    {
        $startDate = date('Y-m-d', strtotime('-30 days'));

        $stmt = $this->pdo->prepare("
            SELECT
                SUM(total_requests) as total_requests,
                SUM(allowed_requests) as allowed_requests,
                SUM(blocked_requests) as blocked_requests,
                COUNT(DISTINCT endpoint) as unique_endpoints,
                COUNT(DISTINCT date) as active_days
            FROM api_usage_analytics
            WHERE user_id = :user_id
            AND date >= :start_date
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':start_date' => $startDate,
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Add current window stats
        $summary['current_hour_requests'] = $this->getCurrentHourRequests($userId);

        return $summary;
    }

    /**
     * Get request count for current hour.
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
            ':hour_start' => $hourStart,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Clean old log entries (older than 7 days).
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
     * Build the rate limit headers for a decision.
     *
     * @param array{limit:int,remaining:int,reset_in:int,window_seconds:int} $decision
     * @return array<string,string>
     */
    public function buildRateLimitHeaders(array $decision): array
    {
        return [
            'X-RateLimit-Limit' => (string) (int) $decision['limit'],
            'X-RateLimit-Remaining' => (string) max(0, (int) $decision['remaining']),
            'X-RateLimit-Reset' => (string) (time() + max(0, (int) $decision['reset_in'])),
            'X-RateLimit-Window' => (string) (int) $decision['window_seconds'],
        ];
    }

    /**
     * @param array{limit:int,remaining:int,reset_in:int,window_seconds:int} $decision
     */
    public function emitRateLimitHeaders(array $decision): void
    {
        foreach ($this->buildRateLimitHeaders($decision) as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * Set HTTP headers for rate limit info.
     */
    public function setRateLimitHeaders(int $userId, string $endpoint, ?string $tier = null): void
    {
        $this->emitRateLimitHeaders($this->evaluate($userId, $endpoint, $tier));
    }

    /**
     * Send rate limit exceeded response.
     *
     * @param array{limit:int,used:int,remaining:int,reset_in:int,window_seconds:int,endpoint:string}|null $decision
     */
    public function sendRateLimitExceededResponse(
        int $userId,
        string $endpoint,
        ?string $tier = null,
        ?array $decision = null
    ): void {
        $decision = $decision ?? $this->evaluate($userId, $endpoint, $tier);
        $retryAfter = max(1, (int) $decision['reset_in']);

        $this->emitRateLimitHeaders($decision);
        header('Retry-After: ' . $retryAfter);

        ApiResponse::sendError(
            'Rate limit exceeded. Please try again later.',
            'RATE_LIMITED',
            429,
            [
                'limit' => (int) $decision['limit'],
                'used' => (int) $decision['used'],
                'window_seconds' => (int) $decision['window_seconds'],
                'retry_after' => $retryAfter,
                'endpoint' => (string) $decision['endpoint'],
            ],
            ['retry_after' => $retryAfter]
        );
    }

    private function isMySql(): bool
    {
        return strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql';
    }

    /**
     * Ensure database tables exist.
     */
    private function ensureTablesExist(): void
    {
        if ($this->isMySql()) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS api_rate_limit_log (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    endpoint VARCHAR(255) NOT NULL,
                    timestamp INT UNSIGNED NOT NULL,
                    allowed TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_rate_limit_user_endpoint (user_id, endpoint, timestamp)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS api_usage_analytics (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    endpoint VARCHAR(255) NOT NULL,
                    date DATE NOT NULL,
                    total_requests INT UNSIGNED DEFAULT 0,
                    allowed_requests INT UNSIGNED DEFAULT 0,
                    blocked_requests INT UNSIGNED DEFAULT 0,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_user_endpoint_date (user_id, endpoint, date),
                    INDEX idx_analytics_user_date (user_id, date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            return;
        }

        // SQLite
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

    private static function envInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            return $default;
        }

        return (int) $parsed;
    }
}
