<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\Logger;
use DateTime;

class Synastry
{
    private Chart $chart1;
    private Chart $chart2;
    
    public function __construct(Chart $chart1, Chart $chart2)
    {
        $this->chart1 = $chart1;
        $this->chart2 = $chart2;
    }
    
    /**
     * Calculate complete synastry analysis between two charts
     */
    public function calculateSynastry(): array
    {
        try {
            $positions1 = $this->chart1->getPlanetaryPositions();
            $positions2 = $this->chart2->getPlanetaryPositions();
            
            if (!$positions1 || !$positions2) {
                throw new \Exception('Both charts must have planetary positions calculated');
            }
            
            // Calculate cross-aspects between the two charts
            $synastryAspects = $this->calculateSynastryAspects($positions1, $positions2);
            
            // Calculate house overlays (where person 2's planets fall in person 1's houses)
            $houseOverlays1 = $this->calculateHouseOverlays($positions2, $this->chart1->getHousePositions());
            $houseOverlays2 = $this->calculateHouseOverlays($positions1, $this->chart2->getHousePositions());
            
            // Calculate compatibility scores
            $compatibilityScores = $this->calculateCompatibilityScores($synastryAspects);
            
            // Analyze relationship dynamics
            $relationshipDynamics = $this->analyzeRelationshipDynamics($synastryAspects, $houseOverlays1, $houseOverlays2);
            
            return [
                'synastry_type' => 'relationship_comparison',
                'chart1_id' => $this->chart1->getId(),
                'chart2_id' => $this->chart2->getId(),
                'chart1_name' => $this->chart1->getName(),
                'chart2_name' => $this->chart2->getName(),
                'chart1_positions' => $positions1,
                'chart2_positions' => $positions2,
                'synastry_aspects' => $synastryAspects,
                'house_overlays_1_to_2' => $houseOverlays1, // Chart2 planets in Chart1 houses
                'house_overlays_2_to_1' => $houseOverlays2, // Chart1 planets in Chart2 houses
                'compatibility_scores' => $compatibilityScores,
                'relationship_dynamics' => $relationshipDynamics,
                'calculation_metadata' => [
                    'calculated_at' => date('c'),
                    'calculation_method' => 'synastry_analysis',
                    'total_aspects' => count($synastryAspects)
                ]
            ];
            
        } catch (\Exception $e) {
            Logger::error("Synastry calculation failed", [
                'error' => $e->getMessage(),
                'chart1_id' => $this->chart1->getId(),
                'chart2_id' => $this->chart2->getId()
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate aspects between planets in two different charts
     */
    private function calculateSynastryAspects(array $positions1, array $positions2): array
    {
        $aspects = [];
        $aspectDefinitions = [
            'conjunction' => ['angle' => 0, 'orb' => 8, 'nature' => 'harmonious'],
            'sextile' => ['angle' => 60, 'orb' => 6, 'nature' => 'harmonious'],
            'square' => ['angle' => 90, 'orb' => 8, 'nature' => 'challenging'],
            'trine' => ['angle' => 120, 'orb' => 8, 'nature' => 'harmonious'],
            'opposition' => ['angle' => 180, 'orb' => 8, 'nature' => 'challenging'],
            'quincunx' => ['angle' => 150, 'orb' => 3, 'nature' => 'adjusting'],
            'semisextile' => ['angle' => 30, 'orb' => 2, 'nature' => 'minor_harmonious'],
            'sesquiquadrate' => ['angle' => 135, 'orb' => 2, 'nature' => 'minor_challenging']
        ];
        
        foreach ($positions1 as $planet1 => $data1) {
            foreach ($positions2 as $planet2 => $data2) {
                $lon1 = $data1['longitude'];
                $lon2 = $data2['longitude'];
                
                $angle = abs($lon1 - $lon2);
                if ($angle > 180) {
                    $angle = 360 - $angle;
                }
                
                foreach ($aspectDefinitions as $aspectName => $aspectData) {
                    $difference = abs($angle - $aspectData['angle']);
                    
                    if ($difference <= $aspectData['orb']) {
                        $aspects[] = [
                            'person1_planet' => $planet1,
                            'person2_planet' => $planet2,
                            'aspect' => $aspectName,
                            'angle' => $angle,
                            'orb' => $difference,
                            'exact_angle' => $aspectData['angle'],
                            'nature' => $aspectData['nature'],
                            'strength' => $this->calculateAspectStrength($difference, $aspectData['orb']),
                            'person1_position' => $lon1,
                            'person2_position' => $lon2,
                            'relationship_significance' => $this->getRelationshipSignificance($planet1, $planet2, $aspectName)
                        ];
                        break;
                    }
                }
            }
        }
        
        // Sort by relationship significance and strength
        usort($aspects, function($a, $b) {
            $sigOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $aSig = $sigOrder[$a['relationship_significance']] ?? 0;
            $bSig = $sigOrder[$b['relationship_significance']] ?? 0;
            
            if ($aSig !== $bSig) {
                return $bSig <=> $aSig; // Higher significance first
            }
            
            return $a['orb'] <=> $b['orb']; // Then by exactness
        });
        
        return $aspects;
    }
    
    /**
     * Calculate where one person's planets fall in another person's houses
     */
    private function calculateHouseOverlays(array $planetPositions, ?array $housePositions): array
    {
        if (!$housePositions) {
            return [];
        }
        
        $overlays = [];
        
        foreach ($planetPositions as $planet => $data) {
            $planetLon = $data['longitude'];
            
            // Find which house this planet falls into
            for ($house = 1; $house <= 12; $house++) {
                if (!isset($housePositions[$house]['cusp'])) continue;
                
                $houseCusp = $housePositions[$house]['cusp'];
                $nextHouse = ($house % 12) + 1;
                $nextCusp = $housePositions[$nextHouse]['cusp'] ?? ($houseCusp + 30);
                
                // Handle zodiac wraparound
                if ($nextCusp < $houseCusp) {
                    $nextCusp += 360;
                }
                
                $testLon = $planetLon;
                if ($testLon < $houseCusp) {
                    $testLon += 360;
                }
                
                if ($testLon >= $houseCusp && $testLon < $nextCusp) {
                    $overlays[] = [
                        'planet' => $planet,
                        'house' => $house,
                        'planet_longitude' => $planetLon,
                        'house_cusp' => $houseCusp,
                        'house_meaning' => $this->getHouseMeaning($house),
                        'overlay_significance' => $this->getOverlaySignificance($planet, $house)
                    ];
                    break;
                }
            }
        }
        
        return $overlays;
    }
    
    /**
     * Calculate compatibility scores based on synastry aspects
     */
    private function calculateCompatibilityScores(array $synastryAspects): array
    {
        $scores = [
            'overall' => 0,
            'romantic' => 0,
            'emotional' => 0,
            'intellectual' => 0,
            'sexual' => 0,
            'spiritual' => 0,
            'challenging_factors' => 0,
            'harmonious_factors' => 0
        ];
        
        $aspectCounts = [
            'harmonious' => 0,
            'challenging' => 0,
            'adjusting' => 0,
            'minor_harmonious' => 0,
            'minor_challenging' => 0
        ];
        
        foreach ($synastryAspects as $aspect) {
            $strength = $aspect['strength'] / 100; // Convert to 0-1 scale
            $nature = $aspect['nature'];
            $planet1 = $aspect['person1_planet'];
            $planet2 = $aspect['person2_planet'];
            $significance = $aspect['relationship_significance'];
            
            // Weight by significance
            $significanceMultiplier = match($significance) {
                'high' => 1.5,
                'medium' => 1.0,
                'low' => 0.5,
                default => 0.7
            };
            
            $weightedStrength = $strength * $significanceMultiplier;
            
            // Count aspect types
            $aspectCounts[$nature] = ($aspectCounts[$nature] ?? 0) + 1;
            
            // Add to category scores
            if (in_array($nature, ['harmonious', 'minor_harmonious'])) {
                $scores['harmonious_factors'] += $weightedStrength;
            } else {
                $scores['challenging_factors'] += $weightedStrength;
            }
            
            // Specific relationship area scores
            $this->addCategoryScores($scores, $planet1, $planet2, $weightedStrength, $nature);
        }
        
        // Calculate overall score (0-100 scale)
        $totalAspects = count($synastryAspects);
        if ($totalAspects > 0) {
            $harmoniousRatio = $scores['harmonious_factors'] / max(1, $totalAspects);
            $challengingRatio = $scores['challenging_factors'] / max(1, $totalAspects);
            
            // Overall compatibility score
            $scores['overall'] = max(0, min(100, 
                ($harmoniousRatio * 80) - ($challengingRatio * 30) + 20
            ));
            
            // Normalize individual scores to 0-100 scale
            foreach (['romantic', 'emotional', 'intellectual', 'sexual', 'spiritual'] as $category) {
                $scores[$category] = max(0, min(100, $scores[$category] * 10));
            }
        }
        
        $scores['aspect_distribution'] = $aspectCounts;
        $scores['total_aspects'] = $totalAspects;
        
        return $scores;
    }
    
    /**
     * Add scores to specific relationship categories
     */
    private function addCategoryScores(array &$scores, string $planet1, string $planet2, float $strength, string $nature): void
    {
        $multiplier = match($nature) {
            'harmonious', 'minor_harmonious' => 1,
            'challenging', 'minor_challenging' => -0.5,
            'adjusting' => 0.2,
            default => 0.5
        };
        
        $adjustedStrength = $strength * $multiplier;
        
        // Romantic connections
        if (in_array($planet1, ['sun', 'moon', 'venus']) && in_array($planet2, ['sun', 'moon', 'venus'])) {
            $scores['romantic'] += $adjustedStrength * 1.5;
        }
        if (($planet1 === 'mars' && $planet2 === 'venus') || ($planet1 === 'venus' && $planet2 === 'mars')) {
            $scores['romantic'] += $adjustedStrength * 2;
            $scores['sexual'] += $adjustedStrength * 1.5;
        }
        
        // Emotional connections
        if (in_array($planet1, ['moon', 'venus', 'neptune']) || in_array($planet2, ['moon', 'venus', 'neptune'])) {
            $scores['emotional'] += $adjustedStrength;
        }
        
        // Intellectual connections
        if (in_array($planet1, ['mercury', 'jupiter', 'uranus']) || in_array($planet2, ['mercury', 'jupiter', 'uranus'])) {
            $scores['intellectual'] += $adjustedStrength;
        }
        
        // Sexual/passionate connections
        if (in_array($planet1, ['mars', 'pluto']) || in_array($planet2, ['mars', 'pluto'])) {
            $scores['sexual'] += $adjustedStrength * 0.8;
        }
        
        // Spiritual connections
        if (in_array($planet1, ['jupiter', 'neptune', 'pluto']) || in_array($planet2, ['jupiter', 'neptune', 'pluto'])) {
            $scores['spiritual'] += $adjustedStrength * 0.7;
        }
    }
    
    /**
     * Analyze relationship dynamics and patterns
     */
    private function analyzeRelationshipDynamics(array $synastryAspects, array $houseOverlays1, array $houseOverlays2): array
    {
        $dynamics = [
            'power_balance' => 'balanced',
            'communication_style' => 'harmonious',
            'emotional_connection' => 'moderate',
            'conflict_areas' => [],
            'strength_areas' => [],
            'karmic_connections' => [],
            'dominant_themes' => []
        ];
        
        $planetCounts = [
            'chart1_aspects' => 0,
            'chart2_aspects' => 0
        ];
        
        $conflictIndicators = 0;
        $harmoniousIndicators = 0;
        
        foreach ($synastryAspects as $aspect) {
            $planet1 = $aspect['person1_planet'];
            $planet2 = $aspect['person2_planet'];
            $aspectType = $aspect['aspect'];
            $nature = $aspect['nature'];
            
            // Track aspect distribution
            if (in_array($planet1, ['sun', 'mars', 'jupiter', 'saturn'])) {
                $planetCounts['chart1_aspects']++;
            }
            if (in_array($planet2, ['sun', 'mars', 'jupiter', 'saturn'])) {
                $planetCounts['chart2_aspects']++;
            }
            
            // Identify key dynamics
            if ($nature === 'challenging') {
                $conflictIndicators++;
                if ($planet1 === 'mars' || $planet2 === 'mars') {
                    $dynamics['conflict_areas'][] = 'Energy and action conflicts';
                }
                if ($planet1 === 'saturn' || $planet2 === 'saturn') {
                    $dynamics['conflict_areas'][] = 'Authority and responsibility issues';
                }
            } else {
                $harmoniousIndicators++;
                if (($planet1 === 'sun' && $planet2 === 'moon') || ($planet1 === 'moon' && $planet2 === 'sun')) {
                    $dynamics['strength_areas'][] = 'Natural masculine/feminine balance';
                }
                if ($planet1 === 'venus' || $planet2 === 'venus') {
                    $dynamics['strength_areas'][] = 'Love and affection flow';
                }
            }
            
            // Karmic indicators
            if (in_array($planet1, ['saturn', 'north_node', 'south_node', 'pluto']) || 
                in_array($planet2, ['saturn', 'north_node', 'south_node', 'pluto'])) {
                $dynamics['karmic_connections'][] = [
                    'planets' => [$planet1, $planet2],
                    'aspect' => $aspectType,
                    'significance' => 'Past life or karmic connection indicated'
                ];
            }
        }
        
        // Determine power balance
        if (abs($planetCounts['chart1_aspects'] - $planetCounts['chart2_aspects']) > 3) {
            $dynamics['power_balance'] = $planetCounts['chart1_aspects'] > $planetCounts['chart2_aspects'] 
                ? 'person1_dominant' : 'person2_dominant';
        }
        
        // Communication and emotional patterns
        if ($conflictIndicators > $harmoniousIndicators * 1.5) {
            $dynamics['communication_style'] = 'challenging';
            $dynamics['emotional_connection'] = 'intense_but_difficult';
        } elseif ($harmoniousIndicators > $conflictIndicators * 2) {
            $dynamics['communication_style'] = 'easy_flowing';
            $dynamics['emotional_connection'] = 'naturally_supportive';
        }
        
        // Dominant themes based on most common planets
        $planetFrequency = [];
        foreach ($synastryAspects as $aspect) {
            $planetFrequency[$aspect['person1_planet']] = ($planetFrequency[$aspect['person1_planet']] ?? 0) + 1;
            $planetFrequency[$aspect['person2_planet']] = ($planetFrequency[$aspect['person2_planet']] ?? 0) + 1;
        }
        
        arsort($planetFrequency);
        $topPlanets = array_slice(array_keys($planetFrequency), 0, 3);
        
        foreach ($topPlanets as $planet) {
            $dynamics['dominant_themes'][] = $this->getPlanetaryTheme($planet);
        }
        
        return $dynamics;
    }
    
    /**
     * Get relationship significance for planet combinations
     */
    private function getRelationshipSignificance(string $planet1, string $planet2): string
    {
        // Highly significant connections
        $highSignificance = [
            ['sun', 'moon'], ['sun', 'venus'], ['sun', 'mars'], ['sun', 'jupiter'],
            ['moon', 'venus'], ['moon', 'mars'], ['venus', 'mars'],
            ['sun', 'sun'], ['moon', 'moon'], ['venus', 'venus']
        ];
        
        // Moderately significant
        $mediumSignificance = [
            ['mercury', 'mercury'], ['mars', 'mars'], ['jupiter', 'jupiter'],
            ['sun', 'mercury'], ['moon', 'mercury'], ['venus', 'mercury'],
            ['sun', 'saturn'], ['moon', 'saturn']
        ];
        
        $pair = [$planet1, $planet2];
        sort($pair);
        
        foreach ($highSignificance as $sigPair) {
            sort($sigPair);
            if ($pair === $sigPair) return 'high';
        }
        
        foreach ($mediumSignificance as $sigPair) {
            sort($sigPair);
            if ($pair === $sigPair) return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Get house overlay significance
     */
    private function getOverlaySignificance(string $planet, int $house): string
    {
        $significantOverlays = [
            'sun' => [1, 5, 7, 10],
            'moon' => [1, 4, 7, 8],
            'venus' => [1, 2, 5, 7, 8],
            'mars' => [1, 5, 7, 8, 10],
            'jupiter' => [1, 5, 9, 10, 11],
            'saturn' => [1, 7, 8, 10]
        ];
        
        if (isset($significantOverlays[$planet]) && in_array($house, $significantOverlays[$planet])) {
            return 'high';
        }
        
        return in_array($house, [1, 5, 7, 8]) ? 'medium' : 'low';
    }
    
    /**
     * Get house meanings for overlays
     */
    private function getHouseMeaning(int $house): string
    {
        $houseMeanings = [
            1 => 'Identity and first impressions',
            2 => 'Values and resources',
            3 => 'Communication and daily interaction',
            4 => 'Home and emotional foundation',
            5 => 'Romance and creativity',
            6 => 'Daily routines and service',
            7 => 'Partnership and marriage',
            8 => 'Intimacy and transformation',
            9 => 'Philosophy and higher learning',
            10 => 'Career and reputation',
            11 => 'Friendship and group activities',
            12 => 'Spirituality and hidden matters'
        ];
        
        return $houseMeanings[$house] ?? 'Unknown house';
    }
    
    /**
     * Get planetary themes for relationship dynamics
     */
    private function getPlanetaryTheme(string $planet): string
    {
        $themes = [
            'sun' => 'Identity and ego expression',
            'moon' => 'Emotional needs and nurturing',
            'mercury' => 'Communication and mental connection',
            'venus' => 'Love, beauty, and harmony',
            'mars' => 'Passion, action, and conflict',
            'jupiter' => 'Growth, expansion, and optimism',
            'saturn' => 'Commitment, responsibility, and structure',
            'uranus' => 'Innovation, freedom, and unpredictability',
            'neptune' => 'Spirituality, dreams, and idealization',
            'pluto' => 'Transformation, power, and intensity'
        ];
        
        return $themes[$planet] ?? 'Unknown planetary influence';
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
     * Generate complete synastry chart data for visualization
     */
    public function generateSynastryChart(): array
    {
        $synastry = $this->calculateSynastry();
        
        return [
            'chart_type' => 'synastry',
            'chart1_id' => $this->chart1->getId(),
            'chart2_id' => $this->chart2->getId(),
            'chart1_name' => $this->chart1->getName(),
            'chart2_name' => $this->chart2->getName(),
            'inner_wheel' => $synastry['chart1_positions'], // Person 1 on inner wheel
            'outer_wheel' => $synastry['chart2_positions'], // Person 2 on outer wheel
            'houses' => $this->chart1->getHousePositions(), // Use person 1's houses
            'synastry_aspects' => $synastry['synastry_aspects'],
            'compatibility_summary' => [
                'overall_score' => $synastry['compatibility_scores']['overall'],
                'top_strengths' => array_slice($synastry['relationship_dynamics']['strength_areas'], 0, 3),
                'main_challenges' => array_slice($synastry['relationship_dynamics']['conflict_areas'], 0, 3),
                'dominant_themes' => $synastry['relationship_dynamics']['dominant_themes']
            ],
            'metadata' => $synastry['calculation_metadata']
        ];
    }
    
    /**
     * Get synastry insights and interpretation
     */
    public function getSynastryInsights(): array
    {
        $synastry = $this->calculateSynastry();
        $scores = $synastry['compatibility_scores'];
        $dynamics = $synastry['relationship_dynamics'];
        
        $insights = [
            'compatibility_level' => $this->getCompatibilityLevel($scores['overall']),
            'relationship_type' => $this->determineRelationshipType($synastry['synastry_aspects'], $dynamics),
            'key_strengths' => $dynamics['strength_areas'],
            'potential_challenges' => $dynamics['conflict_areas'],
            'advice' => $this->generateRelationshipAdvice($scores, $dynamics),
            'long_term_potential' => $this->assessLongTermPotential($synastry['synastry_aspects'])
        ];
        
        return $insights;
    }
    
    /**
     * Determine compatibility level from overall score
     */
    private function getCompatibilityLevel(float $score): string
    {
        if ($score >= 80) return 'Excellent';
        if ($score >= 65) return 'Very Good';
        if ($score >= 50) return 'Good';
        if ($score >= 35) return 'Moderate';
        if ($score >= 20) return 'Challenging';
        return 'Difficult';
    }
    
    /**
     * Determine most likely relationship type
     */
    private function determineRelationshipType(array $aspects, array $dynamics): string
    {
        $romanticScore = 0;
        $friendshipScore = 0;
        $businessScore = 0;
        
        foreach ($aspects as $aspect) {
            if ($aspect['relationship_significance'] === 'high') {
                if (in_array($aspect['person1_planet'], ['venus', 'mars']) || 
                    in_array($aspect['person2_planet'], ['venus', 'mars'])) {
                    $romanticScore += $aspect['nature'] === 'harmonious' ? 2 : -1;
                }
                if (in_array($aspect['person1_planet'], ['mercury', 'jupiter']) || 
                    in_array($aspect['person2_planet'], ['mercury', 'jupiter'])) {
                    $friendshipScore += 1;
                }
                if (in_array($aspect['person1_planet'], ['saturn', 'mars']) || 
                    in_array($aspect['person2_planet'], ['saturn', 'mars'])) {
                    $businessScore += 1;
                }
            }
        }
        
        if ($romanticScore > $friendshipScore && $romanticScore > $businessScore) {
            return 'Romantic Partnership';
        } elseif ($businessScore > $friendshipScore) {
            return 'Business/Professional';
        } else {
            return 'Friendship/Platonic';
        }
    }
    
    /**
     * Generate relationship advice based on analysis
     */
    private function generateRelationshipAdvice(array $scores, array $dynamics): array
    {
        $advice = [];
        
        if ($scores['overall'] < 40) {
            $advice[] = 'This relationship may require significant effort and understanding from both parties.';
        }
        
        if ($dynamics['communication_style'] === 'challenging') {
            $advice[] = 'Focus on improving communication patterns and finding common ground.';
        }
        
        if (count($dynamics['conflict_areas']) > 3) {
            $advice[] = 'Work together to address the main areas of tension constructively.';
        }
        
        if (count($dynamics['strength_areas']) > 0) {
            $advice[] = 'Build on your natural strengths: ' . implode(', ', array_slice($dynamics['strength_areas'], 0, 2));
        }
        
        if (empty($advice)) {
            $advice[] = 'This appears to be a well-balanced relationship with good potential.';
        }
        
        return $advice;
    }
    
    /**
     * Assess long-term relationship potential
     */
    private function assessLongTermPotential(array $aspects): string
    {
        $stabilityScore = 0;
        $growthScore = 0;
        
        foreach ($aspects as $aspect) {
            if (in_array($aspect['person1_planet'], ['saturn', 'jupiter']) || 
                in_array($aspect['person2_planet'], ['saturn', 'jupiter'])) {
                if ($aspect['nature'] === 'harmonious') {
                    $stabilityScore += 2;
                    $growthScore += 1;
                } else {
                    $stabilityScore -= 1;
                }
            }
            
            if ($aspect['person1_planet'] === 'north_node' || $aspect['person2_planet'] === 'north_node') {
                $growthScore += 2;
            }
        }
        
        if ($stabilityScore >= 4 && $growthScore >= 2) {
            return 'Excellent long-term potential';
        } elseif ($stabilityScore >= 2 || $growthScore >= 2) {
            return 'Good long-term potential';
        } elseif ($stabilityScore >= 0) {
            return 'Moderate long-term potential';
        } else {
            return 'Long-term challenges likely';
        }
    }
}