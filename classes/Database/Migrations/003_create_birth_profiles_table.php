<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;
use PDO;

class CreateBirthProfilesTable
{
    public static function up(): void
    {
        $pdo = Connection::getInstance();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');

            $sql = "
                CREATE TABLE IF NOT EXISTS birth_profiles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    birth_datetime TEXT NOT NULL,
                    birth_timezone TEXT NOT NULL DEFAULT 'UTC',
                    birth_latitude REAL NOT NULL,
                    birth_longitude REAL NOT NULL,
                    birth_location_name TEXT,
                    birth_country TEXT,
                    birth_region TEXT,
                    birth_city TEXT,
                    notes TEXT,
                    is_private INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ";
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS birth_profiles (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    birth_datetime DATETIME NOT NULL,
                    birth_timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
                    birth_latitude DECIMAL(10, 7) NOT NULL,
                    birth_longitude DECIMAL(10, 7) NOT NULL,
                    birth_location_name VARCHAR(255),
                    birth_country VARCHAR(100),
                    birth_region VARCHAR(100),
                    birth_city VARCHAR(100),
                    notes TEXT,
                    is_private BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user_id (user_id),
                    INDEX idx_name (name),
                    INDEX idx_birth_datetime (birth_datetime),
                    INDEX idx_location (birth_latitude, birth_longitude)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
        }

        Connection::query($sql);

        // Create indexes for SQLite separately
        if ($driver === 'sqlite') {
            Connection::query("CREATE INDEX IF NOT EXISTS idx_user_id ON birth_profiles(user_id)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_name ON birth_profiles(name)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_birth_datetime ON birth_profiles(birth_datetime)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_location ON birth_profiles(birth_latitude, birth_longitude)");
        }
    }

    public static function down(): void
    {
        $sql = "DROP TABLE IF EXISTS birth_profiles";
        Connection::query($sql);
    }
}