<?php

namespace App\Services\Firebird;

use App\Contracts\VentasMicrosip;
use RuntimeException;

/**
 * Ticket de venta de mostrador en Microsip Punto de Venta (DOCTOS_PV / DOCTOS_PV_DET).
 * TIPO_DOCTO: V = ticket, F = factura de mostrador. ESTATUS: N = normal, C = cancelado.
 * APLICADO = 'S' cuando el documento ya quedó cobrado y afectó inventario.
 * La entrega vive en LIBRES_VTA_PV.ENTREGADO (S/N), la misma que usa "Control de Entregas".
 */
class FirebirdVentas implements VentasMicrosip
{
    public function consultar(string $folio): ?array
    {
        $docto = $this->docto($folio);

        if ($docto === null) {
            return null;
        }

        // ROL: N = artículo normal, J = juego, C = componente del juego (no cuenta como línea aparte).
        $lineas = array_map(Microsip::fila(...), Microsip::db()->select(
            "select d.articulo_id, a.nombre, coalesce(d.unidades, 0) as unidades
               from doctos_pv_det d join articulos a on a.articulo_id = d.articulo_id
              where d.docto_pv_id = ? and d.rol <> 'C'
              order by d.posicion, d.docto_pv_det_id",
            [$docto['docto_pv_id']]
        ));

        $principal = $lineas[0] ?? null;

        return [
            'folio' => $docto['folio'],
            'estado' => match (true) {
                $docto['estatus'] === 'C' => 'cancelada',
                $docto['aplicado'] !== 'S' => 'pendiente',
                default => 'pagada',
            },
            'articulo' => $principal['nombre'] ?? '',
            // Si el artículo se capturó en varios renglones, cuenta el total.
            'cantidad' => $principal === null ? 0.0 : (float) array_sum(array_map(
                fn ($l) => (float) $l['unidades'],
                array_filter($lineas, fn ($l) => $l['articulo_id'] === $principal['articulo_id'])
            )),
            'lineas_ajenas' => count(array_filter($lineas, fn ($l) => $l['articulo_id'] !== $principal['articulo_id'])),
            'entregada' => $docto['entregado'] === 'S',
        ];
    }

    /**
     * Igual que "Control de Entregas": LIBRES_VTA_PV.ENTREGADO = 'S' (datos adicionales de la venta).
     * Es lo único que la app escribe en Microsip; la tabla no tiene triggers. Si ya estaba entregada
     * no se toca. Con el usuario AA_APP basta: GRANT SELECT ... y GRANT UPDATE (ENTREGADO) ON LIBRES_VTA_PV.
     */
    public function marcarEntregada(string $folio, string $usuario, ?string $recibe): void
    {
        $docto = $this->docto($folio)
            ?? throw new RuntimeException("El ticket {$folio} no existe en Microsip.");

        if ($docto['estatus'] === 'C') {
            throw new RuntimeException("El ticket {$docto['folio']} está cancelado en Microsip; no se marca como entregado.");
        }

        if ($docto['entregado'] === 'S') {
            return;
        }

        $filas = Microsip::db()->update(
            "update libres_vta_pv set entregado = 'S' where docto_pv_id = ? and coalesce(entregado, 'N') <> 'S'",
            [$docto['docto_pv_id']]
        );

        if ($filas === 0 && $docto['entregado'] === null) {
            throw new RuntimeException("El ticket {$docto['folio']} no tiene datos de entrega en Microsip (LIBRES_VTA_PV); márcalo desde Control de Entregas.");
        }
    }

    /** El índice DOCTOS_PV_AK1 (TIPO_DOCTO, FOLIO, APLICADO) hace esta búsqueda inmediata. */
    private function docto(string $folio): ?array
    {
        return Microsip::fila(Microsip::db()->selectOne(
            "select first 1 p.docto_pv_id, p.folio, p.estatus, p.aplicado, l.entregado
               from doctos_pv p left join libres_vta_pv l on l.docto_pv_id = p.docto_pv_id
              where p.tipo_docto in ('V', 'F') and p.folio = ?
              order by case p.aplicado when 'S' then 0 else 1 end, p.docto_pv_id desc",
            [Microsip::folio($folio)]
        ));
    }
}
