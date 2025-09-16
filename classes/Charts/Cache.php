<?php
# classes/Charts/Cache.php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\DB;

final class Cache
{
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
        $pdo = DB::conn();
        $stmt = $pdo->prepare("SELECT planets_json, houses_json FROM calc_cache WHERE calc_hash = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return [
            'planets' => json_decode($row['planets_json'], true) ?? [],
            'houses'  => json_decode($row['houses_json'], true) ?? [],
        ];
    }

    public static function put(string $hash, array $planets, array $houses): void
    {
        $pdo = DB::conn();
        $stmt = $pdo->prepare("
            INSERT INTO calc_cache (calc_hash, planets_json, houses_json)
            VALUES (:h, :p, :h2)
            ON DUPLICATE KEY UPDATE planets_json = VALUES(planets_json), houses_json = VALUES(houses_json)
        ");
        $stmt->execute([
            ':h'  => $hash,
            ':p'  => json_encode($planets, JSON_UNESCAPED_SLASHES),
            ':h2' => json_encode($houses,  JSON_UNESCAPED_SLASHES),
        ]);
    }
}
