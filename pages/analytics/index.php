<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;

Auth::requireLogin();

$user = Auth::user();
$pageTitle = 'API Analytics - Quantum Astrology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .analytics-container {
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
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: var(--quantum-primary);
            transform: translateY(-2px);
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--quantum-gold);
        }

        .stat-subtext {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 0.5rem;
        }

        .endpoints-table {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 0.875rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            color: var(--quantum-primary);
            font-weight: 600;
        }

        td {
            padding: 0.875rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--quantum-primary), var(--quantum-purple));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .badge-danger {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
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

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.9rem;
            }

            th, td {
                padding: 0.5rem;
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
            <a href="/analytics" style="color: var(--quantum-gold); text-decoration: none;">Analytics</a>
        </nav>
    </header>

    <div class="analytics-container">
        <div class="page-header">
            <h1 class="page-title">API Usage Analytics</h1>
            <p class="page-description">
                Track your API usage, monitor rate limits, and optimize your astrological calculations.
            </p>
        </div>

        <div id="loading" class="loading-state">
            <div class="loading-spinner"></div>
            <p>Loading analytics data...</p>
        </div>

        <div id="analytics-content" style="display: none;">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Requests (30d)</div>
                    <div class="stat-value" id="total-requests">--</div>
                    <div class="stat-subtext">Across all endpoints</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Allowed Requests</div>
                    <div class="stat-value" id="allowed-requests">--</div>
                    <div class="stat-subtext" id="success-rate">-- success rate</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Blocked Requests</div>
                    <div class="stat-value" id="blocked-requests" style="color: #e74c3c;">--</div>
                    <div class="stat-subtext">Rate limit exceeded</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Active Endpoints</div>
                    <div class="stat-value" id="unique-endpoints">--</div>
                    <div class="stat-subtext" id="active-days">-- active days</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Current Hour Usage</div>
                    <div class="stat-value" id="current-hour">--</div>
                    <div class="stat-subtext">Limit: <span id="hour-limit">100</span>/hour</div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="hour-progress" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <div class="endpoints-table">
                <h2 class="table-title">Top Endpoints (30 days)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th style="text-align: right;">Total</th>
                            <th style="text-align: right;">Allowed</th>
                            <th style="text-align: right;">Blocked</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="endpoints-tbody">
                        <tr>
                            <td colspan="5" style="text-align: center; color: rgba(255,255,255,0.5);">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function loadAnalytics() {
            try {
                const response = await fetch('/api/analytics/usage.php?action=summary');
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to load analytics');
                }

                displaySummary(data.summary);
                displayTopEndpoints(data.top_endpoints);

                document.getElementById('loading').style.display = 'none';
                document.getElementById('analytics-content').style.display = 'block';

            } catch (error) {
                console.error('Failed to load analytics:', error);
                document.getElementById('loading').innerHTML = `
                    <p style="color: #e74c3c;">Failed to load analytics: ${error.message}</p>
                `;
            }
        }

        function displaySummary(summary) {
            const total = parseInt(summary.total_requests) || 0;
            const allowed = parseInt(summary.allowed_requests) || 0;
            const blocked = parseInt(summary.blocked_requests) || 0;
            const currentHour = parseInt(summary.current_hour_requests) || 0;

            document.getElementById('total-requests').textContent = formatNumber(total);
            document.getElementById('allowed-requests').textContent = formatNumber(allowed);
            document.getElementById('blocked-requests').textContent = formatNumber(blocked);
            document.getElementById('unique-endpoints').textContent = summary.unique_endpoints || 0;
            document.getElementById('active-days').textContent = (summary.active_days || 0) + ' active days';

            const successRate = total > 0 ? ((allowed / total) * 100).toFixed(1) : 100;
            document.getElementById('success-rate').textContent = successRate + '% success rate';

            document.getElementById('current-hour').textContent = currentHour;

            const hourLimit = 100; // TODO: Get from user tier
            const hourProgress = Math.min((currentHour / hourLimit) * 100, 100);
            document.getElementById('hour-progress').style.width = hourProgress + '%';
        }

        function displayTopEndpoints(endpoints) {
            const tbody = document.getElementById('endpoints-tbody');

            if (!endpoints || endpoints.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: rgba(255,255,255,0.5);">No endpoint data available</td></tr>';
                return;
            }

            tbody.innerHTML = endpoints.map(endpoint => {
                const total = parseInt(endpoint.total_requests) || 0;
                const allowed = parseInt(endpoint.allowed_requests) || 0;
                const blocked = parseInt(endpoint.blocked_requests) || 0;
                const successRate = total > 0 ? ((allowed / total) * 100).toFixed(0) : 100;

                let badge = '';
                if (successRate >= 95) {
                    badge = '<span class="badge badge-success">Healthy</span>';
                } else if (successRate >= 80) {
                    badge = '<span class="badge badge-warning">Warning</span>';
                } else {
                    badge = '<span class="badge badge-danger">Critical</span>';
                }

                return `
                    <tr>
                        <td><code>${endpoint.endpoint}</code></td>
                        <td style="text-align: right;"><strong>${formatNumber(total)}</strong></td>
                        <td style="text-align: right; color: #2ecc71;">${formatNumber(allowed)}</td>
                        <td style="text-align: right; color: #e74c3c;">${formatNumber(blocked)}</td>
                        <td style="text-align: center;">${badge}</td>
                    </tr>
                `;
            }).join('');
        }

        function formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }

        // Load analytics on page load
        loadAnalytics();

        // Refresh every 30 seconds
        setInterval(loadAnalytics, 30000);

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
