<?php

declare(strict_types=1);

/**
 * Front controller de la API. Todas las rutas /api/* se resuelven acá.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Controllers\ActividadesController;
use App\Controllers\AuthController;
use App\Controllers\CallesController;
use App\Controllers\CarnetController;
use App\Controllers\DeportistaController;
use App\Controllers\ActividadesController as ActCtrl;
use App\Router;
use App\Support\JsonResponse;

Config::load();

$router = new Router();

// CORS: se permite el origen configurado (dev Angular y prod).
$origenSolicitado = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$permitidos = array_values(array_filter([
    Config::get('APP_URL'),
    'http://localhost:4200',
    'http://127.0.0.1:4200',
]));
$origen = in_array($origenSolicitado, $permitidos, true) ? $origenSolicitado : ($permitidos[0] ?? '*');
$router->cors($origen === '' ? '*' : $origen);

$ruta = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$metodo = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

// En producción, servir el build de Angular si existe.
$dist = dirname(__DIR__, 2) . '/frontend/dist/frontend/browser';
if (! str_starts_with($ruta, '/api') && is_dir($dist)) {
    $archivo = $dist . ($ruta === '/' ? '/index.html' : $ruta);
    if (is_file($archivo)) {
        $tipo = match (pathinfo($archivo, PATHINFO_EXTENSION)) {
            'html'  => 'text/html; charset=utf-8',
            'js'    => 'application/javascript',
            'css'   => 'text/css',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'ico'   => 'image/x-icon',
            'json'  => 'application/json',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $tipo);
        readfile($archivo);
        exit;
    }
    if (! str_contains($ruta, '.')) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($dist . '/index.html');
        exit;
    }
}

if (str_starts_with($ruta, '/api')) {
    $ruta = substr($ruta, 4) === '' ? '/' : substr($ruta, 4);
}

// --- Salud ---
$router->get('/health', static function (): never {
    JsonResponse::ok('ok');
});

// --- Auth / registro ---
$router->post('/registro/validar', [AuthController::class, 'validar']);
$router->post('/registro/paso1', [AuthController::class, 'paso1']);
$router->post('/registro/verificar', [AuthController::class, 'verificar']);
$router->post('/registro/clave', [AuthController::class, 'clave']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->post('/recuperar/solicitar', [AuthController::class, 'recuperarSolicitar']);
$router->post('/recuperar/verificar', [AuthController::class, 'recuperarVerificar']);

// --- Deportista ---
$router->get('/me', [DeportistaController::class, 'me']);
$router->put('/me', [DeportistaController::class, 'actualizar']);
$router->post('/me/celular/solicitar', [DeportistaController::class, 'celularSolicitar']);
$router->post('/me/celular/verificar', [DeportistaController::class, 'celularVerificar']);
$router->post('/me/celular/cancelar', [DeportistaController::class, 'celularCancelar']);
$router->post('/me/foto', [DeportistaController::class, 'fotoSubir']);
$router->get('/me/foto', [DeportistaController::class, 'fotoObtener']);

// --- Dominio ---
$router->get('/actividades', [ActividadesController::class, 'index']);
$router->get('/actividades/{id}/asistencias', [ActividadesController::class, 'asistencias']);
$router->get('/carnet', [CarnetController::class, 'index']);
$router->get('/calles', [CallesController::class, 'buscar']);

// --- Demo / desarrollo ---
$router->get('/dev/sms/ultimo-codigo', [ActCtrl::class, 'smsDemo']);
$router->get('/dev/sms/{dni}/ultimo-codigo', [ActCtrl::class, 'smsDemo']);

try {
    $router->despachar($metodo, $ruta);
} catch (Throwable $e) {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    JsonResponse::error('Error interno', 500);
}
