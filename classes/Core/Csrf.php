<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

class Csrf
{
    private const TOKEN_KEY = 'csrf_token';

    /**
     * Generate a new CSRF token and store it in the session.
     *
     * @return string Generated token
     */
    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::TOKEN_KEY, $token);
        return $token;
    }

    /**
     * Retrieve the current CSRF token, generating one if needed.
     *
     * @return string Current token
     */
    public static function getToken(): string
    {
        if (!Session::has(self::TOKEN_KEY)) {
            return self::generateToken();
        }

        $token = Session::get(self::TOKEN_KEY);
        return is_string($token) ? $token : self::generateToken();
    }

    /**
     * Validate the provided token against the stored session token.
     *
     * @param string $token Token to validate
     * @return bool Whether the token is valid
     */
    public static function validateToken(string $token): bool
    {
        if (!Session::has(self::TOKEN_KEY)) {
            return false;
        }

        $stored = Session::get(self::TOKEN_KEY);
        return is_string($stored) && hash_equals($stored, $token);
    }

    /**
     * Remove the token from the session.
     */
    public static function clearToken(): void
    {
        Session::remove(self::TOKEN_KEY);
    }
}
