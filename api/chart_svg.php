<?php
# api/chart_svg.php
declare(strict_types=1);

// 1. Safety wrapper to catch fatal errors
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require __DIR__ . '/../config.php';

    // Manual fallback if autoloader is missing in config
    if (!class_exists('QuantumAstrology\Charts\ChartService')) {
        $autoload = __DIR__ . '/../classes/autoload.php';
        if (file_exists($autoload)) require $autoload;
    }

    use QuantumAstrology\Charts\ChartService;

    header('Content-Type: image/svg+xml');
    // Prevent browser caching while debugging
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $size = isset($_GET['size']) ? max(600, min(3000, (int)$_GET['size'])) : 1200;

    if ($id <= 0) throw new Exception("Invalid Chart ID provided");

    $chart = ChartService::get($id);
    if (!$chart) throw new Exception("Chart #$id not found in database");

    // -- Geometry Calculations --
    $cx = $cy = $size / 2;
    $R_Outer   = $size * 0.48; 
    $R_Inner   = $size * 0.40;
    $R_House   = $size * 0.28;
    $R_Aspect  = $size * 0.24; 
    $R_Planet  = $size * 0.34; 

    $houses   = $chart['houses'] ?? null;
    $asc      = is_array($houses) ? (float)($houses['angles']['ASC'] ?? 0.0) : 0.0;
    $rotation = 180.0 - $asc;

    $planets = $chart['planets'] ?? [];
    $aspects = $chart['aspects'] ?? [];

    // Helper Functions
    function rad(float $deg): float { return $deg * M_PI / 180.0; }
    function norm(float $deg): float { $x = fmod($deg, 360.0); return $x < 0 ? $x + 360.0 : $x; }
    function xy(float $cx, float $cy, float $r, float $deg): array {
        return ['x' => $cx + $r * cos(rad($deg)), 'y' => $cy + $r * sin(rad($deg))];
    }

    // -- Prep Planets & Collision Logic --
    $renderList = [];
    foreach ($planets as $p) {
        if (!isset($p['lon'])) continue; // Skip invalid entries
        $raw = norm((float)$p['lon'] + $rotation);
        $renderList[] = [
            'name' => $p['planet'] ?? $p['name'] ?? '?',
            'raw'  => $raw,
            'pos'  => $raw, // adjusted position
            'sym'  => substr($p['planet'] ?? $p['name'] ?? '??', 0, 2)
        ];
    }
    
    // Sort by angle for collision logic
    usort($renderList, fn($a, $b) => $a['raw'] <=> $b['raw']);
    
    // Spread out overlapping labels
    if (count($renderList) > 1) {
        for ($iter = 0; $iter < 10; $iter++) {
            for ($i = 0; $i < count($renderList); $i++) {
                $curr = &$renderList[$i];
                $next = &$renderList[($i + 1) % count($renderList)];
                $diff = $next['pos'] - $curr['pos'];
                if ($diff < 0) $diff += 360;
                
                if ($diff < 5.5) { // 5.5 degrees separation
                    $push = (5.5 - $diff) / 2;
                    $curr['pos'] -= $push;
                    $next['pos'] += $push;
                }
            }
        }
    }

    // -- Draw SVG --
    echo "<?xml version='1.0' encoding='UTF-8'?>\n";
    printf("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 %d %d'>\n", $size, $size);
    ?>
    <defs>
        <style>
            .ring { fill: none; stroke: #1a202c; stroke-width: 2; }
            .cusp { stroke: #2d3748; stroke-width: 1; }
            .axis { stroke: #1a202c; stroke-width: 3; }
            .glyph { font-family: sans-serif; font-size: <?= $size * 0.025 ?>px; font-weight: bold; fill: #111; }
            .deg   { font-family: sans-serif; font-size: <?= $size * 0.012 ?>px; fill: #666; }
            .asp   { stroke-width: 1.5; opacity: 0.6; }
            .Conjunction { stroke: #ecc94b; stroke-width: 3; }
            .Opposition  { stroke: #e53e3e; }
            .Trine       { stroke: #38a169; }
            .Square      { stroke: #e53e3e; }
            .sign-bg-fire { fill: rgba(255, 100, 100, 0.1); }
            .sign-bg-water { fill: rgba(100, 100, 255, 0.1); }
            .sign-bg-air { fill: rgba(255, 255, 100, 0.1); }
            .sign-bg-earth { fill: rgba(100, 255, 100, 0.1); }
        </style>
    </defs>
    
    <rect width="100%" height="100%" fill="white" />

    <?php
    // Zodiac Ring Backgrounds
    $elements = ['fire', 'earth', 'air', 'water'];
    for ($i = 0; $i < 12; $i++) {
        $a1 = norm($i * 30 + $rotation);
        $a2 = norm(($i + 1) * 30 + $rotation);
        $p1 = xy($cx, $cy, $R_Outer, $a1); $p2 = xy($cx, $cy, $R_Outer, $a2);
        $p3 = xy($cx, $cy, $R_Inner, $a2); $p4 = xy($cx, $cy, $R_Inner, $a1);
        printf("<path d='M %.1f %.1f A %.1f %.1f 0 0 1 %.1f %.1f L %.1f %.1f A %.1f %.1f 0 0 0 %.1f %.1f Z' class='sign-bg-%s' stroke='none' />\n",
            $p1['x'], $p1['y'], $R_Outer, $R_Outer, $p2['x'], $p2['y'],
            $p3['x'], $p3['y'], $R_Inner, $R_Inner, $p4['x'], $p4['y'], $elements[$i % 4]);
    }

    // Rings & Cusps
    echo "<circle class='ring' cx='$cx' cy='$cy' r='$R_Outer' />";
    echo "<circle class='ring' cx='$cx' cy='$cy' r='$R_Inner' />";
    if (isset($houses['cusps'])) {
        foreach ($houses['cusps'] as $h => $deg) {
            $d = norm((float)$deg + $rotation);
            $p1 = xy($cx, $cy, $R_House, $d); $p2 = xy($cx, $cy, $R_Inner, $d);
            $cls = in_array($h, [1,4,7,10]) ? 'axis' : 'cusp';
            printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' class='%s' />", $p1['x'], $p1['y'], $p2['x'], $p2['y'], $cls);
        }
    }

    // Aspects
    $pmap = [];
    foreach ($renderList as $p) $pmap[$p['name']] = $p['raw'];
    foreach ($aspects as $a) {
        if (isset($pmap[$a['planet1'] ?? $a['a']], $pmap[$a['planet2'] ?? $a['b']])) {
            $d1 = $pmap[$a['planet1'] ?? $a['a']];
            $d2 = $pmap[$a['planet2'] ?? $a['b']];
            $q1 = xy($cx, $cy, $R_Aspect, $d1); $q2 = xy($cx, $cy, $R_Aspect, $d2);
            printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' class='asp %s' />", 
                $q1['x'], $q1['y'], $q2['x'], $q2['y'], $a['aspect'] ?? $a['type']);
        }
    }

    // Planets
    foreach ($renderList as $p) {
        $pt = xy($cx, $cy, $R_Planet, $p['pos']);
        // Tick mark
        $t1 = xy($cx, $cy, $R_Inner, $p['raw']); $t2 = xy($cx, $cy, $R_Inner-10, $p['raw']);
        printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' stroke='#aaa' />", $t1['x'], $t1['y'], $t2['x'], $t2['y']);
        
        // Connector if shifted
        if (abs($p['raw'] - $p['pos']) > 1) {
            printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' stroke='#ddd' />", $pt['x'], $pt['y'], $t2['x'], $t2['y']);
        }
        
        // Glyph & Degree
        printf("<text x='%.1f' y='%.1f' class='glyph' text-anchor='middle' dominant-baseline='central'>%s</text>", 
            $pt['x'], $pt['y'], $p['sym']);
        $dpt = xy($cx, $cy, $R_Planet + ($size*0.045), $p['pos']);
        printf("<text x='%.1f' y='%.1f' class='deg' text-anchor='middle'>%.0f°</text>", 
            $dpt['x'], $dpt['y'], fmod($p['raw'] - $rotation, 30));
    }
    ?>
    </svg>
<?php
} catch (Throwable $e) {
    // 2. ERROR HANDLER: Generates a valid SVG image containing the error message
    // This allows you to see the error directly in the <img src="..."> tag
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" standalone="no"?>';
    echo '<svg width="800" height="200" xmlns="http://www.w3.org/2000/svg">';
    echo '<rect width="100%" height="100%" fill="#fff0f0" stroke="#ff0000" stroke-width="2" />';
    echo '<text x="20" y="40" font-family="monospace" font-weight="bold" fill="#cc0000" font-size="16">System Malfunction (Error 500)</text>';
    echo '<text x="20" y="80" font-family="monospace" fill="#333" font-size="12">' . htmlspecialchars($e->getMessage()) . '</text>';
    echo '<text x="20" y="110" font-family="monospace" fill="#666" font-size="10">File: ' . htmlspecialchars(basename($e->getFile())) . ':' . $e->getLine() . '</text>';
    echo '</svg>';
}
?>
