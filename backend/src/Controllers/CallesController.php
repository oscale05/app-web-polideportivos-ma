<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Support\JsonResponse;

final class CallesController
{
    /** Autocompletado de calles del padrón municipal. */
    public static function buscar(): never
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 3) {
            JsonResponse::ok(['calles' => []]);
        }

        $stmt = Database::pdo()->prepare(
            'SELECT calle, codigo FROM calles WHERE calle LIKE ? ORDER BY calle LIMIT 10'
        );
        $stmt->execute(['%' . mb_strtoupper($q) . '%']);
        $filas = $stmt->fetchAll();

        JsonResponse::ok(['calles' => array_map(
            static fn (array $f): array => ['calle' => $f['calle'], 'codigo' => $f['codigo']],
            $filas
        )]);
    }
}
