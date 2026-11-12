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
            'background' => '#120c07',
            'background_secondary' => '#24180d',
            'wheel' => '#f2a93b',
            'houses' => '#7d6649',
            'signs' => '#ffd27a',
            'planets' => '#f6e9d2',
            'planet_orbit' => '#4f3d29',
            'planet_node' => '#f2a93b',
            'aspects' => '#9b7bea',
            'label_primary' => '#f6e9d2',
            'label_secondary' => '#c9b595'
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

    public function generateWheel(array $planetaryPositions, array $housePositions = [], array $aspects = [], array $options = []): string
    {
        $this->colors = $this->resolvePalette($options);

        $svg = $this->createSVGContainer();
        $svg .= $this->drawBackground();
        $svg .= $this->drawTitle($options);
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
        $gradientId = 'bgGradient';
        $glowId = 'innerGlow';

        $defs = sprintf(
            '<defs><radialGradient id="%s" cx="50%%" cy="45%%" r="75%%"><stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></radialGradient><radialGradient id="%s" cx="50%%" cy="50%%" r="55%%"><stop offset="70%%" stop-color="rgba(0,0,0,0)"/><stop offset="100%%" stop-color="rgba(0,0,0,0.45)"/></radialGradient></defs>',
            $gradientId,
            $this->colors['background_secondary'],
            $this->colors['background'],
            $glowId
        );

        return $defs .
            sprintf('<rect width="%d" height="%d" fill="url(#%s)"/>', $this->size, $this->size, $gradientId) .
            sprintf('<circle cx="%d" cy="%d" r="%f" fill="none" stroke="%s" stroke-width="1" opacity="0.35"/>', $this->centerX, $this->centerY, $this->size * 0.46, $this->colors['wheel']) .
            sprintf('<circle cx="%d" cy="%d" r="%f" fill="url(#%s)"/>', $this->centerX, $this->centerY, $this->size * 0.48, $glowId);
    }

    private function drawTitle(array $options): string
    {
        $chartName = trim((string)($options['chart_name'] ?? 'Chart'));
        $chartType = ucfirst(strtolower(trim((string)($options['chart_type'] ?? 'natal'))));
        $houseSystem = strtoupper(trim((string)($options['house_system'] ?? 'P')));
        $birthDatetime = trim((string)($options['birth_datetime'] ?? ''));

        $subtitle = $chartType . ' | House ' . $houseSystem;
        if ($birthDatetime !== '') {
            $subtitle .= ' | ' . $birthDatetime;
        }

        return sprintf(
            '<text x="%d" y="%f" fill="%s" font-size="%d" font-weight="700" text-anchor="middle">%s</text><text x="%d" y="%f" fill="%s" font-size="%d" text-anchor="middle">%s</text>',
            $this->centerX,
            $this->size * 0.055,
            $this->colors['label_primary'],
            max(12, (int)round($this->size * 0.028)),
            htmlspecialchars($chartName),
            $this->centerX,
            $this->size * 0.08,
            $this->colors['label_secondary'],
            max(9, (int)round($this->size * 0.016)),
            htmlspecialchars($subtitle)
        );
    }

    private function drawZodiacCircle(): string
    {
        $svg = '';
        $outerRadius = $this->size * 0.45;
        $innerRadius = $this->size * 0.35;
        $signRadius = $this->size * 0.40;

        // Draw background wedges for each sign
        $signColors = [
            'Aries' => 'rgba(255, 107, 107, 0.1)', 'Taurus' => 'rgba(78, 205, 196, 0.1)',
            'Gemini' => 'rgba(255, 230, 109, 0.1)', 'Cancer' => 'rgba(255, 159, 243, 0.1)',
            'Leo' => 'rgba(255, 159, 67, 0.1)', 'Virgo' => 'rgba(10, 189, 227, 0.1)',
            'Libra' => 'rgba(255, 121, 121, 0.1)', 'Scorpio' => 'rgba(84, 160, 255, 0.1)',
            'Sagittarius' => 'rgba(255, 159, 67, 0.1)', 'Capricorn' => 'rgba(10, 189, 227, 0.1)',
            'Aquarius' => 'rgba(200, 214, 229, 0.1)', 'Pisces' => 'rgba(72, 219, 251, 0.1)'
        ];

        $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
                 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];

        for ($i = 0; $i < 12; $i++) {
            $startAngle = ($i * 30) - 90;
            $endAngle = $startAngle + 30;

            $x1_out = $this->centerX + $outerRadius * cos(deg2rad($startAngle));
            $y1_out = $this->centerY + $outerRadius * sin(deg2rad($startAngle));
            $x2_out = $this->centerX + $outerRadius * cos(deg2rad($endAngle));
            $y2_out = $this->centerY + $outerRadius * sin(deg2rad($endAngle));

            $x1_in = $this->centerX + $innerRadius * cos(deg2rad($startAngle));
            $y1_in = $this->centerY + $innerRadius * sin(deg2rad($startAngle));
            $x2_in = $this->centerX + $innerRadius * cos(deg2rad($endAngle));
            $y2_in = $this->centerY + $innerRadius * sin(deg2rad($endAngle));

            // Create path for the wedge
            $pathData = sprintf(
                "M %f %f A %f %f 0 0 1 %f %f L %f %f A %f %f 0 0 0 %f %f Z",
                $x1_out, $y1_out, $outerRadius, $outerRadius, $x2_out, $y2_out,
                $x2_in, $y2_in, $innerRadius, $innerRadius, $x1_in, $y1_in
            );

            $svg .= sprintf(
                '<path d="%s" fill="%s" stroke="%s" stroke-width="1"/>',
                $pathData, $signColors[$signs[$i]], $this->colors['wheel']
            );

            // Sign symbols
            $midRadians = deg2rad($startAngle + 15);
            $x = $this->centerX + $signRadius * cos($midRadians);
            $y = $this->centerY + $signRadius * sin($midRadians);

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
        $svg .= sprintf(
            '<circle cx="%d" cy="%d" r="%f" fill="none" stroke="%s" stroke-width="1.2" opacity="0.85"/>',
            $this->centerX,
            $this->centerY,
            $planetRadius + ($this->size * 0.012),
            $this->colors['planet_orbit']
        );

        foreach ($planetaryPositions as $planet => $position) {
            if (!isset($position['longitude'])) {
                continue;
            }

            $longitude = $position['longitude'];
            // Normalize longitude to 0-360 range
            $normalizedLongitude = fmod($longitude, 360);
            if ($normalizedLongitude < 0) {
                $normalizedLongitude += 360;
            }

            $angle = $normalizedLongitude - 90; // Adjust for coordinate system
            $radians = deg2rad($angle);

            $x = $this->centerX + $planetRadius * cos($radians);
            $y = $this->centerY + $planetRadius * sin($radians);

            $svg .= sprintf(
                '<circle cx="%f" cy="%f" r="%f" fill="%s" opacity="0.32"/>',
                $x,
                $y,
                $this->size * 0.014,
                $this->colors['planet_node']
            );

            // Planet symbol
            $symbol = $this->planetSymbols[$planet] ?? '●';
            $svg .= sprintf(
                '<text x="%f" y="%f" fill="%s" font-size="14" font-weight="bold" text-anchor="middle" dominant-baseline="central">%s</text>',
                $x, $y, $this->colors['planets'], $symbol
            );

            // Planet degree within sign (0-30)
            $degreeInSign = fmod($normalizedLongitude, 30);
            $degreeText = sprintf('%.0f°', $degreeInSign);
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

            // Normalize longitudes to 0-360 range
            $normalizedPos1 = fmod($pos1, 360);
            if ($normalizedPos1 < 0) {
                $normalizedPos1 += 360;
            }
            $normalizedPos2 = fmod($pos2, 360);
            if ($normalizedPos2 < 0) {
                $normalizedPos2 += 360;
            }

            $angle1 = deg2rad($normalizedPos1 - 90);
            $angle2 = deg2rad($normalizedPos2 - 90);

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

    public function generateWheelWithCache(string $cacheKey, array $planetaryPositions, array $housePositions = [], array $aspects = [], array $options = []): string
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
        $svg = $this->generateWheel($planetaryPositions, $housePositions, $aspects, $options);

        // Cache the result
        file_put_contents($cacheFile, $svg);

        return $svg;
    }

    private function resolvePalette(array $options): array
    {
        $chartType = strtolower(trim((string)($options['chart_type'] ?? 'natal')));

        $palettes = [
            'natal' => [
                'background' => '#120c07',
                'background_secondary' => '#2a1c0f',
                'wheel' => '#f2a93b',
                'houses' => '#a3896a',
                'signs' => '#ffd27a',
                'planets' => '#fff8ec',
                'planet_orbit' => '#5e400f',
                'planet_node' => '#e0921f',
                'aspects' => '#9b7bea',
                'label_primary' => '#f6e9d2',
                'label_secondary' => '#c9b595',
            ],
            'transit' => [
                'background' => '#0c1412',
                'background_secondary' => '#132722',
                'wheel' => '#7dd3c0',
                'houses' => '#9fd9c9',
                'signs' => '#ffe2a3',
                'planets' => '#effbf7',
                'planet_orbit' => '#1f7a63',
                'planet_node' => '#4fb8a2',
                'aspects' => '#f2a93b',
                'label_primary' => '#dcf6ef',
                'label_secondary' => '#a5e6d6',
            ],
            'progression' => [
                'background' => '#140e1f',
                'background_secondary' => '#251a3a',
                'wheel' => '#9b7bea',
                'houses' => '#b9a0f5',
                'signs' => '#ffd27a',
                'planets' => '#f5f1fe',
                'planet_orbit' => '#5638a0',
                'planet_node' => '#7f5ddb',
                'aspects' => '#f2a93b',
                'label_primary' => '#ebe3fd',
                'label_secondary' => '#b9a0f5',
            ],
            'synastry' => [
                'background' => '#1c0f0c',
                'background_secondary' => '#33191a',
                'wheel' => '#f59e8b',
                'houses' => '#f7b8a8',
                'signs' => '#ffe2a3',
                'planets' => '#fff3ef',
                'planet_orbit' => '#a8463a',
                'planet_node' => '#e5735f',
                'aspects' => '#ffd27a',
                'label_primary' => '#ffe9e3',
                'label_secondary' => '#f7b8a8',
            ],
        ];

        return $palettes[$chartType] ?? $palettes['natal'];
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
