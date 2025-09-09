<?php
namespace QuantumAstrology\Core;

class Application {
    public function run() {
        $uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);
        
        switch (rtrim($path, '/')) {
            case '':
            case '/':
                include __DIR__ . '/../../pages/dashboard/index.php';
                break;
            default:
                http_response_code(404);
                echo "<h1>404 - Page Not Found</h1>";
        }
    }
}
?>
