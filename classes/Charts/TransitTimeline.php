<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use DateTime;
use DateInterval;
use QuantumAstrology\Core\SwissEphemeris;

class TransitTimeline
{
    private Chart $chart;
    private SwissEphemeris $swissEph;

    // Transiting planets to track (Mars outwards are most significant for timelines)
    private array $activeTransits = ['mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto'];
    
    // Aspects to track
    private array $aspects = [
        'Conjunction' => ['angle' => 0, 'orb' => 2.5], // Tight orb for graph clarity
        'Square'      => ['angle' => 90, 'orb' => 2.0],
        'Trine'       => ['angle' => 120, 'orb' => 2.0],
        'Opposition'  => ['angle' => 180, 'orb' => 2.5],
    ];

    public function __construct(Chart $chart)
    {
        $this->chart = $chart;
        $this->swissEph = new SwissEphemeris();
    }

    /**
     * Generate data series for the timeline graph
     * Returns: [ 'meta' => [dates], 'series' => [ {label, points: [y, y, ...]} ] ]
     */
    public function calculateSeries(DateTime $start, int $days): array
    {
        $natalPositions = $this->chart->getPlanetaryPositions();
        $seriesData = [];
        $dates = [];
        
        $current = clone $start;
        // Buffer range: Calculate extra days to ensure smooth lines at edges
        $interval = new DateInterval('P1D');

        for ($i = 0; $i <= $days; $i++) {
            $dates[] = $current->format('M j');
            
            // Calc daily positions
            // Note: Using noon (12:00) for daily average
            $transits = $this->swissEph->calculatePlanetaryPositions(
                $current->setTime(12, 0),
                $this->chart->getBirthLatitude(),
                $this->chart->getBirthLongitude()
            );

            // Check every Transiting Planet -> Natal Planet combo
            foreach ($this->activeTransits as $tPlanet) {
                if (!isset($transits[$tPlanet])) continue;
                
                foreach ($natalPositions as $nPlanet => $nPos) {
                    if (in_array($nPlanet, ['mean_node', 'true_node', 'lilith'])) continue; // Skip minor points to reduce noise

                    $tLon = $transits[$tPlanet]['longitude'];
                    $nLon = $nPos['longitude'];

                    foreach ($this->aspects as $aspName => $asp) {
                        // Calculate deviation from exact aspect
                        // e.g., Trine (120). T=121, N=0. Diff=121. Deviation = +1.
                        $angle = $this->shortestAngle($tLon, $nLon);
                        $deviation = abs($angle - $asp['angle']);
                        
                        // Check if within display orb (wider than exact orb to show approach)
                        $displayOrb = $asp['orb'] * 1.5; 

                        if ($deviation <= $displayOrb) {
                            $key = "t.{$tPlanet}-{$aspName}-n.{$nPlanet}";
                            
                            // Initialize series if new
                            if (!isset($seriesData[$key])) {
                                $seriesData[$key] = [
                                    'label' => ucfirst($tPlanet) . " " . $this->getSymbol($aspName) . " " . ucfirst($nPlanet),
                                    'type' => $aspName,
                                    'points' => array_fill(0, $days + 1, null) // Pre-fill with nulls
                                ];
                            }

                            // Store signed deviation for "Approaching/Separating" visual
                            // We need to know if it's +1 deg or -1 deg relative to exact
                            // Simplified: Just store absolute "closeness" where 0 is bottom (exact)
                            // For a timeline, Y axis usually represents Orb. 0 = Center line.
                            
                            // Let's refine the deviation sign for line slope
                            $rawDiff = $this->normalize($tLon - $nLon);
                            // Determine "side" of the aspect
                            $signedDev = $this->getSignedDeviation($rawDiff, $asp['angle']);
                            
                            $seriesData[$key]['points'][$i] = $signedDev;
                        }
                    }
                }
            }
            $current->add($interval);
        }

        // Cleanup: Remove fragmented short lines (noise)
        $seriesData = array_filter($seriesData, function($s) {
            return count(array_filter($s['points'], fn($p) => $p !== null)) > 1;
        });

        return [
            'dates' => $dates,
            'orb_max' => 3.0, // Y-axis limit
            'series' => array_values($seriesData)
        ];
    }

    private function getSymbol(string $aspect): string {
        return match($aspect) {
            'Conjunction' => '☌', 'Opposition' => '☍',
            'Square' => '□', 'Trine' => '△', default => '*'
        };
    }

    private function normalize(float $deg): float {
        $deg = fmod($deg, 360);
        if ($deg < 0) $deg += 360;
        return $deg;
    }

    private function shortestAngle(float $a, float $b): float {
        $diff = abs($a - $b);
        return $diff > 180 ? 360 - $diff : $diff;
    }

    private function getSignedDeviation(float $diff, float $target): float {
        // Logic to determine if we are "before" or "after" the aspect for plotting
        // This allows the line to cross 0
        // Complex logic simplified: assume forward motion for outer planets
        // If diff is 89 (Square 90), result -1. If 91, result +1.
        
        $delta = $diff - $target;
        if ($delta < -180) $delta += 360;
        if ($delta > 180) $delta -= 360;
        
        return $delta;
    }
}
