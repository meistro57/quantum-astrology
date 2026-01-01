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
    private array $activeTransits = ['mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto'];
    
    private array $aspects = [
        'Conjunction' => ['angle' => 0, 'orb' => 2.5],
        'Square'      => ['angle' => 90, 'orb' => 2.0],
        'Trine'       => ['angle' => 120, 'orb' => 2.0],
        'Opposition'  => ['angle' => 180, 'orb' => 2.5],
    ];

    public function __construct(Chart $chart)
    {
        $this->chart = $chart;
        $this->swissEph = new SwissEphemeris();
    }

    public function calculateSeries(DateTime $start, int $days): array
    {
        $natalPositions = $this->chart->getPlanetaryPositions();
        // Fallback for different data structures
        if (isset($natalPositions['sun'])) { 
            // Normalize keyed array
        } else {
            // Re-key numeric array if necessary
            $temp = [];
            foreach($natalPositions as $p) $temp[strtolower($p['planet']??$p['name']??'')] = $p;
            $natalPositions = $temp;
        }

        $seriesData = [];
        $dates = [];
        $current = clone $start;
        $interval = new DateInterval('P1D');

        for ($i = 0; $i <= $days; $i++) {
            $dates[] = $current->format('M j');
            // Noon calculations
            $transits = $this->swissEph->calculatePlanetaryPositions(
                $current->setTime(12, 0),
                $this->chart->getBirthLatitude(),
                $this->chart->getBirthLongitude()
            );

            foreach ($this->activeTransits as $tPlanet) {
                if (!isset($transits[$tPlanet])) continue;
                
                foreach ($natalPositions as $nPlanet => $nPos) {
                    if (in_array($nPlanet, ['mean_node', 'true_node', 'lilith'])) continue;

                    $tLon = $transits[$tPlanet]['longitude'];
                    $nLon = $nPos['longitude'] ?? $nPos['lon'] ?? 0;

                    foreach ($this->aspects as $aspName => $asp) {
                        $angle = $this->shortestAngle($tLon, $nLon);
                        $deviation = abs($angle - $asp['angle']);
                        
                        if ($deviation <= ($asp['orb'] * 1.5)) {
                            $key = "t.{$tPlanet}-{$aspName}-n.{$nPlanet}";
                            if (!isset($seriesData[$key])) {
                                $seriesData[$key] = [
                                    'label' => ucfirst($tPlanet) . " " . $this->getSymbol($aspName) . " " . ucfirst($nPlanet),
                                    'type' => $aspName,
                                    'points' => array_fill(0, $days + 1, null)
                                ];
                            }
                            // Store deviation: 0 is exact
                            $seriesData[$key]['points'][$i] = $deviation;
                        }
                    }
                }
            }
            $current->add($interval);
        }

        // Filter out very short blips
        $seriesData = array_filter($seriesData, function($s) {
            return count(array_filter($s['points'], fn($p) => $p !== null)) > 2;
        });

        return [
            'dates' => $dates,
            'orb_max' => 3.0,
            'series' => array_values($seriesData)
        ];
    }

    private function getSymbol(string $aspect): string {
        return match($aspect) {
            'Conjunction' => '☌', 'Opposition' => '☍',
            'Square' => '□', 'Trine' => '△', default => '*'
        };
    }

    private function shortestAngle(float $a, float $b): float {
        $diff = abs($a - $b);
        return $diff > 180 ? 360 - $diff : $diff;
    }
}
