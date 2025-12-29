<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database/Connection.php';
require_once __DIR__ . '/../classes/Core/Logger.php';

use QuantumAstrology\Database\Connection;
use QuantumAstrology\Core\Logger;

echo "Setting up Quantum Astrology SQLite database...\n";

try {
    // Ensure storage directory exists
    $dbDir = dirname(DB_SQLITE_PATH);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
        echo "Created storage directory: {$dbDir}\n";
    }

    // Initialize the database connection (this creates the database file if needed)
    $pdo = Connection::getInstance();
    echo "SQLite database initialized at: " . DB_SQLITE_PATH . "\n";

    // Run migrations
    echo "\nRunning migrations...\n";
    require_once __DIR__ . '/migrate.php';

    echo "\nDatabase setup complete!\n";
    Logger::info("Database setup completed successfully");

} catch (PDOException $e) {
    echo "Database setup failed: " . $e->getMessage() . "\n";
    Logger::error("Database setup failed", ['error' => $e->getMessage()]);
    exit(1);
}
