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
            $fuera = $this->idsFuera();

            return array_values(array_diff($this->almacenes(), $this->almacenes($fuera)));
        } catch (Throwable $e) {
            report($e);

            return config('aa.sucursales');
        }
    }

    /**
     * Nombres de los almacenes que no son sucursal (CEDIS, producción, materia prima, tránsito), para quitarlos
     * también de lo que ya traen pedidos y usuarios guardados.
     *
     * @return list<string>
     */
    public function noSucursales(): array
    {
        if (config('aa.origen') !== 'firebird') {
            return [];
        }

        try {
            return $this->almacenes($this->idsFuera());
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /** @return list<int> */
    private function idsFuera(): array
    {
        return array_map('intval', (array) config('aa.almacenes_no_sucursal'));
    }

    /**
     * Nombres de almacenes visibles; con ids, solo esos. La clave de caché lleva los ids: si cambia la
     * configuración no se queda la lista vieja.
     *
     * @param  list<int>|null  $ids
     * @return list<string>
     */
    private function almacenes(?array $ids = null): array
    {
        if ($ids === []) {
            return [];
        }

        return Cache::remember('aa:almacenes-microsip:'.implode(',', $ids ?? ['todos']), now()->addMinutes(10), fn () => array_map(
            fn ($a) => Microsip::fila($a)['nombre'],
            Microsip::db()->select(
                "select nombre from almacenes where coalesce(oculto, 'N') <> 'S'"
                .($ids === null ? '' : ' and almacen_id in ('.implode(', ', array_fill(0, count($ids), '?')).')')
                .' order by nombre',
                $ids ?? []
            )
        ));
    }
}
