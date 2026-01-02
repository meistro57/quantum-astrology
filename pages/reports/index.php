<?php // pages/reports/index.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$userCharts = Chart::findByUserId($user->getId(), 100);

$pageTitle = 'Professional Reports - Quantum Astrology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .reports-container {
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
            max-width: 700px;
            margin: 0 auto;
        }

        .report-builder {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .builder-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 1.5rem;
        }

        .builder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--quantum-text);
        }

        .form-select {
            padding: 0.875rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--quantum-primary);
            background: rgba(255, 255, 255, 0.15);
        }

        .form-select option {
            background: var(--quantum-darker);
            color: white;
        }

        .report-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .report-type-card {
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .report-type-card:hover {
            border-color: var(--quantum-primary);
            transform: translateY(-2px);
        }

        .report-type-card.selected {
            border-color: var(--quantum-gold);
            background: rgba(255, 215, 0, 0.1);
        }

        .report-type-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .report-type-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .report-type-desc {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-generate {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
        }

        .btn-generate:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        .btn-download {
            background: linear-gradient(135deg, var(--quantum-gold), #FFA500);
            color: var(--quantum-darker);
        }

        .btn-download:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .loading-state {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.6);
            display: none;
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

        .pdf-preview {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            display: none;
        }

        .preview-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 1rem;
        }

        .pdf-embed {
            width: 100%;
            height: 800px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            background: white;
        }

        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 10px;
            padding: 1.5rem;
            color: #FF6B6B;
            text-align: center;
            margin-top: 1rem;
            display: none;
        }

        .no-charts {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .no-charts h3 {
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

        @media (max-width: 768px) {
            .builder-grid {
                grid-template-columns: 1fr;
            }

            .report-types {
                grid-template-columns: 1fr;
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
            <a href="/reports" style="color: var(--quantum-gold); text-decoration: none;">Reports</a>
        </nav>
    </header>

    <div class="reports-container">
        <div class="page-actions">
            <button type="button" class="back-button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='/dashboard'">
                <span class="icon" aria-hidden="true">←</span>
                <span>Back</span>
            </button>
        </div>
        <div class="page-header">
            <h1 class="page-title">Professional Astrological Reports</h1>
            <p class="page-description">
                Generate comprehensive PDF reports for your natal charts with detailed interpretations,
                planetary positions, aspects, and professional Quantum Minds United branding.
            </p>
        </div>

        <?php if (empty($userCharts)): ?>
        <div class="no-charts">
            <h3>No Charts Available</h3>
            <p>Create a natal chart first to generate reports.</p>
            <a href="/charts/create" class="btn-create">Create Your First Chart</a>
        </div>
        <?php else: ?>

        <div class="report-builder">
            <h2 class="builder-title">Report Builder</h2>

            <div class="builder-grid">
                <div class="form-group">
                    <label class="form-label">Select Chart</label>
                    <select id="chart-select" class="form-select">
                        <option value="">Choose a chart...</option>
                        <?php foreach ($userCharts as $chart): ?>
                            <option value="<?= $chart->getId() ?>">
                                <?= htmlspecialchars($chart->getName()) ?>
                                (<?= $chart->getBirthDatetime() ? $chart->getBirthDatetime()->format('M j, Y') : 'Unknown' ?>)
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label">Report Type</label>
                <div class="report-types">
                    <div class="report-type-card selected" data-type="natal">
                        <div class="report-type-icon">☉</div>
                        <div class="report-type-name">Natal Chart</div>
                        <div class="report-type-desc">Comprehensive birth chart analysis</div>
                    </div>
                    <div class="report-type-card" data-type="transit">
                        <div class="report-type-icon">↻</div>
                        <div class="report-type-name">Transit Report</div>
                        <div class="report-type-desc">Current planetary influences</div>
                    </div>
                    <div class="report-type-card" data-type="synastry">
                        <div class="report-type-icon">♥</div>
                        <div class="report-type-name">Synastry</div>
                        <div class="report-type-desc">Relationship compatibility</div>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button id="generate-btn" class="btn btn-generate" disabled>Generate Report</button>
                <button id="download-btn" class="btn btn-download" disabled style="display: none;">Download PDF</button>
            </div>

            <div id="error" class="error-message"></div>
        </div>

        <div id="loading" class="loading-state">
            <div class="loading-spinner"></div>
            <p>Generating your professional report...</p>
        </div>

        <div id="pdf-preview" class="pdf-preview">
            <h2 class="preview-title">Report Preview</h2>
            <iframe id="pdf-embed" class="pdf-embed"></iframe>
        </div>

        <?php endif ?>
    </div>

    <script>
        let selectedChartId = null;
        let selectedReportType = 'natal';
        let currentPdfUrl = null;

        const chartSelect = document.getElementById('chart-select');
        const generateBtn = document.getElementById('generate-btn');
        const downloadBtn = document.getElementById('download-btn');
        const loadingDiv = document.getElementById('loading');
        const errorDiv = document.getElementById('error');
        const pdfPreview = document.getElementById('pdf-preview');
        const pdfEmbed = document.getElementById('pdf-embed');

        // Chart selection
        chartSelect?.addEventListener('change', function() {
            selectedChartId = this.value ? parseInt(this.value) : null;
            updateGenerateButton();
        });

        // Report type selection
        document.querySelectorAll('.report-type-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.report-type-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedReportType = this.dataset.type;
            });
        });

        function updateGenerateButton() {
            generateBtn.disabled = !selectedChartId;
        }

        // Generate report
        generateBtn?.addEventListener('click', async function() {
            if (!selectedChartId) return;

            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            pdfPreview.style.display = 'none';
            downloadBtn.style.display = 'none';

            try {
                const response = await fetch('/api/reports/generate.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        chart_id: selectedChartId,
                        report_type: selectedReportType,
                        format: 'pdf'
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to generate report');
                }

                // Show PDF preview
                const pdfBlob = base64ToBlob(data.pdf_base64, 'application/pdf');
                currentPdfUrl = URL.createObjectURL(pdfBlob);

                pdfEmbed.src = currentPdfUrl;
                pdfPreview.style.display = 'block';
                downloadBtn.style.display = 'inline-block';
                downloadBtn.disabled = false;

            } catch (error) {
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            } finally {
                loadingDiv.style.display = 'none';
            }
        });

        // Download report
        downloadBtn?.addEventListener('click', function() {
            if (!selectedChartId) return;

            const url = `/api/reports/generate.php?chart_id=${selectedChartId}&report_type=${selectedReportType}&format=download`;
            window.location.href = url;
        });

        function base64ToBlob(base64, mimeType) {
            const byteCharacters = atob(base64);
            const byteNumbers = new Array(byteCharacters.length);

            for (let i = 0; i < byteCharacters.length; i++) {
                byteNumbers[i] = byteCharacters.charCodeAt(i);
            }

            const byteArray = new Uint8Array(byteNumbers);
            return new Blob([byteArray], {type: mimeType});
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
            setTimeout(() => particle.remove(), 20000);
        }

        for (let i = 0; i < 30; i++) {
            setTimeout(createParticle, i * 300);
        }
        setInterval(createParticle, 2000);
    </script>
</body>
</html>

