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
    public static function houses(\DateTimeImmutable $utc, float $lat, float $lon, string $houseSystem = "P"): array
{
    $date = $utc->format("d.m.Y");
    $time = $utc->format("H:i:s");
    $hs   = strtoupper($houseSystem[0] ?? "P");

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

    // Collect only angles (0..360)
    $a = [];
    foreach ($lines as $line) {
        if (preg_match_all('/-?\d+(?:\.\d+)?/', $line, $m)) {
            foreach ($m[0] as $raw) {
                $v = self::norm360((float)$raw);
                if ($v >= 0.0 && $v < 360.0) $a[] = $v;
            }
        }
    }
    if (count($a) < 16) {
        throw new \RuntimeException('Could not parse enough values for houses.');
    }

    // Heuristic #1: use the last block (12 cusps + angles)
    $block = count($a) >= 20 ? array_slice($a, -20) : array_slice($a, -16);
    $cusps = array_slice($block, 0, 12);
    $anglesOut = [
        'ASC'    => $block[12] ?? 0.0,
        'MC'     => $block[13] ?? 0.0,
        'ARMC'   => $block[14] ?? 0.0,
        'Vertex' => $block[15] ?? 0.0,
    ];
    $eps = 0.8; // degrees tolerance

    $mcMatches  = self::delta($anglesOut['MC'], $cusps[9] ?? -999) <= $eps;   // cusp10
    $ascMatches = self::delta($anglesOut['ASC'], $cusps[0] ?? -999) <= $eps;  // cusp1

    // If not aligned, search the whole stream for a 12+4 window that aligns ASC with cusp1 and MC with cusp10.
    if (!$mcMatches || !$ascMatches) {
        $best = null;
        for ($i = 0; $i + 15 < count($a); $i++) {
            $winCusps  = array_slice($a, $i, 12);
            $winAngles = [
                'ASC'    => $a[$i+12],
                'MC'     => $a[$i+13],
                'ARMC'   => $a[$i+14],
                'Vertex' => $a[$i+15],
            ];
            $okAsc = self::delta($winAngles['ASC'], $winCusps[0]) <= $eps;
            $okMC  = self::delta($winAngles['MC'],  $winCusps[9]) <= $eps;
            if ($okAsc && $okMC) { $best = [$winCusps, $winAngles]; break; }
        }
        if ($best) {
            [$cusps, $anglesOut] = $best;
        } else {
            // Last resort in Placidus: force cusp1/10 to ASC/MC so visuals stay correct
            if ($hs === 'P') {
                $cusps[0] = $anglesOut['ASC'];
                $cusps[9] = $anglesOut['MC'];
            }
        }
    }

    // Map to 1..12 and normalise
    $outCusps = [];
    for ($k=0; $k<12; $k++) $outCusps[$k+1] = self::norm360($cusps[$k]);

    return [
        'system' => $hs,
        'cusps'  => $outCusps,
        'angles' => [
            'ASC'    => self::norm360($anglesOut['ASC']),
            'MC'     => self::norm360($anglesOut['MC']),
            'ARMC'   => self::norm360($anglesOut['ARMC']),
            'Vertex' => self::norm360($anglesOut['Vertex']),
        ],
    ];
}


    private static function norm360(float $deg): float
    {
        $x = fmod($deg, 360.0);
        if ($x < 0) $x += 360.0;
        // squash -0 to 0 and round to 3 decimals
        if (abs($x) < 1e-9 $x =  0.0;
        return round($x, 3);
    }

    private static function delta(float $a, float $b): float
    {
        $d = abs($a - $b);
        return $d > 180 ? 360 - $d : $d;
    }
}
