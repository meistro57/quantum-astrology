<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Core/Logger.php';

use QuantumAstrology\Core\Logger;

echo "Backing up Quantum Astrology database...\n";

$backupDir = STORAGE_PATH . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$filename = sprintf('%s/backup_%s.sql', $backupDir, date('Ymd_His'));

$command = sprintf(
    'mysqldump --host=%s --port=%d --user=%s --password=%s %s > %s',
    escapeshellarg(DB_HOST),
    DB_PORT,
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    escapeshellarg($filename)
);

$exitCode = 0;
system($command, $exitCode);

if ($exitCode === 0) {
    echo "Backup created at {$filename}\n";
    Logger::info('Database backup created', ['file' => $filename]);
} else {
    echo "Database backup failed\n";
    Logger::error('Database backup failed', ['command' => $command, 'exit_code' => $exitCode]);
    exit(1);
}
