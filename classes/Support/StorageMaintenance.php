<?php
// classes/Support/StorageMaintenance.php
declare(strict_types=1);

namespace QuantumAstrology\Support;

use DateInterval;
use DateTimeImmutable;
use FilesystemIterator;
use QuantumAstrology\Core\Logger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class StorageMaintenance
{
    /**
     * Calculate key metrics for a storage directory.
     *
     * @param string $directory Absolute directory path.
     *
     * @return array{
     *     path: string,
     *     exists: bool,
     *     file_count: int,
     *     directory_count: int,
     *     total_size: int,
     *     newest_modified_at: string|null,
     *     oldest_modified_at: string|null
     * }
     */
    public static function calculateMetrics(string $directory): array
    {
        $metrics = [
            'path' => $directory,
            'exists' => is_dir($directory),
            'file_count' => 0,
            'directory_count' => 0,
            'total_size' => 0,
            'newest_modified_at' => null,
            'oldest_modified_at' => null,
        ];

        if (!$metrics['exists']) {
            return $metrics;
        }

        $newest = null;
        $oldest = null;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $metrics['directory_count']++;
                continue;
            }

            $metrics['file_count']++;
            $metrics['total_size'] += $item->getSize();

            $modified = DateTimeImmutable::createFromFormat('U', (string) $item->getMTime());
            if ($modified instanceof DateTimeImmutable) {
                if ($newest === null || $modified > $newest) {
                    $newest = $modified;
                }

                if ($oldest === null || $modified < $oldest) {
                    $oldest = $modified;
                }
            }
        }

        $metrics['newest_modified_at'] = $newest?->format(DateTimeImmutable::ATOM);
        $metrics['oldest_modified_at'] = $oldest?->format(DateTimeImmutable::ATOM);

        return $metrics;
    }

    /**
     * Purge files (and optionally directories) from a storage directory.
     *
     * @param string                 $directory    Absolute directory path.
     * @param DateTimeImmutable|null $olderThan    Remove files older than this timestamp when provided.
     * @param bool                   $dryRun       When true, perform a simulation without deleting.
     *
     * @return array{
     *     path: string,
     *     dry_run: bool,
     *     older_than: string|null,
     *     files_removed: int,
     *     directories_removed: int,
     *     failures: array<int, array{path: string, message: string}>
     * }
     */
    public static function purge(string $directory, ?DateTimeImmutable $olderThan = null, bool $dryRun = false): array
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create storage directory: {$directory}");
            }
        }

        $result = [
            'path' => $directory,
            'dry_run' => $dryRun,
            'older_than' => $olderThan?->format(DateTimeImmutable::ATOM),
            'files_removed' => 0,
            'directories_removed' => 0,
            'failures' => [],
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isDir()) {
                if ($dryRun) {
                    continue;
                }

                if (@rmdir($path)) {
                    $result['directories_removed']++;
                    continue;
                }

                if ($olderThan !== null) {
                    // During selective purges, silently skip directories that still contain fresh files.
                    continue;
                }

                if (!is_dir($path)) {
                    $result['directories_removed']++;
                    continue;
                }

                $result['failures'][] = [
                    'path' => $path,
                    'message' => 'Failed to remove directory (not empty or permission denied).',
                ];
                continue;
            }

            $modified = DateTimeImmutable::createFromFormat('U', (string) $item->getMTime());
            if ($olderThan !== null && $modified instanceof DateTimeImmutable && $modified >= $olderThan) {
                continue;
            }

            if ($dryRun) {
                $result['files_removed']++;
                continue;
            }

            if (@unlink($path)) {
                $result['files_removed']++;
                continue;
            }

            $result['failures'][] = [
                'path' => $path,
                'message' => 'Failed to delete file (permission denied).',
            ];
        }

        if ($olderThan === null && !$dryRun) {
            // Ensure root directory exists after full purge.
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to recreate storage directory: {$directory}");
            }
        }

        if (!empty($result['failures'])) {
            Logger::warning('Storage purge completed with warnings', [
                'directory' => $directory,
                'failures' => $result['failures'],
            ]);
        } else {
            Logger::info('Storage purge completed', [
                'directory' => $directory,
                'files_removed' => $result['files_removed'],
                'directories_removed' => $result['directories_removed'],
                'dry_run' => $dryRun,
                'older_than' => $result['older_than'],
            ]);
        }

        return $result;
    }

    /**
     * Convert a relative interval (in days) into a DateTimeImmutable boundary.
     *
     * @param int $days Number of days in the past.
     */
    public static function boundaryFromDays(int $days): DateTimeImmutable
    {
        if ($days < 0) {
            throw new RuntimeException('The number of days must be zero or positive.');
        }

        $now = new DateTimeImmutable('now');
        $interval = new DateInterval('P' . max($days, 0) . 'D');

        return $now->sub($interval);
    }

    /**
     * Format metrics for display.
     *
     * @param array $metrics Metrics array returned by calculateMetrics().
     */
    public static function formatMetricsForCli(array $metrics): string
    {
        if ($metrics['exists'] === false) {
            return sprintf("%-20s %s", basename($metrics['path']), 'not found');
        }

        $sizeInMb = $metrics['total_size'] / 1024 / 1024;

        return sprintf(
            "%-20s %6d files %6d dirs %8.2f MB (newest: %s)",
            basename($metrics['path']),
            $metrics['file_count'],
            $metrics['directory_count'],
            $sizeInMb,
            $metrics['newest_modified_at'] ?? 'n/a'
        );
    }
}
