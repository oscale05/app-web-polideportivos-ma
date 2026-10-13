<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;

final class ActividadesService
{
    private const ORDEN_DIAS = ['Lunes', 'Martes', 'Miercoles', 'Miércoles', 'Jueves', 'Viernes', 'Sabado', 'Sábado', 'Domingo'];

    /**
     * Actividades/categorías del deportista (agrupadas por categoría).
     * @return array{polideportivo: string, actividades: list<array<string,mixed>>}
     */
    public static function delDeportista(string $dni): array
    {
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            'SELECT c.id_categoria, c.categoria, c.dia, c.hora_i, c.hora_f, c.profesor,
                    a.id_actividad, a.nombre AS actividad, p.nombre AS polideportivo
             FROM inscripciones i
             JOIN categorias c ON c.id_categoria = i.categoria_id
             JOIN actividades a ON a.id_actividad = i.actividad_id
             JOIN polideportivos p ON p.id = c.polideportivo_id
             WHERE i.dni = ? AND i.estado = 1 AND c.estado = 1 AND a.estado = 1
             ORDER BY a.nombre, c.categoria, c.hora_i'
        );
        $stmt->execute([$dni]);
        $filas = $stmt->fetchAll();

        $resumenStmt = $pdo->prepare(
            'SELECT categoria_id, COUNT(*) AS total, COALESCE(SUM(presente),0) AS presentes
             FROM asistencias WHERE dni = ? GROUP BY categoria_id'
        );
        $resumenStmt->execute([$dni]);
        $resumen = [];
        foreach ($resumenStmt->fetchAll() as $r) {
            $resumen[(int) $r['categoria_id']] = $r;
        }

        $agrupadas = [];
        foreach ($filas as $fila) {
            $id = (int) $fila['id_categoria'];
            if (! isset($agrupadas[$id])) {
                $r = $resumen[$id] ?? null;
                $total = $r === null ? 0 : (int) $r['total'];
                $presentes = $r === null ? 0 : (int) $r['presentes'];
                $agrupadas[$id] = [
                    'id_categoria'  => $id,
                    'actividad'     => $fila['actividad'],
                    'categoria'     => $fila['categoria'],
                    'dias'          => [],
                    'hora_i'        => $fila['hora_i'],
                    'hora_f'        => $fila['hora_f'],
                    'profesor'      => $fila['profesor'],
                    'polideportivo' => $fila['polideportivo'],
                    'asistencias'   => [
                        'total'     => $total,
                        'presentes' => $presentes,
                        'ausentes'  => $total - $presentes,
                        'pct'       => $total > 0 ? round($presentes * 100 / $total) : null,
                    ],
                ];
            }
            if (! in_array($fila['dia'], $agrupadas[$id]['dias'], true)) {
                $agrupadas[$id]['dias'][] = $fila['dia'];
            }
        }

        foreach ($agrupadas as &$item) {
            usort(
                $item['dias'],
                static fn (string $a, string $b): int =>
                    array_search($a, self::ORDEN_DIAS, true) <=> array_search($b, self::ORDEN_DIAS, true)
            );
        }
        unset($item);

        $resultado = array_values($agrupadas);
        usort(
            $resultado,
            static fn (array $a, array $b): int =>
                [$a['actividad'], $a['categoria']] <=> [$b['actividad'], $b['categoria']]
        );

        return [
            'polideportivo' => Config::get('POLIDEPORTIVO_NOMBRE', 'Palmeras'),
            'actividades'   => $resultado,
        ];
    }

    public static function abreviarDia(string $dia): string
    {
        $mapa = [
            'Lunes' => 'Lun', 'Martes' => 'Mar', 'Miercoles' => 'Mie', 'Miércoles' => 'Mie',
            'Jueves' => 'Jue', 'Viernes' => 'Vie', 'Sabado' => 'Sab', 'Sábado' => 'Sab',
            'Domingo' => 'Dom',
        ];

        return $mapa[$dia] ?? mb_substr($dia, 0, 3);
    }
}
