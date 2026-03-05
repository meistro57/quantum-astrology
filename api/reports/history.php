<?php
declare(strict_types=1);

require_once __DIR__ . '/../../classes/autoload.php';
require_once __DIR__ . '/../../config.php';

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\User;
use QuantumAstrology\Reports\ReportArchive;
use QuantumAstrology\Support\ApiResponse;

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

if (ob_get_level() === 0) {
    ob_start();
}

Auth::requireLogin();

try {
    $user = Auth::user();
    if (!$user) {
        ApiResponse::sendError('Authentication required.', 'AUTH_REQUIRED', 401);
        return;
    }

    $historyId = (int)($_GET['id'] ?? 0);
    if ($historyId > 0) {
        $row = ReportArchive::findByIdForUser($historyId, (int) $user->getId());
        if (!$row) {
            ApiResponse::sendError('Report history item not found.', 'REPORT_NOT_FOUND', 404);
            return;
        }

        $relPath = (string)($row['file_path'] ?? '');
        $absPath = ROOT_PATH . '/' . ltrim($relPath, '/');
        if (!is_file($absPath)) {
            ApiResponse::sendError('Report file is missing from storage.', 'REPORT_FILE_MISSING', 404);
            return;
        }

        $mode = strtolower(trim((string)($_GET['mode'] ?? 'download')));
        $contentDisposition = $mode === 'view' ? 'inline' : 'attachment';
        $fileName = (string)($row['file_name'] ?? basename($absPath));
        $mime = (string)($row['mime_type'] ?? 'application/octet-stream');

        if (ob_get_length() > 0) {
            ob_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $contentDisposition . '; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($absPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($absPath);
        exit;
    }

    $limit = (int)($_GET['limit'] ?? 30);
    $rows = ReportArchive::listByUser((int) $user->getId(), $limit);
    $chartNameCache = [];
    $userNameCache = [];
    $items = [];

    foreach ($rows as $row) {
        $chartId = (int)($row['chart_id'] ?? 0);
        if (!array_key_exists($chartId, $chartNameCache)) {
            $chart = $chartId > 0 ? Chart::findById($chartId) : null;
            $chartNameCache[$chartId] = $chart ? (string)$chart->getName() : null;
        }
        $creatorId = (int)($row['user_id'] ?? 0);
        if (!array_key_exists($creatorId, $userNameCache)) {
            $creator = $creatorId > 0 ? User::findById($creatorId) : null;
            $userNameCache[$creatorId] = $creator?->getUsername() ?: ('User #' . $creatorId);
        }

        $items[] = [
            'id' => (int)($row['id'] ?? 0),
            'chart_id' => $chartId,
            'chart_name' => $chartNameCache[$chartId] ?? ('Chart #' . $chartId),
            'created_by' => (string)($userNameCache[$creatorId] ?? 'Unknown'),
            'report_type' => (string)($row['report_type'] ?? 'natal'),
            'kind' => (string)($row['kind'] ?? ''),
            'mime_type' => (string)($row['mime_type'] ?? ''),
            'file_name' => (string)($row['file_name'] ?? ''),
            'file_size' => (int)($row['file_size'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
            'view_url' => '/api/reports/history.php?id=' . (int)($row['id'] ?? 0) . '&mode=view',
            'download_url' => '/api/reports/history.php?id=' . (int)($row['id'] ?? 0) . '&mode=download',
        ];
    }

    if (ob_get_length() > 0) {
        ob_clean();
    }
    ApiResponse::sendSuccess(
        ['items' => $items],
        ['count' => count($items)],
        200,
        // Keep existing top-level shape for current frontend usage.
        ['items' => $items]
    );
} catch (Throwable $e) {
    $bufferedOutput = '';
    if (ob_get_length() > 0) {
        $bufferedOutput = trim((string)ob_get_contents());
        ob_clean();
    }
    Logger::error('Report history API failed', [
        'error' => $e->getMessage(),
        'buffered_output' => $bufferedOutput !== '' ? mb_substr($bufferedOutput, 0, 1200) : null,
    ]);
    ApiResponse::sendError('Failed to load report history.', 'REPORT_HISTORY_FAILED', 500);
}
