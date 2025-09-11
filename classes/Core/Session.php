<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use QuantumAstrology\Core\User;

class Session
{
    private static bool $started = false;

    /**
     * Start session and enforce inactivity timeout.
     */
    public static function start(): void
    {
        if (!self::$started && session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$started = true;
        } elseif (!self::$started) {
            self::$started = true;
        }

        $lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600;
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $lifetime) {
            self::logout();
            session_start();
            self::$started = true;
        }

        $_SESSION['LAST_ACTIVITY'] = time();
    }

    /**
     * Log in a user and regenerate the session ID.
     */
    public static function login(User $user): void
    {
        self::start();
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_username'] = $user->getUsername();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['logged_in'] = true;

        session_regenerate_id(true);
    }

    /**
     * Log out the current user and destroy the session.
     */
    public static function logout(): void
    {
        if (!self::$started && session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$started = true;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Determine if a user is logged in.
     */
    public static function isLoggedIn(): bool
    {
        self::start();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Retrieve the currently logged-in user model.
     */
    public static function getCurrentUser(): ?User
    {
        self::start();

        if (self::isLoggedIn() && isset($_SESSION['user_id'])) {
            return User::findById((int) $_SESSION['user_id']);
        }

        return null;
    }

    /**
     * Retrieve the current user ID if logged in.
     */
    public static function getCurrentUserId(): ?int
    {
        self::start();

        if (self::isLoggedIn() && isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        return null;
    }

    /**
     * Set a session variable.
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session variable or default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Determine if a session key exists.
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key.
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Store a flash message for retrieval on next request.
     */
    public static function flash(string $key, string $message): void
    {
        self::start();
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Retrieve and remove a flash message.
     */
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

    /**
     * Determine if a flash message exists.
     */
    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['flash'][$key]);
    }

    /**
     * Retrieve all flash messages and clear them.
     *
     * @return array<string, string>
     */
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