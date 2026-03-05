<?php
declare(strict_types=1);

namespace QuantumAstrology\Support;

final class RedisStore
{
    private static ?\Redis $client = null;
    private static bool $unavailable = false;

    public static function isEnabled(): bool
    {
        return defined('REDIS_ENABLED') && REDIS_ENABLED && class_exists(\Redis::class);
    }

    public static function get(string $key): ?string
    {
        $client = self::client();
        if (!$client) {
            return null;
        }

        $value = $client->get($key);
        return is_string($value) ? $value : null;
    }

    public static function set(string $key, string $value, int $ttlSeconds = 0): bool
    {
        $client = self::client();
        if (!$client) {
            return false;
        }

        if ($ttlSeconds > 0) {
            return (bool)$client->setex($key, $ttlSeconds, $value);
        }

        return (bool)$client->set($key, $value);
    }

    public static function delete(string $key): bool
    {
        $client = self::client();
        if (!$client) {
            return false;
        }

        return (int)$client->del($key) > 0;
    }

    private static function client(): ?\Redis
    {
        if (self::$unavailable) {
            return null;
        }

        if (self::$client instanceof \Redis) {
            return self::$client;
        }

        if (!self::isEnabled()) {
            self::$unavailable = true;
            return null;
        }

        try {
            $redis = new \Redis();
            $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
            $port = defined('REDIS_PORT') ? REDIS_PORT : 6379;
            $timeout = defined('REDIS_TIMEOUT') ? REDIS_TIMEOUT : 1.5;

            if (!$redis->connect((string)$host, (int)$port, (float)$timeout)) {
                self::$unavailable = true;
                return null;
            }

            $password = defined('REDIS_PASSWORD') ? trim((string)REDIS_PASSWORD) : '';
            if ($password !== '' && !$redis->auth($password)) {
                self::$unavailable = true;
                return null;
            }

            $db = defined('REDIS_DB') ? (int)REDIS_DB : 0;
            if ($db > 0) {
                $redis->select($db);
            }

            self::$client = $redis;
            return self::$client;
        } catch (\Throwable $e) {
            self::$unavailable = true;
            return null;
        }
    }
}
