<?php

declare(strict_types=1);

namespace App\Tests;

use App\Config;
use App\Database;
use App\Support\Validators;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios de validaciones (no requieren base de datos).
 */
final class ValidatorsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Config::load();
    }

    public function testDniValido(): void
    {
        $this->assertSame('45904146', Validators::dni('45.904.146'));
        $this->assertSame('1234567', Validators::dni('1.234.567'));
        $this->assertNull(Validators::dni('123'));
        $this->assertNull(Validators::dni('123456789'));
        $this->assertNull(Validators::dni('abcdefgh'));
    }

    public function testCelularNormalizaA549(): void
    {
        $this->assertSame('5491123456789', Validators::celular('11 2345-6789'));
        $this->assertSame('5491123456789', Validators::celular('+54 9 11 2345-6789'));
        $this->assertSame('5491123456789', Validators::celular('01123456789'));
        $this->assertSame('5491123456789', Validators::celular('5491123456789'));
        $this->assertNull(Validators::celular('123'));
    }

    public function testFechaNacimiento(): void
    {
        $this->assertSame('1990-04-12', Validators::fechaNacimiento('1990-04-12'));
        $this->assertNull(Validators::fechaNacimiento('1925-01-01')); // edad > 100
        $this->assertNull(Validators::fechaNacimiento('no-es-fecha'));
        $this->assertNull(Validators::fechaNacimiento('2020-13-40'));
    }

    public function testClave(): void
    {
        $this->assertTrue(Validators::clave('Demo2026!'));
        $this->assertFalse(Validators::clave('corta1'));
        $this->assertFalse(Validators::clave('sololetras'));
        $this->assertFalse(Validators::clave('12345678'));
    }

    public function testNombre(): void
    {
        $this->assertSame('Maria Jose', Validators::nombre('  Maria   Jose '));
        $this->assertNull(Validators::nombre('1234'));
        $this->assertNull(Validators::nombre(''));
        $this->assertSame("O'Connor", Validators::nombre("O'Connor"));
    }

    public function testNumeroPuerta(): void
    {
        $this->assertSame('2400', Validators::numeroPuerta('2400'));
        $this->assertNull(Validators::numeroPuerta('2400 bis'));
        $this->assertNull(Validators::numeroPuerta('0'));
    }

    public function testEmail(): void
    {
        $this->assertSame('a@b.com', Validators::email(' a@b.com '));
        $this->assertNull(Validators::email('no-es-email'));
    }
}
