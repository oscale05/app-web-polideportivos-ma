<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;

final class FotoService
{
    public const TAMANIO = 400;

    /** @return string nombre de archivo guardado ({dni}.jpg) */
    public static function guardar(string $dni, array $archivo): string
    {
        if (! isset($archivo['tmp_name']) || ! is_uploaded_file($archivo['tmp_name'])) {
            throw new \RuntimeException('archivo_invalido');
        }
        if ((int) ($archivo['error'] ?? 1) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('error_subida');
        }
        if ((int) ($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new \RuntimeException('muy_grande');
        }

        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false) {
            throw new \RuntimeException('archivo_invalido');
        }
        $mime = $info['mime'];
        $crear = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
            default      => null,
        };
        if ($crear === null) {
            throw new \RuntimeException('formato_no_soportado');
        }

        $origen = $crear($archivo['tmp_name']);
        if ($origen === false) {
            throw new \RuntimeException('archivo_invalido');
        }

        $t = self::TAMANIO;
        $anchoOrigen = imagesx($origen);
        $altoOrigen = imagesy($origen);
        $lado = min($anchoOrigen, $altoOrigen);

        // Recorte cuadrado centrado (selfie).
        $sx = (int) (($anchoOrigen - $lado) / 2);
        $sy = (int) (($altoOrigen - $lado) / 2);

        $destino = imagecreatetruecolor($t, $t);
        imagecopyresampled($destino, $origen, 0, 0, $sx, $sy, $t, $t, $lado, $lado);
        imagedestroy($origen);

        $ruta = self::directorio() . '/' . $dni . '.jpg';
        imagejpeg($destino, $ruta, 88);
        imagedestroy($destino);

        return $dni . '.jpg';
    }

    public static function ruta(string $dni): ?string
    {
        $ruta = self::directorio() . '/' . $dni . '.jpg';

        return is_file($ruta) ? $ruta : null;
    }

    private static function directorio(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/fotos';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function versionPolideportivo(): string
    {
        return (string) Config::int('POLIDEPORTIVO_ID', 5);
    }
}
