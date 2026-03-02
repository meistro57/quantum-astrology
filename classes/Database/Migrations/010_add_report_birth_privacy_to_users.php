<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class AddReportBirthPrivacyToUsers
{
    public static function up(): void
    {
        if (Connection::isMySql()) {
            Connection::query('ALTER TABLE users ADD COLUMN IF NOT EXISTS show_birth_date_in_reports TINYINT(1) NOT NULL DEFAULT 1');
            Connection::query('ALTER TABLE users ADD COLUMN IF NOT EXISTS show_birth_location_in_reports TINYINT(1) NOT NULL DEFAULT 1');
            return;
        }

        $columns = Connection::fetchAll('PRAGMA table_info(users)');
        $hasDateFlag = false;
        $hasLocationFlag = false;

        foreach ($columns as $column) {
            $name = (string)($column['name'] ?? '');
            if ($name === 'show_birth_date_in_reports') {
                $hasDateFlag = true;
            }
            if ($name === 'show_birth_location_in_reports') {
                $hasLocationFlag = true;
            }
        }

        if (!$hasDateFlag) {
            Connection::query('ALTER TABLE users ADD COLUMN show_birth_date_in_reports INTEGER NOT NULL DEFAULT 1');
        }
        if (!$hasLocationFlag) {
            Connection::query('ALTER TABLE users ADD COLUMN show_birth_location_in_reports INTEGER NOT NULL DEFAULT 1');
        }
    }

    public static function down(): void
    {
        // Non-destructive rollback intentionally omitted.
    }
}

