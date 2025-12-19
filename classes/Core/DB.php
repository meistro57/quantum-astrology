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
        return \QuantumAstrology\Database\Connection::getInstance();
    }
}
