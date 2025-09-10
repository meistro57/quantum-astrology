<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Core\Session;
use QuantumAstrology\Core\User;

class Auth
{
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
    
    public static function register(array $userData): ?User
    {
        if (self::validateRegistration($userData)) {
            $user = User::create($userData);
            
            if ($user) {
                self::login($user);
                return $user;
            }
        }
        
        return null;
    }
    
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
        
        if (User::findByEmail($data['email'])) {
            return false;
        }
        
        if (User::findByUsername($data['username'])) {
            return false;
        }
        
        return true;
    }
    
    public static function getValidationErrors(array $data): array
    {
        $errors = [];
        
        if (empty($data['username'])) {
            $errors[] = 'Username is required';
        } elseif (User::findByUsername($data['username'])) {
            $errors[] = 'Username is already taken';
        }
        
        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        } elseif (User::findByEmail($data['email'])) {
            $errors[] = 'Email is already registered';
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