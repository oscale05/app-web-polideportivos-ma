<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;

final class Config
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        $root = dirname(__DIR__);
        $dotenv = Dotenv::createImmutable($root);
        $dotenv->safeLoad();
        self::$loaded = true;
        // La BD usa la zona del sistema (America/Argentina/Buenos_Aires):
        // PHP debe usar la misma para que NOW() y date() coincidan.
        date_default_timezone_set(self::get('APP_TIMEZONE', 'America/Argentina/Buenos_Aires') ?? 'UTC');
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return ($value === false || $value === null || $value === '') ? $default : (string) $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }

    public static function env(): string
    {
        return self::get('APP_ENV', 'production') ?? 'production';
    }

    public static function isLocal(): bool
    {
        return in_array(self::env(), ['local', 'dev'], true);
    }
}
