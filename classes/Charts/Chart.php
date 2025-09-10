<?php
declare(strict_types=1);

namespace QuantumAstrology\Charts;

use QuantumAstrology\Database\Connection;
use QuantumAstrology\Core\SwissEphemeris;
use QuantumAstrology\Core\Logger;
use PDOException;
use DateTime;
use DateTimeZone;

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
            $sql = "INSERT INTO charts (
                user_id, name, chart_type, birth_datetime, birth_timezone, 
                birth_latitude, birth_longitude, birth_location_name, house_system,
                chart_data, planetary_positions, house_positions, aspects, 
                calculation_metadata, is_public, created_at, updated_at
            ) VALUES (
                :user_id, :name, :chart_type, :birth_datetime, :birth_timezone,
                :birth_latitude, :birth_longitude, :birth_location_name, :house_system,
                :chart_data, :planetary_positions, :house_positions, :aspects,
                :calculation_metadata, :is_public, NOW(), NOW()
            )";

            $params = [
                'user_id' => $chartData['user_id'],
                'name' => $chartData['name'],
                'chart_type' => $chartData['chart_type'] ?? 'natal',
                'birth_datetime' => $chartData['birth_datetime'],
                'birth_timezone' => $chartData['birth_timezone'] ?? 'UTC',
                'birth_latitude' => $chartData['birth_latitude'],
                'birth_longitude' => $chartData['birth_longitude'],
                'birth_location_name' => $chartData['birth_location_name'] ?? null,
                'house_system' => $chartData['house_system'] ?? 'P',
                'chart_data' => json_encode($chartData['chart_data'] ?? null),
                'planetary_positions' => json_encode($chartData['planetary_positions'] ?? null),
                'house_positions' => json_encode($chartData['house_positions'] ?? null),
                'aspects' => json_encode($chartData['aspects'] ?? null),
                'calculation_metadata' => json_encode($chartData['calculation_metadata'] ?? null),
                'is_public' => $chartData['is_public'] ?? false
            ];

            Connection::query($sql, $params);
            
            $chartId = Connection::getInstance()->lastInsertId();
            return self::findById((int) $chartId);
            
        } catch (PDOException $e) {
            Logger::error("Chart creation failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function findById(int $id): ?self
    {
        $sql = "SELECT * FROM charts WHERE id = :id LIMIT 1";
        $chartData = Connection::fetchOne($sql, ['id' => $id]);
        
        if ($chartData) {
            return self::fromArray($chartData);
        }
        
        return null;
    }

    public static function findByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM charts WHERE user_id = :user_id 
                ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $results = Connection::fetchAll($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);

        return array_map([self::class, 'fromArray'], $results);
    }

    public static function generateNatalChart(array $birthData): ?self
    {
        try {
            $swissEph = new SwissEphemeris();
            
            // Parse birth datetime with timezone
            $birthDateTime = new DateTime($birthData['birth_datetime']);
            if (isset($birthData['birth_timezone'])) {
                $birthDateTime->setTimezone(new DateTimeZone($birthData['birth_timezone']));
            }

            // Calculate planetary positions
            $planetaryPositions = $swissEph->calculatePlanetaryPositions(
                $birthDateTime,
                $birthData['birth_latitude'],
                $birthData['birth_longitude']
            );

            // Calculate house positions
            $housePositions = $swissEph->calculateHouses(
                $birthDateTime,
                $birthData['birth_latitude'],
                $birthData['birth_longitude'],
                $birthData['house_system'] ?? 'P'
            );

            // Calculate aspects
            $aspects = self::calculateAspects($planetaryPositions);

            // Create chart data
            $chartData = [
                'user_id' => $birthData['user_id'],
                'name' => $birthData['name'],
                'chart_type' => 'natal',
                'birth_datetime' => $birthDateTime->format('Y-m-d H:i:s'),
                'birth_timezone' => $birthData['birth_timezone'] ?? 'UTC',
                'birth_latitude' => $birthData['birth_latitude'],
                'birth_longitude' => $birthData['birth_longitude'],
                'birth_location_name' => $birthData['birth_location_name'] ?? null,
                'house_system' => $birthData['house_system'] ?? 'P',
                'planetary_positions' => $planetaryPositions,
                'house_positions' => $housePositions,
                'aspects' => $aspects,
                'calculation_metadata' => [
                    'calculated_at' => date('c'),
                    'swiss_ephemeris_version' => 'command_line',
                    'calculation_method' => 'natal_chart'
                ]
            ];

            return self::create($chartData);

        } catch (\Exception $e) {
            Logger::error("Natal chart generation failed", [
                'error' => $e->getMessage(),
                'birth_data' => $birthData
            ]);
            return null;
        }
    }

    private static function calculateAspects(array $planetaryPositions): array
    {
        $aspects = [];
        $aspectDefinitions = [
            'conjunction' => ['angle' => 0, 'orb' => 8],
            'sextile' => ['angle' => 60, 'orb' => 6],
            'square' => ['angle' => 90, 'orb' => 8],
            'trine' => ['angle' => 120, 'orb' => 8],
            'opposition' => ['angle' => 180, 'orb' => 8],
            'quincunx' => ['angle' => 150, 'orb' => 3],
            'semisextile' => ['angle' => 30, 'orb' => 2]
        ];

        $planets = array_keys($planetaryPositions);
        
        for ($i = 0; $i < count($planets); $i++) {
            for ($j = $i + 1; $j < count($planets); $j++) {
                $planet1 = $planets[$i];
                $planet2 = $planets[$j];
                
                if (!isset($planetaryPositions[$planet1]) || !isset($planetaryPositions[$planet2])) {
                    continue;
                }

                $pos1 = $planetaryPositions[$planet1]['longitude'];
                $pos2 = $planetaryPositions[$planet2]['longitude'];
                
                $angle = abs($pos1 - $pos2);
                if ($angle > 180) {
                    $angle = 360 - $angle;
                }

                foreach ($aspectDefinitions as $aspectName => $aspectData) {
                    $difference = abs($angle - $aspectData['angle']);
                    if ($difference <= $aspectData['orb']) {
                        $aspects[] = [
                            'planet1' => $planet1,
                            'planet2' => $planet2,
                            'aspect' => $aspectName,
                            'angle' => $angle,
                            'orb' => $difference,
                            'exact_angle' => $aspectData['angle'],
                            'applying' => self::isApplyingAspect($planetaryPositions[$planet1], $planetaryPositions[$planet2], $aspectData['angle'])
                        ];
                        break; // Only record the first matching aspect
                    }
                }
            }
        }

        return $aspects;
    }

    private static function isApplyingAspect(array $planet1, array $planet2, float $exactAngle): bool
    {
        // Simplified logic to determine if aspect is applying or separating
        $speed1 = $planet1['longitude_speed'] ?? 0;
        $speed2 = $planet2['longitude_speed'] ?? 0;
        
        // This is a simplified calculation - a more complete implementation would consider
        // the actual positions and speeds relative to the exact aspect angle
        return ($speed1 + $speed2) > 0;
    }

    public function update(array $data): bool
    {
        $allowedFields = ['name', 'chart_type', 'house_system', 'is_public'];
        $updateFields = [];
        $params = ['id' => $this->id];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            return true;
        }
        
        $updateFields[] = "updated_at = NOW()";
        $sql = "UPDATE charts SET " . implode(', ', $updateFields) . " WHERE id = :id";
        
        try {
            Connection::query($sql, $params);
            return true;
        } catch (PDOException $e) {
            Logger::error("Chart update failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function delete(): bool
    {
        try {
            $sql = "DELETE FROM charts WHERE id = :id";
            Connection::query($sql, ['id' => $this->id]);
            return true;
        } catch (PDOException $e) {
            Logger::error("Chart deletion failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private static function fromArray(array $data): self
    {
        $chart = new self();
        $chart->id = (int) $data['id'];
        $chart->userId = (int) $data['user_id'];
        $chart->name = $data['name'];
        $chart->chartType = $data['chart_type'];
        
        if ($data['birth_datetime']) {
            $chart->birthDatetime = new DateTime($data['birth_datetime']);
        }
        
        $chart->birthTimezone = $data['birth_timezone'];
        $chart->birthLatitude = (float) $data['birth_latitude'];
        $chart->birthLongitude = (float) $data['birth_longitude'];
        $chart->birthLocationName = $data['birth_location_name'];
        $chart->houseSystem = $data['house_system'];
        
        $chart->chartData = $data['chart_data'] ? json_decode($data['chart_data'], true) : null;
        $chart->planetaryPositions = $data['planetary_positions'] ? json_decode($data['planetary_positions'], true) : null;
        $chart->housePositions = $data['house_positions'] ? json_decode($data['house_positions'], true) : null;
        $chart->aspects = $data['aspects'] ? json_decode($data['aspects'], true) : null;
        $chart->calculationMetadata = $data['calculation_metadata'] ? json_decode($data['calculation_metadata'], true) : null;
        
        $chart->isPublic = (bool) $data['is_public'];
        $chart->createdAt = $data['created_at'];
        $chart->updatedAt = $data['updated_at'];
        
        return $chart;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function getName(): ?string { return $this->name; }
    public function getChartType(): ?string { return $this->chartType; }
    public function getBirthDatetime(): ?DateTime { return $this->birthDatetime; }
    public function getBirthTimezone(): ?string { return $this->birthTimezone; }
    public function getBirthLatitude(): ?float { return $this->birthLatitude; }
    public function getBirthLongitude(): ?float { return $this->birthLongitude; }
    public function getBirthLocationName(): ?string { return $this->birthLocationName; }
    public function getHouseSystem(): ?string { return $this->houseSystem; }
    public function getChartData(): ?array { return $this->chartData; }
    public function getPlanetaryPositions(): ?array { return $this->planetaryPositions; }
    public function getHousePositions(): ?array { return $this->housePositions; }
    public function getAspects(): ?array { return $this->aspects; }
    public function getCalculationMetadata(): ?array { return $this->calculationMetadata; }
    public function isPublic(): bool { return $this->isPublic; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'name' => $this->name,
            'chart_type' => $this->chartType,
            'birth_datetime' => $this->birthDatetime?->format('c'),
            'birth_timezone' => $this->birthTimezone,
            'birth_latitude' => $this->birthLatitude,
            'birth_longitude' => $this->birthLongitude,
            'birth_location_name' => $this->birthLocationName,
            'house_system' => $this->houseSystem,
            'planetary_positions' => $this->planetaryPositions,
            'house_positions' => $this->housePositions,
            'aspects' => $this->aspects,
            'calculation_metadata' => $this->calculationMetadata,
            'is_public' => $this->isPublic,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}