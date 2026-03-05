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

function renderMarkdownPreviewHoroscope(string $markdown): string
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
 * @return array{age_seconds:int,payload:array<string,mixed>}|null
 */
function readCachedHoroscope(string $cacheFile, int $cacheTtl, bool $fresh): ?array
{
    if ($fresh || $cacheTtl <= 0 || !is_file($cacheFile)) {
        return null;
    }

    $age = time() - (int)@filemtime($cacheFile);
    if ($age < 0 || $age > $cacheTtl) {
        return null;
    }

    $decoded = json_decode((string)@file_get_contents($cacheFile), true);
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
function writeCachedHoroscope(string $cacheDir, string $cacheFile, array $payload): void
{
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
    $period = strtolower(trim((string)($input['period'] ?? $_GET['period'] ?? 'daily')));
    $forDate = trim((string)($input['for_date'] ?? $_GET['for_date'] ?? date('Y-m-d')));
    $focus = trim((string)($input['focus'] ?? $_GET['focus'] ?? ''));
    $format = strtolower(trim((string)($input['format'] ?? $_GET['format'] ?? 'json')));
    $fresh = filter_var($input['fresh'] ?? $_GET['fresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $areasInput = $input['areas'] ?? $_GET['areas'] ?? [];
    $areas = [];
    if (is_array($areasInput)) {
        $areas = $areasInput;
    } elseif (is_string($areasInput)) {
        $areas = preg_split('/\s*,\s*/', $areasInput) ?: [];
    }

    if (!in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid horoscope period.']);
        exit;
    }
    if ($chartId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid chart ID.']);
        exit;
    }
    if ($forDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $forDate)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD.']);
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
    $provider = trim((string)($input['provider'] ?? $_GET['provider'] ?? $masterAi['provider'] ?? ($_ENV['AI_PROVIDER'] ?? 'ollama')));
    $model = trim((string)($input['model'] ?? $_GET['model'] ?? $masterAi['model'] ?? 'default'));
    $cacheTtl = (int)($_ENV['AI_HOROSCOPE_CACHE_TTL'] ?? $_ENV['AI_SUMMARY_CACHE_TTL'] ?? $_ENV['AI_CACHE_TTL'] ?? 21600);
    if ($cacheTtl < 0) {
        $cacheTtl = 21600;
    }

    $areasNormalized = array_values(array_filter(array_map(
        static fn ($value): string => strtolower(trim((string)$value)),
        $areas
    ), static fn ($value): bool => $value !== ''));

    $cacheKeyPayload = [
        'v' => 1,
        'chart_id' => $chartId,
        'user_id' => (int)$currentUser->getId(),
        'chart_updated' => (string)($chart->getUpdatedAt() ?? ''),
        'period' => $period,
        'for_date' => $forDate,
        'areas' => $areasNormalized,
        'focus' => $focus,
        'provider' => strtolower($provider),
        'model' => $model,
        'master_ai_updated_at' => (string)($masterAi['updated_at'] ?? ''),
    ];

    $cacheKey = sha1(json_encode($cacheKeyPayload, JSON_UNESCAPED_SLASHES));
    $cacheDir = STORAGE_PATH . '/cache/ai_horoscope';
    $cacheFile = $cacheDir . '/' . $cacheKey . '.json';
    $cached = readCachedHoroscope($cacheFile, $cacheTtl, $fresh);

    if (is_array($cached)) {
        $cachedPayload = $cached['payload'];
        $cachedMarkdown = (string)($cachedPayload['markdown'] ?? '');
        $cachedFilename = (string)($cachedPayload['filename'] ?? ('ai_horoscope_' . $period . '_chart_' . $chartId . '.md'));

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
            'period' => $period,
            'for_date' => $forDate,
            'areas' => $areasNormalized,
            'history_id' => $cachedPayload['history_id'] ?? null,
            'markdown' => $cachedMarkdown,
            'filename' => $cachedFilename,
            'html_preview' => (string)($cachedPayload['html_preview'] ?? renderMarkdownPreviewHoroscope($cachedMarkdown)),
            'download_url' => '/api/reports/horoscope.php?chart_id=' . $chartId . '&period=' . urlencode($period) . '&for_date=' . urlencode($forDate) . '&areas=' . urlencode(implode(',', $areasNormalized)) . '&focus=' . urlencode($focus) . '&format=download',
            'cache' => [
                'hit' => true,
                'ttl_seconds' => $cacheTtl,
                'age_seconds' => (int)($cached['age_seconds'] ?? 0),
            ],
        ]);
        exit;
    }

    $ai = new AIInterpreter($chart, [
        'provider' => $provider,
        'model' => $model,
        'focus' => $focus,
    ]);
    $horoscope = $ai->generateHoroscope($period, $forDate, $areasNormalized, $focus);
    $markdown = trim((string)($horoscope['horoscope_markdown'] ?? ''));
    if ($markdown === '') {
        throw new RuntimeException('Failed to generate horoscope markdown.');
    }

    $archive = ReportArchive::save(
        (int)$currentUser->getId(),
        $chartId,
        'horoscope',
        'ai_horoscope_' . $period,
        'md',
        'text/markdown',
        $markdown
    );

    $filename = (string)($archive['file_name'] ?? ('ai_horoscope_' . $period . '_chart_' . $chartId . '.md'));
    $htmlPreview = renderMarkdownPreviewHoroscope($markdown);
    $responsePayload = [
        'history_id' => $archive['id'] ?? null,
        'markdown' => $markdown,
        'filename' => $filename,
        'html_preview' => $htmlPreview,
    ];
    writeCachedHoroscope($cacheDir, $cacheFile, $responsePayload);

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
        'period' => $period,
        'for_date' => $forDate,
        'areas' => $areasNormalized,
        'history_id' => $archive['id'] ?? null,
        'markdown' => $markdown,
        'filename' => $filename,
        'html_preview' => $htmlPreview,
        'download_url' => '/api/reports/horoscope.php?chart_id=' . $chartId . '&period=' . urlencode($period) . '&for_date=' . urlencode($forDate) . '&areas=' . urlencode(implode(',', $areasNormalized)) . '&focus=' . urlencode($focus) . '&format=download',
        'cache' => [
            'hit' => false,
            'ttl_seconds' => $cacheTtl,
            'age_seconds' => 0,
        ],
    ]);
} catch (Throwable $e) {
    $bufferedOutput = '';
    if (ob_get_length() > 0) {
        $bufferedOutput = trim((string)ob_get_contents());
        ob_clean();
    }

    Logger::error('AI horoscope generation failed', [
        'chart_id' => $chartId ?? null,
        'error' => $e->getMessage(),
        'buffered_output' => $bufferedOutput !== '' ? substr($bufferedOutput, 0, 500) : null,
    ]);

    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate AI horoscope.']);
} finally {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}
