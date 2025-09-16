<?php
# classes/Charts/Aspects.php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

final class Aspects
{
    /** Default aspect set + max orbs (deg) */
    private const ASPECTS = [
        ['name' => 'Conjunction', 'angle' => 0,   'orb' => 8],
        ['name' => 'Opposition',  'angle' => 180, 'orb' => 8],
        ['name' => 'Trine',       'angle' => 120, 'orb' => 6],
        ['name' => 'Square',      'angle' => 90,  'orb' => 6],
        ['name' => 'Sextile',     'angle' => 60,  'orb' => 4],
        ['name' => 'Quincunx',    'angle' => 150, 'orb' => 3],
        ['name' => 'Semisextile', 'angle' => 30,  'orb' => 2],
    ];

    /** Some astrologers use larger orbs for luminaries; bump them */
    private const LUM_EXTRA = 2.0; // add to default orb for Sun/Moon pairs

    /**
     * @param array<int, array{planet:string, lon:float}> $planets
     * @return array<int, array{a:string,b:string,type:string,angle:float,delta:float,orb:float,within:float,exact:bool}>
     */
    public static function compute(array $planets): array
    {
        $out = [];
        $n = count($planets);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $planets[$i];
                $b = $planets[$j];
                $sep = self::angularSep($a['lon'], $b['lon']); // 0..180

                foreach (self::ASPECTS as $asp) {
                    $target = (float)$asp['angle'];
                    $baseOrb = (float)$asp['orb'];
                    $orb = $baseOrb + (self::isLuminary($a['planet']) + self::isLuminary($b['planet'])) * (self::LUM_EXTRA / 2.0);

                    $delta = abs($sep - $target);
                    if ($delta <= $orb) {
                        $out[] = [
                            'a'      => $a['planet'],
                            'b'      => $b['planet'],
                            'type'   => $asp['name'],
                            'angle'  => $target,
                            'delta'  => round($delta, 3),
                            'orb'    => $orb,
                            'within' => round($orb - $delta, 3),
                            'exact'  => $delta < 0.1, // within 0.1°
                        ];
                        break;
                    }
                }
            }
        }
        // sort tightest first
        usort($out, fn($x,$y) => $x['delta'] <=> $y['delta']);
        return $out;
    }

    private static function isLuminary(string $name): bool
    {
        $n = strtolower($name);
        return $n === 'sun' || $n === 'moon';
    }

    /** minimal absolute angular separation on circle (0..180) */
    private static function angularSep(float $a, float $b): float
    {
        $d = abs($a - $b);
        $d = fmod($d, 360.0);
        if ($d > 180.0) $d = 360.0 - $d;
        return $d;
    }
}
