<?php
# classes/Charts/TransitService.php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use QuantumAstrology\Core\DB;
use RuntimeException;

final class TransitService
{
    /** Default aspect set shared by transit calculations */
    private const ASPECT_SET = [
        ['name' => 'Conjunction', 'angle' => 0.0, 'orb' => 8.0],
        ['name' => 'Opposition',  'angle' => 180.0, 'orb' => 8.0],
        ['name' => 'Trine',       'angle' => 120.0, 'orb' => 6.0],
        ['name' => 'Square',      'angle' => 90.0,  'orb' => 6.0],
        ['name' => 'Sextile',     'angle' => 60.0,  'orb' => 4.0],
        ['name' => 'Quincunx',    'angle' => 150.0, 'orb' => 3.0],
        ['name' => 'Semisextile', 'angle' => 30.0,  'orb' => 2.0],
    ];

    private const LUMINARY_EXTRA = 2.0; // Additional orb allowance distributed between luminaries

    private function __construct()
    {
        // This class only exposes static helpers.
    }

    /**
     * Calculate transit positions for the supplied chart and timestamp.
     *
     * @param int                $chartId          Chart identifier to load
     * @param int                $userId           Owner of the chart (ensures access control)
     * @param DateTimeInterface  $targetDateTime   Moment to evaluate (any timezone)
     * @param float|null         $observerLatitude Optional override latitude for transit calculation
     * @param float|null         $observerLongitude Optional override longitude for transit calculation
     *
     * @return array<string,mixed>
     */
    public static function calculate(
        int $chartId,
        int $userId,
        DateTimeInterface $targetDateTime,
        ?float $observerLatitude = null,
        ?float $observerLongitude = null
    ): array {
        $chart = self::loadChart($chartId, $userId);

        $lat = $observerLatitude ?? $chart['birth_latitude'];
        $lon = $observerLongitude ?? $chart['birth_longitude'];

        if (!is_finite($lat) || !is_finite($lon)) {
            throw new RuntimeException('Chart has no stored coordinates for transit calculation.');
        }

        $utcTarget = self::toUtc($targetDateTime);

        $transitRaw = SwissEphemeris::positions($utcTarget, $lat, $lon);
        $transitPlanets = self::normalisePlanets($transitRaw);
        if ($transitPlanets === []) {
            throw new RuntimeException('Swiss Ephemeris returned no transiting bodies.');
        }

        $natalPlanets = self::normalisePlanets($chart['planets']);
        if ($natalPlanets === []) {
            throw new RuntimeException('Chart is missing natal planetary positions.');
        }

        $houseCusps = self::extractHouseCusps($chart['houses']);
        $crossAspects = self::computeCrossAspects($transitPlanets, $natalPlanets);

        $transitEntries = [];
        foreach ($transitPlanets as $slug => $planet) {
            $house = $houseCusps ? self::locateHouse($planet['longitude'], $houseCusps) : null;
            $transitEntries[] = [
                'planet' => $planet['name'],
                'slug' => $slug,
                'longitude' => $planet['longitude'],
                'house' => $house,
                'has_aspects' => !empty($crossAspects[$slug]),
                'aspects' => array_values($crossAspects[$slug] ?? []),
            ];
        }

        return [
            'chart' => [
                'id' => $chart['id'],
                'name' => $chart['name'],
                'house_system' => $chart['house_system'],
            ],
            'target' => [
                'datetime_utc' => $utcTarget->format(DATE_ATOM),
                'datetime_local' => $targetDateTime->format('Y-m-d H:i:s'),
                'timezone' => $targetDateTime->getTimezone() ? $targetDateTime->getTimezone()->getName() : 'UTC',
            ],
            'observer' => [
                'latitude' => $lat,
                'longitude' => $lon,
            ],
            'transits' => $transitEntries,
            'metadata' => [
                'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
                'aspect_set' => array_map(
                    static fn(array $aspect): array => [
                        'name' => $aspect['name'],
                        'angle' => $aspect['angle'],
                        'orb' => $aspect['orb'],
                    ],
                    self::ASPECT_SET
                ),
            ],
        ];
    }

