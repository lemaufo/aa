<?php

namespace App\Services;

use App\Models\Pedido;
use App\Services\Firebird\FirebirdArticulos;
use Throwable;

/**
 * Peso por metro del pedido según el catálogo de artículos de Microsip (PESO_UNITARIO).
 * Al dar de alta el pedido se copia del traspaso; antes de imprimir la etiqueta se vuelve a leer, para que
 * una corrección en el catálogo llegue a los pedidos que todavía no se producen. Después de imprimir queda
 * fijo: es el peso que trae la etiqueta y contra el que se valida la báscula.
 */
class PesoCatalogo
{
    public function __construct(private FirebirdArticulos $articulos) {}

    /**
     * Actualiza peso_por_metro (sin guardar). Devuelve el texto para la bitácora si cambió, o null.
     * Si Microsip no contesta se queda el peso que ya tenía el pedido.
     */
    public function actualizar(Pedido $pedido): ?string
    {
        if (config('aa.origen') !== 'firebird') {
            return null;
        }

        try {
            $peso = $this->articulos->pesoUnitario($pedido->articulo_clave, $pedido->material);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($peso === null || abs($peso - (float) $pedido->peso_por_metro) < 0.0005) {
            return null;
        }

        $antes = (float) $pedido->peso_por_metro;
        $pedido->peso_por_metro = $peso;

        return 'Peso por metro actualizado del catálogo de Microsip: '.number_format($antes, 3).' → '.number_format($peso, 3).' kg';
    }
}
