<?php
# classes/Core/DB.php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use PDO;
use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        Env::load(__DIR__ . '/../../.env');
        $dsn  = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', 'localhost'),
            Env::get('DB_NAME', 'quantum_astrology')
        );
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('DB connect failed: ' . $e->getMessage(), 0, $e);
        }
        return self::$pdo;
    }
}
