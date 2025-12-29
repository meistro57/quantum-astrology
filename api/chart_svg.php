<?php
# api/chart_svg.php (safe points, no destructuring assignments)
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Charts\ChartService;

header('Content-Type: image/svg+xml');
// Prevent any caching of the SVG in browsers/proxies
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$size = isset($_GET['size']) ? max(400, min(2000, (int)$_GET['size'])) : 900;

if ($id <= 0) {
    http_response_code(400);
    echo "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='80'><text x='10' y='50' font-family='system-ui' font-size='16'>id required</text></svg>";
    exit;
}

$chart = ChartService::get($id);
if (!$chart) {
    http_response_code(404);
    echo "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='80'><text x='10' y='50' font-family='system-ui' font-size='16'>chart not found</text></svg>";
    exit;
}

$cx = $cy = $size / 2;
$R   = $size * 0.45;   // outer ring
$rH  = $size * 0.28;   // house ring inner radius
$rP  = $size * 0.365;  // planet radius
$rIn = $size * 0.18;   // inner hole
$rAsp= $size * 0.24;   // aspect line radius

$houses = $chart['houses'] ?? null;
$asc = is_array($houses) ? (float)($houses['angles']['ASC'] ?? 0.0) : 0.0;
/** Rotate so ASC sits left (9 o’clock) */
$rotation = 180.0 - $asc;

$planets = $chart['planets'] ?? [];
$aspects = $chart['aspects'] ?? [];

$abbr = [
    'Sun'=>'Su','Moon'=>'Mo','Mercury'=>'Me','Venus'=>'Ve','Mars'=>'Ma','Jupiter'=>'Ju','Saturn'=>'Sa',
    'Uranus'=>'Ur','Neptune'=>'Ne','Pluto'=>'Pl','Chiron'=>'Ch','mean Node'=>'MN','true Node'=>'TN'
];

function deg2radf(float $d): float { return $d * M_PI / 180.0; }
function disp(float $deg, float $rot): float { $x = fmod($deg + $rot, 360.0); return $x < 0 ? $x + 360.0 : $x; }
/** Return point as assoc array to avoid non-writable destructures */
function pt(float $cx,float $cy,float $r,float $deg): array {
    $t = deg2radf($deg);
    return ['x' => $cx + $r * cos($t), 'y' => $cy + $r * sin($t)];
}

