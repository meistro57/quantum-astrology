<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$userCharts = Chart::findByUserId($user->getId(), 10);

$pageTitle = 'Forecasting Tools - Quantum Astrology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .forecasting-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
        }

        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .tool-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .tool-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .tool-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-gradient);
        }

        .tool-card.transits { --card-gradient: linear-gradient(90deg, #00d4ff, #4A90E2); }
        .tool-card.progressions { --card-gradient: linear-gradient(90deg, #8b5cf6, #a855f7); }
        .tool-card.solar-returns { --card-gradient: linear-gradient(90deg, #FFD700, #FFA500); }
        .tool-card.relationships { --card-gradient: linear-gradient(90deg, #FF6B6B, #FF8E53); }

        .tool-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .tool-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .transits .tool-title { color: #00d4ff; }
        .progressions .tool-title { color: #a855f7; }
        .solar-returns .tool-title { color: #FFD700; }
        .relationships .tool-title { color: #FF6B6B; }

        .tool-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }

        .tool-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tool-features li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .tool-features li::before {
            content: '✓';
            color: var(--quantum-gold);
            font-weight: bold;
        }

        .chart-selector-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 1.5rem;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
        }

        .quick-link {
            padding: 0.75rem 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .quick-link:hover {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            border-color: var(--quantum-primary);
        }

        .no-charts {
            text-align: center;
            padding: 2rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .no-charts h3 {
            color: var(--quantum-gold);
            margin-bottom: 1rem;
        }

        .btn-create {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .tools-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>

    <header style="padding: 1rem 2rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold; color: var(--quantum-gold);">QUANTUM ASTROLOGY</div>
        <nav style="display: flex; gap: 1.5rem;">
            <a href="/" style="color: white; text-decoration: none;">Dashboard</a>
            <a href="/charts" style="color: white; text-decoration: none;">Charts</a>
            <a href="/forecasting" style="color: var(--quantum-gold); text-decoration: none;">Forecasting</a>
        </nav>
    </header>

    <div class="forecasting-container">
        <div class="page-header">
            <h1 class="page-title">Forecasting Tools</h1>
            <p class="page-description">
                Explore the cosmic currents influencing your life through our suite of predictive astrology tools.
                From daily transits to yearly solar returns, understand your personal timing cycles.
            </p>
        </div>

        <div class="tools-grid">
            <a href="/charts/transits" class="tool-card transits">
                <div class="tool-icon">&#128640;</div>
                <h2 class="tool-title">Transit Analysis</h2>
                <p class="tool-description">
                    Track the current planetary movements and their aspects to your natal chart.
                    Understand the daily, weekly, and monthly cosmic influences affecting your life.
                </p>
                <ul class="tool-features">
                    <li>Current transiting planets</li>
                    <li>Transits to natal aspects</li>
                    <li>Upcoming exact transits</li>
                    <li>Transit timing windows</li>
                </ul>
            </a>

            <a href="/charts/progressions" class="tool-card progressions">
                <div class="tool-icon">&#127793;</div>
                <h2 class="tool-title">Secondary Progressions</h2>
                <p class="tool-description">
                    Discover your inner evolution through the symbolic unfolding of your chart.
                    Each day after birth represents a year of psychological and spiritual development.
                </p>
                <ul class="tool-features">
                    <li>Progressed planetary positions</li>
                    <li>Progressed Moon phases</li>
                    <li>Progressed-to-natal aspects</li>
                    <li>Life phase timing</li>
                </ul>
            </a>

            <a href="/charts/solar-returns" class="tool-card solar-returns">
                <div class="tool-icon">&#9728;</div>
                <h2 class="tool-title">Solar Returns</h2>
                <p class="tool-description">
                    Your annual cosmic birthday chart reveals the major themes and opportunities
                    for the year ahead. Calculated for the exact moment the Sun returns to its natal position.
                </p>
                <ul class="tool-features">
                    <li>Annual birthday charts</li>
                    <li>Year-ahead themes</li>
                    <li>Annual profections</li>
                    <li>Relocated solar returns</li>
                </ul>
            </a>

            <a href="/charts/relationships" class="tool-card relationships">
                <div class="tool-icon">&#128150;</div>
                <h2 class="tool-title">Relationship Analysis</h2>
                <p class="tool-description">
                    Explore the cosmic dynamics between two people through synastry and composite charts.
                    Understand compatibility, challenges, and the purpose of your connections.
                </p>
                <ul class="tool-features">
                    <li>Synastry aspects</li>
                    <li>Composite charts</li>
                    <li>Compatibility scoring</li>
                    <li>Relationship insights</li>
                </ul>
            </a>
        </div>

        <?php if (!empty($userCharts)): ?>
        <div class="chart-selector-section">
            <h2 class="section-title">Quick Access: Your Charts</h2>
            <div class="quick-links">
                <?php foreach (array_slice($userCharts, 0, 6) as $chart): ?>
                    <a href="/charts/view?id=<?= $chart->getId() ?>" class="quick-link">
                        <?= htmlspecialchars($chart->getName()) ?>
                    </a>
                <?php endforeach ?>
                <?php if (count($userCharts) > 6): ?>
                    <a href="/charts" class="quick-link">View All Charts</a>
                <?php endif ?>
            </div>
        </div>
        <?php else: ?>
        <div class="chart-selector-section no-charts">
            <h3>Get Started</h3>
            <p>Create your first natal chart to access all forecasting tools.</p>
            <a href="/charts/create" class="btn-create">Create Chart</a>
        </div>
        <?php endif ?>
    </div>

    <script>
        // Particle animation
        const particlesContainer = document.querySelector('.particles-container');

        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 15 + 's';
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particlesContainer.appendChild(particle);
            setTimeout(() => particle.remove(), 20000);
        }

        for (let i = 0; i < 40; i++) {
            setTimeout(createParticle, i * 250);
        }
        setInterval(createParticle, 1500);
    </script>
</body>
</html>
