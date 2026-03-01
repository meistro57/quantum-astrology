<?php // classes/Database/Migrations/008_add_birth_location_name_to_users.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class AddBirthLocationNameToUsers
{
    public static function up(): void
    {
        if (Connection::isMySql()) {
            Connection::query('ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_location_name VARCHAR(255) NULL');
            return;
        }

        Connection::query('ALTER TABLE users ADD COLUMN birth_location_name TEXT NULL');
    }

    public static function down(): void
    {
        // Non-destructive migration rollback intentionally omitted.
    }
}
