<?php
declare(strict_types=1);

// Load configuration first (defines constants and autoloading)
require __DIR__ . '/../config.php';

use QuantumAstrology\Core\DB;
use QuantumAstrology\Database\Connection;

echo "=== Quantum Astrology Database Migration Tool ===\n\n";

// Display database configuration being used
echo "Database Configuration:\n";
echo "  Host: " . DB_HOST . "\n";
echo "  Port: " . DB_PORT . "\n";
echo "  Database: " . DB_NAME . "\n";
echo "  User: " . DB_USER . "\n";
echo "  SQLite Path: " . DB_SQLITE_PATH . "\n";
echo "\n";

try {
    // Get PDO connection (may be MySQL or SQLite via automatic fallback)
    $pdo = DB::conn();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Detect actual driver to use correct SQL syntax
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isSqlite = ($driver === 'sqlite');

    if ($isSqlite) {
        echo "✓ Connected to SQLite database: " . DB_SQLITE_PATH . "\n\n";
    } else {
        echo "✓ Connected to MySQL database\n\n";
    }
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n\n";
    echo "=== Database Setup Required ===\n\n";
    echo "Neither MySQL nor SQLite is properly configured. Please choose one option:\n\n";
    echo "Option 1: Fix MySQL Connection\n";
    echo "  If you're using MySQL with unix_socket auth (common on Ubuntu/Debian):\n";
    echo "  1. Connect to MySQL as root: sudo mysql -u root\n";
    echo "  2. Create database: CREATE DATABASE quantum_astrology;\n";
    echo "  3. Create user: CREATE USER 'qauser'@'localhost' IDENTIFIED BY 'password';\n";
    echo "  4. Grant privileges: GRANT ALL ON quantum_astrology.* TO 'qauser'@'localhost';\n";
    echo "  5. Update .env with new credentials:\n";
    echo "     DB_USER=qauser\n";
    echo "     DB_PASS=password\n\n";
    echo "Option 2: Install SQLite\n";
    echo "  1. Install PHP SQLite: sudo apt-get install php-sqlite3\n";
    echo "  2. Restart PHP: sudo service php*-fpm restart (if using FPM)\n\n";
    echo "Option 3: Use existing MySQL with password\n";
    echo "  If you have MySQL credentials that work, update your .env file:\n";
    echo "     DB_HOST=localhost\n";
    echo "     DB_USER=your_username\n";
    echo "     DB_PASS=your_password\n\n";
    exit(1);
}

/**
 * Check if a table exists in the database
 */
function table_exists(PDO $pdo, string $table, bool $isSqlite): bool
{
    if ($isSqlite) {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }
}

/**
 * Check if a column exists in a table
 */
function column_exists(PDO $pdo, string $table, string $column, bool $isSqlite): bool
{
    if ($isSqlite) {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            if ($col['name'] === $column) {
                return true;
            }
        }
        return false;
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
}

/**
 * Create migrations tracking table
 */
function ensure_migrations_table(PDO $pdo, bool $isSqlite): void
{
    if (!table_exists($pdo, 'migrations', $isSqlite)) {
        echo "• Creating migrations tracking table...\n";
        if ($isSqlite) {
            $pdo->exec("
                CREATE TABLE migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL,
                    batch INTEGER NOT NULL DEFAULT 1,
                    executed_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            $pdo->exec("
                CREATE TABLE migrations (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL DEFAULT 1,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_migration (migration)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
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
ensure_migrations_table($pdo, $isSqlite);

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
