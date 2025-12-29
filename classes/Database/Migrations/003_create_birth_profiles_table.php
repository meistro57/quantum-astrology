<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateBirthProfilesTable
{
    public static function up(): void
    {
        $pdo = Connection::getInstance();
        $pdo->exec('PRAGMA foreign_keys = ON');

        $sql = "
            CREATE TABLE IF NOT EXISTS birth_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                birth_datetime TEXT NOT NULL,
                birth_timezone TEXT NOT NULL DEFAULT 'UTC',
                birth_latitude REAL NOT NULL,
                birth_longitude REAL NOT NULL,
                birth_location_name TEXT,
                birth_country TEXT,
                birth_region TEXT,
                birth_city TEXT,
                notes TEXT,
                is_private INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";

        Connection::query($sql);

        // Create indexes
        Connection::query("CREATE INDEX IF NOT EXISTS idx_birth_profiles_user_id ON birth_profiles(user_id)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_birth_profiles_name ON birth_profiles(name)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_birth_profiles_birth_datetime ON birth_profiles(birth_datetime)");
        Connection::query("CREATE INDEX IF NOT EXISTS idx_birth_profiles_location ON birth_profiles(birth_latitude, birth_longitude)");
    }

    public static function down(): void
    {
        Connection::query("DROP TABLE IF EXISTS birth_profiles");
    }
}
