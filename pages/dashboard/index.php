<?php // pages/dashboard/index.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\AdminGate;

Auth::requireLogin();
$user = Auth::user();
$showAdminLink = AdminGate::canAccess($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quantum Astrology Dashboard</title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css?v=<?= urlencode((string) filemtime(ROOT_PATH . '/assets/css/quantum-dashboard.css')) ?>">
    <style>
        html {
            background-color: #0a0e13;
        }
        body {
            background-color: #0a0e13;
            background: linear-gradient(135deg, var(--quantum-darker) 0%, var(--quantum-dark) 100%);
            color: var(--quantum-text);
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
        .portal-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 12px;
        }
        .portal-nav a {
            color: var(--quantum-text);
            opacity: 0.85;
            text-decoration: none;
            margin-left: 0;
        }
        .portal-nav a:hover {
            opacity: 1;
        }
        .portal-nav a.active {
            border-bottom: 2px solid var(--quantum-blue);
            padding-bottom: 4px;
        }
        .dashboard-shell {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .dashboard-hero h1 {
            font-size: clamp(2rem, 4vw, 2.8rem);
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.6rem;
        }
        .dashboard-hero p {
            color: var(--quantum-text-muted);
            margin-bottom: 1.5rem;
        }
        .dashboard-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 2rem;
            backdrop-filter: blur(12px);
        }
        @media (max-width: 768px) {
            .portal-header {
                padding: 12px 14px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .portal-brand > span:last-child {
                display: none;
            }
            .dashboard-shell {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    <?php $activeNav = 'portal'; require __DIR__ . '/../_partials/portal_header.php'; ?>
    <main class="dashboard-shell">
        <section class="dashboard-hero">
            <h1>Quantum Astrology</h1>
            <p>Professional astrology software with Quantum Minds United styling.</p>
        </section>
        <div class="page-actions">
            <button type="button" class="back-button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'">
                <span class="icon" aria-hidden="true">←</span>
                <span>Back</span>
            </button>
        </div>
        <div class="dashboard-card">
            <h2 style="color: var(--quantum-gold); margin-bottom: 0.45rem;">Welcome to Quantum Astrology</h2>
            <p>Your professional astrology software suite</p>
        </div>
    </main>
    
    <script src="/assets/js/core/quantum-ui.js"></script>
</body>
</html>
