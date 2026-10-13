<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Genera el carnet: QR con los datos del deportista embebido sobre la plantilla
 * Imagenes/carnet.jpeg (recuadro blanco x 147–502, y 415–754).
 */
final class QrService
{
    private const QR_TAMANIO = 300;
    private const QR_X = 174;   // centrado en el recuadro blanco (324 - 150)
    private const QR_Y = 434;   // 584 - 150

    /** Texto legible que va dentro del QR. */
    public static function contenido(array $deportista, array $datos): string
    {
        $poli = $datos['polideportivo'];
        $lineas = [
            'POLIDEPORTIVOS - ' . self::sinTildes($poli),
            self::sinTildes($deportista['apellido'] . ', ' . $deportista['nombre']),
            'DNI ' . $deportista['dni'],
            'Actividades:',
        ];

        $actividades = $datos['actividades'];
        $max = 8;
        foreach (array_slice($actividades, 0, $max) as $act) {
            $dias = implode(', ', array_map(
                [ActividadesService::class, 'abreviarDia'],
                $act['dias']
            ));
            $lineas[] = '- ' . self::sinTildes($act['actividad'])
                . ' | ' . self::sinTildes($dias)
                . ' ' . $act['hora_i'] . '-' . $act['hora_f'];
        }
        if (count($actividades) > $max) {
            $lineas[] = '... +' . (count($actividades) - $max) . ' actividades mas';
        }
        $texto = implode("\n", $lineas);

        // Límite de seguridad para que el QR siga siendo escaneable.
        if (mb_strlen($texto) > 450) {
            $texto = mb_substr($texto, 0, 430) . "\n...";
        }

        return $texto;
    }

    /** Genera (o reutiliza cacheada) la imagen del carnet en JPEG. Devuelve la ruta. */
    public static function carnet(string $dni): string
    {
        $dir = self::directorio();
        $ruta = $dir . '/' . $dni . '.jpg';

        $deportista = self::deportista($dni);
        if ($deportista === null) {
            throw new \RuntimeException('deportista_no_encontrado');
        }
        $datos = ActividadesService::delDeportista($dni);
        $texto = self::contenido($deportista, $datos);
        $firma = md5($texto . '|' . $deportista['celular_verificado']);

        $rutaFirma = $dir . '/' . $dni . '.md5';
        if (is_file($ruta) && is_file($rutaFirma) && (string) file_get_contents($rutaFirma) === $firma) {
            return $ruta;
        }

        $plantilla = dirname(__DIR__, 2) . '/../Imagenes/carnet.jpeg';
        $base = @imagecreatefromjpeg($plantilla);
        if ($base === false) {
            throw new \RuntimeException('plantilla_no_disponible');
        }

        $qr = (new Builder(
            writer: new PngWriter(),
            data: $texto,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: self::QR_TAMANIO,
            margin: 6,
        ))->build();

        $qrTmp = $dir . '/tmp_' . $dni . '.png';
        $qr->saveToFile($qrTmp);
        $imagenQr = @imagecreatefrompng($qrTmp);
        @unlink($qrTmp);
        if ($imagenQr === false) {
            imagedestroy($base);
            throw new \RuntimeException('qr_no_generado');
        }

        imagecopy(
            $base,
            $imagenQr,
            self::QR_X,
            self::QR_Y,
            0,
            0,
            imagesx($imagenQr),
            imagesy($imagenQr)
        );
        imagedestroy($imagenQr);

        imagejpeg($base, $ruta, 90);
        imagedestroy($base);
        file_put_contents($rutaFirma, $firma);

        return $ruta;
    }

    /** @return array<string,mixed>|null */
    private static function deportista(string $dni): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT dni, nombre, apellido, celular_verificado FROM deportistas WHERE dni = ?'
        );
        $stmt->execute([$dni]);
        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    private static function directorio(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/carnets';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Quita tildes y convierte a mayúsculas ASCII para máxima compatibilidad de escáneres. */
    private static function sinTildes(string $texto): string
    {
        $mapa = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'];

        return strtr($texto, $mapa);
    }
}
