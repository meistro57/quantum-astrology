<?php
declare(strict_types=1);

require_once __DIR__ . '/../../classes/autoload.php';
require_once __DIR__ . '/../../config.php';

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\SystemSettings;
use QuantumAstrology\Interpretations\AIInterpreter;
use QuantumAstrology\Reports\ReportArchive;

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Convert constrained markdown into safe HTML for in-app preview.
 */
function renderMasterMarkdownPreview(string $markdown): string
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

        if (str_starts_with($trimmed, '### ')) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<h5 style="color: var(--quantum-gold); margin: 0.8rem 0 0.4rem;">' . htmlspecialchars(substr($trimmed, 4)) . '</h5>';
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
 * @return array{age_seconds:int,payload:array<string,mixed>}|null
 */
function readCachedMasterDoc(string $cacheFile, int $cacheTtl, bool $fresh): ?array
{
    if ($fresh || $cacheTtl <= 0 || !is_file($cacheFile)) {
        return null;
    }

    $age = time() - (int) @filemtime($cacheFile);
    if ($age < 0 || $age > $cacheTtl) {
        return null;
    }

    $decoded = json_decode((string) @file_get_contents($cacheFile), true);
    if (!is_array($decoded) || !isset($decoded['markdown'])) {
        return null;
    }

    return [
        'age_seconds' => $age,
        'payload' => $decoded,
    ];
}

/**
 * @param array<string,mixed> $payload
 */
