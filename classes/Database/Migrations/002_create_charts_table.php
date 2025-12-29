<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateChartsTable
{
    public static function up(): void
    {
        $pdo = Connection::getInstance();

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

        Connection::query($sql);

        // Create indexes
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
