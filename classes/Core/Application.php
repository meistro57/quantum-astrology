<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\Session;

class Application
{
    /**
     * Run the application and handle any uncaught exceptions.
     */
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
            case $path === '/' || $path === '' || $path === '/dashboard':
                // FIXED: All dashboard routes should return to index.php
                return;
                
            case str_starts_with($path, '/api/'):
                $this->handleApiRequest($path, $method);
                exit;
                
            case str_starts_with($path, '/assets/'):
                $this->serveAsset($path);
                exit;
                
            case $path === '/charts':
                $this->servePage('charts/index.php');
                exit;
                
            case $path === '/charts/create':
                $this->servePage('charts/create.php');
                exit;
                
            case $path === '/charts/view':
                $this->servePage('charts/view.php');
                exit;
                
            case $path === '/charts/relationships':
                $this->servePage('charts/relationships.php');
                exit;

            case $path === '/charts/transits':
                $this->servePage('charts/transits/index.php');
                exit;

            case $path === '/charts/progressions':
                $this->servePage('charts/progressions/index.php');
                exit;

            case $path === '/charts/solar-returns':
                $this->servePage('charts/solar-returns/index.php');
                exit;

            case $path === '/forecasting':
            case $path === '/forecasting/':
                $this->servePage('forecasting/index.php');
                exit;

            case str_starts_with($path, '/charts/'):
                $this->servePage('charts/index.php');
                exit;
                
            case str_starts_with($path, '/reports/'):
                $this->servePage('reports/index.php');
                exit;
                
            case $path === '/login':
                $this->servePage('auth/login.php');
                exit;
                
            case $path === '/register':
                $this->servePage('auth/register.php');
                exit;
                
            case $path === '/profile':
            case $path === '/settings':
                $this->servePage('auth/profile.php');
                exit;
                
            case $path === '/logout':
                $this->handleLogout();
                exit;
                
            default:
                $this->handle404();
                exit;
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
        
        // Check for transit API requests
        if (preg_match('/^\/api\/charts\/(\d+)\/transits$/', $path, $matches)) {
            $this->serveTransits((int) $matches[1]);
            return;
        }
        
        if (preg_match('/^\/api\/charts\/(\d+)\/transits\/current$/', $path, $matches)) {
            $this->serveCurrentTransits((int) $matches[1]);
            return;
        }
        
        if (preg_match('/^\/api\/charts\/(\d+)\/transits\/upcoming$/', $path, $matches)) {
            $this->serveUpcomingTransits((int) $matches[1]);
            return;
        }
        
        // Check for progression API requests
        if (preg_match('/^\/api\/charts\/(\d+)\/progressions$/', $path, $matches)) {
            $this->serveProgressions((int) $matches[1]);
            return;
        }
        
        if (preg_match('/^\/api\/charts\/(\d+)\/progressions\/current$/', $path, $matches)) {
            $this->serveCurrentProgressions((int) $matches[1]);
            return;
        }
        
        // Check for solar return API requests
        if (preg_match('/^\/api\/charts\/(\d+)\/solar-returns\/(\d+)$/', $path, $matches)) {
            $this->serveSolarReturn((int) $matches[1], (int) $matches[2]);
            return;
        }
        
        if (preg_match('/^\/api\/charts\/(\d+)\/solar-returns$/', $path, $matches)) {
            $this->serveSolarReturns((int) $matches[1]);
            return;
        }
        
        // Check for synastry API requests
        if (preg_match('/^\/api\/charts\/(\d+)\/synastry\/(\d+)$/', $path, $matches)) {
            $this->serveSynastry((int) $matches[1], (int) $matches[2]);
            return;
        }
        
        if (preg_match('/^\/api\/charts\/(\d+)\/synastry\/(\d+)\/insights$/', $path, $matches)) {
            $this->serveSynastryInsights((int) $matches[1], (int) $matches[2]);
            return;
        }
        
        // Check for composite API requests
        if (preg_match('/^\/api\/charts\/(\d+)\/composite\/(\d+)$/', $path, $matches)) {
            $this->serveComposite((int) $matches[1], (int) $matches[2]);
            return;
        }
        
        if (preg_match('/^\/api\/charts\/(\d+)\/composite\/(\d+)\/insights$/', $path, $matches)) {
            $this->serveCompositeInsights((int) $matches[1], (int) $matches[2]);
            return;
        }
        
        // Check for interpretation API requests
        if (preg_match('/^\/api\/charts\/(\d+)\/interpretation$/', $path, $matches)) {
            $this->serveChartInterpretation((int) $matches[1]);
            return;
        }
        
        if (preg_match('/^\/api\/charts\/(\d+)\/interpretation\/ai$/', $path, $matches)) {
            $this->serveAIInterpretation((int) $matches[1]);
            return;
        }
        
