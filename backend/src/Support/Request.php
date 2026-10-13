<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    /** @return array<string,mixed> */
    public static function json(): array
    {
        $crudo = file_get_contents('php://input') ?: '';
        if ($crudo === '') {
            return [];
        }
        $datos = json_decode($crudo, true);

        return is_array($datos) ? $datos : [];
    }

    public static function ip(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public static function userAgent(): ?string
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        return $ua === '' ? null : mb_substr($ua, 0, 255);
    }
}
