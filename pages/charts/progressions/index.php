<?php // pages/charts/progressions/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$userCharts = Chart::findByUserId($user->getId(), 50);

$pageTitle = 'Secondary Progressions - Quantum Astrology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .progressions-container {
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
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
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

        .controls-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--quantum-gold);
        }

        .form-input, .form-select {
            padding: 0.875rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--quantum-primary);
            background: rgba(255, 255, 255, 0.15);
        }

        .form-select option {
            background: var(--quantum-darker);
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .results-container {
            display: none;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--quantum-gold);
        }

        .progressed-age {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }

        .progressed-age span {
            color: var(--quantum-gold);
            font-size: 1.2rem;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--quantum-primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(74, 144, 226, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title .icon {
            font-size: 1.3rem;
        }

        .planet-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .planet-row:last-child {
            border-bottom: none;
        }

        .planet-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .planet-symbol {
            font-size: 1.2rem;
        }

        .planet-position {
            text-align: right;
        }

        .position-sign {
            color: var(--quantum-gold);
            font-weight: 600;
        }

        .position-degree {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .aspect-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .aspect-planets {
            font-weight: 500;
        }

        .aspect-type {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-weight: 600;
        }

        .aspect-conjunction { background: rgba(255, 215, 0, 0.2); color: #FFD700; }
        .aspect-trine { background: rgba(0, 255, 0, 0.2); color: #00ff00; }
        .aspect-sextile { background: rgba(0, 212, 255, 0.2); color: #00d4ff; }
        .aspect-square { background: rgba(255, 107, 107, 0.2); color: #FF6B6B; }
        .aspect-opposition { background: rgba(255, 0, 0, 0.2); color: #ff4444; }

        .lunar-phase {
            text-align: center;
            padding: 1.5rem;
        }

        .phase-symbol {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .phase-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 0.5rem;
        }

        .phase-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .loading-state {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid var(--quantum-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 10px;
            padding: 1.5rem;
            color: #FF6B6B;
            text-align: center;
        }

        .no-charts {
            text-align: center;
            padding: 3rem;
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
            .controls-grid {
                grid-template-columns: 1fr;
            }

            .results-grid {
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
            <a href="/charts/transits" style="color: white; text-decoration: none;">Transits</a>
            <a href="/charts/progressions" style="color: var(--quantum-gold); text-decoration: none;">Progressions</a>
        </nav>
    </header>

    <div class="progressions-container">
        <div class="page-actions">
            <button type="button" class="back-button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='/charts'">
                <span class="icon" aria-hidden="true">←</span>
                <span>Back</span>
            </button>
        </div>
        <div class="page-header">
            <h1 class="page-title">Secondary Progressions</h1>
            <p class="page-description">
                Explore your inner evolution through secondary progressions, where each day after birth represents a year of life.
                Discover how your progressed planets reveal psychological and spiritual development.
            </p>
        </div>

        <?php if (empty($userCharts)): ?>
        <div class="no-charts">
            <h3>No Charts Available</h3>
            <p>Create a natal chart first to view progressions.</p>
            <a href="/charts/create" class="btn-create">Create Your First Chart</a>
        </div>
        <?php else: ?>

        <div class="controls-panel">
            <div class="controls-grid">
                <div class="form-group">
                    <label class="form-label">Select Chart</label>
                    <select id="chart-select" class="form-select">
                        <option value="">Choose a chart...</option>
                        <?php foreach ($userCharts as $chart): ?>
                            <option value="<?= $chart->getId() ?>">
                                <?= htmlspecialchars($chart->getName()) ?>
                                (<?= $chart->getBirthDatetime() ? $chart->getBirthDatetime()->format('M j, Y') : 'Unknown date' ?>)
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Progressed Date</label>
                    <input type="date" id="progressed-date" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <button id="calculate-btn" class="btn-primary" disabled>Calculate Progressions</button>
                </div>
            </div>
        </div>

        <div id="loading" class="loading-state" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Calculating your progressed chart...</p>
        </div>

        <div id="error" class="error-message" style="display: none;"></div>

        <div id="results" class="results-container">
            <div class="results-header">
                <h2 class="results-title">Progressed Positions</h2>
                <div class="progressed-age">
                    Progressed Age: <span id="progressed-age">--</span> years
                </div>
            </div>

            <div class="results-grid">
                <div class="result-card">
                    <h3 class="card-title"><span class="icon">&#127774;</span> Progressed Planets</h3>
                    <div id="progressed-planets"></div>
                </div>

                <div class="result-card">
                    <h3 class="card-title"><span class="icon">&#127769;</span> Progressed Moon Phase</h3>
                    <div id="lunar-phase" class="lunar-phase"></div>
                </div>

                <div class="result-card">
                    <h3 class="card-title"><span class="icon">&#10024;</span> Progressed to Natal Aspects</h3>
                    <div id="progressed-aspects"></div>
                </div>

                <div class="result-card">
                    <h3 class="card-title"><span class="icon">&#127775;</span> Key Themes</h3>
                    <div id="key-themes"></div>
                </div>
            </div>
        </div>

        <?php endif ?>
    </div>

    <script>
        const chartSelect = document.getElementById('chart-select');
        const progressedDate = document.getElementById('progressed-date');
        const calculateBtn = document.getElementById('calculate-btn');
        const loadingDiv = document.getElementById('loading');
        const errorDiv = document.getElementById('error');
        const resultsDiv = document.getElementById('results');

        const planetSymbols = {
            sun: '&#9737;', moon: '&#9790;', mercury: '&#9791;', venus: '&#9792;',
            mars: '&#9794;', jupiter: '&#9795;', saturn: '&#9796;', uranus: '&#9797;',
            neptune: '&#9798;', pluto: '&#9799;', north_node: '&#9738;', chiron: '&#9919;'
        };

        const lunarPhaseSymbols = {
            'New Moon': '&#127761;',
            'Waxing Crescent': '&#127762;',
            'First Quarter': '&#127763;',
            'Waxing Gibbous': '&#127764;',
            'Full Moon': '&#127765;',
            'Waning Gibbous': '&#127766;',
            'Last Quarter': '&#127767;',
            'Waning Crescent': '&#127768;'
        };

        chartSelect.addEventListener('change', updateButtonState);
        progressedDate.addEventListener('change', updateButtonState);

        function updateButtonState() {
            calculateBtn.disabled = !chartSelect.value;
        }

        calculateBtn.addEventListener('click', async () => {
            const chartId = chartSelect.value;
            const date = progressedDate.value;

            if (!chartId) return;

            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            resultsDiv.style.display = 'none';

            try {
                const response = await fetch(`/api/charts/${chartId}/progressions/current?date=${date}`);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to calculate progressions');
                }

                displayResults(data);
            } catch (error) {
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            } finally {
                loadingDiv.style.display = 'none';
            }
        });

        function displayResults(data) {
            resultsDiv.style.display = 'block';

            // Progressed age
            document.getElementById('progressed-age').textContent =
                data.progressed_age ? data.progressed_age.toFixed(1) : '--';

            // Progressed planets
            const planetsHtml = Object.entries(data.progressed_positions || {}).map(([planet, pos]) => `
                <div class="planet-row">
                    <span class="planet-name">
                        <span class="planet-symbol">${planetSymbols[planet] || ''}</span>
                        ${capitalize(planet)}
                    </span>
                    <span class="planet-position">
                        <span class="position-sign">${pos.sign || '--'}</span>
                        <div class="position-degree">${pos.degree ? pos.degree.toFixed(2) + '°' : '--'}</div>
                    </span>
                </div>
            `).join('');
            document.getElementById('progressed-planets').innerHTML = planetsHtml || '<p>No data available</p>';

            // Lunar phase
            const phase = data.progressed_lunar_phase || {};
            const phaseSymbol = lunarPhaseSymbols[phase.phase_name] || '&#127765;';
            document.getElementById('lunar-phase').innerHTML = `
                <div class="phase-symbol">${phaseSymbol}</div>
                <div class="phase-name">${phase.phase_name || 'Unknown Phase'}</div>
                <div class="phase-description">${phase.description || 'The progressed Moon reveals your evolving emotional nature and inner needs.'}</div>
            `;

            // Aspects
            const aspects = data.progressed_to_natal_aspects || [];
            const aspectsHtml = aspects.slice(0, 8).map(aspect => `
                <div class="aspect-row">
                    <span class="aspect-planets">
                        P.${capitalize(aspect.progressed_planet)} - N.${capitalize(aspect.natal_planet)}
                    </span>
                    <span class="aspect-type aspect-${aspect.aspect.toLowerCase()}">
                        ${capitalize(aspect.aspect)} ${aspect.orb ? '(' + aspect.orb.toFixed(1) + '°)' : ''}
                    </span>
                </div>
            `).join('');
            document.getElementById('progressed-aspects').innerHTML = aspectsHtml || '<p>No major aspects at this time</p>';

            // Key themes
            const themes = data.interpretation?.key_themes || ['Personal evolution and growth', 'Inner psychological development'];
            const themesHtml = themes.map(theme => `
                <div class="planet-row">
                    <span class="planet-name">${theme}</span>
                </div>
            `).join('');
            document.getElementById('key-themes').innerHTML = themesHtml;
        }

        function capitalize(str) {
            return str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

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

        for (let i = 0; i < 30; i++) {
            setTimeout(createParticle, i * 300);
        }
        setInterval(createParticle, 2000);
    </script>
</body>
</html>
