<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Services\QrService;
use App\Support\JsonResponse;

final class CarnetController
{
    public static function index(): never
    {
        $deportista = Auth::requerir();

        try {
            $ruta = QrService::carnet($deportista['dni']);
        } catch (\RuntimeException $e) {
            JsonResponse::error('No se pudo generar el carnet', 500, ['detalle' => $e->getMessage()]);
        }

        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=60');
        readfile($ruta);
        exit;
    }
}
