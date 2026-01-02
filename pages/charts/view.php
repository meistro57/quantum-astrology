<?php // pages/charts/view.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

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
        
        .tab-button {
            background: none;
            border: none;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            color: var(--quantum-gold);
            border-bottom-color: var(--quantum-gold);
        }
        
        .tab-button:hover {
            color: var(--quantum-text);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        <div class="page-actions">
            <button type="button" class="back-button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='/charts'">
                <span class="icon" aria-hidden="true">←</span>
                <span>Back</span>
            </button>
        </div>
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

        <!-- Advanced Analysis Tabs -->
        <div class="info-card" style="margin-top: 2rem;">
            <div class="analysis-tabs" style="display: flex; border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 1rem; flex-wrap: wrap;">
                <button class="tab-button active" data-tab="transits" onclick="switchTab('transits')">Transits</button>
                <button class="tab-button" data-tab="progressions" onclick="switchTab('progressions')">Progressions</button>
                <button class="tab-button" data-tab="solar-returns" onclick="switchTab('solar-returns')">Solar Returns</button>
                <button class="tab-button" data-tab="interpretation" onclick="switchTab('interpretation')">Interpretation</button>
            </div>
            
            <!-- Transit Tab -->
            <div id="transits-tab" class="tab-content active">
                <h3 class="info-card-title">Current Transits</h3>
                <div id="transit-loading" style="text-align: center; padding: 2rem; color: rgba(255, 255, 255, 0.6);">
                    Loading current transits...
                </div>
                <div id="transit-content" style="display: none;"></div>
                <div style="text-align: center; margin-top: 1rem;">
                    <button id="load-transits" class="btn btn-primary" onclick="loadTransits()">View Current Transits</button>
                    <button id="load-upcoming" class="btn btn-secondary" onclick="loadUpcomingTransits()" style="margin-left: 0.5rem;">Upcoming Transits</button>
                </div>
            </div>
            
            <!-- Progressions Tab -->
            <div id="progressions-tab" class="tab-content">
                <h3 class="info-card-title">Secondary Progressions</h3>
                <div id="progression-loading" style="text-align: center; padding: 2rem; color: rgba(255, 255, 255, 0.6);">
                    Loading progressions...
                </div>
                <div id="progression-content" style="display: none;"></div>
                <div style="text-align: center; margin-top: 1rem;">
                    <button id="load-progressions" class="btn btn-primary" onclick="loadProgressions()">View Current Progressions</button>
                    <input type="date" id="progression-date" style="margin-left: 0.5rem; padding: 0.5rem; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: white;" onchange="loadProgressions()">
                </div>
            </div>
            
            <!-- Solar Returns Tab -->
            <div id="solar-returns-tab" class="tab-content">
                <h3 class="info-card-title">Solar Returns</h3>
                <div id="solar-return-loading" style="text-align: center; padding: 2rem; color: rgba(255, 255, 255, 0.6);">
                    Loading solar returns...
                </div>
                <div id="solar-return-content" style="display: none;"></div>
                <div style="text-align: center; margin-top: 1rem;">
                    <button id="load-solar-returns" class="btn btn-primary" onclick="loadSolarReturns()">View Solar Returns</button>
                    <select id="solar-return-year" style="margin-left: 0.5rem; padding: 0.5rem; border-radius: 5px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: white;" onchange="loadSpecificSolarReturn()">
                        <!-- Years populated by JavaScript -->
                    </select>
                </div>
            </div>
            
            <!-- Interpretation Tab -->
            <div id="interpretation-tab" class="tab-content">
                <h3 class="info-card-title">Chart Interpretation</h3>
                <div id="interpretation-loading" style="text-align: center; padding: 2rem; color: rgba(255, 255, 255, 0.6);">
                    Loading interpretation...
                </div>
                <div id="interpretation-content" style="display: none;"></div>
                <div style="text-align: center; margin-top: 1rem;">
                    <button id="load-interpretation" class="btn btn-primary" onclick="loadStructuredInterpretation()">Structured Analysis</button>
                    <button id="load-ai-interpretation" class="btn btn-secondary" onclick="loadAIInterpretation()" style="margin-left: 0.5rem;">AI Reading</button>
                    <button id="load-patterns" class="btn btn-secondary" onclick="loadAspectPatterns()" style="margin-left: 0.5rem;">Aspect Patterns</button>
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
        const chartId = <?= $chart->getId() ?>;
        
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

        // Transit functionality
        async function loadTransits() {
            const loading = document.getElementById('transit-loading');
            const content = document.getElementById('transit-content');
            const button = document.getElementById('load-transits');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            button.disabled = true;
            button.textContent = 'Loading...';
            
            try {
                const response = await fetch(`/api/charts/${chartId}/transits/current`);
                const data = await response.json();
                
                if (response.ok) {
                    displayCurrentTransits(data);
                } else {
                    content.innerHTML = `<div class="no-data">Error: ${data.error || 'Failed to load transits'}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="no-data">Network error: ${error.message}</div>`;
            }
            
            loading.style.display = 'none';
            content.style.display = 'block';
            button.disabled = false;
            button.textContent = 'Refresh Current Transits';
        }

        async function loadUpcomingTransits() {
            const loading = document.getElementById('transit-loading');
            const content = document.getElementById('transit-content');
            const button = document.getElementById('load-upcoming');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            button.disabled = true;
            button.textContent = 'Loading...';
            
            try {
                const response = await fetch(`/api/charts/${chartId}/transits/upcoming?days=30`);
                const data = await response.json();
                
                if (response.ok) {
                    displayUpcomingTransits(data);
                } else {
                    content.innerHTML = `<div class="no-data">Error: ${data.error || 'Failed to load upcoming transits'}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="no-data">Network error: ${error.message}</div>`;
            }
            
            loading.style.display = 'none';
            content.style.display = 'block';
            button.disabled = false;
            button.textContent = 'Refresh Upcoming Transits';
        }

        function displayCurrentTransits(data) {
            const content = document.getElementById('transit-content');
            
            if (!data.transits || data.transits.length === 0) {
                content.innerHTML = '<div class="no-data">No significant transits at this time</div>';
                return;
            }
            
            let html = `
                <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(74, 144, 226, 0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>Transit Date:</strong> ${new Date(data.transit_date).toLocaleDateString('en-US', {
                        year: 'numeric', month: 'long', day: 'numeric', 
                        hour: '2-digit', minute: '2-digit'
                    })}
                </div>
                <ul class="aspect-list">
            `;
            
            // Sort transits by orb (strongest first) and limit to 15
            const sortedTransits = data.transits
                .sort((a, b) => a.orb - b.orb)
                .slice(0, 15);
            
            sortedTransits.forEach(transit => {
                const strength = transit.strength || Math.round((1 - (transit.orb / 8)) * 100);
                const strengthColor = strength > 80 ? '#FFD700' : strength > 60 ? '#4A90E2' : 'rgba(255, 255, 255, 0.7)';
                
                html += `
                    <li class="aspect-item">
                        <span class="aspect-name">
                            ${capitalize(transit.transiting_planet)} ${capitalize(transit.aspect)} ${capitalize(transit.natal_planet)}
                            ${transit.applying ? '↗' : '↘'}
                        </span>
                        <span class="aspect-orb" style="color: ${strengthColor}">
                            ${transit.orb.toFixed(1)}° (${strength}%)
                        </span>
                    </li>
                `;
            });
            
            html += '</ul>';
            
            if (data.transits.length > 15) {
                html += `<div style="text-align: center; margin-top: 1rem; color: rgba(255, 255, 255, 0.5); font-style: italic;">
                    ... and ${data.transits.length - 15} more transits
                </div>`;
            }
            
            content.innerHTML = html;
        }

        function displayUpcomingTransits(data) {
            const content = document.getElementById('transit-content');
            
            if (!data.upcoming_transits || data.upcoming_transits.length === 0) {
                content.innerHTML = `<div class="no-data">No exact transits in the next ${data.period_days} days</div>`;
                return;
            }
            
            let html = `
                <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(255, 215, 0, 0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>Upcoming Exact Transits</strong> (Next ${data.period_days} days)
                </div>
                <ul class="aspect-list">
            `;
            
            data.upcoming_transits.forEach(transit => {
                const date = new Date(transit.date);
                const daysFromNow = Math.ceil((date - new Date()) / (1000 * 60 * 60 * 24));
                
                html += `
                    <li class="aspect-item">
                        <span class="aspect-name">
                            ${capitalize(transit.transiting_planet)} ${capitalize(transit.aspect)} ${capitalize(transit.natal_planet)}
                        </span>
                        <span class="aspect-orb" style="color: var(--quantum-gold)">
                            ${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                            (${daysFromNow} day${daysFromNow !== 1 ? 's' : ''})
                        </span>
                    </li>
                `;
            });
            
            html += '</ul>';
            content.innerHTML = html;
        }

        function capitalize(str) {
            return str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        // Tab functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(`${tabName}-tab`).classList.add('active');
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        }

        // Progressions functionality
        async function loadProgressions() {
            const loading = document.getElementById('progression-loading');
            const content = document.getElementById('progression-content');
            const button = document.getElementById('load-progressions');
            const dateInput = document.getElementById('progression-date');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            button.disabled = true;
            button.textContent = 'Loading...';
            
            try {
                let url = `/api/charts/${chartId}/progressions/current`;
                if (dateInput.value) {
                    url += `?date=${dateInput.value}`;
                }
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (response.ok) {
                    displayProgressions(data);
                } else {
                    content.innerHTML = `<div class="no-data">Error: ${data.error || 'Failed to load progressions'}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="no-data">Network error: ${error.message}</div>`;
            }
            
            loading.style.display = 'none';
            content.style.display = 'block';
            button.disabled = false;
            button.textContent = 'Refresh Progressions';
        }

        function displayProgressions(data) {
            const content = document.getElementById('progression-content');
            
            if (!data.progressed_to_natal_aspects || data.progressed_to_natal_aspects.length === 0) {
                content.innerHTML = '<div class="no-data">No significant progressed aspects at this time</div>';
                return;
            }
            
            let html = `
                <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(139, 92, 246, 0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>Progressed Date:</strong> ${new Date(data.progressed_date).toLocaleDateString('en-US', {
                        year: 'numeric', month: 'long', day: 'numeric'
                    })}<br>
                    <strong>Years Progressed:</strong> ${data.years_progressed.toFixed(1)} years
                </div>
                <ul class="aspect-list">
            `;
            
            // Sort and limit progressed aspects
            const sortedAspects = data.progressed_to_natal_aspects
                .sort((a, b) => a.orb - b.orb)
                .slice(0, 12);
            
            sortedAspects.forEach(aspect => {
                const strength = aspect.strength || Math.round((1 - (aspect.orb / 6)) * 100);
                const strengthColor = strength > 80 ? '#FFD700' : strength > 60 ? '#8b5cf6' : 'rgba(255, 255, 255, 0.7)';
                
                html += `
                    <li class="aspect-item">
                        <span class="aspect-name">
                            Prog. ${capitalize(aspect.progressed_planet)} ${capitalize(aspect.aspect)} ${capitalize(aspect.natal_planet)}
                        </span>
                        <span class="aspect-orb" style="color: ${strengthColor}">
                            ${aspect.orb.toFixed(1)}° (${strength}%)
                        </span>
                    </li>
                `;
            });
            
            html += '</ul>';
            content.innerHTML = html;
        }

        // Solar Returns functionality
        async function loadSolarReturns() {
            const loading = document.getElementById('solar-return-loading');
            const content = document.getElementById('solar-return-content');
            const button = document.getElementById('load-solar-returns');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            button.disabled = true;
            button.textContent = 'Loading...';
            
            try {
                const currentYear = new Date().getFullYear();
                const response = await fetch(`/api/charts/${chartId}/solar-returns?start_year=${currentYear-2}&end_year=${currentYear+2}`);
                const data = await response.json();
                
                if (response.ok) {
                    displaySolarReturns(data);
                    populateYearSelector(data.year_range);
                } else {
                    content.innerHTML = `<div class="no-data">Error: ${data.error || 'Failed to load solar returns'}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="no-data">Network error: ${error.message}</div>`;
            }
            
            loading.style.display = 'none';
            content.style.display = 'block';
            button.disabled = false;
            button.textContent = 'Refresh Solar Returns';
        }

        async function loadSpecificSolarReturn() {
            const yearSelect = document.getElementById('solar-return-year');
            const selectedYear = yearSelect.value;
            
            if (!selectedYear) return;
            
            const content = document.getElementById('solar-return-content');
            content.innerHTML = '<div style="text-align: center; padding: 2rem;">Loading specific solar return...</div>';
            
            try {
                const response = await fetch(`/api/charts/${chartId}/solar-returns/${selectedYear}`);
                const data = await response.json();
                
                if (response.ok) {
                    displaySpecificSolarReturn(data);
                } else {
                    content.innerHTML = `<div class="no-data">Error: ${data.error || 'Failed to load solar return'}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="no-data">Network error: ${error.message}</div>`;
            }
        }

        function displaySolarReturns(data) {
            const content = document.getElementById('solar-return-content');
            
            let html = `
                <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(255, 215, 0, 0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>Solar Return Years:</strong> ${data.year_range[0]} - ${data.year_range[1]}
                </div>
            `;
            
            const currentYear = new Date().getFullYear();
            const years = Object.keys(data.solar_returns).sort((a, b) => b - a); // Latest first
            
            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">';
            
            years.forEach(year => {
                const solarReturn = data.solar_returns[year];
                if (solarReturn.error) {
                    html += `
                        <div style="background: rgba(255, 0, 0, 0.1); border: 1px solid rgba(255, 0, 0, 0.3); border-radius: 8px; padding: 1rem;">
                            <h4 style="color: #ff6b6b; margin: 0 0 0.5rem 0;">${year}</h4>
                            <p style="margin: 0; font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);">Error: ${solarReturn.error}</p>
                        </div>
                    `;
                } else {
                    const isCurrentYear = parseInt(year) === currentYear;
                    const returnDate = new Date(solarReturn.solar_return_date);
                    
                    html += `
                        <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid ${isCurrentYear ? 'var(--quantum-gold)' : 'rgba(255, 255, 255, 0.1)'}; border-radius: 8px; padding: 1rem; cursor: pointer;" onclick="document.getElementById('solar-return-year').value='${year}'; loadSpecificSolarReturn();">
                            <h4 style="color: ${isCurrentYear ? 'var(--quantum-gold)' : 'var(--quantum-text)'}; margin: 0 0 0.5rem 0;">
                                ${year} ${isCurrentYear ? '★' : ''}
                            </h4>
                            <p style="margin: 0; font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);">
                                ${returnDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                            </p>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.8rem; color: rgba(255, 255, 255, 0.5);">
                                Click for details
                            </p>
                        </div>
                    `;
                }
            });
            
            html += '</div>';
            content.innerHTML = html;
        }

        function displaySpecificSolarReturn(data) {
            const content = document.getElementById('solar-return-content');
            const returnDate = new Date(data.solar_return_date);
            
            let html = `
                <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(255, 215, 0, 0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>Solar Return ${data.return_year}</strong><br>
                    <strong>Date:</strong> ${returnDate.toLocaleString('en-US', {
                        year: 'numeric', month: 'long', day: 'numeric', 
                        hour: '2-digit', minute: '2-digit', timeZoneName: 'short'
                    })}<br>
                    <strong>Location:</strong> ${data.return_location.name || 'Natal location'}
                </div>
            `;
            
            if (data.cross_aspects && data.cross_aspects.length > 0) {
                html += '<h4 style="color: var(--quantum-gold); margin: 1rem 0 0.5rem 0;">Solar Return to Natal Aspects</h4>';
                html += '<ul class="aspect-list">';
                
                const topAspects = data.cross_aspects.slice(0, 10);
                topAspects.forEach(aspect => {
                    const strength = aspect.strength || Math.round((1 - (aspect.orb / 6)) * 100);
                    const strengthColor = strength > 80 ? '#FFD700' : strength > 60 ? '#4A90E2' : 'rgba(255, 255, 255, 0.7)';
                    
                    html += `
                        <li class="aspect-item">
                            <span class="aspect-name">
                                SR ${capitalize(aspect.solar_return_planet)} ${capitalize(aspect.aspect)} ${capitalize(aspect.natal_planet)}
                            </span>
                            <span class="aspect-orb" style="color: ${strengthColor}">
                                ${aspect.orb.toFixed(1)}° (${strength}%)
                            </span>
                        </li>
                    `;
                });
                
                html += '</ul>';
            }
            
            content.innerHTML = html;
        }

        function populateYearSelector(yearRange) {
            const select = document.getElementById('solar-return-year');
            select.innerHTML = '<option value="">Select Year</option>';
            
            for (let year = yearRange[1]; year >= yearRange[0]; year--) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (year === new Date().getFullYear()) {
                    option.selected = true;
                }
                select.appendChild(option);
            }
        }

        function capitalize(str) {
            return str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        // Interpretation functionality
        async function loadStructuredInterpretation() {
            const loading = document.getElementById('interpretation-loading');
            const content = document.getElementById('interpretation-content');
            const button = document.getElementById('load-interpretation');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            button.disabled = true;
            button.textContent = 'Loading...';
            
            try {
                const response = await fetch(`/api/charts/${chartId}/interpretation`);
                const data = await response.json();
                
                if (response.ok) {
                    displayStructuredInterpretation(data);
                } else {
                    content.innerHTML = `<div class="no-data">Error: ${data.error || 'Failed to load interpretation'}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="no-data">Network error: ${error.message}</div>`;
            }
            
            loading.style.display = 'none';
            content.style.display = 'block';
            button.disabled = false;
            button.textContent = 'Refresh Structured Analysis';
        }

        async function loadAIInterpretation() {
            const loading = document.getElementById('interpretation-loading');
            const content = document.getElementById('interpretation-content');
            const button = document.getElementById('load-ai-interpretation');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            button.disabled = true;
            button.textContent = 'Loading...';
            
            try {
                const response = await fetch(`/api/charts/${chartId}/interpretation/ai`);
                const data = await response.json();
                
                if (response.ok) {
                    displayAIInterpretation(data);
                } else {
                    content.innerHTML = `<div class="no-data">Error: ${data.error || 'Failed to load AI interpretation'}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="no-data">Network error: ${error.message}</div>`;
            }
            
            loading.style.display = 'none';
            content.style.display = 'block';
            button.disabled = false;
            button.textContent = 'Refresh AI Reading';
        }

        async function loadAspectPatterns() {
            const loading = document.getElementById('interpretation-loading');
            const content = document.getElementById('interpretation-content');
            const button = document.getElementById('load-patterns');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            button.disabled = true;
            button.textContent = 'Loading...';
            
            try {
                const response = await fetch(`/api/charts/${chartId}/patterns`);
                const data = await response.json();
                
                if (response.ok) {
                    displayAspectPatterns(data);
                } else {
                    content.innerHTML = `<div class="no-data">Error: ${data.error || 'Failed to load aspect patterns'}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="no-data">Network error: ${error.message}</div>`;
            }
            
            loading.style.display = 'none';
            content.style.display = 'block';
            button.disabled = false;
            button.textContent = 'Refresh Aspect Patterns';
        }

        function displayStructuredInterpretation(data) {
            const content = document.getElementById('interpretation-content');
            
            let html = `
                <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(139, 92, 246, 0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>Structured Chart Analysis</strong><br>
                    <span style="color: rgba(255, 255, 255, 0.7);">Comprehensive astrological interpretation</span>
                </div>
            `;
            
            // Core Identity
            if (data.core_identity) {
                html += '<div style="margin-bottom: 1.5rem;">';
                html += '<h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Core Identity</h4>';
                
                if (data.core_identity.sun) {
                    html += `<div style="margin-bottom: 0.5rem;"><strong>Sun:</strong> ${data.core_identity.sun.sign} in House ${data.core_identity.sun.house} - ${data.core_identity.sun.interpretation}</div>`;
                }
                if (data.core_identity.moon) {
                    html += `<div style="margin-bottom: 0.5rem;"><strong>Moon:</strong> ${data.core_identity.moon.sign} in House ${data.core_identity.moon.house} - ${data.core_identity.moon.interpretation}</div>`;
                }
                if (data.core_identity.rising) {
                    html += `<div style="margin-bottom: 0.5rem;"><strong>Rising:</strong> ${data.core_identity.rising.sign} - ${data.core_identity.rising.interpretation}</div>`;
                }
                
                if (data.core_identity.synthesis) {
                    html += `<div style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(255, 255, 255, 0.05); border-radius: 5px; font-style: italic;">${data.core_identity.synthesis}</div>`;
                }
                html += '</div>';
            }
            
            // Elemental Balance
            if (data.elemental_balance) {
                html += '<div style="margin-bottom: 1.5rem;">';
                html += '<h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Elemental Balance</h4>';
                html += `<div style="margin-bottom: 0.5rem;"><strong>Dominant Element:</strong> ${capitalize(data.elemental_balance.dominant_element)}</div>`;
                
                if (data.elemental_balance.percentages) {
                    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;">';
                    Object.entries(data.elemental_balance.percentages).forEach(([element, percentage]) => {
                        html += `<div style="text-align: center; padding: 0.25rem; background: rgba(255, 255, 255, 0.05); border-radius: 3px;">
                            <div style="font-weight: bold;">${capitalize(element)}</div>
                            <div style="color: var(--quantum-blue);">${percentage}%</div>
                        </div>`;
                    });
                    html += '</div>';
                }
                html += '</div>';
            }
            
            // House Emphasis
            if (data.house_emphasis && data.house_emphasis.stelliums && data.house_emphasis.stelliums.length > 0) {
                html += '<div style="margin-bottom: 1.5rem;">';
                html += '<h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">House Emphasis</h4>';
                
                data.house_emphasis.stelliums.forEach(stellium => {
                    html += `<div style="margin-bottom: 0.5rem; padding: 0.5rem; background: rgba(255, 255, 255, 0.05); border-radius: 5px;">
                        <strong>House ${stellium.house} Stellium:</strong> ${stellium.planets.map(capitalize).join(', ')}<br>
                        <span style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">${stellium.interpretation}</span>
                    </div>`;
                });
                html += '</div>';
            }
            
            // Overall Themes
            if (data.overall_themes) {
                html += '<div style="margin-bottom: 1.5rem;">';
                html += '<h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Overall Themes</h4>';
                
                if (data.overall_themes.life_purpose) {
                    html += `<div style="margin-bottom: 0.5rem;"><strong>Life Purpose:</strong> ${data.overall_themes.life_purpose}</div>`;
                }
                
                if (data.overall_themes.natural_talents && data.overall_themes.natural_talents.length > 0) {
                    html += `<div style="margin-bottom: 0.5rem;"><strong>Natural Talents:</strong> ${data.overall_themes.natural_talents.map(capitalize).join(', ')}</div>`;
                }
                
                if (data.overall_themes.major_challenges && data.overall_themes.major_challenges.length > 0) {
                    html += `<div style="margin-bottom: 0.5rem;"><strong>Growth Areas:</strong> ${data.overall_themes.major_challenges.map(capitalize).join(', ')}</div>`;
                }
                
                html += '</div>';
            }
            
            // Summary
            if (data.interpretation_summary) {
                html += `<div style="margin-top: 1.5rem; padding: 1rem; background: rgba(74, 144, 226, 0.1); border-radius: 8px; border-left: 4px solid var(--quantum-primary);">
                    <h4 style="color: var(--quantum-primary); margin-bottom: 0.5rem;">Summary</h4>
                    <div style="line-height: 1.6;">${data.interpretation_summary}</div>
                </div>`;
            }
            
            content.innerHTML = html;
        }

        function displayAIInterpretation(data) {
            const content = document.getElementById('interpretation-content');
            
            let html = `
                <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(255, 215, 0, 0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>AI-Generated Natural Language Reading</strong><br>
                    <span style="color: rgba(255, 255, 255, 0.7);">Confidence Score: ${data.confidence_score || 0}% | Words: ${data.interpretation_metadata?.word_count || 0}</span>
                </div>
            `;
            
            // Personality Overview
            if (data.personality_overview) {
                html += `<div style="margin-bottom: 1.5rem;">
                    <h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Personality Overview</h4>
                    <div style="line-height: 1.6; padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                        ${data.personality_overview.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>')}
                    </div>
                </div>`;
            }
            
            // Life Purpose
            if (data.life_purpose) {
                html += `<div style="margin-bottom: 1.5rem;">
                    <h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Life Purpose & Soul Mission</h4>
                    <div style="line-height: 1.6; padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                        ${data.life_purpose.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>')}
                    </div>
                </div>`;
            }
            
            // Relationship Insights
            if (data.relationship_insights) {
                html += `<div style="margin-bottom: 1.5rem;">
                    <h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Relationship Patterns</h4>
                    <div style="line-height: 1.6; padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                        ${data.relationship_insights.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>')}
                    </div>
                </div>`;
            }
            
            // Career Guidance
            if (data.career_guidance) {
                html += `<div style="margin-bottom: 1.5rem;">
                    <h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Career & Life Path</h4>
                    <div style="line-height: 1.6; padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                        ${data.career_guidance.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>')}
                    </div>
                </div>`;
            }
            
            // Challenges and Growth
            if (data.challenges_and_growth) {
                html += `<div style="margin-bottom: 1.5rem;">
                    <h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Challenges & Growth Opportunities</h4>
                    <div style="line-height: 1.6; padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                        ${data.challenges_and_growth.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>')}
                    </div>
                </div>`;
            }
            
            // Overall Synthesis
            if (data.overall_synthesis) {
                html += `<div style="margin-top: 1.5rem; padding: 1rem; background: rgba(255, 215, 0, 0.1); border-radius: 8px; border-left: 4px solid var(--quantum-gold);">
                    <h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Overall Synthesis</h4>
                    <div style="line-height: 1.6; font-style: italic;">
                        ${data.overall_synthesis.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>')}
                    </div>
                </div>`;
            }
            
            // Metadata
            if (data.interpretation_metadata) {
                html += `<div style="margin-top: 1rem; padding: 0.5rem; background: rgba(255, 255, 255, 0.05); border-radius: 5px; font-size: 0.8rem; color: rgba(255, 255, 255, 0.6);">
                    Generated by ${data.interpretation_metadata.ai_model} on ${new Date(data.interpretation_metadata.generated_at).toLocaleString()}
                </div>`;
            }
            
            content.innerHTML = html;
        }

        function displayAspectPatterns(data) {
            const content = document.getElementById('interpretation-content');
            
            if (!data.patterns || data.patterns.length === 0) {
                content.innerHTML = '<div class="no-data">No major aspect patterns detected in this chart</div>';
                return;
            }
            
            let html = `
                <div style="margin-bottom: 1rem; padding: 0.5rem; background: rgba(74, 144, 226, 0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>Aspect Pattern Analysis</strong><br>
                    <span style="color: rgba(255, 255, 255, 0.7);">Found ${data.patterns.length} pattern${data.patterns.length !== 1 ? 's' : ''} in ${data.chart_name}</span>
                </div>
            `;
            
            // Group patterns by significance
            const majorPatterns = data.patterns.filter(p => p.significance === 'major');
            const moderatePatterns = data.patterns.filter(p => p.significance === 'moderate');
            const minorPatterns = data.patterns.filter(p => p.significance === 'minor');
            
            // Display Major Patterns
            if (majorPatterns.length > 0) {
                html += '<h4 style="color: var(--quantum-gold); margin-bottom: 0.5rem;">Major Patterns</h4>';
                majorPatterns.forEach(pattern => {
                    const significanceColor = '#FFD700'; // Gold for major
                    html += `<div style="margin-bottom: 1rem; padding: 0.75rem; background: rgba(255, 215, 0, 0.1); border-radius: 8px; border-left: 4px solid ${significanceColor};">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <h5 style="color: ${significanceColor}; margin: 0;">${pattern.name}</h5>
                            <span style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6);">Orb: ${pattern.orb_average?.toFixed(1) || 'N/A'}°</span>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Planets:</strong> ${pattern.planets ? pattern.planets.map(capitalize).join(', ') : 'N/A'}
                        </div>
                        ${pattern.element ? `<div style="margin-bottom: 0.5rem;"><strong>Element:</strong> ${capitalize(pattern.element)}</div>` : ''}
                        ${pattern.mode ? `<div style="margin-bottom: 0.5rem;"><strong>Mode:</strong> ${capitalize(pattern.mode)}</div>` : ''}
                        ${pattern.apex_planet ? `<div style="margin-bottom: 0.5rem;"><strong>Apex Planet:</strong> ${capitalize(pattern.apex_planet)}</div>` : ''}
                        ${pattern.focus_planet ? `<div style="margin-bottom: 0.5rem;"><strong>Focus Planet:</strong> ${capitalize(pattern.focus_planet)}</div>` : ''}
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Keywords:</strong> ${pattern.keywords ? pattern.keywords.map(capitalize).join(', ') : 'N/A'}
                        </div>
                        <div style="line-height: 1.5; font-style: italic; color: rgba(255, 255, 255, 0.9);">
                            ${pattern.interpretation || 'No interpretation available'}
                        </div>
                    </div>`;
                });
            }
            
            // Display Moderate Patterns
            if (moderatePatterns.length > 0) {
                html += '<h4 style="color: var(--quantum-blue); margin: 1.5rem 0 0.5rem 0;">Moderate Patterns</h4>';
                moderatePatterns.forEach(pattern => {
                    const significanceColor = '#4A90E2'; // Blue for moderate
                    html += `<div style="margin-bottom: 1rem; padding: 0.75rem; background: rgba(74, 144, 226, 0.1); border-radius: 8px; border-left: 4px solid ${significanceColor};">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <h5 style="color: ${significanceColor}; margin: 0;">${pattern.name}</h5>
                            <span style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6);">Orb: ${pattern.orb_average?.toFixed(1) || 'N/A'}°</span>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Planets:</strong> ${pattern.planets ? pattern.planets.map(capitalize).join(', ') : 'N/A'}
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Keywords:</strong> ${pattern.keywords ? pattern.keywords.map(capitalize).join(', ') : 'N/A'}
                        </div>
                        <div style="line-height: 1.5; font-style: italic; color: rgba(255, 255, 255, 0.8);">
                            ${pattern.interpretation || 'No interpretation available'}
                        </div>
                    </div>`;
                });
            }
            
            // Display Minor Patterns (collapsed)
            if (minorPatterns.length > 0) {
                html += `<details style="margin-top: 1.5rem;">
                    <summary style="color: var(--quantum-purple); cursor: pointer; font-weight: bold;">Minor Patterns (${minorPatterns.length})</summary>
                    <div style="margin-top: 0.5rem;">`;
                
                minorPatterns.forEach(pattern => {
                    html += `<div style="margin-bottom: 0.5rem; padding: 0.5rem; background: rgba(139, 92, 246, 0.1); border-radius: 5px;">
                        <strong style="color: var(--quantum-purple);">${pattern.name}:</strong> 
                        ${pattern.planets ? pattern.planets.map(capitalize).join(', ') : 'N/A'}
                        <span style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6);"> (${pattern.orb_average?.toFixed(1) || 'N/A'}°)</span>
                    </div>`;
                });
                
                html += '</div></details>';
            }
            
            content.innerHTML = html;
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set up transit tab as default
            document.getElementById('transit-loading').style.display = 'none';
            document.getElementById('transit-content').innerHTML = '<div class="no-data">Click "View Current Transits" to load transit data</div>';
            document.getElementById('transit-content').style.display = 'block';
            
            // Set up progression tab
            document.getElementById('progression-loading').style.display = 'none';
            document.getElementById('progression-content').innerHTML = '<div class="no-data">Click "View Current Progressions" to load progression data</div>';
            document.getElementById('progression-content').style.display = 'block';
            document.getElementById('progression-date').value = new Date().toISOString().split('T')[0];
            
            // Set up solar return tab
            document.getElementById('solar-return-loading').style.display = 'none';
            document.getElementById('solar-return-content').innerHTML = '<div class="no-data">Click "View Solar Returns" to load solar return data</div>';
            document.getElementById('solar-return-content').style.display = 'block';
            
            // Set up interpretation tab
            document.getElementById('interpretation-loading').style.display = 'none';
            document.getElementById('interpretation-content').innerHTML = '<div class="no-data">Click "Structured Analysis", "AI Reading", or "Aspect Patterns" to load interpretations</div>';
            document.getElementById('interpretation-content').style.display = 'block';
        });
    </script>
</body>
</html>