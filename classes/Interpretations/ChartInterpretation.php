<?php
declare(strict_types=1);

namespace QuantumAstrology\Interpretations;

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Interpretations\AspectPatterns;
use QuantumAstrology\Core\Logger;

class ChartInterpretation
{
    private Chart $chart;
    private array $interpretationRules;
    private array $planetarySignifications;
    private array $houseSignifications;
    
    public function __construct(Chart $chart)
    {
        $this->chart = $chart;
        $this->loadInterpretationRules();
        $this->loadSignifications();
    }
    
    /**
     * Generate complete chart interpretation
     */
    public function generateFullInterpretation(): array
    {
        try {
            $positions = $this->chart->getPlanetaryPositions();
            $houses = $this->chart->getHousePositions();
            $aspects = $this->chart->getAspects();
            
            if (!$positions || !$houses || !$aspects) {
                throw new \Exception('Chart data incomplete for interpretation');
            }
            
            // Core interpretation components
            $sunMoonRising = $this->interpretSunMoonRising($positions, $houses);
            $planetaryPlacements = $this->interpretPlanetaryPlacements($positions, $houses);
            $aspectPatterns = $this->analyzeAspectPatterns($positions, $aspects);
            $houseEmphasis = $this->analyzeHouseEmphasis($positions, $houses);
            $elementalBalance = $this->analyzeElementalBalance($positions);
            $modalBalance = $this->analyzeModalBalance($positions);
            $chartShape = $this->analyzeChartShape($positions);
            $dominantPlanets = $this->findDominantPlanets($positions, $aspects);
            
            // Synthesize overall themes
            $overallThemes = $this->synthesizeOverallThemes([
                $sunMoonRising,
                $aspectPatterns,
                $houseEmphasis,
                $elementalBalance,
                $modalBalance,
                $dominantPlanets
            ]);
            
            return [
                'chart_id' => $this->chart->getId(),
                'chart_name' => $this->chart->getName(),
                'interpretation_type' => 'full_natal',
                'core_identity' => $sunMoonRising,
                'planetary_placements' => $planetaryPlacements,
                'aspect_patterns' => $aspectPatterns,
                'house_emphasis' => $houseEmphasis,
                'elemental_balance' => $elementalBalance,
                'modal_balance' => $modalBalance,
                'chart_shape' => $chartShape,
                'dominant_planets' => $dominantPlanets,
                'overall_themes' => $overallThemes,
                'interpretation_summary' => $this->generateInterpretationSummary($overallThemes),
                'calculation_metadata' => [
                    'interpreted_at' => date('c'),
                    'interpretation_version' => '1.0',
                    'rules_applied' => count($this->interpretationRules)
                ]
            ];
            
        } catch (\Exception $e) {
            Logger::error("Chart interpretation failed", [
                'error' => $e->getMessage(),
                'chart_id' => $this->chart->getId()
            ]);
            throw $e;
        }
    }
    
    /**
     * Interpret Sun, Moon, and Rising sign (core identity)
     */
    private function interpretSunMoonRising(array $positions, array $houses): array
    {
        $coreIdentity = [
            'sun' => null,
            'moon' => null,
            'rising' => null,
            'synthesis' => ''
        ];
        
        // Sun interpretation
        if (isset($positions['sun'])) {
            $sunSign = $this->getZodiacSign($positions['sun']['longitude']);
            $sunHouse = $this->findPlanetHouse($positions['sun']['longitude'], $houses);
            
            $coreIdentity['sun'] = [
                'sign' => $sunSign,
                'house' => $sunHouse,
                'degree' => round($positions['sun']['longitude'] % 30, 1),
                'interpretation' => $this->interpretSunPlacement($sunSign, $sunHouse),
                'keywords' => $this->getSunKeywords($sunSign, $sunHouse)
            ];
        }
        
        // Moon interpretation
        if (isset($positions['moon'])) {
            $moonSign = $this->getZodiacSign($positions['moon']['longitude']);
            $moonHouse = $this->findPlanetHouse($positions['moon']['longitude'], $houses);
            
            $coreIdentity['moon'] = [
                'sign' => $moonSign,
                'house' => $moonHouse,
                'degree' => round($positions['moon']['longitude'] % 30, 1),
                'interpretation' => $this->interpretMoonPlacement($moonSign, $moonHouse),
                'keywords' => $this->getMoonKeywords($moonSign, $moonHouse)
            ];
        }
        
        // Ascendant interpretation
        if (isset($houses[1]['cusp'])) {
            $risingSign = $this->getZodiacSign($houses[1]['cusp']);
            
            $coreIdentity['rising'] = [
                'sign' => $risingSign,
                'degree' => round($houses[1]['cusp'] % 30, 1),
                'interpretation' => $this->interpretRisingSign($risingSign),
                'keywords' => $this->getRisingKeywords($risingSign)
            ];
        }
        
        // Synthesis of core identity
        $coreIdentity['synthesis'] = $this->synthesizeCoreIdentity(
            $coreIdentity['sun'], 
            $coreIdentity['moon'], 
            $coreIdentity['rising']
        );
        
        return $coreIdentity;
    }
    
