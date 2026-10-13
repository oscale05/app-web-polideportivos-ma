<?php

declare(strict_types=1);

namespace App\Support\Sms;

interface SmsSenderInterface
{
    public function enviar(string $destino, string $mensaje, string $proposito, ?string $dni = null): void;
}
