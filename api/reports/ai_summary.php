<?php
# api/reports/ai_summary.php
declare(strict_types=1);

require_once __DIR__ . '/../../classes/autoload.php';
require_once __DIR__ . '/../../config.php';

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\SystemSettings;
use QuantumAstrology\Interpretations\AIInterpreter;
use QuantumAstrology\Reports\ReportArchive;

// API responses must remain valid JSON; avoid emitting PHP warnings/notices as HTML.
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Convert constrained markdown into safe HTML for in-app preview.
 */
function renderMarkdownPreview(string $markdown): string
{
    $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            continue;
        }

        if (str_starts_with($trimmed, '## ')) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h4 style="color: var(--quantum-gold); margin: 1rem 0 0.5rem;">' . htmlspecialchars(substr($trimmed, 3)) . '</h4>';
            continue;
        }

        if (str_starts_with($trimmed, '# ')) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h3 style="color: var(--quantum-primary); margin: 0 0 0.75rem;">' . htmlspecialchars(substr($trimmed, 2)) . '</h3>';
            continue;
        }

        if (str_starts_with($trimmed, '- ')) {
            if (!$inList) {
                $html .= '<ul style="margin: 0.25rem 0 0.75rem 1rem;">';
                $inList = true;
            }
            $html .= '<li style="margin: 0.25rem 0;">' . htmlspecialchars(substr($trimmed, 2)) . '</li>';
            continue;
        }

        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }

        $html .= '<p style="line-height: 1.65; margin: 0.5rem 0;">' . nl2br(htmlspecialchars($trimmed), false) . '</p>';
    }

    if ($inList) {
        $html .= '</ul>';
    }

    return $html;
}

/**
 * Build markdown content from AI interpretation payload.
 */
function buildSummaryMarkdown(array $interpretation, string $reportType): string
{
    $chartName = (string)($interpretation['chart_name'] ?? 'Chart');
    $generatedAt = (string)($interpretation['interpretation_metadata']['generated_at'] ?? date('c'));
    $model = (string)($interpretation['interpretation_metadata']['ai_model'] ?? 'AI');
    $confidence = (int)($interpretation['confidence_score'] ?? 0);

    $sections = [
        'Personality Overview' => $interpretation['personality_overview'] ?? '',
        'Life Purpose' => $interpretation['life_purpose'] ?? '',
        'Relationship Insights' => $interpretation['relationship_insights'] ?? '',
        'Career Guidance' => $interpretation['career_guidance'] ?? '',
        'Challenges and Growth' => $interpretation['challenges_and_growth'] ?? '',
        'Timing Advice' => $interpretation['timing_advice'] ?? '',
        'Overall Synthesis' => $interpretation['overall_synthesis'] ?? '',
    ];

    $lines = [];
    $lines[] = '# AI Summary Report';
    $lines[] = '';
    $lines[] = '- Chart: ' . $chartName;
    $lines[] = '- Report Type: ' . ucfirst($reportType);
    $lines[] = '- Generated: ' . $generatedAt;
    $lines[] = '- Model: ' . $model;
    $lines[] = '- Confidence: ' . $confidence . '%';
    $lines[] = '';

    foreach ($sections as $heading => $content) {
        if (!is_string($content) || trim($content) === '') {
            continue;
        }
        $lines[] = '## ' . $heading;
        $lines[] = '';
        $lines[] = trim($content);
        $lines[] = '';
    }

    return implode("\n", $lines);
}

header('Content-Type: application/json');
Auth::requireLogin();

try {
    $raw = file_get_contents('php://input');
    $input = null;

    if ($raw !== false && trim($raw) !== '') {
        $input = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON request body.']);
            exit;
        }
    }

    if (!is_array($input)) {
        $input = $_POST ?: [];
    }

    $chartId = (int)($input['chart_id'] ?? $_GET['chart_id'] ?? 0);
    $reportType = strtolower(trim((string)($input['report_type'] ?? $_GET['report_type'] ?? 'natal')));
    $format = strtolower(trim((string)($input['format'] ?? $_GET['format'] ?? 'json')));
    $masterAi = SystemSettings::getMasterAiConfig();
    $summaryCfg = SystemSettings::getAiSummaryConfig();
    $provider = trim((string)($masterAi['provider'] ?? ($_ENV['AI_PROVIDER'] ?? 'ollama')));
    $model = trim((string)($masterAi['model'] ?? 'default'));
    $focus = trim((string)($input['focus'] ?? $_GET['focus'] ?? ''));

    if (!in_array($reportType, ['natal', 'transit', 'synastry'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report type.']);
        exit;
    }

    if ($chartId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid chart ID.']);
        exit;
    }

    $chart = Chart::findById($chartId);
    if (!$chart) {
        http_response_code(404);
        echo json_encode(['error' => 'Chart not found.']);
        exit;
    }

    $currentUser = Auth::user();
    if (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied.']);
        exit;
    }

    $focusTemplate = trim((string)($summaryCfg['focus_template'] ?? 'Prioritize a concise summary suitable for a {report_type} report.'));
    $focusFromTemplate = str_replace('{report_type}', $reportType, $focusTemplate);
    $focusPrefix = $focus !== '' ? ($focus . '. ') : '';
    $focusWithReport = trim($focusPrefix . $focusFromTemplate);
    $ai = new AIInterpreter($chart, [
        'provider' => $provider,
        'model' => $model,
        'system_prompt' => (string)($summaryCfg['system_prompt'] ?? ''),
        'focus' => $focusWithReport,
        'style' => (string)($summaryCfg['style'] ?? 'professional'),
        'length' => (string)($summaryCfg['length'] ?? 'short'),
    ]);
    $interpretation = $ai->generateSummaryReport($reportType);

    $markdown = buildSummaryMarkdown($interpretation, $reportType);
    $safeName = preg_replace('/[^a-z0-9\-_]+/i', '_', strtolower((string)$chart->getName())) ?: 'chart';
    $filename = sprintf('ai_summary_%s_%d_%s.md', $safeName, $chartId, date('Ymd_His'));
    $archive = ReportArchive::save(
        (int) $currentUser->getId(),
        $chartId,
        $reportType,
        'ai_summary',
        'md',
        'text/markdown',
        $markdown
    );

    if ($format === 'download') {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $markdown;
        exit;
    }

    if (ob_get_length() > 0) {
        ob_clean();
    }
    echo json_encode([
        'success' => true,
        'chart_id' => $chartId,
        'report_type' => $reportType,
        'history_id' => $archive['id'] ?? null,
        'markdown' => $markdown,
        'filename' => $filename,
        'html_preview' => renderMarkdownPreview($markdown),
        'download_url' => '/api/reports/ai_summary.php?chart_id=' . $chartId . '&report_type=' . urlencode($reportType) . '&focus=' . urlencode($focus) . '&format=download',
    ]);
} catch (Throwable $e) {
    $bufferedOutput = '';
    if (ob_get_length() > 0) {
        $bufferedOutput = trim((string) ob_get_contents());
        ob_clean();
    }

    Logger::error('AI summary report generation failed', [
        'chart_id' => $chartId ?? null,
        'report_type' => $reportType ?? null,
        'error' => $e->getMessage(),
        'buffered_output' => $bufferedOutput !== '' ? mb_substr($bufferedOutput, 0, 1200) : null,
    ]);

    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate AI summary report.']);
}
