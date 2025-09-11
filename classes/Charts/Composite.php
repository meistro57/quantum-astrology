<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\SwissEphemeris;
use QuantumAstrology\Core\Logger;
use DateTime;
use DateTimeZone;

class Composite
{
    private Chart $chart1;
    private Chart $chart2;
    private SwissEphemeris $swissEph;
    
    public function __construct(Chart $chart1, Chart $chart2)
    {
        $this->chart1 = $chart1;
        $this->chart2 = $chart2;
        $this->swissEph = new SwissEphemeris();
    }
    
    /**
     * Calculate composite chart using midpoint method
     */
    public function calculateComposite(): array
    {
        try {
            $positions1 = $this->chart1->getPlanetaryPositions();
            $positions2 = $this->chart2->getPlanetaryPositions();
            
            if (!$positions1 || !$positions2) {
                throw new \Exception('Both charts must have planetary positions calculated');
            }
            
            // Calculate midpoint positions
            $compositePositions = $this->calculateMidpointPositions($positions1, $positions2);
            
            // Calculate composite date and location
            $compositeDateTime = $this->calculateCompositeDatetime();
            $compositeLocation = $this->calculateCompositeLocation();
            
            // Calculate composite houses using midpoint location and time
            $compositeHouses = $this->calculateCompositeHouses($compositeDateTime, $compositeLocation);
            
            // Calculate aspects within composite chart
            $compositeAspects = $this->calculateCompositeAspects($compositePositions);
            
            // Analyze composite chart themes
            $compositeThemes = $this->analyzeCompositeThemes($compositePositions, $compositeAspects);
            
            // Calculate relationship purpose and dynamics
            $relationshipPurpose = $this->analyzeRelationshipPurpose($compositePositions, $compositeHouses);
            
            return [
                'composite_type' => 'midpoint_composite',
                'chart1_id' => $this->chart1->getId(),
                'chart2_id' => $this->chart2->getId(),
                'chart1_name' => $this->chart1->getName(),
                'chart2_name' => $this->chart2->getName(),
                'original_positions_1' => $positions1,
                'original_positions_2' => $positions2,
                'composite_positions' => $compositePositions,
                'composite_datetime' => $compositeDateTime->format('c'),
                'composite_location' => $compositeLocation,
                'composite_houses' => $compositeHouses,
                'composite_aspects' => $compositeAspects,
                'composite_themes' => $compositeThemes,
                'relationship_purpose' => $relationshipPurpose,
                'calculation_metadata' => [
                    'calculated_at' => date('c'),
                    'calculation_method' => 'midpoint_composite',
                    'total_aspects' => count($compositeAspects)
                ]
            ];
            
        } catch (\Exception $e) {
            Logger::error("Composite calculation failed", [
                'error' => $e->getMessage(),
                'chart1_id' => $this->chart1->getId(),
                'chart2_id' => $this->chart2->getId()
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate midpoint positions between two charts
     */
    private function calculateMidpointPositions(array $positions1, array $positions2): array
    {
        $compositePositions = [];
        
        foreach ($positions1 as $planet => $data1) {
            if (!isset($positions2[$planet])) continue;
            
            $data2 = $positions2[$planet];
            $lon1 = $data1['longitude'];
            $lon2 = $data2['longitude'];
            
            // Calculate midpoint longitude
            $midpointLon = $this->calculateMidpoint($lon1, $lon2);
            
            // Calculate midpoint latitude (simple average)
            $lat1 = $data1['latitude'] ?? 0;
            $lat2 = $data2['latitude'] ?? 0;
            $midpointLat = ($lat1 + $lat2) / 2;
            
            // Calculate average distance and speed if available
            $distance1 = $data1['distance'] ?? 0;
            $distance2 = $data2['distance'] ?? 0;
            $midpointDistance = ($distance1 + $distance2) / 2;
            
            $speed1 = $data1['longitude_speed'] ?? 0;
            $speed2 = $data2['longitude_speed'] ?? 0;
            $midpointSpeed = ($speed1 + $speed2) / 2;
            
            $compositePositions[$planet] = [
                'longitude' => $midpointLon,
                'latitude' => $midpointLat,
                'distance' => $midpointDistance,
                'longitude_speed' => $midpointSpeed,
                'zodiac_sign' => $this->getZodiacSign($midpointLon),
                'zodiac_degree' => fmod($midpointLon, 30),
                'calculation_method' => 'midpoint'
            ];
        }
        
        return $compositePositions;
    }
    
    /**
     * Calculate midpoint between two longitudes, handling zodiac wraparound
     */
    private function calculateMidpoint(float $lon1, float $lon2): float
    {
        // Calculate both possible midpoints (short and long way around the zodiac)
        $diff = abs($lon2 - $lon1);
        
        if ($diff <= 180) {
            // Short way - simple average
            $midpoint = ($lon1 + $lon2) / 2;
        } else {
            // Long way - add 180° to the simple average
            $midpoint = ($lon1 + $lon2) / 2;
            if ($lon1 < $lon2) {
                $midpoint -= 180;
            } else {
                $midpoint += 180;
            }
        }
        
        // Normalize to 0-360 range
        while ($midpoint < 0) $midpoint += 360;
        while ($midpoint >= 360) $midpoint -= 360;
        
        return $midpoint;
    }
    
    /**
     * Calculate composite datetime (midpoint between birth times)
     */
    private function calculateCompositeDatetime(): DateTime
    {
        $datetime1 = $this->chart1->getBirthDatetime();
        $datetime2 = $this->chart2->getBirthDatetime();
        
        if (!$datetime1 || !$datetime2) {
            throw new \Exception('Both charts must have valid birth datetimes');
        }
        
        $timestamp1 = $datetime1->getTimestamp();
        $timestamp2 = $datetime2->getTimestamp();
        
        $compositeTiestamp = ($timestamp1 + $timestamp2) / 2;
        
        $compositeDateTime = new DateTime();
        $compositeDateTime->setTimestamp((int) $compositeTiestamp);
        
        return $compositeDateTime;
    }
    
    /**
     * Calculate composite location (midpoint between birth locations)
     */
    private function calculateCompositeLocation(): array
    {
        $lat1 = $this->chart1->getBirthLatitude();
        $lon1 = $this->chart1->getBirthLongitude();
        $lat2 = $this->chart2->getBirthLatitude();
        $lon2 = $this->chart2->getBirthLongitude();
        
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            throw new \Exception('Both charts must have valid birth coordinates');
        }
        
        // Simple midpoint calculation (not geodesically accurate but sufficient for astrological purposes)
        $compositeLat = ($lat1 + $lat2) / 2;
        $compositeLon = ($lon1 + $lon2) / 2;
        
        return [
            'latitude' => $compositeLat,
            'longitude' => $compositeLon,
            'name' => 'Composite Location'
        ];
    }
    
    /**
     * Calculate composite houses using composite time and location
     */
    private function calculateCompositeHouses(DateTime $compositeDateTime, array $compositeLocation): array
    {
        try {
            return $this->swissEph->calculateHouses(
                $compositeDateTime,
                $compositeLocation['latitude'],
                $compositeLocation['longitude'],
                $this->chart1->getHouseSystem() ?? 'P'
            );
        } catch (\Exception $e) {
            Logger::warning("Composite houses calculation failed, using chart1 houses", [
                'error' => $e->getMessage()
            ]);
            return $this->chart1->getHousePositions() ?? [];
        }
    }
    
    /**
     * Calculate aspects within the composite chart
     */
    private function calculateCompositeAspects(array $compositePositions): array
    {
        $aspects = [];
        $aspectDefinitions = [
            'conjunction' => ['angle' => 0, 'orb' => 8, 'nature' => 'intense'],
            'sextile' => ['angle' => 60, 'orb' => 6, 'nature' => 'harmonious'],
            'square' => ['angle' => 90, 'orb' => 8, 'nature' => 'dynamic'],
            'trine' => ['angle' => 120, 'orb' => 8, 'nature' => 'flowing'],
            'opposition' => ['angle' => 180, 'orb' => 8, 'nature' => 'polarizing'],
            'quincunx' => ['angle' => 150, 'orb' => 3, 'nature' => 'adjusting']
        ];
        
        $planets = array_keys($compositePositions);
        
        for ($i = 0; $i < count($planets); $i++) {
            for ($j = $i + 1; $j < count($planets); $j++) {
                $planet1 = $planets[$i];
                $planet2 = $planets[$j];
                
                $pos1 = $compositePositions[$planet1]['longitude'];
                $pos2 = $compositePositions[$planet2]['longitude'];
                
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
                            'nature' => $aspectData['nature'],
                            'strength' => $this->calculateAspectStrength($difference, $aspectData['orb']),
                            'composite_significance' => $this->getCompositeAspectSignificance($planet1, $planet2, $aspectName)
                        ];
                        break;
                    }
                }
            }
        }
        
        // Sort by significance and strength
        usort($aspects, function($a, $b) {
            $sigOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $aSig = $sigOrder[$a['composite_significance']] ?? 0;
            $bSig = $sigOrder[$b['composite_significance']] ?? 0;
            
            if ($aSig !== $bSig) {
                return $bSig <=> $aSig;
            }
            
            return $a['orb'] <=> $b['orb'];
        });
        
        return $aspects;
    }
    
