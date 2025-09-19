#!/usr/bin/env php
<?php
declare(strict_types=1);

use QuantumAstrology\Core\QuantumSeed;
use QuantumAstrology\Core\QuantumSeedGenerator;

require_once __DIR__ . '/../vendor/autoload.php';
if (!class_exists(QuantumSeedGenerator::class)) {
    require_once __DIR__ . '/../classes/autoload.php';
}

$argv = $_SERVER['argv'] ?? [];
$commandArg = $argv[1] ?? null;
if ($commandArg === null || str_starts_with($commandArg, '--')) {
    $command = 'seed';
} else {
    $command = $commandArg;
}

$arguments = array_slice($argv, 1);

if ($commandArg !== null && !str_starts_with($commandArg, '--')) {
    array_shift($arguments);
}

$options = parseOptions($arguments);

if ($command === 'help' || $options['help']) {
    printUsage();
    exit(0);
}

switch ($command) {
    case 'seed':
    case 'seeds':
        $count = $options['count'] ?? 1;
        $entropy = $options['entropy'];
        $asJson = $options['json'];

        try {
            $generator = new QuantumSeedGenerator($entropy);
            $seeds = $generator->generateBatch($count);
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
            exit(1);
        }

        if ($asJson) {
            $payload = [
                'generated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'entropy_bytes' => $generator->getEntropyBytes(),
                'count' => count($seeds),
                'seeds' => array_map(
                    static fn (QuantumSeed $seed): array => $seed->toArray(),
                    $seeds
                ),
            ];

            try {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
            } catch (\JsonException $exception) {
                fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
                exit(1);
            }

            exit(0);
        }

        foreach ($seeds as $index => $seed) {
            echo 'Quantum Seed #' . ($index + 1) . PHP_EOL;
            echo str_repeat('-', 40) . PHP_EOL;
            echo $seed->format() . PHP_EOL;
            if ($index < count($seeds) - 1) {
                echo PHP_EOL;
            }
        }

        exit(0);

    default:
        fwrite(STDERR, 'Unknown command: ' . $command . PHP_EOL);
        printUsage();
        exit(1);
}

function printUsage(): void
{
    $usage = <<<TXT
Quantum Astrology CLI

Usage:
  php tools/quantum-cli.php seed [--count=<n>] [--entropy=<bytes>] [--json]

Commands:
  seed, seeds   Generate one or more quantum seeds

Options:
  --count       Number of seeds to generate (default: 1)
  --entropy     Entropy bytes used for each seed (default: 32)
  --json        Output results as JSON
  --help        Show this message
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

/**
 * @param list<string> $arguments
 * @return array{count: ?int, entropy: ?int, json: bool, help: bool}
 */
function parseOptions(array $arguments): array
{
    $options = [
        'count' => null,
        'entropy' => null,
        'json' => false,
        'help' => false,
    ];

    while ($arguments !== []) {
        $argument = array_shift($arguments);
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $argument = substr($argument, 2);

        if ($argument === 'json') {
            $options['json'] = true;
            continue;
        }

        if ($argument === 'help') {
            $options['help'] = true;
            continue;
        }

        $value = null;
        if (str_contains($argument, '=')) {
            [$name, $value] = explode('=', $argument, 2);
        } else {
            $name = $argument;
            if ($arguments !== [] && !str_starts_with((string) $arguments[0], '--')) {
                $value = array_shift($arguments);
            }
        }

        if ($value === null) {
            continue;
        }

        if ($name === 'count') {
            $options['count'] = (int) $value;
        } elseif ($name === 'entropy') {
            $options['entropy'] = (int) $value;
        }
    }

    $options['count'] ??= 1;

    return $options;
}
