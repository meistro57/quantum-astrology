<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Core\DB;
use QuantumAstrology\Core\Logger;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

class Chart
{
    private ?int $id = null;
    private ?int $userId = null;
    private ?string $name = null;
    private ?string $chartType = null;
    private ?DateTime $birthDatetime = null;
    private ?string $birthTimezone = null;
    private ?float $birthLatitude = null;
    private ?float $birthLongitude = null;
    private ?string $birthLocationName = null;
    private ?string $houseSystem = null;
    private ?array $chartData = null;
    private ?array $planetaryPositions = null;
    private ?array $housePositions = null;
    private ?array $aspects = null;
    private ?array $calculationMetadata = null;
    private bool $isPublic = false;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    public static function create(array $chartData): ?self
    {
        try {
            $pdo = DB::conn();
            
            $sql = "INSERT INTO charts (
                user_id, name, is_public, birth_datetime, birth_timezone, 
                birth_latitude, birth_longitude, house_system,
                planetary_positions, house_positions, aspects, 
                created_at, updated_at
            ) VALUES (
                :user_id, :name, :is_public, :birth_datetime, :birth_timezone,
                :birth_latitude, :birth_longitude, :house_system,
                :planetary_positions, :house_positions, :aspects,
                :created_at, :updated_at
            )";

            // Prepare defaults
            $now = date('Y-m-d H:i:s');
            
            $params = [
                ':user_id' => $chartData['user_id'] ?? null,
                ':name' => $chartData['name'],
                ':is_public' => isset($chartData['is_public']) ? (int)$chartData['is_public'] : 0,
                ':birth_datetime' => $chartData['birth_datetime'],
                ':birth_timezone' => $chartData['birth_timezone'] ?? 'UTC',
                ':birth_latitude' => $chartData['birth_latitude'],
                ':birth_longitude' => $chartData['birth_longitude'],
                ':house_system' => $chartData['house_system'] ?? 'P',
                ':planetary_positions' => is_array($chartData['planetary_positions']) 
                    ? json_encode($chartData['planetary_positions'], JSON_UNESCAPED_SLASHES) 
                    : $chartData['planetary_positions'],
                ':house_positions' => is_array($chartData['house_positions']) 
                    ? json_encode($chartData['house_positions'], JSON_UNESCAPED_SLASHES) 
                    : $chartData['house_positions'],
                ':aspects' => is_array($chartData['aspects']) 
                    ? json_encode($chartData['aspects'], JSON_UNESCAPED_SLASHES) 
                    : $chartData['aspects'],
                ':created_at' => $now,
                ':updated_at' => $now
            ];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $chartId = $pdo->lastInsertId();
            return self::findById((int) $chartId);
            
        } catch (\Throwable $e) {
            Logger::error("Chart creation failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function findById(int $id): ?self
    {
        $pdo = DB::conn();
        $stmt = $pdo->prepare("SELECT * FROM charts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return self::fromArray($row);
        }
        
        return null;
    }

    public static function findByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $pdo = DB::conn();
        // Ensure standard pagination
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $stmt = $pdo->prepare("SELECT * FROM charts WHERE user_id = :user_id ORDER BY id DESC LIMIT :lim OFFSET :off");
        
        // PDO bindValue for integers is safer for LIMIT/OFFSET
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'fromArray'], $results);
    }

    /**
     * Generate calculations using the static SwissEphemeris class
     */
    public static function generateNatalChart(array $birthData): ?self
    {
        try {
            // 1. Prepare Date in UTC
            $tz = new DateTimeZone($birthData['birth_timezone'] ?? 'UTC');
            $localDate = new DateTimeImmutable($birthData['birth_datetime'], $tz);
            $utcDate = $localDate->setTimezone(new DateTimeZone('UTC'));

            $lat = (float)$birthData['birth_latitude'];
            $lon = (float)$birthData['birth_longitude'];
            $hsys = $birthData['house_system'] ?? 'P';

            // 2. Call Swiss Ephemeris (Static Methods)
            // Note: This matches the signature in your SwissEphemeris.php file
            $planetaryPositions = SwissEphemeris::positions($utcDate, $lat, $lon);
            $housePositions = SwissEphemeris::houses($utcDate, $lat, $lon, $hsys);

            // 3. Calculate Aspects
            $aspects = self::calculateAspects($planetaryPositions);

            // 4. Prepare for Storage
            $chartData = [
                'user_id' => $birthData['user_id'],
                'name' => $birthData['name'],
                'birth_datetime' => $localDate->format('Y-m-d H:i:s'),
                'birth_timezone' => $tz->getName(),
                'birth_latitude' => $lat,
                'birth_longitude' => $lon,
                'house_system' => $hsys,
                'planetary_positions' => $planetaryPositions,
                'house_positions' => $housePositions,
                'aspects' => $aspects,
                'is_public' => $birthData['is_public'] ?? false
            ];

            return self::create($chartData);

        } catch (\Throwable $e) {
            Logger::error("Natal chart generation failed", [
                'error' => $e->getMessage(),
                'data' => $birthData
            ]);
            return null;
        }
    }

    /**
     * Compute aspects based on the array list returned by SwissEphemeris
     */
    public static function calculateAspects(array $planetaryPositions): array
    {
        $aspects = [];
        // Standard Orbs
        $aspectDefinitions = [
            'Conjunction' => ['angle' => 0,   'orb' => 8],
            'Opposition'  => ['angle' => 180, 'orb' => 8],
            'Trine'       => ['angle' => 120, 'orb' => 8],
            'Square'      => ['angle' => 90,  'orb' => 8],
            'Sextile'     => ['angle' => 60,  'orb' => 6],
            'Quincunx'    => ['angle' => 150, 'orb' => 3]
        ];

        $count = count($planetaryPositions);
        
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $p1 = $planetaryPositions[$i];
                $p2 = $planetaryPositions[$j];

                // Skip calculated points like Nodes if desired, or keep them.
                // Assuming 'lon' key exists based on SwissEphemeris.php
                if (!isset($p1['lon']) || !isset($p2['lon'])) continue;

                $angle = abs($p1['lon'] - $p2['lon']);
                if ($angle > 180) {
                    $angle = 360 - $angle;
                }

                foreach ($aspectDefinitions as $name => $def) {
                    $delta = abs($angle - $def['angle']);
                    if ($delta <= $def['orb']) {
                        $aspects[] = [
                            'planet1' => $p1['planet'] ?? $p1['name'],
                            'planet2' => $p2['planet'] ?? $p2['name'],
                            'aspect' => $name, // e.g. "Trine"
                            'orb' => $delta,
                            'angle' => $angle
                        ];
                        // Don't break here if you want to support multiple overlapping aspect definitions (rare),
                        // but usually one aspect per pair is enough.
                        break; 
                    }
                }
            }
        }

        return $aspects;
    }

    // -- Active Record Methods --

    public function delete(): bool
    {
        try {
            $pdo = DB::conn();
            $stmt = $pdo->prepare("DELETE FROM charts WHERE id = :id");
            return $stmt->execute([':id' => $this->id]);
        } catch (\Throwable $e) {
            Logger::error("Chart deletion failed", ['id' => $this->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private static function fromArray(array $data): self
    {
        $chart = new self();
        $chart->id = (int) $data['id'];
        $chart->userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $chart->name = $data['name'];
        
        if (!empty($data['birth_datetime'])) {
            try {
                $chart->birthDatetime = new DateTime($data['birth_datetime']);
            } catch (\Exception $e) {
                // Handle invalid date
            }
        }
        
        $chart->birthTimezone = $data['birth_timezone'] ?? 'UTC';
        $chart->birthLatitude = (float) ($data['birth_latitude'] ?? 0);
        $chart->birthLongitude = (float) ($data['birth_longitude'] ?? 0);
        $chart->houseSystem = $data['house_system'] ?? 'P';
        
        // Decode JSON columns
        $chart->planetaryPositions = !empty($data['planetary_positions']) ? json_decode($data['planetary_positions'], true) : [];
        $chart->housePositions = !empty($data['house_positions']) ? json_decode($data['house_positions'], true) : [];
        $chart->aspects = !empty($data['aspects']) ? json_decode($data['aspects'], true) : [];
        
        $chart->isPublic = (bool) ($data['is_public'] ?? false);
        $chart->createdAt = $data['created_at'] ?? null;
        $chart->updatedAt = $data['updated_at'] ?? null;
        
        return $chart;
    }

    // -- Getters --
    public function getId(): ?int { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function getName(): ?string { return $this->name; }
    public function getBirthDatetime(): ?DateTime { return $this->birthDatetime; }
    public function getBirthLatitude(): float { return $this->birthLatitude ?? 0.0; }
    public function getBirthLongitude(): float { return $this->birthLongitude ?? 0.0; }
    public function getPlanetaryPositions(): array { return $this->planetaryPositions ?? []; }
    public function getHousePositions(): array { return $this->housePositions ?? []; }
    public function getHouseSystem(): string { return $this->houseSystem ?? 'P'; }
    public function getAspects(): array { return $this->aspects ?? []; }
    
    // Helper to get positions as a keyed array if needed by other services
    public function getPlanetaryPositionsKeyed(): array {
        $keyed = [];
        foreach ($this->getPlanetaryPositions() as $p) {
            $key = strtolower($p['planet'] ?? $p['name'] ?? 'unknown');
            $keyed[$key] = $p;
        }
        return $keyed;
    }
}
