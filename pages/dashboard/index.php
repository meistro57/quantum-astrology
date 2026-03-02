<?php // pages/dashboard/index.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quantum Astrology Dashboard</title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .dashboard-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 2.5rem 1rem 3rem;
        }
        .dashboard-hero h1 {
            font-size: clamp(2rem, 4vw, 2.8rem);
            background: linear-gradient(135deg, var(--quantum-gold), var(--quantum-blue));
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
            padding: 1.5rem;
            backdrop-filter: blur(12px);
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
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
            <h2>Welcome to Quantum Astrology</h2>
            <p>Your professional astrology software suite</p>
        </div>
    </main>
    
    <script src="/assets/js/core/quantum-ui.js"></script>
</body>
</html>
