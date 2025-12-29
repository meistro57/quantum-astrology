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

        Connection::query($sql);

        // Create indexes
        Connection::query("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at)");
    }

    public static function down(): void
    {
        Connection::query("DROP TABLE IF EXISTS users");
    }
}
