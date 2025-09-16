<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$charts = Chart::findByUserId($user->getId(), 20);

$pageTitle = 'My Charts - Quantum Astrology';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .charts-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .charts-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
        }

        .charts-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(74, 144, 226, 0.2);
            border-color: var(--quantum-primary);
        }

        .chart-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .chart-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--quantum-text);
            margin-bottom: 0.25rem;
        }

        .chart-type {
            font-size: 0.85rem;
            color: var(--quantum-gold);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-private {
            background: rgba(139, 92, 246, 0.2);
            color: #a78bfa;
        }

        .status-public {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .chart-info {
            margin-bottom: 1rem;
        }

        .chart-detail {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .chart-detail-icon {
            width: 16px;
            margin-right: 0.5rem;
            color: var(--quantum-primary);
        }

        .chart-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--quantum-text);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .create-chart-card {
            background: linear-gradient(135deg, rgba(74, 144, 226, 0.1), rgba(139, 92, 246, 0.1));
            border: 2px dashed rgba(74, 144, 226, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 200px;
            transition: all 0.3s ease;
        }

        .create-chart-card:hover {
            border-color: var(--quantum-primary);
            background: linear-gradient(135deg, rgba(74, 144, 226, 0.2), rgba(139, 92, 246, 0.2));
        }

        .create-icon {
            font-size: 3rem;
            color: var(--quantum-primary);
            margin-bottom: 1rem;
        }

        .create-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--quantum-text);
            margin-bottom: 0.5rem;
        }

        .create-subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--quantum-primary);
            margin-bottom: 2rem;
        }

        .empty-title {
            font-size: 1.5rem;
            color: var(--quantum-text);
            margin-bottom: 1rem;
        }

        .empty-description {
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .charts-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    
    <div class="charts-container">
        <div class="charts-header">
            <h1 class="charts-title">My Charts</h1>
            <a href="/charts/create" class="btn btn-primary">+ New Chart</a>
        </div>

        <?php if (empty($charts)): ?>
            <div class="empty-state">
                <div class="empty-icon">🌟</div>
                <h2 class="empty-title">No Charts Yet</h2>
                <p class="empty-description">
                    Create your first natal chart to begin your astrological journey
                </p>
                <a href="/charts/create" class="btn btn-primary">Create Your First Chart</a>
            </div>
        <?php else: ?>
            <div class="charts-grid">
                <!-- Create New Chart Card -->
                <a href="/charts/create" class="chart-card create-chart-card">
                    <div class="create-icon">+</div>
                    <div class="create-title">Create New Chart</div>
                    <div class="create-subtitle">Generate precise natal charts with Swiss Ephemeris</div>
                </a>

                <!-- Existing Charts -->
                <?php foreach ($charts as $chart): ?>
                    <div class="chart-card" onclick="location.href='/charts/view?id=<?= $chart->getId() ?>'">
                        <div class="chart-card-header">
                            <div>
                                <div class="chart-name"><?= htmlspecialchars($chart->getName()) ?></div>
                                <div class="chart-type"><?= htmlspecialchars(ucfirst($chart->getChartType())) ?> Chart</div>
                            </div>
                            <div class="chart-status <?= $chart->isPublic() ? 'status-public' : 'status-private' ?>">
                                <?= $chart->isPublic() ? 'Public' : 'Private' ?>
                            </div>
                        </div>

                        <div class="chart-info">
                            <?php if ($chart->getBirthDatetime()): ?>
                                <div class="chart-detail">
                                    <span class="chart-detail-icon">📅</span>
                                    <?= $chart->getBirthDatetime()->format('M j, Y g:i A') ?>
                                </div>
                            <?php endif ?>

                            <?php if ($chart->getBirthLocationName()): ?>
                                <div class="chart-detail">
                                    <span class="chart-detail-icon">📍</span>
                                    <?= htmlspecialchars($chart->getBirthLocationName()) ?>
                                </div>
                            <?php endif ?>

                            <div class="chart-detail">
                                <span class="chart-detail-icon">🏠</span>
                                House System: <?= htmlspecialchars($chart->getHouseSystem()) ?>
                            </div>

                            <div class="chart-detail">
                                <span class="chart-detail-icon">⏰</span>
                                Created <?= date('M j, Y', strtotime($chart->getCreatedAt())) ?>
                            </div>
                        </div>

                        <div class="chart-actions" onclick="event.stopPropagation()">
                            <a href="/charts/view?id=<?= $chart->getId() ?>" class="btn btn-primary">View</a>
                            <a href="/charts/edit?id=<?= $chart->getId() ?>" class="btn btn-secondary">Edit</a>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

    <script>
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
    </script>
</body>
</html>