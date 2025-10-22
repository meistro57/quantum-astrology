<?php
// tools/manage-storage.php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

use QuantumAstrology\Support\StorageMaintenance;

/**
 * Display usage instructions for the storage management utility.
 */
function displayUsage(): void
{
    $message = <<<TEXT
Quantum Astrology Storage Butler
--------------------------------
A delightful little assistant for surveying and tidying cached artefacts.

Usage:
  php tools/manage-storage.php [--list] [--target=<name>]
  php tools/manage-storage.php --purge --target=<name|all> [--older-than=<days>] [--dry-run]
  php tools/manage-storage.php --help

Options:
  --list               Show storage metrics (default action).
  --purge              Remove cached files for the chosen target.
  --target             One of: cache, ephemeris, charts, reports, all.
  --older-than         When purging, only remove files older than the given days.
  --dry-run            Simulate a purge without deleting anything.
  --help               Display this charming message.
TEXT;

    fwrite(STDOUT, $message . PHP_EOL);
}

/**
 * Resolve target selections to directory paths.
 *
 * @param string|null $targetKey
 * @param array       $targets
 *
 * @return array<string, string>
 */
function resolveTargets(?string $targetKey, array $targets): array
{
    if ($targetKey === null || $targetKey === '') {
        return $targets;
    }

    if ($targetKey === 'all') {
        return $targets;
    }

    if (!array_key_exists($targetKey, $targets)) {
        throw new \RuntimeException("Unknown target '{$targetKey}'.");
    }

    return [$targetKey => $targets[$targetKey]];
}

$options = getopt('', ['list', 'purge', 'target::', 'older-than::', 'dry-run', 'help']);

if ($options === false) {
    fwrite(STDERR, "Alas, the option parser had a wobble.\n");
    exit(1);
}

if (array_key_exists('help', $options)) {
    displayUsage();
    exit(0);
}

$targets = [
    'cache' => STORAGE_PATH . '/cache',
    'ephemeris' => STORAGE_PATH . '/cache/ephemeris',
    'charts' => STORAGE_PATH . '/charts',
    'reports' => STORAGE_PATH . '/reports',
];

$action = array_key_exists('purge', $options) ? 'purge' : 'list';

try {
    $selectedTargets = resolveTargets($options['target'] ?? null, $targets);
} catch (\RuntimeException $exception) {
    fwrite(STDERR, 'Not so fast: ' . $exception->getMessage() . PHP_EOL);
    displayUsage();
    exit(1);
}

if ($action === 'list') {
    fwrite(STDOUT, "Conducting a brisk audit of the archives...\n");
    foreach ($selectedTargets as $key => $path) {
        $metrics = StorageMaintenance::calculateMetrics($path);
        fwrite(STDOUT, sprintf("%-10s %s\n", $key, StorageMaintenance::formatMetricsForCli($metrics)));
    }
    fwrite(STDOUT, "Audit complete. Spiffing work.\n");
    exit(0);
}

$olderThan = null;
if (array_key_exists('older-than', $options)) {
    if ($options['older-than'] === false || $options['older-than'] === null || $options['older-than'] === '') {
        fwrite(STDERR, "Pruning requires a sensible number of days.\n");
        exit(1);
    }

    if (!ctype_digit((string) $options['older-than'])) {
        fwrite(STDERR, "The --older-than option expects a non-negative integer.\n");
        exit(1);
    }

    $olderThan = StorageMaintenance::boundaryFromDays((int) $options['older-than']);
}

$dryRun = array_key_exists('dry-run', $options);

fwrite(STDOUT, "Sharpening the cosmic broom...\n");

foreach ($selectedTargets as $key => $path) {
    try {
        $result = StorageMaintenance::purge($path, $olderThan, $dryRun);
        $headline = $dryRun ? 'Simulated tidy-up' : 'Purged';
        fwrite(
            STDOUT,
            sprintf(
                "%s %s: %d files, %d directories.\n",
                ucfirst($headline),
                $key,
                $result['files_removed'],
                $result['directories_removed']
            )
        );

        if (!empty($result['failures'])) {
            fwrite(STDERR, "Some items proved stubborn:\n");
            foreach ($result['failures'] as $failure) {
                fwrite(
                    STDERR,
                    sprintf(" - %s (%s)\n", $failure['path'], $failure['message'])
                );
            }
        }
    } catch (\Throwable $exception) {
        fwrite(STDERR, sprintf("Target %s mutinied: %s\n", $key, $exception->getMessage()));
        if (!$dryRun) {
            exit(1);
        }
    }
}

fwrite(STDOUT, $dryRun ? "Dry run concluded. Nothing actually vanished.\n" : "Operation finished. The cosmos is tidier already.\n");
exit(0);
