<?php // classes/Database/Migrations/005_add_profile_birth_data_to_users.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class AddProfileBirthDataToUsers
{
    public static function up(): void
    {
        $statements = Connection::isMySql()
            ? [
                'ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_date DATE NULL',
                'ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_time TIME NULL',
                'ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_timezone VARCHAR(64) NULL',
                'ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_city VARCHAR(191) NULL',
                'ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_country VARCHAR(191) NULL',
                'ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_latitude DECIMAL(10,6) NULL',
                'ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_longitude DECIMAL(10,6) NULL',
            ]
            : [
                'ALTER TABLE users ADD COLUMN birth_date TEXT NULL',
                'ALTER TABLE users ADD COLUMN birth_time TEXT NULL',
                'ALTER TABLE users ADD COLUMN birth_timezone TEXT NULL',
                'ALTER TABLE users ADD COLUMN birth_city TEXT NULL',
                'ALTER TABLE users ADD COLUMN birth_country TEXT NULL',
                'ALTER TABLE users ADD COLUMN birth_latitude REAL NULL',
                'ALTER TABLE users ADD COLUMN birth_longitude REAL NULL',
            ];

        foreach ($statements as $statement) {
            Connection::query($statement);
        }
    }

    public static function down(): void
    {
        // Column drops are intentionally omitted to avoid destructive operations in production environments.
    }
}