    /**
     * Interpret all planetary placements
     */
    private function interpretPlanetaryPlacements(array $positions, array $houses): array
    {
        $placements = [];
        
        foreach ($positions as $planet => $data) {
            if (in_array($planet, ['sun', 'moon'])) continue; // Already covered in core identity
            
            $sign = $this->getZodiacSign($data['longitude']);
            $house = $this->findPlanetHouse($data['longitude'], $houses);
            $degree = round($data['longitude'] % 30, 1);
            
            $placements[$planet] = [
                'sign' => $sign,
                'house' => $house,
                'degree' => $degree,
                'retrograde' => ($data['longitude_speed'] ?? 0) < 0,
                'interpretation' => $this->interpretPlanetPlacement($planet, $sign, $house),
                'keywords' => $this->getPlanetKeywords($planet, $sign, $house),
                'dignity' => $this->assessPlanetaryDignity($planet, $sign)
            ];
        }
        
        return $placements;
    }
    
    /**
     * Analyze aspect patterns using AspectPatterns class
     */
    private function analyzeAspectPatterns(array $positions, array $aspects): array
    {
        $patternAnalyzer = new AspectPatterns($positions, $aspects);
        $patterns = $patternAnalyzer->detectAllPatterns();
        
        // Add interpretation context
        $patterns['interpretation'] = $this->interpretAspectPatternOverall($patterns);
        
        return $patterns;
    }
    
    /**
     * Analyze house emphasis and stelliums
     */
    private function analyzeHouseEmphasis(array $positions, array $houses): array
    {
        $houseOccupancy = array_fill(1, 12, 0);
        $houseDetails = [];
        
        foreach ($positions as $planet => $data) {
            $house = $this->findPlanetHouse($data['longitude'], $houses);
            if ($house >= 1 && $house <= 12) {
                $houseOccupancy[$house]++;
                $houseDetails[$house][] = $planet;
            }
        }
        
        // Find stelliums (3+ planets in same house)
        $stelliums = [];
        foreach ($houseOccupancy as $house => $count) {
            if ($count >= 3) {
                $stelliums[] = [
                    'house' => $house,
                    'planet_count' => $count,
                    'planets' => $houseDetails[$house],
                    'house_meaning' => $this->getHouseMeaning($house),
                    'interpretation' => $this->interpretStellium($house, $houseDetails[$house])
                ];
            }
        }
        
        // Find emphasized houses
        $maxOccupancy = max($houseOccupancy);
        $emphasizedHouses = [];
        foreach ($houseOccupancy as $house => $count) {
            if ($count > 0 && $count >= $maxOccupancy - 1) {
                $emphasizedHouses[] = [
                    'house' => $house,
                    'planet_count' => $count,
                    'planets' => $houseDetails[$house] ?? [],
                    'house_meaning' => $this->getHouseMeaning($house)
                ];
            }
        }
        
        return [
            'house_occupancy' => $houseOccupancy,
            'stelliums' => $stelliums,
            'emphasized_houses' => $emphasizedHouses,
            'empty_houses' => array_keys(array_filter($houseOccupancy, fn($count) => $count === 0)),
            'interpretation' => $this->interpretHouseEmphasis($stelliums, $emphasizedHouses)
        ];
    }
    
    /**
     * Analyze elemental balance
     */
    private function analyzeElementalBalance(array $positions): array
    {
        $elements = ['fire' => 0, 'earth' => 0, 'air' => 0, 'water' => 0];
        $planetWeights = [
            'sun' => 3, 'moon' => 3, 'mercury' => 2, 'venus' => 2, 'mars' => 2,
            'jupiter' => 2, 'saturn' => 2, 'uranus' => 1, 'neptune' => 1, 'pluto' => 1
        ];
        
        foreach ($positions as $planet => $data) {
            $sign = $this->getZodiacSign($data['longitude']);
            $element = $this->getSignElement($sign);
            $weight = $planetWeights[$planet] ?? 1;
            $elements[$element] += $weight;
        }
        
        $total = array_sum($elements);
        $percentages = [];
        foreach ($elements as $element => $count) {
            $percentages[$element] = $total > 0 ? round(($count / $total) * 100) : 0;
        }
        
        arsort($percentages);
        $dominant = array_keys($percentages)[0] ?? 'balanced';
        $lacking = array_keys($percentages)[3] ?? 'none';
        
        return [
            'raw_counts' => $elements,
            'percentages' => $percentages,
            'dominant_element' => $dominant,
            'lacking_element' => $lacking,
            'balance_type' => $this->determineElementalBalanceType($percentages),
            'interpretation' => $this->interpretElementalBalance($percentages, $dominant, $lacking)
        ];
    }
    
