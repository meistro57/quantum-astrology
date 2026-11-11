<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Core;

use PDO;
use PHPUnit\Framework\TestCase;
use QuantumAstrology\Core\RateLimiter;

final class RateLimiterTest extends TestCase
{
    private function sqlite(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    public function testEnforcesLimitAcrossPathIdentifiers(): void
    {
        $limiter = new RateLimiter($this->sqlite(), 2, 60, 1);

        $this->assertTrue($limiter->isAllowed(7, '/api/charts/1/wheel'));
        $this->assertTrue($limiter->isAllowed(7, '/api/charts/2/wheel'));
        $this->assertFalse($limiter->isAllowed(7, '/api/charts/3/wheel'));

        // Different chart ids share a bucket.
        $this->assertSame(3, $limiter->getRequestCount(7, '/api/charts/999/wheel'));

        $summary = $limiter->getUsageSummary(7);
        $this->assertSame(2, (int) $summary['allowed_requests']);
        $this->assertSame(1, (int) $summary['blocked_requests']);
    }

    public function testWindowResetsAfterExpiry(): void
    {
        $limiter = new RateLimiter($this->sqlite(), 1, 2, 1);

        $this->assertTrue($limiter->isAllowed(11, '/api/charts/1/wheel'));
        $this->assertFalse($limiter->isAllowed(11, '/api/charts/1/wheel'));

        sleep(3);

        $this->assertTrue($limiter->isAllowed(11, '/api/charts/1/wheel'));
    }

    public function testAiEndpointsUseStricterLimit(): void
    {
        $limiter = new RateLimiter($this->sqlite(), 100, 60, 2);

        $this->assertSame(2, $limiter->limitFor('/api/reports/ai_summary.php'));
        $this->assertSame(2, $limiter->limitFor('/api/reports/horoscope.php'));
        $this->assertSame(2, $limiter->limitFor('/api/charts/5/interpretation/ai'));
        $this->assertSame(100, $limiter->limitFor('/api/charts/5/wheel'));
        $this->assertSame(100, $limiter->limitFor('/api/ai/providers'));
    }

    public function testHeadersReflectCurrentRequest(): void
    {
        $limiter = new RateLimiter($this->sqlite(), 5, 60, 1);

        $headers = $limiter->buildRateLimitHeaders($limiter->evaluate(9, '/api/charts/1/wheel'));

        $this->assertSame('5', $headers['X-RateLimit-Limit']);
        $this->assertSame('4', $headers['X-RateLimit-Remaining']);
        $this->assertSame('60', $headers['X-RateLimit-Window']);
        $this->assertGreaterThan(0, (int) $headers['X-RateLimit-Reset']);
    }

    public function testAnonymousKeysAreStableAndSeparateFromUserIds(): void
    {
        $first = RateLimiter::anonymousKey('203.0.113.10');
        $second = RateLimiter::anonymousKey('203.0.113.11');

        $this->assertSame($first, RateLimiter::anonymousKey('203.0.113.10'));
        $this->assertNotSame($first, $second);
        $this->assertGreaterThanOrEqual(0x40000000, $first);
        $this->assertLessThanOrEqual(0x7FFFFFFF, $first);
    }
}
