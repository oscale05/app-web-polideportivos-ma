<?php

declare(strict_types=1);

namespace App\Support\Sms;

use App\Database;

/**
 * SMS de demostración: no envía nada real. Registra el mensaje en la tabla
 * sms_log y en el log de PHP. El código puede leerse por /api/dev/sms/ultimo-codigo
 * o en la respuesta de registro cuando APP_ENV=local.
 */
final class DemoSmsSender implements SmsSenderInterface
{
    public function enviar(string $destino, string $mensaje, string $proposito, ?string $dni = null): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO sms_log (destino, proposito, dni, mensaje, creado_en)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$destino, $proposito, $dni, $mensaje]);

        error_log(sprintf('[SMS DEMO] destino=%s proposito=%s dni=%s mensaje=%s', $destino, $proposito, $dni ?? '-', $mensaje));
    }
}