    /**
     * Analyze modal balance (Cardinal, Fixed, Mutable)
     */
    private function analyzeModalBalance(array $positions): array
    {
        $modes = ['cardinal' => 0, 'fixed' => 0, 'mutable' => 0];
        $planetWeights = [
            'sun' => 3, 'moon' => 3, 'mercury' => 2, 'venus' => 2, 'mars' => 2,
            'jupiter' => 2, 'saturn' => 2, 'uranus' => 1, 'neptune' => 1, 'pluto' => 1
        ];
        
        foreach ($positions as $planet => $data) {
            $sign = $this->getZodiacSign($data['longitude']);
            $mode = $this->getSignMode($sign);
            $weight = $planetWeights[$planet] ?? 1;
            $modes[$mode] += $weight;
        }
        
        $total = array_sum($modes);
        $percentages = [];
        foreach ($modes as $mode => $count) {
            $percentages[$mode] = $total > 0 ? round(($count / $total) * 100) : 0;
        }
        
        arsort($percentages);
        $dominant = array_keys($percentages)[0] ?? 'balanced';
        
        return [
            'raw_counts' => $modes,
            'percentages' => $percentages,
            'dominant_mode' => $dominant,
            'balance_type' => $this->determineModalBalanceType($percentages),
            'interpretation' => $this->interpretModalBalance($percentages, $dominant)
        ];
    }
    
    /**
     * Analyze chart shape (Jones Patterns)
     */
    private function analyzeChartShape(array $positions): array
    {
        $longitudes = array_column($positions, 'longitude');
        sort($longitudes);
        
        $shape = $this->determineChartShape($longitudes);
        
        return [
            'shape_type' => $shape,
            'interpretation' => $this->interpretChartShape($shape),
            'keywords' => $this->getChartShapeKeywords($shape)
        ];
    }
    
    /**
     * Find dominant planets based on aspects, dignity, and house placement
     */
    private function findDominantPlanets(array $positions, array $aspects): array
    {
        $planetScores = [];
        
        foreach ($positions as $planet => $data) {
            $score = 0;
            
            // Aspect count weight
            $aspectCount = $this->countPlanetAspects($planet, $aspects);
            $score += $aspectCount * 2;
            
            // Dignity weight
            $sign = $this->getZodiacSign($data['longitude']);
            $dignity = $this->assessPlanetaryDignity($planet, $sign);
            $score += $this->getDignityScore($dignity);
            
            // Angular house weight
            $house = $this->findPlanetHouse($data['longitude'], $this->chart->getHousePositions() ?? []);
            if (in_array($house, [1, 4, 7, 10])) {
                $score += 3;
            }
            
            $planetScores[$planet] = $score;
        }
        
        arsort($planetScores);
        $topPlanets = array_slice(array_keys($planetScores), 0, 3);
        
        return [
            'planet_scores' => $planetScores,
            'dominant_planets' => $topPlanets,
            'interpretation' => $this->interpretDominantPlanets($topPlanets)
        ];
    }
    
    /**
     * Synthesize overall themes from all components
     */
    private function synthesizeOverallThemes(array $components): array
    {
        $themes = [];
        
        // Extract keywords from all components
        $allKeywords = [];
        foreach ($components as $component) {
            if (isset($component['keywords'])) {
                $allKeywords = array_merge($allKeywords, $component['keywords']);
            }
        }
        
        // Count keyword frequency
        $keywordCounts = array_count_values($allKeywords);
        arsort($keywordCounts);
        
        $themes['primary_themes'] = array_slice(array_keys($keywordCounts), 0, 5);
        $themes['life_purpose'] = $this->determineLifePurpose($components);
        $themes['major_challenges'] = $this->identifyMajorChallenges($components);
        $themes['natural_talents'] = $this->identifyNaturalTalents($components);
        $themes['growth_areas'] = $this->identifyGrowthAreas($components);
        
        return $themes;
    }
    
    /**
     * Generate interpretation summary
     */
    private function generateInterpretationSummary(array $themes): string
    {
        $summary = "This chart reveals a person whose ";
        
        if (!empty($themes['life_purpose'])) {
            $summary .= "primary life purpose centers around " . strtolower($themes['life_purpose']) . ". ";
        }
        
        if (!empty($themes['natural_talents'])) {
            $summary .= "Natural talents include " . strtolower(implode(', ', array_slice($themes['natural_talents'], 0, 3))) . ". ";
        }
        
        if (!empty($themes['major_challenges'])) {
            $summary .= "Key growth areas involve " . strtolower(implode(' and ', array_slice($themes['major_challenges'], 0, 2))) . ". ";
        }
        
        $summary .= "The overall themes of " . strtolower(implode(', ', array_slice($themes['primary_themes'], 0, 3))) . " dominate the chart expression.";
        
        return $summary;
    }
    
