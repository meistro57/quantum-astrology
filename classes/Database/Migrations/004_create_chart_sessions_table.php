<?php // classes/Database/Migrations/004_create_chart_sessions_table.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateChartSessionsTable
{
    public static function up(): void
    {
        if (Connection::isMySql()) {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS chart_sessions (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    chart_id INT UNSIGNED NOT NULL,
                    session_data LONGTEXT,
                    view_settings LONGTEXT,
                    aspect_settings LONGTEXT,
                    display_preferences LONGTEXT,
                    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_chart_sessions_user_chart (user_id, chart_id),
                    INDEX idx_chart_sessions_user_id (user_id),
                    INDEX idx_chart_sessions_chart_id (chart_id),
                    INDEX idx_chart_sessions_last_accessed (last_accessed),
                    CONSTRAINT fk_chart_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_chart_sessions_chart FOREIGN KEY (chart_id) REFERENCES charts(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s',
                DB_CHARSET,
                DB_COLLATION
            );
        } else {
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
        }

        Connection::query($sql);

        Connection::query("CREATE INDEX IF NOT EXISTS idx_chart_sessions_user_id ON chart_sessions(user_id)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_chart_sessions_chart_id ON chart_sessions(chart_id)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_chart_sessions_last_accessed ON chart_sessions(last_accessed)");
    }

    public static function down(): void
    {
        Connection::query("DROP TABLE IF EXISTS chart_sessions");
    }
}
