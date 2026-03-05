<?php
declare(strict_types=1);

namespace QuantumAstrology\Support;

final class OpenRouterMetrics
{
    /**
     * @param array<string,mixed> $keyData
     * @param array<string,mixed> $creditsNode
     * @return array{
     *   limit:?float,
     *   limit_remaining:?float,
     *   usage:?float,
     *   total_credits:?float,
     *   total_usage:?float,
     *   credits_left:?float,
     *   utilization_percent:?float,
     *   usage_daily:?float,
     *   usage_weekly:?float,
     *   usage_monthly:?float,
     *   byok_usage:?float
     * }
     */
    public static function summarize(array $keyData, array $creditsNode): array
    {
        $limit = self::asFloat($keyData['limit'] ?? null);
        $limitRemaining = self::asFloat($keyData['limit_remaining'] ?? null);
        $usage = self::asFloat($keyData['usage'] ?? null);
        $usageDaily = self::asFloat($keyData['usage_daily'] ?? null);
        $usageWeekly = self::asFloat($keyData['usage_weekly'] ?? null);
        $usageMonthly = self::asFloat($keyData['usage_monthly'] ?? null);
        $byokUsage = self::asFloat($keyData['byok_usage'] ?? null);

        $totalCredits = self::asFloat($creditsNode['total_credits'] ?? null);
        $totalUsage = self::asFloat($creditsNode['total_usage'] ?? null);

        $creditsLeft = $limitRemaining;
        if ($creditsLeft === null && $totalCredits !== null && $totalUsage !== null) {
            $creditsLeft = max(0.0, $totalCredits - $totalUsage);
        }

        $utilizationPercent = null;
        if ($limit !== null && $limit > 0.0 && $usage !== null && $usage >= 0.0) {
            $utilizationPercent = min(100.0, max(0.0, ($usage / $limit) * 100.0));
        }

        return [
            'limit' => $limit,
            'limit_remaining' => $limitRemaining,
            'usage' => $usage,
            'total_credits' => $totalCredits,
            'total_usage' => $totalUsage,
            'credits_left' => $creditsLeft,
            'utilization_percent' => $utilizationPercent,
            'usage_daily' => $usageDaily,
            'usage_weekly' => $usageWeekly,
            'usage_monthly' => $usageMonthly,
            'byok_usage' => $byokUsage,
        ];
    }

    private static function asFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
