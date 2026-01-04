<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Core\Logger;

class SwissEphemeris
{
    private string $swetestPath;
    private string $dataPath;
    private array $planetCodes = [
        'sun' => 0,
        'moon' => 1,
        'mercury' => 2,
        'venus' => 3,
        'mars' => 4,
        'jupiter' => 5,
        'saturn' => 6,
        'uranus' => 7,
        'neptune' => 8,
        'pluto' => 9,
        'north_node' => 11,
        'south_node' => -11,
        'chiron' => 15,
        'lilith' => -1
    ];

    /**
     * Cache for calculated planetary positions keyed by julian day and planet code.
     *
     * @var array<string, array|null>
     */
    private array $positionCache = [];

    /**
     * Initialize Swiss Ephemeris helper and verify swetest availability.
     */
    public function __construct()
    {
        $this->swetestPath = defined('SWEPH_PATH') ? SWEPH_PATH : '/usr/local/bin/swetest';
        $this->dataPath = defined('SWEPH_DATA_PATH') ? SWEPH_DATA_PATH : ROOT_PATH . '/data/ephemeris';
        
        if (!$this->isSwetestAvailable()) {
            Logger::warning("Swiss Ephemeris swetest not found at: " . $this->swetestPath);
        }
    }

    /**
     * Calculate planetary positions for a given time and location.
     *
     * @param \DateTime $datetime  The date and time of the chart.
     * @param float      $latitude  Latitude in decimal degrees.
     * @param float      $longitude Longitude in decimal degrees.
     * @param array|null $planets   Optional list of planet identifiers.
     *
     * @return array<string, array|null> Array of planet positions keyed by planet name.
     */
    public function calculatePlanetaryPositions(\DateTime $datetime, float $latitude, float $longitude, ?array $planets = null): array
    {
        if ($planets === null) {
            $planets = ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto'];
        }

        $julianDay = $this->dateTimeToJulianDay($datetime);
        $results = [];

        foreach ($planets as $planet) {
            if (!isset($this->planetCodes[$planet])) {
                Logger::warning("Unknown planet code: {$planet}");
                continue;
            }

            try {
                $position = $this->calculatePlanetPosition($julianDay, $this->planetCodes[$planet]);
                $results[$planet] = $position;
            } catch (\Exception $e) {
                Logger::error("Failed to calculate position for {$planet}", ['error' => $e->getMessage()]);
                $results[$planet] = null;
            }
        }

        return $results;
    }

    /**
     * Calculate house cusps for a given time, location and house system.
     *
     * @param \DateTime $datetime  The date and time of the chart.
     * @param float      $latitude  Latitude in decimal degrees.
     * @param float      $longitude Longitude in decimal degrees.
     * @param string     $houseSystem House system code (e.g. 'P' for Placidus).
     *
     * @return array<int, array> List of houses keyed by house number.
     */
    public function calculateHouses(\DateTime $datetime, float $latitude, float $longitude, string $houseSystem = 'P'): array
    {
        $julianDay = $this->dateTimeToJulianDay($datetime);

        try {
            return $this->calculateHousePositions($julianDay, $latitude, $longitude, $houseSystem);
        } catch (\Exception $e) {
            Logger::error("Failed to calculate houses", ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function calculatePlanetPosition(float $julianDay, int $planetCode): ?array
    {
        $cacheKey = $julianDay . '_' . $planetCode;
        if (isset($this->positionCache[$cacheKey])) {
            return $this->positionCache[$cacheKey];
        }

        if (!$this->isSwetestAvailable()) {
            $this->positionCache[$cacheKey] = $this->getAnalyticalPosition($julianDay, $planetCode);
            return $this->positionCache[$cacheKey];
        }

        $command = sprintf(
            '%s -b%f -p%d -f -head',
            escapeshellcmd($this->swetestPath),
            $julianDay,
            $planetCode
        );

        if (is_dir($this->dataPath)) {
            $command .= ' -edir' . escapeshellarg($this->dataPath);
        }

        $output = shell_exec($command . ' 2>&1');

        if ($output === null) {
            throw new \Exception("Failed to execute swetest command");
        }

        $this->positionCache[$cacheKey] = $this->parseSwetestOutput($output);
        return $this->positionCache[$cacheKey];
    }

    private function calculateHousePositions(float $julianDay, float $latitude, float $longitude, string $houseSystem): array
    {
        if (!$this->isSwetestAvailable()) {
            return $this->getAnalyticalHouses($latitude, $longitude);
        }

        $command = sprintf(
            '%s -b%f -house%f,%f,%s -f -head',
            escapeshellcmd($this->swetestPath),
            $julianDay,
            $longitude,
            $latitude,
            $houseSystem
        );

        if (is_dir($this->dataPath)) {
            $command .= ' -edir' . escapeshellarg($this->dataPath);
        }

        $output = shell_exec($command . ' 2>&1');
        
        if ($output === null) {
            throw new \Exception("Failed to execute swetest house command");
        }

        return $this->parseHouseOutput($output);
    }

    private function parseSwetestOutput(string $output): ?array
    {
        $lines = explode("\n", trim($output));
        
        foreach ($lines as $line) {
            if (empty($line) || strpos($line, 'ET') === false) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 4) {
                return [
                    'longitude' => (float) $parts[1],
                    'latitude' => (float) $parts[2],
                    'distance' => (float) $parts[3],
                    'longitude_speed' => isset($parts[4]) ? (float) $parts[4] : 0,
                    'latitude_speed' => isset($parts[5]) ? (float) $parts[5] : 0,
                    'distance_speed' => isset($parts[6]) ? (float) $parts[6] : 0
                ];
            }
        }

        return null;
    }

    private function parseHouseOutput(string $output): array
    {
        $houses = [];
        $cusps = [];
        
        $lines = explode("\n", trim($output));
        
        foreach ($lines as $line) {
            if (strpos($line, 'house') !== false) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 3) {
                    $houseNumber = (int) str_replace('house', '', $parts[0]);
                    if ($houseNumber >= 1 && $houseNumber <= 12) {
                        $cusps[$houseNumber] = (float) $parts[1];
                    }
                }
            }
        }

        for ($i = 1; $i <= 12; $i++) {
            $houses[$i] = [
                'cusp' => $cusps[$i] ?? 0,
                'sign' => $this->getZodiacSign($cusps[$i] ?? 0)
            ];
        }

        return $houses;
    }

