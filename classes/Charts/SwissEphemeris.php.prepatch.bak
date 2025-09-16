<?php
# classes/Charts/SwissEphemeris.php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\Env;

final class SwissEphemeris
{
    private static function bin(): string
    {
        Env::load(__DIR__ . '/../../.env');
        $bin = Env::get('SWEPH_PATH') ?? '';
        if ($bin === '' || !is_file($bin) || !is_executable($bin)) {
            throw new \RuntimeException('swetest not found or not executable. Set SWEPH_PATH in .env');
        }
        return $bin;
    }

    /** Execute swetest and return non-empty, trimmed lines */
    private static function run(string $args): array
    {
        $cmd = sprintf('%s %s', escapeshellarg(self::bin()), $args);
        $out = []; $code = 0;
        @exec($cmd, $out, $code);
        if ($code !== 0 || empty($out)) {
            throw new \RuntimeException('swetest failed or returned no data (args: ' . $args . ')');
        }
        return array_values(array_filter(array_map('trim', $out), fn($s) => $s !== ''));
    }

    /** Planetary positions: Sun..Pluto + Chiron + mean & true nodes */
    public static function positions(\DateTimeImmutable $utc, float $lat, float $lon): array
    {
        $date = $utc->format('d.m.Y');
        $time = $utc->format('H:i:s');
        $plan = '0123456789Dmt';

        $args = sprintf(
            '-eswe -b%s -ut%s -p%s -fPlbR -g, -head -n1',
            escapeshellarg($date),
            escapeshellarg($time),
            escapeshellarg($plan)
        );

        $rows = [];
        foreach (self::run($args) as $line) {
            if ($line === '' || str_starts_with($line, 'date')) continue;
            $parts = array_map('trim', explode(',', $line));
            if (count($parts) < 4) continue;
            $rows[] = [
                'planet' => $parts[0],
                'lon'    => (float)$parts[1],
                'lat'    => (float)$parts[2],
                'dist'   => (float)$parts[3],
            ];
        }
        if (!$rows) throw new \RuntimeException('Could not parse planetary positions.');
        return $rows;
    }

    /**
     * Houses: 12 cusps + angles (ASC, MC, ARMC, Vertex, EqAsc, CoAsc(Koch), CoAsc(Munkasey), PolarAsc)
     * Returns:
     * [
     *   'system' => 'P',
     *   'cusps'  => [1=>deg,...,12=>deg],
     *   'angles' => ['ASC'=>deg,'MC'=>deg,'ARMC'=>deg,'Vertex'=>deg]
     * ]
     */
    public static function houses(\DateTimeImmutable $utc, float $lat, float $lon, string $houseSystem = 'P'): array
    {
        $date = $utc->format('d.m.Y');
        $time = $utc->format('H:i:s');
        $hs   = strtoupper($houseSystem[0] ?? 'P'); // Placidus default

        // swetest expects lon,lat,hsys; requires -ut for houses
        $args = sprintf(
            '-eswe -b%s -ut%s -house%F,%F,%s -g, -head',
            escapeshellarg($date),
            escapeshellarg($time),
            $lon,  // east positive
            $lat,  // north positive
            escapeshellarg(strtolower($hs))
        );

        $lines = self::run($args);

        // Collect ONLY plausible angles; normalise to 0..360
        $angles = [];
        foreach ($lines as $line) {
            if (preg_match_all('/-?\d+(?:\.\d+)?/', $line, $m)) {
                foreach ($m[0] as $raw) {
                    $v = self::norm360((float)$raw);
                    // keep angles; discard obvious junk like huge years
                    if ($v >= 0.0 && $v < 360.0) $angles[] = $v;
                }
            }
        }

        // Prefer the final block (cusps+angles are printed together at the end)
        // Manual says: 12 cusps, then 8 angles. We only need first 4. 
        $slice = [];
        if (count($angles) >= 20) {
            $slice = array_slice($angles, -20);
        } elseif (count($angles) >= 16) {
            $slice = array_slice($angles, -16);
        } else {
            throw new \RuntimeException('Could not parse enough values for houses.');
        }

        // Map: first 12 = cusps
        $cusps = [];
        for ($i = 0; $i < 12; $i++) $cusps[$i + 1] = $slice[$i];

        // Next values = ASC, MC, ARMC, Vertex (ignore further extras if present)
        $anglesOut = [
            'ASC'    => $slice[12] ?? 0.0,
            'MC'     => $slice[13] ?? 0.0,
            'ARMC'   => $slice[14] ?? 0.0,
            'Vertex' => $slice[15] ?? 0.0,
        ];

        // Sanity: in Placidus, MC equals 10th cusp within tiny epsilon.
        if ($hs === 'P' && self::delta($anglesOut['MC'], $cusps[10]) > 0.5) {
            // Try alternative: take the FIRST 20 numbers from the final chunk we collected
            $try = (count($angles) >= 20) ? array_slice($angles, 0, 20) : $slice;
            $tmpCusps = [];
            for ($i = 0; $i < 12 && $i < count($try); $i++) $tmpCusps[$i + 1] = $try[$i];
            $tmpAngles = [
                'ASC'    => $try[12] ?? $anglesOut['ASC'],
                'MC'     => $try[13] ?? $anglesOut['MC'],
                'ARMC'   => $try[14] ?? $anglesOut['ARMC'],
                'Vertex' => $try[15] ?? $anglesOut['Vertex'],
            ];
            if (isset($tmpCusps[10]) && self::delta($tmpAngles['MC'], $tmpCusps[10]) <= self::delta($anglesOut['MC'], $cusps[10])) {
                $cusps = $tmpCusps;
                $anglesOut = $tmpAngles;
            }
        }

        return [
            'system' => $hs,
            'cusps'  => $cusps,
            'angles' => $anglesOut,
        ];
    }

    private static function norm360(float $deg): float
    {
        $x = fmod($deg, 360.0);
        if ($x < 0) $x += 360.0;
        return $x;
    }

    private static function delta(float $a, float $b): float
    {
        $d = abs($a - $b);
        return $d > 180 ? 360 - $d : $d;
    }
}
