<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$userCharts = Chart::findByUserId($user->getId(), 50);

$pageTitle = 'Transit Analysis - Quantum Astrology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .transits-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #00d4ff, var(--quantum-primary));
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
            padding: 1.5rem;
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
            padding: 0.75rem;
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
            background: linear-gradient(135deg, #00d4ff, var(--quantum-primary));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 212, 255, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 1rem;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.6);
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            color: #00d4ff;
            background: rgba(0, 212, 255, 0.1);
            border-bottom: 2px solid #00d4ff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .transit-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .transit-card:hover {
            border-color: rgba(0, 212, 255, 0.3);
        }

        .transit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .transit-planet {
            font-weight: bold;
            color: #00d4ff;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .planet-symbol {
            font-size: 1.4rem;
        }

        .transit-position {
            text-align: right;
        }

        .position-sign {
            color: var(--quantum-gold);
            font-weight: 600;
        }

        .position-degree {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .aspects-list {
            margin-top: 0.75rem;
        }

        .aspect-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .aspect-item:last-child {
            margin-bottom: 0;
        }

        .aspect-type {
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .aspect-conjunction { background: rgba(255, 215, 0, 0.2); color: #FFD700; }
        .aspect-trine { background: rgba(0, 255, 0, 0.2); color: #00ff00; }
        .aspect-sextile { background: rgba(0, 212, 255, 0.2); color: #00d4ff; }
        .aspect-square { background: rgba(255, 107, 107, 0.2); color: #FF6B6B; }
        .aspect-opposition { background: rgba(255, 0, 0, 0.2); color: #ff4444; }

        .aspect-orb {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Timeline styles */
        .timeline-container {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .timeline-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--quantum-gold);
        }

        .timeline-range {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .timeline {
            position: relative;
            padding: 1rem 0;
        }

        .timeline-event {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            margin-bottom: 1rem;
            border-left: 3px solid #00d4ff;
        }

        .timeline-event.major {
            border-left-color: var(--quantum-gold);
            background: rgba(255, 215, 0, 0.05);
        }

        .event-date {
            min-width: 100px;
            font-weight: 600;
            color: #00d4ff;
        }

        .event-content {
            flex: 1;
        }

        .event-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .event-description {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
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
            border-top: 3px solid #00d4ff;
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

        .no-aspects {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
            padding: 0.5rem;
        }

        @media (max-width: 768px) {
            .controls-grid {
                grid-template-columns: 1fr;
            }

            .results-grid {
                grid-template-columns: 1fr;
            }

            .tab-navigation {
                flex-wrap: wrap;
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
            <a href="/forecasting" style="color: white; text-decoration: none;">Forecasting</a>
            <a href="/charts/transits" style="color: #00d4ff; text-decoration: none;">Transits</a>
        </nav>
    </header>

    <div class="transits-container">
        <div class="page-header">
            <h1 class="page-title">Transit Analysis</h1>
            <p class="page-description">
                Track the current planetary movements and discover how they aspect your natal chart.
                See what cosmic influences are active in your life right now and in the coming weeks.
            </p>
        </div>

        <?php if (empty($userCharts)): ?>
        <div class="no-charts">
            <h3>No Charts Available</h3>
            <p>Create a natal chart first to view transits.</p>
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
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" id="target-date" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Time</label>
                    <input type="time" id="target-time" class="form-input" value="<?= date('H:i') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Upcoming Days</label>
                    <select id="upcoming-days" class="form-select">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>

                <div class="form-group">
                    <button id="calculate-btn" class="btn-primary" disabled>Analyze Transits</button>
                </div>
            </div>
        </div>

        <div class="tab-navigation">
            <button class="tab-btn active" data-tab="current">Current Transits</button>
            <button class="tab-btn" data-tab="upcoming">Upcoming Events</button>
            <button class="tab-btn" data-tab="timeline">Timeline View</button>
        </div>

        <div id="loading" class="loading-state" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Scanning the cosmic currents...</p>
        </div>

        <div id="error" class="error-message" style="display: none;"></div>

        <!-- Current Transits Tab -->
        <div id="tab-current" class="tab-content active">
            <div id="current-results" class="results-grid"></div>
        </div>

        <!-- Upcoming Events Tab -->
        <div id="tab-upcoming" class="tab-content">
            <div class="timeline-container">
                <div class="timeline-header">
                    <h3 class="timeline-title">Upcoming Exact Transits</h3>
                </div>
                <div id="upcoming-results" class="timeline"></div>
            </div>
        </div>

        <!-- Timeline Tab -->
        <div id="tab-timeline" class="tab-content">
            <div class="timeline-container">
                <div class="timeline-header">
                    <h3 class="timeline-title">Transit Timeline</h3>
                </div>
                <div id="timeline-results" class="timeline"></div>
            </div>
        </div>

        <?php endif ?>
    </div>

    <script>
        const chartSelect = document.getElementById('chart-select');
        const targetDate = document.getElementById('target-date');
        const targetTime = document.getElementById('target-time');
        const upcomingDays = document.getElementById('upcoming-days');
        const calculateBtn = document.getElementById('calculate-btn');
        const loadingDiv = document.getElementById('loading');
        const errorDiv = document.getElementById('error');

        let selectedChartId = null;

        const planetSymbols = {
            sun: '&#9737;', moon: '&#9790;', mercury: '&#9791;', venus: '&#9792;',
            mars: '&#9794;', jupiter: '&#9795;', saturn: '&#9796;', uranus: '&#9797;',
            neptune: '&#9798;', pluto: '&#9799;', north_node: '&#9738;', chiron: '&#9919;'
        };

        const signSymbols = {
            aries: '&#9800;', taurus: '&#9801;', gemini: '&#9802;', cancer: '&#9803;',
            leo: '&#9804;', virgo: '&#9805;', libra: '&#9806;', scorpio: '&#9807;',
            sagittarius: '&#9808;', capricorn: '&#9809;', aquarius: '&#9810;', pisces: '&#9811;'
        };

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                btn.classList.add('active');
                document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
            });
        });

        chartSelect.addEventListener('change', function() {
            selectedChartId = this.value;
            updateButtonState();
        });

        function updateButtonState() {
            calculateBtn.disabled = !selectedChartId;
        }

        calculateBtn.addEventListener('click', async () => {
            if (!selectedChartId) return;

            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';

            try {
                // Fetch current transits and upcoming transits in parallel
                const days = upcomingDays.value;
                const [currentRes, upcomingRes] = await Promise.all([
                    fetch(`/api/charts/${selectedChartId}/transits/current`),
                    fetch(`/api/charts/${selectedChartId}/transits/upcoming?days=${days}`)
                ]);

                const currentData = await currentRes.json();
                const upcomingData = await upcomingRes.json();

                if (!currentRes.ok) {
                    throw new Error(currentData.error || 'Failed to fetch transits');
                }

                displayCurrentTransits(currentData);
                displayUpcomingTransits(upcomingData);
                displayTimeline(currentData, upcomingData);

            } catch (error) {
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            } finally {
                loadingDiv.style.display = 'none';
            }
        });

        function displayCurrentTransits(data) {
            const container = document.getElementById('current-results');
            const transits = data.current_transits || data.transiting_planets || [];

            if (!transits.length) {
                container.innerHTML = '<p class="no-aspects">No current transit data available.</p>';
                return;
            }

            container.innerHTML = transits.map(t => {
                const planet = t.planet || t.name;
                const planetKey = planet.toLowerCase().replace(' ', '_');
                const aspects = t.aspects_to_natal || t.aspects || [];

                const aspectsHtml = aspects.length ? aspects.map(a => `
                    <div class="aspect-item">
                        <span>
                            <span class="aspect-type aspect-${(a.aspect || a.type || '').toLowerCase()}">${capitalize(a.aspect || a.type)}</span>
                            natal ${capitalize(a.natal_planet)}
                        </span>
                        <span class="aspect-orb">${a.orb ? a.orb.toFixed(1) + '°' : ''}</span>
                    </div>
                `).join('') : '<div class="no-aspects">No major aspects</div>';

                return `
                    <div class="transit-card">
                        <div class="transit-header">
                            <div class="transit-planet">
                                <span class="planet-symbol">${planetSymbols[planetKey] || ''}</span>
                                ${capitalize(planet)}
                            </div>
                            <div class="transit-position">
                                <span class="position-sign">${t.sign || '--'}</span>
                                <div class="position-degree">${t.longitude ? t.longitude.toFixed(2) + '°' : t.degree ? t.degree.toFixed(2) + '°' : '--'}</div>
                            </div>
                        </div>
                        <div class="aspects-list">${aspectsHtml}</div>
                    </div>
                `;
            }).join('');
        }

        function displayUpcomingTransits(data) {
            const container = document.getElementById('upcoming-results');
            const events = data.upcoming_transits || [];

            if (!events.length) {
                container.innerHTML = '<p class="no-aspects">No upcoming exact transits in this period.</p>';
                return;
            }

            container.innerHTML = events.slice(0, 20).map(e => {
                const isMajor = ['conjunction', 'opposition', 'square'].includes((e.aspect || '').toLowerCase());

                return `
                    <div class="timeline-event ${isMajor ? 'major' : ''}">
                        <div class="event-date">${formatDate(e.exact_date || e.date)}</div>
                        <div class="event-content">
                            <div class="event-title">
                                ${capitalize(e.transiting_planet || e.planet)} ${capitalize(e.aspect)} ${capitalize(e.natal_planet || 'natal point')}
                            </div>
                            <div class="event-description">
                                ${e.interpretation || getAspectDescription(e.aspect, e.transiting_planet, e.natal_planet)}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function displayTimeline(currentData, upcomingData) {
            const container = document.getElementById('timeline-results');

            // Combine current aspects and upcoming events
            const events = [];

            // Add current active transits
            const currentTransits = currentData.current_transits || currentData.transiting_planets || [];
            currentTransits.forEach(t => {
                const aspects = t.aspects_to_natal || t.aspects || [];
                aspects.forEach(a => {
                    events.push({
                        date: new Date(),
                        type: 'current',
                        planet: t.planet || t.name,
                        aspect: a.aspect || a.type,
                        natalPlanet: a.natal_planet,
                        orb: a.orb
                    });
                });
            });

            // Add upcoming events
            const upcoming = upcomingData.upcoming_transits || [];
            upcoming.forEach(e => {
                events.push({
                    date: new Date(e.exact_date || e.date),
                    type: 'upcoming',
                    planet: e.transiting_planet || e.planet,
                    aspect: e.aspect,
                    natalPlanet: e.natal_planet
                });
            });

            // Sort by date
            events.sort((a, b) => a.date - b.date);

            if (!events.length) {
                container.innerHTML = '<p class="no-aspects">No transit events to display.</p>';
                return;
            }

            container.innerHTML = events.slice(0, 25).map(e => {
                const isMajor = ['conjunction', 'opposition', 'square'].includes((e.aspect || '').toLowerCase());
                const isActive = e.type === 'current';

                return `
                    <div class="timeline-event ${isMajor ? 'major' : ''}" style="${isActive ? 'opacity: 1;' : ''}">
                        <div class="event-date">${isActive ? 'NOW' : formatDate(e.date)}</div>
                        <div class="event-content">
                            <div class="event-title">
                                ${capitalize(e.planet)} ${capitalize(e.aspect)} ${capitalize(e.natalPlanet)}
                            </div>
                            <div class="event-description">
                                ${isActive && e.orb ? 'Orb: ' + e.orb.toFixed(1) + '°' : ''}
                                ${getAspectDescription(e.aspect, e.planet, e.natalPlanet)}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function capitalize(str) {
            if (!str) return '';
            return str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        function formatDate(date) {
            if (!date) return '--';
            const d = new Date(date);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        function getAspectDescription(aspect, transitPlanet, natalPlanet) {
            const descriptions = {
                conjunction: 'intensifying and merging energies',
                opposition: 'creating awareness and balance through polarity',
                square: 'generating dynamic tension and motivation for change',
                trine: 'flowing harmoniously and supporting natural expression',
                sextile: 'offering opportunities for growth and connection'
            };
            return descriptions[(aspect || '').toLowerCase()] || 'influencing your chart';
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

        for (let i = 0; i < 40; i++) {
            setTimeout(createParticle, i * 250);
        }
        setInterval(createParticle, 1500);
    </script>
</body>
</html>
