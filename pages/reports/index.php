<?php // pages/reports/index.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\AdminGate;
use QuantumAstrology\Charts\Chart;

Auth::requireLogin();

$user = Auth::user();
$userCharts = Chart::findByUserId($user->getId(), 100);
$creatorLabel = trim((string)($user?->getUsername() ?? 'Unknown'));
$showAdminLink = AdminGate::canAccess($user);

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
        body {
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
        .ai-preview-body {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            color: rgba(255, 255, 255, 0.92);
        }

        .pdf-embed {
            width: 100%;
            height: 800px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            background: var(--quantum-darker);
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
        .history-panel {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.25rem;
            margin-top: 1.5rem;
        }
        .history-table-wrap {
            overflow-x: auto;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 740px;
        }
        .history-table th,
        .history-table td {
            text-align: left;
            padding: 0.7rem 0.6rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            vertical-align: middle;
        }
        .history-table th {
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .history-kind {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.2rem 0.6rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--quantum-gold);
        }
        .history-actions {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }
        .history-link {
            color: var(--quantum-gold);
            text-decoration: none;
            font-weight: 600;
        }
        .history-link:hover {
            text-decoration: underline;
        }
        .history-empty {
            color: rgba(255, 255, 255, 0.65);
            margin: 0.2rem 0 0;
        }
        .history-error {
            color: #ff8f8f;
            margin: 0.2rem 0 0;
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
            .reports-container {
                padding: 1rem;
            }
            .builder-grid {
                grid-template-columns: 1fr;
            }

            .report-types {
                grid-template-columns: 1fr;
            }
            .history-table {
                min-width: 620px;
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
            <a href="/charts">Charts</a>
            <a href="/reports" class="active">Reports</a>
            <?php if ($showAdminLink): ?><a href="/admin">Admin</a><?php endif; ?>
            <a href="/profile">Profile</a>
            <a href="/logout">Logout</a>
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
                                (<?= $chart->getBirthDatetime() ? $chart->getBirthDatetime()->format('M j, Y') : 'Unknown' ?> · Created by <?= htmlspecialchars($creatorLabel) ?>)
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
                <button id="generate-ai-summary-btn" class="btn btn-secondary" disabled>Generate AI Summary (.md)</button>
                <button id="download-ai-summary-btn" class="btn btn-download" disabled style="display: none;">Download AI Summary (.md)</button>
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

        <div id="ai-preview" class="pdf-preview">
            <h2 class="preview-title">AI Summary Preview</h2>
            <div id="ai-preview-body" class="ai-preview-body"></div>
        </div>

        <div class="history-panel">
            <h2 class="preview-title" style="margin-bottom: 0.65rem;">Previous Reports</h2>
            <p id="history-status" class="history-empty">Loading report history...</p>
            <div class="history-table-wrap">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Chart</th>
                            <th>Created By</th>
                            <th>Type</th>
                            <th>Kind</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="history-body"></tbody>
                </table>
            </div>
            <div id="history-inline-preview" class="pdf-preview" style="display:none; margin-top:1rem;">
                <h2 class="preview-title">Saved Report View</h2>
                <div id="history-inline-title" style="margin-bottom:0.8rem;color:rgba(255,255,255,0.75);font-size:0.9rem;font-weight:600;"></div>
                <div id="history-inline-markdown" class="ai-preview-body" style="display:none;"></div>
                <iframe id="history-inline-frame" class="pdf-embed" style="display:none;"></iframe>
            </div>
        </div>

        <?php endif ?>
    </div>

    <script>
        let selectedChartId = null;
        let selectedReportType = 'natal';
        let currentPdfUrl = null;
        let currentAiSummaryDownloadUrl = null;
        let currentAiSummaryMarkdown = null;
        let currentAiSummaryFilename = null;

        const chartSelect = document.getElementById('chart-select');
        const generateBtn = document.getElementById('generate-btn');
        const downloadBtn = document.getElementById('download-btn');
        const generateAiSummaryBtn = document.getElementById('generate-ai-summary-btn');
        const downloadAiSummaryBtn = document.getElementById('download-ai-summary-btn');
        const loadingDiv = document.getElementById('loading');
        const errorDiv = document.getElementById('error');
        const pdfPreview = document.getElementById('pdf-preview');
        const pdfEmbed = document.getElementById('pdf-embed');
        const aiPreview = document.getElementById('ai-preview');
        const aiPreviewBody = document.getElementById('ai-preview-body');
        const historyBody = document.getElementById('history-body');
        const historyStatus = document.getElementById('history-status');
        const historyItemsById = new Map();
        const historyInlinePreview = document.getElementById('history-inline-preview');
        const historyInlineTitle = document.getElementById('history-inline-title');
        const historyInlineMarkdown = document.getElementById('history-inline-markdown');
        const historyInlineFrame = document.getElementById('history-inline-frame');

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
            generateAiSummaryBtn.disabled = !selectedChartId;
        }

        // Generate report
        generateBtn?.addEventListener('click', async function() {
            if (!selectedChartId) return;

            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            pdfPreview.style.display = 'none';
            aiPreview.style.display = 'none';
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

                const data = await parseJsonResponse(response);

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
                loadReportHistory();

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

        generateAiSummaryBtn?.addEventListener('click', async function() {
            if (!selectedChartId) return;

            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            aiPreview.style.display = 'none';
            pdfPreview.style.display = 'none';
            downloadAiSummaryBtn.style.display = 'none';
            currentAiSummaryMarkdown = null;
            currentAiSummaryFilename = null;

            try {
                const response = await fetch('/api/reports/ai_summary.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        chart_id: selectedChartId,
                        report_type: selectedReportType,
                        format: 'json'
                    })
                });

                const data = await parseJsonResponse(response);
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to generate AI summary report');
                }

                aiPreviewBody.innerHTML = data.html_preview || '<div class="no-data">No AI summary content returned.</div>';
                aiPreview.style.display = 'block';
                currentAiSummaryDownloadUrl = data.download_url || null;
                currentAiSummaryMarkdown = typeof data.markdown === 'string' ? data.markdown : null;
                currentAiSummaryFilename = typeof data.filename === 'string' && data.filename.trim() !== ''
                    ? data.filename
                    : null;
                if (currentAiSummaryDownloadUrl) {
                    downloadAiSummaryBtn.style.display = 'inline-block';
                    downloadAiSummaryBtn.disabled = false;
                }
                loadReportHistory();
            } catch (error) {
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            } finally {
                loadingDiv.style.display = 'none';
            }
        });

        downloadAiSummaryBtn?.addEventListener('click', function() {
            if (currentAiSummaryMarkdown) {
                const filename = currentAiSummaryFilename
                    || `ai_summary_chart_${selectedChartId || 'unknown'}_${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.md`;
                const blob = new Blob([currentAiSummaryMarkdown], { type: 'text/markdown;charset=utf-8' });
                const blobUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);
                return;
            }

            if (currentAiSummaryDownloadUrl) {
                window.location.href = currentAiSummaryDownloadUrl;
            }
        });

        async function parseJsonResponse(response) {
            const raw = await response.text();
            try {
                return JSON.parse(raw);
            } catch (err) {
                const short = raw.replace(/\s+/g, ' ').slice(0, 160);
                throw new Error(short ? `Server returned non-JSON response: ${short}` : 'Server returned non-JSON response.');
            }
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatBytes(bytes) {
            const num = Number(bytes || 0);
            if (!Number.isFinite(num) || num <= 0) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = num;
            let unit = 0;
            while (size >= 1024 && unit < units.length - 1) {
                size /= 1024;
                unit += 1;
            }
            return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
        }

        function renderHistoryRows(items) {
            if (!historyBody || !historyStatus) return;
            historyItemsById.clear();
            if (!Array.isArray(items) || items.length === 0) {
                historyBody.innerHTML = '';
                historyStatus.textContent = 'No previous reports yet.';
                historyStatus.className = 'history-empty';
                return;
            }

            const rows = items.map(item => {
                const itemId = Number(item.id || 0);
                historyItemsById.set(itemId, item);
                const created = item.created_at ? new Date(item.created_at) : null;
                const createdLabel = created && !Number.isNaN(created.getTime())
                    ? created.toLocaleString()
                    : 'Unknown';
                const kind = escapeHtml(item.kind || 'report');
                const downloadUrl = escapeHtml(item.download_url || '#');

                return `
                    <tr>
                        <td>${escapeHtml(createdLabel)}</td>
                        <td>${escapeHtml(item.chart_name || `Chart #${item.chart_id || '?'}`)}</td>
                        <td>${escapeHtml(item.created_by || 'Unknown')}</td>
                        <td>${escapeHtml(item.report_type || 'natal')}</td>
                        <td><span class="history-kind">${kind}</span></td>
                        <td>${escapeHtml(formatBytes(item.file_size || 0))}</td>
                        <td class="history-actions">
                            <button type="button" class="history-link history-view-btn" data-history-id="${itemId}" style="background:none;border:none;padding:0;cursor:pointer;">View</button>
                            <a class="history-link" href="${downloadUrl}">Download</a>
                        </td>
                    </tr>
                `;
            });

            historyBody.innerHTML = rows.join('');
            historyStatus.textContent = `Showing ${items.length} saved report${items.length === 1 ? '' : 's'}.`;
            historyStatus.className = 'history-empty';
        }

        historyBody?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const viewButton = target.closest('.history-view-btn');
            if (!(viewButton instanceof HTMLElement)) return;
            const id = Number(viewButton.dataset.historyId || 0);
            if (!Number.isFinite(id) || id <= 0) return;
            event.preventDefault();
            viewHistoryItem(id);
        });

        async function loadReportHistory() {
            if (!historyBody || !historyStatus) return;
            historyStatus.textContent = 'Loading report history...';
            historyStatus.className = 'history-empty';

            try {
                const response = await fetch('/api/reports/history.php?limit=40', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await parseJsonResponse(response);
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to load report history.');
                }
                renderHistoryRows(data.items || []);
            } catch (error) {
                historyBody.innerHTML = '';
                historyStatus.textContent = error.message || 'Failed to load report history.';
                historyStatus.className = 'history-error';
            }
        }

        function renderSimpleMarkdown(markdown) {
            const lines = String(markdown || '').split(/\r\n|\r|\n/);
            let html = '';
            let inList = false;

            for (const line of lines) {
                const trimmed = line.trim();
                if (trimmed === '') {
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    continue;
                }

                if (trimmed.startsWith('## ')) {
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    html += `<h2>${escapeHtml(trimmed.slice(3))}</h2>`;
                    continue;
                }

                if (trimmed.startsWith('# ')) {
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    html += `<h1>${escapeHtml(trimmed.slice(2))}</h1>`;
                    continue;
                }

                if (trimmed.startsWith('- ')) {
                    if (!inList) {
                        html += '<ul>';
                        inList = true;
                    }
                    html += `<li>${escapeHtml(trimmed.slice(2))}</li>`;
                    continue;
                }

                if (inList) {
                    html += '</ul>';
                    inList = false;
                }
                html += `<p>${escapeHtml(trimmed)}</p>`;
            }

            if (inList) {
                html += '</ul>';
            }
            return html;
        }

        async function viewHistoryItem(id) {
            const item = historyItemsById.get(Number(id));
            if (!item) {
                historyStatus.textContent = 'History item not found.';
                historyStatus.className = 'history-error';
                return;
            }

            const kind = String(item.kind || '').toLowerCase();
            const mime = String(item.mime_type || '').toLowerCase();
            const viewUrl = String(item.view_url || '');
            const title = `${item.chart_name || 'Chart'} - ${item.report_type || 'report'} (${item.kind || 'item'})`;

            try {
                if (historyInlineTitle) {
                    historyInlineTitle.textContent = title;
                }

                if (kind === 'ai_summary' || mime.includes('markdown') || mime.includes('text/plain')) {
                    const response = await fetch(viewUrl, { headers: { 'Accept': 'text/markdown,text/plain,*/*' } });
                    if (!response.ok) {
                        throw new Error('Failed to load summary content.');
                    }
                    const markdown = await response.text();
                    const html = renderSimpleMarkdown(markdown);
                    if (historyInlineMarkdown) {
                        historyInlineMarkdown.innerHTML = html || '<p>No content.</p>';
                        historyInlineMarkdown.style.display = 'block';
                    }
                    if (historyInlineFrame) {
                        historyInlineFrame.style.display = 'none';
                        historyInlineFrame.src = 'about:blank';
                    }
                    if (historyInlinePreview) {
                        historyInlinePreview.style.display = 'block';
                        historyInlinePreview.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    return;
                }

                // Render PDF and other files directly in the same page.
                if (historyInlineMarkdown) {
                    historyInlineMarkdown.style.display = 'none';
                    historyInlineMarkdown.innerHTML = '';
                }
                if (historyInlineFrame) {
                    historyInlineFrame.src = viewUrl;
                    historyInlineFrame.style.display = 'block';
                }
                if (historyInlinePreview) {
                    historyInlinePreview.style.display = 'block';
                    historyInlinePreview.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } catch (error) {
                historyStatus.textContent = error.message || 'Failed to open report.';
                historyStatus.className = 'history-error';
            }
        }

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
        loadReportHistory();
    </script>
</body>
</html>