echo "<?xml version='1.0' encoding='UTF-8'?>\n";
printf("<svg xmlns='http://www.w3.org/2000/svg' width='%d' height='%d' viewBox='0 0 %d %d'>\n", $size, $size, $size, $size);
echo "<defs>\n";
echo "  <style><![CDATA[
        .bg{fill:#fff}
        .ring{fill:none;stroke:#111;stroke-width:2}
        .thin{stroke:#777;stroke-width:1}
        .house{stroke:#111;stroke-width:1.5}
        .axis{stroke:#111;stroke-width:2.5;stroke-dasharray:4 3}
        .label{font-family:system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial,sans-serif; fill:#111}
        .hnum{font-size:11px; fill:#333}
        .plabel{font-size:12px}
        .title{font-size:14px; font-weight:600}
        .asp{stroke-width:1.6; fill:none; opacity:.9}
        .Conjunction{stroke:#222}
        .Opposition{stroke:#222; stroke-dasharray:6 4}
        .Trine{stroke:#2a7}
        .Square{stroke:#e33}
        .Sextile{stroke:#17a}
        .Quincunx{stroke:#a71; stroke-dasharray:2 4}
        .Semisextile{stroke:#888; stroke-dasharray:2 3}
        .legend{font-size:11px; fill:#333}
    ]]></style>\n";
echo "</defs>\n";

echo "<rect class='bg' x='0' y='0' width='$size' height='$size' rx='12' ry='12'/>\n";

/* Outer and inner rings */
printf("<circle class='ring' cx='%f' cy='%f' r='%f' />\n", $cx, $cy, $R);
printf("<circle class='ring' cx='%f' cy='%f' r='%f' />\n", $cx, $cy, $rH);
printf("<circle class='ring' cx='%f' cy='%f' r='%f' />\n", $cx, $cy, $rIn);

/* Zodiac ticks every 30° */
for ($k=0;$k<12;$k++) {
    $deg = disp($k*30.0, $rotation);
    $p1 = pt($cx,$cy,$R,$deg);
    $p2 = pt($cx,$cy,$R-8,$deg);
    printf("<line class='thin' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $p1['x'],$p1['y'],$p2['x'],$p2['y']);
}

/* Houses (cusps lines) */
if (is_array($houses) && isset($houses['cusps'])) {
    for ($i=1;$i<=12;$i++){
        $c = (float)($houses['cusps'][$i] ?? 0.0);
        $deg = disp($c, $rotation);
        $p1 = pt($cx,$cy,$rH,$deg);
        $p2 = pt($cx,$cy,$R,$deg);
        printf("<line class='house' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $p1['x'],$p1['y'],$p2['x'],$p2['y']);

        $next = (float)($houses['cusps'][$i%12 + 1] ?? ($c + 30.0));
        $span = fmod(($next - $c + 360.0),360.0);
        $mid = disp($c + $span/2.0, $rotation);
        $t = pt($cx,$cy, ($R + $rH)/2.0, $mid);
        printf("<text class='label hnum' x='%.2f' y='%.2f' text-anchor='middle' dominant-baseline='middle'>%d</text>\n",
            $t['x'], $t['y'], $i);
    }

    /* ASC/MC axes */
    $ascD = disp((float)$houses['angles']['ASC'], $rotation);
    $mcD  = disp((float)$houses['angles']['MC'],  $rotation);
    $a1 = pt($cx,$cy,$rIn,$ascD); $a2 = pt($cx,$cy,$R,$ascD);
    $m1 = pt($cx,$cy,$rIn,$mcD);  $m2 = pt($cx,$cy,$R,$mcD);
    printf("<line class='axis' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $a1['x'],$a1['y'],$a2['x'],$a2['y']);
    printf("<line class='axis' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $m1['x'],$m1['y'],$m2['x'],$m2['y']);
}

/* Planets */
$idx=0; $pmap=[];
foreach ($planets as $p) {
    $name = (string)$p['planet'];
    $lon  = (float)$p['lon'];
    $deg  = disp($lon, $rotation);

    $pp = pt($cx,$cy,$rP, $deg);
    $tp = pt($cx,$cy,$rP + 16 + (($idx++ % 2) ? 6 : 0), $deg);

    printf("<circle cx='%.2f' cy='%.2f' r='3.2' fill='#111' />\n", $pp['x'],$pp['y']);
    $label = $abbr[$name] ?? preg_replace('/\s+/', '', substr($name,0,2));
    printf("<text class='label plabel' x='%.2f' y='%.2f' text-anchor='middle' dominant-baseline='middle'>%s</text>\n", $tp['x'],$tp['y'], htmlspecialchars($label, ENT_QUOTES));
    $pmap[$name] = $deg; // for aspect lines
}

/* Aspect chords */
foreach ($aspects as $a) {
    $A = $a['a'] ?? null; $B = $a['b'] ?? null;
    if (!isset($pmap[$A], $pmap[$B])) continue;

    $d1 = $pmap[$A]; $d2 = $pmap[$B];
    $q1 = pt($cx,$cy,$rAsp, $d1);
    $q2 = pt($cx,$cy,$rAsp, $d2);
    $cls = preg_replace('/[^A-Za-z]/','', (string)($a['type'] ?? ''));
    printf("<line class='asp %s' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $cls, $q1['x'],$q1['y'],$q2['x'],$q2['y']);
}

/* Legend */
$legend = [
    ['Conjunction','solid'],
    ['Opposition','dashed'],
    ['Trine','solid'],
    ['Square','solid'],
    ['Sextile','solid'],
    ['Quincunx','dashed'],
    ['Semisextile','dashed'],
];
$lx = 20; $ly = $size - 110;
echo "<g class='legend'>";
echo "<text class='legend' x='".($lx)."' y='".($ly-10)."'>Aspects</text>";
$k=0;
foreach ($legend as $L) {
    $name = $L[0];
    $y = $ly + $k*16;
    printf("<line class='asp %s' x1='%d' y1='%d' x2='%d' y2='%d' />", $name, $lx, $y-4, $lx+24, $y-4);
    printf("<text class='legend' x='%d' y='%d'>%s</text>", $lx+30, $y, $name);
    $k++;
}
echo "</g>";

/* Title */
$title = htmlspecialchars($chart['name'] ?? ('Chart #'.$id), ENT_QUOTES);
printf("<text class='label title' x='%.1f' y='%.1f' text-anchor='middle'>%s</text>\n", $cx, $size-18, $title);

echo "</svg>";
