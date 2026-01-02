<?php // classes/Database/Migrations/003_create_birth_profiles_table.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateBirthProfilesTable
{
    public static function up(): void
    {
        if (Connection::isMySql()) {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS birth_profiles (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    name VARCHAR(191) NOT NULL,
                    birth_datetime DATETIME NOT NULL,
                    birth_timezone VARCHAR(64) NOT NULL DEFAULT "UTC",
                    birth_latitude DECIMAL(10,6) NOT NULL,
                    birth_longitude DECIMAL(10,6) NOT NULL,
                    birth_location_name VARCHAR(255) NULL,
                    notes LONGTEXT,
                    is_private TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_birth_profiles_user_id (user_id),
                    INDEX idx_birth_profiles_name (name),
                    INDEX idx_birth_profiles_birth_datetime (birth_datetime),
                    INDEX idx_birth_profiles_location (birth_latitude, birth_longitude),
                    CONSTRAINT fk_birth_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s',
                DB_CHARSET,
                DB_COLLATION
            );
        } else {
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
                    notes TEXT,
                    is_private INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ";
        }

        Connection::query($sql);

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
