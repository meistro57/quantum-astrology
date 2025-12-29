<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Core/Logger.php';

use QuantumAstrology\Core\Logger;

echo "Backing up Quantum Astrology SQLite database...\n";

$backupDir = STORAGE_PATH . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$sourceDb = DB_SQLITE_PATH;
$backupFile = sprintf('%s/backup_%s.sqlite', $backupDir, date('Ymd_His'));

if (!file_exists($sourceDb)) {
    echo "Source database not found: {$sourceDb}\n";
    Logger::error('Database backup failed: source not found', ['file' => $sourceDb]);
    exit(1);
}

// Copy the SQLite database file
if (copy($sourceDb, $backupFile)) {
    echo "Backup created at {$backupFile}\n";

    // Also create a SQL dump for portability
    $sqlDumpFile = sprintf('%s/backup_%s.sql', $backupDir, date('Ymd_His'));

    try {
        $pdo = new PDO('sqlite:' . $sourceDb);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $dump = "-- Quantum Astrology SQLite Backup\n";
        $dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";

        // Get all tables
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Get CREATE statement
            $createStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();
            $dump .= "-- Table: $table\n";
            $dump .= "$createStmt;\n\n";

            // Get data
            $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote((string)$v);
                }, array_values($row));

                $dump .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            $dump .= "\n";
        }

        file_put_contents($sqlDumpFile, $dump);
        echo "SQL dump created at {$sqlDumpFile}\n";

    } catch (PDOException $e) {
        echo "Warning: Could not create SQL dump: " . $e->getMessage() . "\n";
    }

    Logger::info('Database backup created', ['file' => $backupFile]);
} else {
    echo "Database backup failed\n";
    Logger::error('Database backup failed', ['source' => $sourceDb, 'destination' => $backupFile]);
    exit(1);
}
