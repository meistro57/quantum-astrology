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
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage() . ". Falling back to SQLite.");

                try {
                    @mkdir(dirname(DB_SQLITE_PATH), 0755, true);
                    $dsn = 'sqlite:' . DB_SQLITE_PATH;
                    self::$instance = new PDO($dsn, null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);

                    self::initializeSqlite(self::$instance);
                } catch (PDOException $sqliteException) {
                    error_log("SQLite fallback failed: " . $sqliteException->getMessage());
                    throw new PDOException("Database connection failed");
                }
            }
        }

        return self::$instance;
    }

    private static function initializeSqlite(PDO $pdo): void
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
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
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
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
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
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT,
            batch INTEGER
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