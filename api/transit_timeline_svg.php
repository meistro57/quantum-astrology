<?php
declare(strict_types=1);

// Error Handling Wrapper
ini_set('display_errors', '0');
try {
    require __DIR__ . '/../config.php';
    
    // Autoload fallback
    if (!class_exists('QuantumAstrology\Charts\Chart')) {
        $autoload = __DIR__ . '/../classes/autoload.php';
        if (file_exists($autoload)) require $autoload;
    }

    use QuantumAstrology\Charts\Chart;
    use QuantumAstrology\Charts\TransitTimeline;

    header('Content-Type: image/svg+xml');
    
    $id = (int)($_GET['id'] ?? 0);
    $days = (int)($_GET['days'] ?? 30);
    $width = (int)($_GET['width'] ?? 1000);
    $height = 400;

    if ($id <= 0) throw new Exception("Chart ID required");

    $chart = Chart::findById($id);
    if (!$chart) throw new Exception("Chart not found");
    
    $timeline = new TransitTimeline($chart);
    $data = $timeline->calculateSeries(new DateTime(), $days);
    
    // -- Rendering --
    $padding = ['top' => 30, 'right' => 100, 'bottom' => 30, 'left' => 40];
    $graphW = $width - $padding['left'] - $padding['right'];
    $graphH = $height - $padding['top'] - $padding['bottom'];
    $orbMax = $data['orb_max'];

    // Y-Scale: 0 dev (Exact) -> Bottom (High Intensity)
    $yScale = function($deg) use ($graphH, $orbMax) {
        $norm = 1 - ($deg / $orbMax); 
        return $graphH - ($norm * $graphH);
    };
    $xScale = $graphW / max(1, count($data['dates']) - 1);

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
                .exact-line { stroke: #4facfe; stroke-width: 2; stroke-dasharray: 4; opacity: 0.5; }
            </style>
        </defs>
        <rect width="100%" height="100%" class="bg" />
        <g transform="translate(<?= $padding['left'] ?>, <?= $padding['top'] ?>)">
            
            <line x1="0" y1="<?= $graphH ?>" x2="<?= $graphW ?>" y2="<?= $graphH ?>" class="exact-line" />
            <text x="5" y="10" class="text">Approaching</text>
            <text x="5" y="<?= $graphH - 5 ?>" class="text" fill="#4facfe">EXACT</text>

            <?php foreach ($data['dates'] as $i => $date): if ($i % 5 === 0): ?>
                <line x1="<?= $i * $xScale ?>" y1="0" x2="<?= $i * $xScale ?>" y2="<?= $graphH ?>" class="grid" />
                <text x="<?= $i * $xScale ?>" y="<?= $graphH + 15 ?>" class="text"><?= $date ?></text>
            <?php endif; endforeach; ?>

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
} catch (Throwable $e) {
    // Error handling SVG
    header('Content-Type: image/svg+xml');
    echo '<svg width="600" height="100" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#300"/><text x="20" y="50" fill="red" font-family="monospace">Error: ' . htmlspecialchars($e->getMessage()) . '</text></svg>';
}
?>
