<?php
# api/chart_svg.php
declare(strict_types=1);

// Enable error reporting to catch issues in the SVG output
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require __DIR__ . '/../config.php';

    // Ensure autoloader is present if config didn't load it
    if (!class_exists('QuantumAstrology\Charts\ChartService')) {
        $autoload = __DIR__ . '/../classes/autoload.php';
        if (file_exists($autoload)) require $autoload;
    }

    use QuantumAstrology\Charts\ChartService;

    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $size = isset($_GET['size']) ? max(600, min(3000, (int)$_GET['size'])) : 1200;

    if ($id <= 0) throw new Exception("Invalid Chart ID");

    $chart = ChartService::get($id);
    if (!$chart) throw new Exception("Chart #$id not found");

    // -- Geometry Settings --
    $cx = $cy = $size / 2;
    $R_Outer   = $size * 0.48; 
    $R_Inner   = $size * 0.40;
    $R_House   = $size * 0.28;
    $R_Aspect  = $size * 0.24; 
    $R_Planet  = $size * 0.34; 

    // -- Data Prep --
    $houses = $chart['houses'] ?? null;
    $asc = is_array($houses) ? (float)($houses['angles']['ASC'] ?? 0.0) : 0.0;
    $rotation = 180.0 - $asc;

    $planets = $chart['planets'] ?? [];
    $aspects = $chart['aspects'] ?? [];

    // Helper Math
    function rad(float $deg): float { return $deg * M_PI / 180.0; }
    function norm(float $deg): float { $x = fmod($deg, 360.0); return $x < 0 ? $x + 360.0 : $x; }
    function xy(float $cx, float $cy, float $r, float $deg): array {
        return ['x' => $cx + $r * cos(rad($deg)), 'y' => $cy + $r * sin(rad($deg))];
    }

    // -- Collision Detection Prep --
    $planetRenderList = [];
    foreach ($planets as $p) {
        // Validation to prevent 500s on bad data
        if (!isset($p['lon']) || !isset($p['planet'])) continue; 
        
        $rawDeg = norm((float)$p['lon'] + $rotation);
        $planetRenderList[] = [
            'name' => $p['planet'],
            'raw_deg' => $rawDeg,
            'label_deg' => $rawDeg, 
            'symbol' => substr($p['planet'], 0, 2)
        ];
    }

    // Sort for collision logic
    usort($planetRenderList, fn($a, $b) => $a['raw_deg'] <=> $b['raw_deg']);

    // Iterative Repulsion
    if (count($planetRenderList) > 1) {
        for ($iter = 0; $iter < 12; $iter++) {
            for ($i = 0; $i < count($planetRenderList); $i++) {
                $curr = &$planetRenderList[$i];
                $next = &$planetRenderList[($i + 1) % count($planetRenderList)];
                
                $diff = $next['label_deg'] - $curr['label_deg'];
                if ($diff < 0) $diff += 360; 
                
                $minSep = 5.5; 
                if ($diff < $minSep) {
                    $push = ($minSep - $diff) / 2;
                    $curr['label_deg'] -= $push;
                    $next['label_deg'] += $push;
                }
            }
        }
    }

    // -- SVG Output --
    echo "<?xml version='1.0' encoding='UTF-8'?>\n";
    printf("<svg xmlns='http://www.w3.org/2000/svg' width='%d' height='%d' viewBox='0 0 %d %d'>\n", $size, $size, $size, $size);
    ?>
    <defs>
        <style>
            .bg { fill: #ffffff; }
            .ring { fill: none; stroke: #1a202c; stroke-width: 2; }
            .cusp { stroke: #2d3748; stroke-width: 1.5; }
            .axis { stroke: #1a202c; stroke-width: 3; stroke-linecap: round; }
            .tick { stroke: #a0aec0; stroke-width: 1; }
            .glyph { font-family: 'Segoe UI Symbol', 'DejaVu Sans', sans-serif; font-size: <?= $size * 0.025 ?>px; font-weight: bold; fill: #1a202c; }
            .deg-text { font-family: sans-serif; font-size: <?= $size * 0.012 ?>px; fill: #718096; }
            .asp { fill: none; stroke-opacity: 0.6; stroke-width: 1.5; }
            .Conjunction { stroke: #ecc94b; stroke-width: 3; }
            .Opposition { stroke: #e53e3e; stroke-width: 2; }
            .Square { stroke: #e53e3e; }
            .Trine { stroke: #38a169; }
            .Sextile { stroke: #3182ce; }
            .sign-bg-fire { fill: rgba(255, 100, 100, 0.1); }
            .sign-bg-water { fill: rgba(100, 100, 255, 0.1); }
            .sign-bg-air { fill: rgba(255, 255, 100, 0.1); }
            .sign-bg-earth { fill: rgba(100, 255, 100, 0.1); }
        </style>
    </defs>

    <rect class="bg" width="100%" height="100%" />

    <?php
    $elements = ['fire', 'earth', 'air', 'water'];
    for ($i = 0; $i < 12; $i++) {
        $start = norm(($i * 30) + $rotation);
        $end = norm((($i + 1) * 30) + $rotation);
        $p1 = xy($cx, $cy, $R_Outer, $start);
        $p2 = xy($cx, $cy, $R_Outer, $end);
        $p3 = xy($cx, $cy, $R_Inner, $end);
        $p4 = xy($cx, $cy, $R_Inner, $start);
        $elClass = 'sign-bg-' . $elements[$i % 4];
        printf("<path d='M %.1f %.1f A %.1f %.1f 0 0 1 %.1f %.1f L %.1f %.1f A %.1f %.1f 0 0 0 %.1f %.1f Z' class='%s' stroke='none' />\n",
            $p1['x'], $p1['y'], $R_Outer, $R_Outer, $p2['x'], $p2['y'],
            $p3['x'], $p3['y'], $R_Inner, $R_Inner, $p4['x'], $p4['y'], $elClass
        );
    }
    ?>

    <circle class="ring" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $R_Outer ?>" />
    <circle class="ring" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $R_Inner ?>" />
    <circle class="ring" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $R_House ?>" />

    <?php
    if (isset($houses['cusps'])) {
        foreach ($houses['cusps'] as $h => $deg) {
            $drawDeg = norm((float)$deg + $rotation);
            $p1 = xy($cx, $cy, $R_House, $drawDeg);
            $p2 = xy($cx, $cy, $R_Inner, $drawDeg);
            $cls = in_array($h, [1, 4, 7, 10]) ? 'axis' : 'cusp';
            printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' class='%s' />\n", $p1['x'], $p1['y'], $p2['x'], $p2['y'], $cls);
            // House Number
            $pt = xy($cx, $cy, $R_House * 0.85, $drawDeg + 15); 
            printf("<text x='%.1f' y='%.1f' text-anchor='middle' font-size='10' fill='#999'>%d</text>", $pt['x'], $pt['y'], $h);
        }
    }

    // Aspects
    $planetCoords = [];
    foreach ($planetRenderList as $p) $planetCoords[$p['name']] = $p['raw_deg'];

    foreach ($aspects as $asp) {
        if (!isset($planetCoords[$asp['a']]) || !isset($planetCoords[$asp['b']])) continue;
        $pt1 = xy($cx, $cy, $R_Aspect, $planetCoords[$asp['a']]);
        $pt2 = xy($cx, $cy, $R_Aspect, $planetCoords[$asp['b']]);
        printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' class='asp %s' />\n",
            $pt1['x'], $pt1['y'], $pt2['x'], $pt2['y'], $asp['type']
        );
    }

    // Planets
    foreach ($planetRenderList as $p) {
        $tickStart = xy($cx, $cy, $R_Inner, $p['raw_deg']);
        $tickEnd   = xy($cx, $cy, $R_Inner - 10, $p['raw_deg']);
        echo "<line x1='{$tickStart['x']}' y1='{$tickStart['y']}' x2='{$tickEnd['x']}' y2='{$tickEnd['y']}' class='tick' />";
        
        $labelPos = xy($cx, $cy, $R_Planet, $p['label_deg']);
        if (abs($p['raw_deg'] - $p['label_deg']) > 1.0) {
            $connEnd = xy($cx, $cy, $R_Inner - 10, $p['raw_deg']);
            printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' stroke='#cbd5e0' stroke-width='1' />",
                $labelPos['x'], $labelPos['y'], $connEnd['x'], $connEnd['y']
            );
        }
        
        $abbr = $p['name'] === 'Sun' ? '☉' : ($p['name'] === 'Moon' ? '☽' : substr($p['name'], 0, 2));
        printf("<text x='%.1f' y='%.1f' text-anchor='middle' dominant-baseline='central' class='glyph'>%s</text>", 
            $labelPos['x'], $labelPos['y'], $abbr
        );
        
        $degPos = xy($cx, $cy, $R_Planet + ($size * 0.04), $p['label_deg']);
        printf("<text x='%.1f' y='%.1f' text-anchor='middle' dominant-baseline='central' class='deg-text'>%d°</text>",
            $degPos['x'], $degPos['y'], floor(fmod($p['raw_deg'] - $rotation, 30))
        );
    }
    ?>
    <text x="20" y="<?= $size - 20 ?>" font-family="sans-serif" font-size="12" fill="#999">Quantum Astrology v1.3</text>
    </svg>

<?php
} catch (Throwable $e) {
    // Generate an Error SVG so the user sees the problem directly in the image
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" standalone="no"?>';
    echo '<svg width="800" height="200" xmlns="http://www.w3.org/2000/svg">';
    echo '<rect width="100%" height="100%" fill="#fee" />';
    echo '<text x="10" y="30" font-family="monospace" fill="red" font-size="14">Error Generating Chart:</text>';
    echo '<text x="10" y="60" font-family="monospace" fill="red" font-size="12">' . htmlspecialchars($e->getMessage()) . '</text>';
    echo '<text x="10" y="80" font-family="monospace" fill="#555" font-size="10">File: ' . htmlspecialchars(basename($e->getFile())) . ':' . $e->getLine() . '</text>';
    echo '</svg>';
}
?>
