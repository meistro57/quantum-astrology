<?php
# api/reports/generate.php
declare(strict_types=1);

require_once __DIR__ . '/../../classes/autoload.php';
require_once __DIR__ . '/../../config.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Charts\Transit;
use QuantumAstrology\Reports\ReportGenerator;
use QuantumAstrology\Reports\ReportArchive;
use QuantumAstrology\Core\Logger;
use QuantumAstrology\Support\ApiResponse;

// API responses must remain valid JSON; do not emit PHP warnings/notices to output.
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

if (ob_get_level() === 0) {
    ob_start();
}

header('Content-Type: application/json');

// Require authentication
Auth::requireLogin();

try {
    $raw = file_get_contents('php://input');
    $input = null;
    if ($raw !== false && trim($raw) !== '') {
        $input = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            ApiResponse::sendError('Invalid JSON request body.', 'INVALID_JSON', 400);
            return;
        }
    }
    if (!is_array($input)) {
        $input = $_POST ?: [];
    }

    $chartId = (int) ($input['chart_id'] ?? $_GET['chart_id'] ?? 0);
    $reportType = $input['report_type'] ?? $_GET['report_type'] ?? 'natal';
    $format = $input['format'] ?? $_GET['format'] ?? 'pdf'; // pdf or download

    $reportType = strtolower(trim((string) $reportType));
    if (!in_array($reportType, ['natal', 'transit'], true)) {
        ApiResponse::sendError('Report type not available yet.', 'INVALID_REPORT_TYPE', 400);
        return;
    }

    if ($chartId <= 0) {
        ApiResponse::sendError('Invalid chart ID', 'INVALID_CHART_ID', 400);
        return;
    }

    $chart = Chart::findById($chartId);
    if (!$chart) {
        ApiResponse::sendError('Chart not found.', 'CHART_NOT_FOUND', 404);
        return;
    }

    $currentUser = Auth::user();
    if (!$currentUser || (int)$chart->getUserId() !== (int)$currentUser->getId()) {
        ApiResponse::sendError('Access denied.', 'ACCESS_DENIED', 403);
        return;
    }

    $reportPrivacyOptions = [
        'show_birth_date' => $currentUser->shouldShowBirthDateInReports(),
        'show_birth_location' => $currentUser->shouldShowBirthLocationInReports(),
    ];

    // Initialize report generator
    $generator = new ReportGenerator($reportType);

    // Generate PDF content based on report type
    if ($reportType === 'transit') {
        $transitDate = null;
        $dateInput = $input['date'] ?? $_GET['date'] ?? null;
        if (is_string($dateInput) && trim($dateInput) !== '') {
            try {
                $transitDate = new \DateTime(trim($dateInput));
            } catch (Throwable $dateError) {
                ApiResponse::sendError('Invalid transit date format.', 'INVALID_TRANSIT_DATE', 400);
                return;
            }
        }

        $transitEngine = new Transit($chart);
        $transitData = $transitEngine->getCurrentTransits($transitDate);
        $pdfContent = $generator->generateTransitReport($chartId, $transitData, $reportPrivacyOptions);
    } else {
        $pdfContent = $generator->generateNatalReport($chartId, $reportPrivacyOptions);
    }
    $archive = ReportArchive::save(
        (int) $currentUser->getId(),
        $chartId,
        $reportType,
        'pdf',
        'pdf',
        'application/pdf',
        $pdfContent
    );

    if ($format === 'download') {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        // Download the PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="chart_' . $chartId . '_' . $reportType . '_report.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }

    // Return base64-encoded PDF for preview
    if (ob_get_length() > 0) {
        ob_clean();
    }
    $responseData = [
        'chart_id' => $chartId,
        'report_type' => $reportType,
        'history_id' => $archive['id'] ?? null,
        'pdf_base64' => base64_encode($pdfContent),
        'download_url' => "/api/reports/generate.php?chart_id={$chartId}&report_type={$reportType}&format=download",
    ];
    ApiResponse::sendSuccess(
        $responseData,
        [],
        200
    );

} catch (Throwable $e) {
    $bufferedOutput = '';
    if (ob_get_length() > 0) {
        $bufferedOutput = trim((string) ob_get_contents());
        ob_clean();
    }

    Logger::error('Report generation failed', [
        'chart_id' => $chartId ?? null,
        'report_type' => $reportType ?? null,
        'format' => $format ?? null,
        'error' => $e->getMessage(),
        'buffered_output' => $bufferedOutput !== '' ? mb_substr($bufferedOutput, 0, 1200) : null,
    ]);

    ApiResponse::sendError('Failed to generate report.', 'REPORT_GENERATION_FAILED', 500);
}
