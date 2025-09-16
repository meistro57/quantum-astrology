<?php
# api/chart_svg.php
declare(strict_types=1);

require __DIR__ . '/../classes/autoload.php';

use QuantumAstrology\Charts\ChartService;

header('Content-Type: image/svg+xml');

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$size = isset($_GET['size']) ? max(400, min(2000, (int)$_GET['size'])) : 800;

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
$R  = $size * 0.45;      // outer ring
$rH = $size * 0.28;      // house ring inner radius
$rP = $size * 0.365;     // planet radius
$rIn= $size * 0.18;      // inner hole

$houses = $chart['houses'] ?? null;
$asc = is_array($houses) ? (float)($houses['angles']['ASC'] ?? 0.0) : 0.0;
/** Rotate so ASC sits at 180° display (left). Display angle = (lon + rot) % 360 */
$rotation = 180.0 - $asc;

$planets = $chart['planets'] ?? [];
$abbr = [
    'Sun'=>'Su','Moon'=>'Mo','Mercury'=>'Me','Venus'=>'Ve','Mars'=>'Ma','Jupiter'=>'Ju','Saturn'=>'Sa',
    'Uranus'=>'Ur','Neptune'=>'Ne','Pluto'=>'Pl','Chiron'=>'Ch','mean Node'=>'MN','true Node'=>'TN'
];

function deg2radf(float $d): float { return $d * M_PI / 180.0; }
function disp(float $deg, float $rot): float { $x = fmod($deg + $rot, 360.0); return $x < 0 ? $x + 360.0 : $x; }
function pt(float $cx,float $cy,float $r,float $deg): array {
    $t = deg2radf($deg);
    // Screen coords: 0° to the right, +deg CCW
    return [$cx + $r * cos($t), $cy + $r * sin($t)];
}

echo "<?xml version='1.0' encoding='UTF-8'?>\n";
printf("<svg xmlns='http://www.w3.org/2000/svg' width='%d' height='%d' viewBox='0 0 %d %d'>\n", $size, $size, $size, $size);
echo "<defs>\n";
echo "  <style><![CDATA[
        .bg{fill:#fff}
        .ring{fill:none;stroke:#111;stroke-width:2}
        .thin{stroke:#555;stroke-width:1}
        .house{stroke:#111;stroke-width:1.5}
        .axis{stroke:#111;stroke-width:2.5;stroke-dasharray:4 3}
        .label{font-family:system-ui, -apple-system, Segoe UI, Inter, Roboto, Arial, sans-serif; fill:#111}
        .hnum{font-size:11px; fill:#333}
        .plabel{font-size:12px}
        .title{font-size:14px; font-weight:600}
    ]]></style>\n";
echo "</defs>\n";

echo "<rect class='bg' x='0' y='0' width='$size' height='$size' rx='12' ry='12'/>\n";

/* Outer and inner rings */
printf("<circle class='ring' cx='%f' cy='%f' r='%f' />\n", $cx, $cy, $R);
printf("<circle class='ring' cx='%f' cy='%f' r='%f' />\n", $cx, $cy, $rH);
printf("<circle class='ring' cx='%f' cy='%f' r='%f' />\n", $cx, $cy, $rIn);

/* Zodiac ticks every 30° (minor aesthetics) */
for ($k=0;$k<12;$k++){
    $deg = disp($k*30.0, $rotation);
    [$x1,$y1] = pt($cx,$cy,$R,  $deg);
    [$x2,$y2] = pt($cx,$cy,$R-8,$deg);
    printf("<line class='thin' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $x1,$y1,$x2,$y2);
}

/* Houses (cusps lines) */
if (is_array($houses) && isset($houses['cusps'])) {
    for ($i=1;$i<=12;$i++){
        $c = (float)($houses['cusps'][$i] ?? 0.0);
        $deg = disp($c, $rotation);
        [$x1,$y1] = pt($cx,$cy,$rH,$deg);
        [$x2,$y2] = pt($cx,$cy,$R,$deg);
        printf("<line class='house' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $x1,$y1,$x2,$y2);

        // House number half-way between this cusp and next
        $next = (float)($houses['cusps'][$i%12 + 1] ?? ($c + 30.0));
        $mid = disp($c + fmod(($next - $c + 360.0),360.0)/2.0, $rotation);
        [$tx,$ty] = pt($cx,$cy, ($R + $rH)/2.0, $mid);
        printf("<text class='label hnum' x='%.2f' y='%.2f' text-anchor='middle' dominant-baseline='middle'>%d</text>\n",
            $tx, $ty, $i);
    }

    /* ASC/MC axes */
    $ascD = disp((float)$houses['angles']['ASC'], $rotation);
    $mcD  = disp((float)$houses['angles']['MC'],  $rotation);
    [$ax1,$ay1] = pt($cx,$cy,$rIn,$ascD); [$ax2,$ay2] = pt($cx,$cy,$R,$ascD);
    [$mx1,$my1] = pt($cx,$cy,$rIn,$mcD);  [$mx2,$my2] = pt($cx,$cy,$R,$mcD);
    printf("<line class='axis' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $ax1,$ay1,$ax2,$ay2);
    printf("<line class='axis' x1='%.2f' y1='%.2f' x2='%.2f' y2='%.2f' />\n", $mx1,$my1,$mx2,$my2);
}

/* Planets: dots + labels */
$idx=0;
foreach ($planets as $p) {
    $name = (string)$p['planet'];
    $lon  = (float)$p['lon'];
    $deg  = disp($lon, $rotation);

    [$px,$py] = pt($cx,$cy,$rP, $deg);
    [$tx,$ty] = pt($cx,$cy,$rP + 16 + (($idx++ % 2) ? 6 : 0), $deg); // stagger labels a smidge

    printf("<circle cx='%.2f' cy='%.2f' r='3.2' fill='#111' />\n", $px,$py);
    $label = $abbr[$name] ?? preg_replace('/\s+/', '', substr($name,0,2));
    printf("<text class='label plabel' x='%.2f' y='%.2f' text-anchor='middle' dominant-baseline='middle'>%s</text>\n", $tx,$ty, htmlspecialchars($label, ENT_QUOTES));
}

/* Title / meta */
$title = htmlspecialchars($chart['name'] ?? ('Chart #'.$id), ENT_QUOTES);
printf("<text class='label title' x='%.1f' y='%.1f' text-anchor='middle'>%s</text>\n", $cx, $size-18, $title);

echo "</svg>";
