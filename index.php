<?php
declare(strict_types=1);

// Load configuration first
require_once __DIR__ . '/config.php';

// Simple autoloader since Composer may not be available yet
spl_autoload_register(function ($class) {
    $prefix = 'QuantumAstrology\\';
    $baseDir = __DIR__ . '/classes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Try to load Composer autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use QuantumAstrology\Core\Application;
use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\Session;
use QuantumAstrology\Core\Auth;

// Initialize application and handle routing
$app = null;
$error = null;

try {
    // Initialize session first
    Session::start();
    
    $app = new Application();
    // Check if this is an API request, asset request, or a page request
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    
    // If it's an API request or asset request, or other page routes, run the application normally
    if (strpos($requestUri, '/api/') === 0 || 
        strpos($requestUri, '/assets/') === 0 || 
        strpos($requestUri, '/login') === 0 || 
        strpos($requestUri, '/register') === 0 || 
        strpos($requestUri, '/logout') === 0 || 
        strpos($requestUri, '/profile') === 0 ||
        strpos($requestUri, '/dashboard') === 0) {
        $app->run();
        return;
    }
    
    // For the root/main page, require login
    Auth::requireLogin();
} catch (Throwable $e) {
    // Last resort error handling
    Logger::error("Fatal application error", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    $error = $e;
}

// If there's a fatal error and not in debug mode, show error page
if ($error && !APP_DEBUG) {
    echo "<h1>Service Temporarily Unavailable</h1>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quantum Astrology Dashboard</title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
</head>
<body>
    <div class="particles-bg" id="particles"></div>

    <header class="quantum-header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">🌟</div>
                <div>
                    <h3>Quantum Astrology</h3>
                    <small style="color: var(--quantum-text-muted);">Quantum Minds United</small>
                </div>
            </div>
            <nav class="nav-links">
                <a href="/" class="nav-link active">Dashboard</a>
                <a href="/charts" class="nav-link">Charts</a>
                <a href="/timing" class="nav-link">Transits</a>
                <a href="/reports" class="nav-link">Reports</a>
                <?php if (Auth::check()): ?>
                    <div class="user-menu">
                        <span class="user-name">Welcome, <?= htmlspecialchars(Auth::user()->getUsername()) ?>!</span>
                        <a href="/profile" class="nav-link">Profile</a>
                        <a href="/logout" class="nav-link">Logout</a>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($error && APP_DEBUG): ?>
            <div class="error-container">
                <h2 class="error-title">Fatal Application Error</h2>
                <div class="error-details"><?= htmlspecialchars($error->getMessage() . ' in ' . $error->getFile() . ' on line ' . $error->getLine()) ?></div>
            </div>
        <?php endif; ?>

        <section class="hero">
            <h1>The Quantum Chart Collection</h1>
            <p class="hero-subtitle">Professional astrology software with cosmic precision</p>
            <p class="hero-description">Advanced calculations powered by Swiss Ephemeris</p>
        </section>

        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-value">47</span>
                <span class="stat-label">Charts</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">12</span>
                <span class="stat-label">Active Transits</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">8</span>
                <span class="stat-label">Profiles</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">156</span>
                <span class="stat-label">Reports Generated</span>
            </div>
        </div>

        <section class="featured-section">
            <h2 class="section-title">Latest Charts</h2>
            <p style="text-align: center; color: var(--quantum-text-muted); margin-bottom: 40px;">Recently calculated astrological insights</p>
            
            <div class="featured-carousel">
                <div class="featured-card">
                    <div class="featured-badge">Newest</div>
                    <div class="chart-wheel"></div>
                    <h3 class="chart-title">Mark's Natal Chart</h3>
                    <div class="chart-details">
                        📅 October 20, 1972 • 4:03 AM<br>
                        📍 Libertyville, IL • 93.68% Moon
                    </div>
                    <div class="chart-actions">
                        <a href="/charts/create" class="btn btn-primary">Create Chart</a>
                        <a href="/charts" class="btn btn-secondary">View All</a>
                    </div>
                </div>

                <div class="featured-card">
                    <div class="featured-badge">Latest</div>
                    <div class="chart-wheel"></div>
                    <h3 class="chart-title">Solar Return 2025</h3>
                    <div class="chart-details">
                        🌞 Annual forecast chart<br>
                        📍 Current location • High impact year
                    </div>
                    <div class="chart-actions">
                        <a href="/pages/forecasting/solar-return.php" class="btn btn-primary">Explore</a>
                        <a href="/pages/reports/generate.php" class="btn btn-secondary">Report</a>
                    </div>
                </div>

                <div class="featured-card">
                    <div class="chart-wheel"></div>
                    <h3 class="chart-title">Current Transits</h3>
                    <div class="chart-details">
                        🌙 Live planetary positions<br>
                        ⚡ 3 exact aspects today
                    </div>
                    <div class="chart-actions">
                        <a href="/pages/timing/transits.php" class="btn btn-primary">Analyze</a>
                        <a href="/pages/timing/timeline.php" class="btn btn-secondary">Timeline</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-icon">📊</div>
                <h3 class="card-title">Natal Charts</h3>
                <p class="card-description">Generate precise birth charts with professional accuracy. Complete planetary positions, houses, and aspects.</p>
                <a href="/charts/create" class="btn btn-primary">Create New Chart</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">🌙</div>
                <h3 class="card-title">Transit Analysis</h3>
                <p class="card-description">Track current planetary movements and their effects on your natal chart. Real-time cosmic weather.</p>
                <a href="/pages/timing/transits.php" class="btn btn-primary">View Transits</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">💫</div>
                <h3 class="card-title">Progressions</h3>
                <p class="card-description">Explore secondary progressions and symbolic timing. Your evolving astrological blueprint.</p>
                <a href="/pages/timing/progressions.php" class="btn btn-primary">Calculate</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">🌞</div>
                <h3 class="card-title">Solar Returns</h3>
                <p class="card-description">Annual forecast charts showing themes and opportunities for your personal year ahead.</p>
                <a href="/pages/forecasting/solar-return.php" class="btn btn-primary">Generate</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">💕</div>
                <h3 class="card-title">Synastry</h3>
                <p class="card-description">Relationship compatibility analysis and composite charts. Understand connections between charts.</p>
                <a href="/pages/charts/synastry.php" class="btn btn-primary">Compare Charts</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">📈</div>
                <h3 class="card-title">Reports</h3>
                <p class="card-description">Professional astrological reports with detailed interpretations. PDF and audio formats available.</p>
                <a href="/pages/reports/" class="btn btn-primary">Browse Reports</a>
            </div>
        </section>

        <section>
            <h2 class="section-title">Quick Actions</h2>
            <div class="quick-actions">
                <a href="/pages/charts/create.php" class="quick-action">⚡ New Birth Chart</a>
                <a href="/pages/timing/transit-search.php" class="quick-action">🔍 Find Transit</a>
                <a href="/pages/timing/daily-aspects.php" class="quick-action">📅 Today's Aspects</a>
                <a href="/pages/charts/electional.php" class="quick-action">🎯 Electional Chart</a>
                <a href="/pages/charts/" class="quick-action">📊 Chart Library</a>
                <a href="/pages/dashboard/preferences.php" class="quick-action">⚙️ Preferences</a>
            </div>
        </section>
    </main>

    <script>
        // Create floating particles
        function createParticles() {
            const container = document.getElementById('particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 4 + 1;
                const left = Math.random() * 100;
                const animationDelay = Math.random() * 6;
                const animationDuration = Math.random() * 3 + 3;

                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = left + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = animationDelay + 's';
                particle.style.animationDuration = animationDuration + 's';

                container.appendChild(particle);
            }
        }

        // Enhanced card interactions
        document.addEventListener('DOMContentLoaded', () => {
            createParticles();

            const cards = document.querySelectorAll('.featured-card, .dashboard-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Smooth scrolling for carousel
            const carousel = document.querySelector('.featured-carousel');
            let isDown = false;
            let startX;
            let scrollLeft;

            carousel.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX - carousel.offsetLeft;
                scrollLeft = carousel.scrollLeft;
            });

            carousel.addEventListener('mouseleave', () => {
                isDown = false;
            });

            carousel.addEventListener('mouseup', () => {
                isDown = false;
            });

            carousel.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - carousel.offsetLeft;
                const walk = (x - startX) * 2;
                carousel.scrollLeft = scrollLeft - walk;
            });
        });
    </script>
</body>
</html>
