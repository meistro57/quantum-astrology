<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Core/Logger.php';

use QuantumAstrology\Core\Logger;

echo "Clearing Quantum Astrology cache...\n";

$cacheDir = STORAGE_PATH . '/cache';
$cleared = 0;

if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $cleared++;
        }
    }
}

echo "Cleared {$cleared} cache files.\n";
Logger::info("Cache cleared", ['files_cleared' => $cleared]);