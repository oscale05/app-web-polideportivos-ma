<?php

declare(strict_types=1);

namespace App;

use App\Support\JsonResponse;

final class Router
{
    /** @var array<string, list<array{patron:string, regex:string, handler:callable}>> */
    private array $rutas = [];

    public function add(string $metodo, string $patron, callable $handler): void
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $patron);
        $this->rutas[strtoupper($metodo)][] = [
            'patron'  => $patron,
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function get(string $patron, callable $handler): void
    {
        $this->add('GET', $patron, $handler);
    }

    public function post(string $patron, callable $handler): void
    {
        $this->add('POST', $patron, $handler);
    }

    public function put(string $patron, callable $handler): void
    {
        $this->add('PUT', $patron, $handler);
    }

    public function despachar(string $metodo, string $ruta): void
    {
        $metodo = strtoupper($metodo);
        foreach ($this->rutas[$metodo] ?? [] as $rutaDef) {
            if (preg_match($rutaDef['regex'], $ruta, $coincidencias) === 1) {
                $params = array_filter(
                    $coincidencias,
                    static fn (string|int $k): bool => is_string($k),
                    ARRAY_FILTER_USE_KEY
                );
                ($rutaDef['handler'])($params);

                return;
            }
        }

        JsonResponse::error('Ruta no encontrada', 404);
    }

    public function cors(string $origenPermitido): void
    {
        header('Access-Control-Allow-Origin: ' . $origenPermitido);
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Vary: Origin');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
