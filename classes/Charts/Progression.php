<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\SwissEphemeris;
use QuantumAstrology\Core\Logger;
use DateTime;
use DateTimeZone;
use DateInterval;

class Progression
{
    private SwissEphemeris $swissEph;
    private Chart $natalChart;
    
    public function __construct(Chart $natalChart)
    {
        $this->natalChart = $natalChart;
        $this->swissEph = new SwissEphemeris();
    }
    
    /**
     * Calculate secondary progressions for a given date
     * Uses day-for-year method: 1 day after birth = 1 year after birth
     */
    public function calculateSecondaryProgressions(?DateTime $progressedDate = null): array
    {
        $progressedDate = $progressedDate ?? new DateTime();
        
        try {
            $birthDate = $this->natalChart->getBirthDatetime();
            if (!$birthDate) {
                throw new \Exception('Natal chart birth date not available');
            }
            
            // Calculate progressed date using day-for-year method
            $yearsFromBirth = $progressedDate->diff($birthDate)->days / 365.25;
            $daysToProgress = (int) round($yearsFromBirth);
            
            $calculationDate = clone $birthDate;
            $calculationDate->add(new DateInterval("P{$daysToProgress}D"));
            
            // Calculate progressed planetary positions for the calculation date
            $progressedPositions = $this->swissEph->calculatePlanetaryPositions(
                $calculationDate,
                $this->natalChart->getBirthLatitude(),
                $this->natalChart->getBirthLongitude()
            );
            
            // Calculate progressed houses (using progressed date but natal birth time and place)
            $progressedHouses = $this->swissEph->calculateHouses(
                $calculationDate,
                $this->natalChart->getBirthLatitude(),
                $this->natalChart->getBirthLongitude(),
                $this->natalChart->getHouseSystem() ?? 'P'
            );
            
            // Calculate aspects between progressed and natal positions
            $natalPositions = $this->natalChart->getPlanetaryPositions();
            $progressedAspects = $this->calculateProgressedAspects($progressedPositions, $natalPositions);
            
            // Calculate aspects within progressed chart
            $progressedInternalAspects = Chart::calculateAspects($progressedPositions);
            
            return [
                'progression_type' => 'secondary',
                'natal_date' => $birthDate->format('c'),
                'progressed_date' => $progressedDate->format('c'),
                'calculation_date' => $calculationDate->format('c'),
                'years_progressed' => $yearsFromBirth,
                'days_progressed' => $daysToProgress,
                'natal_positions' => $natalPositions,
                'progressed_positions' => $progressedPositions,
                'progressed_houses' => $progressedHouses,
                'progressed_to_natal_aspects' => $progressedAspects,
                'progressed_internal_aspects' => $progressedInternalAspects,
                'calculation_metadata' => [
                    'calculated_at' => date('c'),
                    'chart_id' => $this->natalChart->getId(),
                    'calculation_method' => 'secondary_progressions',
                    'progression_formula' => 'day_for_year'
                ]
            ];
            
        } catch (\Exception $e) {
            Logger::error("Secondary progression calculation failed", [
                'error' => $e->getMessage(),
                'chart_id' => $this->natalChart->getId(),
                'progressed_date' => $progressedDate->format('c')
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate progressed lunar phases and important progressed events
     */
    public function calculateProgressedLunarPhases(DateTime $startDate, DateTime $endDate): array
    {
        $lunarPhases = [];
        $current = clone $startDate;
        $interval = new DateInterval('P7D'); // Check weekly
        
        while ($current <= $endDate) {
            try {
                $progression = $this->calculateSecondaryProgressions($current);
                $progressedPositions = $progression['progressed_positions'];
                
                if (isset($progressedPositions['sun']) && isset($progressedPositions['moon'])) {
                    $sunLon = $progressedPositions['sun']['longitude'];
                    $moonLon = $progressedPositions['moon']['longitude'];
                    
                    $angle = abs($moonLon - $sunLon);
                    if ($angle > 180) {
                        $angle = 360 - $angle;
                    }
                    
                    $phase = $this->determineLunarPhase($angle);
                    
                    $lunarPhases[] = [
                        'date' => $current->format('c'),
                        'phase' => $phase,
                        'angle' => $angle,
                        'sun_position' => $sunLon,
                        'moon_position' => $moonLon
                    ];
                }
                
                $current->add($interval);
                
            } catch (\Exception $e) {
                Logger::error("Progressed lunar phase calculation failed", [
                    'date' => $current->format('c'),
                    'error' => $e->getMessage()
                ]);
                $current->add($interval);
            }
        }
        
        return $lunarPhases;
    }
    
    /**
     * Calculate progressed aspects for a date range to find exact progression dates
     */
    public function findProgressedAspectDates(DateTime $startDate, DateTime $endDate, array $aspectTypes = ['conjunction', 'square', 'trine', 'opposition']): array
    {
        $aspectEvents = [];
        $current = clone $startDate;
        $previousProgression = null;
        
        while ($current <= $endDate) {
            try {
                $currentProgression = $this->calculateSecondaryProgressions($current);
                
                if ($previousProgression) {
                    $exactDates = $this->findExactProgressedAspectCrossings(
                        $previousProgression['progressed_positions'],
                        $currentProgression['progressed_positions'],
                        $this->natalChart->getPlanetaryPositions(),
                        $aspectTypes,
                        clone $current->sub(new DateInterval('P1M')),
                        $current
                    );
                    
                    $aspectEvents = array_merge($aspectEvents, $exactDates);
                }
                
                $previousProgression = $currentProgression;
                $current->add(new DateInterval('P1M')); // Monthly intervals for progressions
                
            } catch (\Exception $e) {
                Logger::error("Progressed aspect search failed", [
                    'date' => $current->format('c'),
                    'error' => $e->getMessage()
                ]);
                $current->add(new DateInterval('P1M'));
            }
        }
        
        return $aspectEvents;
    }
    
    /**
     * Generate a complete progressed chart for visualization
     */
    public function generateProgressedChart(?DateTime $progressedDate = null): array
    {
        $progression = $this->calculateSecondaryProgressions($progressedDate);
        
        return [
            'chart_type' => 'progressed',
            'natal_chart_id' => $this->natalChart->getId(),
            'progressed_date' => $progression['progressed_date'],
            'calculation_date' => $progression['calculation_date'],
            'inner_wheel' => $progression['natal_positions'], // Natal on inner wheel
            'outer_wheel' => $progression['progressed_positions'], // Progressed on outer wheel
            'progressed_houses' => $progression['progressed_houses'],
            'progressed_aspects' => $progression['progressed_to_natal_aspects'],
            'internal_aspects' => $progression['progressed_internal_aspects'],
            'metadata' => $progression['calculation_metadata']
        ];
    }
    
    /**
     * Calculate aspects between progressed and natal planets
     */
    private function calculateProgressedAspects(array $progressedPositions, array $natalPositions): array
    {
        if (!$natalPositions) {
            return [];
        }
        
        $aspects = [];
        $aspectDefinitions = [
            'conjunction' => ['angle' => 0, 'orb' => 6],
            'sextile' => ['angle' => 60, 'orb' => 4],
            'square' => ['angle' => 90, 'orb' => 6],
            'trine' => ['angle' => 120, 'orb' => 6],
            'opposition' => ['angle' => 180, 'orb' => 6],
            'quincunx' => ['angle' => 150, 'orb' => 2]
        ];
        
        foreach ($progressedPositions as $progressedPlanet => $progressedData) {
            foreach ($natalPositions as $natalPlanet => $natalData) {
                $progressedLon = $progressedData['longitude'];
                $natalLon = $natalData['longitude'];
                
                $angle = abs($progressedLon - $natalLon);
                if ($angle > 180) {
                    $angle = 360 - $angle;
                }
                
                foreach ($aspectDefinitions as $aspectName => $aspectData) {
                    $difference = abs($angle - $aspectData['angle']);
                    
                    if ($difference <= $aspectData['orb']) {
                        $aspects[] = [
                            'progressed_planet' => $progressedPlanet,
                            'natal_planet' => $natalPlanet,
                            'aspect' => $aspectName,
                            'angle' => $angle,
                            'orb' => $difference,
                            'exact_angle' => $aspectData['angle'],
                            'strength' => $this->calculateAspectStrength($difference, $aspectData['orb']),
                            'progressed_position' => $progressedLon,
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
     * Find exact moments when progressed aspects become perfect
     */
    private function findExactProgressedAspectCrossings(array $pos1, array $pos2, array $natalPos, array $aspectTypes, DateTime $date1, DateTime $date2): array
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
                    $exactDate = $this->interpolateExactProgressedAspectDate($lon1, $lon2, $natalLon, $targetAngle, $date1, $date2);
                    
                    if ($exactDate) {
                        $exactDates[] = [
                            'date' => $exactDate->format('c'),
                            'progressed_planet' => $planet,
                            'natal_planet' => $natalPlanet,
                            'aspect' => $aspectType,
                            'exact_angle' => $targetAngle,
                            'progression_type' => 'secondary'
                        ];
                    }
                }
            }
        }
        
        return $exactDates;
    }
    
    /**
     * Interpolate exact date for progressed aspect
     */
    private function interpolateExactProgressedAspectDate(float $lon1, float $lon2, float $natalLon, float $targetAngle, DateTime $date1, DateTime $date2): ?DateTime
    {
        $angle1 = $this->normalizeAngle(abs($lon1 - $natalLon));
        $angle2 = $this->normalizeAngle(abs($lon2 - $natalLon));
        
        // Check if the target angle was crossed
        $crossed = false;
        
        if ($angle1 <= $targetAngle && $angle2 >= $targetAngle) {
            $crossed = true;
        } elseif ($angle1 >= $targetAngle && $angle2 <= $targetAngle) {
            $crossed = true;
        }
        
        if (!$crossed) {
            return null;
        }
        
        // Linear interpolation for date
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
     * Determine lunar phase from Sun-Moon angle
     */
    private function determineLunarPhase(float $angle): string
    {
        if ($angle <= 15 || $angle >= 345) {
            return 'New Moon';
        } elseif ($angle >= 75 && $angle <= 105) {
            return 'First Quarter';
        } elseif ($angle >= 165 && $angle <= 195) {
            return 'Full Moon';
        } elseif ($angle >= 255 && $angle <= 285) {
            return 'Last Quarter';
        } elseif ($angle > 15 && $angle < 75) {
            return 'Waxing Crescent';
        } elseif ($angle > 105 && $angle < 165) {
            return 'Waxing Gibbous';
        } elseif ($angle > 195 && $angle < 255) {
            return 'Waning Gibbous';
        } else {
            return 'Waning Crescent';
        }
    }
    
    /**
     * Calculate aspect strength (0-100 scale)
     */
    private function calculateAspectStrength(float $orb, float $maxOrb): int
    {
        $strength = (1 - ($orb / $maxOrb)) * 100;
        return max(0, min(100, (int) round($strength)));
    }
    
    /**
     * Normalize angle to 0-180 range
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
     * Calculate progressed lunar returns (progressed Moon returning to natal Moon position)
     */
    public function calculateProgressedLunarReturns(DateTime $startDate, DateTime $endDate): array
    {
        $lunarReturns = [];
        $natalPositions = $this->natalChart->getPlanetaryPositions();
        
        if (!isset($natalPositions['moon'])) {
            return $lunarReturns;
        }
        
        $natalMoonLon = $natalPositions['moon']['longitude'];
        $current = clone $startDate;
        
        while ($current <= $endDate) {
            try {
                $progression = $this->calculateSecondaryProgressions($current);
                $progressedPositions = $progression['progressed_positions'];
                
                if (isset($progressedPositions['moon'])) {
                    $progressedMoonLon = $progressedPositions['moon']['longitude'];
                    $angle = abs($progressedMoonLon - $natalMoonLon);
                    
                    // Check for conjunction (within 2 degrees)
                    if ($angle <= 2 || (360 - $angle) <= 2) {
                        $lunarReturns[] = [
                            'date' => $current->format('c'),
                            'calculation_date' => $progression['calculation_date'],
                            'natal_moon_position' => $natalMoonLon,
                            'progressed_moon_position' => $progressedMoonLon,
                            'orb' => min($angle, 360 - $angle),
                            'return_type' => 'progressed_lunar'
                        ];
                    }
                }
                
                $current->add(new DateInterval('P3M')); // Check quarterly
                
            } catch (\Exception $e) {
                Logger::error("Progressed lunar return calculation failed", [
                    'date' => $current->format('c'),
                    'error' => $e->getMessage()
                ]);
                $current->add(new DateInterval('P3M'));
            }
        }
        
        return $lunarReturns;
    }
}