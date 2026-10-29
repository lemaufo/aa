<?php

namespace App\Services;

use App\Services\Firebird\Microsip;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Sucursales que se pueden asignar a un usuario. Con Microsip son sus almacenes (ALMACENES), sin los que
 * no son sucursal (aa.almacenes_no_sucursal); en demo, o si Microsip no responde, las de configuración.
 */
class Sucursales
{
    /** @return list<string> */
    public function nombres(): array
    {
        if (config('aa.origen') !== 'firebird') {
            return config('aa.sucursales');
        }

        try {
            $fuera = array_map('intval', (array) config('aa.almacenes_no_sucursal')) ?: [0];

            return Cache::remember('aa:sucursales-microsip', now()->addMinutes(10), fn () => array_map(
                fn ($a) => Microsip::fila($a)['nombre'],
                Microsip::db()->select(
                    "select nombre from almacenes
                      where coalesce(oculto, 'N') <> 'S' and almacen_id not in (".implode(', ', array_fill(0, count($fuera), '?')).')
                      order by nombre',
                    $fuera
                )
            ));
        } catch (Throwable $e) {
            report($e);

            return config('aa.sucursales');
        }
    }
}
