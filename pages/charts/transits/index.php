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
            background: rgba(22, 27, 43, 0.8); border: 1px solid rgba(255,255,255,0.1); 
            backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 16px; 
            display: flex; gap: 1.5rem; align-items: flex-end; margin-bottom: 2rem;
        }
        .input-group { display: flex; flex-direction: column; gap: 0.5rem; }
        label { color: #a0aec0; font-size: 0.85rem; text-transform: uppercase; font-weight: bold; }
        select { background: #0f1424; border: 1px solid #2d3748; color: #fff; padding: 0.8rem; border-radius: 8px; }
        .btn-forecast {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; 
            padding: 0.8rem 2rem; border-radius: 8px; cursor: pointer; font-weight: bold;
        }
        .monitor-frame {
            background: #0f1424; border: 1px solid #232a40; border-radius: 12px;
            padding: 1rem; min-height: 400px; display: flex; justify-content: center; align-items: center;
        }
        .monitor-frame img { width: 100%; height: auto; max-height: 600px; display: none; }
        .monitor-frame img.active { display: block; }
        .status-text { color: #718096; font-size: 1.2rem; }
    </style>
</head>
<body>
    <div class="weather-station">
        <h1>Cosmic Weather Forecast</h1>
        <?php if (empty($userCharts)): ?>
            <p>You need a natal chart first. <a href="/charts/create">Create one here.</a></p>
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
                    <label>Timeline</label>
                    <select id="durationSelect">
                        <option value="30">30 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                    </select>
                </div>
                <button id="btnRefresh" class="btn-forecast">Scan Skies</button>
            </div>

            <div class="monitor-frame">
                <div id="status" class="status-text">Select a chart to begin...</div>
                <img id="timelineImg" src="" alt="Transit Timeline" />
            </div>
        <?php endif; ?>
    </div>

    <script>
        const btn = document.getElementById('btnRefresh');
        const img = document.getElementById('timelineImg');
        const status = document.getElementById('status');
        
        btn?.addEventListener('click', () => {
            const id = document.getElementById('chartSelect').value;
            const days = document.getElementById('durationSelect').value;
            const width = document.querySelector('.monitor-frame').clientWidth - 40;
            
            status.innerText = "Consulting the oracles (calculating)...";
            status.style.display = 'block';
            img.classList.remove('active');
            
            // Add timestamp to prevent caching
            const src = `/api/transit_timeline_svg.php?id=${id}&days=${days}&width=${width}&t=${Date.now()}`;
            
            const temp = new Image();
            temp.onload = () => {
                img.src = src;
                img.classList.add('active');
                status.style.display = 'none';
            };
            temp.onerror = () => {
                status.innerHTML = "<span style='color:red'>Transmission failed. Check console.</span>";
            };
            temp.src = src;
        });
    </script>
</body>
</html>
