<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Database\Connection;
use PDOException;

class User
{
    public const ERROR_DUPLICATE = 1;

    private ?int $id = null;
    private ?string $username = null;
    private ?string $email = null;
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $timezone = null;
    private ?string $birthDate = null;
    private ?string $birthTime = null;
    private ?string $birthTimezone = null;
    private ?float $birthLatitude = null;
    private ?float $birthLongitude = null;
    private ?string $birthLocationName = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    /**
     * Create a new user record.
     *
     * @param array<string, mixed> $userData
     * @return self|int|null Returns the created User, ERROR_DUPLICATE on unique constraint violation, or null on other failure
     */
    public static function create(array $userData): self|int|null
    {
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, timezone, birth_date, birth_time, birth_timezone, birth_latitude, birth_longitude, birth_location_name, created_at, updated_at)
                    VALUES (:username, :email, :password_hash, :first_name, :last_name, :timezone, :birth_date, :birth_time, :birth_timezone, :birth_latitude, :birth_longitude, :birth_location_name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

            $params = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password_hash' => $hashedPassword,
                'first_name' => $userData['first_name'] ?? null,
                'last_name' => $userData['last_name'] ?? null,
                'timezone' => $userData['timezone'] ?? 'UTC',
                'birth_date' => $userData['birth_date'] ?? null,
                'birth_time' => $userData['birth_time'] ?? null,
                'birth_timezone' => $userData['birth_timezone'] ?? null,
                'birth_latitude' => $userData['birth_latitude'] ?? null,
                'birth_longitude' => $userData['birth_longitude'] ?? null,
                'birth_location_name' => $userData['birth_location_name'] ?? null
            ];

            Connection::query($sql, $params);

            return self::findByEmail($userData['email']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                error_log("User creation failed due to duplicate entry: " . $e->getMessage());
                return self::ERROR_DUPLICATE;
            }

            error_log("User creation failed: " . $e->getMessage());
            return null;
        }
    }

    public static function findByEmail(string $email): ?self
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $userData = Connection::fetchOne($sql, ['email' => $email]);
        
        if ($userData) {
            return self::fromArray($userData);
        }
        
        return null;
    }

    public static function findByUsername(string $username): ?self
    {
        $sql = "SELECT * FROM users WHERE username = :username LIMIT 1";
        $userData = Connection::fetchOne($sql, ['username' => $username]);
        
        if ($userData) {
            return self::fromArray($userData);
        }
        
        return null;
    }

    public static function findById(int $id): ?self
    {
        $sql = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $userData = Connection::fetchOne($sql, ['id' => $id]);
        
        if ($userData) {
            return self::fromArray($userData);
        }
        
        return null;
    }

    public static function authenticate(string $emailOrUsername, string $password): ?self
    {
        $user = self::findByEmail($emailOrUsername) ?? self::findByUsername($emailOrUsername);
        
        if ($user && $user->verifyPassword($password)) {
            $user->updateLastLogin();
            return $user;
        }
        
        return null;
    }

    public function verifyPassword(string $password): bool
    {
        $sql = "SELECT password_hash FROM users WHERE id = :id";
        $result = Connection::fetchOne($sql, ['id' => $this->id]);
        
        if ($result && isset($result['password_hash'])) {
            return password_verify($password, $result['password_hash']);
        }
        
        return false;
    }

    public function updatePassword(string $newPassword): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $sql = "UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            Connection::query($sql, [
                'password_hash' => $hashedPassword,
                'id' => $this->id
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Password update failed: " . $e->getMessage());
            return false;
        }
    }

    public function update(array $data): bool
    {
        $allowedFields = [
            'username',
            'email',
            'first_name',
            'last_name',
            'timezone',
            'birth_date',
            'birth_time',
            'birth_timezone',
            'birth_latitude',
            'birth_longitude',
            'birth_location_name'
        ];
        $updateFields = [];
        $params = ['id' => $this->id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            return true;
        }
        
        $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
        
        try {
            Connection::query($sql, $params);
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $property = str_replace('_', '', ucwords($field, '_'));
                    $property[0] = strtolower($property[0]);
                    $this->$property = $data[$field];
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("User update failed: " . $e->getMessage());
            return false;
        }
    }

    private function updateLastLogin(): void
    {
        try {
            $sql = "UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id";
            Connection::query($sql, ['id' => $this->id]);
        } catch (PDOException $e) {
            error_log("Last login update failed: " . $e->getMessage());
        }
    }

    private static function fromArray(array $data): self
    {
        $user = new self();
        $user->id = (int) $data['id'];
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->firstName = $data['first_name'];
        $user->lastName = $data['last_name'];
        $user->timezone = $data['timezone'];
        $user->birthDate = $data['birth_date'] ?? null;
        $user->birthTime = $data['birth_time'] ?? null;
        $user->birthTimezone = $data['birth_timezone'] ?? null;
        $user->birthLatitude = isset($data['birth_latitude']) ? (float) $data['birth_latitude'] : null;
        $user->birthLongitude = isset($data['birth_longitude']) ? (float) $data['birth_longitude'] : null;
        $user->birthLocationName = $data['birth_location_name'] ?? null;
        $user->createdAt = $data['created_at'];
        $user->updatedAt = $data['updated_at'];
        
        return $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);
        return implode(' ', $parts) ?: $this->username ?: 'Unknown User';
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }

    public function getBirthTime(): ?string
    {
        return $this->birthTime;
    }

    public function getBirthTimezone(): ?string
    {
        return $this->birthTimezone;
    }

    public function getBirthLatitude(): ?float
    {
        return $this->birthLatitude;
    }

    public function getBirthLongitude(): ?float
    {
        return $this->birthLongitude;
    }

    public function getBirthLocationName(): ?string
    {
        return $this->birthLocationName;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'timezone' => $this->timezone,
            'birth_date' => $this->birthDate,
            'birth_time' => $this->birthTime,
            'birth_timezone' => $this->birthTimezone,
            'birth_latitude' => $this->birthLatitude,
            'birth_longitude' => $this->birthLongitude,
            'birth_location_name' => $this->birthLocationName,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}
