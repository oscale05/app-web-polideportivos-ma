<?php

declare(strict_types=1);

namespace App\Support;

final class Validators
{
    /** Normaliza un DNI: solo dígitos, 7 u 8 caracteres. */
    public static function dni(string $valor): ?string
    {
        $limpio = preg_replace('/\D+/', '', $valor) ?? '';
        if (strlen($limpio) < 7 || strlen($limpio) > 8) {
            return null;
        }

        return $limpio;
    }

    public static function email(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '' || filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $valor;
    }

    /**
     * Normaliza celular argentino a formato 549XXXXXXXXXX.
     * Acepta: 1123456789, +54 9 11 2345-6789, 011 2345 6789, 5491123456789.
     */
    public static function celular(string $valor): ?string
    {
        $limpio = preg_replace('/[^\d+]/', '', trim($valor));
        $limpio = ltrim($limpio, '+');
        if ($limpio === '' || strlen($limpio) > 15) {
            return null;
        }
        // Quitar 00 / 0 inicial de Argentina
        if (str_starts_with($limpio, '00')) {
            $limpio = substr($limpio, 2);
        }
        if (str_starts_with($limpio, '54')) {
            $resto = substr($limpio, 2);
            if (str_starts_with($resto, '9')) {
                $limpio = '549' . substr($resto, 1);
            } else {
                $limpio = '549' . $resto;
            }
        } elseif (str_starts_with($limpio, '0')) {
            // 011 23456789 -> código de área sin 0
            $limpio = '549' . substr($limpio, 1);
        } elseif (strlen($limpio) >= 10 && strlen($limpio) <= 11) {
            // 1123456789 (área + número)
            $limpio = '549' . $limpio;
        } else {
            return null;
        }

        if (!preg_match('/^549\d{10,11}$/', $limpio)) {
            return null;
        }

        return $limpio;
    }

    public static function fechaNacimiento(string $valor): ?string
    {
        $valor = trim($valor);
        $fecha = \DateTime::createFromFormat('Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            return null;
        }
        $hoy = new \DateTime('today');
        $edad = $fecha->diff($hoy)->y;
        if ($edad < 5 || $edad > 100) {
            return null;
        }

        return $valor;
    }

    /** Clave: mínimo 8, una letra y un número. */
    public static function clave(string $valor): bool
    {
        return strlen($valor) >= 8
            && preg_match('/[A-Za-z]/', $valor) === 1
            && preg_match('/\d/', $valor) === 1;
    }

    /** Solo letras, números, espacios y puntuación básica para nombres. */
    public static function nombre(string $valor): ?string
    {
        $valor = trim(preg_replace('/\s+/', ' ', $valor) ?? '');
        if ($valor === '' || mb_strlen($valor) > 80) {
            return null;
        }
        if (preg_match('/^[\p{L}ÑñÁÉÍÓÚÜáéíóúü\'\-\. ]+$/u', $valor) !== 1) {
            return null;
        }

        return $valor;
    }

    public static function textoLibre(string $valor, int $max = 120): ?string
    {
        $valor = trim($valor);
        if ($valor === '' || mb_strlen($valor) > $max) {
            return null;
        }

        return $valor;
    }

    public static function numeroPuerta(string $valor): ?string
    {
        $valor = trim($valor);
        if (preg_match('/^[1-9]\d{0,4}$/', $valor) !== 1) {
            return null;
        }

        return $valor;
    }
}
