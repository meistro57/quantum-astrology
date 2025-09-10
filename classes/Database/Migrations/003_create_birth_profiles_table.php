<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateBirthProfilesTable
{
    public static function up(): void
    {
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
        
        Connection::query($sql);
    }

    public static function down(): void
    {
        $sql = "DROP TABLE IF EXISTS birth_profiles";
        Connection::query($sql);
    }
}