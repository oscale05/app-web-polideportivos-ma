<?php

declare(strict_types=1);

namespace App\Support;

final class CsvReader
{
    /**
     * @return list<array<string,string>>
     */
    public static function leer(string $ruta, string $delim = ';', bool $omitirBom = true): array
    {
        if (! is_file($ruta)) {
            throw new \RuntimeException("No existe el archivo: {$ruta}");
        }
        $contenido = (string) file_get_contents($ruta);
        if ($omitirBom && str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }
        // Algunos CSV vienen en ISO-8859-1/Windows-1252: se normalizan a UTF-8.
        if (mb_check_encoding($contenido, 'UTF-8') !== true) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
        }

        $filas = array_map(
            static fn (string $linea): array => str_getcsv($linea, $delim, '"', ''),
            explode("\n", str_replace("\r\n", "\n", $contenido))
        );
        $filas = array_values(array_filter($filas, static fn (array $f): bool => $f !== [null] && $f !== ['']));

        $cabecera = array_map(
            static fn (string $c): string => trim($c, " \t\0\x0B\""),
            (array) array_shift($filas)
        );

        $resultado = [];
        foreach ($filas as $fila) {
            $fila = array_values($fila);
            $item = [];
            foreach ($cabecera as $i => $clave) {
                $item[$clave] = isset($fila[$i]) ? trim((string) $fila[$i], " \t\0\x0B\"") : '';
            }
            $resultado[] = $item;
        }

        return $resultado;
    }
}
