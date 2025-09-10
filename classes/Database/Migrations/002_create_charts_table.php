<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateChartsTable
{
    public static function up(): void
    {
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
        
        Connection::query($sql);
    }

    public static function down(): void
    {
        $sql = "DROP TABLE IF EXISTS charts";
        Connection::query($sql);
    }
}