function writeCachedMasterDoc(string $cacheDir, string $cacheFile, array $payload): void
{
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * @param array<string,mixed> $interpretation
 */
function buildMasterSectionMarkdown(array $interpretation, string $reportType): string
{
    $model = (string)($interpretation['interpretation_metadata']['ai_model'] ?? 'AI');
    $confidence = (int)($interpretation['confidence_score'] ?? 0);
    $personality = trim((string)($interpretation['personality_overview'] ?? ''));
    $synthesis = trim((string)($interpretation['overall_synthesis'] ?? ''));

    $lines = [];
    $lines[] = '## ' . ucfirst($reportType) . ' Report AI Summary';
    $lines[] = '';
    $lines[] = '- Model: ' . $model;
    $lines[] = '- Confidence: ' . $confidence . '%';
    $lines[] = '';
    $lines[] = '### Core Narrative';
    $lines[] = '';
    $lines[] = $personality !== '' ? $personality : 'No core narrative returned.';
    $lines[] = '';
    $lines[] = '### Overall Synthesis';
    $lines[] = '';
    $lines[] = $synthesis !== '' ? $synthesis : 'No synthesis returned.';
    $lines[] = '';

    return implode("\n", $lines);
}

/**
 * @param array<string,string> $sections
 */
function buildMasterDocumentMarkdown(string $chartName, array $sections): string
{
    $lines = [];
    $lines[] = '# Comprehensive Astrology Intelligence Dossier';
    $lines[] = '';
    $lines[] = '- Chart: ' . $chartName;
    $lines[] = '- Generated: ' . date('c');
    $lines[] = '- Included Reports: Natal, Transit, Synastry';
    $lines[] = '';
    $lines[] = '## Executive Overview';
    $lines[] = '';
    $lines[] = 'This dossier consolidates all major report perspectives into one streamlined document with AI-generated summaries for fast review and decision support.';
    $lines[] = '';

    foreach (['natal', 'transit', 'synastry'] as $type) {
        if (isset($sections[$type]) && trim($sections[$type]) !== '') {
            $lines[] = trim($sections[$type]);
            $lines[] = '';
        }
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
    $format = strtolower(trim((string)($input['format'] ?? $_GET['format'] ?? 'json')));
    $focus = trim((string)($input['focus'] ?? $_GET['focus'] ?? ''));
    $fresh = filter_var($input['fresh'] ?? $_GET['fresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $cacheTtl = (int)($_ENV['AI_SUMMARY_CACHE_TTL'] ?? $_ENV['AI_CACHE_TTL'] ?? 21600);
    if ($cacheTtl < 0) {
        $cacheTtl = 21600;
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

    $masterAi = SystemSettings::getMasterAiConfig();
    $summaryCfg = SystemSettings::getAiSummaryConfig();
    $provider = trim((string)($masterAi['provider'] ?? ($_ENV['AI_PROVIDER'] ?? 'ollama')));
    $model = trim((string)($masterAi['model'] ?? 'default'));

    $focusTemplate = trim((string)($summaryCfg['focus_template'] ?? 'Prioritize a concise summary suitable for a {report_type} report.'));
    $cacheKeyPayload = [
        'v' => 1,
        'chart_id' => $chartId,
        'user_id' => (int) $currentUser->getId(),
        'chart_updated' => (string)($chart->getUpdatedAt() ?? ''),
        'provider' => strtolower($provider),
        'model' => $model,
        'master_ai_updated_at' => (string)($masterAi['updated_at'] ?? ''),
        'system_prompt' => (string)($summaryCfg['system_prompt'] ?? ''),
        'style' => (string)($summaryCfg['style'] ?? ''),
        'length' => (string)($summaryCfg['length'] ?? ''),
        'focus_template' => $focusTemplate,
        'focus' => $focus,
        'scope' => 'natal_transit_synastry',
    ];
    $cacheKey = sha1(json_encode($cacheKeyPayload, JSON_UNESCAPED_SLASHES));
    $cacheDir = STORAGE_PATH . '/cache/ai_summary';
    $cacheFile = $cacheDir . '/master_doc_' . $cacheKey . '.json';

    $cached = readCachedMasterDoc($cacheFile, $cacheTtl, $fresh);
    if (is_array($cached)) {
        $cachedPayload = $cached['payload'];
        $cachedMarkdown = (string)($cachedPayload['markdown'] ?? '');
        $cachedFilename = (string)($cachedPayload['filename'] ?? ('ai_master_document_chart_' . $chartId . '.md'));

        if ($format === 'download') {
            if (ob_get_length() > 0) {
                ob_clean();
            }
            header('Content-Type: text/markdown; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $cachedFilename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $cachedMarkdown;
            exit;
        }

        if (ob_get_length() > 0) {
            ob_clean();
        }
        echo json_encode([
            'success' => true,
            'chart_id' => $chartId,
            'report_type' => 'master',
            'history_id' => $cachedPayload['history_id'] ?? null,
            'markdown' => $cachedMarkdown,
            'filename' => $cachedFilename,
            'html_preview' => (string)($cachedPayload['html_preview'] ?? renderMasterMarkdownPreview($cachedMarkdown)),
            'download_url' => '/api/reports/ai_master_document.php?chart_id=' . $chartId . '&focus=' . urlencode($focus) . '&format=download',
            'cache' => [
                'hit' => true,
                'ttl_seconds' => $cacheTtl,
                'age_seconds' => (int)($cached['age_seconds'] ?? 0),
            ],
        ]);
        exit;
    }

    $sections = [];
    foreach (['natal', 'transit', 'synastry'] as $reportType) {
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
        $sections[$reportType] = buildMasterSectionMarkdown($interpretation, $reportType);
    }

    $markdown = buildMasterDocumentMarkdown((string)$chart->getName(), $sections);
    $archive = ReportArchive::save(
        (int) $currentUser->getId(),
        $chartId,
        'master',
        'ai_summary_bundle',
        'md',
        'text/markdown',
        $markdown
    );
    $filename = (string)($archive['file_name'] ?? ('ai_master_document_chart_' . $chartId . '.md'));
    $htmlPreview = renderMasterMarkdownPreview($markdown);
    $responsePayload = [
        'history_id' => $archive['id'] ?? null,
        'markdown' => $markdown,
        'filename' => $filename,
        'html_preview' => $htmlPreview,
    ];
    writeCachedMasterDoc($cacheDir, $cacheFile, $responsePayload);

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
        'report_type' => 'master',
        'history_id' => $archive['id'] ?? null,
        'markdown' => $markdown,
        'filename' => $filename,
        'html_preview' => $htmlPreview,
        'download_url' => '/api/reports/ai_master_document.php?chart_id=' . $chartId . '&focus=' . urlencode($focus) . '&format=download',
        'cache' => [
            'hit' => false,
            'ttl_seconds' => $cacheTtl,
            'age_seconds' => 0,
        ],
    ]);
} catch (Throwable $e) {
    $bufferedOutput = '';
    if (ob_get_length() > 0) {
        $bufferedOutput = trim((string) ob_get_contents());
        ob_clean();
    }

    Logger::error('AI master document generation failed', [
        'chart_id' => $chartId ?? null,
        'format' => $format ?? null,
        'error' => $e->getMessage(),
        'buffered_output' => $bufferedOutput !== '' ? mb_substr($bufferedOutput, 0, 1200) : null,
    ]);

    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate AI master document.']);
}
