<?php // classes/Database/Migrations/001_create_users_table.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateUsersTable
{
    public static function up(): void
    {
        if (Connection::isMySql()) {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS users (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(191) NOT NULL UNIQUE,
                    email VARCHAR(191) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    first_name VARCHAR(100) NULL,
                    last_name VARCHAR(100) NULL,
                    timezone VARCHAR(64) DEFAULT "UTC",
                    email_verified_at DATETIME NULL,
                    last_login_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s',
                DB_CHARSET,
                DB_COLLATION
            );
        } else {
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
        }

        Connection::query($sql);

        Connection::query("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at)");
    }

    public static function down(): void
    {
        Connection::query("DROP TABLE IF EXISTS users");
    }
}
