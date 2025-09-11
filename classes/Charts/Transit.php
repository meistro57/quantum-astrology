<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\SwissEphemeris;
use QuantumAstrology\Core\Logger;
use DateTime;
use DateTimeZone;
use DateInterval;

class Transit
{
    private SwissEphemeris $swissEph;
    private Chart $natalChart;
    
    public function __construct(Chart $natalChart)
    {
        $this->natalChart = $natalChart;
        $this->swissEph = new SwissEphemeris();
    }
    
    /**
     * Calculate current transits to natal chart positions
     */
    public function getCurrentTransits(?DateTime $transitDate = null): array
    {
        $transitDate = $transitDate ?? new DateTime();
        
        try {
            // Get current planetary positions
            $currentPositions = $this->swissEph->calculatePlanetaryPositions(
                $transitDate,
                $this->natalChart->getBirthLatitude(),
                $this->natalChart->getBirthLongitude()
            );
            
            $natalPositions = $this->natalChart->getPlanetaryPositions();
            
            if (!$natalPositions) {
                throw new \Exception('Natal chart positions not available');
            }
            
            // Calculate transits (current planets aspecting natal planets)
            $transits = $this->calculateTransitAspects($currentPositions, $natalPositions);
            
            return [
                'transit_date' => $transitDate->format('c'),
                'current_positions' => $currentPositions,
                'natal_positions' => $natalPositions,
                'transits' => $transits,
                'calculation_metadata' => [
                    'calculated_at' => date('c'),
                    'chart_id' => $this->natalChart->getId(),
                    'calculation_method' => 'current_transits'
                ]
            ];
            
        } catch (\Exception $e) {
            Logger::error("Transit calculation failed", [
                'error' => $e->getMessage(),
                'chart_id' => $this->natalChart->getId(),
                'transit_date' => $transitDate->format('c')
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate transits for a date range
     */
    public function getTransitRange(DateTime $startDate, DateTime $endDate, string $interval = 'P1D'): array
    {
        $transits = [];
        $current = clone $startDate;
        $intervalObj = new DateInterval($interval);
        
        while ($current <= $endDate) {
            try {
                $dayTransits = $this->getCurrentTransits($current);
                $transits[$current->format('Y-m-d')] = $dayTransits;
            } catch (\Exception $e) {
                Logger::error("Transit range calculation failed for date", [
                    'date' => $current->format('c'),
                    'error' => $e->getMessage()
                ]);
            }
            
            $current->add($intervalObj);
        }
        
        return $transits;
    }
    
    /**
     * Find exact transit dates when aspects are perfected
     */
    public function findExactTransitDates(DateTime $startDate, DateTime $endDate, array $aspectTypes = ['conjunction', 'square', 'trine', 'opposition']): array
    {
        $exactTransits = [];
        $natalPositions = $this->natalChart->getPlanetaryPositions();
        
        if (!$natalPositions) {
            return [];
        }
        
        // Sample daily positions to find when aspects cross exact
        $current = clone $startDate;
        $previousPositions = null;
        
        while ($current <= $endDate) {
            try {
                $currentPositions = $this->swissEph->calculatePlanetaryPositions(
                    $current,
                    $this->natalChart->getBirthLatitude(),
                    $this->natalChart->getBirthLongitude()
                );
                
                if ($previousPositions) {
                    $exactDates = $this->findExactAspectCrossings(
                        $previousPositions,
                        $currentPositions,
                        $natalPositions,
                        $aspectTypes,
                        clone $current->sub(new DateInterval('P1D')),
                        $current
                    );
                    
                    $exactTransits = array_merge($exactTransits, $exactDates);
                }
                
                $previousPositions = $currentPositions;
                $current->add(new DateInterval('P1D'));
                
            } catch (\Exception $e) {
                Logger::error("Exact transit search failed for date", [
                    'date' => $current->format('c'),
                    'error' => $e->getMessage()
                ]);
                $current->add(new DateInterval('P1D'));
            }
        }
        
        return $exactTransits;
    }
    
    /**
     * Calculate aspects between transiting and natal planets
     */
    private function calculateTransitAspects(array $transitPositions, array $natalPositions): array
    {
        $aspects = [];
        $aspectDefinitions = [
            'conjunction' => ['angle' => 0, 'orb' => 8],
            'sextile' => ['angle' => 60, 'orb' => 6],
            'square' => ['angle' => 90, 'orb' => 8],
            'trine' => ['angle' => 120, 'orb' => 8],
            'opposition' => ['angle' => 180, 'orb' => 8],
            'quincunx' => ['angle' => 150, 'orb' => 3]
        ];
        
        foreach ($transitPositions as $transitPlanet => $transitData) {
            foreach ($natalPositions as $natalPlanet => $natalData) {
                $transitLon = $transitData['longitude'];
                $natalLon = $natalData['longitude'];
                
                $angle = abs($transitLon - $natalLon);
                if ($angle > 180) {
                    $angle = 360 - $angle;
                }
                
                foreach ($aspectDefinitions as $aspectName => $aspectData) {
                    $difference = abs($angle - $aspectData['angle']);
                    
                    if ($difference <= $aspectData['orb']) {
                        $aspects[] = [
                            'transiting_planet' => $transitPlanet,
                            'natal_planet' => $natalPlanet,
                            'aspect' => $aspectName,
                            'angle' => $angle,
                            'orb' => $difference,
                            'exact_angle' => $aspectData['angle'],
                            'applying' => $this->isTransitApplying($transitData, $natalData, $aspectData['angle']),
                            'strength' => $this->calculateAspectStrength($difference, $aspectData['orb']),
                            'transiting_position' => $transitLon,
                            'natal_position' => $natalLon
                        ];
                        break;
                    }
                }
            }
        }
        
        // Sort by strength (exact aspects first)
        usort($aspects, function($a, $b) {
            return $a['orb'] <=> $b['orb'];
        });
        
        return $aspects;
    }
    
    /**
     * Determine if a transit is applying (getting closer) or separating
     */
    private function isTransitApplying(array $transitData, array $natalData, float $exactAngle): bool
    {
        $transitSpeed = $transitData['longitude_speed'] ?? 0;
        
        // If transiting planet is moving forward, it's generally applying
        // More sophisticated logic would calculate the actual angular approach
        return $transitSpeed > 0;
    }
    
    /**
     * Calculate aspect strength based on exactness (0-100 scale)
     */
    private function calculateAspectStrength(float $orb, float $maxOrb): int
    {
        $strength = (1 - ($orb / $maxOrb)) * 100;
        return max(0, min(100, (int) round($strength)));
    }
    
    /**
     * Find exact moments when aspects cross their perfect angles
     */
    private function findExactAspectCrossings(array $pos1, array $pos2, array $natalPos, array $aspectTypes, DateTime $date1, DateTime $date2): array
    {
        $exactDates = [];
        $aspectAngles = [
            'conjunction' => 0,
            'sextile' => 60,
            'square' => 90,
            'trine' => 120,
            'opposition' => 180,
            'quincunx' => 150
        ];
        
        foreach ($pos1 as $planet => $data1) {
            if (!isset($pos2[$planet])) continue;
            
            $data2 = $pos2[$planet];
            $lon1 = $data1['longitude'];
            $lon2 = $data2['longitude'];
            
            foreach ($natalPos as $natalPlanet => $natalData) {
                $natalLon = $natalData['longitude'];
                
                foreach ($aspectTypes as $aspectType) {
                    if (!isset($aspectAngles[$aspectType])) continue;
                    
                    $targetAngle = $aspectAngles[$aspectType];
                    $exactDate = $this->interpolateExactAspectDate($lon1, $lon2, $natalLon, $targetAngle, $date1, $date2);
                    
                    if ($exactDate) {
                        $exactDates[] = [
                            'date' => $exactDate->format('c'),
                            'transiting_planet' => $planet,
                            'natal_planet' => $natalPlanet,
                            'aspect' => $aspectType,
                            'exact_angle' => $targetAngle
                        ];
                    }
                }
            }
        }
        
        return $exactDates;
    }
    
    /**
     * Interpolate the exact date when an aspect becomes perfect
     */
    private function interpolateExactAspectDate(float $lon1, float $lon2, float $natalLon, float $targetAngle, DateTime $date1, DateTime $date2): ?DateTime
    {
        // Calculate angles at both dates
        $angle1 = $this->normalizeAngle(abs($lon1 - $natalLon));
        $angle2 = $this->normalizeAngle(abs($lon2 - $natalLon));
        
        // Check if the target angle was crossed between the two dates
        $crossed = false;
        
        if ($angle1 <= $targetAngle && $angle2 >= $targetAngle) {
            $crossed = true;
        } elseif ($angle1 >= $targetAngle && $angle2 <= $targetAngle) {
            $crossed = true;
        }
        
        if (!$crossed) {
            return null;
        }
        
        // Simple linear interpolation (could be improved with more sophisticated methods)
        $totalChange = abs($angle2 - $angle1);
        if ($totalChange == 0) {
            return clone $date1;
        }
        
        $targetChange = abs($targetAngle - $angle1);
        $ratio = $targetChange / $totalChange;
        
        $timeDiff = $date2->getTimestamp() - $date1->getTimestamp();
        $exactTimestamp = $date1->getTimestamp() + ($ratio * $timeDiff);
        
        $exactDate = new DateTime();
        $exactDate->setTimestamp((int) $exactTimestamp);
        
        return $exactDate;
    }
    
    /**
     * Normalize angle to 0-180 range for aspect calculations
     */
    private function normalizeAngle(float $angle): float
    {
        $angle = fmod($angle, 360);
        if ($angle > 180) {
            $angle = 360 - $angle;
        }
        return abs($angle);
    }
    
    /**
     * Get upcoming significant transits within specified period
     */
    public function getUpcomingTransits(int $days = 30, array $significantAspects = ['conjunction', 'square', 'trine', 'opposition']): array
    {
        $startDate = new DateTime();
        $endDate = (clone $startDate)->add(new DateInterval("P{$days}D"));
        
        $exactTransits = $this->findExactTransitDates($startDate, $endDate, $significantAspects);
        
        // Sort by date
        usort($exactTransits, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        return $exactTransits;
    }
    
    /**
     * Create transit chart data for visualization
     */
    public function generateTransitChart(?DateTime $transitDate = null): array
    {
        $transitData = $this->getCurrentTransits($transitDate);
        
        return [
            'chart_type' => 'transit',
            'natal_chart_id' => $this->natalChart->getId(),
            'transit_date' => $transitData['transit_date'],
            'inner_wheel' => $this->natalChart->getPlanetaryPositions(),
            'outer_wheel' => $transitData['current_positions'],
            'transit_aspects' => $transitData['transits'],
            'houses' => $this->natalChart->getHousePositions(),
            'metadata' => $transitData['calculation_metadata']
        ];
    }
}