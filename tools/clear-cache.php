<?php
// tools/clear-cache.php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

use QuantumAstrology\Support\StorageMaintenance;

fwrite(STDOUT, "Summoning the digital dustpan...\n");

try {
    $result = StorageMaintenance::purge(STORAGE_PATH . '/cache');
    fwrite(
        STDOUT,
        sprintf(
            "Cache broomed: %d files and %d directories spirited away.\n",
            $result['files_removed'],
            $result['directories_removed']
        )
    );
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Cache refused to budge: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
