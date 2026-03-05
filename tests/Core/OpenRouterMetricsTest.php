<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Core;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Support\OpenRouterMetrics;

final class OpenRouterMetricsTest extends TestCase
{
    public function testUsesLimitRemainingAsCreditsLeftWhenPresent(): void
    {
        $result = OpenRouterMetrics::summarize(
            [
                'limit' => 100.0,
                'limit_remaining' => 42.5,
                'usage' => 57.5,
            ],
            [
                'total_credits' => 300.0,
                'total_usage' => 120.0,
            ]
        );

        $this->assertSame(42.5, $result['credits_left']);
        $this->assertEqualsWithDelta(57.5, (float)$result['utilization_percent'], 0.00001);
    }

    public function testFallsBackToTotalCreditsMinusUsageWhenLimitRemainingMissing(): void
    {
        $result = OpenRouterMetrics::summarize(
            [
                'limit' => 50.0,
                'usage' => 5.0,
            ],
            [
                'total_credits' => 12.0,
                'total_usage' => 4.5,
            ]
        );

        $this->assertSame(7.5, $result['credits_left']);
        $this->assertSame(10.0, $result['utilization_percent']);
    }

    public function testCreditsLeftIsClampedAtZero(): void
    {
        $result = OpenRouterMetrics::summarize(
            [],
            [
                'total_credits' => 1.0,
                'total_usage' => 3.25,
            ]
        );

        $this->assertSame(0.0, $result['credits_left']);
    }

    public function testUtilizationIsNullWhenLimitInvalid(): void
    {
        $noLimit = OpenRouterMetrics::summarize(['usage' => 10], []);
        $zeroLimit = OpenRouterMetrics::summarize(['limit' => 0, 'usage' => 10], []);
        $negativeUsage = OpenRouterMetrics::summarize(['limit' => 10, 'usage' => -1], []);

        $this->assertNull($noLimit['utilization_percent']);
        $this->assertNull($zeroLimit['utilization_percent']);
        $this->assertNull($negativeUsage['utilization_percent']);
    }

    public function testUtilizationIsClampedToHundredPercent(): void
    {
        $result = OpenRouterMetrics::summarize(
            ['limit' => 5.0, 'usage' => 9.0],
            []
        );

        $this->assertSame(100.0, $result['utilization_percent']);
    }
}
