<?php
# classes/Charts/ChartService.php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\DB;
use QuantumAstrology\Core\Logger;

final class ChartService
{
    /** @return array{id:int, name:string, planets:array, houses:array, aspects:array} */
    public static function create(
        string $name,
        string $birthDate,
        string $birthTime,
        string $birthTimezone,
        float  $birthLat,
        float  $birthLon,
        string $houseSystem = 'P'
    ): array {
        try {
            if ($name === '') { throw new \InvalidArgumentException('Name required'); }

            $tz = @new \DateTimeZone($birthTimezone);
            if (!$tz) throw new \InvalidArgumentException('Invalid timezone');

            $local = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%s %s', $birthDate, preg_match('/^\d{2}:\d{2}:\d{2}$/', $birthTime) ? $birthTime : ($birthTime . ':00')),
                $tz
            );
            if (!$local) throw new \InvalidArgumentException('Invalid date/time');
            $utc = $local->setTimezone(new \DateTimeZone('UTC'));

            // ---- cache lookup
            $key = Cache::key($utc, $birthLat, $birthLon, $houseSystem);
            $cached = Cache::get($key);

            if ($cached) {
                $planets = $cached['planets'];
                $houses  = $cached['houses'];
            } else {
                $planets = SwissEphemeris::positions($utc, $birthLat, $birthLon);
                $houses  = SwissEphemeris::houses($utc, $birthLat, $birthLon, $houseSystem);
                Cache::put($key, $planets, $houses);
            }

            $aspects = Aspects::compute($planets);

            $pdo = DB::conn();
            $stmt = $pdo->prepare("
                INSERT INTO charts
                  (user_id, name, birth_datetime, birth_timezone, birth_latitude, birth_longitude, house_system, is_public, planetary_positions, house_positions, aspects)
                VALUES
                  (NULL,    :name, :dt,             :tz,            :lat,           :lon,            :hsys,        0,        :planets,     :houses,     :aspects)
            ");
            $stmt->execute([
                ':name'    => $name,
                ':dt'      => $local->format('Y-m-d H:i:s'),
                ':tz'      => $birthTimezone,
                ':lat'     => $birthLat,
                ':lon'     => $birthLon,
                ':hsys'    => strtoupper($houseSystem),
                ':planets' => json_encode($planets, JSON_UNESCAPED_SLASHES),
                ':houses'  => json_encode($houses,  JSON_UNESCAPED_SLASHES),
                ':aspects' => json_encode($aspects, JSON_UNESCAPED_SLASHES),
            ]);
            $id = (int)$pdo->lastInsertId();

            return ['id' => $id, 'name' => $name, 'planets' => $planets, 'houses' => $houses, 'aspects' => $aspects];
        } catch (\Throwable $e) {
            Logger::error('Chart generation failed', [
                'name' => $name,
                'birth_date' => $birthDate,
                'birth_time' => $birthTime,
                'birth_timezone' => $birthTimezone,
                'birth_latitude' => $birthLat,
                'birth_longitude' => $birthLon,
                'house_system' => $houseSystem,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** @return array|null */
    public static function get(int $id): ?array
    {
        $pdo = DB::conn();
        $stmt = $pdo->prepare("SELECT * FROM charts WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        return [
            'id'       => (int)$row['id'],
            'name'     => $row['name'],
            'birth'    => [
                'datetime' => $row['birth_datetime'],
                'timezone' => $row['birth_timezone'],
                'lat'      => (float)$row['birth_latitude'],
                'lon'      => (float)$row['birth_longitude'],
                'house'    => $row['house_system'],
            ],
            'planets'  => json_decode($row['planetary_positions'] ?? '', true) ?? [],
            'houses'   => json_decode($row['house_positions'] ?? '',  true) ?? [],
            'aspects'  => json_decode($row['aspects'] ?? '', true) ?? [],
            'created'  => $row['created_at'],
            'updated'  => $row['updated_at'],
        ];
    }
}
