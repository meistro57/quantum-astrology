<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\Session;

class Application 
{
    public function run(): void
    {
        try {
            $this->handleRequest();
        } catch (\Exception $e) {
            Logger::error("Application error", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->handleError($e);
        }
    }
    
    private function handleRequest(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Remove query parameters and trailing slashes
        $path = rtrim($path, '/') ?: '/';
        
        Logger::debug("Handling request", ['path' => $path, 'method' => $method]);
        
        // Route handling
        switch (true) {
            case $path === '/' || $path === '':
                // Dashboard is handled by index.php itself, so just return
                return;
                break;
                
            case str_starts_with($path, '/api/'):
                $this->handleApiRequest($path, $method);
                break;
                
            case str_starts_with($path, '/assets/'):
                $this->serveAsset($path);
                break;
                
            case $path === '/charts':
                $this->servePage('charts/index.php');
                break;
                
            case $path === '/charts/create':
                $this->servePage('charts/create.php');
                break;
                
            case $path === '/charts/view':
                $this->servePage('charts/view.php');
                break;
                
            case str_starts_with($path, '/charts/'):
                $this->servePage('charts/index.php');
                break;
                
            case str_starts_with($path, '/reports/'):
                $this->servePage('reports/index.php');
                break;
                
            case $path === '/login':
                $this->servePage('auth/login.php');
                break;
                
            case $path === '/register':
                $this->servePage('auth/register.php');
                break;
                
            case $path === '/profile':
                $this->servePage('auth/profile.php');
                break;
                
            case $path === '/logout':
                $this->handleLogout();
                break;
                
            case $path === '/dashboard':
                $this->servePage('../index.php'); // Dashboard is handled by index.php
                break;
                
            default:
                $this->handle404();
        }
    }
    
    private function servePage(string $page): void
    {
        $filepath = __DIR__ . '/../../pages/' . $page;
        if (file_exists($filepath)) {
            include $filepath;
        } else {
            Logger::warning("Page not found", ['page' => $page]);
            $this->handle404();
        }
    }
    
    private function serveAsset(string $path): void
    {
        $filepath = __DIR__ . '/../../' . ltrim($path, '/');
        if (file_exists($filepath)) {
            $mimeType = $this->getMimeType($filepath);
            header('Content-Type: ' . $mimeType);
            readfile($filepath);
        } else {
            $this->handle404();
        }
    }
    
    private function handleApiRequest(string $path, string $method): void
    {
        // Check for chart wheel SVG requests
        if (preg_match('/^\/api\/charts\/(\d+)\/wheel$/', $path, $matches)) {
            $this->serveChartWheel((int) $matches[1]);
            return;
        }
        
        header('Content-Type: application/json');
        
        // Basic API routing - expand as needed
        switch ($path) {
            case '/api/health':
                echo json_encode(['status' => 'ok', 'timestamp' => time()]);
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'API endpoint not found']);
        }
    }
    
    private function serveChartWheel(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Chart not found']);
                return;
            }
            
            // Check if chart is public or belongs to current user
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $planetaryPositions = $chart->getPlanetaryPositions();
            $housePositions = $chart->getHousePositions();
            $aspects = $chart->getAspects();
            
            if (!$planetaryPositions) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Chart has no planetary position data']);
                return;
            }
            
            $chartWheel = new \QuantumAstrology\Charts\ChartWheel();
            $cacheKey = "chart_{$chartId}_" . md5(json_encode($planetaryPositions) . json_encode($housePositions));
            
            $svg = $chartWheel->generateWheelWithCache($cacheKey, $planetaryPositions, $housePositions ?: [], $aspects ?: []);
            
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=3600');
            echo $svg;
            
        } catch (\Exception $e) {
            Logger::error("Chart wheel generation failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Chart wheel generation failed']);
        }
    }
    
    private function getMimeType(string $filepath): string
    {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        return match(strtolower($extension)) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'woff', 'woff2' => 'font/woff',
            'ttf' => 'font/ttf',
            default => 'application/octet-stream'
        };
    }
    
    private function handle404(): void
    {
        http_response_code(404);
        Logger::warning("404 Not Found", ['uri' => $_SERVER['REQUEST_URI'] ?? 'unknown']);
        
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found']);
        } else {
            echo "<h1>404 - Page Not Found</h1>";
        }
    }
    
    private function handleLogout(): void
    {
        Session::start();
        Session::logout();
        header('Location: /login');
        exit;
    }
    
    private function handleError(\Exception $e): void
    {
        http_response_code(500);
        
        if (APP_DEBUG) {
            echo "<h1>Application Error</h1>";
            echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
        } else {
            echo "<h1>Internal Server Error</h1>";
        }
    }
}
