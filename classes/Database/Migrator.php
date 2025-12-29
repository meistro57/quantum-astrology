<?php
declare(strict_types=1);

namespace QuantumAstrology\Database;

use QuantumAstrology\Database\Connection;
use PDOException;

class Migrator
{
    private const MIGRATIONS_TABLE = 'migrations';

    public static function run(): void
    {
        self::createMigrationsTable();
        $migrations = self::getPendingMigrations();

        foreach ($migrations as $migration) {
            self::runMigration($migration);
        }
    }

    private static function createMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS " . self::MIGRATIONS_TABLE . " (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                executed_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ";

        Connection::query($sql);
    }

    private static function getPendingMigrations(): array
    {
        $migrationFiles = glob(__DIR__ . '/Migrations/*.php');
        $executedMigrations = self::getExecutedMigrations();
        $pending = [];

        foreach ($migrationFiles as $file) {
            $migrationName = basename($file, '.php');
            if (!in_array($migrationName, $executedMigrations)) {
                $pending[] = $migrationName;
            }
        }

        sort($pending);
        return $pending;
    }

    private static function getExecutedMigrations(): array
    {
        try {
            $sql = "SELECT migration FROM " . self::MIGRATIONS_TABLE . " ORDER BY migration";
            $result = Connection::fetchAll($sql);
            return array_column($result, 'migration');
        } catch (PDOException $e) {
            return [];
        }
    }

    private static function runMigration(string $migrationName): void
    {
        try {
            require_once __DIR__ . '/Migrations/' . $migrationName . '.php';

            $className = self::getClassNameFromFile($migrationName);
            $fullClassName = "QuantumAstrology\\Database\\Migrations\\{$className}";

            if (class_exists($fullClassName) && method_exists($fullClassName, 'up')) {
                $fullClassName::up();
                self::markAsExecuted($migrationName);
                echo "Executed migration: {$migrationName}\n";
            } else {
                echo "Warning: Migration class not found or missing 'up' method: {$migrationName}\n";
            }
        } catch (\Exception $e) {
            echo "Error executing migration {$migrationName}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private static function getClassNameFromFile(string $filename): string
    {
        $parts = explode('_', $filename, 2);
        if (count($parts) >= 2) {
            $className = str_replace('_', '', ucwords($parts[1], '_'));
            return $className;
        }
        return ucfirst($filename);
    }

    private static function markAsExecuted(string $migrationName): void
    {
        $sql = "INSERT INTO " . self::MIGRATIONS_TABLE . " (migration) VALUES (:migration)";
        Connection::query($sql, ['migration' => $migrationName]);
    }
}
