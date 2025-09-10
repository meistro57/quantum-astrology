<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateUsersTable
{
    public static function up(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NULL,
                last_name VARCHAR(100) NULL,
                timezone VARCHAR(50) DEFAULT 'UTC',
                email_verified_at TIMESTAMP NULL,
                last_login_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        Connection::query($sql);
    }

    public static function down(): void
    {
        $sql = "DROP TABLE IF EXISTS users";
        Connection::query($sql);
    }
}