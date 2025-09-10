<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use QuantumAstrology\Database\Migrator;

try {
    echo "Running database migrations...\n";
    Migrator::run();
    echo "Migration complete!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}