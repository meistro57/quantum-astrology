<?php
# api/chart_svg.php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Charts\ChartService;

header('Content-Type: image/svg+xml');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Increased default size for better resolution
$size = isset($_GET['size']) ? max(600, min(3000, (int)$_GET['size'])) : 1200;

if ($id <= 0) {
    http_response_code(400);
    echo "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 20'><text x='10' y='15'>ID Required</text></svg>";
    exit;
}

$chart = ChartService::get($id);
if (!$chart) {
    http_response_code(404);
    echo "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 20'><text x='10' y='15'>Chart Not Found</text></svg>";
    exit;
}

// -- Geometry Settings --
$cx = $cy = $size / 2;
// Radii (percentages of size)
$R_Outer   = $size * 0.48; // Zodiac Outer Ring
$R_Inner   = $size * 0.40; // Zodiac Inner Ring
$R_House   = $size * 0.28; // House Inner Ring
$R_Aspect  = $size * 0.24; // Aspect Chord Limit
$R_Planet  = $size * 0.34; // Planet Glyph Radius (Base)

// -- Data Prep --
$houses = $chart['houses'] ?? null;
$asc = is_array($houses) ? (float)($houses['angles']['ASC'] ?? 0.0) : 0.0;
// Standard: ASC at 9 o'clock (180 degrees in SVG space where 0 is 3 o'clock)
$rotation = 180.0 - $asc;

$planets = $chart['planets'] ?? [];
$aspects = $chart['aspects'] ?? [];

// Helper Math
function rad(float $deg): float { return $deg * M_PI / 180.0; }
function norm(float $deg): float { $x = fmod($deg, 360.0); return $x < 0 ? $x + 360.0 : $x; }
function xy(float $cx, float $cy, float $r, float $deg): array {
    return ['x' => $cx + $r * cos(rad($deg)), 'y' => $cy + $r * sin(rad($deg))];
}

// -- Collision Detection Logic --
// Sort planets by angle to process overlapping clusters
$planetRenderList = [];
foreach ($planets as $p) {
    $rawDeg = norm((float)$p['lon'] + $rotation);
    $planetRenderList[] = [
        'name' => $p['planet'],
        'raw_deg' => $rawDeg,
        'label_deg' => $rawDeg, // Initial position
        'symbol' => substr($p['planet'], 0, 2), // Fallback symbol
        'is_retro' => false // TODO: Add retrograde check from source
    ];
}
usort($planetRenderList, fn($a, $b) => $a['raw_deg'] <=> $b['raw_deg']);

