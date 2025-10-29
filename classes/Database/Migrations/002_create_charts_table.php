<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;
use PDO;

class CreateChartsTable
{
    public static function up(): void
    {
        $pdo = Connection::getInstance();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // Enable foreign keys for SQLite
            $pdo->exec('PRAGMA foreign_keys = ON');

            $sql = "
                CREATE TABLE IF NOT EXISTS charts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    chart_type TEXT DEFAULT 'natal' CHECK(chart_type IN ('natal', 'transit', 'progressed', 'solar_return', 'lunar_return', 'composite', 'synastry')),
                    birth_datetime TEXT NOT NULL,
                    birth_timezone TEXT NOT NULL DEFAULT 'UTC',
                    birth_latitude REAL NOT NULL,
                    birth_longitude REAL NOT NULL,
                    birth_location_name TEXT,
                    house_system TEXT DEFAULT 'P',
                    chart_data TEXT,
                    planetary_positions TEXT,
                    house_positions TEXT,
                    aspects TEXT,
                    calculation_metadata TEXT,
                    is_public INTEGER DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ";
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS charts (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    chart_type ENUM('natal', 'transit', 'progressed', 'solar_return', 'lunar_return', 'composite', 'synastry') DEFAULT 'natal',
                    birth_datetime DATETIME NOT NULL,
                    birth_timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
                    birth_latitude DECIMAL(10, 7) NOT NULL,
                    birth_longitude DECIMAL(10, 7) NOT NULL,
                    birth_location_name VARCHAR(255),
                    house_system CHAR(1) DEFAULT 'P',
                    chart_data JSON,
                    planetary_positions JSON,
                    house_positions JSON,
                    aspects JSON,
                    calculation_metadata JSON,
                    is_public BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user_id (user_id),
                    INDEX idx_chart_type (chart_type),
                    INDEX idx_birth_datetime (birth_datetime),
                    INDEX idx_created_at (created_at),
                    INDEX idx_public (is_public)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
        }

        Connection::query($sql);

        // Create indexes for SQLite separately
        if ($driver === 'sqlite') {
            Connection::query("CREATE INDEX IF NOT EXISTS idx_user_id ON charts(user_id)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_chart_type ON charts(chart_type)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_birth_datetime ON charts(birth_datetime)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_created_at ON charts(created_at)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_public ON charts(is_public)");
        }
    }

    public static function down(): void
    {
        $sql = "DROP TABLE IF EXISTS charts";
        Connection::query($sql);
    }
}