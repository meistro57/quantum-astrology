<?php // classes/Database/Migrations/006_seed_default_admin_user.php
declare(strict_types=1);

namespace QuantumAstrology\Database\Migrations;

use QuantumAstrology\Database\Connection;

class SeedDefaultAdminUser
{
    public static function up(): void
    {
        $existing = Connection::fetchOne(
            'SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1',
            [
                'username' => 'admin',
                'email' => 'admin@local.test',
            ]
        );

        if ($existing) {
            return;
        }

        Connection::query(
            'INSERT INTO users (username, email, password_hash, first_name, last_name, timezone, created_at, updated_at)
             VALUES (:username, :email, :password_hash, :first_name, :last_name, :timezone, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                'username' => 'admin',
                'email' => 'admin@local.test',
                'password_hash' => password_hash('password', PASSWORD_DEFAULT),
                'first_name' => 'Admin',
                'last_name' => 'User',
                'timezone' => 'UTC',
            ]
        );
    }

    public static function down(): void
    {
        Connection::query(
            'DELETE FROM users WHERE username = :username AND email = :email',
            [
                'username' => 'admin',
                'email' => 'admin@local.test',
            ]
        );
    }
}
