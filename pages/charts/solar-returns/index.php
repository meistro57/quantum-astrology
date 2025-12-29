<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$userCharts = Chart::findByUserId($user->getId(), 50);

$currentYear = (int) date('Y');
$pageTitle = 'Solar Returns - Quantum Astrology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .solar-returns-container {
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
            background: linear-gradient(135deg, var(--quantum-gold), #FFA500);
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            border-color: var(--quantum-gold);
            background: rgba(255, 255, 255, 0.15);
        }

        .form-select option {
            background: var(--quantum-darker);
            color: white;
        }

        .form-hint {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--quantum-gold), #FFA500);
            color: var(--quantum-darker);
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
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .year-navigation {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .year-btn {
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .year-btn:hover {
            background: rgba(255, 215, 0, 0.2);
            border-color: var(--quantum-gold);
        }

        .year-btn.active {
            background: linear-gradient(135deg, var(--quantum-gold), #FFA500);
            color: var(--quantum-darker);
            border-color: var(--quantum-gold);
        }

        .results-container {
            display: none;
        }

        .sr-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
        }

        .sr-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--quantum-gold);
        }

        .sr-datetime {
            text-align: right;
        }

        .sr-datetime-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .sr-datetime-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
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

        .house-cusp {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .house-number {
            font-weight: 600;
            color: var(--quantum-primary);
        }

        .profection-card {
            background: radial-gradient(circle at center, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
            text-align: center;
            padding: 2rem;
        }

        .profection-year {
            font-size: 3rem;
            font-weight: bold;
            color: var(--quantum-gold);
            margin-bottom: 0.5rem;
        }

        .profection-ruler {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .profection-house {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .theme-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .theme-item:last-child {
            border-bottom: none;
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
            border-top: 3px solid var(--quantum-gold);
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

        .relocation-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .toggle-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .relocation-fields {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .relocation-fields.visible {
            display: grid;
        }

        @media (max-width: 768px) {
            .controls-grid {
                grid-template-columns: 1fr;
            }

            .results-grid {
                grid-template-columns: 1fr;
            }

            .year-navigation {
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 0.5rem;
            }

            .relocation-fields {
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
            <a href="/charts/solar-returns" style="color: var(--quantum-gold); text-decoration: none;">Solar Returns</a>
        </nav>
    </header>

    <div class="solar-returns-container">
        <div class="page-header">
            <h1 class="page-title">Solar Returns</h1>
            <p class="page-description">
                Your Solar Return chart reveals the themes and energies for your year ahead,
                calculated for the exact moment the Sun returns to its natal position.
            </p>
        </div>

        <?php if (empty($userCharts)): ?>
        <div class="no-charts">
            <h3>No Charts Available</h3>
            <p>Create a natal chart first to view your Solar Return.</p>
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
                    <label class="form-label">Solar Return Year</label>
                    <select id="year-select" class="form-select">
                        <?php for ($y = $currentYear - 5; $y <= $currentYear + 5; $y++): ?>
                            <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor ?>
                    </select>
                </div>

                <div class="form-group">
                    <button id="calculate-btn" class="btn-primary" disabled>Calculate Solar Return</button>
                    <div class="relocation-toggle">
                        <input type="checkbox" id="relocate-toggle" class="toggle-checkbox">
                        <label for="relocate-toggle" style="font-size: 0.85rem; cursor: pointer;">Relocate chart</label>
                    </div>
                </div>
            </div>

            <div id="relocation-fields" class="relocation-fields">
                <div class="form-group">
                    <label class="form-label">Location Name</label>
                    <input type="text" id="location-name" class="form-input" placeholder="e.g., New York, NY">
                </div>
                <div class="form-group">
                    <label class="form-label">Latitude</label>
                    <input type="number" id="latitude" class="form-input" step="0.0001" placeholder="40.7128">
                </div>
                <div class="form-group">
                    <label class="form-label">Longitude</label>
                    <input type="number" id="longitude" class="form-input" step="0.0001" placeholder="-74.0060">
                </div>
            </div>
        </div>

        <div id="year-nav" class="year-navigation" style="display: none;"></div>

        <div id="loading" class="loading-state" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Calculating your Solar Return chart...</p>
        </div>

        <div id="error" class="error-message" style="display: none;"></div>

        <div id="results" class="results-container">
            <div class="sr-header">
                <div>
                    <h2 class="sr-title">Solar Return <span id="sr-year"><?= $currentYear ?></span></h2>
                    <div id="sr-location" style="font-size: 0.9rem; color: rgba(255,255,255,0.6);"></div>
                </div>
                <div class="sr-datetime">
                    <div class="sr-datetime-label">Exact Return Moment</div>
                    <div id="sr-datetime" class="sr-datetime-value">--</div>
                </div>
            </div>

            <div class="results-grid">
                <div class="result-card profection-card">
                    <h3 class="card-title" style="justify-content: center;"><span class="icon">&#127775;</span> Annual Profection</h3>
                    <div id="profection-year" class="profection-year">--</div>
                    <div id="profection-ruler" class="profection-ruler">Ruler: <span style="color: var(--quantum-gold);">--</span></div>
                    <div id="profection-house" class="profection-house">Focus on House --</div>
                </div>

                <div class="result-card">
                    <h3 class="card-title"><span class="icon">&#9737;</span> Solar Return Planets</h3>
                    <div id="sr-planets"></div>
                </div>

                <div class="result-card">
                    <h3 class="card-title"><span class="icon">&#127968;</span> Solar Return Houses</h3>
                    <div id="sr-houses"></div>
                </div>

                <div class="result-card">
                    <h3 class="card-title"><span class="icon">&#10024;</span> Year Themes</h3>
                    <div id="sr-themes"></div>
                </div>
            </div>
        </div>

        <?php endif ?>
    </div>

    <script>
        const chartSelect = document.getElementById('chart-select');
        const yearSelect = document.getElementById('year-select');
        const calculateBtn = document.getElementById('calculate-btn');
        const relocateToggle = document.getElementById('relocate-toggle');
        const relocationFields = document.getElementById('relocation-fields');
        const loadingDiv = document.getElementById('loading');
        const errorDiv = document.getElementById('error');
        const resultsDiv = document.getElementById('results');
        const yearNav = document.getElementById('year-nav');

        let selectedChartId = null;
        let currentSRYear = <?= $currentYear ?>;

        const planetSymbols = {
            sun: '&#9737;', moon: '&#9790;', mercury: '&#9791;', venus: '&#9792;',
            mars: '&#9794;', jupiter: '&#9795;', saturn: '&#9796;', uranus: '&#9797;',
            neptune: '&#9798;', pluto: '&#9799;', north_node: '&#9738;', chiron: '&#9919;'
        };

        chartSelect.addEventListener('change', function() {
            selectedChartId = this.value;
            updateButtonState();
        });

        relocateToggle.addEventListener('change', function() {
            relocationFields.classList.toggle('visible', this.checked);
        });

        function updateButtonState() {
            calculateBtn.disabled = !selectedChartId;
        }

        calculateBtn.addEventListener('click', async () => {
            const year = yearSelect.value;
            await loadSolarReturn(year);
        });

        async function loadSolarReturn(year) {
            if (!selectedChartId) return;

            currentSRYear = parseInt(year);
            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            resultsDiv.style.display = 'none';

            let url = `/api/charts/${selectedChartId}/solar-returns/${year}`;

            if (relocateToggle.checked) {
                const lat = document.getElementById('latitude').value;
                const lng = document.getElementById('longitude').value;
                const loc = document.getElementById('location-name').value;
                if (lat && lng) {
                    url += `?latitude=${lat}&longitude=${lng}&location=${encodeURIComponent(loc)}`;
                }
            }

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to calculate solar return');
                }

                displayResults(data);
                updateYearNavigation();
            } catch (error) {
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            } finally {
                loadingDiv.style.display = 'none';
            }
        }

        function displayResults(data) {
            resultsDiv.style.display = 'block';
            yearNav.style.display = 'flex';

            document.getElementById('sr-year').textContent = data.solar_return_year || currentSRYear;

            const srDatetime = data.solar_return_datetime ?
                new Date(data.solar_return_datetime).toLocaleString() : '--';
            document.getElementById('sr-datetime').textContent = srDatetime;

            const location = data.location?.name || data.birth_location?.name || 'Birth Location';
            document.getElementById('sr-location').textContent = location;

            // Profection
            const profection = data.profection || {};
            const age = profection.age || (currentSRYear - (data.birth_year || 1990));
            document.getElementById('profection-year').textContent = age;
            document.getElementById('profection-ruler').innerHTML =
                `Ruler: <span style="color: var(--quantum-gold);">${capitalize(profection.ruler || 'sun')}</span>`;
            document.getElementById('profection-house').textContent =
                `Focus on House ${profection.house || ((age % 12) + 1)}`;

            // Planets
            const planets = data.planetary_positions || {};
            const planetsHtml = Object.entries(planets).slice(0, 10).map(([planet, pos]) => `
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
            document.getElementById('sr-planets').innerHTML = planetsHtml || '<p>No data</p>';

            // Houses
            const houses = data.house_cusps || [];
            const housesHtml = houses.slice(0, 12).map((cusp, i) => `
                <div class="house-cusp">
                    <span class="house-number">House ${i + 1}</span>
                    <span>${cusp.sign || '--'} ${cusp.degree ? cusp.degree.toFixed(1) + '°' : ''}</span>
                </div>
            `).join('');
            document.getElementById('sr-houses').innerHTML = housesHtml || '<p>No data</p>';

            // Themes
            const themes = data.year_themes || [
                'Personal growth and development',
                'New opportunities and beginnings',
                'Focus on relationships and connections'
            ];
            const themesHtml = themes.map(theme => `
                <div class="theme-item">${theme}</div>
            `).join('');
            document.getElementById('sr-themes').innerHTML = themesHtml;
        }

        function updateYearNavigation() {
            const years = [];
            for (let y = currentSRYear - 3; y <= currentSRYear + 3; y++) {
                years.push(y);
            }

            yearNav.innerHTML = years.map(y => `
                <button class="year-btn ${y === currentSRYear ? 'active' : ''}"
                        onclick="loadSolarReturn(${y})">${y}</button>
            `).join('');
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
