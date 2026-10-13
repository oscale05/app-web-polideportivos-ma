<?php

declare(strict_types=1);

/**
 * Seed idempotente: crea la BD, aplica el schema y carga los datos de los CSV
 * de la carpeta raíz del proyecto + asistencias sintéticas + cuentas demo.
 *
 * Uso:  php scripts/seed.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Database;
use App\Support\CsvReader;
use App\Support\Validators;

Config::load();

$inicio = microtime(true);

$dirDatos = getenv('CSV_DIR') ?: dirname(__DIR__, 3); // carpeta que contiene poli_5_*.csv
$nombreDb = Config::get('DB_NAME', 'polideportivos_app');
$poliId = Config::int('POLIDEPORTIVO_ID', 5);
$poliNombre = Config::get('POLIDEPORTIVO_NOMBRE', 'Palmeras');

echo "== Seed Polideportivos ({$nombreDb}) ==\n";

// 1. Crear BD si no existe
$servidor = Database::servidor();
$servidor->exec("CREATE DATABASE IF NOT EXISTS `{$nombreDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "BD '{$nombreDb}' OK\n";

$pdo = Database::pdo();

// 2. Schema
$schema = (string) file_get_contents(__DIR__ . '/../sql/schema.sql');
foreach (array_filter(array_map('trim', explode(';', $schema))) as $sentencia) {
    if (str_starts_with($sentencia, '--')) {
        $sentencia = ltrim(preg_replace('/^--.*$/m', '', $sentencia) ?? '');
    }
    if ($sentencia !== '') {
        $pdo->exec($sentencia);
    }
}
echo "Schema aplicado\n";

// 3. Polideportivo
$pdo->prepare('INSERT INTO polideportivos (id, nombre) VALUES (?, ?)')
    ->execute([$poliId, $poliNombre]);
echo "Polideportivo {$poliId} = {$poliNombre}\n";

// 4. Calles (desde data/calles.csv generado por convertir_calles.py)
$rutaCalles = __DIR__ . '/../data/calles.csv';
if (is_file($rutaCalles)) {
    $calles = CsvReader::leer($rutaCalles, ';');
    $ins = $pdo->prepare('INSERT INTO calles (calle, codigo) VALUES (?, ?)');
    $pdo->beginTransaction();
    foreach ($calles as $c) {
        $ins->execute([$c['calle'], $c['codigo'] ?: null]);
    }
    $pdo->commit();
    echo 'Calles: ' . count($calles) . "\n";
} else {
    echo "Aviso: no existe data/calles.csv (corré scripts/convertir_calles.py)\n";
}

// 5. Actividades
$actividades = CsvReader::leer($dirDatos . '/poli_5_actividades.csv', ';');
$ins = $pdo->prepare('INSERT INTO actividades (id_actividad, nombre, cupos, estado) VALUES (?, ?, ?, ?)');
foreach ($actividades as $a) {
    if ($a['id_actividad'] === '') {
        continue;
    }
    $ins->execute([
        (int) $a['id_actividad'],
        $a['actividad'] ?: 'Sin nombre',
        (int) ($a['cupos'] ?? 0),
        (int) ($a['estado'] ?? 1),
    ]);
}
echo 'Actividades: ' . count($actividades) . "\n";

// 6. Categorías
$categorias = CsvReader::leer($dirDatos . '/poli_5_categorias.csv', ';');
$ins = $pdo->prepare(
    'INSERT INTO categorias (id_categoria, categoria, dia, hora_i, hora_f, profesor, actividad_id, polideportivo_id, estado)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$catInsertadas = 0;
foreach ($categorias as $c) {
    if ($c['id_categoria'] === '' || $c['dia'] === '') {
        continue;
    }
    $ins->execute([
        (int) $c['id_categoria'],
        $c['categoria'] ?: 'Única',
        ucfirst(mb_strtolower($c['dia'])),
        substr($c['hora_i'] ?: '00:00', 0, 5),
        substr($c['hora_f'] ?: '00:00', 0, 5),
        $c['profesor'] !== '' ? $c['profesor'] : null,
        (int) $c['id_actividad'],
        $poliId,
        (int) ($c['estado'] ?? 1),
    ]);
    $catInsertadas++;
}
echo "Categorías: {$catInsertadas}\n";

// 7. Deportistas base (CSV) — origen 'csv', sin cuenta
$inscriptos = CsvReader::leer($dirDatos . '/poli_5_inscriptos.csv', ';');
$ins = $pdo->prepare(
    'INSERT INTO deportistas (dni, nombre, apellido, fecha_nacimiento, email, calle, numero,
        departamento, localidad, celular, celular_verificado, origen, estado)
     VALUES (?, ?, ?, ?, NULL, ?, ?, NULL, ?, ?, 0, "csv", 1)'
);
$pdo->beginTransaction();
$depInsertados = 0;
$depDescartados = 0;
$dnisBase = [];
foreach ($inscriptos as $fila) {
    $dni = Validators::dni($fila['dni_ins'] ?? '');
    $nombre = Validators::nombre($fila['nombre_ins'] ?? '');
    $apellido = Validators::nombre($fila['apellido_ins'] ?? '');
    if ($dni === null || $nombre === null || $apellido === null || isset($dnisBase[$dni])) {
        $depDescartados++;
        continue;
    }
    $dnisBase[$dni] = true;

    [$calle, $numero] = partirDireccion($fila['direccion_ins'] ?? '');
    $localidad = trim($fila['localidad_ins'] ?? '');
    if ($localidad === '' || strtoupper($localidad) === 'FALTA DATO') {
        $localidad = 'Sin especificar';
    }

    $celular = Validators::celular($fila['telefono_ins'] ?? '');
    if ($celular === null) {
        $limpio = preg_replace('/\D+/', '', (string) ($fila['telefono_ins'] ?? '')) ?? '';
        $celular = strlen($limpio) >= 8 ? substr($limpio, 0, 15) : '0';
    }

    $ins->execute([
        $dni,
        $nombre,
        $apellido,
        fechaValida($fila['fechanac_ins'] ?? ''),
        $calle,
        $numero,
        $localidad,
        $celular,
    ]);
    $depInsertados++;
}
$pdo->commit();
echo "Deportistas (csv): {$depInsertados} insertados, {$depDescartados} descartados\n";

// 8. Inscripciones
$actInsc = CsvReader::leer($dirDatos . '/poli_5_act_inscriptos.csv', ';');
$idsCat = [];
foreach ($pdo->query('SELECT DISTINCT id_categoria FROM categorias')->fetchAll() as $c) {
    $idsCat[(int) $c['id_categoria']] = true;
}
$idsAct = [];
foreach ($pdo->query('SELECT id_actividad FROM actividades')->fetchAll() as $a) {
    $idsAct[(int) $a['id_actividad']] = true;
}

$ins = $pdo->prepare(
    'INSERT INTO inscripciones (id_act_ins, dni, actividad_id, categoria_id, estado, fecha_alta)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$pdo->beginTransaction();
$inscInsertadas = 0;
$inscDescartadas = 0;
foreach ($actInsc as $fila) {
    $idActIns = (int) ($fila['id_act_ins'] ?? 0);
    $dni = Validators::dni($fila['dni_ins'] ?? '');
    $idCat = (int) ($fila['id_categoria'] ?? 0);
    $idAct = (int) ($fila['id_actividad'] ?? 0);
    if ($idActIns <= 0 || $dni === null || ! isset($dnisBase[$dni]) || ! isset($idsCat[$idCat]) || ! isset($idsAct[$idAct])) {
        $inscDescartadas++;
        continue;
    }
    $estado = (int) ($fila['estado'] ?? 1);
    $fecha = fechaHoraValida($fila['fecha_alta'] ?? '');
    $ins->execute([$idActIns, $dni, $idAct, $idCat, $estado, $fecha]);
    $inscInsertadas++;
}
$pdo->commit();
echo "Inscripciones: {$inscInsertadas} insertadas, {$inscDescartadas} descartadas\n";

// 9. Asistencias sintéticas para las inscripciones activas
$insActivas = $pdo->query(
    'SELECT i.dni, i.categoria_id, c.dia FROM inscripciones i
     JOIN categorias c ON c.id_categoria = i.categoria_id
     WHERE i.estado = 1'
)->fetchAll();

$insAsis = $pdo->prepare(
    'INSERT IGNORE INTO asistencias (dni, categoria_id, fecha, presente, tomada) VALUES (?, ?, ?, ?, 1)'
);
$pdo->beginTransaction();
$asisInsertadas = 0;
$hoy = new DateTimeImmutable('today');
foreach ($insActivas as $fila) {
    $fechas = fechasPorDia($fila['dia'], $hoy, 10);
    mt_srand(crc32($fila['dni'] . '|' . $fila['categoria_id']));
    foreach ($fechas as $fecha) {
        $presente = mt_rand(0, 99) < 80 ? 1 : 0;
        $stmt = $insAsis->execute([$fila['dni'], $fila['categoria_id'], $fecha, $presente]);
        if ($stmt && $insAsis->rowCount() > 0) {
            $asisInsertadas++;
        }
    }
}
$pdo->commit();
echo "Asistencias sintéticas: {$asisInsertadas}\n";

// 10. Cuentas demo (DNIs ficticios, no presentes en los CSV)
$demo = [
    ['dni' => '99000001', 'nombre' => 'Juan', 'apellido' => 'Demo', 'celular' => '5491100000001'],
    ['dni' => '99000002', 'nombre' => 'María', 'apellido' => 'Prueba', 'celular' => '5491100000002'],
    ['dni' => '99000003', 'nombre' => 'Carlos', 'apellido' => 'Ejemplo', 'celular' => '5491100000003'],
];
$insDep = $pdo->prepare(
    'INSERT INTO deportistas (dni, nombre, apellido, fecha_nacimiento, email, calle, numero,
        departamento, localidad, celular, celular_verificado, celular_verificado_at, password_hash, origen, estado)
     VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 1, NOW(), ?, "app", 1)'
);
$hash = password_hash('Demo2026!', PASSWORD_DEFAULT);
foreach ($demo as $i => $d) {
    $insDep->execute([
        $d['dni'], $d['nombre'], $d['apellido'],
        sprintf('199%d-05-1%d', ($i % 8) + 1, ($i % 8) + 1),
        strtolower($d['nombre']) . '.' . strtolower($d['apellido']) . '@demo.test',
        'Av. Malvinas Argentinas', '100' . ($i + 1),
        'Villa de Mayo',
        $d['celular'],
        $hash,
    ]);
}

// Inscripciones demo: primeras categorías activas
$catsDemo = $pdo->query(
    'SELECT c.id_categoria, c.actividad_id FROM categorias c
     WHERE c.estado = 1 ORDER BY c.id_categoria LIMIT 6'
)->fetchAll();
$insInsc = $pdo->prepare(
    'INSERT INTO inscripciones (id_act_ins, dni, actividad_id, categoria_id, estado) VALUES (?, ?, ?, ?, 1)'
);
$asisDemo = $pdo->prepare(
    'INSERT IGNORE INTO asistencias (dni, categoria_id, fecha, presente, tomada) VALUES (?, ?, ?, ?, 1)'
);
$pdo->beginTransaction();
$n = 900000;
foreach ($demo as $i => $d) {
    $elegidas = array_slice($catsDemo, $i, 2 + ($i % 2));
    foreach ($elegidas as $cat) {
        $n++;
        $insInsc->execute([$n, $d['dni'], $cat['actividad_id'], $cat['id_categoria']]);
        $dias = $pdo->prepare('SELECT dia FROM categorias WHERE id_categoria = ?');
        $dias->execute([$cat['id_categoria']]);
        foreach ($dias->fetchAll() as $filaDia) {
            mt_srand(crc32($d['dni'] . '|' . $cat['id_categoria']));
            foreach (fechasPorDia($filaDia['dia'], $hoy, 8) as $fecha) {
                $asisDemo->execute([$d['dni'], $cat['id_categoria'], $fecha, mt_rand(0, 99) < 85 ? 1 : 0]);
            }
        }
    }
}
$pdo->commit();
echo 'Cuentas demo: ' . count($demo) . " (clave: Demo2026!)\n";

// Resumen final
foreach (['deportistas', 'actividades', 'categorias', 'inscripciones', 'asistencias', 'calles'] as $t) {
    $total = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    printf("  %-14s %6d\n", $t . ':', $total);
}

printf("Listo en %.1fs\n", microtime(true) - $inicio);

// ---------------------------------------------------------------------------

/** @return array{0:string,1:string} calle y número */
function partirDireccion(string $direccion): array
{
    $direccion = trim(preg_replace('/\s+/', ' ', $direccion) ?? '');
    if ($direccion === '' || strtoupper($direccion) === 'FALTA DATO') {
        return ['Sin especificar', '0'];
    }
    if (preg_match('/^(.*?)[\s,]+(\d{1,5})(?:\s*(bis|B|BIS))?$/u', $direccion, $m) === 1) {
        return [trim($m[1]), $m[2]];
    }

    return [$direccion, '0'];
}

