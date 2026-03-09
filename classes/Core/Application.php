<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\Session;
use QuantumAstrology\Support\OpenRouterMetrics;

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
            case $path === '/transits':
                $this->servePage('charts/transits/index.php');
                exit;

            case $path === '/charts/progressions':
                $this->servePage('charts/progressions/index.php');
                exit;
            case $path === '/progressions':
                $this->servePage('charts/progressions/index.php');
                exit;

            case $path === '/charts/solar-returns':
                $this->servePage('charts/solar-returns/index.php');
                exit;
            case $path === '/relationships':
                $this->servePage('charts/relationships.php');
                exit;
            case $path === '/timing':
                $this->servePage('forecasting/index.php');
                exit;

            case $path === '/forecasting':
            case $path === '/forecasting/':
                $this->servePage('forecasting/index.php');
                exit;

            case $path === '/analytics':
            case $path === '/analytics/':
                $this->servePage('analytics/index.php');
                exit;

            case str_starts_with($path, '/charts/'):
                $this->servePage('charts/index.php');
                exit;

            case $path === '/reports':
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

            case $path === '/admin':
                $this->servePage('admin/index.php');
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
        $this->trackApiUsage($path);

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

        if ($path === '/api/ai/providers') {
            $this->serveAIProviders();
            return;
        }

        if ($path === '/api/admin/overview') {
            $this->serveAdminOverview();
            return;
        }

        if ($path === '/api/admin/actions') {
            $this->serveAdminActions($method);
            return;
        }

        if ($path === '/api/admin/db-backup') {
            $this->serveAdminBackupDownload();
            return;
        }

        if (preg_match('/^\/api\/charts\/(\d+)\/patterns$/', $path, $matches)) {
            $this->serveAspectPatterns((int) $matches[1]);
            return;
        }

        if (preg_match('/^\/api\/charts\/(\d+)\/export$/', $path, $matches)) {
            $_GET['id'] = (string) $matches[1];
            require __DIR__ . '/../../api/charts/export.php';
            return;
        }

        // Legacy API passthrough for file-based endpoints like /api/analytics/usage.php
        if (str_starts_with($path, '/api/') && str_ends_with($path, '.php')) {
            $apiRoot = realpath(__DIR__ . '/../../api');
            $candidate = realpath(__DIR__ . '/../../' . ltrim($path, '/'));
            if (
                $apiRoot !== false &&
                $candidate !== false &&
                str_starts_with($candidate, $apiRoot . DIRECTORY_SEPARATOR) &&
                is_file($candidate)
            ) {
                require $candidate;
                return;
            }
        }

        // Basic API routing - expand as needed
        switch ($path) {
            case '/api/health':
                require __DIR__ . '/../../api/health.php';
                break;

            case '/api/ephemeris/planets':
                $this->serveAvailablePlanets();
                break;

            default:
                $this->sendJson(['error' => 'API endpoint not found'], 404);
        }
    }

    /**
     * Record API usage for authenticated users without enforcing limits.
     */
    private function trackApiUsage(string $path): void
    {
        try {
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$currentUser) {
                return;
            }

            $limiter = new \QuantumAstrology\Core\RateLimiter();
            $limiter->recordRequest((int) $currentUser->getId(), $path, true);
        } catch (\Throwable $e) {
            // Usage tracking must never break API responses.
        }
    }

    private function serveChartWheel(int $chartId): void
    {
        try {
            $size = isset($_GET['size']) ? (int) $_GET['size'] : 900;
            $size = max(400, min(1800, $size));

            $chart = \QuantumAstrology\Charts\Chart::findById($chartId);

            if (!$chart) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Chart not found']);
                return;
            }

            // Check if chart is public or belongs to current user
            $currentUser = \QuantumAstrology\Core\Auth::user();
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Access denied']);
                return;
            }

            $planetaryPositions = [];
            foreach ($chart->getPlanetaryPositions() as $key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = is_string($key) && !is_int($key)
                    ? strtolower($key)
                    : strtolower((string) ($row['planet'] ?? $row['name'] ?? ''));
                $longitude = $row['longitude'] ?? $row['lon'] ?? null;
                if ($name === '' || !is_numeric($longitude)) {
                    continue;
                }
                $planetaryPositions[$name] = ['longitude' => (float) $longitude];
            }

            $houseRaw = $chart->getHousePositions();
            $housePositions = [];
            if (isset($houseRaw['cusps']) && is_array($houseRaw['cusps'])) {
                foreach ($houseRaw['cusps'] as $house => $cusp) {
                    if (is_numeric($house) && is_numeric($cusp)) {
                        $housePositions[(int) $house] = ['cusp' => (float) $cusp];
                    }
                }
            } else {
                foreach ($houseRaw as $house => $row) {
                    if (is_numeric($house) && is_array($row) && isset($row['cusp']) && is_numeric($row['cusp'])) {
                        $housePositions[(int) $house] = ['cusp' => (float) $row['cusp']];
                    }
                }
            }

            $aspects = [];
            foreach ($chart->getAspects() as $aspect) {
                if (!is_array($aspect)) {
                    continue;
                }
                $p1 = strtolower((string) ($aspect['planet1'] ?? ''));
                $p2 = strtolower((string) ($aspect['planet2'] ?? ''));
                $type = strtolower((string) ($aspect['aspect'] ?? $aspect['type'] ?? ''));
                if ($p1 === '' || $p2 === '' || $type === '') {
                    continue;
                }
                $aspects[] = ['planet1' => $p1, 'planet2' => $p2, 'aspect' => $type];
            }

            if (!$planetaryPositions) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Chart has no planetary position data']);
                return;
            }

            $chartWheel = new \QuantumAstrology\Charts\ChartWheel($size);
            $wheelOptions = [
                'chart_name' => (string)($chart->getName() ?? 'Chart'),
                'chart_type' => strtolower((string)$chart->getChartType()),
                'house_system' => strtoupper((string)$chart->getHouseSystem()),
                'birth_datetime' => $chart->getBirthDatetime()?->format('Y-m-d H:i') ?? '',
            ];
            $cacheKey = "chart_{$chartId}_{$size}_" . md5(
                json_encode($planetaryPositions) .
                json_encode($housePositions) .
                json_encode($wheelOptions)
            );

            $svg = $chartWheel->generateWheelWithCache(
                $cacheKey,
                $planetaryPositions,
                $housePositions ?: [],
                $aspects ?: [],
                $wheelOptions
            );

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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }

            $progression = new \QuantumAstrology\Charts\Progression($chart);
            $progressedChart = $progression->generateProgressedChart();

            echo json_encode($progressedChart);

        } catch (\Throwable $e) {
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }

            $progressedDate = isset($_GET['date']) ? new \DateTime($_GET['date']) : new \DateTime();

            $progression = new \QuantumAstrology\Charts\Progression($chart);
            $currentProgressions = $progression->calculateSecondaryProgressions($progressedDate);

            echo json_encode($currentProgressions);

        } catch (\Throwable $e) {
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
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
            if ((!$chart1->isPublic() && (!$currentUser || (int)$chart1->getUserId() !== (int)$currentUser->getId())) ||
                (!$chart2->isPublic() && (!$currentUser || (int)$chart2->getUserId() !== (int)$currentUser->getId()))) {
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
            if ((!$chart1->isPublic() && (!$currentUser || (int)$chart1->getUserId() !== (int)$currentUser->getId())) ||
                (!$chart2->isPublic() && (!$currentUser || (int)$chart2->getUserId() !== (int)$currentUser->getId()))) {
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
            if ((!$chart1->isPublic() && (!$currentUser || (int)$chart1->getUserId() !== (int)$currentUser->getId())) ||
                (!$chart2->isPublic() && (!$currentUser || (int)$chart2->getUserId() !== (int)$currentUser->getId()))) {
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
            if ((!$chart1->isPublic() && (!$currentUser || (int)$chart1->getUserId() !== (int)$currentUser->getId())) ||
                (!$chart2->isPublic() && (!$currentUser || (int)$chart2->getUserId() !== (int)$currentUser->getId()))) {
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
                $this->sendJson(['error' => 'Access denied'], 403);
                return;
            }

            $masterAi = \QuantumAstrology\Core\SystemSettings::getMasterAiConfig();
            $summaryCfg = \QuantumAstrology\Core\SystemSettings::getAiSummaryConfig();
            $provider = strtolower(trim((string)($masterAi['provider'] ?? ($_ENV['AI_PROVIDER'] ?? 'ollama'))));
            $model = trim((string)($masterAi['model'] ?? 'default'));
            $style = trim((string)($summaryCfg['style'] ?? 'professional'));
            $length = trim((string)($summaryCfg['length'] ?? 'medium'));
            $focus = trim((string)($_GET['focus'] ?? ''));
            $fresh = filter_var($_GET['fresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $cacheTtl = (int)($_ENV['AI_CACHE_TTL'] ?? 21600);
            if ($cacheTtl < 0) {
                $cacheTtl = 21600;
            }

            $cacheKeyPayload = [
                'chart_id' => $chart->getId(),
                'chart_updated' => $chart->getUpdatedAt(),
                'provider' => $provider,
                'model' => $model,
                'style' => $style,
                'length' => $length,
                'focus' => $focus,
            ];
            $cacheKey = sha1(json_encode($cacheKeyPayload, JSON_UNESCAPED_SLASHES));

            $cacheDir = STORAGE_PATH . '/cache/ai';
            $cacheFile = $cacheDir . '/' . $cacheKey . '.json';

            if (!$fresh && $cacheTtl > 0 && is_file($cacheFile)) {
                $age = time() - filemtime($cacheFile);
                if ($age >= 0 && $age <= $cacheTtl) {
                    $cached = json_decode((string)file_get_contents($cacheFile), true);
                    if (is_array($cached)) {
                        $cached['cache'] = [
                            'hit' => true,
                            'ttl_seconds' => $cacheTtl,
                            'age_seconds' => $age,
                        ];
                        $this->sendJson($cached);
                        return;
                    }
                }
            }

            $aiConfig = [
                'provider' => $provider,
                'model' => $model,
                'api_key' => (string)($masterAi['api_key'] ?? ''),
                'style' => $style,
                'length' => $length,
                'focus' => $focus,
            ];

            $startedAt = microtime(true);
            $aiInterpreter = new \QuantumAstrology\Interpretations\AIInterpreter($chart, $aiConfig);
            $interpretation = $aiInterpreter->generateNaturalLanguageInterpretation();
            $processingMs = (int) round((microtime(true) - $startedAt) * 1000);
            $interpretation['cache'] = [
                'hit' => false,
                'ttl_seconds' => $cacheTtl,
                'age_seconds' => 0,
            ];
            $interpretation['request'] = [
                'provider' => $provider,
                'model' => $model,
                'style' => $style,
                'length' => $length,
                'focus' => $focus,
                'processing_ms' => $processingMs,
            ];

            if ($cacheTtl > 0) {
                if (!is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0755, true);
                }
                @file_put_contents($cacheFile, json_encode($interpretation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $this->sendJson($interpretation);

        } catch (\Exception $e) {
            Logger::error("AI interpretation failed", ['error' => $e->getMessage(), 'chart_id' => $chartId]);
            $this->sendJson(['error' => 'AI interpretation failed'], 500);
        }
    }

    private function serveAIProviders(): void
    {
        try {
            $masterAi = \QuantumAstrology\Core\SystemSettings::getMasterAiConfig();
            $providers = \QuantumAstrology\Interpretations\AIInterpreter::getSupportedProviders();
            $this->sendJson([
                'providers' => $providers,
                'default_provider' => strtolower((string)($masterAi['provider'] ?? ($_ENV['AI_PROVIDER'] ?? 'ollama'))),
                'default_model' => (string)($masterAi['model'] ?? ($_ENV['AI_MODEL'] ?? '')),
                'master_key_set' => !empty($masterAi['api_key_set']),
            ]);
        } catch (\Throwable $e) {
            Logger::error('AI provider metadata failed', ['error' => $e->getMessage()]);
            $this->sendJson(['error' => 'Unable to load AI provider metadata'], 500);
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
            if (!$chart->isPublic() && (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId())) {
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

    private function serveAdminOverview(): void
    {
        try {
            \QuantumAstrology\Core\Auth::requireLogin();
            $user = \QuantumAstrology\Core\Auth::user();
            if (!\QuantumAstrology\Core\AdminGate::canAccess($user)) {
                $this->sendJson(['error' => 'Admin access required.'], 403);
                return;
            }

            $pdo = \QuantumAstrology\Core\DB::conn();
            $counts = [
                'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'charts' => (int)$pdo->query('SELECT COUNT(*) FROM charts')->fetchColumn(),
                'public_charts' => (int)$pdo->query('SELECT COUNT(*) FROM charts WHERE is_public = 1')->fetchColumn(),
            ];

            $logFile = LOGS_PATH . '/' . date('Y-m-d') . '.log';
            $logTail = [];
            $errorCountToday = 0;
            if (is_file($logFile)) {
                $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    if (str_contains($line, ' ERROR: ')) {
                        $errorCountToday++;
                    }
                }
                $logTail = array_slice($lines, -80);
            }

            $storage = [
                'logs_bytes' => $this->directorySize(LOGS_PATH),
                'cache_bytes' => $this->directorySize(STORAGE_PATH . '/cache'),
                'charts_bytes' => $this->directorySize(STORAGE_PATH . '/charts'),
            ];

            $redisDashboard = $this->adminGetRedisDashboard();
            $redisSummary = [
                'status' => (string)($redisDashboard['status'] ?? 'unknown'),
                'driver' => (string)($redisDashboard['driver'] ?? 'none'),
                'message' => (string)($redisDashboard['message'] ?? ''),
                'dbsize' => isset($redisDashboard['metrics']['dbsize']) ? (int)$redisDashboard['metrics']['dbsize'] : null,
                'used_memory_human' => (string)($redisDashboard['metrics']['used_memory_human'] ?? ''),
                'hit_rate_percent' => isset($redisDashboard['metrics']['hit_rate_percent']) ? (float)$redisDashboard['metrics']['hit_rate_percent'] : null,
                'ops_per_sec' => isset($redisDashboard['metrics']['instantaneous_ops_per_sec']) ? (int)$redisDashboard['metrics']['instantaneous_ops_per_sec'] : null,
                'connected_clients' => isset($redisDashboard['metrics']['connected_clients']) ? (int)$redisDashboard['metrics']['connected_clients'] : null,
            ];

            $this->sendJson([
                'ok' => true,
                'counts' => $counts,
                'errors_today' => $errorCountToday,
                'log_file' => $logFile,
                'log_tail' => $logTail,
                'storage' => $storage,
                'redis' => $redisSummary,
                'app' => [
                    'env' => APP_ENV,
                    'debug' => APP_DEBUG,
                    'php' => PHP_VERSION,
                    'timezone' => APP_TIMEZONE,
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error('Admin overview failed', ['error' => $e->getMessage()]);
            $this->sendJson(['error' => 'Failed to load admin overview.'], 500);
        }
    }

    private function serveAdminActions(string $method): void
    {
        if (strtoupper($method) !== 'POST') {
            $this->sendJson(['error' => 'Use POST for admin actions.'], 405);
            return;
        }

        try {
            \QuantumAstrology\Core\Auth::requireLogin();
            $user = \QuantumAstrology\Core\Auth::user();
            if (!\QuantumAstrology\Core\AdminGate::canAccess($user)) {
                $this->sendJson(['error' => 'Admin access required.'], 403);
                return;
            }

            $raw = file_get_contents('php://input');
            $input = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
            if (!is_array($input)) {
                $this->sendJson(['error' => 'Invalid JSON body.'], 400);
                return;
            }

            $csrf = (string)($input['csrf'] ?? '');
            if (!isset($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
                $this->sendJson(['error' => 'Invalid CSRF token.'], 403);
                return;
            }

            $action = strtolower(trim((string)($input['action'] ?? '')));
            $result = match ($action) {
                'clear_ai_cache' => $this->clearDirectoryFiles(STORAGE_PATH . '/cache/ai', ['json']),
                'clear_chart_cache' => $this->clearDirectoryFiles(STORAGE_PATH . '/cache/charts', ['svg']),
                'clear_all_cache' => $this->clearDirectoryFiles(STORAGE_PATH . '/cache', []),
                'run_system_task' => $this->adminRunSystemTask($input),
                'create_db_backup' => $this->adminCreateDatabaseBackup(),
                'list_db_backups' => $this->adminListDatabaseBackups(),
                'delete_db_backup' => $this->adminDeleteDatabaseBackup($input),
                'list_users' => $this->adminListUsers(),
                'get_ai_master_config' => $this->adminGetMasterAiConfig(),
                'set_ai_master_config' => $this->adminSetMasterAiConfig($input),
                'get_openrouter_key_status' => $this->adminGetOpenRouterKeyStatus(),
                'get_redis_dashboard' => $this->adminGetRedisDashboard(),
                'get_ai_summary_config' => $this->adminGetAiSummaryConfig(),
                'set_ai_summary_config' => $this->adminSetAiSummaryConfig($input),
                'create_user' => $this->adminCreateUser($input),
                'reset_user_password' => $this->adminResetUserPassword($input),
                'set_user_admin' => $this->adminSetUserAdmin($input),
                default => null,
            };

            if ($result === null) {
                $this->sendJson(['error' => 'Unknown admin action.'], 400);
                return;
            }

            if (is_array($result)) {
                $this->sendJson(array_merge(['ok' => true, 'action' => $action], $result));
                return;
            }

            $this->sendJson([
                'ok' => true,
                'action' => $action,
                'deleted_files' => (int)$result,
            ]);
        } catch (\RuntimeException $e) {
            $this->sendJson(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Logger::error('Admin action failed', ['error' => $e->getMessage()]);
            $this->sendJson(['error' => 'Admin action failed.'], 500);
        }
    }

    private function serveAdminBackupDownload(): void
    {
        try {
            \QuantumAstrology\Core\Auth::requireLogin();
            $user = \QuantumAstrology\Core\Auth::user();
            if (!\QuantumAstrology\Core\AdminGate::canAccess($user)) {
                http_response_code(403);
                echo 'Admin access required.';
                return;
            }

            $file = (string)($_GET['file'] ?? '');
            if ($file === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
                http_response_code(400);
                echo 'Invalid backup file.';
                return;
            }

            $backupPath = STORAGE_PATH . '/backups/' . $file;
            if (!is_file($backupPath)) {
                http_response_code(404);
                echo 'Backup file not found.';
                return;
            }

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
            header('Content-Length: ' . (string)filesize($backupPath));
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            readfile($backupPath);
        } catch (\Throwable $e) {
            Logger::error('Admin backup download failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo 'Backup download failed.';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function adminCreateDatabaseBackup(): array
    {
        $driver = strtolower((string) \QuantumAstrology\Database\Connection::getDriver());
        $backupDir = STORAGE_PATH . '/backups';
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('Failed to create backup directory.');
        }

        $timestamp = date('Ymd_His');
        $createdFiles = [];

        if ($driver === 'sqlite' && is_file(DB_SQLITE_PATH)) {
            $sqliteCopy = sprintf('db_backup_%s.sqlite', $timestamp);
            $sqlitePath = $backupDir . '/' . $sqliteCopy;
            if (@copy(DB_SQLITE_PATH, $sqlitePath)) {
                $createdFiles[] = $sqliteCopy;
            }
        }

        $sqlDump = $this->buildDatabaseSqlDump();
        $sqlFile = sprintf('db_backup_%s.sql', $timestamp);
        $sqlPath = $backupDir . '/' . $sqlFile;
        if (@file_put_contents($sqlPath, $sqlDump) === false) {
            throw new \RuntimeException('Failed to write SQL backup.');
        }
        $createdFiles[] = $sqlFile;

        return [
            'message' => 'Database backup created.',
            'driver' => $driver,
            'files' => $createdFiles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminListDatabaseBackups(): array
    {
        $backupDir = STORAGE_PATH . '/backups';
        if (!is_dir($backupDir)) {
            return ['backups' => []];
        }

        $items = [];
        $entries = @scandir($backupDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!preg_match('/^db_backup_.*\.(sql|sqlite)$/', $entry)) {
                continue;
            }
            $fullPath = $backupDir . '/' . $entry;
            if (!is_file($fullPath)) {
                continue;
            }
            $items[] = [
                'file' => $entry,
                'size' => (int)filesize($fullPath),
                'modified_at' => date('c', (int)filemtime($fullPath)),
            ];
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)$b['modified_at'], (string)$a['modified_at']));
        return ['backups' => $items];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function adminDeleteDatabaseBackup(array $input): array
    {
        $file = trim((string)($input['file'] ?? ''));
        if ($file === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            throw new \RuntimeException('Invalid backup filename.');
        }

        $backupPath = STORAGE_PATH . '/backups/' . $file;
        if (!is_file($backupPath)) {
            throw new \RuntimeException('Backup file not found.');
        }

        if (!@unlink($backupPath)) {
            throw new \RuntimeException('Failed to delete backup.');
        }

        return [
            'message' => 'Backup deleted.',
            'file' => $file,
        ];
    }

    private function buildDatabaseSqlDump(): string
    {
        $pdo = \QuantumAstrology\Core\DB::conn();
        $driver = strtolower((string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $tables = [];

        if ($driver === 'mysql') {
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } else {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = $stmt ? ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        }

        $dump = [];
        $dump[] = '-- Quantum Astrology database backup';
        $dump[] = '-- Driver: ' . $driver;
        $dump[] = '-- Generated: ' . date('c');
        $dump[] = '';

        foreach ($tables as $table) {
            $tableName = (string)$table;
            if ($tableName === '') {
                continue;
            }
            $dump[] = '-- Table: ' . $tableName;

            if ($driver === 'mysql') {
                $createStmt = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $tableName) . '`');
                $createRow = $createStmt ? $createStmt->fetch(\PDO::FETCH_ASSOC) : false;
                $createSql = is_array($createRow) ? (string)($createRow['Create Table'] ?? '') : '';
            } else {
                $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = :t");
                $stmt->execute([':t' => $tableName]);
                $createSql = (string)($stmt->fetchColumn() ?: '');
            }

            if ($createSql !== '') {
                $dump[] = $createSql . ';';
            }

            $rowsStmt = $pdo->query('SELECT * FROM "' . str_replace('"', '""', $tableName) . '"');
            $rows = $rowsStmt ? ($rowsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $quotedCols = array_map(static fn(string $c): string => '"' . str_replace('"', '""', $c) . '"', $columns);
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_int($value) || is_float($value)) {
                        $values[] = (string)$value;
                    } else {
                        $values[] = $pdo->quote((string)$value);
                    }
                }
                $dump[] = 'INSERT INTO "' . str_replace('"', '""', $tableName) . '" (' . implode(', ', $quotedCols) . ') VALUES (' . implode(', ', $values) . ');';
            }

            $dump[] = '';
        }

        return implode("\n", $dump) . "\n";
    }

    /**
     * @return array{users: array<int, array<string, mixed>>}
     */
    private function adminListUsers(): array
    {
        $pdo = \QuantumAstrology\Core\DB::conn();
        $sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.timezone, u.is_admin, u.created_at, u.last_login_at, COUNT(c.id) AS chart_count
                FROM users u
                LEFT JOIN charts c ON c.user_id = u.id
                GROUP BY u.id, u.username, u.email, u.first_name, u.last_name, u.timezone, u.is_admin, u.created_at, u.last_login_at
                ORDER BY u.id DESC
                LIMIT 200";
        $stmt = $pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        return ['users' => is_array($rows) ? $rows : []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function adminCreateUser(array $input): array
    {
        $username = trim((string)($input['username'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $firstName = trim((string)($input['first_name'] ?? ''));
        $lastName = trim((string)($input['last_name'] ?? ''));
        $timezone = trim((string)($input['timezone'] ?? 'UTC'));

        if ($username === '' || $email === '' || $password === '') {
            throw new \RuntimeException('Username, email, and password are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email format.');
        }
        if (strlen($password) < 8) {
            throw new \RuntimeException('Password must be at least 8 characters.');
        }

        if (\QuantumAstrology\Core\User::findByUsername($username)) {
            throw new \RuntimeException('Username is already taken.');
        }
        if (\QuantumAstrology\Core\User::findByEmail($email)) {
            throw new \RuntimeException('Email is already in use.');
        }

        $created = \QuantumAstrology\Core\User::create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'timezone' => $timezone !== '' ? $timezone : 'UTC',
            'is_admin' => !empty($input['is_admin']),
        ]);

        if (!$created instanceof \QuantumAstrology\Core\User) {
            throw new \RuntimeException('Failed to create user.');
        }

        return [
            'message' => 'User created successfully.',
            'user' => [
                'id' => $created->getId(),
                'username' => $created->getUsername(),
                'email' => $created->getEmail(),
                'first_name' => $created->getFirstName(),
                'last_name' => $created->getLastName(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function adminResetUserPassword(array $input): array
    {
        $userId = (int)($input['user_id'] ?? 0);
        $newPassword = (string)($input['new_password'] ?? '');

        if ($userId <= 0 || $newPassword === '') {
            throw new \RuntimeException('User and new password are required.');
        }
        if (strlen($newPassword) < 8) {
            throw new \RuntimeException('Password must be at least 8 characters.');
        }

        $target = \QuantumAstrology\Core\User::findById($userId);
        if (!$target) {
            throw new \RuntimeException('User not found.');
        }

        if (!$target->updatePassword($newPassword)) {
            throw new \RuntimeException('Failed to reset password.');
        }

        return [
            'message' => 'Password reset successfully.',
            'user' => [
                'id' => $target->getId(),
                'username' => $target->getUsername(),
                'email' => $target->getEmail(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function adminSetUserAdmin(array $input): array
    {
        $userId = (int)($input['user_id'] ?? 0);
        $isAdmin = filter_var($input['is_admin'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($userId <= 0) {
            throw new \RuntimeException('Valid user_id is required.');
        }

        $target = \QuantumAstrology\Core\User::findById($userId);
        if (!$target) {
            throw new \RuntimeException('User not found.');
        }

        if (!$target->update(['is_admin' => $isAdmin ? 1 : 0])) {
            throw new \RuntimeException('Failed to update admin flag.');
        }

        return [
            'message' => $isAdmin ? 'Admin role granted.' : 'Admin role removed.',
            'user' => [
                'id' => $target->getId(),
                'username' => $target->getUsername(),
                'is_admin' => $isAdmin,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminGetMasterAiConfig(): array
    {
        $cfg = \QuantumAstrology\Core\SystemSettings::getMasterAiConfig();

        return [
            'provider' => $cfg['provider'],
            'model' => $cfg['model'],
            'api_key_set' => (bool) $cfg['api_key_set'],
            'updated_at' => $cfg['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function adminSetMasterAiConfig(array $input): array
    {
        $provider = strtolower(trim((string)($input['provider'] ?? '')));
        $model = trim((string)($input['model'] ?? ''));
        $apiKeyRaw = array_key_exists('api_key', $input) ? (string) $input['api_key'] : null;
        $clearKey = filter_var($input['clear_api_key'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $providers = \QuantumAstrology\Interpretations\AIInterpreter::getSupportedProviders();
        if ($provider === '' || !isset($providers[$provider])) {
            throw new \RuntimeException('Valid AI provider is required.');
        }

        \QuantumAstrology\Core\SystemSettings::setMasterAiConfig($provider, $model, $apiKeyRaw, (bool)$clearKey);
        $cfg = \QuantumAstrology\Core\SystemSettings::getMasterAiConfig();

        return [
            'message' => 'Master AI configuration saved.',
            'provider' => $cfg['provider'],
            'model' => $cfg['model'],
            'api_key_set' => (bool) $cfg['api_key_set'],
            'updated_at' => $cfg['updated_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminGetOpenRouterKeyStatus(): array
    {
        $cfg = \QuantumAstrology\Core\SystemSettings::getMasterAiConfig();
        $provider = strtolower(trim((string)($cfg['provider'] ?? '')));
        $apiKey = trim((string)($cfg['api_key'] ?? ''));

        if ($provider !== 'openrouter') {
            return [
                'status' => 'provider_not_openrouter',
                'provider' => $provider,
                'api_key_set' => (bool)($cfg['api_key_set'] ?? false),
                'updated_at' => $cfg['updated_at'] ?? null,
                'message' => 'Master AI provider is not set to OpenRouter.',
            ];
        }

        if ($apiKey === '') {
            return [
                'status' => 'key_missing',
                'provider' => $provider,
                'api_key_set' => false,
                'updated_at' => $cfg['updated_at'] ?? null,
                'message' => 'No OpenRouter API key is saved.',
            ];
        }

        $keyInfoUrlUsed = null;
        $keyInfoData = null;
        $keyInfoHttpStatus = 0;
        $keyInfoError = '';

        foreach (['https://openrouter.ai/api/v1/key', 'https://openrouter.ai/api/v1/auth/key'] as $url) {
            $response = $this->httpGetJsonWithBearer($url, $apiKey, 20);
            $keyInfoHttpStatus = (int)($response['http_status'] ?? 0);
            $keyInfoError = (string)($response['error'] ?? '');

            if (($response['ok'] ?? false) && is_array($response['json'])) {
                $keyInfoUrlUsed = $url;
                $keyInfoData = $response['json'];
                break;
            }
        }

        if (!is_array($keyInfoData)) {
            throw new \RuntimeException(
                'Unable to fetch OpenRouter key status.'
                . ($keyInfoHttpStatus > 0 ? " HTTP {$keyInfoHttpStatus}." : '')
                . ($keyInfoError !== '' ? " {$keyInfoError}" : '')
            );
        }

        $keyData = is_array($keyInfoData['data'] ?? null) ? $keyInfoData['data'] : [];

        $creditsResponse = $this->httpGetJsonWithBearer('https://openrouter.ai/api/v1/credits', $apiKey, 20);
        $creditsData = (($creditsResponse['ok'] ?? false) && is_array($creditsResponse['json'])) ? $creditsResponse['json'] : [];
        $creditsNode = is_array($creditsData['data'] ?? null) ? $creditsData['data'] : [];
        $metrics = OpenRouterMetrics::summarize($keyData, $creditsNode);

        return [
            'status' => 'ok',
            'provider' => $provider,
            'api_key_set' => true,
            'updated_at' => $cfg['updated_at'] ?? null,
            'fetched_at' => date('c'),
            'key_info' => [
                'label' => isset($keyData['label']) ? (string)$keyData['label'] : '',
                'is_free_tier' => isset($keyData['is_free_tier']) ? (bool)$keyData['is_free_tier'] : null,
                'limit' => $metrics['limit'],
                'limit_remaining' => $metrics['limit_remaining'],
                'limit_reset' => isset($keyData['limit_reset']) ? (string)$keyData['limit_reset'] : null,
                'usage' => $metrics['usage'],
                'usage_daily' => $metrics['usage_daily'],
                'usage_weekly' => $metrics['usage_weekly'],
                'usage_monthly' => $metrics['usage_monthly'],
                'byok_usage' => $metrics['byok_usage'],
                'include_byok_in_limit' => isset($keyData['include_byok_in_limit']) ? (bool)$keyData['include_byok_in_limit'] : null,
            ],
            'credits' => [
                'credits_left' => $metrics['credits_left'],
                'total_credits' => $metrics['total_credits'],
                'total_usage' => $metrics['total_usage'],
                'utilization_percent' => $metrics['utilization_percent'],
            ],
            'rate_limit' => is_array($keyData['rate_limit'] ?? null) ? $keyData['rate_limit'] : null,
            'source' => [
                'key_info_endpoint' => $keyInfoUrlUsed,
                'credits_endpoint' => 'https://openrouter.ai/api/v1/credits',
                'credits_http_status' => (int)($creditsResponse['http_status'] ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminGetRedisDashboard(): array
    {
        $host = trim((string)($_ENV['REDIS_HOST'] ?? '127.0.0.1'));
        if ($host === '') {
            $host = '127.0.0.1';
        }

        $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
        if ($port < 1 || $port > 65535) {
            $port = 6379;
        }

        $timeout = (float)($_ENV['REDIS_TIMEOUT'] ?? 1.5);
        if ($timeout <= 0) {
            $timeout = 1.5;
        }

        $dbIndex = (int)($_ENV['REDIS_DB'] ?? 0);
        if ($dbIndex < 0) {
            $dbIndex = 0;
        }

        $password = trim((string)($_ENV['REDIS_PASSWORD'] ?? ''));
        $base = [
            'connection' => [
                'host' => $host,
                'port' => $port,
                'db' => $dbIndex,
                'timeout_seconds' => $timeout,
                'password_set' => $password !== '',
            ],
            'fetched_at' => date('c'),
        ];

        try {
            $probe = $this->probeRedisViaPhpRedis($host, $port, $password, $dbIndex, $timeout);
            if (is_array($probe)) {
                return array_merge($base, $probe);
            }

            $probe = $this->probeRedisViaSocket($host, $port, $password, $dbIndex, $timeout);
            return array_merge($base, $probe);
        } catch (\Throwable $e) {
            return array_merge($base, [
                'status' => 'error',
                'driver' => 'none',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function probeRedisViaPhpRedis(string $host, int $port, string $password, int $dbIndex, float $timeout): ?array
    {
        if (!class_exists(\Redis::class)) {
            return null;
        }

        $redis = new \Redis();
        try {
            $connected = @$redis->connect($host, $port, $timeout);
            if ($connected !== true) {
                return null;
            }

            if ($password !== '' && @$redis->auth($password) !== true) {
                return [
                    'status' => 'error',
                    'driver' => 'phpredis',
                    'message' => 'Redis authentication failed.',
                ];
            }

            if ($dbIndex > 0) {
                @$redis->select($dbIndex);
            }

            $pong = $redis->ping();
            if (!is_string($pong) && $pong !== true) {
                return [
                    'status' => 'error',
                    'driver' => 'phpredis',
                    'message' => 'Redis ping failed.',
                ];
            }

            $info = $redis->info();
            $dbSize = (int)$redis->dbSize();
            return [
                'status' => 'ok',
                'driver' => 'phpredis',
                'message' => 'Redis connection healthy.',
                'metrics' => $this->buildRedisMetrics(is_array($info) ? $info : [], $dbSize),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'driver' => 'phpredis',
                'message' => $e->getMessage(),
            ];
        } finally {
            try {
                $redis->close();
            } catch (\Throwable $e) {
                // no-op
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function probeRedisViaSocket(string $host, int $port, string $password, int $dbIndex, float $timeout): array
    {
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errno,
            $errstr,
            $timeout
        );

        if (!is_resource($stream)) {
            return [
                'status' => 'unavailable',
                'driver' => 'socket',
                'message' => 'Redis not reachable: ' . trim($errstr !== '' ? $errstr : ('connection error ' . $errno)),
            ];
        }

        stream_set_timeout($stream, max(1, (int)ceil($timeout)));

        try {
            if ($password !== '') {
                $auth = $this->redisSocketCommand($stream, ['AUTH', $password]);
                if (!is_string($auth) || stripos($auth, 'OK') === false) {
                    return [
                        'status' => 'error',
                        'driver' => 'socket',
                        'message' => 'Redis authentication failed.',
                    ];
                }
            }

            if ($dbIndex > 0) {
                $select = $this->redisSocketCommand($stream, ['SELECT', (string)$dbIndex]);
                if (!is_string($select) || stripos($select, 'OK') === false) {
                    return [
                        'status' => 'error',
                        'driver' => 'socket',
                        'message' => 'Failed to select Redis database.',
                    ];
                }
            }

            $pong = $this->redisSocketCommand($stream, ['PING']);
            if (!is_string($pong) || stripos($pong, 'PONG') === false) {
                return [
                    'status' => 'error',
                    'driver' => 'socket',
                    'message' => 'Redis ping failed.',
                ];
            }

            $rawInfo = $this->redisSocketCommand($stream, ['INFO']);
            $dbSize = $this->redisSocketCommand($stream, ['DBSIZE']);
            $info = is_string($rawInfo) ? $this->parseRedisInfo($rawInfo) : [];

            return [
                'status' => 'ok',
                'driver' => 'socket',
                'message' => 'Redis connection healthy.',
                'metrics' => $this->buildRedisMetrics($info, (int)$dbSize),
            ];
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param resource $stream
     * @param list<string> $parts
     * @return mixed
     */
    private function redisSocketCommand($stream, array $parts)
    {
        $payload = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $payload .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
        }

        fwrite($stream, $payload);
        return $this->redisSocketRead($stream);
    }

    /**
     * @param resource $stream
     * @return mixed
     */
    private function redisSocketRead($stream)
    {
        $prefix = fgetc($stream);
        if (!is_string($prefix) || $prefix === '') {
            return null;
        }

        $line = fgets($stream);
        if (!is_string($line)) {
            return null;
        }

        $line = rtrim($line, "\r\n");

        return match ($prefix) {
            '+' => $line,
            '-' => 'ERR: ' . $line,
            ':' => (int)$line,
            '$' => $this->redisReadBulkString($stream, (int)$line),
            '*' => $this->redisReadArray($stream, (int)$line),
            default => null,
        };
    }

    /**
     * @param resource $stream
     * @return string|null
     */
    private function redisReadBulkString($stream, int $length): ?string
    {
        if ($length < 0) {
            return null;
        }

        $remaining = $length;
        $data = '';
        while ($remaining > 0 && !feof($stream)) {
            $chunk = fread($stream, $remaining);
            if (!is_string($chunk) || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        if ($remaining > 0) {
            return null;
        }

        // consume trailing CRLF
        fread($stream, 2);
        return $data;
    }

    /**
     * @param resource $stream
     * @return list<mixed>|null
     */
    private function redisReadArray($stream, int $length): ?array
    {
        if ($length < 0) {
            return null;
        }

        $items = [];
        for ($i = 0; $i < $length; $i++) {
            $items[] = $this->redisSocketRead($stream);
        }
        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function parseRedisInfo(string $raw): array
    {
        $info = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) as $line) {
            $line = trim((string)$line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $info[trim($parts[0])] = trim($parts[1]);
        }
        return $info;
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function buildRedisMetrics(array $info, int $dbSize): array
    {
        $usedMemoryBytes = isset($info['used_memory']) ? (int)$info['used_memory'] : null;
        $peakMemoryBytes = isset($info['used_memory_peak']) ? (int)$info['used_memory_peak'] : null;
        $hits = isset($info['keyspace_hits']) ? max(0, (int)$info['keyspace_hits']) : null;
        $misses = isset($info['keyspace_misses']) ? max(0, (int)$info['keyspace_misses']) : null;

        $hitRate = null;
        if ($hits !== null && $misses !== null && ($hits + $misses) > 0) {
            $hitRate = round(($hits / ($hits + $misses)) * 100, 2);
        }

        return [
            'redis_version' => (string)($info['redis_version'] ?? ''),
            'redis_mode' => (string)($info['redis_mode'] ?? ''),
            'uptime_seconds' => isset($info['uptime_in_seconds']) ? (int)$info['uptime_in_seconds'] : null,
            'connected_clients' => isset($info['connected_clients']) ? (int)$info['connected_clients'] : null,
            'dbsize' => $dbSize,
            'used_memory_bytes' => $usedMemoryBytes,
            'used_memory_human' => $usedMemoryBytes !== null ? $this->formatBytes($usedMemoryBytes) : null,
            'used_memory_peak_bytes' => $peakMemoryBytes,
            'used_memory_peak_human' => $peakMemoryBytes !== null ? $this->formatBytes($peakMemoryBytes) : null,
            'mem_fragmentation_ratio' => isset($info['mem_fragmentation_ratio']) ? (float)$info['mem_fragmentation_ratio'] : null,
            'total_commands_processed' => isset($info['total_commands_processed']) ? (int)$info['total_commands_processed'] : null,
            'instantaneous_ops_per_sec' => isset($info['instantaneous_ops_per_sec']) ? (int)$info['instantaneous_ops_per_sec'] : null,
            'keyspace_hits' => $hits,
            'keyspace_misses' => $misses,
            'hit_rate_percent' => $hitRate,
            'expired_keys' => isset($info['expired_keys']) ? (int)$info['expired_keys'] : null,
            'evicted_keys' => isset($info['evicted_keys']) ? (int)$info['evicted_keys'] : null,
            'keyspace' => isset($info['db' . (string)max(0, (int)($_ENV['REDIS_DB'] ?? 0))]) ? (string)$info['db' . (string)max(0, (int)($_ENV['REDIS_DB'] ?? 0))] : null,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $idx = -1;
        while ($value >= 1024 && $idx < count($units) - 1) {
            $value /= 1024;
            $idx++;
        }
        return number_format($value, 2) . ' ' . $units[max(0, $idx)];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminGetAiSummaryConfig(): array
    {
        $cfg = \QuantumAstrology\Core\SystemSettings::getAiSummaryConfig();
        return [
            'system_prompt' => (string) ($cfg['system_prompt'] ?? ''),
            'style' => (string) ($cfg['style'] ?? 'professional'),
            'length' => (string) ($cfg['length'] ?? 'short'),
            'focus_template' => (string) ($cfg['focus_template'] ?? ''),
            'updated_at' => $cfg['updated_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function adminSetAiSummaryConfig(array $input): array
    {
        $systemPrompt = trim((string)($input['system_prompt'] ?? ''));
        $style = strtolower(trim((string)($input['style'] ?? 'professional')));
        $length = strtolower(trim((string)($input['length'] ?? 'short')));
        $focusTemplate = trim((string)($input['focus_template'] ?? ''));

        if ($systemPrompt === '') {
            throw new \RuntimeException('System prompt is required.');
        }
        if (!in_array($style, ['professional', 'empathetic', 'direct', 'technical'], true)) {
            throw new \RuntimeException('Invalid summary style.');
        }
        if (!in_array($length, ['short', 'medium', 'long'], true)) {
            throw new \RuntimeException('Invalid summary length.');
        }
        if ($focusTemplate === '') {
            $focusTemplate = 'Prioritize a concise summary suitable for a {report_type} report.';
        }

        \QuantumAstrology\Core\SystemSettings::setAiSummaryConfig($systemPrompt, $style, $length, $focusTemplate);
        $cfg = \QuantumAstrology\Core\SystemSettings::getAiSummaryConfig();

        return [
            'message' => 'AI summary settings saved.',
            'system_prompt' => (string) ($cfg['system_prompt'] ?? ''),
            'style' => (string) ($cfg['style'] ?? 'professional'),
            'length' => (string) ($cfg['length'] ?? 'short'),
            'focus_template' => (string) ($cfg['focus_template'] ?? ''),
            'updated_at' => $cfg['updated_at'] ?? null,
        ];
    }

    /**
     * @return array{ok:bool,http_status:int,error:string,json:array<string,mixed>|null}
     */
    private function httpGetJsonWithBearer(string $url, string $bearerToken, int $timeoutSeconds = 20): array
    {
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'http_status' => 0,
                'error' => 'cURL extension is not available.',
                'json' => null,
            ];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok' => false,
                'http_status' => 0,
                'error' => 'Failed to initialize cURL.',
                'json' => null,
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $bearerToken,
                'Accept: application/json',
                'User-Agent: QuantumAstrologyAdmin/1.0',
            ],
            CURLOPT_TIMEOUT => max(3, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $raw = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw)) {
            return [
                'ok' => false,
                'http_status' => $httpStatus,
                'error' => $curlError !== '' ? $curlError : 'Empty response body.',
                'json' => null,
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'http_status' => $httpStatus,
                'error' => 'Invalid JSON response.',
                'json' => null,
            ];
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $apiError = isset($decoded['error']) ? (string)$decoded['error'] : '';
            return [
                'ok' => false,
                'http_status' => $httpStatus,
                'error' => $apiError !== '' ? $apiError : 'HTTP request failed.',
                'json' => $decoded,
            ];
        }

        return [
            'ok' => true,
            'http_status' => $httpStatus,
            'error' => '',
            'json' => $decoded,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function adminRunSystemTask(array $input): array
    {
        $task = strtolower(trim((string)($input['task'] ?? '')));
        $tasks = [
            'syntax_check' => [
                'label' => 'Syntax check',
                'command' => ['php', 'test-syntax.php'],
            ],
            'chart_smoke_test' => [
                'label' => 'Chart generation smoke test',
                'command' => ['php', 'tools/test-chart-generation.php'],
            ],
            'run_migrations' => [
                'label' => 'Database migration update',
                'command' => ['php', 'tools/migrate.php'],
            ],
            'rebuild_cache' => [
                'label' => 'Runtime cache rebuild',
                'command' => ['php', 'tools/clear-cache.php'],
            ],
            'storage_audit' => [
                'label' => 'Storage audit',
                'command' => ['php', 'tools/manage-storage.php', '--list'],
            ],
        ];

        if (!isset($tasks[$task])) {
            throw new \RuntimeException('Unknown system task.');
        }

        $definition = $tasks[$task];
        $execution = $this->runAdminCommand($definition['command']);

        return [
            'message' => $definition['label'] . ' completed.',
            'task' => $task,
            'task_label' => $definition['label'],
            'command' => implode(' ', array_map('strval', $definition['command'])),
            'exit_code' => $execution['exit_code'],
            'stdout' => $execution['stdout'],
            'stderr' => $execution['stderr'],
            'ok' => $execution['exit_code'] === 0,
        ];
    }

    /**
     * @param array<int, string> $command
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runAdminCommand(array $command): array
    {
        if ($command === []) {
            throw new \RuntimeException('System command is empty.');
        }
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('Command execution is unavailable on this server.');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = [
            'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME' => ROOT_PATH,
        ];

        $process = @proc_open($command, $descriptors, $pipes, ROOT_PATH, $env, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start command.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exit_code' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => $this->truncateAdminCommandOutput($stdout),
            'stderr' => $this->truncateAdminCommandOutput($stderr),
        ];
    }

    private function truncateAdminCommandOutput(string $output, int $maxBytes = 48000): string
    {
        if (strlen($output) <= $maxBytes) {
            return $output;
        }

        $tail = substr($output, -$maxBytes);
        return "[output truncated to last {$maxBytes} bytes]\n" . $tail;
    }

    private function directorySize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * @param string[] $allowedExtensions Empty array means all files.
     */
    private function clearDirectoryFiles(string $dir, array $allowedExtensions): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $ext = strtolower((string)$file->getExtension());
            if ($allowedExtensions !== [] && !in_array($ext, $allowedExtensions, true)) {
                continue;
            }

            if (@unlink($file->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
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
