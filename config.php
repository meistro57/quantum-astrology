<?php
declare(strict_types=1);

use Dotenv\Dotenv;

// Load Composer autoloader if available for Dotenv and other dependencies
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load environment variables from .env if present
if (class_exists(Dotenv::class)) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

// Helper to retrieve environment variables with defaults
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

// Application settings
define('APP_ENV', (string) env('APP_ENV', 'production'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
define('APP_URL', (string) env('APP_URL', 'http://localhost'));
define('APP_TIMEZONE', (string) env('APP_TIMEZONE', 'UTC'));

// Paths
define('ROOT_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('LOGS_PATH', STORAGE_PATH . '/logs');

// Database configuration
define('DB_HOST', (string) env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', (string) env('DB_NAME', 'quantum_astrology'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));
define('DB_CHARSET', (string) env('DB_CHARSET', 'utf8mb4'));

// Cache configuration
define('CACHE_ENABLED', filter_var(env('CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN));
define('CACHE_TTL', (int) env('CACHE_TTL', 3600));

// Swiss Ephemeris configuration
define('SWEPH_PATH', (string) env('SWEPH_PATH', '/usr/local/bin/swetest'));
define('SWEPH_DATA_PATH', (string) env('SWEPH_DATA_PATH', ROOT_PATH . '/data/ephemeris'));

// Set default timezone
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set(APP_TIMEZONE);
}
