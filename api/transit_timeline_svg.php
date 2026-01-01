<?php
# api/transit_timeline_svg.php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Charts\ChartService;
use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Charts\TransitTimeline;

header('Content-Type: image/svg+xml');
header('Cache-Control: no-cache');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$days = isset($_GET['days']) ? min(90, max(7, (int)$_GET['days'])) : 30; // Default 30 days
$width = isset($_GET['width']) ? (int)$_GET['width'] : 1000;
$height = 400;

if ($id <= 0) exit_error("Chart ID required");

// 1. Fetch Data
try {
    $chart = Chart::find($id); // Assuming Chart::find works via ORM
    if (!$chart) exit_error("Chart not found");
    
    $timeline = new TransitTimeline($chart);
    $start = new DateTime();
    $data = $timeline->calculateSeries($start, $days);
} catch (Exception $e) {
    exit_error($e->getMessage());
}

// 2. Setup Geometry
$padding = ['top' => 30, 'right' => 100, 'bottom' => 30, 'left' => 40];
$graphW = $width - $padding['left'] - $padding['right'];
$graphH = $height - $padding['top'] - $padding['bottom'];
$orbMax = $data['orb_max']; // e.g. 3 degrees

// Y-Scale: Map degrees (-3 to +3) to pixels (height to 0)
// 0 degrees (exact) should be at $graphH / 2
$yScale = function($deg) use ($graphH, $orbMax) {
    // Invert Y so positive is top
    $norm = $deg / $orbMax; // -1 to 1
    return ($graphH / 2) - ($norm * ($graphH / 2));
};

$xScale = $graphW / count($data['dates']);

// 3. Render SVG
echo '<?xml version="1.0" standalone="no"?>';
?>
<svg width="<?= $width ?>" height="<?= $height ?>" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <style>
            .bg { fill: #0f1424; }
            .grid { stroke: #232a40; stroke-width: 1; }
            .center-line { stroke: #4A90E2; stroke-width: 1; stroke-dasharray: 4; opacity: 0.5; }
            .text { font-family: system-ui, sans-serif; fill: #6b7280; font-size: 10px; }
            .label { font-family: system-ui, sans-serif; font-size: 12px; font-weight: bold; }
            .line { fill: none; stroke-width: 2; }
            .dot { r: 3; }
            /* Aspect Colors */
            .Conjunction { stroke: #ffd700; fill: #ffd700; }
            .Opposition { stroke: #ef4444; fill: #ef4444; }
            .Square { stroke: #ef4444; fill: #ef4444; }
            .Trine { stroke: #10b981; fill: #10b981; }
        </style>
    </defs>
    
    <rect width="100%" height="100%" class="bg" />
    
    <g transform="translate(<?= $padding['left'] ?>, <?= $padding['top'] ?>)">
        
        <line x1="0" y1="<?= $graphH/2 ?>" x2="<?= $graphW ?>" y2="<?= $graphH/2 ?>" class="center-line" />
        <text x="<?= $graphW + 5 ?>" y="<?= $graphH/2 + 4 ?>" class="text" style="fill:#4A90E2">EXACT</text>

        <?php foreach ($data['dates'] as $i => $date): ?>
            <?php if ($i % 2 === 0): // Show every other date to save space ?>
                <line x1="<?= $i * $xScale ?>" y1="0" x2="<?= $i * $xScale ?>" y2="<?= $graphH ?>" class="grid" opacity="0.3" />
                <text x="<?= $i * $xScale ?>" y="<?= $graphH + 15 ?>" text-anchor="middle" class="text"><?= $date ?></text>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php foreach ($data['series'] as $series): ?>
            <?php
                // Build Path
                $d = "";
                $lastX = -1;
                $labelY = -1;
                
                // We need to handle gaps (nulls) by starting new M commands
                foreach ($series['points'] as $i => $val) {
                    if ($val === null || abs($val) > $orbMax) continue;
                    
                    $x = $i * $xScale;
                    $y = $yScale($val);
                    
                    $d .= ($lastX === -1) ? "M {$x},{$y}" : " L {$x},{$y}";
                    $lastX = $x;
                    $labelY = $y; // Store last known Y for label
                    
                    // Draw point if it's very close to exact (crossing 0)
                    if (abs($val) < 0.1) {
                        echo "<circle cx='{$x}' cy='{$y}' class='dot {$series['type']}' />";
                    }
                }
                
                // Reset for next segment check
                if ($d !== ""):
            ?>
            <path d="<?= $d ?>" class="line <?= $series['type'] ?>" />
            <text x="<?= $lastX + 5 ?>" y="<?= $labelY + 4 ?>" class="label <?= $series['type'] ?>" style="stroke:none;">
                <?= $series['label'] ?>
            </text>
            <?php endif; ?>
        <?php endforeach; ?>
        
    </g>
</svg>

<?php
function exit_error($msg) {
    echo "<svg width='400' height='60' xmlns='http://www.w3.org/2000/svg'><text x='10' y='30' fill='red'>Error: $msg</text></svg>";
    exit;
}
