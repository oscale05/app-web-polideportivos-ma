<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Database;
use App\Services\FotoService;
use App\Services\SmsService;
use App\Support\JsonResponse;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Validators;

final class DeportistaController
{
    public static function me(): never
    {
        $deportista = Auth::requerir();
        $data = AuthController::perfilPublico($deportista);
        $data['celular_en_verificacion'] = $deportista['celular_nuevo'] !== null;
        JsonResponse::ok($data);
    }

    public static function actualizar(): never
    {
        $deportista = Auth::requerir();
        $datos = Request::json();

        // El DNI es inmutable: si viene en el body y no coincide, se rechaza.
        if (isset($datos['dni']) && Validators::dni((string) $datos['dni']) !== $deportista['dni']) {
            JsonResponse::error('El DNI no se puede modificar', 422);
        }

        $nombre = Validators::nombre((string) ($datos['nombre'] ?? $deportista['nombre']));
        $apellido = Validators::nombre((string) ($datos['apellido'] ?? $deportista['apellido']));
        $fecha = $deportista['fecha_nacimiento'];
        if (isset($datos['fecha_nacimiento'])) {
            $fecha = $datos['fecha_nacimiento'] === ''
                ? null
                : Validators::fechaNacimiento((string) $datos['fecha_nacimiento']);
        }
        $email = isset($datos['email']) && trim((string) $datos['email']) !== ''
            ? Validators::email((string) $datos['email'])
            : null;
        $calle = Validators::textoLibre((string) ($datos['calle'] ?? $deportista['calle']), 120);
        $numero = Validators::numeroPuerta((string) ($datos['numero'] ?? $deportista['numero']));
        $departamento = isset($datos['departamento']) && trim((string) $datos['departamento']) !== ''
            ? Validators::textoLibre((string) $datos['departamento'], 20)
            : null;
        $localidad = Validators::textoLibre((string) ($datos['localidad'] ?? $deportista['localidad']), 80);

        $errores = [];
        if ($nombre === null) {
            $errores['nombre'] = 'Nombre inválido';
        }
        if ($apellido === null) {
            $errores['apellido'] = 'Apellido inválido';
        }
        if (isset($datos['fecha_nacimiento']) && $fecha === null) {
            $errores['fecha_nacimiento'] = 'Fecha de nacimiento inválida';
        }
        if (isset($datos['email']) && trim((string) $datos['email']) !== '' && $email === null) {
            $errores['email'] = 'Email inválido';
        }
        if ($calle === null) {
            $errores['calle'] = 'Calle inválida';
        }
        if ($numero === null) {
            $errores['numero'] = 'Número inválido';
        }
        if ($localidad === null) {
            $errores['localidad'] = 'Localidad inválida';
        }
        if ($errores !== []) {
            JsonResponse::send(422, ['campos' => $errores], 'Revisá los datos ingresados');
        }

        Database::pdo()
            ->prepare(
                'UPDATE deportistas SET nombre = ?, apellido = ?, fecha_nacimiento = ?, email = ?,
                 calle = ?, numero = ?, departamento = ?, localidad = ?
                 WHERE dni = ?'
            )
            ->execute([
                $nombre, $apellido, $fecha, $email,
                $calle, $numero, $departamento, $localidad,
                $deportista['dni'],
            ]);

        $stmt = Database::pdo()->prepare('SELECT * FROM deportistas WHERE dni = ?');
        $stmt->execute([$deportista['dni']]);
        $actualizado = $stmt->fetch();

        JsonResponse::ok(AuthController::perfilPublico($actualizado));
    }

    public static function celularSolicitar(): never
    {
        $deportista = Auth::requerir();
        $celular = Validators::celular((string) (Request::json()['celular'] ?? ''));

        if ($celular === null) {
            JsonResponse::error('Celular inválido', 422);
        }
        if ($celular === $deportista['celular'] && (int) $deportista['celular_verificado'] === 1) {
            JsonResponse::error('Ese es tu celular actual', 422);
        }
        if (! RateLimiter::intentar('sms:' . $celular, 3, 3600)) {
            JsonResponse::error('Demasiadas solicitudes de SMS. Intentá más tarde.', 429);
        }

        Database::pdo()
            ->prepare('UPDATE deportistas SET celular_nuevo = ? WHERE dni = ?')
            ->execute([$celular, $deportista['dni']]);
        (new SmsService())->enviarCodigo($deportista['dni'], $celular, 'cambio_celular');

        $data = ['celular_en_verificacion' => true];
        if (Config::isLocal()) {
            $data['codigo_demo'] = SmsService::ultimoCodigo($deportista['dni'])['codigo'] ?? null;
        }
        JsonResponse::ok($data);
    }

    public static function celularVerificar(): never
    {
        $deportista = Auth::requerir();
        $codigo = trim((string) (Request::json()['codigo'] ?? ''));

        if ($deportista['celular_nuevo'] === null) {
            JsonResponse::error('No hay celular pendiente de verificar', 422);
        }
        if (preg_match('/^\d{6}$/', $codigo) !== 1) {
            JsonResponse::error('Código inválido', 422);
        }
        if (! RateLimiter::intentar('verificar:' . $deportista['dni'], 10, 600)) {
            JsonResponse::error('Demasiados intentos. Esperá unos minutos.', 429);
        }

        if (! (new SmsService())->verificarCodigo($deportista['dni'], 'cambio_celular', $codigo)) {
            JsonResponse::error('Código incorrecto o vencido', 422);
        }

        Database::pdo()
            ->prepare(
                'UPDATE deportistas SET celular = celular_nuevo, celular_verificado = 1,
                 celular_verificado_at = NOW(), celular_nuevo = NULL WHERE dni = ?'
            )
            ->execute([$deportista['dni']]);

        JsonResponse::ok(['ok' => true]);
    }

    public static function celularCancelar(): never
    {
        $deportista = Auth::requerir();
        Database::pdo()
            ->prepare('UPDATE deportistas SET celular_nuevo = NULL WHERE dni = ?')
            ->execute([$deportista['dni']]);
        JsonResponse::ok(['ok' => true]);
    }

    public static function fotoSubir(): never
    {
        $deportista = Auth::requerir();
        if (! isset($_FILES['foto'])) {
            JsonResponse::error('Falta la imagen', 422);
        }

        try {
            $archivo = FotoService::guardar($deportista['dni'], $_FILES['foto']);
        } catch (\RuntimeException $e) {
            $mensaje = match ($e->getMessage()) {
                'muy_grande'        => 'La imagen no puede superar 5 MB',
                'formato_no_soportado' => 'Formato no soportado (usá JPG, PNG o WEBP)',
                default             => 'No se pudo procesar la imagen',
            };
            JsonResponse::error($mensaje, 422);
        }

        Database::pdo()
            ->prepare('UPDATE deportistas SET foto = ? WHERE dni = ?')
            ->execute([$archivo, $deportista['dni']]);

        JsonResponse::ok(['foto' => $archivo]);
    }

    public static function fotoObtener(): never
    {
        $deportista = Auth::requerir();
        $ruta = FotoService::ruta($deportista['dni']);
        if ($ruta === null) {
            JsonResponse::error('Sin foto de perfil', 404);
        }

        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=300');
        readfile($ruta);
        exit;
    }
}