    /**
     * Analyze themes present in the composite chart
     */
    private function analyzeCompositeThemes(array $compositePositions, array $compositeAspects): array
    {
        $themes = [
            'primary_purpose' => [],
            'relationship_challenges' => [],
            'growth_opportunities' => [],
            'karmic_lessons' => [],
            'dominant_elements' => [],
            'relationship_style' => 'balanced'
        ];
        
        // Analyze elemental distribution
        $elements = ['fire' => 0, 'earth' => 0, 'air' => 0, 'water' => 0];
        $modes = ['cardinal' => 0, 'fixed' => 0, 'mutable' => 0];
        
        foreach ($compositePositions as $planet => $data) {
            $sign = $this->getZodiacSign($data['longitude']);
            $element = $this->getSignElement($sign);
            $mode = $this->getSignMode($sign);
            
            $elements[$element]++;
            $modes[$mode]++;
        }
        
        // Determine dominant elements
        arsort($elements);
        $themes['dominant_elements'] = array_slice(array_keys($elements), 0, 2);
        
        // Analyze relationship style based on modes
        arsort($modes);
        $dominantMode = array_keys($modes)[0];
        $themes['relationship_style'] = match($dominantMode) {
            'cardinal' => 'initiative_taking',
            'fixed' => 'stable_committed',
            'mutable' => 'adaptable_flexible',
            default => 'balanced'
        };
        
        // Analyze aspects for themes
        foreach ($compositeAspects as $aspect) {
            $planet1 = $aspect['planet1'];
            $planet2 = $aspect['planet2'];
            $aspectType = $aspect['aspect'];
            $nature = $aspect['nature'];
            
            // Identify relationship purposes
            if (($planet1 === 'sun' && $planet2 === 'moon') || ($planet1 === 'moon' && $planet2 === 'sun')) {
                if ($nature === 'flowing' || $nature === 'harmonious') {
                    $themes['primary_purpose'][] = 'Natural balance and mutual support';
                } else {
                    $themes['relationship_challenges'][] = 'Reconciling different needs and expressions';
                }
            }
            
            if ($planet1 === 'venus' || $planet2 === 'venus') {
                if ($nature === 'flowing' || $nature === 'harmonious') {
                    $themes['primary_purpose'][] = 'Creating beauty, harmony, and shared values';
                } else {
                    $themes['relationship_challenges'][] = 'Differences in aesthetic or romantic expression';
                }
            }
            
            if ($planet1 === 'mars' || $planet2 === 'mars') {
                if ($nature === 'dynamic' || $nature === 'intense') {
                    $themes['growth_opportunities'][] = 'Dynamic action and passionate expression';
                } else {
                    $themes['relationship_challenges'][] = 'Managing conflict and aggressive impulses';
                }
            }
            
            // Karmic indicators
            if (in_array($planet1, ['saturn', 'pluto', 'north_node', 'south_node']) || 
                in_array($planet2, ['saturn', 'pluto', 'north_node', 'south_node'])) {
                $themes['karmic_lessons'][] = $this->getKarmicLesson($planet1, $planet2, $aspectType);
            }
        }
        
        // Remove duplicates and limit entries
        foreach ($themes as $key => $value) {
            if (is_array($value)) {
                $themes[$key] = array_unique(array_slice($value, 0, 3));
            }
        }
        
        return $themes;
    }
    
