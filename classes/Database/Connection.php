<?php // classes/Database/Connection.php
declare(strict_types=1);

namespace QuantumAstrology\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $driver = self::getDriver();
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                if ($driver === 'mysql') {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                        DB_HOST,
                        DB_PORT,
                        DB_NAME,
                        DB_CHARSET
                    );
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                    self::$instance->exec('SET NAMES ' . DB_CHARSET . ' COLLATE ' . DB_COLLATION);
                } else {
                    // Ensure storage directory exists for SQLite
                    $dbDir = dirname(DB_SQLITE_PATH);
                    if (!is_dir($dbDir)) {
                        mkdir($dbDir, 0755, true);
                    }

                    $dsn = 'sqlite:' . DB_SQLITE_PATH;
                    self::$instance = new PDO($dsn, null, null, $options);
                    self::$instance->exec('PRAGMA foreign_keys = ON');
                }

                self::initializeTables();
            } catch (PDOException $e) {
                error_log(self::getDriver() . " connection failed: " . $e->getMessage());
                throw new PDOException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function getDriver(): string
    {
        return in_array(DB_DRIVER, ['mysql', 'sqlite'], true) ? DB_DRIVER : 'sqlite';
    }

    public static function isMySql(): bool
    {
        return self::getDriver() === 'mysql';
    }

    public static function isSqlite(): bool
    {
        return self::getDriver() === 'sqlite';
    }

    private static function initializeTables(): void
    {
        // Run migrations for both drivers to keep schemas aligned
        Migrator::run();
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }
}