    // Utility methods
    
    private function getZodiacSign(float $longitude): string
    {
        $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
                 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
        
        $signIndex = (int) floor($longitude / 30);
        return $signs[$signIndex] ?? 'Aries';
    }
    
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
    
    private function getSignMode(string $sign): string
    {
        $modes = [
            'Aries' => 'cardinal', 'Cancer' => 'cardinal', 'Libra' => 'cardinal', 'Capricorn' => 'cardinal',
            'Taurus' => 'fixed', 'Leo' => 'fixed', 'Scorpio' => 'fixed', 'Aquarius' => 'fixed',
            'Gemini' => 'mutable', 'Virgo' => 'mutable', 'Sagittarius' => 'mutable', 'Pisces' => 'mutable'
        ];
        
        return $modes[$sign] ?? 'cardinal';
    }
    
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
    
    // Load interpretation rules and significations
    private function loadInterpretationRules(): void
    {
        $this->interpretationRules = [
            'planetary_rulerships' => $this->loadPlanetaryRulerships(),
            'aspect_meanings' => $this->loadAspectMeanings(),
            'house_meanings' => $this->loadHouseMeanings(),
            'sign_meanings' => $this->loadSignMeanings()
        ];
    }
    
    private function loadSignifications(): void
    {
        $this->planetarySignifications = $this->loadPlanetarySignifications();
        $this->houseSignifications = $this->loadHouseSignifications();
    }
    
    // Placeholder methods for detailed implementations
    private function interpretSunPlacement(string $sign, int $house): string { return "Sun in {$sign} in house {$house}"; }
    private function interpretMoonPlacement(string $sign, int $house): string { return "Moon in {$sign} in house {$house}"; }
    private function interpretRisingSign(string $sign): string { return "{$sign} rising"; }
    private function interpretPlanetPlacement(string $planet, string $sign, int $house): string { return "{$planet} in {$sign} in house {$house}"; }
    private function synthesizeCoreIdentity(?array $sun, ?array $moon, ?array $rising): string { return "Core identity synthesis"; }
    private function getSunKeywords(string $sign, int $house): array { return ['identity', 'purpose', 'ego']; }
    private function getMoonKeywords(string $sign, int $house): array { return ['emotions', 'instincts', 'needs']; }
    private function getRisingKeywords(string $sign): array { return ['appearance', 'approach', 'first impression']; }
    private function getPlanetKeywords(string $planet, string $sign, int $house): array { return ['keyword1', 'keyword2']; }
    private function assessPlanetaryDignity(string $planet, string $sign): string { return 'neutral'; }
    private function interpretAspectPatternOverall(array $patterns): string { return 'Pattern interpretation'; }
    private function getHouseMeaning(int $house): string { return "House {$house} meaning"; }
    private function interpretStellium(int $house, array $planets): string { return 'Stellium interpretation'; }
    private function interpretHouseEmphasis(array $stelliums, array $emphasized): string { return 'House emphasis interpretation'; }
    private function determineElementalBalanceType(array $percentages): string { return 'balanced'; }
    private function interpretElementalBalance(array $percentages, string $dominant, string $lacking): string { return 'Elemental balance interpretation'; }
    private function determineModalBalanceType(array $percentages): string { return 'balanced'; }
    private function interpretModalBalance(array $percentages, string $dominant): string { return 'Modal balance interpretation'; }
    private function determineChartShape(array $longitudes): string { return 'splash'; }
    private function interpretChartShape(string $shape): string { return 'Chart shape interpretation'; }
    private function getChartShapeKeywords(string $shape): array { return ['versatile', 'scattered']; }
    private function countPlanetAspects(string $planet, array $aspects): int { return 0; }
    private function getDignityScore(string $dignity): int { return 0; }
    private function interpretDominantPlanets(array $planets): string { return 'Dominant planets interpretation'; }
    private function determineLifepurpose(array $components): string { return 'Personal growth and expression'; }
    private function identifyMajorChallenges(array $components): array { return ['self-understanding', 'balance']; }
    private function identifyNaturalTalents(array $components): array { return ['creativity', 'communication']; }
    private function identifyGrowthAreas(array $components): array { return ['patience', 'discipline']; }
    
    // Configuration loaders
    private function loadPlanetaryRulerships(): array { return []; }
    private function loadAspectMeanings(): array { return []; }
    private function loadHouseMeanings(): array { return []; }
    private function loadSignMeanings(): array { return []; }
    private function loadPlanetarySignifications(): array { return []; }
    private function loadHouseSignifications(): array { return []; }
}