// Simple iterative repulsion to separate labels
// We run this multiple times to settle the positions
for ($iter = 0; $iter < 10; $iter++) {
    for ($i = 0; $i < count($planetRenderList); $i++) {
        $curr = &$planetRenderList[$i];
        $next = &$planetRenderList[($i + 1) % count($planetRenderList)];
        
        $diff = $next['label_deg'] - $curr['label_deg'];
        if ($diff < 0) $diff += 360; // Wrap around case
        
        // If closer than 6 degrees, push apart
        $minSep = 6.0; 
        if ($diff < $minSep) {
            $push = ($minSep - $diff) / 2;
            $curr['label_deg'] -= $push;
            $next['label_deg'] += $push;
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
        .spoke { stroke: #cbd5e0; stroke-width: 1; }
        .cusp { stroke: #2d3748; stroke-width: 1.5; }
        .axis { stroke: #1a202c; stroke-width: 3; stroke-linecap: round; }
        .tick { stroke: #a0aec0; stroke-width: 1; }
        .glyph { font-family: 'Segoe UI Symbol', 'DejaVu Sans', sans-serif; font-size: <?= $size * 0.025 ?>px; font-weight: bold; fill: #1a202c; }
        .deg-text { font-family: sans-serif; font-size: <?= $size * 0.012 ?>px; fill: #718096; }
        
        /* Aspects */
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
    
    // SVG Arc Path
    $p1 = xy($cx, $cy, $R_Outer, $start);
    $p2 = xy($cx, $cy, $R_Outer, $end);
    $p3 = xy($cx, $cy, $R_Inner, $end);
    $p4 = xy($cx, $cy, $R_Inner, $start);
    
    $largeArc = 0; // 30 degrees is always small
    $elClass = 'sign-bg-' . $elements[$i % 4];
    
    printf("<path d='M %.1f %.1f A %.1f %.1f 0 %d 1 %.1f %.1f L %.1f %.1f A %.1f %.1f 0 %d 0 %.1f %.1f Z' class='%s' stroke='none' />\n",
        $p1['x'], $p1['y'], $R_Outer, $R_Outer, $largeArc, $p2['x'], $p2['y'],
        $p3['x'], $p3['y'], $R_Inner, $R_Inner, $largeArc, $p4['x'], $p4['y'],
        $elClass
    );
}
?>

<circle class="ring" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $R_Outer ?>" />
<circle class="ring" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $R_Inner ?>" />
<circle class="ring" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $R_House ?>" />

<?php
if (isset($houses['cusps'])) {
    foreach ($houses['cusps'] as $h => $deg) {
        $drawDeg = norm($deg + $rotation);
        $p1 = xy($cx, $cy, $R_House, $drawDeg);
        $p2 = xy($cx, $cy, $R_Inner, $drawDeg);
        
        $isAngle = in_array($h, [1, 4, 7, 10]);
        $cls = $isAngle ? 'axis' : 'cusp';
        
        printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' class='%s' />\n", $p1['x'], $p1['y'], $p2['x'], $p2['y'], $cls);
        
        // House Numbers
        $midDeg = $drawDeg + 15; // Rough approximation, refined logic would calculate midpoint of house width
        $pt = xy($cx, $cy, $R_House * 0.85, $midDeg); // Place inside house ring
        printf("<text x='%.1f' y='%.1f' text-anchor='middle' font-size='10' fill='#999'>%d</text>", $pt['x'], $pt['y'], $h);
    }
}
?>

<?php
// We need a map of Planet Name -> Rendered Coordinates for lines
$planetCoords = [];
foreach ($planetRenderList as $p) {
    $planetCoords[$p['name']] = $p['raw_deg']; // Use RAW degree for aspect accuracy, not label degree
}

foreach ($aspects as $asp) {
    if (!isset($planetCoords[$asp['a']]) || !isset($planetCoords[$asp['b']])) continue;
    $d1 = $planetCoords[$asp['a']];
    $d2 = $planetCoords[$asp['b']];
    
    $pt1 = xy($cx, $cy, $R_Aspect, $d1);
    $pt2 = xy($cx, $cy, $R_Aspect, $d2);
    
    printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' class='asp %s' />\n",
        $pt1['x'], $pt1['y'], $pt2['x'], $pt2['y'], $asp['type']
    );
}
?>

<?php
foreach ($planetRenderList as $p) {
    // 1. Precise Tick Mark
    $tickStart = xy($cx, $cy, $R_Inner, $p['raw_deg']);
    $tickEnd   = xy($cx, $cy, $R_Inner - 10, $p['raw_deg']);
    echo "<line x1='{$tickStart['x']}' y1='{$tickStart['y']}' x2='{$tickEnd['x']}' y2='{$tickEnd['y']}' class='tick' />";
    
    // 2. Connector Line (if label moved)
    $labelPos = xy($cx, $cy, $R_Planet, $p['label_deg']);
    if (abs($p['raw_deg'] - $p['label_deg']) > 1.0) {
        $connEnd = xy($cx, $cy, $R_Inner - 10, $p['raw_deg']);
        printf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' stroke='#cbd5e0' stroke-width='1' />",
            $labelPos['x'], $labelPos['y'], $connEnd['x'], $connEnd['y']
        );
    }
    
    // 3. The Glyph
    // Map common names to unicode if you have a font, otherwise use 2-char abbr
    $abbr = $p['name'] === 'Sun' ? '☉' : ($p['name'] === 'Moon' ? '☽' : substr($p['name'], 0, 2));
    
    printf("<text x='%.1f' y='%.1f' text-anchor='middle' dominant-baseline='central' class='glyph'>%s</text>", 
        $labelPos['x'], $labelPos['y'], $abbr
    );
    
    // 4. Degree text (smaller, further out)
    $degPos = xy($cx, $cy, $R_Planet + ($size * 0.04), $p['label_deg']);
    // Calculate display degree (0-29) relative to sign
    // Note: The logic for "Which sign is this?" needs the absolute longitude from backend
    // For now, we print the whole degree or simple int
    printf("<text x='%.1f' y='%.1f' text-anchor='middle' dominant-baseline='central' class='deg-text'>%d°</text>",
        $degPos['x'], $degPos['y'], floor(fmod($p['raw_deg'] - $rotation, 30)) // Rough approximation for display
    );
}
?>

<text x="20" y="<?= $size - 20 ?>" font-family="sans-serif" font-size="12" fill="#999">Quantum Astrology v1.3</text>
</svg>
