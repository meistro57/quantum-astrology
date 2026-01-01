<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
// Pagination fixed to 100 just in case they are popular
$userCharts = Chart::findByUserId($user->getId(), 100); 

$pageTitle = 'Cosmic Weather Forecast - Quantum Astrology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .weather-station {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Hero Section */
        .hero {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }
        
        .hero h1 {
            font-size: 3rem;
            background: linear-gradient(to right, #4facfe 0%, #00f2fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .hero p {
            font-size: 1.2rem;
            color: #a0aec0;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Control Deck */
        .control-deck {
            background: rgba(16, 20, 35, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: flex-end;
            justify-content: center;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
        }

        label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #718096;
            font-weight: 600;
        }

        select, input {
            background: #1a202c;
            border: 1px solid #2d3748;
            color: #fff;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
            transition: all 0.2s;
        }

        select:focus, input:focus {
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
        }

        .btn-forecast {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.1s;
            min-width: 150px;
        }

        .btn-forecast:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(118, 75, 162, 0.4);
        }

        .btn-forecast:active {
            transform: translateY(0);
        }

        /* The Monitor (Graph Area) */
        .monitor-frame {
            background: #0f1424;
            border: 1px solid #232a40;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            min-height: 400px;
            position: relative;
            overflow: hidden;
        }

        .monitor-screen {
            width: 100%;
            height: auto;
            display: block;
            opacity: 0;
            transition: opacity 0.5s ease-in;
        }

        .monitor-screen.active {
            opacity: 1;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 20, 36, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            display: none; /* Hidden by default */
        }

        .pulse-star {
            font-size: 3rem;
            animation: pulse 1.5s infinite;
            margin-bottom: 1rem;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; text-shadow: 0 0 10px #4facfe; }
            50% { transform: scale(1.2); opacity: 0.7; text-shadow: 0 0 20px #00f2fe; }
            100% { transform: scale(1); opacity: 1; text-shadow: 0 0 10px #4facfe; }
        }

        /* Data cards below */
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .insight-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 12px;
        }

        .insight-card h3 {
            color: #ffd700;
            margin-top: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 0.5rem;
        }

        .status-msg {
            text-align: center;
            font-style: italic;
            color: #718096;
            margin-top: 2rem;
        }
    </style>
</head>
<body>

    <div class="weather-station">
        
        <div class="hero">
            <h1>Cosmic Weather Forecast</h1>
            <p>Tracking the planetary drama so you don't have to.</p>
        </div>

        <?php if (empty($userCharts)): ?>
            <div class="control-deck" style="flex-direction: column; align-items: center;">
                <h3>Bit empty in here, isn't it?</h3>
                <p>You need a natal chart before we can tell you if Saturn is ruining your week.</p>
                <a href="/charts/create" class="btn-forecast" style="text-decoration: none; text-align: center;">Create Chart</a>
            </div>
        <?php else: ?>

            <div class="control-deck">
                <div class="input-group">
                    <label for="chartSelect">Subject</label>
                    <select id="chartSelect">
                        <option value="" disabled selected>Who's in trouble?</option>
                        <?php foreach ($userCharts as $c): ?>
                            <option value="<?= $c->getId() ?>"><?= htmlspecialchars($c->getName()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label for="durationSelect">Timeline</label>
                    <select id="durationSelect">
                        <option value="14">Fortnight (14 Days)</option>
                        <option value="30" selected>Standard Month (30 Days)</option>
                        <option value="90">Season (90 Days)</option>
                    </select>
                </div>

                <button id="btnRefresh" class="btn-forecast">Consult the Oracles</button>
            </div>

            <div class="monitor-frame">
                <div id="loader" class="loading-overlay">
                    <div class="pulse-star">✨</div>
                    <div id="loadingText" style="color: #4facfe;">Aligning quantum states...</div>
                </div>
                
                <img id="timelineImg" class="monitor-screen" src="" alt="Transit Timeline Graph" />
                
                <div id="placeholderText" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #4a5568; text-align: center;">
                    <h3>Awaiting Input</h3>
                    <p>Select a chart above to generate the timeline.</p>
                </div>
            </div>

            <div class="insights-grid" id="detailsArea" style="display: none;">
                <div class="insight-card">
                    <h3>Current Vibe</h3>
                    <div id="currentVibeContent">
                        <p>Loading...</p>
                    </div>
                </div>
                <div class="insight-card">
                    <h3>Incoming Drama</h3>
                    <div id="dramaContent">
                        <p>Loading...</p>
                    </div>
                </div>
            </div>

            <div class="status-msg">
                * Timelines are calculated using Swiss Ephemeris precision. Orbs are tight (2°). <br>
                If the line crosses the center, buckle up.
            </div>

        <?php endif; ?>
    </div>

    <script>
        const btn = document.getElementById('btnRefresh');
        const chartSelect = document.getElementById('chartSelect');
        const durationSelect = document.getElementById('durationSelect');
        const img = document.getElementById('timelineImg');
        const loader = document.getElementById('loader');
        const loadText = document.getElementById('loadingText');
        const placeholder = document.getElementById('placeholderText');
        const detailsArea = document.getElementById('detailsArea');

        const cheekymessages = [
            "Brewing tea for Saturn...",
            "Asking Pluto to be nice...",
            "Calculating emotional damage...",
            "Aligning the chakras...",
            "Checking Mars's temper...",
            "Translating celestial gibberish..."
        ];

        function getRandomCheek() {
            return cheekymessages[Math.floor(Math.random() * cheekymessages.length)];
        }

        btn?.addEventListener('click', () => {
            const chartId = chartSelect.value;
            const days = durationSelect.value;

            if (!chartId) {
                alert("Love, you need to pick a chart first. I'm psychic, but not that psychic.");
                return;
            }

            // 1. UI State: Loading
            placeholder.style.display = 'none';
            detailsArea.style.display = 'none';
            img.classList.remove('active');
            loader.style.display = 'flex';
            loadText.innerText = getRandomCheek();

            // 2. Load the Image (Triggering the PHP calculation)
            // We use a timestamp to prevent browser caching of the image
            const width = document.querySelector('.monitor-frame').clientWidth || 1000;
            const srcUrl = `/api/transit_timeline_svg.php?id=${chartId}&days=${days}&width=${width}&t=${Date.now()}`;

            // Create a temporary image loader to know when it's ready
            const tempImg = new Image();
            tempImg.onload = () => {
                img.src = srcUrl;
                img.classList.add('active');
                loader.style.display = 'none';
                detailsArea.style.display = 'grid'; // Show the cards
                
                // Mocking the text data for now (since we don't have a JSON endpoint for text summaries yet)
                // In a future step, we'd fetch JSON alongside the SVG.
                populateMockData(); 
            };
            
            tempImg.onerror = () => {
                loader.style.display = 'none';
                alert("The stars refused to align (Server Error). Check the logs.");
            };

            tempImg.src = srcUrl;
        });

        // Just to make the page feel alive while we wait for the real text API
        function populateMockData() {
            const vibeDiv = document.getElementById('currentVibeContent');
            const dramaDiv = document.getElementById('dramaContent');
            
            vibeDiv.innerHTML = `
                <p><strong>Sun aspecting Natal Moon:</strong> You might feel a bit more emotional than usual today. Or hungry. Probably both.</p>
                <p style="color: #4facfe">Look at the graph above. Green lines are your friends.</p>
            `;
            
            dramaDiv.innerHTML = `
                <p><strong>Mars Square:</strong> Keep your head down next Tuesday. Avoid arguments with baristas.</p>
            `;
        }

        // Auto-load if chart is pre-selected (optional)
        if(chartSelect && chartSelect.value) {
            // btn.click();
        }
    </script>
</body>
</html>
