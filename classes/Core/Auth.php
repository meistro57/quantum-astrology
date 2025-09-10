<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Core\Session;
use QuantumAstrology\Core\User;

class Auth
{
    public const ERROR_DUPLICATE = User::ERROR_DUPLICATE;
    public static function requireLogin(?string $redirectTo = null): void
    {
        if (!Session::isLoggedIn()) {
            $redirectTo = $redirectTo ?? '/login';
            
            if (self::isApiRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Authentication required',
                    'code' => 401
                ]);
                exit;
            } else {
                Session::flash('error', 'Please log in to access this page.');
                self::redirect($redirectTo);
            }
        }
    }
    
    public static function requireGuest(?string $redirectTo = null): void
    {
        if (Session::isLoggedIn()) {
            $redirectTo = $redirectTo ?? '/dashboard';
            self::redirect($redirectTo);
        }
    }
    
    public static function user(): ?User
    {
        return Session::getCurrentUser();
    }
    
    public static function id(): ?int
    {
        return Session::getCurrentUserId();
    }
    
    public static function check(): bool
    {
        return Session::isLoggedIn();
    }
    
    public static function guest(): bool
    {
        return !Session::isLoggedIn();
    }
    
    public static function login(User $user): void
    {
        Session::login($user);
    }
    
    public static function logout(): void
    {
        Session::logout();
    }
    
    public static function attempt(string $emailOrUsername, string $password): bool
    {
        $user = User::authenticate($emailOrUsername, $password);
        
        if ($user) {
            self::login($user);
            return true;
        }
        
        return false;
    }
    
    /**
     * Register a new user.
     *
     * @param array<string, mixed> $userData
     * @return User|int|null Returns the created user, ERROR_DUPLICATE on unique constraint violation, or null on other failure
     */
    public static function register(array $userData): User|int|null
    {
        if (self::validateRegistration($userData)) {
            $result = User::create($userData);

            if ($result instanceof User) {
                self::login($result);
                return $result;
            }

            return $result; // propagate error codes
        }

        return null;
    }
    
    /**
     * Basic validation for registration data.
     *
     * @param array<string, mixed> $data
     */
    private static function validateRegistration(array $data): bool
    {
        $required = ['username', 'email', 'password'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (strlen($data['password']) < 8) {
            return false;
        }

        return true;
    }
    
    /**
     * Get validation errors for registration data.
     *
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    public static function getValidationErrors(array $data): array
    {
        $errors = [];

        if (empty($data['username'])) {
            $errors[] = 'Username is required';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (empty($data['password'])) {
            $errors[] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!empty($data['password_confirm']) && $data['password'] !== $data['password_confirm']) {
            $errors[] = 'Password confirmation does not match';
        }

        return $errors;
    }
    
    private static function isApiRequest(): bool
    {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0 ||
               strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    }
    
    private static function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }
}