    /**
     * Load a chart and ensure the requesting user owns it.
     *
     * @return array{
     *     id:int,
     *     name:string,
     *     user_id:int,
     *     house_system:string,
     *     birth_latitude:float,
     *     birth_longitude:float,
     *     planets:array,
     *     houses:array
     * }
     */
    private static function loadChart(int $chartId, int $userId): array
    {
        if ($chartId <= 0) {
            throw new InvalidArgumentException('A valid chart id must be provided.');
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException('A valid user id must be provided.');
        }

        $pdo = DB::conn();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, name, birth_latitude, birth_longitude, house_system, planets_json, houses_json ' .
            'FROM charts WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $chartId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Chart not found.');
        }

        $ownerId = $row['user_id'] !== null ? (int)$row['user_id'] : null;
        if ($ownerId === null || $ownerId !== $userId) {
            throw new RuntimeException('Chart is not accessible to this user.');
        }

        $planets = self::decodeJson($row['planets_json'] ?? null, 'planets_json', true);
        $houses = self::decodeJson($row['houses_json'] ?? null, 'houses_json', false);

        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'user_id' => $ownerId,
            'house_system' => (string)($row['house_system'] ?? 'P'),
            'birth_latitude' => isset($row['birth_latitude']) ? (float)$row['birth_latitude'] : NAN,
            'birth_longitude' => isset($row['birth_longitude']) ? (float)$row['birth_longitude'] : NAN,
            'planets' => $planets,
            'houses' => $houses,
        ];
    }

    /**
     * Decode JSON stored in the database and ensure we always return an array.
     *
     * @param bool $required When true, missing/invalid payloads throw exceptions
     * @return array<mixed>
     */
    private static function decodeJson(?string $json, string $field, bool $required): array
    {
        if ($json === null || $json === '') {
            if ($required) {
                throw new RuntimeException(sprintf('Chart is missing %s data.', $field));
            }
            return [];
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('Failed decoding %s: %s', $field, json_last_error_msg()));
        }

        if (!is_array($decoded)) {
            if ($required) {
                throw new RuntimeException(sprintf('Chart %s data is invalid.', $field));
            }
            return [];
        }

        return $decoded;
    }

    /**
     * Normalise a list of planetary rows into a keyed structure.
     *
     * @param array<int, array<string,mixed>> $rows
     * @return array<string, array{slug:string,name:string,longitude:float}>
     */
    private static function normalisePlanets(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rawName = (string)($row['planet'] ?? $row['name'] ?? '');
            $slug = self::planetSlug($rawName);
            if ($slug === '') {
                continue;
            }

            $longitude = null;
            if (isset($row['lon'])) {
                $longitude = (float)$row['lon'];
            } elseif (isset($row['longitude'])) {
                $longitude = (float)$row['longitude'];
            }

            if ($longitude === null) {
                continue;
            }

            $out[$slug] = [
                'slug' => $slug,
                'name' => self::formatPlanetName($rawName),
                'longitude' => self::wrap360((float)$longitude),
            ];
        }

        return $out;
    }

    /**
     * Extract a simple house cusp array keyed 1..12.
     *
     * @param array<mixed> $houses
     * @return array<int,float>
     */
    private static function extractHouseCusps(array $houses): array
    {
        if ($houses === []) {
            return [];
        }

        $source = [];
        if (isset($houses['cusps']) && is_array($houses['cusps'])) {
            $source = $houses['cusps'];
        } elseif (isset($houses[1]) && is_array($houses[1]) && array_key_exists('cusp', $houses[1])) {
            for ($i = 1; $i <= 12; $i++) {
                if (isset($houses[$i]['cusp'])) {
                    $source[$i] = $houses[$i]['cusp'];
                }
            }
        } else {
            $source = $houses;
        }

        $cusps = [];
        for ($i = 1; $i <= 12; $i++) {
            if (!isset($source[$i])) {
                continue;
            }
            $cusps[$i] = self::wrap360((float)$source[$i]);
        }

        return $cusps;
    }

    /**
     * Determine the natal house for a longitude.
     *
     * @param array<int,float> $cusps
     */
    private static function locateHouse(float $longitude, array $cusps): ?int
    {
        if (count($cusps) < 12) {
            return null;
        }

        $lon = self::wrap360($longitude);

        for ($house = 1; $house <= 12; $house++) {
            $start = $cusps[$house];
            $nextIndex = $house === 12 ? 1 : $house + 1;
            $end = $cusps[$nextIndex];

            $startNorm = $start;
            $endNorm = $end;
            if ($endNorm <= $startNorm) {
                $endNorm += 360.0;
            }

            $lonNorm = $lon;
            if ($lonNorm < $startNorm) {
                $lonNorm += 360.0;
            }

            if ($lonNorm >= $startNorm && $lonNorm < $endNorm) {
                return $house;
            }
        }

        return null;
    }

    /**
     * Compute aspects between transiting and natal planets.
     *
     * @param array<string, array{slug:string,name:string,longitude:float}> $transits
     * @param array<string, array{slug:string,name:string,longitude:float}> $natal
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    private static function computeCrossAspects(array $transits, array $natal): array
    {
        $result = [];

        foreach ($transits as $transitSlug => $transit) {
            $list = [];

            foreach ($natal as $natalSlug => $natalPlanet) {
                $sep = self::angularSeparation($transit['longitude'], $natalPlanet['longitude']);

                foreach (self::ASPECT_SET as $aspect) {
                    $target = (float)$aspect['angle'];
                    $baseOrb = (float)$aspect['orb'];
                    $orb = self::aspectOrb($baseOrb, $transitSlug, $natalSlug);
                    $delta = abs($sep - $target);

                    if ($delta <= $orb) {
                        $list[] = [
                            'natal_planet' => $natalPlanet['name'],
                            'natal_slug' => $natalSlug,
                            'type' => $aspect['name'],
                            'angle' => $target,
                            'orb' => $orb,
                            'delta' => round($delta, 3),
                            'within' => round($orb - $delta, 3),
                            'exact' => $delta < 0.1,
                            'natal_longitude' => $natalPlanet['longitude'],
                            'transit_longitude' => $transit['longitude'],
                        ];
                        break;
                    }
                }
            }

            if ($list) {
                usort($list, static fn(array $a, array $b): int => $a['delta'] <=> $b['delta']);
            }

            $result[$transitSlug] = $list;
        }

        return $result;
    }

    private static function aspectOrb(float $baseOrb, string $transitSlug, string $natalSlug): float
    {
        $orb = $baseOrb;
        $luminaryCount = (self::isLuminary($transitSlug) ? 1 : 0) + (self::isLuminary($natalSlug) ? 1 : 0);
        if ($luminaryCount > 0) {
            $orb += $luminaryCount * (self::LUMINARY_EXTRA / 2.0);
        }
        return $orb;
    }

    private static function isLuminary(string $slug): bool
    {
        $slug = strtolower($slug);
        return $slug === 'sun' || $slug === 'moon';
    }

    private static function angularSeparation(float $a, float $b): float
    {
        $a = self::wrap360($a);
        $b = self::wrap360($b);
        $diff = abs($a - $b);
        $diff = fmod($diff, 360.0);
        if ($diff > 180.0) {
            $diff = 360.0 - $diff;
        }
        return $diff;
    }

    private static function wrap360(float $deg): float
    {
        $value = fmod($deg, 360.0);
        if ($value < 0) {
            $value += 360.0;
        }
        return round($value, 6);
    }

    private static function planetSlug(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return '';
        }

        $raw = str_replace(['-', '/', '\\'], ' ', $raw);
        $raw = preg_replace('/\s+/', ' ', $raw);
        $raw = preg_replace('/[^a-z0-9 ]/', '', $raw) ?? $raw;
        $raw = trim($raw);
        $slug = str_replace(' ', '_', $raw);
        return trim($slug, '_');
    }

    private static function formatPlanetName(string $raw): string
    {
        $clean = trim(str_replace('_', ' ', $raw));
        if ($clean === '') {
            return 'Unknown';
        }

        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $lower = strtolower($clean);
        $map = [
            'mean node' => 'Mean Node',
            'true node' => 'True Node',
            'north node' => 'North Node',
            'south node' => 'South Node',
        ];

        return $map[$lower] ?? ucwords($lower);
    }

    private static function toUtc(DateTimeInterface $dt): DateTimeImmutable
    {
        $immutable = $dt instanceof DateTimeImmutable ? $dt : DateTimeImmutable::createFromInterface($dt);
        return $immutable->setTimezone(new DateTimeZone('UTC'));
    }
}
