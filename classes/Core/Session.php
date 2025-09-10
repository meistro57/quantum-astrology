<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Core\User;

class Session
{
    private static bool $started = false;
    
    public static function start(): void
    {
        if (!self::$started && session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$started = true;
        }
    }
    
    public static function login(User $user): void
    {
        self::start();
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_username'] = $user->getUsername();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['logged_in'] = true;
        
        session_regenerate_id(true);
    }
    
    public static function logout(): void
    {
        self::start();
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        self::$started = false;
    }
    
    public static function isLoggedIn(): bool
    {
        self::start();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public static function getCurrentUser(): ?User
    {
        self::start();
        
        if (self::isLoggedIn() && isset($_SESSION['user_id'])) {
            return User::findById((int) $_SESSION['user_id']);
        }
        
        return null;
    }
    
    public static function getCurrentUserId(): ?int
    {
        self::start();
        
        if (self::isLoggedIn() && isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
        
        return null;
    }
    
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }
    
    public static function flash(string $key, string $message): void
    {
        self::start();
        $_SESSION['flash'][$key] = $message;
    }
    
    public static function getFlash(string $key): ?string
    {
        self::start();
        
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        
        return null;
    }
    
    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['flash'][$key]);
    }
    
    public static function getAllFlash(): array
    {
        self::start();
        
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = [];
            return $flash;
        }
        
        return [];
    }
}