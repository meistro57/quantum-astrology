<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

class ChartWheel
{
    private int $size;
    private int $centerX;
    private int $centerY;
    private array $colors;
    private array $planetSymbols;
    private array $signSymbols;

    public function __construct(int $size = 400)
    {
        $this->size = $size;
        $this->centerX = $size / 2;
        $this->centerY = $size / 2;
        
        $this->colors = [
            'background' => '#0a0a0f',
            'wheel' => '#4A90E2',
            'houses' => '#666666',
            'signs' => '#FFD700',
            'planets' => '#FFFFFF',
            'aspects' => '#8b5cf6'
        ];

        $this->planetSymbols = [
            'sun' => '☉',
            'moon' => '☽',
            'mercury' => '☿',
            'venus' => '♀',
            'mars' => '♂',
            'jupiter' => '♃',
            'saturn' => '♄',
            'uranus' => '♅',
            'neptune' => '♆',
            'pluto' => '♇',
            'north_node' => '☊',
            'south_node' => '☋',
            'chiron' => '⚷',
            'lilith' => '⚸'
        ];

        $this->signSymbols = [
            'Aries' => '♈', 'Taurus' => '♉', 'Gemini' => '♊', 'Cancer' => '♋',
            'Leo' => '♌', 'Virgo' => '♍', 'Libra' => '♎', 'Scorpio' => '♏',
            'Sagittarius' => '♐', 'Capricorn' => '♑', 'Aquarius' => '♒', 'Pisces' => '♓'
        ];
    }

    public function generateWheel(array $planetaryPositions, array $housePositions = [], array $aspects = []): string
    {
        $svg = $this->createSVGContainer();
        $svg .= $this->drawBackground();
        $svg .= $this->drawZodiacCircle();
        $svg .= $this->drawHouseDivisions($housePositions);
        $svg .= $this->drawPlanets($planetaryPositions);
        $svg .= $this->drawAspects($planetaryPositions, $aspects);
        $svg .= '</svg>';

        return $svg;
    }

    private function createSVGContainer(): string
    {
        return sprintf(
            '<svg width="%d" height="%d" viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg">',
            $this->size, $this->size, $this->size, $this->size
        );
    }

    private function drawBackground(): string
    {
        return sprintf(
            '<rect width="%d" height="%d" fill="%s"/>',
            $this->size, $this->size, $this->colors['background']
        );
    }

    private function drawZodiacCircle(): string
    {
        $svg = '';
        $outerRadius = $this->size * 0.45;
        $innerRadius = $this->size * 0.35;
        $signRadius = $this->size * 0.40;

        // Draw outer circle
        $svg .= sprintf(
            '<circle cx="%d" cy="%d" r="%f" fill="none" stroke="%s" stroke-width="2"/>',
            $this->centerX, $this->centerY, $outerRadius, $this->colors['wheel']
        );

        // Draw inner circle
        $svg .= sprintf(
            '<circle cx="%d" cy="%d" r="%f" fill="none" stroke="%s" stroke-width="2"/>',
            $this->centerX, $this->centerY, $innerRadius, $this->colors['wheel']
        );

        // Draw zodiac signs
        $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
                 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];

        for ($i = 0; $i < 12; $i++) {
            $angle = ($i * 30) - 90; // Start from 0° Aries at top
            $radians = deg2rad($angle + 15); // Center of each sign
            
            // Sign division lines
            $lineRadians = deg2rad($angle);
            $x1 = $this->centerX + $innerRadius * cos($lineRadians);
            $y1 = $this->centerY + $innerRadius * sin($lineRadians);
            $x2 = $this->centerX + $outerRadius * cos($lineRadians);
            $y2 = $this->centerY + $outerRadius * sin($lineRadians);
            
            $svg .= sprintf(
                '<line x1="%f" y1="%f" x2="%f" y2="%f" stroke="%s" stroke-width="1"/>',
                $x1, $y1, $x2, $y2, $this->colors['signs']
            );

            // Sign symbols
            $x = $this->centerX + $signRadius * cos($radians);
            $y = $this->centerY + $signRadius * sin($radians);
            
            $svg .= sprintf(
                '<text x="%f" y="%f" fill="%s" font-size="16" font-weight="bold" text-anchor="middle" dominant-baseline="central">%s</text>',
                $x, $y, $this->colors['signs'], $this->signSymbols[$signs[$i]]
            );
        }

