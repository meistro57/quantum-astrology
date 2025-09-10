<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database/Connection.php';
require_once __DIR__ . '/../classes/Core/Logger.php';

use QuantumAstrology\Database\Connection;
use QuantumAstrology\Core\Logger;

echo "Setting up Quantum Astrology database...\n";

try {
    // Create database if it doesn't exist
    $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`");
    echo "Database created or already exists.\n";
    
    // Switch to our database
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // Run migration files
    $migrationPath = __DIR__ . '/../classes/Database/Migrations';
    if (is_dir($migrationPath)) {
        $migrations = glob($migrationPath . '/*.sql');
        sort($migrations);
        
        foreach ($migrations as $migration) {
            $sql = file_get_contents($migration);
            $filename = basename($migration);
            
            try {
                $pdo->exec($sql);
                echo "Applied migration: {$filename}\n";
                Logger::info("Applied database migration", ['file' => $filename]);
            } catch (PDOException $e) {
                echo "Error applying migration {$filename}: " . $e->getMessage() . "\n";
                Logger::error("Migration failed", ['file' => $filename, 'error' => $e->getMessage()]);
            }
        }
    }
    
    echo "Database setup complete!\n";
    Logger::info("Database setup completed successfully");
    
} catch (PDOException $e) {
    echo "Database setup failed: " . $e->getMessage() . "\n";
    Logger::error("Database setup failed", ['error' => $e->getMessage()]);
    exit(1);
}