        if (preg_match('/^\/api\/charts\/(\d+)\/patterns$/', $path, $matches)) {
            $this->serveAspectPatterns((int) $matches[1]);
            return;
        }
        
        // Basic API routing - expand as needed
        switch ($path) {
            case '/api/health':
                $this->sendJson(['status' => 'ok', 'timestamp' => time()]);
                break;

            case '/api/ephemeris/planets':
                $this->serveAvailablePlanets();
                break;

            default:
                $this->sendJson(['error' => 'API endpoint not found'], 404);
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
    
    private function serveTransits(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                http_response_code(404);
                echo json_encode(['error' => 'Chart not found']);
                return;
            }
            
            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $transit = new \QuantumAstrology\Charts\Transit($chart);
            $transitChart = $transit->generateTransitChart();
            
            echo json_encode($transitChart);
            
        } catch (\Exception $e) {
            Logger::error("Transit chart generation failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            http_response_code(500);
            echo json_encode(['error' => 'Transit chart generation failed']);
        }
    }
    
    private function serveCurrentTransits(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                http_response_code(404);
                echo json_encode(['error' => 'Chart not found']);
                return;
            }
            
            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $transit = new \QuantumAstrology\Charts\Transit($chart);
            $currentTransits = $transit->getCurrentTransits();
            
