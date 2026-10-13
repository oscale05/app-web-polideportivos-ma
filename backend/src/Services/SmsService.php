<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;
use App\Support\Sms\DemoSmsSender;
use App\Support\Sms\SmsSenderInterface;

final class SmsService
{
    private SmsSenderInterface $sender;

    public function __construct(?SmsSenderInterface $sender = null)
    {
        $this->sender = $sender ?? self::crearSender();
    }

    private static function crearSender(): SmsSenderInterface
    {
        $proveedor = Config::get('SMS_PROVIDER', 'demo');

        return match ($proveedor) {
            // "real" todavía no está implementado: cae al demo para no cortar el flujo.
            default => new DemoSmsSender(),
        };
    }

    /** Genera, persiste y "envía" un código de 6 dígitos. Devuelve el código (solo demo). */
    public function enviarCodigo(string $dni, string $celular, string $proposito): string
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = Config::int('CODIGO_TTL_MINUTES', 10);
        $pdo = Database::pdo();

        $pdo->prepare(
            'UPDATE codigos_verificacion SET usado_en = NOW()
             WHERE dni = ? AND proposito = ? AND usado_en IS NULL'
        )->execute([$dni, $proposito]);

        $pdo->prepare(
            'INSERT INTO codigos_verificacion (dni, proposito, destino, codigo_hash, expira_en)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
        )->execute([$dni, $proposito, $celular, password_hash($codigo, PASSWORD_DEFAULT), $ttl]);

        $nombrePoli = Config::get('POLIDEPORTIVO_NOMBRE', 'Palmeras');
        $mensaje = "Polideportivos {$nombrePoli}: tu codigo de verificacion es {$codigo}. Vence en {$ttl} minutos.";
        $this->sender->enviar($celular, $mensaje, $proposito, $dni);

        return $codigo;
    }

    /** Valida un código. Devuelve true si es correcto; marca el código como usado. */
    public function verificarCodigo(string $dni, string $proposito, string $codigo): bool
    {
        $pdo = Database::pdo();
        $maxIntentos = Config::int('CODIGO_MAX_INTENTOS', 5);

        $stmt = $pdo->prepare(
            'SELECT id, codigo_hash, expira_en, intentos FROM codigos_verificacion
             WHERE dni = ? AND proposito = ? AND usado_en IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$dni, $proposito]);
        $fila = $stmt->fetch();

        if ($fila === false) {
            return false;
        }
        if ($fila['expira_en'] < date('Y-m-d H:i:s')) {
            return false;
        }
        if ((int) $fila['intentos'] >= $maxIntentos) {
            return false;
        }

        if (! password_verify($codigo, $fila['codigo_hash'])) {
            $pdo->prepare('UPDATE codigos_verificacion SET intentos = intentos + 1 WHERE id = ?')
                ->execute([$fila['id']]);

            return false;
        }

        $pdo->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id = ?')
            ->execute([$fila['id']]);

        return true;
    }

    /** Modo demo: último código enviado (sin hashear) para poder completar el flujo. */
    public static function ultimoCodigo(?string $dni = null): ?array
    {
        $pdo = Database::pdo();
        if ($dni !== null) {
            $stmt = $pdo->prepare(
                'SELECT destino, proposito, mensaje, creado_en FROM sms_log
                 WHERE dni = ? ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$dni]);
        } else {
            $stmt = $pdo->query(
                'SELECT destino, proposito, mensaje, creado_en FROM sms_log ORDER BY id DESC LIMIT 1'
            );
        }
        $fila = $stmt->fetch();
        if ($fila === false) {
            return null;
        }
        $codigo = null;
        if (preg_match('/codigo de verificacion es (\d{6})/', $fila['mensaje'], $m) === 1) {
            $codigo = $m[1];
        }

        return [
            'destino'    => $fila['destino'],
            'proposito'  => $fila['proposito'],
            'codigo'     => $codigo,
            'mensaje'    => $fila['mensaje'],
            'creado_en'  => $fila['creado_en'],
        ];
    }
}
