<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;
use App\Support\Validators;

final class RegistroService
{
    /** @return array{disponible: bool, motivo?: string} */
    public static function validarDni(string $dniCrudo): array
    {
        $dni = Validators::dni($dniCrudo);
        if ($dni === null) {
            return ['disponible' => false, 'motivo' => 'dni_invalido'];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT origen, password_hash FROM deportistas WHERE dni = ?');
        $stmt->execute([$dni]);
        $fila = $stmt->fetch();

        if ($fila === false) {
            return ['disponible' => true];
        }
        if ($fila['origen'] === 'csv') {
            return ['disponible' => false, 'motivo' => 'dni_en_base'];
        }

        return ['disponible' => false, 'motivo' => 'dni_con_cuenta'];
    }

    /**
     * Guarda (o actualiza) el registro pendiente y envía el código SMS.
     * @return array{celular: string} | null  null si el DNI no está disponible
     */
    public static function iniciar(array $datos): ?array
    {
        $dni = Validators::dni((string) ($datos['dni'] ?? ''));
        if ($dni === null || self::validarDni($dni)['disponible'] !== true) {
            return null;
        }

        $celular = Validators::celular((string) ($datos['celular'] ?? ''));
        if ($celular === null) {
            return null;
        }

        $ttl = Config::int('REGISTRO_TTL_MINUTES', 30);
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO registros_pendientes
                (dni, nombre, apellido, fecha_nacimiento, email, calle, numero, departamento,
                 localidad, celular, celular_verificado, creado_en, expira_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE))
             ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre), apellido = VALUES(apellido),
                fecha_nacimiento = VALUES(fecha_nacimiento), email = VALUES(email),
                calle = VALUES(calle), numero = VALUES(numero), departamento = VALUES(departamento),
                localidad = VALUES(localidad), celular = VALUES(celular),
                celular_verificado = 0, creado_en = NOW(), expira_en = VALUES(expira_en)'
        )->execute([
            $dni,
            $datos['nombre'],
            $datos['apellido'],
            $datos['fecha_nacimiento'] ?? null,
            $datos['email'] ?? null,
            $datos['calle'],
            $datos['numero'],
            $datos['departamento'] ?? null,
            $datos['localidad'],
            $celular,
            $ttl,
        ]);

        (new SmsService())->enviarCodigo($dni, $celular, 'registro');

        return ['celular' => $celular];
    }

    /** @return array<string,mixed>|null */
    public static function pendiente(string $dni): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM registros_pendientes WHERE dni = ? AND expira_en >= NOW()'
        );
        $stmt->execute([$dni]);
        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    public static function marcarCelularVerificado(string $dni): void
    {
        Database::pdo()
            ->prepare('UPDATE registros_pendientes SET celular_verificado = 1 WHERE dni = ?')
            ->execute([$dni]);
    }

    /** Crea la cuenta definitiva y borra el registro pendiente. */
    public static function crearCuenta(string $dni, string $clave): void
    {
        $pendiente = self::pendiente($dni);
        if ($pendiente === null || (int) $pendiente['celular_verificado'] !== 1) {
            throw new \RuntimeException('registro_incompleto');
        }
        if (self::validarDni($dni)['disponible'] !== true) {
            throw new \RuntimeException('dni_no_disponible');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO deportistas
                    (dni, nombre, apellido, fecha_nacimiento, email, calle, numero, departamento,
                     localidad, celular, celular_verificado, celular_verificado_at, password_hash, origen, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?, 1)'
            )->execute([
                $dni,
                $pendiente['nombre'],
                $pendiente['apellido'],
                $pendiente['fecha_nacimiento'],
                $pendiente['email'],
                $pendiente['calle'],
                $pendiente['numero'],
                $pendiente['departamento'],
                $pendiente['localidad'],
                $pendiente['celular'],
                password_hash($clave, PASSWORD_DEFAULT),
                'app',
            ]);
            $pdo->prepare('DELETE FROM registros_pendientes WHERE dni = ?')->execute([$dni]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
