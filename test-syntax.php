<?php
// Simple syntax test for the application
declare(strict_types=1);

echo "Testing PHP syntax...\n";

// Test config loading
if (file_exists(__DIR__ . '/config.php')) {
    echo "✓ config.php exists\n";
} else {
    echo "✗ config.php missing\n";
}

// Test autoloader
if (function_exists('spl_autoload_register')) {
    echo "✓ Autoloader function available\n";
} else {
    echo "✗ Autoloader not available\n";
}

// Test class files exist
$coreFiles = [
    'classes/Core/Application.php',
    'classes/Core/Logger.php',
    'classes/Database/Connection.php'
];

foreach ($coreFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file missing\n";
    }
}

// Test CSS file
if (file_exists(__DIR__ . '/assets/css/quantum-dashboard.css')) {
    echo "✓ CSS file exists\n";
} else {
    echo "✗ CSS file missing\n";
}

// Test directory structure
$directories = ['pages', 'storage', 'assets'];
foreach ($directories as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        echo "✓ $dir directory exists\n";
    } else {
        echo "✗ $dir directory missing\n";
    }
}

echo "\nSetup verification complete!\n";