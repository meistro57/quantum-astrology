<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

use QuantumAstrology\Core\Auth;

Auth::requireLogin();

$pageTitle = 'Transits - Quantum Astrology';
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
            max-width: 800px;
            margin: 4rem auto;
            text-align: center;
        }
        .transits-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .transits-description {
            color: rgba(255, 255, 255, 0.8);
        }
    </style>
</head>
<body>
<div class="particles-container"></div>
<div class="transits-container">
    <h1 class="transits-title">Transits</h1>
    <p class="transits-description">Transit calculations will be available soon.</p>
</div>
<script>
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
    for (let i = 0; i < 50; i++) {
        setTimeout(createParticle, i * 200);
    }
    setInterval(createParticle, 1000);
</script>
</body>
</html>

