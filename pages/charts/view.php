<?php
declare(strict_types=1);

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$chartId = (int) ($_GET['id'] ?? 0);

if (!$chartId) {
    header('Location: /dashboard');
    exit;
}

$chart = Chart::findById($chartId);

if (!$chart) {
    header('Location: /dashboard');
    exit;
}

// Check if user has access to this chart
if ($chart->getUserId() !== $user->getId() && !$chart->isPublic()) {
    header('Location: /dashboard');
    exit;
}

$pageTitle = htmlspecialchars($chart->getName()) . ' - Quantum Astrology';
$planetaryPositions = $chart->getPlanetaryPositions();
$housePositions = $chart->getHousePositions();
$aspects = $chart->getAspects();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .chart-viewer {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .chart-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .chart-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .chart-info {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .chart-meta {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .chart-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-wheel-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
        }

        .chart-wheel {
            width: 400px;
            height: 400px;
            border: 2px solid var(--quantum-primary);
            border-radius: 50%;
            position: relative;
            background: radial-gradient(circle, rgba(74, 144, 226, 0.1) 0%, transparent 70%);
        }

        .chart-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
        }

        .info-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }

        .planet-list, .house-list, .aspect-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .planet-item, .house-item, .aspect-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .planet-item:last-child, .house-item:last-child, .aspect-item:last-child {
            border-bottom: none;
        }

        .planet-name, .house-name, .aspect-name {
            font-weight: 500;
            color: var(--quantum-text);
        }

        .planet-position, .house-position, .aspect-detail {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .aspect-orb {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .chart-actions {
            text-align: center;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin: 0 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--quantum-text);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .no-data {
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            font-style: italic;
            padding: 2rem;
        }

        .chart-wheel-placeholder {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
        }

        .chart-wheel-placeholder h3 {
            color: var(--quantum-gold);
            margin-bottom: 1rem;
        }

        @media (max-width: 968px) {
            .chart-content {
                grid-template-columns: 1fr;
            }
            
            .chart-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    
    <div class="chart-viewer">
        <div class="chart-header">
            <h1 class="chart-title"><?= htmlspecialchars($chart->getName()) ?></h1>
            
            <?php if ($chart->getBirthDatetime()): ?>
                <div class="chart-info">
                    <?= $chart->getBirthDatetime()->format('F j, Y \a\t g:i A') ?>
                    <?php if ($chart->getBirthLocationName()): ?>
                        • <?= htmlspecialchars($chart->getBirthLocationName()) ?>
                    <?php endif ?>
                </div>
            <?php endif ?>
            
            <div class="chart-meta">
                <span>Chart Type: <?= ucfirst(htmlspecialchars($chart->getChartType())) ?></span>
                <span>House System: <?= htmlspecialchars($chart->getHouseSystem()) ?></span>
                <?php if ($chart->getBirthTimezone()): ?>
                    <span>Timezone: <?= htmlspecialchars($chart->getBirthTimezone()) ?></span>
                <?php endif ?>
                <span>Created: <?= date('M j, Y', strtotime($chart->getCreatedAt())) ?></span>
            </div>
        </div>

        <div class="chart-content">
            <div class="chart-wheel-container">
                <?php if ($planetaryPositions): ?>
                    <div class="chart-wheel">
                        <img src="/api/charts/<?= $chart->getId() ?>/wheel" 
                             alt="<?= htmlspecialchars($chart->getName()) ?> Chart Wheel"
                             style="width: 100%; max-width: 400px; height: auto;">
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <h3>Chart Calculation Error</h3>
                        <p>Unable to calculate planetary positions for this chart.</p>
                    </div>
                <?php endif ?>
            </div>

            <div class="chart-sidebar">
                <!-- Planetary Positions -->
                <div class="info-card">
                    <h3 class="info-card-title">Planetary Positions</h3>
                    <?php if ($planetaryPositions && is_array($planetaryPositions)): ?>
                        <ul class="planet-list">
                            <?php foreach ($planetaryPositions as $planet => $position): ?>
                                <?php if ($position && is_array($position) && isset($position['longitude'])): ?>
                                    <li class="planet-item">
                                        <span class="planet-name"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($planet))) ?></span>
                                        <span class="planet-position">
                                            <?= number_format($position['longitude'], 2) ?>°
                                            <?php
                                            $sign = floor($position['longitude'] / 30);
                                            $signs = ['Ari', 'Tau', 'Gem', 'Can', 'Leo', 'Vir', 'Lib', 'Sco', 'Sag', 'Cap', 'Aqu', 'Pis'];
                                            echo ' ' . ($signs[$sign] ?? '');
                                            ?>
                                        </span>
                                    </li>
                                <?php endif ?>
                            <?php endforeach ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data">No planetary position data available</div>
                    <?php endif ?>
                </div>

                <!-- House Cusps -->
                <div class="info-card">
                    <h3 class="info-card-title">House Cusps</h3>
                    <?php if ($housePositions && is_array($housePositions)): ?>
                        <ul class="house-list">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <?php if (isset($housePositions[$i]) && isset($housePositions[$i]['cusp'])): ?>
                                    <li class="house-item">
                                        <span class="house-name">House <?= $i ?></span>
                                        <span class="house-position">
                                            <?= number_format($housePositions[$i]['cusp'], 2) ?>°
                                            <?= htmlspecialchars($housePositions[$i]['sign'] ?? '') ?>
                                        </span>
                                    </li>
                                <?php endif ?>
                            <?php endfor ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data">No house cusp data available</div>
                    <?php endif ?>
                </div>

                <!-- Major Aspects -->
                <div class="info-card">
                    <h3 class="info-card-title">Major Aspects</h3>
                    <?php if ($aspects && is_array($aspects) && count($aspects) > 0): ?>
                        <ul class="aspect-list">
                            <?php 
                            // Show only first 10 aspects to avoid clutter
                            $displayAspects = array_slice($aspects, 0, 10);
                            foreach ($displayAspects as $aspect): ?>
                                <li class="aspect-item">
                                    <span class="aspect-name">
                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($aspect['planet1']))) ?>
                                        <?= ucfirst(htmlspecialchars($aspect['aspect'])) ?>
                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($aspect['planet2']))) ?>
                                    </span>
                                    <span class="aspect-orb">
                                        <?= number_format($aspect['orb'], 1) ?>° orb
                                    </span>
                                </li>
                            <?php endforeach ?>
                            <?php if (count($aspects) > 10): ?>
                                <li class="aspect-item">
                                    <span style="color: rgba(255, 255, 255, 0.5); font-style: italic;">
                                        ... and <?= count($aspects) - 10 ?> more aspects
                                    </span>
                                </li>
                            <?php endif ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data">No aspect data available</div>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <div class="chart-actions">
            <a href="/dashboard" class="btn btn-secondary">Back to Dashboard</a>
            <?php if ($chart->getUserId() === $user->getId()): ?>
                <a href="/charts/edit?id=<?= $chart->getId() ?>" class="btn btn-primary">Edit Chart</a>
            <?php endif ?>
            <a href="/api/charts/<?= $chart->getId() ?>/export" class="btn btn-primary">Export Data</a>
        </div>
    </div>

    <script>
        // Add particle animation
        const particlesContainer = document.querySelector('.particles-container');
        
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 15 + 's';
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particlesContainer.appendChild(particle);
            
            setTimeout(() => {
                particle.remove();
            }, 20000);
        }
        
        for (let i = 0; i < 50; i++) {
            setTimeout(createParticle, i * 200);
        }
        
        setInterval(() => {
            createParticle();
        }, 1000);
    </script>
</body>
</html>