            echo json_encode($currentTransits);
            
        } catch (\Exception $e) {
            Logger::error("Current transits failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            http_response_code(500);
            echo json_encode(['error' => 'Current transits calculation failed']);
        }
    }
    
    private function serveUpcomingTransits(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                http_response_code(404);
                echo json_encode(['error' => 'Chart not found']);
                return;
            }
            
            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $days = (int) ($_GET['days'] ?? 30);
            $days = min(365, max(1, $days)); // Limit between 1-365 days
            
            $transit = new \QuantumAstrology\Charts\Transit($chart);
            $upcomingTransits = $transit->getUpcomingTransits($days);
            
            echo json_encode([
                'chart_id' => $chartId,
                'period_days' => $days,
                'upcoming_transits' => $upcomingTransits
            ]);
            
        } catch (\Exception $e) {
            Logger::error("Upcoming transits failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            http_response_code(500);
            echo json_encode(['error' => 'Upcoming transits calculation failed']);
        }
    }
    
    private function serveProgressions(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                http_response_code(404);
                echo json_encode(['error' => 'Chart not found']);
                return;
            }
            
            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $progression = new \QuantumAstrology\Charts\Progression($chart);
            $progressedChart = $progression->generateProgressedChart();
            
            echo json_encode($progressedChart);
            
        } catch (\Exception $e) {
            Logger::error("Progression chart generation failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            http_response_code(500);
            echo json_encode(['error' => 'Progression chart generation failed']);
        }
    }
    
    private function serveCurrentProgressions(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                http_response_code(404);
                echo json_encode(['error' => 'Chart not found']);
                return;
            }
            
            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $progressedDate = isset($_GET['date']) ? new DateTime($_GET['date']) : new DateTime();
            
            $progression = new \QuantumAstrology\Charts\Progression($chart);
            $currentProgressions = $progression->calculateSecondaryProgressions($progressedDate);
            
            echo json_encode($currentProgressions);
            
        } catch (\Exception $e) {
            Logger::error("Current progressions failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            http_response_code(500);
            echo json_encode(['error' => 'Current progressions calculation failed']);
        }
    }
    
    private function serveSolarReturn(int $chartId, int $returnYear): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                http_response_code(404);
                echo json_encode(['error' => 'Chart not found']);
                return;
            }
            
            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            // Optional location parameters for relocated solar returns
            $latitude = isset($_GET['latitude']) ? (float) $_GET['latitude'] : null;
            $longitude = isset($_GET['longitude']) ? (float) $_GET['longitude'] : null;
            $locationName = $_GET['location'] ?? null;
            
            $solarReturn = new \QuantumAstrology\Charts\SolarReturn($chart);
            $solarReturnChart = $solarReturn->generateSolarReturnChart($returnYear, $latitude, $longitude, $locationName);
            
            echo json_encode($solarReturnChart);
            
        } catch (\Exception $e) {
            Logger::error("Solar Return calculation failed", ['error' => $e->getMessage(), 'chart_id' => $chartId, 'year' => $returnYear]);
            http_response_code(500);
            echo json_encode(['error' => 'Solar Return calculation failed']);
        }
    }
    
    private function serveSolarReturns(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                http_response_code(404);
                echo json_encode(['error' => 'Chart not found']);
                return;
            }
            
            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $currentYear = (int) date('Y');
            $startYear = (int) ($_GET['start_year'] ?? $currentYear - 2);
            $endYear = (int) ($_GET['end_year'] ?? $currentYear + 2);
            
            // Limit range to prevent excessive calculations
            $yearRange = $endYear - $startYear;
            if ($yearRange > 10) {
                http_response_code(400);
                echo json_encode(['error' => 'Year range too large. Maximum 10 years.']);
                return;
            }
            
            $latitude = isset($_GET['latitude']) ? (float) $_GET['latitude'] : null;
            $longitude = isset($_GET['longitude']) ? (float) $_GET['longitude'] : null;
            
            $solarReturn = new \QuantumAstrology\Charts\SolarReturn($chart);
            $solarReturns = $solarReturn->calculateMultipleSolarReturns($startYear, $endYear, $latitude, $longitude);
            
            echo json_encode([
                'chart_id' => $chartId,
                'year_range' => [$startYear, $endYear],
                'solar_returns' => $solarReturns
            ]);
            
        } catch (\Exception $e) {
            Logger::error("Multiple Solar Returns calculation failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            http_response_code(500);
            echo json_encode(['error' => 'Multiple Solar Returns calculation failed']);
        }
    }
    
    private function serveSynastry(int $chart1Id, int $chart2Id): void
    {
        try {
            $chart1 = \QuantumAstrology\Charts\Chart::findById($chart1Id);
            $chart2 = \QuantumAstrology\Charts\Chart::findById($chart2Id);
            
            if (!$chart1 || !$chart2) {
                http_response_code(404);
                echo json_encode(['error' => 'One or both charts not found']);
                return;
            }
            
            // Check access permissions for both charts
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if ((!$chart1->isPublic() && (!$currentUser || $chart1->getUserId() !== $currentUser->getId())) ||
                (!$chart2->isPublic() && (!$currentUser || $chart2->getUserId() !== $currentUser->getId()))) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied to one or both charts']);
                return;
            }
            
            $synastry = new \QuantumAstrology\Charts\Synastry($chart1, $chart2);
            $synastryChart = $synastry->generateSynastryChart();
            
            echo json_encode($synastryChart);
            
        } catch (\Exception $e) {
            Logger::error("Synastry calculation failed", ['error' => $e->getMessage(), 'chart1_id' => $chart1Id, 'chart2_id' => $chart2Id]);
            http_response_code(500);
            echo json_encode(['error' => 'Synastry calculation failed']);
        }
    }
    
    private function serveSynastryInsights(int $chart1Id, int $chart2Id): void
    {
        try {
            $chart1 = \QuantumAstrology\Charts\Chart::findById($chart1Id);
            $chart2 = \QuantumAstrology\Charts\Chart::findById($chart2Id);
            
            if (!$chart1 || !$chart2) {
                http_response_code(404);
                echo json_encode(['error' => 'One or both charts not found']);
                return;
            }
            
            // Check access permissions for both charts
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if ((!$chart1->isPublic() && (!$currentUser || $chart1->getUserId() !== $currentUser->getId())) ||
                (!$chart2->isPublic() && (!$currentUser || $chart2->getUserId() !== $currentUser->getId()))) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied to one or both charts']);
                return;
            }
            
            $synastry = new \QuantumAstrology\Charts\Synastry($chart1, $chart2);
            $synastryInsights = $synastry->getSynastryInsights();
            
            echo json_encode([
                'chart1_id' => $chart1Id,
                'chart2_id' => $chart2Id,
                'chart1_name' => $chart1->getName(),
                'chart2_name' => $chart2->getName(),
                'insights' => $synastryInsights
            ]);
            
        } catch (\Exception $e) {
            Logger::error("Synastry insights failed", ['error' => $e->getMessage(), 'chart1_id' => $chart1Id, 'chart2_id' => $chart2Id]);
            http_response_code(500);
            echo json_encode(['error' => 'Synastry insights calculation failed']);
        }
    }
    
    private function serveComposite(int $chart1Id, int $chart2Id): void
    {
        try {
            $chart1 = \QuantumAstrology\Charts\Chart::findById($chart1Id);
            $chart2 = \QuantumAstrology\Charts\Chart::findById($chart2Id);
            
            if (!$chart1 || !$chart2) {
                http_response_code(404);
                echo json_encode(['error' => 'One or both charts not found']);
                return;
            }
            
            // Check access permissions for both charts
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if ((!$chart1->isPublic() && (!$currentUser || $chart1->getUserId() !== $currentUser->getId())) ||
                (!$chart2->isPublic() && (!$currentUser || $chart2->getUserId() !== $currentUser->getId()))) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied to one or both charts']);
                return;
            }
            
            $composite = new \QuantumAstrology\Charts\Composite($chart1, $chart2);
            $compositeChart = $composite->generateCompositeChart();
            
            echo json_encode($compositeChart);
            
        } catch (\Exception $e) {
            Logger::error("Composite calculation failed", ['error' => $e->getMessage(), 'chart1_id' => $chart1Id, 'chart2_id' => $chart2Id]);
            http_response_code(500);
            echo json_encode(['error' => 'Composite chart calculation failed']);
        }
    }
    
    private function serveCompositeInsights(int $chart1Id, int $chart2Id): void
    {
        try {
            $chart1 = \QuantumAstrology\Charts\Chart::findById($chart1Id);
            $chart2 = \QuantumAstrology\Charts\Chart::findById($chart2Id);
            
            if (!$chart1 || !$chart2) {
                http_response_code(404);
                echo json_encode(['error' => 'One or both charts not found']);
                return;
            }
            
            // Check access permissions for both charts
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if ((!$chart1->isPublic() && (!$currentUser || $chart1->getUserId() !== $currentUser->getId())) ||
                (!$chart2->isPublic() && (!$currentUser || $chart2->getUserId() !== $currentUser->getId()))) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied to one or both charts']);
                return;
            }
            
            $composite = new \QuantumAstrology\Charts\Composite($chart1, $chart2);
            $compositeInsights = $composite->getCompositeInsights();
            
            echo json_encode([
                'chart1_id' => $chart1Id,
                'chart2_id' => $chart2Id,
                'chart1_name' => $chart1->getName(),
                'chart2_name' => $chart2->getName(),
                'insights' => $compositeInsights
            ]);
            
        } catch (\Exception $e) {
            Logger::error("Composite insights failed", ['error' => $e->getMessage(), 'chart1_id' => $chart1Id, 'chart2_id' => $chart2Id]);
            http_response_code(500);
            echo json_encode(['error' => 'Composite insights calculation failed']);
        }
    }
    
    private function serveChartInterpretation(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                $this->sendJson(['error' => 'Chart not found'], 404);
                return;
            }

            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                $this->sendJson(['error' => 'Access denied'], 403);
                return;
            }

            $interpreter = new \QuantumAstrology\Interpretations\ChartInterpretation($chart);
            $interpretation = $interpreter->generateFullInterpretation();

            $this->sendJson($interpretation);

        } catch (\Exception $e) {
            Logger::error("Chart interpretation failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            $this->sendJson(['error' => 'Chart interpretation failed'], 500);
        }
    }
    
    private function serveAIInterpretation(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                $this->sendJson(['error' => 'Chart not found'], 404);
                return;
            }

            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                $this->sendJson(['error' => 'Access denied'], 403);
                return;
            }

            // Optional AI configuration from query parameters
            $aiConfig = [
                'model' => $_GET['model'] ?? 'default',
                'style' => $_GET['style'] ?? 'professional',
                'length' => $_GET['length'] ?? 'medium'
            ];

            $aiInterpreter = new \QuantumAstrology\Interpretations\AIInterpreter($chart, $aiConfig);
            $interpretation = $aiInterpreter->generateNaturalLanguageInterpretation();

            $this->sendJson($interpretation);

        } catch (\Exception $e) {
            Logger::error("AI interpretation failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            $this->sendJson(['error' => 'AI interpretation failed'], 500);
        }
    }
    
    private function serveAspectPatterns(int $chartId): void
    {
        try {
            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);
            
            if (!$chart) {
                $this->sendJson(['error' => 'Chart not found'], 404);
                return;
            }

            // Check access permissions
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || $chart->getUserId() !== $currentUser->getId())) {
                $this->sendJson(['error' => 'Access denied'], 403);
                return;
            }

            $positions = $chart->getPlanetaryPositions();
            $aspects = $chart->getAspects();

            if (!$positions || !$aspects) {
                $this->sendJson(['error' => 'Chart lacks planetary positions or aspects'], 400);
                return;
            }

            $patternAnalyzer = new \QuantumAstrology\Interpretations\AspectPatterns($positions, $aspects);
            $patterns = $patternAnalyzer->detectAllPatterns();

            $this->sendJson([
                'chart_id' => $chartId,
                'chart_name' => $chart->getName(),
                'patterns' => $patterns
            ]);

        } catch (\Exception $e) {
            Logger::error("Aspect patterns analysis failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            $this->sendJson(['error' => 'Aspect patterns analysis failed'], 500);
        }
    }

    /**
     * Serve a list of available planetary identifiers from Swiss Ephemeris.
     */
    private function serveAvailablePlanets(): void
    {
        $ephemeris = new \QuantumAstrology\Core\SwissEphemeris();
        $this->sendJson(['planets' => $ephemeris->getAvailablePlanets()]);
    }

    /**
     * Send a JSON response with the proper headers.
     *
     * @param mixed $data       Data to encode as JSON.
     * @param int   $statusCode HTTP status code.
     */
    private function sendJson(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
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
