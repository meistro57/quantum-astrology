<?php
# classes/Reports/ReportGenerator.php
declare(strict_types=1);

namespace QuantumAstrology\Reports;

use Mpdf\Mpdf;
use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Interpretations\ChartInterpretation;
use QuantumAstrology\Core\Logger;
use RuntimeException;

class ReportGenerator
{
    private Mpdf $mpdf;
    private string $reportType;

    public function __construct(string $reportType = 'natal')
    {
        $this->reportType = $reportType;

        // Initialize mPDF with professional settings
        $this->mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 25,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10,
        ]);

        // Set document properties
        $this->mpdf->SetAuthor('Quantum Minds United');
        $this->mpdf->SetCreator('Quantum Astrology v1.0');
    }

    /**
     * Generate a comprehensive PDF report for a natal chart
     */
    public function generateNatalReport(int $chartId, array $options = []): string
    {
        try {
            $chart = Chart::find($chartId);
            if (!$chart) {
                throw new RuntimeException("Chart #$chartId not found");
            }

            // Get chart data and interpretation
            $chartData = $chart->toArray();
            $interpretation = (new ChartInterpretation($chart))->generateFullInterpretation();

            // Build HTML content
            $html = $this->buildReportHTML($chart, $chartData, $interpretation, $options);

            // Set document metadata
            $this->mpdf->SetTitle('Natal Chart Report - ' . $chart->getName());
            $this->mpdf->SetSubject('Professional Astrological Analysis');
            $this->mpdf->SetKeywords('astrology, natal chart, horoscope, birth chart');

            // Write HTML to PDF
            $this->mpdf->WriteHTML($this->getStylesheet());
            $this->mpdf->WriteHTML($html);

            // Return as string (or use 'D' for download, 'F' for file)
            return $this->mpdf->Output('', 'S');
        } catch (\Throwable $e) {
            Logger::error('Natal report generation failed', [
                'chart_id' => $chartId,
                'report_type' => $this->reportType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate PDF for transit report
     */
    public function generateTransitReport(int $chartId, array $transitData, array $options = []): string
    {
        $chart = Chart::find($chartId);
        if (!$chart) {
            throw new RuntimeException("Chart #$chartId not found");
        }

        $html = $this->buildTransitReportHTML($chart, $transitData, $options);

        $this->mpdf->SetTitle('Transit Report - ' . $chart->getName());
        $this->mpdf->WriteHTML($this->getStylesheet());
        $this->mpdf->WriteHTML($html);

        return $this->mpdf->Output('', 'S');
    }

    /**
     * Generate PDF for synastry/compatibility report
     */
    public function generateSynastryReport(int $chart1Id, int $chart2Id, array $synastryData, array $options = []): string
    {
        $chart1 = Chart::find($chart1Id);
        $chart2 = Chart::find($chart2Id);

        if (!$chart1 || !$chart2) {
            throw new RuntimeException("One or both charts not found");
        }

        $html = $this->buildSynastryReportHTML($chart1, $chart2, $synastryData, $options);

        $this->mpdf->SetTitle('Synastry Report - ' . $chart1->getName() . ' & ' . $chart2->getName());
        $this->mpdf->WriteHTML($this->getStylesheet());
        $this->mpdf->WriteHTML($html);

        return $this->mpdf->Output('', 'S');
    }

    /**
     * Get CSS stylesheet for PDF
     */
    private function getStylesheet(): string
    {
        return '
        <style>
            @page {
                margin-header: 10mm;
                margin-footer: 10mm;
            }

            body {
                font-family: "DejaVu Sans", Arial, sans-serif;
                font-size: 10pt;
                color: #333;
                line-height: 1.6;
            }

            h1 {
                color: #4A90E2;
                font-size: 24pt;
                margin-top: 0;
                margin-bottom: 10pt;
                border-bottom: 3px solid #FFD700;
                padding-bottom: 10pt;
            }

            h2 {
                color: #4A90E2;
                font-size: 16pt;
                margin-top: 20pt;
                margin-bottom: 10pt;
                border-left: 4px solid #FFD700;
                padding-left: 10pt;
            }

            h3 {
                color: #2C5AA0;
                font-size: 13pt;
                margin-top: 15pt;
                margin-bottom: 8pt;
            }

            .cover-page {
                text-align: center;
                padding: 50pt 0;
            }

            .cover-title {
                font-size: 32pt;
                color: #4A90E2;
                font-weight: bold;
                margin-bottom: 5pt;
            }

            .cover-subtitle {
                font-size: 18pt;
                color: #FFD700;
                margin-bottom: 30pt;
            }

            .cover-name {
                font-size: 22pt;
                color: #333;
                margin: 20pt 0;
            }

            .cover-footer {
                margin-top: 80pt;
                font-size: 11pt;
                color: #666;
            }

            .info-box {
                background: #F8F9FA;
                border: 1px solid #DEE2E6;
                border-radius: 5px;
                padding: 10pt;
                margin: 10pt 0;
            }

            .info-row {
                margin: 5pt 0;
            }

            .info-label {
                font-weight: bold;
                color: #4A90E2;
                display: inline-block;
                width: 120pt;
            }

            .planet-table {
                width: 100%;
                border-collapse: collapse;
                margin: 10pt 0;
            }

            .planet-table th {
                background: #4A90E2;
                color: white;
                padding: 8pt;
                text-align: left;
                font-weight: bold;
            }

            .planet-table td {
                padding: 6pt 8pt;
                border-bottom: 1px solid #E0E0E0;
            }

            .planet-table tr:nth-child(even) {
                background: #F8F9FA;
            }

            .aspect-table {
                width: 100%;
                border-collapse: collapse;
                margin: 10pt 0;
                font-size: 9pt;
            }

            .aspect-table th {
                background: #FFD700;
                color: #333;
                padding: 6pt;
                text-align: left;
                font-weight: bold;
            }

            .aspect-table td {
                padding: 5pt 6pt;
                border-bottom: 1px solid #E0E0E0;
            }

            .aspect-conjunction { color: #FFD700; font-weight: bold; }
            .aspect-trine { color: #28a745; font-weight: bold; }
            .aspect-sextile { color: #17a2b8; font-weight: bold; }
            .aspect-square { color: #dc3545; font-weight: bold; }
            .aspect-opposition { color: #d9534f; font-weight: bold; }

            .interpretation-box {
                background: #FFF9E6;
                border-left: 4px solid #FFD700;
                padding: 12pt;
                margin: 15pt 0;
                border-radius: 3px;
            }

            .highlight {
                background: #FFF9E6;
                padding: 2pt 5pt;
                border-radius: 2px;
            }

            .footer-text {
                text-align: center;
                font-size: 9pt;
                color: #666;
                margin-top: 30pt;
                padding-top: 10pt;
                border-top: 1px solid #CCC;
            }

            .page-break {
                page-break-before: always;
            }
        </style>
        ';
    }

    /**
     * Build complete natal report HTML
     */
    private function buildReportHTML(Chart $chart, array $chartData, array $interpretation, array $options): string
    {
        $html = '';

        // Cover page
        $html .= $this->buildCoverPage($chart);

        // Chart information
        $html .= '<div class="page-break"></div>';
        $html .= $this->buildChartInfo($chart, $chartData);

        // Planetary positions
        $html .= '<div class="page-break"></div>';
        $html .= $this->buildPlanetaryPositions($chartData);

        // House cusps
        $html .= $this->buildHouseCusps($chartData);

        // Aspects
        $html .= '<div class="page-break"></div>';
        $html .= $this->buildAspects($chartData);

        // Interpretation
        $html .= '<div class="page-break"></div>';
        $html .= $this->buildInterpretation($interpretation);

        // Footer
        $html .= $this->buildFooter();

        return $html;
    }

    private function buildCoverPage(Chart $chart): string
    {
        $name = htmlspecialchars($chart->getName());
        $date = $chart->getBirthDatetime() ? $chart->getBirthDatetime()->format('F j, Y - g:i A') : 'Unknown';
        $location = $chart->getBirthLocation() ? htmlspecialchars($chart->getBirthLocation()) : 'Unknown';

        return '
        <div class="cover-page">
            <div class="cover-title">QUANTUM ASTROLOGY</div>
            <div class="cover-subtitle">Professional Natal Chart Analysis</div>

            <div style="margin: 50pt 0;">
                <div style="width: 200pt; height: 200pt; margin: 0 auto; border: 3px solid #4A90E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #F8F9FA 0%, #E9ECEF 100%);">
                    <div style="font-size: 48pt; color: #FFD700;">☉</div>
                </div>
            </div>

            <div class="cover-name">' . $name . '</div>
            <div style="font-size: 13pt; color: #666; margin: 10pt 0;">
                Born: ' . $date . '<br>
                Location: ' . $location . '
            </div>

            <div class="cover-footer">
                <strong>Quantum Minds United</strong><br>
                Professional Astrological Services<br>
                Report Generated: ' . date('F j, Y') . '
            </div>
        </div>
        ';
    }

    private function buildChartInfo(Chart $chart, array $chartData): string
    {
        $name = htmlspecialchars($chart->getName());
        $datetime = $chart->getBirthDatetime() ? $chart->getBirthDatetime()->format('F j, Y - g:i A T') : 'Unknown';
        $location = $chart->getBirthLocation() ? htmlspecialchars($chart->getBirthLocation()) : 'Unknown';
        $lat = $chart->getBirthLatitude() ?? 0;
        $lon = $chart->getBirthLongitude() ?? 0;
        $houseSystem = ucfirst($chartData['house_system'] ?? 'placidus');

        return '
        <h1>Chart Information</h1>
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Name:</span> ' . $name . '
            </div>
            <div class="info-row">
                <span class="info-label">Birth Date/Time:</span> ' . $datetime . '
            </div>
            <div class="info-row">
                <span class="info-label">Birth Location:</span> ' . $location . '
            </div>
            <div class="info-row">
                <span class="info-label">Coordinates:</span> ' . number_format($lat, 4) . '° ' . ($lat >= 0 ? 'N' : 'S') . ', ' . number_format($lon, 4) . '° ' . ($lon >= 0 ? 'E' : 'W') . '
            </div>
            <div class="info-row">
                <span class="info-label">House System:</span> ' . $houseSystem . '
            </div>
        </div>
        ';
    }

    private function buildPlanetaryPositions(array $chartData): string
    {
        $html = '<h1>Planetary Positions</h1>';

        $planets = json_decode($chartData['planetary_positions'] ?? '{}', true);

        if (empty($planets)) {
            return $html . '<p>No planetary position data available.</p>';
        }

        $html .= '<table class="planet-table">';
        $html .= '<tr><th>Planet</th><th>Sign</th><th>Degree</th><th>House</th><th>Retrograde</th></tr>';

        foreach ($planets as $planetName => $data) {
            $sign = $data['sign'] ?? '--';
            $degree = isset($data['degree']) ? number_format($data['degree'], 2) . '°' : '--';
            $house = $data['house'] ?? '--';
            $retrograde = ($data['retrograde'] ?? false) ? 'Yes ℞' : 'No';

            $html .= '<tr>';
            $html .= '<td><strong>' . ucfirst(str_replace('_', ' ', $planetName)) . '</strong></td>';
            $html .= '<td>' . $sign . '</td>';
            $html .= '<td>' . $degree . '</td>';
            $html .= '<td>' . $house . '</td>';
            $html .= '<td>' . $retrograde . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    private function buildHouseCusps(array $chartData): string
    {
        $html = '<h2>House Cusps</h2>';

        $houses = json_decode($chartData['house_cusps'] ?? '[]', true);

        if (empty($houses)) {
            return $html . '<p>No house cusp data available.</p>';
        }

        $html .= '<table class="planet-table">';
        $html .= '<tr><th>House</th><th>Sign</th><th>Degree</th></tr>';

        foreach ($houses as $index => $cusp) {
            $houseNum = $index + 1;
            $sign = $cusp['sign'] ?? '--';
            $degree = isset($cusp['degree']) ? number_format($cusp['degree'], 2) . '°' : '--';

            $html .= '<tr>';
            $html .= '<td><strong>' . $houseNum . '</strong></td>';
            $html .= '<td>' . $sign . '</td>';
            $html .= '<td>' . $degree . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    private function buildAspects(array $chartData): string
    {
        $html = '<h1>Major Aspects</h1>';

        $aspects = json_decode($chartData['aspects'] ?? '[]', true);

        if (empty($aspects)) {
            return $html . '<p>No aspect data available.</p>';
        }

        $html .= '<table class="aspect-table">';
        $html .= '<tr><th>Planet 1</th><th>Aspect</th><th>Planet 2</th><th>Orb</th><th>Applying/Separating</th></tr>';

        foreach ($aspects as $aspect) {
            $p1 = ucfirst(str_replace('_', ' ', $aspect['planet1'] ?? ''));
            $p2 = ucfirst(str_replace('_', ' ', $aspect['planet2'] ?? ''));
            $type = $aspect['aspect'] ?? $aspect['type'] ?? '--';
            $orb = isset($aspect['orb']) ? number_format($aspect['orb'], 2) . '°' : '--';
            $applying = ($aspect['applying'] ?? null) === true ? 'Applying' : (($aspect['applying'] ?? null) === false ? 'Separating' : '--');

            $aspectClass = 'aspect-' . strtolower($type);

            $html .= '<tr>';
            $html .= '<td>' . $p1 . '</td>';
            $html .= '<td class="' . $aspectClass . '">' . ucfirst($type) . '</td>';
            $html .= '<td>' . $p2 . '</td>';
            $html .= '<td>' . $orb . '</td>';
            $html .= '<td>' . $applying . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    private function buildInterpretation(array $interpretation): string
    {
        $html = '<h1>Chart Interpretation</h1>';

        // Sun, Moon, Rising synthesis
        if (!empty($interpretation['synthesis'])) {
            $html .= '<h2>Core Identity</h2>';
            $html .= '<div class="interpretation-box">';
            $html .= '<p>' . htmlspecialchars($interpretation['synthesis']) . '</p>';
            $html .= '</div>';
        }

        // Dominant elements
        if (!empty($interpretation['elemental_balance'])) {
            $html .= '<h2>Elemental Balance</h2>';
            $html .= '<p>';
            foreach ($interpretation['elemental_balance'] as $element => $count) {
                $html .= '<span class="highlight">' . ucfirst($element) . ': ' . $count . '</span> ';
            }
            $html .= '</p>';
        }

        // Planetary interpretations
        if (!empty($interpretation['planetary_placements'])) {
            $html .= '<h2>Planetary Placements</h2>';
            foreach ($interpretation['planetary_placements'] as $planet => $text) {
                $html .= '<h3>' . ucfirst(str_replace('_', ' ', $planet)) . '</h3>';
                $html .= '<p>' . htmlspecialchars($text) . '</p>';
            }
        }

        return $html;
    }

    private function buildTransitReportHTML(Chart $chart, array $transitData, array $options): string
    {
        // Similar structure for transit reports
        return '<h1>Transit Report</h1><p>Transit report content goes here...</p>';
    }

    /**
     * Generate PDF for synastry/compatibility report
     */
    private function buildSynastryReportHTML(Chart $chart1, Chart $chart2, array $synastryData, array $options): string
    {
        $html = $this->buildCoverPage($chart1); // You might want a custom cover for couples

        $html .= '<div class="page-break"></div>';
        $html .= '<h1>Synastry Analysis</h1>';
        $html .= '<h3>' . $chart1->getName() . ' & ' . $chart2->getName() . '</h3>';

        // Compatibility Score (if available in your logic)
        $score = $synastryData['compatibility_scores']['overall'] ?? 0;
        $html .= '<div class="info-box">';
        $html .= '<h2>Compatibility Score: ' . number_format($score, 1) . '%</h2>';
        $html .= '</div>';

        // Aspects Table
        $html .= '<h2>Inter-Chart Aspects</h2>';
        $html .= '<table class="aspect-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . $chart1->getName() . ' (Planet)</th>';
        $html .= '<th>Aspect</th>';
        $html .= '<th>' . $chart2->getName() . ' (Planet)</th>';
        $html .= '<th>Orb</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($synastryData['synastry_aspects'] ?? [] as $aspect) {
            $p1 = ucfirst($aspect['person1_planet']);
            $p2 = ucfirst($aspect['person2_planet']);
            $type = ucfirst($aspect['aspect']);
            $orb = number_format($aspect['orb'], 2) . '°';

            // Color code harsh vs soft aspects
            $class = in_array($type, ['Square', 'Opposition']) ? 'aspect-square' : 'aspect-trine';

            $html .= '<tr>';
            $html .= "<td>$p1</td>";
            $html .= "<td class='$class'>$type</td>";
            $html .= "<td>$p2</td>";
            $html .= "<td>$orb</td>";
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // AI/Manual Interpretations
        if (isset($synastryData['relationship_dynamics'])) {
            $html .= '<div class="page-break"></div>';
            $html .= '<h2>Relationship Dynamics</h2>';

            $dynamics = $synastryData['relationship_dynamics'];

            $html .= '<div class="interpretation-box">';
            $html .= '<h3>Key Strengths</h3><ul>';
            foreach ($dynamics['strength_areas'] ?? [] as $strength) {
                $html .= "<li>$strength</li>";
            }
            $html .= '</ul></div>';

            $html .= '<div class="interpretation-box" style="border-left-color: #e53e3e;">';
            $html .= '<h3>Challenges</h3><ul>';
            foreach ($dynamics['conflict_areas'] ?? [] as $conflict) {
                $html .= "<li>$conflict</li>";
            }
            $html .= '</ul></div>';
        }

        $html .= $this->buildFooter();
        return $html;
    }

    private function buildFooter(): string
    {
        return '
        <div class="footer-text">
            <p>
                <strong>Quantum Minds United</strong><br>
                Professional Astrological Software Suite<br>
                This report is generated using Swiss Ephemeris calculations with professional-grade precision.<br>
                © ' . date('Y') . ' Quantum Minds United. All rights reserved.
            </p>
        </div>
        ';
    }
}
