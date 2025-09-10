<?php
declare(strict_types=1);

// Load configuration first
require_once __DIR__ . '/config.php';

// Simple autoloader since Composer may not be available yet
spl_autoload_register(function ($class) {
    $prefix = 'QuantumAstrology\\';
    $baseDir = __DIR__ . '/classes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Try to load Composer autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use QuantumAstrology\Core\Application;
use QuantumAstrology\Core\Logger;

try {
    $app = new Application();
    $app->run();
} catch (Throwable $e) {
    // Last resort error handling
    Logger::error("Fatal application error", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    if (APP_DEBUG) {
        echo "<h1>Fatal Error</h1>";
        echo "<pre>" . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "</pre>";
    } else {
        echo "<h1>Service Temporarily Unavailable</h1>";
    }
}
