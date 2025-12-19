<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

use QuantumAstrology\Core\Auth;

Auth::requireLogin();

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
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .transit-controls {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        label { font-size: 0.875rem; color: var(--quantum-gold); }
        select, input {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
        }
        .btn {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        .transit-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
        }
        .transit-planet { font-weight: bold; color: var(--quantum-primary); font-size: 1.1rem; }
        .aspect-item {
            margin-top: 0.5rem;
            padding: 0.4rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .aspect-type { color: var(--quantum-gold); font-weight: bold; }
        .loading { text-align: center; padding: 2rem; opacity: 0.7; }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    <header style="padding: 1rem 2rem; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold; color: var(--quantum-gold);">QUANTUM ASTROLOGY</div>
        <nav><a href="/" style="color: white; text-decoration: none;">Dashboard</a></nav>
    </header>

    <div class="container">
        <h1 style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #fff, var(--quantum-gold)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Transit Analysis</h1>
        
        <div class="transit-controls">
            <div class="form-group">
                <label>Select Chart</label>
                <select id="chartSelect">
                    <option value="">Loading charts...</option>
                </select>
            </div>
            <div class="form-group">
                <label>Target Date</label>
                <input type="date" id="targetDate" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Time</label>
                <input type="time" id="targetTime" value="<?= date('H:i') ?>">
            </div>
            <button class="btn" id="calculateBtn">Calculate Transits</button>
        </div>

        <div id="status"></div>
        <div id="results" class="results-grid"></div>
    </div>

    <script>
        const chartSelect = document.getElementById('chartSelect');
        const resultsDiv = document.getElementById('results');
        const statusDiv = document.getElementById('status');
        const calcBtn = document.getElementById('calculateBtn');

        // Load charts
        fetch('/api/charts_list.php')
            .then(r => r.json())
            .then(data => {
                chartSelect.innerHTML = '<option value="">-- Choose a Chart --</option>';
                if (data.ok && data.charts) {
                    data.charts.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        chartSelect.appendChild(opt);
                    });
                }
            });

        calcBtn.addEventListener('click', () => {
            const chartId = chartSelect.value;
            const date = document.getElementById('targetDate').value;
            const time = document.getElementById('targetTime').value;

            if (!chartId) return alert('Please select a chart');

            resultsDiv.innerHTML = '';
            statusDiv.innerHTML = '<div class="loading">Aligning cosmic vectors...</div>';

            fetch(`/api/transits.php?id=${chartId}&date=${date}&time=${time}`)
                .then(r => r.json())
                .then(data => {
                    statusDiv.innerHTML = '';
                    if (!data.ok) {
                        statusDiv.innerHTML = `<div style="color: #ff6b6b; padding: 1rem;">Error: ${data.error.message}</div>`;
                        return;
                    }

                    const transits = data.data.transits;
                    transits.forEach(t => {
                        const card = document.createElement('div');
                        card.className = 'transit-card';
                        
                        let aspectsHtml = '';
                        if (t.aspects && t.aspects.length > 0) {
                            t.aspects.forEach(a => {
                                aspectsHtml += `
                                    <div class="aspect-item">
                                        <span class="aspect-type">${a.type}</span> natal ${a.natal_planet}
                                        <div style="font-size: 0.75rem; opacity: 0.7;">Orb: ${a.delta}°</div>
                                    </div>
                                `;
                            });
                        }

                        card.innerHTML = `
                            <div class="transit-planet">${t.planet}</div>
                            <div style="font-size: 0.8rem; margin-bottom: 0.5rem; opacity: 0.8;">
                                House ${t.house} | ${t.longitude.toFixed(2)}°
                            </div>
                            <div class="aspects-list">${aspectsHtml || '<span style="font-size: 0.8rem; opacity: 0.5;">No major aspects</span>'}</div>
                        `;
                        resultsDiv.appendChild(card);
                    });
                })
                .catch(err => {
                    statusDiv.innerHTML = `<div style="color: #ff6b6b;">Failed to fetch transits.</div>`;
                });
        });

        // Particle Background
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
        for (let i = 0; i < 50; i++) { setTimeout(createParticle, i * 200); }
        setInterval(createParticle, 1000);
    </script>
</body>
</html>