    /**
     * Analyze the relationship's purpose and long-term direction
     */
    private function analyzeRelationshipPurpose(array $compositePositions, array $compositeHouses): array
    {
        $purpose = [
            'life_area_focus' => [],
            'evolutionary_goal' => '',
            'collective_contribution' => '',
            'soul_lesson' => ''
        ];
        
        // Analyze 7th house composite sun (relationship identity)
        if (isset($compositePositions['sun'])) {
            $sunHouse = $this->findPlanetHouse($compositePositions['sun']['longitude'], $compositeHouses);
            $purpose['life_area_focus'][] = $this->getHousePurpose($sunHouse);
        }
        
        // North Node indicates evolutionary direction
        if (isset($compositePositions['north_node'])) {
            $nnHouse = $this->findPlanetHouse($compositePositions['north_node']['longitude'], $compositeHouses);
            $purpose['evolutionary_goal'] = $this->getHousePurpose($nnHouse);
        }
        
        // Jupiter shows growth and expansion areas
        if (isset($compositePositions['jupiter'])) {
            $jupiterHouse = $this->findPlanetHouse($compositePositions['jupiter']['longitude'], $compositeHouses);
            $purpose['collective_contribution'] = $this->getHousePurpose($jupiterHouse);
        }
        
        // Saturn shows lessons and commitments
        if (isset($compositePositions['saturn'])) {
            $saturnHouse = $this->findPlanetHouse($compositePositions['saturn']['longitude'], $compositeHouses);
            $purpose['soul_lesson'] = $this->getHousePurpose($saturnHouse);
        }
        
        return $purpose;
    }
    
