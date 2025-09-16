<?php
// tools/migrate.php (full enough to drop in the new bits)

require __DIR__ . '/../classes/autoload.php';

use QuantumAstrology\Core\DB;

$pdo = DB::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** helpers already in your file — keeping these brief */
function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}
function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}
function has_json(PDO $pdo): bool {
    // crude check for MySQL JSON support
    try { $pdo->query("SELECT JSON_VALID('{}')"); return true; } catch (Throwable $e) { return false; }
}

/** NEW: ensure charts.user_id exists + index */
function ensure_charts_user_id(PDO $pdo): void {
    if (!table_exists($pdo, 'charts')) return;

    if (!column_exists($pdo, 'charts', 'user_id')) {
        echo "• Adding charts.user_id…\n";
        $pdo->exec("ALTER TABLE charts ADD COLUMN user_id INT NULL AFTER id");
        $pdo->exec("ALTER TABLE charts ADD INDEX idx_charts_user_created (user_id, created_at)");
        echo "  ✔ charts.user_id added\n";
    }
}

/** call your existing ensure_* functions here… then: */
ensure_charts_user_id($pdo);

echo "Migrations complete.\n";
