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

$pdo->exec("
CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL UNIQUE,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

function applied(PDO $pdo, string $name): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM migrations WHERE name=?");
    $stmt->execute([$name]);
    return (bool) $stmt->fetchColumn();
}
function mark_applied(PDO $pdo, string $name): void {
    $stmt = $pdo->prepare("INSERT INTO migrations (name) VALUES (?)");
    $stmt->execute([$name]);
}

$migrations = [
    '001_create_users' => "
        CREATE TABLE IF NOT EXISTS users (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          email VARCHAR(191) NOT NULL UNIQUE,
          password_hash VARCHAR(255) NOT NULL,
          name VARCHAR(191) NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    '002_create_charts' => "
        CREATE TABLE IF NOT EXISTS charts (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id BIGINT UNSIGNED NOT NULL,
          name VARCHAR(191) NOT NULL,
          birth_datetime DATETIME NOT NULL,
          birth_timezone VARCHAR(64) NOT NULL,
          birth_latitude DECIMAL(9,6) NOT NULL,
          birth_longitude DECIMAL(9,6) NOT NULL,
          house_system CHAR(2) NOT NULL DEFAULT 'P',
          is_public TINYINT(1) NOT NULL DEFAULT 0,
          planets_json JSON NOT NULL,
          houses_json JSON NOT NULL,
          aspects_json JSON NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_user (user_id),
          CONSTRAINT fk_charts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    '003_create_birth_profiles' => "
        CREATE TABLE IF NOT EXISTS birth_profiles (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id BIGINT UNSIGNED NOT NULL,
          label VARCHAR(191) NOT NULL,
          birth_datetime DATETIME NOT NULL,
          birth_timezone VARCHAR(64) NOT NULL,
          birth_latitude DECIMAL(9,6) NOT NULL,
          birth_longitude DECIMAL(9,6) NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_user (user_id),
          CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    '004_create_chart_sessions' => "
        CREATE TABLE IF NOT EXISTS chart_sessions (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id BIGINT UNSIGNED NOT NULL,
          chart_id BIGINT UNSIGNED NOT NULL,
          prefs_json JSON NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_user (user_id),
          INDEX idx_chart (chart_id),
          CONSTRAINT fk_sessions_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
          CONSTRAINT fk_sessions_chart FOREIGN KEY (chart_id) REFERENCES charts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($migrations as $name => $sql) {
    if (applied($pdo, $name)) {
        echo "✔ $name (already applied)\n";
        continue;
    }
    echo "→ Applying $name...\n";
    $pdo->exec($sql);
    mark_applied($pdo, $name);
    echo "✔ $name (done)\n";
}

echo "✅ Migrations complete.\n";
