<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\SwissEphemeris;
use QuantumAstrology\Core\Logger;
use DateTime;
use DateTimeZone;
use DateInterval;

class SolarReturn
{
    private SwissEphemeris $swissEph;
    private Chart $natalChart;
    
    public function __construct(Chart $natalChart)
    {
        $this->natalChart = $natalChart;
        $this->swissEph = new SwissEphemeris();
    }
    
    /**
     * Calculate Solar Return chart for a specific year
     * Solar Return is cast when the Sun returns to its exact natal position
     */
    public function calculateSolarReturn(int $returnYear, ?float $returnLatitude = null, ?float $returnLongitude = null, ?string $returnLocationName = null): array
    {
        try {
            $natalPositions = $this->natalChart->getPlanetaryPositions();
            if (!$natalPositions || !isset($natalPositions['sun'])) {
                throw new \Exception('Natal chart Sun position not available');
            }
            
            $natalSunLongitude = $natalPositions['sun']['longitude'];
            
            // Use return location or default to natal location
            $latitude = $returnLatitude ?? $this->natalChart->getBirthLatitude();
            $longitude = $returnLongitude ?? $this->natalChart->getBirthLongitude();
            $locationName = $returnLocationName ?? $this->natalChart->getBirthLocationName();
            
            // Find the exact moment when Sun returns to natal position
            $solarReturnDate = $this->findSolarReturnDate($returnYear, $natalSunLongitude);
            
            // Calculate planetary positions for Solar Return
            $solarReturnPositions = $this->swissEph->calculatePlanetaryPositions(
                $solarReturnDate,
                $latitude,
                $longitude
            );
            
            // Calculate houses for Solar Return location
            $solarReturnHouses = $this->swissEph->calculateHouses(
                $solarReturnDate,
                $latitude,
                $longitude,
                $this->natalChart->getHouseSystem() ?? 'P'
            );
            
            // Calculate aspects within Solar Return chart
            $solarReturnAspects = $this->calculateSolarReturnAspects($solarReturnPositions);
            
            // Calculate aspects between Solar Return and Natal positions
            $solarReturnToNatalAspects = $this->calculateSolarReturnToNatalAspects($solarReturnPositions, $natalPositions);
            
            return [
                'chart_type' => 'solar_return',
                'return_year' => $returnYear,
                'natal_chart_id' => $this->natalChart->getId(),
                'solar_return_date' => $solarReturnDate->format('c'),
                'return_location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'name' => $locationName
                ],
                'natal_sun_longitude' => $natalSunLongitude,
                'return_sun_longitude' => $solarReturnPositions['sun']['longitude'],
                'natal_positions' => $natalPositions,
                'solar_return_positions' => $solarReturnPositions,
                'solar_return_houses' => $solarReturnHouses,
                'internal_aspects' => $solarReturnAspects,
                'solar_return_to_natal_aspects' => $solarReturnToNatalAspects,
                'calculation_metadata' => [
                    'calculated_at' => date('c'),
                    'chart_id' => $this->natalChart->getId(),
                    'calculation_method' => 'solar_return',
                    'return_year' => $returnYear,
                    'sun_return_precision' => abs($solarReturnPositions['sun']['longitude'] - $natalSunLongitude)
                ]
            ];
            
        } catch (\Exception $e) {
            Logger::error("Solar Return calculation failed", [
                'error' => $e->getMessage(),
                'chart_id' => $this->natalChart->getId(),
                'return_year' => $returnYear
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate Solar Returns for multiple years
     */
    public function calculateMultipleSolarReturns(int $startYear, int $endYear, ?float $returnLatitude = null, ?float $returnLongitude = null): array
    {
        $solarReturns = [];
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            try {
                $solarReturn = $this->calculateSolarReturn($year, $returnLatitude, $returnLongitude);
                $solarReturns[$year] = $solarReturn;
            } catch (\Exception $e) {
                Logger::error("Solar Return calculation failed for year", [
                    'year' => $year,
                    'error' => $e->getMessage()
                ]);
                $solarReturns[$year] = [
                    'error' => $e->getMessage(),
                    'year' => $year
                ];
            }
        }
        
        return $solarReturns;
    }
    
    /**
     * Find the exact date and time when the Sun returns to its natal position
     */
    private function findSolarReturnDate(int $returnYear, float $natalSunLongitude): DateTime
    {
        $birthDate = $this->natalChart->getBirthDatetime();
        if (!$birthDate) {
            throw new \Exception('Natal birth date not available');
        }
        
        // Start with birthday in the return year
        $searchDate = new DateTime($returnYear . '-' . $birthDate->format('m-d H:i:s'));
        $searchDate->setTimezone($birthDate->getTimezone());
        
        // Search range: 5 days before to 5 days after birthday
        $startSearch = clone $searchDate;
        $startSearch->sub(new DateInterval('P5D'));
        
        $endSearch = clone $searchDate;
        $endSearch->add(new DateInterval('P5D'));
        
        $bestDate = null;
        $bestDifference = 360; // Maximum possible difference
        
        // Search with 6-hour intervals initially
        $current = clone $startSearch;
        while ($current <= $endSearch) {
            try {
                $positions = $this->swissEph->calculatePlanetaryPositions(
                    $current,
                    $this->natalChart->getBirthLatitude(),
                    $this->natalChart->getBirthLongitude()
                );
                
                if (isset($positions['sun'])) {
                    $currentSunLon = $positions['sun']['longitude'];
                    $difference = abs($currentSunLon - $natalSunLongitude);
                    
                    // Handle zodiac wraparound
                    if ($difference > 180) {
                        $difference = 360 - $difference;
                    }
                    
                    if ($difference < $bestDifference) {
                        $bestDifference = $difference;
                        $bestDate = clone $current;
                    }
                }
                
                $current->add(new DateInterval('PT6H'));
                
            } catch (\Exception $e) {
                $current->add(new DateInterval('PT6H'));
            }
        }
        
        if (!$bestDate) {
            throw new \Exception('Could not find Solar Return date for year ' . $returnYear);
        }
        
        // Refine the search around the best date found (1-hour intervals)
        $refineStart = clone $bestDate;
        $refineStart->sub(new DateInterval('PT12H'));
        
        $refineEnd = clone $bestDate;
        $refineEnd->add(new DateInterval('PT12H'));
        
        $current = clone $refineStart;
        while ($current <= $refineEnd) {
            try {
                $positions = $this->swissEph->calculatePlanetaryPositions(
                    $current,
                    $this->natalChart->getBirthLatitude(),
                    $this->natalChart->getBirthLongitude()
                );
                
                if (isset($positions['sun'])) {
                    $currentSunLon = $positions['sun']['longitude'];
                    $difference = abs($currentSunLon - $natalSunLongitude);
                    
                    if ($difference > 180) {
                        $difference = 360 - $difference;
                    }
                    
                    if ($difference < $bestDifference) {
                        $bestDifference = $difference;
                        $bestDate = clone $current;
                    }
                }
                
                $current->add(new DateInterval('PT1H'));
                
            } catch (\Exception $e) {
                $current->add(new DateInterval('PT1H'));
            }
        }
        
        return $bestDate;
    }
    
    /**
     * Calculate aspects within the Solar Return chart
     */
    private function calculateSolarReturnAspects(array $solarReturnPositions): array
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
        
        $planets = array_keys($solarReturnPositions);
        
        for ($i = 0; $i < count($planets); $i++) {
            for ($j = $i + 1; $j < count($planets); $j++) {
                $planet1 = $planets[$i];
                $planet2 = $planets[$j];
                
                if (!isset($solarReturnPositions[$planet1]) || !isset($solarReturnPositions[$planet2])) {
                    continue;
                }
                
                $pos1 = $solarReturnPositions[$planet1]['longitude'];
                $pos2 = $solarReturnPositions[$planet2]['longitude'];
                
                $angle = abs($pos1 - $pos2);
                if ($angle > 180) {
                    $angle = 360 - $angle;
                }
                
                foreach ($aspectDefinitions as $aspectName => $aspectData) {
                    $difference = abs($angle - $aspectData['angle']);
                    if ($difference <= $aspectData['orb']) {
                        $aspects[] = [
                            'planet1' => $planet1,
                            'planet2' => $planet2,
                            'aspect' => $aspectName,
                            'angle' => $angle,
                            'orb' => $difference,
                            'exact_angle' => $aspectData['angle'],
                            'strength' => $this->calculateAspectStrength($difference, $aspectData['orb']),
                            'chart_type' => 'solar_return_internal'
                        ];
                        break;
                    }
                }
            }
        }
        
        return $aspects;
    }
    
    /**
     * Calculate aspects between Solar Return and Natal positions
     */
    private function calculateSolarReturnToNatalAspects(array $solarReturnPositions, array $natalPositions): array
    {
        $aspects = [];
        $aspectDefinitions = [
            'conjunction' => ['angle' => 0, 'orb' => 6],
            'sextile' => ['angle' => 60, 'orb' => 4],
            'square' => ['angle' => 90, 'orb' => 6],
            'trine' => ['angle' => 120, 'orb' => 6],
            'opposition' => ['angle' => 180, 'orb' => 6],
            'quincunx' => ['angle' => 150, 'orb' => 3]
        ];
        
        foreach ($solarReturnPositions as $srPlanet => $srData) {
            foreach ($natalPositions as $natalPlanet => $natalData) {
                $srLon = $srData['longitude'];
                $natalLon = $natalData['longitude'];
                
                $angle = abs($srLon - $natalLon);
                if ($angle > 180) {
                    $angle = 360 - $angle;
                }
                
                foreach ($aspectDefinitions as $aspectName => $aspectData) {
                    $difference = abs($angle - $aspectData['angle']);
                    
                    if ($difference <= $aspectData['orb']) {
                        $aspects[] = [
                            'solar_return_planet' => $srPlanet,
                            'natal_planet' => $natalPlanet,
                            'aspect' => $aspectName,
                            'angle' => $angle,
                            'orb' => $difference,
                            'exact_angle' => $aspectData['angle'],
                            'strength' => $this->calculateAspectStrength($difference, $aspectData['orb']),
                            'solar_return_position' => $srLon,
                            'natal_position' => $natalLon,
                            'chart_type' => 'solar_return_to_natal'
                        ];
                        break;
                    }
                }
            }
        }
        
        // Sort by strength
        usort($aspects, function($a, $b) {
            return $a['orb'] <=> $b['orb'];
        });
        
        return $aspects;
    }
    
    /**
     * Generate complete Solar Return chart data for visualization
     */
    public function generateSolarReturnChart(int $returnYear, ?float $returnLatitude = null, ?float $returnLongitude = null, ?string $returnLocationName = null): array
    {
        $solarReturn = $this->calculateSolarReturn($returnYear, $returnLatitude, $returnLongitude, $returnLocationName);
        
        return [
            'chart_type' => 'solar_return',
            'natal_chart_id' => $this->natalChart->getId(),
            'return_year' => $returnYear,
            'solar_return_date' => $solarReturn['solar_return_date'],
            'return_location' => $solarReturn['return_location'],
            'inner_wheel' => $solarReturn['natal_positions'], // Natal on inner
            'outer_wheel' => $solarReturn['solar_return_positions'], // Solar Return on outer
            'houses' => $solarReturn['solar_return_houses'], // Use Solar Return houses
            'solar_return_aspects' => $solarReturn['internal_aspects'],
            'cross_aspects' => $solarReturn['solar_return_to_natal_aspects'],
            'metadata' => $solarReturn['calculation_metadata']
        ];
    }
    
    /**
     * Calculate Solar Return profections (annual planetary rulers)
     */
    public function calculateSolarReturnProfections(int $returnYear): array
    {
        $birthDate = $this->natalChart->getBirthDatetime();
        if (!$birthDate) {
            throw new \Exception('Natal birth date not available');
        }
        
        $currentAge = $returnYear - (int) $birthDate->format('Y');
        
        // Traditional planet rulers
        $houseRulers = [
            1 => 'mars',      // Aries
            2 => 'venus',     // Taurus  
            3 => 'mercury',   // Gemini
            4 => 'moon',      // Cancer
            5 => 'sun',       // Leo
            6 => 'mercury',   // Virgo
            7 => 'venus',     // Libra
            8 => 'mars',      // Scorpio
            9 => 'jupiter',   // Sagittarius
            10 => 'saturn',   // Capricorn
            11 => 'saturn',   // Aquarius
            12 => 'jupiter'   // Pisces
        ];
        
        // Annual profection: each year activates the next house
        $profectedHouse = (($currentAge - 1) % 12) + 1;
        $lordOfTheYear = $houseRulers[$profectedHouse];
        
        return [
            'return_year' => $returnYear,
            'current_age' => $currentAge,
            'profected_house' => $profectedHouse,
            'lord_of_year' => $lordOfTheYear,
            'house_themes' => $this->getHouseThemes($profectedHouse)
        ];
    }
    
    /**
     * Get thematic meanings for profected houses
     */
    private function getHouseThemes(int $house): array
    {
        $themes = [
            1 => ['Identity', 'Self-expression', 'New beginnings', 'Physical body'],
            2 => ['Resources', 'Values', 'Self-worth', 'Material security'],
            3 => ['Communication', 'Learning', 'Siblings', 'Short trips'],
            4 => ['Home', 'Family', 'Roots', 'Emotional foundation'],
            5 => ['Creativity', 'Children', 'Romance', 'Self-expression'],
            6 => ['Health', 'Work', 'Service', 'Daily routines'],
            7 => ['Relationships', 'Partnership', 'Others', 'Open enemies'],
            8 => ['Transformation', 'Shared resources', 'Death/rebirth', 'Occult'],
            9 => ['Higher learning', 'Philosophy', 'Travel', 'Spirituality'],
            10 => ['Career', 'Reputation', 'Authority', 'Public life'],
            11 => ['Friends', 'Groups', 'Hopes', 'Social networks'],
            12 => ['Spirituality', 'Hidden enemies', 'Subconscious', 'Sacrifice']
        ];
        
        return $themes[$house] ?? [];
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
     * Calculate Solar Return relocated charts for different cities
     */
    public function calculateRelocatedSolarReturns(int $returnYear, array $locations): array
    {
        $relocatedReturns = [];
        
        foreach ($locations as $location) {
            if (!isset($location['latitude'], $location['longitude'])) {
                continue;
            }
            
            try {
                $solarReturn = $this->calculateSolarReturn(
                    $returnYear,
                    $location['latitude'],
                    $location['longitude'],
                    $location['name'] ?? 'Unknown Location'
                );
                
                $relocatedReturns[] = [
                    'location' => $location,
                    'solar_return' => $solarReturn,
                    'rising_sign' => $this->getSignFromLongitude($solarReturn['solar_return_houses'][1]['cusp'] ?? 0),
                    'midheaven_sign' => $this->getSignFromLongitude($solarReturn['solar_return_houses'][10]['cusp'] ?? 0)
                ];
                
            } catch (\Exception $e) {
                Logger::error("Relocated Solar Return calculation failed", [
                    'location' => $location,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $relocatedReturns;
    }
    
    /**
     * Get zodiac sign from longitude
     */
    private function getSignFromLongitude(float $longitude): string
    {
        $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 
                 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
        
        $signIndex = (int) floor($longitude / 30);
        return $signs[$signIndex] ?? 'Unknown';
    }
}