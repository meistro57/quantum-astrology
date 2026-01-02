<?php // tools/setup-database.php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database/Connection.php';
require_once __DIR__ . '/../classes/Core/Logger.php';
require_once __DIR__ . '/../classes/Database/Migrator.php';

use QuantumAstrology\Database\Connection;
use QuantumAstrology\Database\Migrator;
use QuantumAstrology\Core\Logger;
use PDOException;

$driver = Connection::getDriver();

echo "Setting up Quantum Astrology database using {$driver}...\n";

try {
    if (Connection::isSqlite()) {
        $dbDir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
            echo "Created storage directory: {$dbDir}\n";
        }
    }

    $pdo = Connection::getInstance();

    echo Connection::isMySql()
        ? "Connected to MySQL/MariaDB at " . DB_HOST . ':' . DB_PORT . " (schema: " . DB_NAME . ")\n"
        : "SQLite database initialized at: " . DB_SQLITE_PATH . "\n";

    echo "\nRunning migrations...\n";
    Migrator::run();

    echo "\nDatabase setup complete!\n";
    Logger::info('Database setup completed successfully', ['driver' => $driver]);
} catch (PDOException $e) {
    echo "Database setup failed: " . $e->getMessage() . "\n";
    Logger::error('Database setup failed', ['error' => $e->getMessage(), 'driver' => $driver]);
    exit(1);
}
