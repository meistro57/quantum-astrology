<?php
# tools/migrate.php
declare(strict_types=1);

use QuantumAstrology\Core\Env;

require __DIR__ . '/../classes/Core/Env.php';
Env::load(__DIR__ . '/../.env');

$dsn  = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', Env::get('DB_HOST', 'localhost'), Env::get('DB_NAME', 'quantum_astrology'));
$user = Env::get('DB_USER', 'root');
$pass = Env::get('DB_PASS', '');

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connect failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

/* ---------------- helpers ---------------- */

function table_exists(PDO $pdo, string $table): bool {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

function col_exists(PDO $pdo, string $table, string $col): bool {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $q->execute([$table, $col]);
    return (bool)$q->fetchColumn();
}

function index_exists(PDO $pdo, string $table, string $index): bool {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
    $q->execute([$table, $index]);
    return (bool)$q->fetchColumn();
}

function has_json(PDO $pdo): bool {
    try {
        $pdo->query("SELECT JSON_VALID('{}')"); // MySQL 5.7+/MariaDB 10.2.7+
        return true;
    } catch (Throwable $e) { return false; }
}

function add_column_json(PDO $pdo, string $table, string $col, bool $notNull = true): void {
    if (col_exists($pdo, $table, $col)) return;
    $type = has_json($pdo) ? 'JSON' : 'LONGTEXT';
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $type NULL");
    // backfill empty arrays/objects
    $empty = ($col === 'planets_json' || $col === 'aspects_json' || $col === 'houses_json') ? '[]' : '{}';
    $stmt = $pdo->prepare("UPDATE `$table` SET `$col` = :v WHERE `$col` IS NULL");
    $stmt->execute([':v' => $empty]);
    if ($notNull) {
        try { $pdo->exec("ALTER TABLE `$table` MODIFY `$col` $type NOT NULL"); } catch (Throwable $e) { /* some MariaDBs dislike NOT NULL JSON; ignore */ }
    }
}

function drop_foreign_keys(PDO $pdo, string $table): void {
    // make it tolerant: if none exist, nothing happens
    $sql = "SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_type = 'FOREIGN KEY'";
    $q = $pdo->prepare($sql); $q->execute([$table]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $fk) {
        $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$fk`");
    }
}

/* ------------- ensure schemas ------------- */

function ensure_migrations(PDO $pdo): void {
    if (!table_exists($pdo, 'migrations')) {
        $pdo->exec("
            CREATE TABLE migrations (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(191) NOT NULL UNIQUE,
              applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "✔ created migrations\n";
        return;
    }
    // heal columns if older
    if (!col_exists($pdo, 'migrations', 'name')) {
        if (col_exists($pdo, 'migrations', 'migration')) {
            $pdo->exec("ALTER TABLE migrations CHANGE migration name VARCHAR(191) NOT NULL");
            echo "↺ migrations: renamed migration→name\n";
        } elseif (col_exists($pdo, 'migrations', 'filename')) {
            $pdo->exec("ALTER TABLE migrations CHANGE filename name VARCHAR(191) NOT NULL");
            echo "↺ migrations: renamed filename→name\n";
        } else {
            $pdo->exec("ALTER TABLE migrations ADD COLUMN name VARCHAR(191) NOT NULL");
            echo "↺ migrations: added name\n";
        }
    }
    if (!col_exists($pdo, 'migrations', 'applied_at')) {
        $pdo->exec("ALTER TABLE migrations ADD COLUMN applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        echo "↺ migrations: added applied_at\n";
    }
    if (!index_exists($pdo, 'migrations', 'uniq_migrations_name')) {
        try { $pdo->exec("CREATE UNIQUE INDEX uniq_migrations_name ON migrations (name)"); } catch (Throwable $e) {}
    }
}

function ensure_users(PDO $pdo): void {
    if (!table_exists($pdo, 'users')) {
        $pdo->exec("
            CREATE TABLE users (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              email VARCHAR(191) NOT NULL UNIQUE,
              password_hash VARCHAR(255) NOT NULL,
              name VARCHAR(191) NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "✔ created users\n";
    }
}

function ensure_charts(PDO $pdo): void {
    if (!table_exists($pdo, 'charts')) {
        $pdo->exec("
            CREATE TABLE charts (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id BIGINT UNSIGNED NULL,
              name VARCHAR(191) NOT NULL,
              birth_datetime DATETIME NOT NULL,
              birth_timezone VARCHAR(64) NOT NULL,
              birth_latitude DECIMAL(9,6) NOT NULL,
              birth_longitude DECIMAL(9,6) NOT NULL,
              house_system CHAR(2) NOT NULL DEFAULT 'P',
              is_public TINYINT(1) NOT NULL DEFAULT 0,
              planets_json JSON NOT NULL,
              houses_json  JSON NOT NULL,
              aspects_json JSON NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        // If JSON unsupported, alter columns to LONGTEXT silently
        if (!has_json($pdo)) {
            $pdo->exec("ALTER TABLE charts MODIFY planets_json LONGTEXT NOT NULL");
            $pdo->exec("ALTER TABLE charts MODIFY houses_json  LONGTEXT NOT NULL");
            $pdo->exec("ALTER TABLE charts MODIFY aspects_json LONGTEXT NOT NULL");
        }
        echo "✔ created charts\n";
        return;
    }

    // Heal legacy schemas
    // user_id nullable + drop any FKs for now (until auth lands)
    try { $pdo->exec("ALTER TABLE charts MODIFY user_id BIGINT UNSIGNED NULL"); } catch (Throwable $e) {}
    drop_foreign_keys($pdo, 'charts');

    // Required columns
    foreach ([
        ['name',            'VARCHAR(191) NOT NULL'],
        ['birth_datetime',  'DATETIME NOT NULL'],
        ['birth_timezone',  "VARCHAR(64) NOT NULL"],
        ['birth_latitude',  'DECIMAL(9,6) NOT NULL'],
        ['birth_longitude', 'DECIMAL(9,6) NOT NULL'],
        ['house_system',    "CHAR(2) NOT NULL DEFAULT 'P'"],
        ['is_public',       'TINYINT(1) NOT NULL DEFAULT 0'],
        ['created_at',      'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['updated_at',      'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
    ] as [$col, $def]) {
        if (!col_exists($pdo, 'charts', $col)) {
            $pdo->exec("ALTER TABLE charts ADD COLUMN `$col` $def");
            echo "↺ charts: added $col\n";
        }
    }

    // JSON-ish columns
    add_column_json($pdo, 'charts', 'planets_json', true);
    add_column_json($pdo, 'charts', 'houses_json',  true);
    add_column_json($pdo, 'charts', 'aspects_json', true);

    if (!index_exists($pdo, 'charts', 'idx_user')) {
        try { $pdo->exec("CREATE INDEX idx_user ON charts (user_id)"); } catch (Throwable $e) {}
    }
}
function ensure_calc_cache(PDO $pdo): void {
    if (!table_exists($pdo, 'calc_cache')) {
        $pdo->exec("
            CREATE TABLE calc_cache (
              calc_hash CHAR(40) PRIMARY KEY,
              planets_json JSON NOT NULL,
              houses_json  JSON NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        if (!has_json($pdo)) {
            $pdo->exec("ALTER TABLE calc_cache MODIFY planets_json LONGTEXT NOT NULL");
            $pdo->exec("ALTER TABLE calc_cache MODIFY houses_json  LONGTEXT NOT NULL");
        }
        echo "✔ created calc_cache\n";
    }
}

/* --------------- run “migrations” --------------- */

ensure_migrations($pdo);
ensure_users($pdo);
ensure_charts($pdo);
ensure_calc_cache($pdo);

echo "✅ Schema is up to date.\n";
