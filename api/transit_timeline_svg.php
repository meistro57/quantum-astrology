<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Charts\TransitTimeline;

header('Content-Type: image/svg+xml');

$id = (int)($_GET['id'] ?? 0);
$days = (int)($_GET['days'] ?? 30);
$width = (int)($_GET['width'] ?? 1000);
$height = 400;

if ($id <= 0) { echo errorSvg("Chart ID required"); exit; }

try {
    $chart = Chart::findById($id);
    if (!$chart) throw new Exception("Chart not found");
    
    $timeline = new TransitTimeline($chart);
    $data = $timeline->calculateSeries(new DateTime(), $days);
} catch (Throwable $e) {
    echo errorSvg($e->getMessage()); exit;
}

$padding = ['top' => 30, 'right' => 100, 'bottom' => 30, 'left' => 40];
$graphW = $width - $padding['left'] - $padding['right'];
$graphH = $height - $padding['top'] - $padding['bottom'];
$orbMax = $data['orb_max'];

$yScale = function($deg) use ($graphH, $orbMax) {
    // 0 deg deviation = Bottom (high intensity), Max Orb = Top (low intensity)
    $norm = 1 - ($deg / $orbMax); // 1 at exact, 0 at max orb
    return $graphH - ($norm * $graphH);
};
$xScale = $graphW / count($data['dates']);

echo '<?xml version="1.0" standalone="no"?>';
?>
<svg width="<?= $width ?>" height="<?= $height ?>" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <style>
            .bg { fill: #0f1424; }
            .grid { stroke: #232a40; stroke-width: 1; }
            .line { fill: none; stroke-width: 3; stroke-linecap: round; }
            .text { font-family: sans-serif; fill: #6b7280; font-size: 10px; }
            .label { font-family: sans-serif; font-size: 12px; font-weight: bold; fill: #e2e8f0; }
            .Conjunction { stroke: #ffd700; }
            .Opposition { stroke: #ef4444; }
            .Square { stroke: #ef4444; }
            .Trine { stroke: #10b981; }
        </style>
    </defs>
    <rect width="100%" height="100%" class="bg" />
    <g transform="translate(<?= $padding['left'] ?>, <?= $padding['top'] ?>)">
        <?php foreach ($data['dates'] as $i => $date): if ($i % 5 === 0): ?>
            <line x1="<?= $i * $xScale ?>" y1="0" x2="<?= $i * $xScale ?>" y2="<?= $graphH ?>" class="grid" />
            <text x="<?= $i * $xScale ?>" y="<?= $graphH + 15 ?>" class="text"><?= $date ?></text>
        <?php endif; endforeach; ?>
        
        <text x="5" y="10" class="text">Exact (High Intensity)</text>
        <line x1="0" y1="<?= $graphH ?>" x2="<?= $graphW ?>" y2="<?= $graphH ?>" stroke="#4A90E2" stroke-width="2" />

        <?php foreach ($data['series'] as $s): ?>
            <?php
            $d = ""; $lx = 0; $ly = 0;
            foreach ($s['points'] as $i => $val) {
                if ($val === null) continue;
                $x = $i * $xScale;
                $y = $yScale($val);
                $d .= ($d === "") ? "M $x,$y" : " L $x,$y";
                $lx = $x; $ly = $y;
            }
            if ($d) {
                echo "<path d='$d' class='line {$s['type']}' />";
                echo "<text x='" . ($lx + 5) . "' y='$ly' class='label'>{$s['label']}</text>";
            }
            ?>
        <?php endforeach; ?>
    </g>
</svg>
<?php
function errorSvg($m) { return "<svg width='400' height='50' xmlns='http://www.w3.org/2000/svg'><text x='10' y='30' fill='red'>$m</text></svg>"; }
?>
