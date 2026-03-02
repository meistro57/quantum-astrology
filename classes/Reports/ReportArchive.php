<?php
declare(strict_types=1);

namespace QuantumAstrology\Reports;

use QuantumAstrology\Core\DB;
use RuntimeException;

final class ReportArchive
{
    private const TABLE = 'report_archives';
    private static bool $tableEnsured = false;

    /**
     * @return array{id:int,file_path:string,file_name:string,file_size:int,created_at:string}
     */
    public static function save(
        int $userId,
        int $chartId,
        string $reportType,
        string $kind,
        string $extension,
        string $mimeType,
        string $content
    ): array {
        self::ensureTable();

        $baseDir = ROOT_PATH . '/storage/reports/' . $userId;
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new RuntimeException('Unable to create report storage directory.');
        }

        $safeKind = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($kind)) ?: 'report';
        $safeType = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($reportType)) ?: 'natal';
        $ext = ltrim(strtolower($extension), '.');
        if ($ext === '') {
            $ext = 'bin';
        }

        $fileName = sprintf(
            '%s_%s_chart_%d_%s.%s',
            $safeKind,
            $safeType,
            $chartId,
            date('Ymd_His'),
            $ext
        );

        $absPath = $baseDir . '/' . $fileName;
        if (@file_put_contents($absPath, $content) === false) {
            throw new RuntimeException('Unable to write report file to storage.');
        }

        @chmod($absPath, 0664);
        $relPath = 'storage/reports/' . $userId . '/' . $fileName;
        $fileSize = (int) filesize($absPath);

        $pdo = DB::conn();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (user_id, chart_id, report_type, kind, mime_type, file_path, file_name, file_size, created_at)
             VALUES (:user_id, :chart_id, :report_type, :kind, :mime_type, :file_path, :file_name, :file_size, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':chart_id' => $chartId,
            ':report_type' => $reportType,
            ':kind' => $kind,
            ':mime_type' => $mimeType,
            ':file_path' => $relPath,
            ':file_name' => $fileName,
            ':file_size' => $fileSize,
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'file_path' => $relPath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'created_at' => date('c'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listByUser(int $userId, int $limit = 50): array
    {
        self::ensureTable();
        $limit = max(1, min(200, $limit));
        $pdo = DB::conn();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, chart_id, report_type, kind, mime_type, file_name, file_size, created_at
             FROM ' . self::TABLE . '
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findByIdForUser(int $id, int $userId): ?array
    {
        self::ensureTable();
        $pdo = DB::conn();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, chart_id, report_type, kind, mime_type, file_path, file_name, file_size, created_at
             FROM ' . self::TABLE . '
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $pdo = DB::conn();
        $driver = strtolower((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        if ($driver === 'mysql') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    chart_id BIGINT UNSIGNED NOT NULL,
                    report_type VARCHAR(32) NOT NULL,
                    kind VARCHAR(32) NOT NULL,
                    mime_type VARCHAR(128) NOT NULL,
                    file_path VARCHAR(512) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_size BIGINT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_user_created (user_id, created_at),
                    KEY idx_chart_created (chart_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    chart_id INTEGER NOT NULL,
                    report_type TEXT NOT NULL,
                    kind TEXT NOT NULL,
                    mime_type TEXT NOT NULL,
                    file_path TEXT NOT NULL,
                    file_name TEXT NOT NULL,
                    file_size INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_archives_user_created ON ' . self::TABLE . ' (user_id, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_archives_chart_created ON ' . self::TABLE . ' (chart_id, created_at)');
        }

        self::$tableEnsured = true;
    }
}
