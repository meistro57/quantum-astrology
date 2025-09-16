<?php
# classes/autoload.php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'QuantumAstrology\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) return;
    $relative = substr($class, $len);
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) require $path;
});
