<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateChartSessionsTable
{
    public static function up(): void
    {
        $pdo = Connection::getInstance();
        $pdo->exec('PRAGMA foreign_keys = ON');

        $sql = "
            CREATE TABLE IF NOT EXISTS chart_sessions (
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
            )
        ";

        Connection::query($sql);

        // Create indexes
        Connection::query("CREATE INDEX IF NOT EXISTS idx_chart_sessions_user_id ON chart_sessions(user_id)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_chart_sessions_chart_id ON chart_sessions(chart_id)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_chart_sessions_last_accessed ON chart_sessions(last_accessed)");
    }

    public static function down(): void
    {
        Connection::query("DROP TABLE IF EXISTS chart_sessions");
    }
}