    /**
     * Find which house a planet falls into
     */
    private function findPlanetHouse(float $planetLon, array $houses): int
    {
        for ($house = 1; $house <= 12; $house++) {
            if (!isset($houses[$house]['cusp'])) continue;
            
            $houseCusp = $houses[$house]['cusp'];
            $nextHouse = ($house % 12) + 1;
            $nextCusp = $houses[$nextHouse]['cusp'] ?? ($houseCusp + 30);
            
            // Handle zodiac wraparound
            if ($nextCusp < $houseCusp) {
                $nextCusp += 360;
            }
            
            $testLon = $planetLon;
            if ($testLon < $houseCusp) {
                $testLon += 360;
            }
            
            if ($testLon >= $houseCusp && $testLon < $nextCusp) {
                return $house;
            }
        }
        
        return 1; // Default to 1st house if no match found
    }
    
    /**
     * Get relationship purpose for each house
     */
    private function getHousePurpose(int $house): string
    {
        $purposes = [
            1 => 'Developing joint identity and self-expression',
            2 => 'Building shared resources and values',
            3 => 'Improving communication and local community involvement',
            4 => 'Creating emotional security and home foundation',
            5 => 'Expressing creativity and joy together',
            6 => 'Service to others and daily life improvement',
            7 => 'Partnership development and relationship balance',
            8 => 'Transformation and shared spiritual growth',
            9 => 'Higher learning and philosophical expansion',
            10 => 'Public achievement and professional collaboration',
            11 => 'Social contribution and future visioning',
            12 => 'Spiritual service and karmic completion'
        ];
        
        return $purposes[$house] ?? 'Personal growth and understanding';
    }
    
    /**
     * Get karmic lessons from planetary combinations
     */
    private function getKarmicLesson(string $planet1, string $planet2, string $aspect): string
    {
        $lessons = [
            'saturn' => 'Learning commitment, responsibility, and maturity',
            'pluto' => 'Experiencing transformation and power dynamics',
            'north_node' => 'Moving toward evolutionary growth',
            'south_node' => 'Releasing past patterns and karma'
        ];
        
        $karmicPlanet = in_array($planet1, array_keys($lessons)) ? $planet1 : $planet2;
        return $lessons[$karmicPlanet] ?? 'Spiritual growth through relationship';
    }
    
