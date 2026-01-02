<?php // tools/migrate.php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use PDO;
use QuantumAstrology\Database\Connection;
use QuantumAstrology\Database\Migrator;

echo "=== Quantum Astrology Database Migration Tool ===\n\n";

echo "Database driver: " . Connection::getDriver() . "\n";
if (Connection::isMySql()) {
    echo "  Host: " . DB_HOST . ":" . DB_PORT . "\n";
    echo "  Database: " . DB_NAME . "\n";
    echo "  User: " . DB_USER . "\n\n";
} else {
    echo "  SQLite Path: " . DB_SQLITE_PATH . "\n\n";
}

try {
    $pdo = Connection::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected successfully\n";

    Migrator::run();

    echo "\n✓ All migrations completed successfully!\n";
} catch (Throwable $e) {
    echo "✗ Database connection or migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
