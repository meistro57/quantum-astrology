<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;
use PDO;

class CreateUsersTable
{
    public static function up(): void
    {
        $pdo = Connection::getInstance();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    first_name TEXT NULL,
                    last_name TEXT NULL,
                    timezone TEXT DEFAULT 'UTC',
                    email_verified_at TEXT NULL,
                    last_login_at TEXT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ";
        } else {
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
        }

        Connection::query($sql);

        // Create indexes for SQLite separately
        if ($driver === 'sqlite') {
            Connection::query("CREATE INDEX IF NOT EXISTS idx_username ON users(username)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_email ON users(email)");
            Connection::query("CREATE INDEX IF NOT EXISTS idx_created_at ON users(created_at)");
        }
    }

    public static function down(): void
    {
        $sql = "DROP TABLE IF EXISTS users";
        Connection::query($sql);
    }
}