    /**
     * Get significance of composite aspects
     */
    private function getCompositeAspectSignificance(string $planet1, string $planet2, string $aspect): string
    {
        // Highly significant for relationship identity
        $highSig = [
            ['sun', 'moon'], ['sun', 'venus'], ['sun', 'mars'], 
            ['moon', 'venus'], ['venus', 'mars'], ['sun', 'jupiter']
        ];
        
        // Moderately significant
        $mediumSig = [
            ['mercury', 'venus'], ['mars', 'jupiter'], ['sun', 'saturn'],
            ['moon', 'saturn'], ['venus', 'saturn'], ['sun', 'mercury']
        ];
        
        $pair = [$planet1, $planet2];
        sort($pair);
        
        foreach ($highSig as $sigPair) {
            sort($sigPair);
            if ($pair === $sigPair) return 'high';
        }
        
        foreach ($mediumSig as $sigPair) {
            sort($sigPair);
            if ($pair === $sigPair) return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Get zodiac sign from longitude
     */
    private function getZodiacSign(float $longitude): string
    {
        $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
                 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
        
        $signIndex = (int) floor($longitude / 30);
        return $signs[$signIndex] ?? 'Aries';
    }
    
    /**
     * Get element of zodiac sign
     */
    private function getSignElement(string $sign): string
    {
        $elements = [
            'Aries' => 'fire', 'Leo' => 'fire', 'Sagittarius' => 'fire',
            'Taurus' => 'earth', 'Virgo' => 'earth', 'Capricorn' => 'earth',
            'Gemini' => 'air', 'Libra' => 'air', 'Aquarius' => 'air',
            'Cancer' => 'water', 'Scorpio' => 'water', 'Pisces' => 'water'
        ];
        
        return $elements[$sign] ?? 'fire';
    }
    
    /**
     * Get mode of zodiac sign
     */
    private function getSignMode(string $sign): string
    {
        $modes = [
            'Aries' => 'cardinal', 'Cancer' => 'cardinal', 'Libra' => 'cardinal', 'Capricorn' => 'cardinal',
            'Taurus' => 'fixed', 'Leo' => 'fixed', 'Scorpio' => 'fixed', 'Aquarius' => 'fixed',
            'Gemini' => 'mutable', 'Virgo' => 'mutable', 'Sagittarius' => 'mutable', 'Pisces' => 'mutable'
        ];
        
        return $modes[$sign] ?? 'cardinal';
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
     * Generate complete composite chart data for visualization
     */
    public function generateCompositeChart(): array
    {
        $composite = $this->calculateComposite();
        
        return [
            'chart_type' => 'composite',
            'chart1_id' => $this->chart1->getId(),
            'chart2_id' => $this->chart2->getId(),
            'chart1_name' => $this->chart1->getName(),
            'chart2_name' => $this->chart2->getName(),
            'composite_datetime' => $composite['composite_datetime'],
            'composite_location' => $composite['composite_location'],
            'planetary_positions' => $composite['composite_positions'],
            'house_positions' => $composite['composite_houses'],
            'aspects' => $composite['composite_aspects'],
            'relationship_themes' => [
                'primary_purpose' => $composite['composite_themes']['primary_purpose'],
                'dominant_elements' => $composite['composite_themes']['dominant_elements'],
                'relationship_style' => $composite['composite_themes']['relationship_style'],
                'evolutionary_goal' => $composite['relationship_purpose']['evolutionary_goal']
            ],
            'metadata' => $composite['calculation_metadata']
        ];
    }
    
    /**
     * Get composite chart interpretation and insights
     */
    public function getCompositeInsights(): array
    {
        $composite = $this->calculateComposite();
        
        return [
            'relationship_identity' => $this->analyzeCompositeIdentity($composite['composite_positions']),
            'relationship_challenges' => $composite['composite_themes']['relationship_challenges'],
            'growth_opportunities' => $composite['composite_themes']['growth_opportunities'],
            'karmic_purpose' => $composite['relationship_purpose'],
            'elemental_balance' => $this->analyzeElementalBalance($composite['composite_positions']),
            'compatibility_factors' => $this->analyzeCompatibilityFactors($composite['composite_aspects'])
        ];
    }
    
    /**
     * Analyze the composite chart's identity (Sun/Moon/Rising combination)
     */
    private function analyzeCompositeIdentity(array $positions): array
    {
        $identity = [
            'core_expression' => 'Balanced partnership',
            'emotional_nature' => 'Supportive connection',
            'public_image' => 'Harmonious couple'
        ];
        
        if (isset($positions['sun'])) {
            $sunSign = $this->getZodiacSign($positions['sun']['longitude']);
            $identity['core_expression'] = $this->getSunSignExpression($sunSign);
        }
        
        if (isset($positions['moon'])) {
            $moonSign = $this->getZodiacSign($positions['moon']['longitude']);
            $identity['emotional_nature'] = $this->getMoonSignExpression($moonSign);
        }
        
        return $identity;
    }
    
    /**
     * Get Sun sign expression for composite
     */
    private function getSunSignExpression(string $sign): string
    {
        $expressions = [
            'Aries' => 'Dynamic, pioneering partnership',
            'Taurus' => 'Stable, sensual connection',
            'Gemini' => 'Communicative, versatile relationship',
            'Cancer' => 'Nurturing, emotionally deep bond',
            'Leo' => 'Creative, dramatic partnership',
            'Virgo' => 'Practical, service-oriented relationship',
            'Libra' => 'Harmonious, aesthetic partnership',
            'Scorpio' => 'Intense, transformative connection',
            'Sagittarius' => 'Adventurous, philosophical bond',
            'Capricorn' => 'Ambitious, traditional partnership',
            'Aquarius' => 'Innovative, friendship-based relationship',
            'Pisces' => 'Spiritual, intuitive connection'
        ];
        
        return $expressions[$sign] ?? 'Balanced partnership expression';
    }
    
    /**
     * Get Moon sign expression for composite
     */
    private function getMoonSignExpression(string $sign): string
    {
        $expressions = [
            'Aries' => 'Impulsive emotional responses',
            'Taurus' => 'Steady, comforting emotional nature',
            'Gemini' => 'Mentally stimulating emotional connection',
            'Cancer' => 'Deeply nurturing emotional bond',
            'Leo' => 'Dramatic, warm emotional expression',
            'Virgo' => 'Practical, analytical emotional approach',
            'Libra' => 'Harmonious emotional balance',
            'Scorpio' => 'Intense emotional depths',
            'Sagittarius' => 'Optimistic emotional outlook',
            'Capricorn' => 'Reserved, responsible emotional nature',
            'Aquarius' => 'Detached, unique emotional connection',
            'Pisces' => 'Psychic, compassionate emotional bond'
        ];
        
        return $expressions[$sign] ?? 'Supportive emotional connection';
    }
    
    /**
     * Analyze elemental balance in composite chart
     */
    private function analyzeElementalBalance(array $positions): array
    {
        $elements = ['fire' => 0, 'earth' => 0, 'air' => 0, 'water' => 0];
        
        foreach ($positions as $planet => $data) {
            $sign = $this->getZodiacSign($data['longitude']);
            $element = $this->getSignElement($sign);
            $elements[$element]++;
        }
        
        $total = array_sum($elements);
        $percentages = [];
        
        foreach ($elements as $element => $count) {
            $percentages[$element] = $total > 0 ? round(($count / $total) * 100) : 0;
        }
        
        return [
            'distribution' => $percentages,
            'dominant_element' => array_keys($elements, max($elements))[0] ?? 'balanced',
            'lacking_element' => array_keys($elements, min($elements))[0] ?? 'none'
        ];
    }
    
    /**
     * Analyze compatibility factors from composite aspects
     */
    private function analyzeCompatibilityFactors(array $aspects): array
    {
        $factors = [
            'harmony_score' => 0,
            'challenge_score' => 0,
            'growth_potential' => 0,
            'stability_factors' => 0
        ];
        
        foreach ($aspects as $aspect) {
            $strength = $aspect['strength'] / 100;
            $nature = $aspect['nature'];
            
            switch ($nature) {
                case 'harmonious':
                case 'flowing':
                    $factors['harmony_score'] += $strength;
                    break;
                case 'dynamic':
                case 'polarizing':
                    $factors['challenge_score'] += $strength;
                    $factors['growth_potential'] += $strength * 0.7;
                    break;
                case 'intense':
                    $factors['growth_potential'] += $strength;
                    $factors['stability_factors'] += $strength * 0.5;
                    break;
            }
        }
        
        // Normalize scores to 0-100 scale
        $totalAspects = count($aspects);
        if ($totalAspects > 0) {
            $factors['harmony_score'] = min(100, ($factors['harmony_score'] / $totalAspects) * 100);
            $factors['challenge_score'] = min(100, ($factors['challenge_score'] / $totalAspects) * 100);
            $factors['growth_potential'] = min(100, ($factors['growth_potential'] / $totalAspects) * 100);
            $factors['stability_factors'] = min(100, ($factors['stability_factors'] / $totalAspects) * 100);
        }
        
        return $factors;
    }
}