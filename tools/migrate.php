<?php
declare(strict_types=1);

// Load configuration first (defines constants and autoloading)
require __DIR__ . '/../config.php';

use QuantumAstrology\Core\DB;
use QuantumAstrology\Database\Connection;

echo "=== Quantum Astrology Database Migration Tool ===\n\n";

// Display database configuration being used
echo "Database Configuration:\n";
echo "  SQLite Path: " . DB_SQLITE_PATH . "\n";
echo "\n";

try {
    // Get PDO connection (SQLite)
    $pdo = DB::conn();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Enable foreign keys for SQLite
    $pdo->exec('PRAGMA foreign_keys = ON');

    echo "✓ Connected to SQLite database: " . DB_SQLITE_PATH . "\n\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n\n";
    echo "=== Database Setup Required ===\n\n";
    echo "SQLite connection failed. Please check:\n\n";
    echo "1. PHP SQLite extension is installed:\n";
    echo "   sudo apt-get install php-sqlite3\n\n";
    echo "2. Storage directory is writable:\n";
    echo "   chmod 755 storage/\n\n";
    exit(1);
}

/**
 * Check if a table exists in the database
 */
function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Check if a column exists in a table
 */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        if ($col['name'] === $column) {
            return true;
        }
    }
    return false;
}

/**
 * Create migrations tracking table
 */
function ensure_migrations_table(PDO $pdo): void
{
    if (!table_exists($pdo, 'migrations')) {
        echo "• Creating migrations tracking table...\n";
        $pdo->exec("
            CREATE TABLE migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL,
                batch INTEGER NOT NULL DEFAULT 1,
                executed_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "  ✔ Migrations table created\n";
    }
}

/**
 * Check if a migration has been run
 */
function migration_exists(PDO $pdo, string $migration): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM migrations WHERE migration = ?");
    $stmt->execute([$migration]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Record a migration as executed
 */
function record_migration(PDO $pdo, string $migration, int $batch): void
{
    $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
    $stmt->execute([$migration, $batch]);
}

/**
 * Get the next batch number
 */
function get_next_batch(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
    $result = $stmt->fetch();
    return ($result && $result['max_batch']) ? (int)$result['max_batch'] + 1 : 1;
}

// Ensure migrations tracking table exists
ensure_migrations_table($pdo);

// Get next batch number
$batch = get_next_batch($pdo);

// Discover migration files
$migrationsPath = __DIR__ . '/../classes/Database/Migrations';
$migrationFiles = glob($migrationsPath . '/*.php');
sort($migrationFiles);

if (empty($migrationFiles)) {
    echo "⚠ No migration files found in $migrationsPath\n";
    exit(0);
}

echo "Found " . count($migrationFiles) . " migration file(s)\n\n";

$executed = 0;
$skipped = 0;

foreach ($migrationFiles as $file) {
    $filename = basename($file);
    $migrationName = pathinfo($filename, PATHINFO_FILENAME);

    // Check if already executed
    if (migration_exists($pdo, $migrationName)) {
        echo "⊘ Skipping $filename (already executed)\n";
        $skipped++;
        continue;
    }

    echo "→ Running $filename...\n";

    try {
        // Include the migration file
        require_once $file;

        // Convert filename to class name
        // e.g., "001_create_users_table" -> "CreateUsersTable"
        $parts = explode('_', $migrationName);
        array_shift($parts); // Remove numeric prefix
        $className = 'QuantumAstrology\\Database\\Migrations\\' .
                     implode('', array_map('ucfirst', $parts));

        if (!class_exists($className)) {
            throw new Exception("Migration class $className not found");
        }

        // Run the migration's up() method
        $className::up();

        // Record successful migration
        record_migration($pdo, $migrationName, $batch);

        echo "  ✔ $filename completed successfully\n";
        $executed++;

    } catch (Exception $e) {
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
        echo "  Migration stopped at $filename\n";
        exit(1);
    }
}

echo "\n=== Migration Summary ===\n";
echo "Executed: $executed\n";
echo "Skipped: $skipped\n";
echo "Batch: $batch\n";
echo "\n✓ All migrations completed successfully!\n";
