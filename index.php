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
    Session::start();
    $app = new Application();
    
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = rtrim($path, '/') ?: '/';
    
    if (str_starts_with($path, '/api/') || 
        str_starts_with($path, '/assets/') || 
        $path === '/login' || 
        $path === '/register' || 
        $path === '/logout' || 
        $path === '/profile' ||
        str_starts_with($path, '/charts/')) {
        $app->run();
        return;
    }
    
    if ($path === '/' || $path === '/dashboard') {
        Auth::requireLogin();
    } else {
        $app->run();
        return;
    }
    
} catch (Throwable $e) {
    Logger::error("Fatal application error", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    $error = $e;
}

if ($error && !APP_DEBUG) {
    echo "<h1>Service Temporarily Unavailable</h1>";
    exit;
}

$currentUser = Auth::user();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quantum Astrology Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --quantum-dark: #0f1419;
            --quantum-darker: #0a0e13;
            --quantum-blue: #4a90e2;
            --quantum-purple: #8b5cf6;
            --quantum-gold: #ffd700;
            --quantum-text: #e2e8f0;
            --quantum-text-muted: #94a3b8;
            --quantum-card-bg: rgba(30, 41, 59, 0.4);
            --quantum-card-border: rgba(148, 163, 184, 0.1);
            --quantum-card-hover: rgba(30, 41, 59, 0.6);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--quantum-darker) 0%, var(--quantum-dark) 100%);
            color: var(--quantum-text);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Starfield Background */
        .starfield {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.8;
            animation: twinkle 3s infinite ease-in-out;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* Header */
        .quantum-header {
            position: relative;
            z-index: 100;
            background: rgba(15, 20, 25, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--quantum-card-border);
            padding: 1rem 0;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, var(--quantum-gold), var(--quantum-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }

        .logo h3 {
            font-size: 1.25rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--quantum-gold), var(--quantum-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo small {
            color: var(--quantum-text-muted);
            font-size: 0.875rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            color: var(--quantum-text-muted);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--quantum-blue);
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--quantum-blue), var(--quantum-purple));
            border-radius: 1px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: 2rem;
            padding-left: 2rem;
            border-left: 1px solid var(--quantum-card-border);
        }

        .user-name {
            color: var(--quantum-gold);
            font-weight: 500;
            font-size: 0.875rem;
        }

        /* Main Content */
        .container {
            position: relative;
            z-index: 10;
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        /* Hero Section */
        .hero {
            text-align: center;
            margin-bottom: 4rem;
            position: relative;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--quantum-gold), var(--quantum-blue), var(--quantum-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            text-shadow: 0 0 30px rgba(255, 215, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--quantum-text);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .hero-description {
            color: var(--quantum-text-muted);
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Portal Grid */
        .portal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .portal-card {
            background: var(--quantum-card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--quantum-card-border);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .portal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--quantum-blue), transparent);
        }

        .portal-card:hover {
            background: var(--quantum-card-hover);
            border-color: var(--quantum-blue);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(74, 144, 226, 0.1);
        }

        .card-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, var(--quantum-blue), var(--quantum-purple));
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px rgba(74, 144, 226, 0.2);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--quantum-text);
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: var(--quantum-text-muted);
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .card-action {
            background: linear-gradient(135deg, var(--quantum-blue), var(--quantum-purple));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            width: 100%;
        }

        .card-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(74, 144, 226, 0.3);
        }

        /* Stats Bar */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 2rem;
            background: var(--quantum-card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--quantum-card-border);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--quantum-gold);
            display: block;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        }

        .stat-label {
            color: var(--quantum-text-muted);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .quick-action {
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid var(--quantum-card-border);
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--quantum-text);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-action:hover {
            background: rgba(74, 144, 226, 0.1);
            border-color: var(--quantum-blue);
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 4rem;
            padding: 2rem 0;
            border-top: 1px solid var(--quantum-card-border);
            color: var(--quantum-text-muted);
            font-size: 0.875rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .user-menu {
                margin-left: 0;
                padding-left: 0;
                border-left: none;
                border-top: 1px solid var(--quantum-card-border);
                padding-top: 1rem;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .portal-grid {
                grid-template-columns: 1fr;
            }

            .stats-bar {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="starfield" id="starfield"></div>

    <header class="quantum-header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">🌟</div>
                <div>
                    <h3>Quantum Astrology</h3>
                    <small>Quantum Minds United</small>
                </div>
            </div>
            <nav class="nav-links">
                <a href="/" class="nav-link active">Portal</a>
                <a href="/charts" class="nav-link">Charts</a>
                <a href="/timing" class="nav-link">Transits</a>
                <a href="/reports" class="nav-link">Reports</a>
                <?php if (Auth::check()): ?>
                    <div class="user-menu">
                        <span class="user-name">Welcome, <?= htmlspecialchars(Auth::user()->getUsername()) ?></span>
                        <a href="/profile" class="nav-link">Profile</a>
                        <a href="/logout" class="nav-link">Logout</a>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($error && APP_DEBUG): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 0.5rem; padding: 1rem; margin-bottom: 2rem;">
                <h2 style="color: #ef4444; margin-bottom: 0.5rem;">Application Error</h2>
                <div style="color: var(--quantum-text-muted); font-family: monospace; font-size: 0.875rem;">
                    <?= htmlspecialchars($error->getMessage() . ' in ' . $error->getFile() . ' on line ' . $error->getLine()) ?>
                </div>
            </div>
        <?php endif; ?>

        <section class="hero">
            <h1>Enter the Portal</h1>
            <p class="hero-subtitle">Your Command Deck of Cosmic Awakening</p>
            <p class="hero-description">
                One map to your astrological multiverse: charts, transits, calculations, insights, 
                and cosmic wisdom. Everything, elegantly connected.
            </p>
        </section>

        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-value">47</span>
                <span class="stat-label">Charts Generated</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">12</span>
                <span class="stat-label">Active Transits</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">8</span>
                <span class="stat-label">Cosmic Profiles</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">156</span>
                <span class="stat-label">Insights Generated</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">99.9%</span>
                <span class="stat-label">Swiss Precision</span>
            </div>
        </div>

        <section class="portal-grid">
            <div class="portal-card" onclick="location.href='/charts/create'">
                <div class="card-icon">🎯</div>
                <h3 class="card-title">Natal Chart Generator</h3>
                <p class="card-description">
                    Align your energy centers with sacred frequencies. 
                    Swiss Ephemeris precision for chart activation and cosmic mapping.
                </p>
                <a href="/charts/create" class="card-action">Generate Chart</a>
            </div>

            <div class="portal-card" onclick="location.href='/charts'">
                <div class="card-icon">📚</div>
                <h3 class="card-title">Chart Library</h3>
                <p class="card-description">
                    Access your complete digital archive of cosmic blueprints — 
                    a curated catalog of hidden astrological wisdom.
                </p>
                <a href="/charts" class="card-action">Browse Charts</a>
            </div>

            <div class="portal-card" onclick="showTransitAnalysis()">
                <div class="card-icon">🌙</div>
                <h3 class="card-title">Transit Analysis</h3>
                <p class="card-description">
                    Real-time cosmic exploration. Track planetary movements 
                    and their influence on your personal energy matrix.
                </p>
                <button class="card-action" onclick="showTransitAnalysis()">Analyze Transits</button>
            </div>

            <div class="portal-card" onclick="showProgressions()">
                <div class="card-icon">⚡</div>
                <h3 class="card-title">Progression Engine</h3>
                <p class="card-description">
                    Short, potent cosmic explainers. Secondary progressions, 
                    solar returns — access your evolutionary timeline.
                </p>
                <button class="card-action" onclick="showProgressions()">Calculate</button>
            </div>

            <div class="portal-card" onclick="showNumerology()">
                <div class="card-icon">🔢</div>
                <h3 class="card-title">Numerology Hub</h3>
                <p class="card-description">
                    Direct access to the complete personal numerology system 
                    with real-time cosmic number synchronization.
                </p>
                <button class="card-action" onclick="showNumerology()">Enter Hub</button>
            </div>

            <div class="portal-card" onclick="showOptimalTiming()">
                <div class="card-icon">⏰</div>
                <h3 class="card-title">Timing Optimizer</h3>
                <p class="card-description">
                    Your daily practice portal. Guided cosmic timing sessions, 
                    optimal decision windows, and synchronicity tracking.
                </p>
                <button class="card-action" onclick="showOptimalTiming()">Find Timing</button>
            </div>

            <div class="portal-card" onclick="generateReport()">
                <div class="card-icon">📊</div>
                <h3 class="card-title">Cosmic Reports</h3>
                <p class="card-description">
                    Source docs, references, and long-form research 
                    for serious cosmic spelunkers. Deep-dive analysis.
                </p>
                <button class="card-action" onclick="generateReport()">Generate Report</button>
            </div>

            <div class="portal-card" onclick="showSynastry()">
                <div class="card-icon">💫</div>
                <h3 class="card-title">Relationship Matrix</h3>
                <p class="card-description">
                    Digital collection of relationship compatibility analysis. 
                    Synastry charts and composite cosmic connections.
                </p>
                <button class="card-action" onclick="showSynastry()">Compare Charts</button>
            </div>
        </section>

        <section>
            <h2 style="text-align: center; font-size: 1.5rem; color: var(--quantum-gold); margin-bottom: 1.5rem;">Quick Activations</h2>
            <div class="quick-actions">
                <a href="/charts/create" class="quick-action">🎯 New Chart</a>
                <button onclick="showDailyAspects()" class="quick-action">📅 Today's Aspects</button>
                <button onclick="showMoonPhase()" class="quick-action">🌙 Moon Phase</button>
                <button onclick="showCosmicWeather()" class="quick-action">⚡ Cosmic Weather</button>
                <a href="/profile" class="quick-action">⚙️ Settings</a>
                <button onclick="randomActivation()" class="quick-action">🎲 Random Activation</button>
            </div>
        </section>

        <div class="footer">
            <p>© 2025 Quantum Minds United. Built with love, curiosity, and a dash of cosmic precision.</p>
            <p style="margin-top: 0.5rem;">Questions? <a href="mailto:mark@quantummindsunited.com" style="color: var(--quantum-blue);">Contact</a> • <a href="#" style="color: var(--quantum-blue);">Support the Mission</a></p>
        </div>
    </main>

    <script>
        // Create starfield
        function createStarfield() {
            const starfield = document.getElementById('starfield');
            const starCount = 100;

            for (let i = 0; i < starCount; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                
                const size = Math.random() * 3 + 1;
                star.style.width = size + 'px';
                star.style.height = size + 'px';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 3 + 's';
                star.style.animationDuration = (Math.random() * 2 + 2) + 's';

                starfield.appendChild(star);
            }
        }

        // Interactive functions
        function showTransitAnalysis() {
            alert('Transit Analysis Portal\n\nEntering the living conversation of planetary movements. Track current cosmic influences, aspect patterns, and their resonance with your natal blueprint.\n\nReal-time Swiss Ephemeris precision with elegant UI.');
        }

        function showProgressions() {
            alert('Progression Engine\n\nAccess your evolutionary timeline through secondary progressions, solar returns, and symbolic timing systems.\n\nShort, potent cosmic explainers for your unfolding journey.');
        }

        function showNumerology() {
            alert('Numerology Hub Portal\n\nDirect access to the complete personal numerology system:\n\n• Personal Year, Month & Day cycles\n• Core number calculations\n• Sacred geometry patterns\n• Real-time cosmic number synchronization');
        }

        function showOptimalTiming() {
            alert('Timing Optimizer\n\nYour daily practice portal for cosmic alignment:\n\n• Optimal decision windows\n• Planetary hour calculations\n• Synchronicity tracking\n• Guided timing sessions\n\nEverything, elegantly connected.');
        }

        function generateReport() {
            alert('Cosmic Reports\n\nDeep-dive analysis and long-form research:\n\n• Source documentation\n• Multi-system correlations\n• Historical pattern analysis\n• Professional-grade insights\n\nFor serious cosmic spelunkers.');
        }

        function showSynastry() {
            alert('Relationship Matrix\n\nDigital collection of cosmic connections:\n\n• Synastry chart analysis\n• Composite chart generation\n• Compatibility assessments\n• Karmic relationship patterns\n\nExplore the mathematics of connection.');
        }

        function showDailyAspects() {
            alert('Today\'s Cosmic Weather\n\n🌟 Sun square Mars - Dynamic tension, channel energy constructively\n🌙 Moon trine Jupiter - Emotional expansion and optimism\n☿ Mercury sextile Venus - Harmonious communication in relationships\n\nOverall energy: Balanced action with creative flow');
        }

        function showMoonPhase() {
            alert('Current Lunar Portal\n\n🌔 Waxing Gibbous (87% illuminated)\n\nOptimal for:\n• Building momentum on projects\n• Refinement and adjustment\n• Gathering resources and support\n\nNext Full Moon: September 17, 2025');
        }

        function showCosmicWeather() {
            alert('Live Cosmic Weather Report\n\n⚡ Current Conditions: Stable with building intensity\n🌟 Planetary Ruler: Mercury (communication focus)\n🎯 Energy Quality: 87% harmony between systems\n\nRecommendation: Excellent day for detailed planning and creative communication');
        }

        function randomActivation() {
            const activations = [
                'Today\'s cosmic frequency: 432 Hz - Perfect for meditation and inner alignment',
                'Lucky numbers: 3, 7, 21 - Pay attention to these patterns today',
                'Planetary activation: Venus energy strong - Focus on beauty, love, and harmony',
                'Synchronicity alert: Notice repeated symbols and signs today',
                'Energy shift detected: Major transformation window opening this week'
            ];
            
            const random = activations[Math.floor(Math.random() * activations.length)];
            alert('Random Cosmic Activation\n\n🎲 ' + random);
        }

        // Initialize everything
        document.addEventListener('DOMContentLoaded', () => {
            createStarfield();

            // Add hover effects to cards
            const cards = document.querySelectorAll('.portal-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0) scale(1)';
                });
            });
        });
    </script>
</body>
</html>
