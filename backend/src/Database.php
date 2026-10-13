<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Config::get('DB_HOST', 'localhost') ?? 'localhost';
        $port = Config::get('DB_PORT', '3306') ?? '3306';
        $name = Config::get('DB_NAME', 'polideportivos_app') ?? 'polideportivos_app';
        $user = Config::get('DB_USER', 'slm') ?? 'slm';
        $pass = Config::get('DB_PASS', '') ?? '';
        $socket = Config::get('DB_SOCKET');

        $dsn = $host === 'localhost' && $socket !== null
            ? "mysql:unix_socket={$socket};dbname={$name};charset=utf8mb4"
            : "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    /** Conexión sin base de datos (para crearla en el seed). */
    public static function servidor(): PDO
    {
        $host = Config::get('DB_HOST', 'localhost') ?? 'localhost';
        $port = Config::get('DB_PORT', '3306') ?? '3306';
        $user = Config::get('DB_USER', 'slm') ?? 'slm';
        $pass = Config::get('DB_PASS', '') ?? '';
        $socket = Config::get('DB_SOCKET');

        $dsn = $host === 'localhost' && $socket !== null
            ? "mysql:unix_socket={$socket};charset=utf8mb4"
            : "mysql:host={$host};port={$port};charset=utf8mb4";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
