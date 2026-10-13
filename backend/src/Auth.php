<?php

declare(strict_types=1);

namespace App;

use App\Support\JsonResponse;
use PDO;

final class Auth
{
    /** Crea una sesión y devuelve el token en texto plano (solo se guarda su hash). */
    public static function crearSesion(string $dni, ?string $ip = null, ?string $userAgent = null): string
    {
        $pdo = Database::pdo();
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $ttl = Config::int('TOKEN_TTL_DAYS', 30);
        $expira = (new \DateTimeImmutable('now'))->modify("+{$ttl} days")->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO sesiones (token_hash, dni, creada_en, expira_en, ip, user_agent)
             VALUES (?, ?, NOW(), ?, ?, ?)'
        );
        $stmt->execute([$hash, $dni, $expira, $ip, $userAgent]);

        return $token;
    }

    /** @return array<string,mixed>|null */
    public static function usuarioActual(): ?array
    {
        $token = self::bearerToken();
        if ($token === null) {
            return null;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT s.token_hash, s.dni, s.expira_en FROM sesiones s WHERE s.token_hash = ? AND s.revocada_en IS NULL'
        );
        $stmt->execute([hash('sha256', $token)]);
        $sesion = $stmt->fetch();

        if ($sesion === false || $sesion['expira_en'] < date('Y-m-d H:i:s')) {
            return null;
        }

        $dep = $pdo->prepare('SELECT * FROM deportistas WHERE dni = ?');
        $dep->execute([$sesion['dni']]);
        $deportista = $dep->fetch();

        return $deportista === false ? null : $deportista;
    }

    /** Devuelve el deportista autenticado o responde 401. */
    public static function requerir(): array
    {
        $deportista = self::usuarioActual();
        if ($deportista === null) {
            JsonResponse::error('No autenticado', 401);
        }

        return $deportista;
    }

    public static function cerrarSesion(): void
    {
        $token = self::bearerToken();
        if ($token === null) {
            return;
        }
        Database::pdo()
            ->prepare('UPDATE sesiones SET revocada_en = NOW() WHERE token_hash = ?')
            ->execute([hash('sha256', $token)]);
    }

    public static function revocarSesiones(string $dni): void
    {
        Database::pdo()
            ->prepare('UPDATE sesiones SET revocada_en = NOW() WHERE dni = ? AND revocada_en IS NULL')
            ->execute([$dni]);
    }

    private static function bearerToken(): ?string
    {
        $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($cabecera === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0) {
                    $cabecera = $valor;
                    break;
                }
            }
        }
        if (preg_match('/^Bearer\s+(\S+)$/i', (string) $cabecera, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
