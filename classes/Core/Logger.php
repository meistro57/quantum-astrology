<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        $logFile = LOGS_PATH . '/' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }
    
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }
    
    public static function debug(string $message, array $context = []): void
    {
        if (APP_DEBUG) {
            self::log('DEBUG', $message, $context);
        }
    }
}