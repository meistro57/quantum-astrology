<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Database\Connection;
use PDOException;

class User
{
    private ?int $id = null;
    private ?string $username = null;
    private ?string $email = null;
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $timezone = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    public static function create(array $userData): ?self
    {
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        try {
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, timezone, created_at, updated_at) 
                    VALUES (:username, :email, :password_hash, :first_name, :last_name, :timezone, NOW(), NOW())";
            
            $params = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password_hash' => $hashedPassword,
                'first_name' => $userData['first_name'] ?? null,
                'last_name' => $userData['last_name'] ?? null,
                'timezone' => $userData['timezone'] ?? 'UTC'
            ];
            
            Connection::query($sql, $params);
            
            return self::findByEmail($userData['email']);
        } catch (PDOException $e) {
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
            $sql = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";
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
        $allowedFields = ['username', 'email', 'first_name', 'last_name', 'timezone'];
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
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
        
        try {
            Connection::query($sql, $params);
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
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
            $sql = "UPDATE users SET last_login_at = NOW() WHERE id = :id";
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
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}