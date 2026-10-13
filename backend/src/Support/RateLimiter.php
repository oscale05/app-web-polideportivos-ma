<?php

declare(strict_types=1);

namespace App\Support;

use App\Database;
use PDO;

final class RateLimiter
{
    /**
     * Incrementa el contador de una llave y devuelve false si superó el máximo
     * en la ventana indicada.
     */
    public static function intentar(string $llave, int $maximo, int $ventanaSegundos): bool
    {
        $pdo = Database::pdo();
        $ahora = new \DateTimeImmutable('now');

        $stmt = $pdo->prepare(
            'SELECT llave, contador, inicio FROM rate_limits WHERE llave = ? FOR UPDATE'
        );
        $stmt->execute([$llave]);
        $fila = $stmt->fetch();

        if ($fila === false) {
            $ins = $pdo->prepare(
                'INSERT INTO rate_limits (llave, contador, inicio) VALUES (?, 1, ?)'
            );
            $ins->execute([$llave, $ahora->format('Y-m-d H:i:s')]);

            return true;
        }

        $inicio = new \DateTimeImmutable($fila['inicio']);
        if ($ahora->getTimestamp() - $inicio->getTimestamp() > $ventanaSegundos) {
            $upd = $pdo->prepare(
                'UPDATE rate_limits SET contador = 1, inicio = ? WHERE llave = ?'
            );
            $upd->execute([$ahora->format('Y-m-d H:i:s'), $llave]);

            return true;
        }

        if ((int) $fila['contador'] >= $maximo) {
            return false;
        }

        $upd = $pdo->prepare('UPDATE rate_limits SET contador = contador + 1 WHERE llave = ?');
        $upd->execute([$llave]);

        return true;
    }

    public static function restablecer(string $llave): void
    {
        Database::pdo()->prepare('DELETE FROM rate_limits WHERE llave = ?')->execute([$llave]);
    }
}
