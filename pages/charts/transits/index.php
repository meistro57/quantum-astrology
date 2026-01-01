<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_bootstrap.php';
use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();
$userCharts = Chart::findByUserId(Auth::user()->getId(), 100);
$pageTitle = 'Cosmic Weather Forecast';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .weather-station { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .control-deck {
            background: #161b2b; padding: 1.5rem; border-radius: 16px; border: 1px solid #232a40;
            display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 2rem;
        }
        .input-group { display: flex; flex-direction: column; gap: 0.5rem; }
        label { color: #98a0b3; font-size: 0.85rem; text-transform: uppercase; font-weight: bold; }
        select { background: #0b0e14; border: 1px solid #232a40; color: #fff; padding: 0.8rem; border-radius: 8px; }
        .btn-forecast {
            background: linear-gradient(135deg, #7b5cff, #5cc8ff); color: #0b0e14; font-weight: bold;
            border: none; padding: 0.8rem 2rem; border-radius: 8px; cursor: pointer;
        }
        .monitor-frame {
            background: #0f1424; border: 1px solid #232a40; border-radius: 12px;
            padding: 1rem; min-height: 400px; text-align: center;
        }
        .monitor-frame img { width: 100%; height: auto; display: none; }
        .monitor-frame img.active { display: block; }
        .placeholder { color: #4a5568; margin-top: 150px; }
    </style>
</head>
<body>
    <div class="weather-station">
        <h1>Cosmic Weather Forecast</h1>
        
        <?php if (empty($userCharts)): ?>
            <p>You need a chart to see the weather. <a href="/charts/create">Create one here.</a></p>
        <?php else: ?>
            <div class="control-deck">
                <div class="input-group">
                    <label>Subject</label>
                    <select id="chartSelect">
                        <?php foreach ($userCharts as $c): ?>
                            <option value="<?= $c->getId() ?>"><?= htmlspecialchars($c->getName()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Duration</label>
                    <select id="durationSelect">
                        <option value="30">30 Days</option>
                        <option value="90">90 Days</option>
                    </select>
                </div>
                <button id="btnRefresh" class="btn-forecast">Scan Skies</button>
            </div>

            <div class="monitor-frame">
                <div id="msg" class="placeholder">Select a chart and click Scan.</div>
                <img id="timelineImg" src="" alt="Transit Graph" />
            </div>
        <?php endif; ?>
    </div>

    <script>
        const btn = document.getElementById('btnRefresh');
        const img = document.getElementById('timelineImg');
        const msg = document.getElementById('msg');
        
        btn?.addEventListener('click', () => {
            const id = document.getElementById('chartSelect').value;
            const days = document.getElementById('durationSelect').value;
            const width = document.querySelector('.monitor-frame').clientWidth - 40;
            
            msg.innerText = "Consulting the stars...";
            img.classList.remove('active');
            
            const src = `/api/transit_timeline_svg.php?id=${id}&days=${days}&width=${width}&t=${Date.now()}`;
            
            const temp = new Image();
            temp.onload = () => {
                img.src = src;
                img.classList.add('active');
                msg.style.display = 'none';
            };
            temp.onerror = () => {
                msg.innerText = "Error loading graph. Check the console/network tab.";
                msg.style.display = 'block';
            }
            temp.src = src;
        });
    </script>
</body>
</html>
