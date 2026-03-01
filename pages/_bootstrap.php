<?php
declare(strict_types=1);

if (!defined('QUANTUM_ASTROLOGY_BOOTSTRAPPED')) {
    define('QUANTUM_ASTROLOGY_BOOTSTRAPPED', true);

    $rootPath = dirname(__DIR__);

    require_once $rootPath . '/config.php';
    require_once $rootPath . '/classes/autoload.php';

    \QuantumAstrology\Core\Session::start();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
