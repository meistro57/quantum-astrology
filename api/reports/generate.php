<?php
# api/reports/generate.php
declare(strict_types=1);

require_once __DIR__ . '/../../classes/autoload.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Reports\ReportGenerator;
use QuantumAstrology\Core\Logger;

header('Content-Type: application/json');

// Require authentication
Auth::requireLogin();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $chartId = (int) ($input['chart_id'] ?? $_GET['chart_id'] ?? 0);
    $reportType = $input['report_type'] ?? $_GET['report_type'] ?? 'natal';
    $format = $input['format'] ?? $_GET['format'] ?? 'pdf'; // pdf or download

    if (!in_array($reportType, ['natal'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Report type not available yet.']);
        exit;
    }

    if ($chartId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid chart ID']);
        exit;
    }

    // Initialize report generator
    $generator = new ReportGenerator($reportType);

    // Generate PDF content
    $pdfContent = $generator->generateNatalReport($chartId);

    if ($format === 'download') {
        // Download the PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="chart_' . $chartId . '_report.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }

    // Return base64-encoded PDF for preview
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'chart_id' => $chartId,
        'report_type' => $reportType,
        'pdf_base64' => base64_encode($pdfContent),
        'download_url' => "/api/reports/generate.php?chart_id={$chartId}&report_type={$reportType}&format=download"
    ]);

} catch (Throwable $e) {
    Logger::error('Report generation failed', [
        'chart_id' => $chartId ?? null,
        'report_type' => $reportType ?? null,
        'format' => $format ?? null,
        'error' => $e->getMessage(),
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate report: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
