<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests de integración contra la API en 127.0.0.1:8080.
 * Se omiten si el servidor no está levantado (php -S 127.0.0.1:8080 -t public).
 */
final class ApiFlowTest extends TestCase
{
    private const API = 'http://127.0.0.1:8080/api';

    private static function disponible(): bool
    {
        $r = self::http('GET', '/health');

        return $r['status'] === 200;
    }

    /** @return array{status:int, data:mixed, error:?string} */
    private static function http(string $metodo, string $ruta, ?array $body = null, ?string $token = null): array
    {
        $ch = curl_init(self::API . $ruta);
        $headers = ['Content-Type: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $respuesta = (string) curl_exec($ch);
        curl_close($ch);

        $json = json_decode($respuesta, true);

        return is_array($json)
            ? ['status' => (int) ($json['status'] ?? 0), 'data' => $json['data'] ?? null, 'error' => $json['error'] ?? null]
            : ['status' => 0, 'data' => null, 'error' => 'respuesta ilegible'];
    }

    protected function setUp(): void
    {
        if (! self::disponible()) {
            $this->markTestSkipped('API no disponible en 127.0.0.1:8080');
        }
    }

    public function testLoginDemo(): void
    {
        $r = self::http('POST', '/login', ['dni' => '99000001', 'clave' => 'Demo2026!']);
        $this->assertSame(200, $r['status']);
        $this->assertNotEmpty($r['data']['token']);
        $this->assertSame('99000001', $r['data']['deportista']['dni']);
    }

    public function testLoginClaveIncorrecta(): void
    {
        $r = self::http('POST', '/login', ['dni' => '99000001', 'clave' => 'Incorrecta1']);
        $this->assertSame(401, $r['status']);
    }

    public function testDniDeLaBaseSeRechaza(): void
    {
        $r = self::http('POST', '/registro/validar', ['dni' => '45904146']);
        $this->assertSame(200, $r['status']);
        $this->assertFalse($r['data']['disponible']);
        $this->assertSame('dni_en_base', $r['data']['motivo']);
    }

    public function testRegistroFlujoCompleto(): void
    {
        $dni = (string) random_int(80000000, 89999999);
        $base = [
            'dni' => $dni, 'nombre' => 'Test', 'apellido' => 'Automatizado',
            'fecha_nacimiento' => '1995-07-07', 'calle' => '9 De Julio', 'numero' => '500',
            'localidad' => 'Tortuguitas', 'celular' => '11 7777-8888', 'email' => 'test@demo.test',
        ];

        $r1 = self::http('POST', '/registro/validar', ['dni' => $dni]);
        $this->assertTrue($r1['data']['disponible']);

        $r2 = self::http('POST', '/registro/paso1', $base);
        $this->assertSame(200, $r2['status']);
        $this->assertSame('verificar', $r2['data']['etapa']);

        // Modo local: el código viene en la respuesta.
        $codigo = $r2['data']['codigo_demo'] ?? null;
        if ($codigo === null) {
            $dev = self::http('GET', "/dev/sms/{$dni}/ultimo-codigo");
            $codigo = $dev['data']['codigo'] ?? null;
        }
        $this->assertNotNull($codigo, 'Debe haber un código SMS (modo demo)');

        $r3 = self::http('POST', '/registro/verificar', ['dni' => $dni, 'codigo' => $codigo]);
        $this->assertSame(200, $r3['status']);

        $r4 = self::http('POST', '/registro/clave', [
            'dni' => $dni, 'clave' => 'Clave2026!', 'clave_confirmacion' => 'Clave2026!',
        ]);
        $this->assertSame(201, $r4['status']);

        $r5 = self::http('POST', '/login', ['dni' => $dni, 'clave' => 'Clave2026!']);
        $this->assertSame(200, $r5['status']);

        // El DNI es inmutable.
        $r6 = self::http('PUT', '/me', ['dni' => '11111111', 'nombre' => 'Hack'], $r5['data']['token']);
        $this->assertSame(422, $r6['status']);
    }

    public function testActividadesYAsistenciasDelDemo(): void
    {
        $login = self::http('POST', '/login', ['dni' => '99000001', 'clave' => 'Demo2026!']);
        $token = $login['data']['token'];

        $r = self::http('GET', '/actividades', null, $token);
        $this->assertSame(200, $r['status']);
        $this->assertSame('Palmeras', $r['data']['polideportivo']);
        $this->assertNotEmpty($r['data']['actividades']);

        $cat = $r['data']['actividades'][0]['id_categoria'];
        $a = self::http('GET', "/actividades/{$cat}/asistencias", null, $token);
        $this->assertSame(200, $a['status']);
        $this->assertArrayHasKey('pct', $a['data']['resumen']);
        $this->assertNotEmpty($a['data']['historial']);
    }

    public function testCarnetEsJpegConQr(): void
    {
        $login = self::http('POST', '/login', ['dni' => '99000001', 'clave' => 'Demo2026!']);
        $token = $login['data']['token'];

        $ch = curl_init(self::API . '/carnet');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $binario = (string) curl_exec($ch);
        curl_close($ch);

        $this->assertStringStartsWith("\xFF\xD8", $binario, 'El carnet debe ser JPEG');
        $this->assertGreaterThan(10000, strlen($binario));
    }

    public function testSinTokenDevuelve401(): void
    {
        $r = self::http('GET', '/me');
        $this->assertSame(401, $r['status']);
    }

    public function testRecuperarClave(): void
    {
        $r1 = self::http('POST', '/recuperar/solicitar', ['dni' => '99000003']);
        $this->assertSame(200, $r1['status']);
        // No revela si el DNI existe.
        $r2 = self::http('POST', '/recuperar/solicitar', ['dni' => '45904146']);
        $this->assertSame(200, $r2['status']);
    }
}
