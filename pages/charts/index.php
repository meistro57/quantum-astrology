<?php // pages/charts/index.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\AdminGate;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$perPage = 20;

$searchQuery = trim((string)($_GET['q'] ?? ''));
$visibility = strtolower(trim((string)($_GET['visibility'] ?? 'all')));
if (!in_array($visibility, ['all', 'public', 'private'], true)) {
    $visibility = 'all';
}
$sort = strtolower(trim((string)($_GET['sort'] ?? 'newest')));
if (!in_array($sort, ['newest', 'oldest', 'name_asc', 'name_desc'], true)) {
    $sort = 'newest';
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if ($page === null || $page === false) {
    $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
}
$page = is_int($page) ? max(1, $page) : 1;

$totalCharts = Chart::countByUserIdFiltered((int) $user->getId(), $searchQuery, $visibility);
$totalPages = max(1, (int) ceil($totalCharts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$charts = Chart::findByUserIdFiltered((int) $user->getId(), $perPage, $offset, $searchQuery, $visibility, $sort);
$hasPrev = $page > 1;
$hasNext = $page < $totalPages;
$hasActiveFilters = ($searchQuery !== '' || $visibility !== 'all' || $sort !== 'newest');
$baseQuery = http_build_query([
    'q' => $searchQuery,
    'visibility' => $visibility,
    'sort' => $sort,
]);
$baseQuery = $baseQuery !== '' ? $baseQuery . '&' : '';

$pageTitle = 'My Charts - Quantum Astrology';
$csrfToken = (string)($_SESSION['csrf_token'] ?? '');
$creatorLabel = trim((string)($user?->getUsername() ?? 'Unknown'));
$showAdminLink = AdminGate::canAccess($user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        html, body {
            background: #0a0e13 !important;
            background-image: linear-gradient(135deg, #0a0e13 0%, #0f1419 100%) !important;
            color: #e2e8f0 !important;
        }
        .portal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            background: rgba(10, 13, 20, 0.65);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .portal-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: var(--quantum-text);
        }
        .portal-brand-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--quantum-purple), var(--quantum-blue));
        }
        .portal-nav a {
            color: var(--quantum-text);
            opacity: 0.85;
            text-decoration: none;
            margin-left: 16px;
        }
        .portal-nav a:hover {
            opacity: 1;
        }
        .portal-nav a.active {
            border-bottom: 2px solid var(--quantum-blue);
            padding-bottom: 4px;
        }
        .portal-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 12px;
        }
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
        .charts-controls {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.04);
        }
        .control-group {
            min-width: 180px;
            flex: 1;
        }
        .control-label {
            display: block;
            font-size: 0.8rem;
            margin-bottom: 0.35rem;
            color: rgba(255, 255, 255, 0.75);
        }
        .control-input, .control-select {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: var(--quantum-text);
        }
        .control-select option {
            background: var(--quantum-dark);
            color: var(--quantum-text);
        }
        .controls-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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
        .btn-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #ffb3bf;
            border: 1px solid rgba(220, 53, 69, 0.45);
        }
        .btn-danger:hover {
            background: rgba(220, 53, 69, 0.32);
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
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
            margin-bottom: 1.5rem;
        }
        .pagination .btn[aria-disabled="true"] {
            opacity: 0.5;
            pointer-events: none;
        }
        .pagination-summary {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .toast-notice {
            position: fixed;
            right: 1.25rem;
            bottom: 1.25rem;
            z-index: 1200;
            background: rgba(34, 197, 94, 0.18);
            border: 1px solid rgba(34, 197, 94, 0.42);
            color: #86efac;
            padding: 0.8rem 1rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.25s ease, transform 0.25s ease;
            pointer-events: none;
        }
        .toast-notice.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .portal-header {
                padding: 12px 14px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .portal-nav a {
                margin-left: 0;
            }
            .portal-brand > span:last-child {
                display: none;
            }
            .charts-container {
                padding: 1rem;
            }
            .charts-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .charts-controls {
                flex-direction: column;
                align-items: stretch;
            }
            .controls-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    <header class="portal-header">
        <div class="portal-brand">
            <div class="portal-brand-dot"></div>
            Quantum Astrology
            <span style="opacity:.6;font-weight:500;margin-left:8px">· Quantum Minds United</span>
        </div>
        <nav class="portal-nav">
            <a href="/">Portal</a>
            <a href="/charts" class="active">Charts</a>
            <a href="/reports">Reports</a>
            <?php if ($showAdminLink): ?><a href="/admin">Admin</a><?php endif; ?>
            <a href="/profile">Profile</a>
            <a href="/logout">Logout</a>
        </nav>
    </header>

    <div class="charts-container">
        <div class="page-actions">
            <button type="button" class="back-button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='/dashboard'">
                <span class="icon" aria-hidden="true">←</span>
                <span>Back</span>
            </button>
        </div>
        <div class="charts-header">
            <h1 class="charts-title">My Charts</h1>
            <a href="/charts/create" class="btn btn-primary">+ New Chart</a>
        </div>

        <form method="GET" class="charts-controls">
            <div class="control-group">
                <label class="control-label" for="q">Search</label>
                <input id="q" name="q" class="control-input" type="text" placeholder="Name or location" value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
            <div class="control-group">
                <label class="control-label" for="visibility">Visibility</label>
                <select id="visibility" name="visibility" class="control-select">
                    <option value="all" <?= $visibility === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>Public</option>
                    <option value="private" <?= $visibility === 'private' ? 'selected' : '' ?>>Private</option>
                </select>
            </div>
            <div class="control-group">
                <label class="control-label" for="sort">Sort</label>
                <select id="sort" name="sort" class="control-select">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                </select>
            </div>
            <div class="controls-actions">
                <button type="submit" class="btn btn-secondary">Apply</button>
                <?php if ($hasActiveFilters): ?>
                    <a href="/charts" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($charts)): ?>
            <div class="empty-state">
                <div class="empty-icon">🌟</div>
                <h2 class="empty-title"><?= $hasActiveFilters ? 'No Matching Charts' : 'No Charts Yet' ?></h2>
                <p class="empty-description">
                    <?= $hasActiveFilters
                        ? 'Try broadening your search or resetting filters.'
                        : 'Create your first natal chart to begin your astrological journey' ?>
                </p>
                <?php if ($hasActiveFilters): ?>
                    <a href="/charts" class="btn btn-secondary">Reset Filters</a>
                <?php endif; ?>
                <a href="/charts/create" class="btn btn-primary"><?= $hasActiveFilters ? 'Create New Chart' : 'Create Your First Chart' ?></a>
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
                    <div class="chart-card" id="chart-card-<?= $chart->getId() ?>" onclick="location.href='/charts/view?id=<?= $chart->getId() ?>'">
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
                            <div class="chart-detail">
                                <span class="chart-detail-icon">👤</span>
                                Created by <?= htmlspecialchars($creatorLabel) ?>
                            </div>

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
                            <button type="button"
                                    class="btn btn-danger"
                                    onclick="deleteChartFromList(<?= $chart->getId() ?>, <?= json_encode((string)$chart->getName(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)">
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <?php
                    $startItem = $offset + 1;
                    $endItem = min($offset + count($charts), $totalCharts);
                ?>
                <div class="pagination-summary">
                    Showing <?= $startItem ?>-<?= $endItem ?> of <?= $totalCharts ?> charts
                </div>
                <div class="pagination" role="navigation" aria-label="Charts pagination">
                    <a href="/charts?<?= $baseQuery ?>page=<?= $page - 1 ?>"
                       class="btn btn-secondary"
                       aria-disabled="<?= $hasPrev ? 'false' : 'true' ?>">Previous</a>
                    <span class="btn btn-secondary" style="cursor: default;">Page <?= $page ?> of <?= $totalPages ?></span>
                    <a href="/charts?<?= $baseQuery ?>page=<?= $page + 1 ?>"
                       class="btn btn-secondary"
                       aria-disabled="<?= $hasNext ? 'false' : 'true' ?>">Next</a>
                </div>
            <?php endif; ?>
        <?php endif ?>
    </div>
    <div id="delete-toast" class="toast-notice" role="status" aria-live="polite"></div>

    <script>
        const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const deleteToast = document.getElementById('delete-toast');

        function showDeleteToast(message) {
            if (!deleteToast) return;
            deleteToast.textContent = message;
            deleteToast.classList.add('visible');
            window.setTimeout(() => deleteToast.classList.remove('visible'), 2600);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const queuedToast = sessionStorage.getItem('qa_toast');
            if (queuedToast) {
                showDeleteToast(queuedToast);
                sessionStorage.removeItem('qa_toast');
            }
        });

        async function deleteChartFromList(chartId, chartName) {
            const confirmed = window.confirm(`Delete chart "${chartName}"? This cannot be undone.`);
            if (!confirmed) return;

            try {
                const response = await fetch('/api/chart_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: Number(chartId),
                        csrf: csrfToken
                    })
                });
                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload?.error?.message || 'Failed to delete chart');
                }

                const card = document.getElementById(`chart-card-${chartId}`);
                if (card) {
                    card.remove();
                }
                showDeleteToast(`Chart "${chartName}" deleted.`);

                const remainingCards = document.querySelectorAll('.chart-card[id^="chart-card-"]').length;
                if (remainingCards === 0) {
                    sessionStorage.setItem('qa_toast', `Chart "${chartName}" deleted.`);
                    window.location.reload();
                }
            } catch (error) {
                alert(error.message || 'Failed to delete chart');
            }
        }

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
