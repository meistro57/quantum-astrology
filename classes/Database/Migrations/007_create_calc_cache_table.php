<?php // classes/Database/Migrations/007_create_calc_cache_table.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class CreateCalcCacheTable
{
    public static function up(): void
    {
        if (Connection::isMySql()) {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS calc_cache (
                    calc_hash CHAR(40) PRIMARY KEY,
                    planets_json LONGTEXT NOT NULL,
                    houses_json LONGTEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=%s COLLATE=%s',
                DB_CHARSET,
                DB_COLLATION
            );
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS calc_cache (
                    calc_hash TEXT PRIMARY KEY,
                    planets_json TEXT NOT NULL,
                    houses_json TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ";
        }

        Connection::query($sql);
    }

    public static function down(): void
    {
        Connection::query('DROP TABLE IF EXISTS calc_cache');
    }
}
