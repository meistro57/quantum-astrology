<?php // classes/Database/Migrations/002_create_charts_table.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateChartsTable
{
    public static function up(): void
    {
        if (Connection::isMySql()) {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS charts (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    name VARCHAR(191) NOT NULL,
                    chart_type VARCHAR(50) DEFAULT "natal",
                    birth_datetime DATETIME NOT NULL,
                    birth_timezone VARCHAR(64) NOT NULL DEFAULT "UTC",
                    birth_latitude DECIMAL(10,6) NOT NULL,
                    birth_longitude DECIMAL(10,6) NOT NULL,
                    birth_location_name VARCHAR(255) NULL,
                    house_system VARCHAR(10) DEFAULT "P",
                    chart_data LONGTEXT,
                    planetary_positions LONGTEXT,
                    house_positions LONGTEXT,
                    aspects LONGTEXT,
                    calculation_metadata LONGTEXT,
                    is_public TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_charts_user_id (user_id),
                    INDEX idx_charts_chart_type (chart_type),
                    INDEX idx_charts_birth_datetime (birth_datetime),
                    INDEX idx_charts_created_at (created_at),
                    INDEX idx_charts_public (is_public),
                    CONSTRAINT fk_charts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s',
                DB_CHARSET,
                DB_COLLATION
            );
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS charts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    chart_type TEXT DEFAULT 'natal',
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
        }

        Connection::query($sql);

        Connection::query("CREATE INDEX IF NOT EXISTS idx_charts_user_id ON charts(user_id)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_charts_chart_type ON charts(chart_type)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_charts_birth_datetime ON charts(birth_datetime)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_charts_created_at ON charts(created_at)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_charts_public ON charts(is_public)");
    }

    public static function down(): void
    {
        Connection::query("DROP TABLE IF EXISTS charts");
    }
}
