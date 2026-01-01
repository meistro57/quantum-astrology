<?php
# classes/Database/Migrations/005_add_profile_birth_data_to_users.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use PDO;
use QuantumAstrology\Database\Connection;

class AddProfileBirthDataToUsers
{
    public static function up(): void
    {
        $pdo = Connection::getInstance();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $columns = [
            'birth_date' => 'TEXT NULL',
            'birth_time' => 'TEXT NULL',
            'birth_timezone' => "TEXT NULL",
            'birth_latitude' => 'REAL NULL',
            'birth_longitude' => 'REAL NULL',
            'birth_location_name' => 'TEXT NULL'
        ];

        foreach ($columns as $name => $definition) {
            if (self::columnExists($pdo, 'users', $name)) {
                continue;
            }

            $sql = "ALTER TABLE users ADD COLUMN {$name} {$definition}";
            // MySQL supports IF NOT EXISTS; SQLite ignores it pre-3.35.0, so check first.
            if ($driver === 'mysql') {
                $sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS {$name} {$definition}";
            }

            Connection::query($sql);
        }
    }

    public static function down(): void
    {
        // Dropping columns in SQLite requires table recreation; omit for safety.
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("PRAGMA table_info({$table})");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $stmt->execute(['column' => $column]);
        return (bool) $stmt->fetchColumn();
    }
}
