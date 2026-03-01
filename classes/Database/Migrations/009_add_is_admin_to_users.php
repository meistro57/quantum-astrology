<?php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class AddIsAdminToUsers
{
    public static function up(): void
    {
        if (Connection::isMySql()) {
            Connection::query('ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0');
            return;
        }

        // SQLite: add column if not present.
        $columns = Connection::fetchAll('PRAGMA table_info(users)');
        $hasIsAdmin = false;
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'is_admin') {
                $hasIsAdmin = true;
                break;
            }
        }

        if (!$hasIsAdmin) {
            Connection::query('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
        }
    }

    public static function down(): void
    {
        // Non-destructive rollback intentionally omitted.
    }
}

