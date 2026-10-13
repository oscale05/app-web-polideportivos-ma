<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Database;
use App\Services\RegistroService;
use App\Services\SmsService;
use App\Support\JsonResponse;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Validators;

final class AuthController
{
    public static function validar(): never
    {
        $dni = Validators::dni((string) (Request::json()['dni'] ?? ''));
        if ($dni === null) {
            JsonResponse::error('DNI inválido', 422);
        }
        JsonResponse::ok(RegistroService::validarDni($dni));
    }

    public static function paso1(): never
    {
        $datos = Request::json();

        $nombre = Validators::nombre((string) ($datos['nombre'] ?? ''));
        $apellido = Validators::nombre((string) ($datos['apellido'] ?? ''));
        $dni = Validators::dni((string) ($datos['dni'] ?? ''));
        $fecha = isset($datos['fecha_nacimiento']) && $datos['fecha_nacimiento'] !== ''
            ? Validators::fechaNacimiento((string) $datos['fecha_nacimiento'])
            : null;
        $calle = Validators::textoLibre((string) ($datos['calle'] ?? ''), 120);
        $numero = Validators::numeroPuerta((string) ($datos['numero'] ?? ''));
        $departamento = isset($datos['departamento']) && trim((string) $datos['departamento']) !== ''
            ? Validators::textoLibre((string) $datos['departamento'], 20)
            : null;
        $localidad = Validators::textoLibre((string) ($datos['localidad'] ?? ''), 80);
        $celular = Validators::celular((string) ($datos['celular'] ?? ''));
        $email = isset($datos['email']) && trim((string) $datos['email']) !== ''
            ? Validators::email((string) $datos['email'])
            : null;

        $errores = [];
        if ($nombre === null) {
            $errores['nombre'] = 'Nombre inválido';
        }
        if ($apellido === null) {
            $errores['apellido'] = 'Apellido inválido';
        }
        if ($dni === null) {
            $errores['dni'] = 'DNI inválido';
        }
        if ($fecha === null) {
            $errores['fecha_nacimiento'] = 'Fecha de nacimiento inválida';
        }
        if ($calle === null) {
            $errores['calle'] = 'Calle inválida';
        }
        if ($numero === null) {
            $errores['numero'] = 'Número inválido';
        }
        if ($departamento === null && isset($datos['departamento']) && trim((string) $datos['departamento']) !== '') {
            $errores['departamento'] = 'Departamento inválido';
        }
        if ($localidad === null) {
            $errores['localidad'] = 'Localidad inválida';
        }
        if ($celular === null) {
            $errores['celular'] = 'Celular inválido';
        }
        if ($email === null && isset($datos['email']) && trim((string) $datos['email']) !== '') {
            $errores['email'] = 'Email inválido';
        }
        if ($errores !== []) {
            JsonResponse::send(422, ['campos' => $errores], 'Revisá los datos ingresados');
        }

        $disponible = RegistroService::validarDni($dni);
        if ($disponible['disponible'] !== true) {
            $motivo = $disponible['motivo'] ?? 'dni_no_disponible';
            $mensaje = match ($motivo) {
                'dni_en_base'    => 'Este DNI ya figura en el polideportivo. Comunicate con el polideportivo.',
                'dni_con_cuenta' => 'Este DNI ya tiene una cuenta. Ingresá con tu clave.',
                default          => 'No se puede registrar con este DNI.',
            };
            JsonResponse::send(409, ['motivo' => $motivo], $mensaje);
        }

        if (! RateLimiter::intentar('sms:' . ($celular), 3, 3600)) {
            JsonResponse::error('Demasiadas solicitudes de SMS. Intentá más tarde.', 429);
        }

        $resultado = RegistroService::iniciar([
            'dni'              => $dni,
            'nombre'           => $nombre,
            'apellido'         => $apellido,
            'fecha_nacimiento' => $fecha,
            'email'            => $email,
            'calle'            => $calle,
            'numero'           => $numero,
            'departamento'     => $departamento,
            'localidad'        => $localidad,
            'celular'          => $celular,
        ]);

        if ($resultado === null) {
            JsonResponse::error('No se pudo iniciar el registro', 422);
        }

        $data = ['etapa' => 'verificar', 'celular' => $resultado['celular']];
        if (Config::isLocal()) {
            $ultimo = SmsService::ultimoCodigo($dni);
            $data['codigo_demo'] = $ultimo['codigo'] ?? null;
        }
        JsonResponse::ok($data);
    }

    public static function verificar(): never
    {
        $datos = Request::json();
        $dni = Validators::dni((string) ($datos['dni'] ?? ''));
        $codigo = trim((string) ($datos['codigo'] ?? ''));

        if ($dni === null || preg_match('/^\d{6}$/', $codigo) !== 1) {
            JsonResponse::error('Datos inválidos', 422);
        }
        if (RegistroService::pendiente($dni) === null) {
            JsonResponse::error('El registro expiró. Empezá de nuevo.', 410);
        }
        if (! RateLimiter::intentar('verificar:' . $dni, 10, 600)) {
            JsonResponse::error('Demasiados intentos. Esperá unos minutos.', 429);
        }

        $ok = (new SmsService())->verificarCodigo($dni, 'registro', $codigo);
        if (! $ok) {
            JsonResponse::error('Código incorrecto o vencido', 422);
        }

        RegistroService::marcarCelularVerificado($dni);
        JsonResponse::ok(['etapa' => 'clave']);
    }