    private function getAnalyticalPosition(float $julianDay, int $planetCode): array
    {
        // Simplified analytical calculations for basic positions
        // This is a fallback when Swiss Ephemeris is not available
        
        $centuries = ($julianDay - 2451545.0) / 36525.0;
        
        switch ($planetCode) {
            case 0: // Sun
                $longitude = $this->calculateSunLongitude($centuries);
                break;
            case 1: // Moon
                $longitude = $this->calculateMoonLongitude($centuries);
                break;
            default:
                // For other planets, provide approximations
                $longitude = $this->normalizeLongitude(($planetCode * 30) + ($centuries * 360));
        }

        return [
            'longitude' => $longitude,
            'latitude' => 0,
            'distance' => 1,
            'longitude_speed' => 1,
            'latitude_speed' => 0,
            'distance_speed' => 0,
            'note' => 'Analytical approximation - install Swiss Ephemeris for precision'
        ];
    }

    private function calculateSunLongitude(float $centuries): float
    {
        // Simplified sun position calculation
        $L0 = 280.46646 + $centuries * (36000.76983 + $centuries * 0.0003032);
        $M = 357.52911 + $centuries * (35999.05029 - 0.0001537 * $centuries);
        $C = sin(deg2rad($M)) * (1.914602 - $centuries * (0.004817 + 0.000014 * $centuries)) +
             sin(deg2rad(2 * $M)) * (0.019993 - 0.000101 * $centuries) +
             sin(deg2rad(3 * $M)) * 0.000289;

        return $this->normalizeLongitude($L0 + $C);
    }

    private function calculateMoonLongitude(float $centuries): float
    {
        // Simplified moon position calculation
        $L = 218.3164477 + $centuries * (481267.88123421 - $centuries * (0.0015786 + $centuries / 538841.0 - $centuries / 65194000.0));
        return $this->normalizeLongitude($L);
    }

    private function getAnalyticalHouses(float $latitude, float $longitude): array
    {
        // Simple equal house system as fallback
        $houses = [];
        $ascendant = $this->normalizeLongitude($longitude + 180); // Simplified ascendant calculation

        for ($i = 1; $i <= 12; $i++) {
            $cusp = $this->normalizeLongitude($ascendant + ($i - 1) * 30);
            $houses[$i] = [
                'cusp' => $cusp,
                'sign' => $this->getZodiacSign($cusp)
            ];
        }

        return $houses;
    }

    /**
     * Normalize longitude to the range 0-360 degrees.
     *
     * @param float $longitude The longitude value to normalize.
     * @return float Normalized longitude in range [0, 360).
     */
    private function normalizeLongitude(float $longitude): float
    {
        $normalized = fmod($longitude, 360);
        if ($normalized < 0) {
            $normalized += 360;
        }
        return $normalized;
    }

    private function getZodiacSign(float $longitude): string
    {
        $signs = [
            'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
            'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'
        ];

        $normalizedLongitude = $this->normalizeLongitude($longitude);
        $signIndex = (int) floor($normalizedLongitude / 30);
        return $signs[$signIndex] ?? 'Unknown';
    }

    private function dateTimeToJulianDay(\DateTime $datetime): float
    {
        $utcDateTime = clone $datetime;
        $utcDateTime->setTimezone(new \DateTimeZone('UTC'));
        
        $year = (int) $utcDateTime->format('Y');
        $month = (int) $utcDateTime->format('m');
        $day = (int) $utcDateTime->format('d');
        $hour = (int) $utcDateTime->format('H');
        $minute = (int) $utcDateTime->format('i');
        $second = (int) $utcDateTime->format('s');
        
        if ($month <= 2) {
            $year--;
            $month += 12;
        }
        
        $a = (int) ($year / 100);
        $b = 2 - $a + (int) ($a / 4);
        
        $jd = (int) (365.25 * ($year + 4716)) + 
              (int) (30.6001 * ($month + 1)) + 
              $day + $b - 1524.5;
        
        $jd += ($hour + $minute / 60.0 + $second / 3600.0) / 24.0;
        
        return $jd;
    }

    private function isSwetestAvailable(): bool
    {
        return file_exists($this->swetestPath) && is_executable($this->swetestPath);
    }

    /**
     * Retrieve the list of available planetary identifiers.
     *
     * @return array<int, string>
     */
    public function getAvailablePlanets(): array
    {
        return array_keys($this->planetCodes);
    }

    /**
     * Retrieve supported house systems mapped to their names.
     *
     * @return array<string, string>
     */
    public function getSupportedHouseSystems(): array
    {
        return [
            'P' => 'Placidus',
            'K' => 'Koch',
            'O' => 'Porphyrius',
            'R' => 'Regiomontanus',
            'C' => 'Campanus',
            'A' => 'Equal',
            'E' => 'Equal from Ascendant',
            'W' => 'Whole Signs',
            'X' => 'Meridian System',
            'T' => 'Topocentric',
            'B' => 'Alcabitius',
            'M' => 'Morinus'
        ];
    }
}