        return $svg;
    }

    private function drawHouseDivisions(array $housePositions): string
    {
        if (empty($housePositions)) {
            return '';
        }

        $svg = '';
        $outerRadius = $this->size * 0.35;
        $innerRadius = $this->size * 0.15;
        $houseRadius = $this->size * 0.25;

        for ($house = 1; $house <= 12; $house++) {
            if (!isset($housePositions[$house]['cusp'])) {
                continue;
            }

            $cusp = $housePositions[$house]['cusp'];
            $angle = $cusp - 90; // Adjust for coordinate system
            $radians = deg2rad($angle);

            // House cusp lines
            $x1 = $this->centerX + $innerRadius * cos($radians);
            $y1 = $this->centerY + $innerRadius * sin($radians);
            $x2 = $this->centerX + $outerRadius * cos($radians);
            $y2 = $this->centerY + $outerRadius * sin($radians);

            $strokeWidth = ($house % 3 === 1) ? 2 : 1; // Angular houses thicker
            
            $svg .= sprintf(
                '<line x1="%f" y1="%f" x2="%f" y2="%f" stroke="%s" stroke-width="%d"/>',
                $x1, $y1, $x2, $y2, $this->colors['houses'], $strokeWidth
            );

            // House numbers
            $nextHouse = ($house % 12) + 1;
            $nextCusp = $housePositions[$nextHouse]['cusp'] ?? ($cusp + 30);
            $midAngle = $cusp + (($nextCusp - $cusp) / 2);
            if ($nextCusp < $cusp) $midAngle = $cusp + (($nextCusp + 360 - $cusp) / 2);
            
            $midRadians = deg2rad($midAngle - 90);
            $x = $this->centerX + $houseRadius * cos($midRadians);
            $y = $this->centerY + $houseRadius * sin($midRadians);
            
            $svg .= sprintf(
                '<text x="%f" y="%f" fill="%s" font-size="12" text-anchor="middle" dominant-baseline="central">%d</text>',
                $x, $y, $this->colors['houses'], $house
            );
        }

        return $svg;
    }

    private function drawPlanets(array $planetaryPositions): string
    {
        if (empty($planetaryPositions)) {
            return '';
        }

        $svg = '';
        $planetRadius = $this->size * 0.30;

        foreach ($planetaryPositions as $planet => $position) {
            if (!isset($position['longitude'])) {
                continue;
            }

            $longitude = $position['longitude'];
            $angle = $longitude - 90; // Adjust for coordinate system
            $radians = deg2rad($angle);

            $x = $this->centerX + $planetRadius * cos($radians);
            $y = $this->centerY + $planetRadius * sin($radians);

            // Planet symbol
            $symbol = $this->planetSymbols[$planet] ?? '●';
            $svg .= sprintf(
                '<text x="%f" y="%f" fill="%s" font-size="14" font-weight="bold" text-anchor="middle" dominant-baseline="central">%s</text>',
                $x, $y, $this->colors['planets'], $symbol
            );

            // Planet degree
            $degreeText = sprintf('%.0f°', fmod($longitude, 30));
            $svg .= sprintf(
                '<text x="%f" y="%f" fill="%s" font-size="8" text-anchor="middle" dominant-baseline="central">%s</text>',
                $x, $y + 12, $this->colors['planets'], $degreeText
            );
        }

        return $svg;
    }

    private function drawAspects(array $planetaryPositions, array $aspects): string
    {
        if (empty($aspects) || empty($planetaryPositions)) {
            return '';
        }

        $svg = '';
        $aspectRadius = $this->size * 0.13;

        $aspectColors = [
            'conjunction' => '#ff6b6b',
            'sextile' => '#4ecdc4',
            'square' => '#ff6b6b',
            'trine' => '#45b7d1',
            'opposition' => '#ff6b6b',
            'quincunx' => '#96ceb4',
            'semisextile' => '#feca57'
        ];

        foreach ($aspects as $aspect) {
            if (!isset($planetaryPositions[$aspect['planet1']]) || 
                !isset($planetaryPositions[$aspect['planet2']])) {
                continue;
            }

            $pos1 = $planetaryPositions[$aspect['planet1']]['longitude'];
            $pos2 = $planetaryPositions[$aspect['planet2']]['longitude'];

            $angle1 = deg2rad($pos1 - 90);
            $angle2 = deg2rad($pos2 - 90);

            $x1 = $this->centerX + $aspectRadius * cos($angle1);
            $y1 = $this->centerY + $aspectRadius * sin($angle1);
            $x2 = $this->centerX + $aspectRadius * cos($angle2);
            $y2 = $this->centerY + $aspectRadius * sin($angle2);

            $color = $aspectColors[$aspect['aspect']] ?? $this->colors['aspects'];
            $strokeWidth = ($aspect['aspect'] === 'conjunction' || $aspect['aspect'] === 'opposition') ? 2 : 1;

            $svg .= sprintf(
                '<line x1="%f" y1="%f" x2="%f" y2="%f" stroke="%s" stroke-width="%d" opacity="0.6"/>',
                $x1, $y1, $x2, $y2, $color, $strokeWidth
            );
        }

        return $svg;
    }

    public function generateWheelWithCache(string $cacheKey, array $planetaryPositions, array $housePositions = [], array $aspects = []): string
    {
        $cacheDir = ROOT_PATH . '/storage/cache/charts';
        $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.svg';

        // Create cache directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Return cached version if exists and is recent
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            return file_get_contents($cacheFile);
        }

        // Generate new chart
        $svg = $this->generateWheel($planetaryPositions, $housePositions, $aspects);
        
        // Cache the result
        file_put_contents($cacheFile, $svg);

        return $svg;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        $this->centerX = $size / 2;
        $this->centerY = $size / 2;
        return $this;
    }

    public function setColors(array $colors): self
    {
        $this->colors = array_merge($this->colors, $colors);
        return $this;
    }
}