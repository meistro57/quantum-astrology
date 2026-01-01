<?php
/**
 * Quantum Astrology - SVG Chart Generator
 * * Generates dynamic SVG astrology wheels.
 * Returns image/svg+xml headers.
 */

// 1. NAMESPACES FIRST (The VIP Queue)
// These must be the very first things before any logic.
// Adjust these class names if yours differ!
use App\Services\SVGBuilder; 
use App\Astrology\ChartCalculator;
use App\Database\Database; 

// 2. ERROR HANDLING SETUP
// We use output buffering to catch any stray whitespace or errors
ob_start();

try {
    // 3. CONFIGURATION
    // specific path using __DIR__ ensures we find the file no matter where we are
    $configPath = __DIR__ . '/../config.php';
    
    if (!file_exists($configPath)) {
        throw new Exception("Config file not found at: $configPath");
    }
    require_once $configPath;

    // 4. INPUT VALIDATION
    $chartId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $size    = isset($_GET['size']) ? (int)$_GET['size'] : 900;
    $ts      = $_GET['ts'] ?? time(); // Timestamp for caching busting

    if ($chartId === 0) {
        throw new Exception("No Chart ID specified.");
    }

// 5. DATABASE CONNECTION
    // We will connect directly here to avoid issues with config.php scope
    $host = '127.0.0.1';
    $db   = 'q_astro'; // CHECK THIS: Is your DB name correct?
    $user = 'astro';              // CHECK THIS: Your DB username
    $pass = 'logical';                  // CHECK THIS: Your DB password (often empty on localhost)
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new Exception("DB Connect Failed: " . $e->getMessage());
    }

    // 6. FETCH CHART DATA
    $stmt = $pdo->prepare("SELECT * FROM charts WHERE id = ? LIMIT 1");
    $stmt->execute([$chartId]);
    $chartData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chartData) {
        throw new Exception("Chart #$chartId not found in database.");
    }

    // 7. GENERATE THE SVG
    // This assumes you have a builder class. If you are coding this raw,
    // replace this block with your actual SVG drawing code.
    
    // Example usage of your likely classes:
    // $calculator = new ChartCalculator($chartData);
    // $planetaryPositions = $calculator->getPositions();
    // $svgBuilder = new SVGBuilder($size);
    // $svgContent = $svgBuilder->render($planetaryPositions);

    // --- TEMPORARY PLACEHOLDER ---
    // (If your classes aren't ready, this draws a simple test circle so you know it works)
    $svgContent = <<<SVG
    <svg width="{$size}" height="{$size}" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <circle cx="50" cy="50" r="45" stroke="#6875F5" stroke-width="2" fill="#1a202c" />
        <text x="50" y="55" font-size="5" text-anchor="middle" fill="white" font-family="sans-serif">
            Chart #{$chartId}
        </text>
        <text x="50" y="65" font-size="3" text-anchor="middle" fill="#a0aec0" font-family="sans-serif">
            {$chartData['name']}
        </text>
    </svg>
    SVG;
    // --- END PLACEHOLDER ---

    // Clear buffer (discard any previous stray text)
    ob_end_clean();

    // 8. OUTPUT HEADERS & IMAGE
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $svgContent;

} catch (Throwable $e) {
    // 9. THE SAFETY NET
    // If anything fails, clear the buffer and draw the error as an image.
    ob_end_clean();
    
    header('Content-Type: image/svg+xml');
    $errorMsg = htmlspecialchars($e->getMessage());
    $line = $e->getLine();
    $file = basename($e->getFile());
    
    echo <<<SVG
    <svg width="800" height="200" xmlns="http://www.w3.org/2000/svg">
        <rect width="100%" height="100%" fill="#fee2e2"/>
        <text x="10" y="30" font-family="monospace" font-size="14" fill="#991b1b" font-weight="bold">
            Quantum Error:
        </text>
        <text x="10" y="60" font-family="monospace" font-size="12" fill="#b91c1c">
            {$errorMsg}
        </text>
        <text x="10" y="80" font-family="monospace" font-size="10" fill="#7f1d1d">
            in {$file} on line {$line}
        </text>
    </svg>
    SVG;
}
?>
