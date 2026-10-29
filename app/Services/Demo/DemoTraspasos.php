<?php

namespace App\Services\Demo;

use App\Contracts\TraspasosExpertos;
use Illuminate\Support\Facades\DB;

class DemoTraspasos implements TraspasosExpertos
{
    public function estaCancelado(string $folio): bool
    {
        return (bool) DB::table('demo_traspasos')->where('folio', $folio)->value('cancelado');
    }

    /** En demo los pedidos ya vienen del seeder; no hay traspasos que dar de alta. */
    public function leer(string $folio): ?array
    {
        return null;
    }

    public function deMicrosipDesde(string $fecha): array
    {
        return [];
    }
}