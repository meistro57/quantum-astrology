<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$userCharts = Chart::findByUserId($user->getId(), 50); // Get up to 50 charts

$pageTitle = 'Relationship Analysis - Quantum Astrology';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .relationships-container {
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
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .chart-selection {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .selection-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .chart-selectors {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 2rem;
            align-items: center;
        }

        .chart-selector {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .selector-label {
            font-weight: 600;
            color: var(--quantum-text);
            text-align: center;
        }

        .chart-dropdown {
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .chart-dropdown:focus {
            border-color: var(--quantum-primary);
            background: rgba(255, 255, 255, 0.15);
        }

        .chart-dropdown option {
            background: var(--quantum-darker);
            color: white;
        }

        .vs-indicator {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--quantum-gold);
            text-align: center;
            padding: 1rem;
            background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .analysis-options {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .analysis-button {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-width: 140px;
        }

        .btn-synastry {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            color: white;
        }

        .btn-synastry:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
        }

        .btn-composite {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
        }

        .btn-composite:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        .btn-insights {
            background: linear-gradient(135deg, var(--quantum-gold), #FFA500);
            color: var(--quantum-darker);
        }

        .btn-insights:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }

        .analysis-results {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            display: none;
            margin-top: 2rem;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .analysis-type-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--quantum-gold);
        }

        .compatibility-score {
            background: radial-gradient(circle, rgba(255, 215, 0, 0.2) 0%, transparent 70%);
            border: 2px solid var(--quantum-gold);
            border-radius: 50%;
            width: 80px;
            height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .score-value {
            font-size: 1.4rem;
            color: var(--quantum-gold);
        }

        .score-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .results-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .result-section {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--quantum-primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(74, 144, 226, 0.3);
        }

        .aspect-list, .theme-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .aspect-item, .theme-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .aspect-item:last-child, .theme-item:last-child {
            border-bottom: none;
        }

        .aspect-name, .theme-name {
            font-weight: 500;
            color: var(--quantum-text);
        }

        .aspect-strength {
            font-size: 0.9rem;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-weight: 500;
        }

        .strength-high { background: rgba(255, 215, 0, 0.2); color: #FFD700; }
        .strength-medium { background: rgba(74, 144, 226, 0.2); color: #4A90E2; }
        .strength-low { background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.7); }

        .loading-state {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
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

        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 8px;
            padding: 1rem;
            color: #FF6B6B;
            text-align: center;
        }

        .no-charts-message {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .no-charts-message h3 {
            color: var(--quantum-gold);
            margin-bottom: 1rem;
        }

        .btn-create {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }

        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        @media (max-width: 968px) {
            .chart-selectors {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .vs-indicator {
                order: -1;
                margin: 1rem auto;
            }

            .analysis-options {
                flex-direction: column;
                align-items: center;
            }

            .results-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    
    <div class="relationships-container">
        <div class="page-header">
            <h1 class="page-title">Relationship Analysis</h1>
            <div class="page-description">
                Compare two birth charts to explore relationship compatibility through Synastry and Composite analysis
            </div>
        </div>

        <?php if (empty($userCharts)): ?>
        <div class="no-charts-message">
            <h3>No Charts Available</h3>
            <p>You need at least two birth charts to perform relationship analysis.</p>
            <a href="/charts/create" class="btn-create">Create Your First Chart</a>
        </div>
        <?php elseif (count($userCharts) < 2): ?>
        <div class="no-charts-message">
            <h3>More Charts Needed</h3>
            <p>You need at least two birth charts to perform relationship analysis. You currently have <?= count($userCharts) ?> chart<?= count($userCharts) === 1 ? '' : 's' ?>.</p>
            <a href="/charts/create" class="btn-create">Create Another Chart</a>
        </div>
        <?php else: ?>
        
        <div class="chart-selection">
            <h2 class="selection-title">Select Two Charts to Compare</h2>
            
            <div class="chart-selectors">
                <div class="chart-selector">
                    <label class="selector-label">Person 1</label>
                    <select id="chart1-select" class="chart-dropdown">
                        <option value="">Select first chart...</option>
                        <?php foreach ($userCharts as $chart): ?>
                            <option value="<?= $chart->getId() ?>" data-name="<?= htmlspecialchars($chart->getName()) ?>">
                                <?= htmlspecialchars($chart->getName()) ?> 
                                (<?= $chart->getBirthDatetime() ? $chart->getBirthDatetime()->format('M j, Y') : 'Unknown date' ?>)
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="vs-indicator">VS</div>

                <div class="chart-selector">
                    <label class="selector-label">Person 2</label>
                    <select id="chart2-select" class="chart-dropdown">
                        <option value="">Select second chart...</option>
                        <?php foreach ($userCharts as $chart): ?>
                            <option value="<?= $chart->getId() ?>" data-name="<?= htmlspecialchars($chart->getName()) ?>">
                                <?= htmlspecialchars($chart->getName()) ?> 
                                (<?= $chart->getBirthDatetime() ? $chart->getBirthDatetime()->format('M j, Y') : 'Unknown date' ?>)
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <div class="analysis-options">
                <button id="synastry-btn" class="analysis-button btn-synastry" onclick="loadSynastry()" disabled>
                    Synastry Analysis
                </button>
                <button id="composite-btn" class="analysis-button btn-composite" onclick="loadComposite()" disabled>
                    Composite Chart
                </button>
                <button id="insights-btn" class="analysis-button btn-insights" onclick="loadInsights()" disabled>
                    Compatibility Insights
                </button>
            </div>
        </div>

        <div id="analysis-results" class="analysis-results">
            <div class="results-header">
                <h2 id="analysis-title" class="analysis-type-title">Analysis Results</h2>
                <div id="compatibility-score" class="compatibility-score" style="display: none;">
                    <div id="score-value" class="score-value">--</div>
                    <div class="score-label">Score</div>
                </div>
            </div>
            
            <div id="loading-state" class="loading-state">
                <div class="loading-spinner"></div>
                <p>Calculating astrological compatibility...</p>
            </div>
            
            <div id="results-content" class="results-content" style="display: none;">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
        
        <?php endif ?>
    </div>

    <script>
        let selectedChart1 = null;
        let selectedChart2 = null;
        
        // Chart selection handlers
        document.getElementById('chart1-select').addEventListener('change', function() {
            selectedChart1 = this.value ? parseInt(this.value) : null;
            updateAnalysisButtons();
        });
        
        document.getElementById('chart2-select').addEventListener('change', function() {
            selectedChart2 = this.value ? parseInt(this.value) : null;
            updateAnalysisButtons();
        });
        
        function updateAnalysisButtons() {
            const hasValidSelection = selectedChart1 && selectedChart2 && selectedChart1 !== selectedChart2;
            const buttons = ['synastry-btn', 'composite-btn', 'insights-btn'];
            
            buttons.forEach(buttonId => {
                const button = document.getElementById(buttonId);
                button.disabled = !hasValidSelection;
                button.style.opacity = hasValidSelection ? '1' : '0.5';
                button.style.cursor = hasValidSelection ? 'pointer' : 'not-allowed';
            });
            
            if (selectedChart1 === selectedChart2 && selectedChart1) {
                showError('Please select two different charts for comparison.');
            } else {
                hideError();
            }
        }
        
        // Analysis functions
        async function loadSynastry() {
            if (!validateSelection()) return;
            
            showLoading('Synastry Analysis');
            
            try {
                const response = await fetch(`/api/charts/${selectedChart1}/synastry/${selectedChart2}`);
                const data = await response.json();
                
                if (response.ok) {
                    displaySynastryResults(data);
                } else {
                    showError(data.error || 'Failed to load synastry analysis');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }
        
        async function loadComposite() {
            if (!validateSelection()) return;
            
            showLoading('Composite Chart Analysis');
            
            try {
                const response = await fetch(`/api/charts/${selectedChart1}/composite/${selectedChart2}`);
                const data = await response.json();
                
                if (response.ok) {
                    displayCompositeResults(data);
                } else {
                    showError(data.error || 'Failed to load composite chart');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }
        
        async function loadInsights() {
            if (!validateSelection()) return;
            
            showLoading('Compatibility Insights');
            
            try {
                const [synastryResponse, compositeResponse] = await Promise.all([
                    fetch(`/api/charts/${selectedChart1}/synastry/${selectedChart2}/insights`),
                    fetch(`/api/charts/${selectedChart1}/composite/${selectedChart2}/insights`)
                ]);
                
                const synastryData = await synastryResponse.json();
                const compositeData = await compositeResponse.json();
                
                if (synastryResponse.ok && compositeResponse.ok) {
                    displayInsightsResults(synastryData, compositeData);
                } else {
                    showError('Failed to load compatibility insights');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }
        
        function validateSelection() {
            if (!selectedChart1 || !selectedChart2) {
                showError('Please select two charts for comparison.');
                return false;
            }
            
            if (selectedChart1 === selectedChart2) {
                showError('Please select two different charts for comparison.');
                return false;
            }
            
            return true;
        }
        
        function showLoading(analysisType) {
            document.getElementById('analysis-results').style.display = 'block';
            document.getElementById('analysis-title').textContent = analysisType;
            document.getElementById('loading-state').style.display = 'block';
            document.getElementById('results-content').style.display = 'none';
            document.getElementById('compatibility-score').style.display = 'none';
        }
        
        function displaySynastryResults(data) {
            hideLoading();
            
            const compatibilityScore = data.compatibility_summary?.overall_score || 0;
            showCompatibilityScore(compatibilityScore);
            
            const content = `
                <div class="result-section">
                    <h3 class="section-title">Key Synastry Aspects</h3>
                    <ul class="aspect-list">
                        ${data.synastry_aspects?.slice(0, 10).map(aspect => `
                            <li class="aspect-item">
                                <span class="aspect-name">
                                    ${capitalize(aspect.person1_planet)} ${capitalize(aspect.aspect)} ${capitalize(aspect.person2_planet)}
                                </span>
                                <span class="aspect-strength ${getStrengthClass(aspect.strength)}">
                                    ${aspect.orb.toFixed(1)}° (${aspect.strength || 0}%)
                                </span>
                            </li>
                        `).join('') || '<li class="aspect-item">No significant aspects found</li>'}
                    </ul>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Relationship Strengths</h3>
                    <ul class="theme-list">
                        ${data.compatibility_summary?.top_strengths?.map(strength => `
                            <li class="theme-item">
                                <span class="theme-name">${strength}</span>
                            </li>
                        `).join('') || '<li class="theme-item">No specific strengths identified</li>'}
                    </ul>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Potential Challenges</h3>
                    <ul class="theme-list">
                        ${data.compatibility_summary?.main_challenges?.map(challenge => `
                            <li class="theme-item">
                                <span class="theme-name">${challenge}</span>
                            </li>
                        `).join('') || '<li class="theme-item">No major challenges identified</li>'}
                    </ul>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Dominant Themes</h3>
                    <ul class="theme-list">
                        ${data.compatibility_summary?.dominant_themes?.map(theme => `
                            <li class="theme-item">
                                <span class="theme-name">${theme}</span>
                            </li>
                        `).join('') || '<li class="theme-item">No dominant themes identified</li>'}
                    </ul>
                </div>
            `;
            
            document.getElementById('results-content').innerHTML = content;
            document.getElementById('results-content').style.display = 'grid';
        }
        
        function displayCompositeResults(data) {
            hideLoading();
            
            const content = `
                <div class="result-section">
                    <h3 class="section-title">Composite Chart Info</h3>
                    <div style="margin-bottom: 1rem;">
                        <strong>Composite Date:</strong> ${new Date(data.composite_datetime).toLocaleString()}
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <strong>Location:</strong> ${data.composite_location.name}
                    </div>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Major Composite Aspects</h3>
                    <ul class="aspect-list">
                        ${data.aspects?.slice(0, 8).map(aspect => `
                            <li class="aspect-item">
                                <span class="aspect-name">
                                    ${capitalize(aspect.planet1)} ${capitalize(aspect.aspect)} ${capitalize(aspect.planet2)}
                                </span>
                                <span class="aspect-strength ${getStrengthClass(aspect.strength)}">
                                    ${aspect.orb.toFixed(1)}° (${aspect.strength || 0}%)
                                </span>
                            </li>
                        `).join('') || '<li class="aspect-item">No significant aspects found</li>'}
                    </ul>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Relationship Themes</h3>
                    <div style="margin-bottom: 1rem;">
                        <strong>Primary Purpose:</strong> 
                        ${data.relationship_themes?.primary_purpose?.join(', ') || 'Balanced growth and harmony'}
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <strong>Relationship Style:</strong> 
                        ${data.relationship_themes?.relationship_style?.replace(/_/g, ' ') || 'Balanced'}
                    </div>
                    <div>
                        <strong>Dominant Elements:</strong> 
                        ${data.relationship_themes?.dominant_elements?.map(e => capitalize(e)).join(', ') || 'Balanced'}
                    </div>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Evolutionary Goal</h3>
                    <div>
                        ${data.relationship_themes?.evolutionary_goal || 'Personal growth through partnership'}
                    </div>
                </div>
            `;
            
            document.getElementById('results-content').innerHTML = content;
            document.getElementById('results-content').style.display = 'grid';
        }
        
        function displayInsightsResults(synastryData, compositeData) {
            hideLoading();
            
            const content = `
                <div class="result-section">
                    <h3 class="section-title">Compatibility Overview</h3>
                    <div style="margin-bottom: 1rem;">
                        <strong>Compatibility Level:</strong> 
                        <span style="color: var(--quantum-gold);">
                            ${synastryData.insights?.compatibility_level || 'Moderate'}
                        </span>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <strong>Relationship Type:</strong> 
                        ${synastryData.insights?.relationship_type || 'Balanced Partnership'}
                    </div>
                    <div>
                        <strong>Long-term Potential:</strong> 
                        ${synastryData.insights?.long_term_potential || 'Good potential'}
                    </div>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Key Strengths</h3>
                    <ul class="theme-list">
                        ${synastryData.insights?.key_strengths?.map(strength => `
                            <li class="theme-item">
                                <span class="theme-name">${strength}</span>
                            </li>
                        `).join('') || '<li class="theme-item">Natural compatibility</li>'}
                    </ul>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Growth Areas</h3>
                    <ul class="theme-list">
                        ${synastryData.insights?.potential_challenges?.map(challenge => `
                            <li class="theme-item">
                                <span class="theme-name">${challenge}</span>
                            </li>
                        `).join('') || '<li class="theme-item">Minor communication adjustments</li>'}
                    </ul>
                </div>
                
                <div class="result-section">
                    <h3 class="section-title">Relationship Advice</h3>
                    <ul class="theme-list">
                        ${synastryData.insights?.advice?.map(advice => `
                            <li class="theme-item">
                                <span class="theme-name">${advice}</span>
                            </li>
                        `).join('') || '<li class="theme-item">Focus on open communication and mutual respect</li>'}
                    </ul>
                </div>
            `;
            
            document.getElementById('results-content').innerHTML = content;
            document.getElementById('results-content').style.display = 'grid';
        }
        
        function showCompatibilityScore(score) {
            document.getElementById('compatibility-score').style.display = 'flex';
            document.getElementById('score-value').textContent = Math.round(score);
            
            // Color code the score
            const scoreElement = document.getElementById('score-value');
            if (score >= 80) {
                scoreElement.style.color = '#00ff00';
            } else if (score >= 60) {
                scoreElement.style.color = '#FFD700';
            } else if (score >= 40) {
                scoreElement.style.color = '#FFA500';
            } else {
                scoreElement.style.color = '#FF6B6B';
            }
        }
        
        function hideLoading() {
            document.getElementById('loading-state').style.display = 'none';
        }
        
        function showError(message) {
            const results = document.getElementById('analysis-results');
            results.style.display = 'block';
            results.innerHTML = `<div class="error-message">${message}</div>`;
        }
        
        function hideError() {
            // Clear any existing error messages
        }
        
        function getStrengthClass(strength) {
            if (strength >= 80) return 'strength-high';
            if (strength >= 60) return 'strength-medium';
            return 'strength-low';
        }
        
        function capitalize(str) {
            return str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }
        
        // Particle animation
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
        
        for (let i = 0; i < 30; i++) {
            setTimeout(createParticle, i * 300);
        }
        
        setInterval(() => {
            createParticle();
        }, 2000);
    </script>
</body>
</html>