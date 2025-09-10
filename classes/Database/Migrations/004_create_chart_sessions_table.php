<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateChartSessionsTable
{
    public static function up(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS chart_sessions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                chart_id INT UNSIGNED NOT NULL,
                session_data JSON,
                view_settings JSON,
                aspect_settings JSON,
                display_preferences JSON,
                last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (chart_id) REFERENCES charts(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_chart_id (chart_id),
                INDEX idx_last_accessed (last_accessed),
                UNIQUE KEY unique_user_chart (user_id, chart_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        Connection::query($sql);
    }

    public static function down(): void
    {
        $sql = "DROP TABLE IF EXISTS chart_sessions";
        Connection::query($sql);
    }
}