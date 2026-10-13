<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Services\ActividadesService;
use App\Services\SmsService;
use App\Support\JsonResponse;
use App\Support\Request;

final class ActividadesController
{
    /** Actividades/categorías del deportista autenticado, con polideportivo. */
    public static function index(): never
    {
        $deportista = Auth::requerir();
        JsonResponse::ok(ActividadesService::delDeportista($deportista['dni']));
    }

    /** Historial de asistencias de una categoría. */
    public static function asistencias(array $params): never
    {
        $deportista = Auth::requerir();
        $idCategoria = (int) ($params['id'] ?? 0);
        $pdo = \App\Database::pdo();

        $cat = $pdo->prepare(
            'SELECT c.id_categoria, c.categoria, c.dia, c.hora_i, c.hora_f, c.profesor,
                    a.nombre AS actividad, p.nombre AS polideportivo
             FROM categorias c
             JOIN actividades a ON a.id_actividad = c.actividad_id
             JOIN polideportivos p ON p.id = c.polideportivo_id
             WHERE c.id_categoria = ? LIMIT 1'
        );
        $cat->execute([$idCategoria]);
        $categoria = $cat->fetch();
        if ($categoria === false) {
            JsonResponse::error('Categoría no encontrada', 404);
        }

        $insc = $pdo->prepare(
            'SELECT 1 FROM inscripciones WHERE dni = ? AND categoria_id = ? AND estado = 1 LIMIT 1'
        );
        $insc->execute([$deportista['dni'], $idCategoria]);
        if ($insc->fetch() === false) {
            JsonResponse::error('No estás inscripto en esta categoría', 403);
        }

        $his = $pdo->prepare(
            'SELECT fecha, presente, tomada, observacion FROM asistencias
             WHERE dni = ? AND categoria_id = ? ORDER BY fecha DESC'
        );
        $his->execute([$deportista['dni'], $idCategoria]);
        $historial = array_map(
            static fn (array $f): array => [
                'fecha'       => $f['fecha'],
                'presente'    => (int) $f['presente'] === 1,
                'tomada'      => (int) $f['tomada'] === 1,
                'observacion' => $f['observacion'],
            ],
            $his->fetchAll()
        );

        $total = count(array_filter($historial, static fn (array $h): bool => $h['tomada']));
        $presentes = count(array_filter($historial, static fn (array $h): bool => $h['tomada'] && $h['presente']));

        JsonResponse::ok([
            'categoria' => [
                'id_categoria'  => (int) $categoria['id_categoria'],
                'actividad'     => $categoria['actividad'],
                'categoria'     => $categoria['categoria'],
                'dia'           => $categoria['dia'],
                'hora_i'        => $categoria['hora_i'],
                'hora_f'        => $categoria['hora_f'],
                'profesor'      => $categoria['profesor'],
                'polideportivo' => $categoria['polideportivo'],
            ],
            'historial' => $historial,
            'resumen'   => [
                'total'     => $total,
                'presentes' => $presentes,
                'ausentes'  => $total - $presentes,
                'pct'       => $total > 0 ? round($presentes * 100 / $total) : null,
            ],
        ]);
    }

    /** Últimos códigos SMS (solo modo local, para completar el flujo demo). */
    public static function smsDemo(array $params): never
    {
        if (! \App\Config::isLocal()) {
            JsonResponse::error('No disponible', 404);
        }
        $dni = isset($params['dni']) ? (string) $params['dni'] : null;
        $ultimo = SmsService::ultimoCodigo($dni);
        if ($ultimo === null) {
            JsonResponse::error('Sin códigos', 404);
        }
        JsonResponse::ok($ultimo);
    }
}
