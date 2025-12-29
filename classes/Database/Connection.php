<?php
declare(strict_types=1);

namespace QuantumAstrology\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                // Ensure storage directory exists
                $dbDir = dirname(DB_SQLITE_PATH);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }

                $dsn = 'sqlite:' . DB_SQLITE_PATH;
                self::$instance = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // Enable foreign keys for SQLite
                self::$instance->exec('PRAGMA foreign_keys = ON');

                // Initialize tables if needed (for fresh installs)
                self::initializeTables(self::$instance);
            } catch (PDOException $e) {
                error_log("SQLite connection failed: " . $e->getMessage());
                throw new PDOException("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private static function initializeTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            email TEXT UNIQUE,
            password_hash TEXT,
            first_name TEXT,
            last_name TEXT,
            timezone TEXT DEFAULT "UTC",
            email_verified_at TEXT NULL,
            last_login_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS charts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            chart_type TEXT DEFAULT "natal",
            birth_datetime TEXT NOT NULL,
            birth_timezone TEXT NOT NULL DEFAULT "UTC",
            birth_latitude REAL NOT NULL,
            birth_longitude REAL NOT NULL,
            birth_location_name TEXT,
            house_system TEXT DEFAULT "P",
            chart_data TEXT,
            planetary_positions TEXT,
            house_positions TEXT,
            aspects TEXT,
            calculation_metadata TEXT,
            is_public INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS birth_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            birth_datetime TEXT NOT NULL,
            birth_timezone TEXT NOT NULL DEFAULT "UTC",
            birth_latitude REAL NOT NULL,
            birth_longitude REAL NOT NULL,
            birth_location_name TEXT,
            notes TEXT,
            is_private INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS chart_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            chart_id INTEGER NOT NULL,
            session_data TEXT,
            view_settings TEXT,
            aspect_settings TEXT,
            display_preferences TEXT,
            last_accessed TEXT DEFAULT CURRENT_TIMESTAMP,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (chart_id) REFERENCES charts(id) ON DELETE CASCADE,
            UNIQUE(user_id, chart_id)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT NOT NULL,
            batch INTEGER NOT NULL
        )');
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }
}