    public static function clave(): never
    {
        $datos = Request::json();
        $dni = Validators::dni((string) ($datos['dni'] ?? ''));
        $clave = (string) ($datos['clave'] ?? '');
        $confirmacion = (string) ($datos['clave_confirmacion'] ?? $datos['confirmacion'] ?? '');

        if ($dni === null) {
            JsonResponse::error('DNI inválido', 422);
        }
        if (! Validators::clave($clave)) {
            JsonResponse::error('La clave debe tener al menos 8 caracteres, una letra y un número', 422);
        }
        if ($clave !== $confirmacion) {
            JsonResponse::error('Las claves no coinciden', 422);
        }

        try {
            RegistroService::crearCuenta($dni, $clave);
        } catch (\RuntimeException $e) {
            JsonResponse::error('El registro expiró o está incompleto. Empezá de nuevo.', 409);
        }

        JsonResponse::ok(['etapa' => 'completado'], 201);
    }

    public static function login(): never
    {
        $datos = Request::json();
        $dni = Validators::dni((string) ($datos['dni'] ?? ''));
        $clave = (string) ($datos['clave'] ?? '');

        if ($dni === null || $clave === '') {
            JsonResponse::error('Ingresá DNI y clave', 422);
        }

        $ventana = Config::int('LOGIN_VENTANA_MINUTOS', 15) * 60;
        $maximo = Config::int('LOGIN_MAX_INTENTOS', 10);
        if (! RateLimiter::intentar('login:' . $dni . ':' . (Request::ip() ?? ''), $maximo, $ventana)) {
            JsonResponse::error('Demasiados intentos. Esperá unos minutos.', 429);
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM deportistas WHERE dni = ? AND estado = 1');
        $stmt->execute([$dni]);
        $deportista = $stmt->fetch();

        if ($deportista === false || $deportista['password_hash'] === null
            || ! password_verify($clave, $deportista['password_hash'])) {
            JsonResponse::error('DNI o clave incorrectos', 401);
        }

        RateLimiter::restablecer('login:' . $dni . ':' . (Request::ip() ?? ''));

        $token = Auth::crearSesion($dni, Request::ip(), Request::userAgent());
        JsonResponse::ok([
            'token'      => $token,
            'deportista' => self::perfilPublico($deportista),
        ]);
    }

    public static function logout(): never
    {
        Auth::cerrarSesion();
        JsonResponse::ok(['ok' => true]);
    }

    public static function recuperarSolicitar(): never
    {
        $dni = Validators::dni((string) (Request::json()['dni'] ?? ''));
        if ($dni === null) {
            JsonResponse::error('DNI inválido', 422);
        }

        $stmt = Database::pdo()->prepare(
            'SELECT dni, celular FROM deportistas WHERE dni = ? AND origen = "app" AND estado = 1 AND password_hash IS NOT NULL'
        );
        $stmt->execute([$dni]);
        $fila = $stmt->fetch();

        if ($fila !== false) {
            if (RateLimiter::intentar('sms:' . $fila['celular'], 3, 3600)) {
                (new SmsService())->enviarCodigo($dni, $fila['celular'], 'recuperar_clave');
            }
        }

        // Respuesta genérica: no revela si el DNI existe.
        $data = ['ok' => true];
        if (Config::isLocal() && $fila !== false) {
            $ultimo = SmsService::ultimoCodigo($dni);
            $data['codigo_demo'] = $ultimo['codigo'] ?? null;
        }
        JsonResponse::ok($data);
    }

    public static function recuperarVerificar(): never
    {
        $datos = Request::json();
        $dni = Validators::dni((string) ($datos['dni'] ?? ''));
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $clave = (string) ($datos['clave'] ?? '');
        $confirmacion = (string) ($datos['clave_confirmacion'] ?? $datos['confirmacion'] ?? '');

        if ($dni === null || preg_match('/^\d{6}$/', $codigo) !== 1) {
            JsonResponse::error('Datos inválidos', 422);
        }
        if (! Validators::clave($clave)) {
            JsonResponse::error('La clave debe tener al menos 8 caracteres, una letra y un número', 422);
        }
        if ($clave !== $confirmacion) {
            JsonResponse::error('Las claves no coinciden', 422);
        }
        if (! RateLimiter::intentar('verificar:' . $dni, 10, 600)) {
            JsonResponse::error('Demasiados intentos. Esperá unos minutos.', 429);
        }

        if (! (new SmsService())->verificarCodigo($dni, 'recuperar_clave', $codigo)) {
            JsonResponse::error('Código incorrecto o vencido', 422);
        }

        Database::pdo()
            ->prepare('UPDATE deportistas SET password_hash = ? WHERE dni = ? AND origen = "app"')
            ->execute([password_hash($clave, PASSWORD_DEFAULT), $dni]);
        Auth::revocarSesiones($dni);

        JsonResponse::ok(['ok' => true]);
    }

    /** @param array<string,mixed> $d */
    public static function perfilPublico(array $d): array
    {
        return [
            'dni'                => $d['dni'],
            'nombre'             => $d['nombre'],
            'apellido'           => $d['apellido'],
            'fecha_nacimiento'   => $d['fecha_nacimiento'],
            'email'              => $d['email'],
            'calle'              => $d['calle'],
            'numero'             => $d['numero'],
            'departamento'       => $d['departamento'],
            'localidad'          => $d['localidad'],
            'celular'            => $d['celular'],
            'celular_verificado' => (int) $d['celular_verificado'] === 1,
            'tiene_foto'         => $d['foto'] !== null,
        ];
    }
}
