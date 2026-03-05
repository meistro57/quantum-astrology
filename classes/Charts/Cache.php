<?php
# classes/Charts/Cache.php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\DB;
use QuantumAstrology\Database\Connection;
use QuantumAstrology\Support\RedisStore;

final class Cache
{
    private const REDIS_PREFIX = 'qa:calc_cache:';

    public static function key(\DateTimeImmutable $utc, float $lat, float $lon, string $hsys): string
    {
        $payload = json_encode([
            'utc' => $utc->format('c'),
            'lat' => round($lat, 6),
            'lon' => round($lon, 6),
            'hs'  => strtoupper($hsys),
        ], JSON_UNESCAPED_SLASHES);
        return sha1((string)$payload);
    }

    /** @return array{planets:array, houses:array}|null */
    public static function get(string $hash): ?array
    {
        $redisKey = self::REDIS_PREFIX . $hash;
        $redisPayload = RedisStore::get($redisKey);
        if (is_string($redisPayload) && $redisPayload !== '') {
            $decoded = json_decode($redisPayload, true);
            if (is_array($decoded)) {
                return [
                    'planets' => is_array($decoded['planets'] ?? null) ? $decoded['planets'] : [],
                    'houses' => is_array($decoded['houses'] ?? null) ? $decoded['houses'] : [],
                ];
            }
        }

        $pdo = DB::conn();
        $stmt = $pdo->prepare("SELECT planets_json, houses_json FROM calc_cache WHERE calc_hash = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $result = [
            'planets' => json_decode($row['planets_json'], true) ?? [],
            'houses'  => json_decode($row['houses_json'], true) ?? [],
        ];

        // Warm Redis opportunistically after a DB hit.
        RedisStore::set($redisKey, (string)json_encode($result, JSON_UNESCAPED_SLASHES), max(60, CACHE_TTL));
        return $result;
    }

    public static function put(string $hash, array $planets, array $houses): void
    {
        $cachePayload = [
            'planets' => $planets,
            'houses' => $houses,
        ];
        RedisStore::set(
            self::REDIS_PREFIX . $hash,
            (string)json_encode($cachePayload, JSON_UNESCAPED_SLASHES),
            max(60, CACHE_TTL)
        );

        $pdo = DB::conn();
        if (Connection::isMySql()) {
            $sql = "
                INSERT INTO calc_cache (calc_hash, planets_json, houses_json)
                VALUES (:h, :p, :h2)
                ON DUPLICATE KEY UPDATE
                    planets_json = VALUES(planets_json),
                    houses_json = VALUES(houses_json)
            ";
        } else {
            $sql = "
                INSERT INTO calc_cache (calc_hash, planets_json, houses_json)
                VALUES (:h, :p, :h2)
                ON CONFLICT(calc_hash) DO UPDATE SET
                    planets_json = excluded.planets_json,
                    houses_json = excluded.houses_json
            ";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':h'  => $hash,
            ':p'  => json_encode($planets, JSON_UNESCAPED_SLASHES),
            ':h2' => json_encode($houses,  JSON_UNESCAPED_SLASHES),
        ]);
    }
}