function fechaValida(string $valor): ?string
{
    $valor = trim($valor);
    if ($valor === '' || str_starts_with($valor, '0000')) {
        return null;
    }
    $fecha = DateTime::createFromFormat('Y-m-d', $valor)
        ?: DateTime::createFromFormat('d/m/Y', $valor);
    if ($fecha === false) {
        return null;
    }
    $anio = (int) $fecha->format('Y');
    if ($anio < 1920 || $anio > (int) date('Y')) {
        return null;
    }

    return $fecha->format('Y-m-d');
}

function fechaHoraValida(string $valor): ?string
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }
    foreach (['Y-m-d H:i:s', 'd-m-Y H:i:s', 'd/m/y H:i:s', 'Y-m-d'] as $formato) {
        $f = DateTime::createFromFormat($formato, $valor);
        if ($f !== false && $f->format($formato === 'Y-m-d' ? 'Y-m-d' : 'Y-m-d H:i:s') !== '') {
            return $f->format('Y-m-d H:i:s');
        }
    }

    return null;
}

/**
 * Últimas $cantidad fechas pasadas en las que cae el día de la semana indicado.
 * @return list<string>
 */
function fechasPorDia(string $dia, DateTimeImmutable $desde, int $cantidad): array
{
    $mapa = [
        'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'miércoles' => 3,
        'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'sábado' => 6, 'domingo' => 7,
    ];
    $numero = $mapa[mb_strtolower(trim($dia))] ?? null;
    if ($numero === null) {
        return [];
    }

    $fechas = [];
    $fecha = $desde;
    // Retrocede hasta encontrar el primer día anterior (no incluye hoy por si aún no se tomó).
    while (count($fechas) < $cantidad) {
        $fecha = $fecha->modify('-1 day');
        if ((int) $fecha->format('N') === $numero) {
            $fechas[] = $fecha->format('Y-m-d');
        }
    }

    return $fechas;
}
